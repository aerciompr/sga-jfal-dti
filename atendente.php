<?php
/**
 * PAINEL DE CHAMADA DO PERITO - JFAL
 * 
 * atendente.php: Interface utilizada pelos peritos médicos do Tribunal para chamar pacientes.
 * Funcionalidades:
 * - Acesso autenticado com autorização do profissional.
 * - Confirmação e trava obrigatória de sala de atendimento no primeiro uso do dia (anti-conflito).
 * - Ações de controle da chamada: Chamar Próximo, Chamar Novamente, Iniciar, Concluir.
 * - Tolerância inteligente: Paciente ausente desce 2 posições na fila.
 * - Registro expresso de desistência (periciado que foi embora).
 * - Busca instantânea e filtros por situação na pauta (Aguardando, Pendentes, Concluídos, Desistentes, Todos).
 * - Atualização em tempo real da fila via polling assíncrono.
 */

require_once __DIR__ . '/banco.php';
require_once __DIR__ . '/atendimento_service.php';

// Proteção de Acesso: Apenas peritos e administradores podem acessar o painel do perito
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'perito'])) {
    header("Location: dashboard.php");
    exit;
}

$is_logged_in = true;
$user_name = $_SESSION['usuario_name'];
$user_role = $_SESSION['usuario_role'];
$ownerId = (int)$_SESSION['usuario_id'];
$ownerAdmin = $user_role === 'admin' ? 1 : 0;

// BYOD Inteligência: Detecção de troca de dia com tampa de notebook fechada
$hoje = date('Y-m-d');
if (isset($_SESSION['data_sessao']) && $_SESSION['data_sessao'] !== $hoje) {
    $_SESSION['data_sessao'] = $hoje;
    unset($_SESSION['sala'], $_SESSION['perito_selecionado']);
    if ($user_role === 'perito' && multisubsecaoAtiva($pdo)) {
        try {
            $stmtSub = $pdo->prepare("SELECT DISTINCT subsecao_id FROM atendimentos WHERE (perito_id = ? OR perito = ?) AND data_pauta = CURRENT_DATE() AND subsecao_id IS NOT NULL");
            $stmtSub->execute([$ownerId, $_SESSION['usuario_nome'] ?? $_SESSION['usuario_name']]);
            $subsHoje = $stmtSub->fetchAll(PDO::FETCH_COLUMN);
            if (count($subsHoje) === 1) {
                $_SESSION['subsecao_id_ativa'] = (int)$subsHoje[0];
            }
        } catch (Throwable $e) {}
    }
}

$subsecaoId = subsecaoAtivaId($pdo);
$multiAtiva = multisubsecaoAtiva($pdo);
$temSubAtend = colunaExiste($pdo, 'atendimentos', 'subsecao_id');
$temSubSalas = colunaExiste($pdo, 'salas', 'subsecao_id');
$temSubSalasPeritos = colunaExiste($pdo, 'salas_peritos', 'subsecao_id');

// Consulta peritos escalados hoje
if ($temSubAtend && $multiAtiva) {
    $stmtPeritos = $pdo->prepare("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != '' AND data_pauta = CURRENT_DATE() AND subsecao_id = ? ORDER BY perito ASC");
    $stmtPeritos->execute([$subsecaoId]);
    $peritos_lista = $stmtPeritos->fetchAll(PDO::FETCH_COLUMN);
} else {
    $peritos_lista = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != '' AND data_pauta = CURRENT_DATE() ORDER BY perito ASC")->fetchAll(PDO::FETCH_COLUMN);
}

// Carrega ou altera o nome do perito selecionado
if ($user_role === 'perito') {
    $nome_cadastro = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_name'];
    $perito_selecionado = $nome_cadastro;
    // Se o nome na pauta diferir sutilmente em caixa alta/espaços, alinha com o nome canônico da pauta
    foreach ($peritos_lista as $pl) {
        if (mb_strtolower(trim($pl)) === mb_strtolower(trim($nome_cadastro))) {
            $perito_selecionado = $pl;
            break;
        }
    }
    $_SESSION['perito_selecionado'] = $perito_selecionado;
} else {
    $perito_selecionado = $_SESSION['perito_selecionado'] ?? '';
    if (isset($_POST['set_perito'])) {
        $perito_selecionado = trim($_POST['perito_selecionado'] ?? '');
        $_SESSION['perito_selecionado'] = $perito_selecionado;
        unset($_SESSION['sala']); // Limpa sala em sessão ao trocar perito
    }
    // Administrador: se nenhum perito foi escolhido na sessão, espelha o perito ativo hoje
    if (empty($perito_selecionado) && !empty($peritos_lista)) {
        if ($temSubAtend && $multiAtiva) {
            $stmtAtivoHoje = $pdo->prepare("SELECT DISTINCT perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND perito IS NOT NULL AND perito != '' AND subsecao_id = ? LIMIT 1");
            $stmtAtivoHoje->execute([$subsecaoId]);
        } else {
            $stmtAtivoHoje = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND perito IS NOT NULL AND perito != '' LIMIT 1");
        }
        $peritoAtivoHoje = $stmtAtivoHoje->fetchColumn();
        if ($peritoAtivoHoje && in_array($peritoAtivoHoje, $peritos_lista, true)) {
            $perito_selecionado = (string)$peritoAtivoHoje;
        } else {
            if ($temSubSalasPeritos && $multiAtiva) {
                $stmtSalaHoje = $pdo->prepare("SELECT DISTINCT perito FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE() AND subsecao_id = ? LIMIT 1");
                $stmtSalaHoje->execute([$subsecaoId]);
            } else {
                $stmtSalaHoje = $pdo->query("SELECT DISTINCT perito FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE() LIMIT 1");
            }
            $peritoSalaHoje = $stmtSalaHoje->fetchColumn();
            if ($peritoSalaHoje && in_array($peritoSalaHoje, $peritos_lista, true)) {
                $perito_selecionado = (string)$peritoSalaHoje;
            } else {
                $perito_selecionado = $peritos_lista[0];
            }
        }
        $_SESSION['perito_selecionado'] = $perito_selecionado;
    }
}

// Consulta todas as salas cadastradas no banco de dados para a escolha do perito
if ($temSubSalas && $multiAtiva) {
    $stmtSalas = $pdo->prepare("SELECT nome FROM salas WHERE subsecao_id = ? ORDER BY nome ASC");
    $stmtSalas->execute([$subsecaoId]);
    $salas_lista = $stmtSalas->fetchAll(PDO::FETCH_COLUMN);
} else {
    $salas_lista = $pdo->query("SELECT nome FROM salas ORDER BY nome ASC")->fetchAll(PDO::FETCH_COLUMN);
}
if (empty($salas_lista)) {
    $salas_lista = ['Sala 1', 'Sala 2', 'Sala 3', 'Sala 4'];
}

// Verifica se este perito já confirmou/travou sala hoje (ou possui atendimento ativo)
$sala_confirmada = false;
$sala = $_SESSION['sala'] ?? '';
if ($perito_selecionado) {
    // 1. Busca em salas_peritos (trava de hoje)
    if ($temSubSalasPeritos && $multiAtiva) {
        $stmtSalaConf = $pdo->prepare("SELECT sala FROM salas_peritos WHERE LOWER(TRIM(perito)) = LOWER(TRIM(?)) AND DATE(atualizado_em) = CURRENT_DATE() AND subsecao_id = ?");
        $stmtSalaConf->execute([$perito_selecionado, $subsecaoId]);
    } else {
        $stmtSalaConf = $pdo->prepare("SELECT sala FROM salas_peritos WHERE LOWER(TRIM(perito)) = LOWER(TRIM(?)) AND DATE(atualizado_em) = CURRENT_DATE()");
        $stmtSalaConf->execute([$perito_selecionado]);
    }
    $salaSalvaHoje = $stmtSalaConf->fetchColumn();

    // 2. Se não achou por match direto, busca por nome simplificado sem acentos
    if (!$salaSalvaHoje && function_exists('simplifyName')) {
        $simpAlvo = simplifyName($perito_selecionado);
        $stmtAllSalas = ($temSubSalasPeritos && $multiAtiva)
            ? $pdo->prepare("SELECT perito, sala FROM salas_peritos WHERE subsecao_id = ? AND DATE(atualizado_em) = CURRENT_DATE()")
            : $pdo->prepare("SELECT perito, sala FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE()");
        if ($temSubSalasPeritos && $multiAtiva) $stmtAllSalas->execute([$subsecaoId]); else $stmtAllSalas->execute();
        while ($r = $stmtAllSalas->fetch(PDO::FETCH_ASSOC)) {
            if (simplifyName((string)$r['perito']) === $simpAlvo) {
                $salaSalvaHoje = $r['sala'];
                break;
            }
        }
    }

    // 3. Se não achou por nome exato em salas_peritos, busca por perito_id no cadastro de usuários
    if (!$salaSalvaHoje) {
        $pId = peritoId($pdo, $perito_selecionado);
        if ($pId) {
            $stmtUserPerito = $pdo->prepare("SELECT username, nome FROM usuarios WHERE id = ?");
            $stmtUserPerito->execute([$pId]);
            $uP = $stmtUserPerito->fetch(PDO::FETCH_ASSOC);
            if ($uP) {
                if ($temSubSalasPeritos && $multiAtiva) {
                    $stmtSalaConfUser = $pdo->prepare("SELECT sala FROM salas_peritos WHERE (LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND DATE(atualizado_em) = CURRENT_DATE() AND subsecao_id = ?");
                    $stmtSalaConfUser->execute([$uP['username'], $uP['nome'] ?? '', $subsecaoId]);
                } else {
                    $stmtSalaConfUser = $pdo->prepare("SELECT sala FROM salas_peritos WHERE (LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND DATE(atualizado_em) = CURRENT_DATE()");
                    $stmtSalaConfUser->execute([$uP['username'], $uP['nome'] ?? '']);
                }
                $salaSalvaHoje = $stmtSalaConfUser->fetchColumn();
            }
        }
    }

    // 4. Se ainda não achou em salas_peritos, verifica se o perito já tem atendimento ATIVO neste momento
    if (!$salaSalvaHoje) {
        $pId = peritoId($pdo, $perito_selecionado);
        if ($temSubAtend && $multiAtiva) {
            $stmtSalaAtend = $pdo->prepare("SELECT sala FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (perito = ? OR (? IS NOT NULL AND perito_id = ?)) AND sala IS NOT NULL AND sala != '' AND status IN ('chamada', 'atendimento') AND subsecao_id = ? ORDER BY id DESC LIMIT 1");
            $stmtSalaAtend->execute([$perito_selecionado, $pId, $pId, $subsecaoId]);
        } else {
            $stmtSalaAtend = $pdo->prepare("SELECT sala FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (perito = ? OR (? IS NOT NULL AND perito_id = ?)) AND sala IS NOT NULL AND sala != '' AND status IN ('chamada', 'atendimento') ORDER BY id DESC LIMIT 1");
            $stmtSalaAtend->execute([$perito_selecionado, $pId, $pId]);
        }
        $salaSalvaHoje = $stmtSalaAtend->fetchColumn();
    }

    if ($salaSalvaHoje) {
        $sala = (string)$salaSalvaHoje;
        $_SESSION['sala'] = $sala;
        $sala_confirmada = true;
    } else {
        $sala_confirmada = false;
        unset($_SESSION['sala']);
    }
}
if (!$sala || !in_array($sala, $salas_lista, true)) {
    $sala = $salas_lista[0] ?? 'Sala 1';
}

// Ações de Trava e Liberação de Sala
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_sala_trava'])) {
    try {
        $sala_post = trim($_POST['sala'] ?? '');
        if (!in_array($sala_post, $salas_lista, true)) throw new DomainException('Sala inválida.');
        travarSalaPerito($pdo, $perito_selecionado, $sala_post, $subsecaoId);
        $_SESSION['sala'] = $sala_post;
        $_SESSION['flash_atendimento'] = ["Sala {$sala_post} confirmada e travada com sucesso para seus atendimentos hoje.", 'success'];
    } catch (DomainException $e) {
        $_SESSION['flash_atendimento'] = [$e->getMessage(), 'error'];
    } catch (Throwable $e) {
        $_SESSION['flash_atendimento'] = [erroPublico($e), 'error'];
    }
    header('Location: atendente.php', true, 303); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['liberar_sala'])) {
    try {
        liberarSalaPerito($pdo, $perito_selecionado, $sala, $subsecaoId);
        unset($_SESSION['sala']);
        $_SESSION['flash_atendimento'] = ["Sala {$sala} liberada. Selecione uma nova sala para continuar.", 'info'];
    } catch (DomainException $e) {
        $_SESSION['flash_atendimento'] = [$e->getMessage(), 'error'];
    } catch (Throwable $e) {
        $_SESSION['flash_atendimento'] = [erroPublico($e), 'error'];
    }
    header('Location: atendente.php', true, 303); exit;
}

// Consulta salas ocupadas hoje por outros médicos
$salas_ocupadas = obterSalasOcupadasHoje($pdo, $perito_selecionado, $subsecaoId);

// Ações de Chamada e Fluxo do Atendimento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if (!$sala_confirmada && in_array($_POST['action'], ['chamar_proximo', 'chamar_especifico', 'chamar_avulsa'], true)) {
            throw new DomainException('Por favor, confirme e trave sua sala de atendimento antes de chamar o primeiro paciente.');
        }
        $message = agirAtendimento($pdo, (string)$_POST['action'], $perito_selecionado, $sala, $_POST, $subsecaoId);
        $message_type = 'success';
    } catch (DomainException $e) {
        $message = $e->getMessage(); $message_type = 'error';
    } catch (Throwable $e) {
        $message = erroPublico($e); $message_type = 'error';
    }
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $message_type === 'success',
            'message' => $message,
            'message_type' => $message_type,
            'csrf_token' => csrfToken(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['flash_atendimento'] = [$message, $message_type];
    header('Location: atendente.php', true, 303); exit;
}

[$message, $message_type] = $_SESSION['flash_atendimento'] ?? ['', ''];
unset($_SESSION['flash_atendimento']);

// Consulta se o perito possui agendamentos hoje
$temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
$colHora = $temHora ? ", hora_pericia" : "";
$temPrioridade = colunaExiste($pdo, 'atendimentos', 'prioridade');
$colPrioridade = $temPrioridade ? ", prioridade" : "";

$clausulaPerito = ($user_role === 'admin') ? "perito = ?" : "(perito_id = ? OR perito = ?)";
$paramsPerito = ($user_role === 'admin') ? [$perito_selecionado] : [$ownerId, $perito_selecionado];

$clausulaSub = ($temSubAtend && $multiAtiva) ? " AND subsecao_id = ?" : "";
$paramsSub = ($temSubAtend && $multiAtiva) ? [$subsecaoId] : [];

$criterio_fila = $perito_selecionado !== '' ? obterCriterioFilaPerito($pdo, $perito_selecionado) : 'chegada';
$atende_prioridade = $perito_selecionado !== '' ? obterAtenderPrioridadePerito($pdo, $perito_selecionado) : false;

$nome_nao_encontrado_pauta = false;
if ($user_role === 'perito' && $perito_selecionado) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND $clausulaPerito" . $clausulaSub);
    $stmt->execute(array_merge($paramsPerito, $paramsSub));
    if ($stmt->fetchColumn() == 0) {
        $nome_nao_encontrado_pauta = true;
    }
}

// Atendimento atual nesta sala/médico
$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND $clausulaPerito" . $clausulaSub . " ORDER BY chamado_em DESC LIMIT 1");
$stmt->execute(array_merge([$sala], $paramsPerito, $paramsSub));
$atendimento_atual = $stmt->fetch();

// Ordenação da fila (idêntica à api_perito_fila.php)
$orderSecundario = ($criterio_fila === 'horario' && $temHora)
    ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC"
    : "fila_em ASC, id ASC";

$orderFilaPresentes = ($atende_prioridade && $temPrioridade)
    ? "CASE WHEN prioridade = 'superprioridade' THEN 1 WHEN prioridade = 'prioridade' THEN 2 ELSE 3 END ASC, " . $orderSecundario
    : $orderSecundario;

$orderClausula = "ORDER BY CASE WHEN status = 'presente' THEN 1 WHEN status IN ('chamada','atendimento') THEN 2 WHEN status = 'agendado' THEN 3 WHEN status = 'concluido' THEN 4 ELSE 5 END, " . $orderFilaPresentes;

// Contadores e Pauta completa do perito hoje
$todos_agendamentos = [];
$cont_aguardando = 0;
$cont_pendentes = 0;
$cont_concluidos = 0;
$cont_desistentes = 0;
$cont_prioritarios = 0;
$filaFormatada = [];
$todosFormatados = [];

if ($perito_selecionado !== '') {
    $stmtTodos = $pdo->prepare("
        SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora $colPrioridade 
        FROM atendimentos 
        WHERE data_pauta = CURRENT_DATE() AND $clausulaPerito" . $clausulaSub . "
        $orderClausula
    ");
    $stmtTodos->execute(array_merge($paramsPerito, $paramsSub));
    $todos_agendamentos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

    $posFila = 1;
    foreach ($todos_agendamentos as $p) {
        $st = (string)$p['status'];
        $prio = (string)($p['prioridade'] ?? 'normal');
        if ($st === 'presente' && in_array($prio, ['prioridade', 'superprioridade'], true)) $cont_prioritarios++;
        if ($st === 'presente') $cont_aguardando++;
        elseif ($st === 'agendado') $cont_pendentes++;
        elseif ($st === 'concluido') $cont_concluidos++;
        elseif ($st === 'desistente') $cont_desistentes++;

        $posAtual = null;
        if ($st === 'presente') {
            $posAtual = $posFila;
            $posFila++;
        }

        $horaFormatada = (!empty($p['hora_pericia'])) ? date('H:i', strtotime($p['hora_pericia'])) : null;

        $itemFormatado = [
            'id' => (int)$p['id'],
            'senha' => (string)($p['senha'] ?: '---'),
            'nome' => (string)($p['nome'] ?: 'Sem Nome'),
            'processo' => (string)($p['processo'] ?: 'Sem processo'),
            'cpf' => (string)($p['cpf'] ?? ''),
            'status' => $st,
            'sala' => (string)($p['sala'] ?? ''),
            'posicao_fila' => $posAtual,
            'prioridade' => $prio,
            'hora_pericia' => $horaFormatada,
            'chamadas_count' => (int)$p['chamadas_count'],
            'chegada' => !empty($p['chegada_em']) ? date('H:i:s', strtotime($p['chegada_em'])) : '--:--:--',
            'chamado' => !empty($p['chamado_em']) ? date('H:i:s', strtotime($p['chamado_em'])) : '--:--:--',
            'concluido' => !empty($p['finalizado_em']) ? date('H:i:s', strtotime($p['finalizado_em'])) : '--:--:--',
        ];

        $todosFormatados[] = $itemFormatado;
        if ($st === 'presente') {
            $filaFormatada[] = $itemFormatado;
        }
    }
}

$fila_espera = $cont_aguardando;

$dados_iniciais_json = [
    'success' => true,
    'criterio_fila' => $criterio_fila,
    'atender_prioridade' => $atende_prioridade,
    'total_aguardando' => $cont_aguardando,
    'totais' => [
        'aguardando' => $cont_aguardando,
        'pendentes' => $cont_pendentes,
        'atendidos' => $cont_concluidos,
        'desistentes' => $cont_desistentes,
        'prioritarios' => $cont_prioritarios,
        'total' => count($todosFormatados),
    ],
    'fila' => $filaFormatada,
    'todos' => $todosFormatados,
    'atendimento_atual_id' => $atendimento_atual ? (int)$atendimento_atual['id'] : 0,
    'atendimento_atual' => $atendimento_atual ? [
        'id' => (int)$atendimento_atual['id'],
        'nome' => (string)$atendimento_atual['nome'],
        'processo' => (string)$atendimento_atual['processo'],
        'senha' => (string)$atendimento_atual['senha'],
        'status' => (string)$atendimento_atual['status'],
        'hora_pericia' => !empty($atendimento_atual['hora_pericia']) ? date('H:i', strtotime($atendimento_atual['hora_pericia'])) : null,
        'chamadas_count' => (int)$atendimento_atual['chamadas_count'],
        'prioridade' => (string)($atendimento_atual['prioridade'] ?? 'normal'),
    ] : null,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Perito - <?= escapar(defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal') ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
    <script src="tema.js?v=2.5"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">

    <!-- Container de Toasts de Notificação do Perito -->

    <div id="toast-container-perito" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none *:pointer-events-auto"></div>
    <?php require_once __DIR__ . '/header_menu.php'; ?>

    <main class="flex-1 w-full max-w-[1600px] mx-auto p-4 sm:p-6 lg:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Controles Lateral (Identificação, Trava de Sala e Contadores) -->
        <div class="space-y-6 order-2 md:order-1">
            <!-- Seleção de Nome do Perito -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Identificação do Perito</h2>
                <?php if ($user_role === 'perito'): ?>
                    <div class="bg-gray-950 p-4 rounded-xl border border-gray-800">
                        <span class="block text-xs text-gray-500 uppercase font-semibold">Perito Conectado</span>
                        <span class="text-sm font-bold text-blue-400 mt-1 block"><?= escapar($perito_selecionado) ?></span>
                        <span class="text-[10px] text-gray-500 mt-1 block">Acesso restrito ao seu perfil profissional.</span>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-3"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                        <input type="hidden" name="set_perito" value="1">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Selecione seu Nome</label>
                            <select name="perito_selecionado" onchange="this.form.submit()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Escolha o Perito --</option>
                                <?php foreach ($peritos_lista as $p): ?>
                                    <option value="<?= escapar($p) ?>" <?= $perito_selecionado === $p ? 'selected' : '' ?>>
                                        <?= escapar($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Seleção e Trava de Sala Obrigatória (Anti-Conflito) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider flex items-center gap-1.5">
                        <span>🚪</span> Sala de Atendimento
                    </h2>
                    <?php if ($sala_confirmada): ?>
                        <span class="bg-emerald-950 text-emerald-400 border border-emerald-900/60 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            🔒 Travada
                        </span>
                    <?php else: ?>
                        <span class="bg-amber-950 text-amber-400 border border-amber-900/60 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            ⚠️ Não confirmada
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($user_role === 'admin'): ?>
                    <?php if ($sala_confirmada): ?>
                        <div class="bg-gray-950 p-4 rounded-xl border border-blue-900/40 mb-2">
                            <span class="text-xs text-blue-400 block font-semibold flex items-center gap-1">
                                <span>👁️</span> Espelho da Sala do Perito:
                            </span>
                            <div class="text-2xl font-black text-blue-400 mt-1"><?= escapar($sala) ?></div>
                            <p class="text-[10px] text-gray-400 mt-1">Acompanhando em tempo real a sala confirmada para <strong><?= escapar($perito_selecionado) ?></strong>.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-950 p-4 rounded-xl border border-amber-900/40">
                            <span class="text-xs text-amber-400 block font-semibold flex items-center gap-1">
                                <span>⚠️</span> Sala não confirmada
                            </span>
                            <p class="text-xs text-gray-400 mt-1">O médico <strong><?= escapar($perito_selecionado) ?></strong> ainda não travou a sala de atendimento hoje.</p>
                        </div>
                    <?php endif; ?>
                <?php elseif ($sala_confirmada): ?>
                    <div class="bg-gray-950 p-4 rounded-xl border border-emerald-900/40 mb-4">
                        <span class="text-xs text-gray-400 block font-medium">Sua sala de perícia hoje:</span>
                        <div class="text-2xl font-black text-emerald-400 mt-1"><?= escapar($sala) ?></div>
                        <p class="text-[10px] text-gray-500 mt-1">Sala bloqueada para outros médicos enquanto você estiver atendendo.</p>
                    </div>

                    <form method="POST" onsubmit="return confirm('Deseja liberar a <?= escapar($sala) ?> para trocar de sala? Certifique-se de não haver perícia em andamento.');">
                        <?= csrfCampo() ?>
                        <input type="hidden" name="liberar_sala" value="1">
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-750 text-gray-300 border border-gray-700 text-xs font-semibold py-2 rounded-lg transition">
                            🔄 Liberar / Trocar de Sala
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="space-y-3">
                        <?= csrfCampo() ?>
                        <input type="hidden" name="set_sala_trava" value="1">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1 font-semibold">Escolha uma Sala Disponível</label>
                            <select name="sala" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <?php foreach ($salas_lista as $s_nome): ?>
                                    <?php 
                                    $ocupada = isset($salas_ocupadas[$s_nome]);
                                    $ocupante = $ocupada ? $salas_ocupadas[$s_nome]['perito'] : '';
                                    ?>
                                    <option value="<?= escapar($s_nome) ?>" <?= $ocupada ? 'disabled class="text-gray-500 bg-gray-900"' : '' ?> <?= $sala === $s_nome ? 'selected' : '' ?>>
                                        <?= escapar($s_nome) ?> <?= $ocupada ? " (🔒 Ocupada por {$ocupante})" : ' (Livre)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 rounded-lg transition shadow-md">
                            🔒 Confirmar e Travar Sala
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Contador da fila de espera -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl text-center">
                <h2 class="text-xs uppercase font-bold text-gray-400 tracking-widest mb-2">Pacientes Aguardando</h2>
                <div class="text-5xl font-black text-amber-500 my-4" id="contador-fila"><?= $fila_espera ?></div>
                <p class="text-xs text-gray-400">confirmados na recepção prontos para chamada</p>
            </div>

            <!-- Widget de Critérios e Priorização da Fila (Autonomia Médica) -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase text-gray-400 tracking-wider flex items-center gap-1.5">
                        <span>⚙️</span> Regras da Sua Fila
                    </h2>
                    <span id="criterio-aviso-sync" class="text-[10px] text-emerald-400 flex items-center gap-1 font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Sincronizada
                    </span>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1.5 tracking-wider">Critério de Ordenação</label>
                    <div class="grid grid-cols-2 gap-1 rounded-xl border border-gray-750 bg-gray-950 p-1" id="grupo-criterio-fila">
                        <button type="button" id="btn-crit-chegada" class="px-2 py-1.5 text-xs rounded-lg transition text-center <?= $criterio_fila === 'chegada' ? 'bg-blue-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white font-medium' ?>">
                            🏃 Chegada
                        </button>
                        <button type="button" id="btn-crit-horario" class="px-2 py-1.5 text-xs rounded-lg transition text-center <?= $criterio_fila === 'horario' ? 'bg-blue-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white font-medium' ?>">
                            🕒 Horário PJe
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1.5 tracking-wider">Prioridade no Topo</label>
                    <div class="grid grid-cols-2 gap-1 rounded-xl border border-gray-750 bg-gray-950 p-1" id="grupo-prioridade-fila">
                        <button type="button" id="btn-prio-desativado" class="px-2 py-1.5 text-xs rounded-lg transition text-center <?= !$atende_prioridade ? 'bg-gray-800 text-gray-300 font-bold shadow-sm' : 'text-gray-400 hover:text-white font-medium' ?>" title="Fila segue ordem normal sem priorização automática">
                            ⚪ Não
                        </button>
                        <button type="button" id="btn-prio-ativado" class="px-2 py-1.5 text-xs rounded-lg transition text-center <?= $atende_prioridade ? 'bg-amber-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white font-medium' ?>" title="Prioridades Legais sobem automaticamente">
                            ♿ Sim (Topo)
                        </button>
                    </div>
                </div>
                
                <p class="text-[10px] text-gray-500 leading-tight">
                    A recepção e a ordem de chamada respeitam a preferência selecionada acima.
                </p>
            </div>
        </div>

        <!-- Área Central (Paciente Ativo, Ações de Chamada e Fila) -->
        <div class="md:col-span-2 space-y-6">
            <?php if (!$sala_confirmada): ?>
                <div class="bg-amber-950/40 border border-amber-900/80 text-amber-300 p-4 rounded-2xl text-xs leading-relaxed flex items-center gap-3">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <strong class="block font-bold text-amber-400 uppercase tracking-wide">Confirmação Obrigatória da Sala</strong>
                        Por favor, selecione e confirme sua sala ao lado para iniciar os atendimentos de hoje. As chamadas estão temporariamente bloqueadas para evitar conflitos de sala com outros médicos.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($nome_nao_encontrado_pauta): ?>
                <div class="bg-yellow-950/40 border border-yellow-900/80 text-yellow-300 p-4 rounded-xl text-xs leading-normal">
                    ⚠️ <strong>Aviso:</strong> O seu Nome Completo cadastrado (<strong><?= escapar($perito_selecionado) ?></strong>) não foi localizado em nenhum atendimento da pauta de hoje.
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div role="status" aria-live="polite" class="sga-feedback p-4 rounded-xl border text-sm text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= escapar($message) ?>
                </div>
            <?php endif; ?>

            <!-- Informações do Paciente em Atendimento na Sala -->
            <div id="card-atendimento-container" class="bg-gray-900 p-5 sm:p-8 rounded-3xl border border-gray-800 shadow-xl flex flex-col items-center justify-center text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-blue-600"></div>
                <h2 class="text-xs uppercase font-bold text-gray-400 tracking-wider mb-4">Periciado em Atendimento</h2>
                <div id="conteudo-atendimento-atual" class="w-full flex flex-col items-center">
                
                <?php if ($atendimento_atual): ?>
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="text-2xl sm:text-3xl md:text-4xl font-black text-white break-words"><?= escapar($atendimento_atual['nome'] ?: 'Sem Nome') ?></span>
                        <?php if (($atendimento_atual['prioridade'] ?? 'normal') === 'superprioridade'): ?>
                            <span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-xs px-2.5 py-1 rounded-full font-black uppercase tracking-wider">⭐ Superprioridade (80+)</span>
                        <?php elseif (($atendimento_atual['prioridade'] ?? 'normal') === 'prioridade'): ?>
                            <span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-xs px-2.5 py-1 rounded-full font-bold uppercase tracking-wider">♿ Prioridade Legal</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xl font-bold text-blue-400 font-mono mb-2">Senha: <?= escapar($atendimento_atual['senha']) ?></div>
                    <div class="text-xs text-gray-400 mb-6 font-mono">Processo: <?= escapar($atendimento_atual['processo'] ?: 'Sem processo') ?></div>
                    
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2.5 w-full max-w-xl">
                        <?php if ($atendimento_atual['status'] === 'chamada'): ?>
                            <!-- Chamar Novamente -->
                            <form method="POST" class="flex-1 min-w-[140px] form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="chamar_novamente">
                                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-3 rounded-xl text-xs transition">
                                    📢 Chamar Novamente (<?= $atendimento_atual['chamadas_count'] ?>)
                                </button>
                            </form>
                            <!-- Iniciar Perícia -->
                            <form method="POST" class="flex-1 min-w-[140px] form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="iniciar_atendimento">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white font-semibold py-3 px-3 rounded-xl text-xs transition font-bold">
                                    🚀 Iniciar Perícia
                                </button>
                            </form>
                            <!-- Ausente (Descer 2 posições na fila) -->
                            <form method="POST" class="flex-1 min-w-[140px] form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="nao_compareceu">
                                <button type="submit" onclick="return confirm('Marcar como ausente temporário? O periciado descerá 2 posições na fila para você chamar os próximos.');" class="w-full bg-amber-850 hover:bg-amber-900 border border-amber-700/60 text-amber-200 font-semibold py-3 px-2 rounded-xl text-xs transition" title="Dá tolerância reposicionando 2 posições abaixo na fila">
                                    ⏳ Ausente (Descer 2 na fila)
                                </button>
                            </form>
                            <!-- Desistência (Foi embora) -->
                            <form method="POST" class="flex-1 min-w-[140px] form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="marcar_desistencia">
                                <button type="submit" onclick="return confirm('Confirmar desistência? O paciente será finalizado como desistente e sairá definitivamente da fila.');" class="w-full bg-rose-800 hover:bg-rose-900 text-white font-semibold py-3 px-2 rounded-xl text-xs transition">
                                    🚫 Desistência (Foi embora)
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Paciente em perícia (status 'atendimento') -->
                            <form method="POST" class="flex-1 form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="concluir">
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl text-xs transition">
                                    ✓ Concluir Perícia
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <a href="declaracao_comparecimento?id=<?= (int)$atendimento_atual['id'] ?>&print=1" target="_blank" class="w-full max-w-xl bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 font-semibold py-2.5 px-3 rounded-xl text-xs transition inline-flex items-center justify-center gap-1.5 mt-3">
                        🖨️ Emitir Declaração de Comparecimento
                    </a>
                <?php else: ?>
                    <div class="text-2xl font-bold text-gray-500 my-8">Nenhum atendimento em andamento</div>
                <?php endif; ?>

                <hr class="w-full border-gray-800 my-6">

                <?php if ($atendimento_atual): ?><p id="orientacao-atendimento" class="sga-helper mb-3">Conclua o atendimento atual antes de chamar outra pessoa.</p><?php endif; ?>
                <!-- Botão Principal: Chamar o próximo da fila de espera -->
                <form method="POST" class="w-full max-w-sm form-acao-perito"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="chamar_proximo">
                    <button type="submit" <?= ($atendimento_atual || !$sala_confirmada) ? 'disabled aria-describedby="orientacao-atendimento"' : '' ?> class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-2xl text-base shadow-lg hover:shadow-blue-500/20 transition transform hover:-translate-y-0.5">
                        🔊 Chamar Próximo da Fila
                    </button>
                </form>
                </div>
            </div>

            <!-- Fila de Espera Detalhada deste Médico hoje com Busca e Filtros -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider">Sua Pauta e Fila de Hoje</h3>
                    <a href="atendente" class="sga-neutral text-xs">Atualizar</a>
                </div>



                <!-- Barra de Busca Instantânea e Filtros por Situação -->
                <div class="space-y-3 mb-4">
                    <div>
                        <label for="filtro-busca-perito" class="sr-only">Buscar periciado</label>
                        <input type="search" id="filtro-busca-perito" autocomplete="off" placeholder="Buscar por nome, processo, CPF ou senha..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div class="flex flex-wrap gap-1.5" id="chips-filtro-status">
                        <button type="button" data-filtro="presente" class="chip-status bg-blue-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg transition">
                            🟢 Aguardando (<span id="cont-aguardando"><?= $cont_aguardando ?></span>)
                        </button>
                        <button type="button" data-filtro="agendado" class="chip-status bg-gray-800 hover:bg-gray-750 text-gray-300 text-[11px] font-medium px-3 py-1.5 rounded-lg transition">
                            🟡 Pendentes (<span id="cont-pendentes"><?= $cont_pendentes ?></span>)
                        </button>
                        <button type="button" data-filtro="concluido" class="chip-status bg-gray-800 hover:bg-gray-750 text-gray-300 text-[11px] font-medium px-3 py-1.5 rounded-lg transition">
                            ✓ Atendidos (<span id="cont-atendidos"><?= $cont_concluidos ?></span>)
                        </button>
                        <button type="button" data-filtro="desistente" class="chip-status bg-gray-800 hover:bg-gray-750 text-gray-300 text-[11px] font-medium px-3 py-1.5 rounded-lg transition">
                            🚫 Desistências (<span id="cont-desistentes"><?= $cont_desistentes ?></span>)
                        </button>
                        <button type="button" data-filtro="todos" class="chip-status bg-gray-800 hover:bg-gray-750 text-gray-300 text-[11px] font-medium px-3 py-1.5 rounded-lg transition">
                            📋 Todos (<span id="cont-todos"><?= count($todos_agendamentos) ?></span>)
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto sga-touch-scroll max-h-[420px] overflow-y-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2 w-16">Senha</th>
                                <th class="pb-2">Nome / Processo / CPF</th>
                                <th class="pb-2">Horário</th>
                                <th class="pb-2">Situação</th>
                                <th class="pb-2 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="corpo-fila-espera" class="divide-y divide-gray-800/50">
                            <?php
                            $filtrados_iniciais = array_values(array_filter($todosFormatados, fn($item) => $item['status'] === 'presente'));
                            if (empty($filtrados_iniciais)): ?>
                                <tr><td colspan="5" class="py-6 text-center text-xs text-gray-500">Nenhum periciado encontrado para este filtro.</td></tr>
                            <?php else: ?>
                                <?php foreach ($filtrados_iniciais as $p): ?>
                                    <?php
                                    $pos = $p['posicao_fila'] ? "{$p['posicao_fila']}º na fila" : 'Na fila';
                                    $chamadoTag = $p['chamadas_count'] > 0 ? '<span class="bg-amber-950/70 border border-amber-800/60 text-amber-400 text-[9px] px-1.5 py-0.2 rounded ml-1">Já chamado ' . (int)$p['chamadas_count'] . 'x</span>' : '';
                                    $statusBadge = '<span class="bg-emerald-950/60 text-emerald-400 border border-emerald-900/50 text-[10px] font-semibold px-2 py-0.5 rounded-full">🟢 ' . $pos . '</span>' . $chamadoTag;

                                    $prioBadge = '';
                                    if ($p['prioridade'] === 'superprioridade') {
                                        $prioBadge = '<span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase tracking-wider">⭐ 80+ Superprioridade</span>';
                                    } elseif ($p['prioridade'] === 'prioridade') {
                                        $prioBadge = '<span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-bold uppercase tracking-wider">♿ Prioridade</span>';
                                    }
                                    ?>
                                    <tr class="hover:bg-gray-800/30 transition">
                                        <td class="py-3 font-mono font-bold text-blue-400 text-xs"><?= escapar($p['senha']) ?></td>
                                        <td class="py-3">
                                            <div class="font-semibold text-white text-xs flex items-center gap-1.5 flex-wrap">
                                                <span><?= escapar($p['nome']) ?></span>
                                                <?= $prioBadge ?>
                                            </div>
                                            <div class="text-[10px] text-gray-500 font-mono">
                                                <?= escapar($p['processo']) ?>
                                                <?php if (!empty($p['cpf'])): ?>
                                                    <span class="text-blue-400/80 ml-1">| CPF: <?= escapar($p['cpf']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <?php if (!empty($p['hora_pericia'])): ?>
                                                <span class="bg-blue-950/70 border border-blue-900 text-blue-300 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded block w-max mb-0.5">🕒 <?= escapar($p['hora_pericia']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($p['chegada'] !== '--:--:--'): ?>
                                                <span class="text-[10px] text-gray-400 font-mono">Entrada: <?= escapar($p['chegada']) ?></span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-gray-600 font-mono">Aguardando</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3"><?= $statusBadge ?></td>
                                        <td class="py-3 text-right">
                                            <form method="POST" class="inline" onsubmit="return confirm('Chamar este periciado para a sala?');">
                                                <?= csrfCampo() ?>
                                                <input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                                <input type="hidden" name="action" value="chamar_especifico">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" <?= !$sala_confirmada ? 'disabled title="Confirme a sala primeiro"' : '' ?> class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-[10px] font-bold px-2.5 py-1 rounded transition">
                                                    Chamar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chamada Manual/Avulsa -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Chamada Manual / Avulsa</h3>
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="chamar_avulsa">
                    <input type="text" value="Gerada automaticamente" disabled aria-label="Senha gerada automaticamente" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    <input type="text" name="nome_avulso" required maxlength="100" placeholder="Nome completo do periciado" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-medium">
                    <button type="submit" <?= !$sala_confirmada ? 'disabled' : '' ?> class="bg-gray-800 hover:bg-gray-700 disabled:opacity-50 text-gray-200 border border-gray-700 text-xs font-semibold py-2 rounded-lg transition">
                        Chamar Manual
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Rodapé fora da área dos controles -->
    <footer class="py-4 px-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
<script>
(function() {
    let filtroAtual = 'presente';
    let buscaTexto = '';
    let dadosCache = <?= json_encode($dados_iniciais_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    let anteriorTotalAguardando = dadosCache?.totais?.aguardando ?? ((dadosCache?.fila || []).length);
    let filaIdsConhecidos = new Set((dadosCache?.fila || []).map(f => f.id));
    let salaConfirmada = <?= json_encode($sala_confirmada) ?>;
    let isPrimeiraCarga = true;
    const peritoNome = <?= json_encode($perito_selecionado) ?>;
    const userRole = <?= json_encode($user_role) ?>;

    const elContador = document.getElementById('contador-fila');
    const elCorpoFila = document.getElementById('corpo-fila-espera');
    const inputBusca = document.getElementById('filtro-busca-perito');
    const chipsFiltro = document.querySelectorAll('.chip-status');
    const btnCritChegada = document.getElementById('btn-crit-chegada');
    const btnCritHorario = document.getElementById('btn-crit-horario');
    const btnPrioDesativado = document.getElementById('btn-prio-desativado');
    const btnPrioAtivado = document.getElementById('btn-prio-ativado');
    const btnToggleSom = document.getElementById('btn-audio-perito') || document.getElementById('btn-toggle-som-perito');
    const iconeSom = document.getElementById('icone-audio-perito') || document.getElementById('icone-som');
    const textoSom = document.getElementById('texto-audio-perito') || document.getElementById('texto-som');
    const toastContainer = document.getElementById('toast-container-perito');

    function escaparHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto || '';
        return div.innerHTML;
    }

    // Configuração de Som (Web Audio API)
    let somHabilitado = localStorage.getItem('sga_som_perito') !== '0';
    function atualizarUiSom() {
        if (iconeSom) iconeSom.textContent = somHabilitado ? '🔊' : '🔇';
        if (textoSom) textoSom.textContent = somHabilitado ? 'Som Ativo' : 'Silencioso';
        if (btnToggleSom) {
            if (somHabilitado) {
                btnToggleSom.classList.remove('text-gray-400', 'border-gray-800');
                btnToggleSom.classList.add('text-blue-400', 'border-blue-900/60');
            } else {
                btnToggleSom.classList.remove('text-blue-400', 'border-blue-900/60');
                btnToggleSom.classList.add('text-gray-400', 'border-gray-800');
            }
        }
    }
    atualizarUiSom();

    window.toggleAudioPerito = function() {
        somHabilitado = !somHabilitado;
        localStorage.setItem('sga_som_perito', somHabilitado ? '1' : '0');
        atualizarUiSom();
        if (somHabilitado) {
            tocarChimeChegada();
        }
    };

    if (btnToggleSom) {
        btnToggleSom.addEventListener('click', window.toggleAudioPerito);
    }

    function tocarChimeChegada() {
        if (!somHabilitado) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') ctx.resume();
            const now = ctx.currentTime;

            // Tom 1: C5 (523.25 Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, now);
            gain1.gain.setValueAtTime(0.001, now);
            gain1.gain.exponentialRampToValueAtTime(0.2, now + 0.04);
            gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.35);

            // Tom 2: G5 (783.99 Hz)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(783.99, now + 0.15);
            gain2.gain.setValueAtTime(0.001, now + 0.15);
            gain2.gain.exponentialRampToValueAtTime(0.25, now + 0.19);
            gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.65);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.15);
            osc2.stop(now + 0.65);
        } catch (e) {
            // Ignora bloqueio de reprodução do navegador
        }
    }

    function exibirToastChegada(nome, senha, horaAgendada) {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = 'bg-gray-900 border-2 border-emerald-500 text-white shadow-2xl rounded-2xl p-4 flex items-start gap-3 transition-all duration-300 transform translate-x-full max-w-sm w-full';
        toast.innerHTML = `
            <div class="text-2xl mt-0.5">🔔</div>
            <div class="flex-1 min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Novo Periciado na Recepção</span>
                <div class="text-sm font-bold truncate text-white mt-0.5">${escaparHtml(nome)}</div>
                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                    <span class="font-mono text-blue-400 font-semibold">${escaparHtml(senha)}</span>
                    ${horaAgendada ? `<span class="bg-gray-800 text-gray-300 px-1.5 py-0.2 rounded text-[10px]">🕒 ${escaparHtml(horaAgendada)}</span>` : ''}
                </div>
            </div>
            <button type="button" class="text-gray-400 hover:text-white text-base leading-none">&times;</button>
        `;
        toast.querySelector('button').addEventListener('click', () => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        });
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.remove('translate-x-full'), 20);
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }
        }, 7000);
    }

    function exibirToastFeedback(mensagem, tipo = 'success') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        const isSucesso = tipo === 'success';
        const borda = isSucesso ? 'border-emerald-500/80 bg-gray-900' : 'border-rose-500 bg-gray-900';
        const corTitulo = isSucesso ? 'text-emerald-400' : 'text-rose-400';
        const icone = isSucesso ? '✅' : '⚠️';
        const titulo = isSucesso ? 'Ação Realizada' : 'Atenção / Aviso';

        toast.className = `border-2 ${borda} text-white shadow-2xl rounded-2xl p-4 flex items-start gap-3 transition-all duration-300 transform translate-x-full max-w-sm w-full`;
        toast.innerHTML = `
            <div class="text-2xl mt-0.5">${icone}</div>
            <div class="flex-1 min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider ${corTitulo} block">${titulo}</span>
                <div class="text-sm font-semibold text-gray-100 mt-0.5 break-words">${escaparHtml(mensagem)}</div>
            </div>
            <button type="button" class="text-gray-400 hover:text-white text-base leading-none">&times;</button>
        `;
        toast.querySelector('button').addEventListener('click', () => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        });
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.remove('translate-x-full'), 20);
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }
        }, 7000);
    }

    // Configuração do alternador de critério
    async function alternarCriterioFila(novoCriterio) {
        atualizarUiCriterio(novoCriterio);
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        try {
            const formData = new FormData();
            formData.append('csrf', csrfToken);
            formData.append('action', 'alternar_criterio_fila');
            formData.append('criterio_fila', novoCriterio);

            const resp = await fetch('atendente.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (resp.ok) {
                const res = await resp.json().catch(() => null);
                if (res && res.csrf_token) {
                    document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                }
            }
            await atualizarFilaPerito();
        } catch (e) {
            // Silencioso
        }
    }

    function atualizarUiCriterio(criterio) {
        if (!btnCritHorario || !btnCritChegada) return;
        if (criterio === 'horario') {
            btnCritHorario.className = 'px-2 py-1.5 text-xs font-bold rounded-lg bg-blue-600 text-white transition text-center shadow-sm';
            btnCritChegada.className = 'px-2 py-1.5 text-xs font-medium rounded-lg text-gray-400 hover:text-white transition text-center';
        } else {
            btnCritChegada.className = 'px-2 py-1.5 text-xs font-bold rounded-lg bg-blue-600 text-white transition text-center shadow-sm';
            btnCritHorario.className = 'px-2 py-1.5 text-xs font-medium rounded-lg text-gray-400 hover:text-white transition text-center';
        }
    }

    if (btnCritChegada) btnCritChegada.addEventListener('click', () => alternarCriterioFila('chegada'));
    if (btnCritHorario) btnCritHorario.addEventListener('click', () => alternarCriterioFila('horario'));

    // Configuração do alternador de priorização na fila
    async function alternarAtenderPrioridade(ativar) {
        atualizarUiPrioridade(ativar);
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        try {
            const formData = new FormData();
            formData.append('csrf', csrfToken);
            formData.append('action', 'alternar_atender_prioridade');
            formData.append('atender_prioridade', ativar ? '1' : '0');

            const resp = await fetch('atendente.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (resp.ok) {
                const res = await resp.json().catch(() => null);
                if (res && res.csrf_token) {
                    document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                }
            }
            await atualizarFilaPerito();
        } catch (e) {
            // Silencioso
        }
    }

    function atualizarUiPrioridade(ativar) {
        if (!btnPrioDesativado || !btnPrioAtivado) return;
        if (ativar) {
            btnPrioAtivado.className = 'px-2 py-1.5 text-xs font-bold rounded-lg bg-amber-600 text-white transition text-center shadow-sm';
            btnPrioDesativado.className = 'px-2 py-1.5 text-xs font-medium rounded-lg text-gray-400 hover:text-white transition text-center';
        } else {
            btnPrioDesativado.className = 'px-2 py-1.5 text-xs font-bold rounded-lg bg-gray-800 text-gray-300 transition text-center shadow-sm';
            btnPrioAtivado.className = 'px-2 py-1.5 text-xs font-medium rounded-lg text-gray-400 hover:text-white transition text-center';
        }
    }

    if (btnPrioDesativado) btnPrioDesativado.addEventListener('click', () => alternarAtenderPrioridade(false));
    if (btnPrioAtivado) btnPrioAtivado.addEventListener('click', () => alternarAtenderPrioridade(true));

    // Configuração dos chips de filtro
    chipsFiltro.forEach(chip => {
        chip.addEventListener('click', function() {
            filtroAtual = this.getAttribute('data-filtro') || 'todos';
            chipsFiltro.forEach(c => {
                c.classList.remove('bg-blue-600', 'text-white', 'font-bold');
                c.classList.add('bg-gray-800', 'text-gray-300', 'font-medium');
            });
            this.classList.remove('bg-gray-800', 'text-gray-300', 'font-medium');
            this.classList.add('bg-blue-600', 'text-white', 'font-bold');
            renderizarTabela();
        });
    });

    if (inputBusca) {
        inputBusca.addEventListener('input', function() {
            buscaTexto = this.value.toLowerCase().trim();
            renderizarTabela();
        });
    }

    function renderizarTabela() {
        if (!dadosCache || !elCorpoFila) return;
        
        const registros = dadosCache.todos || [];
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const atendimentoAtualId = dadosCache.atendimento_atual_id || 0;

        const filtrados = registros.filter(item => {
            // Filtro por status
            if (filtroAtual === 'presente' && item.status !== 'presente') return false;
            if (filtroAtual === 'agendado' && item.status !== 'agendado') return false;
            if (filtroAtual === 'concluido' && item.status !== 'concluido') return false;
            if (filtroAtual === 'desistente' && item.status !== 'desistente') return false;

            // Filtro por busca textual
            if (buscaTexto) {
                const termo = buscaTexto;
                const nome = (item.nome || '').toLowerCase();
                const processo = (item.processo || '').toLowerCase();
                const cpf = (item.cpf || '').toLowerCase();
                const senha = (item.senha || '').toLowerCase();
                if (!nome.includes(termo) && !processo.includes(termo) && !cpf.includes(termo) && !senha.includes(termo)) {
                    return false;
                }
            }

            return true;
        });

        if (filtrados.length === 0) {
            elCorpoFila.innerHTML = '<tr><td colspan="5" class="py-6 text-center text-xs text-gray-500">Nenhum periciado encontrado para este filtro.</td></tr>';
            return;
        }

        let html = '';
        filtrados.forEach(p => {
            let statusBadge = '';
            let acaoBotao = '';

            if (p.status === 'presente') {
                const pos = p.posicao_fila ? `${p.posicao_fila}º na fila` : 'Na fila';
                const chamadoTag = p.chamadas_count > 0 ? `<span class="bg-amber-950/70 border border-amber-800/60 text-amber-400 text-[9px] px-1.5 py-0.2 rounded ml-1">Já chamado ${p.chamadas_count}x</span>` : '';
                statusBadge = `<span class="bg-emerald-950/60 text-emerald-400 border border-emerald-900/50 text-[10px] font-semibold px-2 py-0.5 rounded-full">🟢 ${pos}</span>${chamadoTag}`;
                
                acaoBotao = `
                    <form method="POST" class="inline" onsubmit="return confirm('Chamar este periciado para a sala?');">
                        <input type="hidden" name="csrf" value="${csrfToken}">
                        <input type="hidden" name="atendimento_id" value="${atendimentoAtualId}">
                        <input type="hidden" name="action" value="chamar_especifico">
                        <input type="hidden" name="id" value="${p.id}">
                        <button type="submit" ${!salaConfirmada ? 'disabled title="Confirme a sala primeiro"' : ''} class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-[10px] font-bold px-2.5 py-1 rounded transition">
                            Chamar
                        </button>
                    </form>
                `;
            } else if (p.status === 'agendado') {
                statusBadge = '<span class="bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded-full">⚪ Aguardando Recepção</span>';
                acaoBotao = '<button disabled class="bg-gray-800/60 text-gray-500 cursor-not-allowed text-[10px] font-semibold px-2 py-1 rounded border border-gray-750" title="Apenas periciados com chegada confirmada na recepção podem ser chamados">⏳ Não chegou</button>';
            } else if (p.status === 'concluido') {
                statusBadge = '<span class="bg-gray-850 text-emerald-400/80 text-[10px] px-2 py-0.5 rounded-full">✓ Perícia Concluída</span>';
                acaoBotao = `
                    <div class="inline-flex items-center gap-1">
                        <span class="text-[10px] text-gray-500 font-mono">${escaparHtml(p.concluido)}</span>
                        <a href="declaracao_comparecimento?id=${p.id}&print=1" target="_blank" title="Imprimir Declaração de Comparecimento" class="text-[10px] bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 px-2 py-0.5 rounded transition">🖨️</a>
                    </div>
                `;
            } else if (p.status === 'desistente') {
                statusBadge = '<span class="bg-rose-950/60 text-rose-400 border border-rose-900/40 text-[10px] px-2 py-0.5 rounded-full">🚫 Desistente</span>';
                acaoBotao = '<span class="text-[10px] text-gray-500">Foi embora</span>';
            } else if (p.status === 'chamada' || p.status === 'atendimento') {
                statusBadge = `<span class="bg-blue-950 text-blue-400 border border-blue-900/50 text-[10px] px-2 py-0.5 rounded-full animate-pulse">🔔 Em atendimento (${escaparHtml(p.sala)})</span>`;
                acaoBotao = `
                    <div class="inline-flex items-center gap-1">
                        <span class="text-[10px] text-blue-400 font-bold">Em curso</span>
                        <a href="declaracao_comparecimento?id=${p.id}&print=1" target="_blank" title="Imprimir Declaração" class="text-[10px] bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 px-2 py-0.5 rounded transition">🖨️</a>
                    </div>
                `;
            }

            // Exibição do Horário Agendado e Horário de Chegada
            let prioBadge = '';
            if (p.prioridade === 'superprioridade') {
                prioBadge = `<span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase tracking-wider">⭐ 80+ Superprioridade</span>`;
            } else if (p.prioridade === 'prioridade') {
                prioBadge = `<span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-bold uppercase tracking-wider">♿ Prioridade</span>`;
            }

            let horarioHtml = '';
            if (p.hora_pericia) {
                horarioHtml += `<span class="bg-blue-950/70 border border-blue-900 text-blue-300 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded block w-max mb-0.5">🕒 ${escaparHtml(p.hora_pericia)}</span>`;
            }
            if (p.chegada !== '--:--:--') {
                horarioHtml += `<span class="text-[10px] text-gray-400 font-mono">Entrada: ${escaparHtml(p.chegada)}</span>`;
            } else {
                horarioHtml += `<span class="text-[10px] text-gray-600 font-mono">Aguardando</span>`;
            }

            html += `
            <tr class="hover:bg-gray-800/30 transition">
                <td class="py-3 font-mono font-bold text-blue-400 text-xs">${escaparHtml(p.senha)}</td>
                <td class="py-3">
                    <div class="font-semibold text-white text-xs flex items-center gap-1.5 flex-wrap">
                        <span>${escaparHtml(p.nome)}</span>
                        ${prioBadge}
                    </div>
                    <div class="text-[10px] text-gray-500 font-mono">
                        ${escaparHtml(p.processo)}
                        ${p.cpf ? `<span class="text-blue-400/80 ml-1">| CPF: ${escaparHtml(p.cpf)}</span>` : ''}
                    </div>
                </td>
                <td class="py-3">${horarioHtml}</td>
                <td class="py-3">${statusBadge}</td>
                <td class="py-3 text-right">${acaoBotao}</td>
            </tr>`;
        });

        elCorpoFila.innerHTML = html;
    }

    async function atualizarFilaPerito() {
        try {
            const resp = await fetch('api_perito_fila.php', { cache: 'no-store' });
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data || !data.success) return;

            // Sincronizar status da sala
            if (typeof data.sala_confirmada !== 'undefined') {
                salaConfirmada = Boolean(data.sala_confirmada);
            }

            // Notificação sonora e toast APENAS para novos periciados que chegarem após a página já estar aberta
            const novoTotalAguardando = data.totais?.aguardando || 0;
            if (userRole === 'admin') {
                // Administrador opera como espelho/observador: não toca alerta sonoro nem exibe toast de chegada
            } else if (isPrimeiraCarga) {
                // Primeira execução: sincroniza a base sem disparar alertas para quem já estava aguardando
                isPrimeiraCarga = false;
            } else if (anteriorTotalAguardando !== null && novoTotalAguardando > anteriorTotalAguardando) {
                const novos = (data.fila || []).filter(f => !filaIdsConhecidos.has(f.id));
                const pacienteNovo = novos[0];
                if (pacienteNovo) {
                    tocarChimeChegada();
                    exibirToastChegada(pacienteNovo.nome, pacienteNovo.senha, pacienteNovo.hora_pericia);
                }
            }

            anteriorTotalAguardando = novoTotalAguardando;
            filaIdsConhecidos = new Set((data.fila || []).map(f => f.id));

            dadosCache = data;

            // Sincronizar UI do alternador de critério
            if (data.criterio_fila) {
                atualizarUiCriterio(data.criterio_fila);
            }
            if (typeof data.atender_prioridade !== 'undefined') {
                atualizarUiPrioridade(Boolean(data.atender_prioridade));
            }

            // 1. Atualizar o contador da fila
            if (elContador && elContador.textContent.trim() != data.total_aguardando) {
                elContador.textContent = data.total_aguardando;
                elContador.classList.add('scale-110', 'text-amber-400');
                setTimeout(() => {
                    elContador.classList.remove('scale-110', 'text-amber-400');
                }, 600);
            }

            // Atualizar contadores dos chips
            if (data.totais) {
                document.getElementById('cont-aguardando').textContent = data.totais.aguardando;
                document.getElementById('cont-pendentes').textContent = data.totais.pendentes;
                document.getElementById('cont-atendidos').textContent = data.totais.atendidos;
                document.getElementById('cont-desistentes').textContent = data.totais.desistentes;
                document.getElementById('cont-todos').textContent = data.totais.total;
            }

            // Renderizar a tabela respeitando busca e filtros ativos
            renderizarTabela();
            renderizarCardAtendimentoAtual(data.atendimento_atual);
        } catch (e) {
            // Falha de rede momentânea silenciosa
        }
    }

    function renderizarCardAtendimentoAtual(atual) {
        const elContainer = document.getElementById('conteudo-atendimento-atual');
        if (!elContainer) return;
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';

        if (!atual) {
            elContainer.innerHTML = `
                <div class="text-2xl font-bold text-gray-500 my-8">Nenhum atendimento em andamento</div>
                <hr class="w-full border-gray-800 my-6">
                <form method="POST" class="w-full max-w-sm form-acao-perito">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="0">
                    <input type="hidden" name="action" value="chamar_proximo">
                    <button type="submit" ${!salaConfirmada ? 'disabled aria-describedby="orientacao-atendimento"' : ''} class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-2xl text-base shadow-lg hover:shadow-blue-500/20 transition transform hover:-translate-y-0.5">
                        🔊 Chamar Próximo da Fila
                    </button>
                </form>
            `;
            return;
        }

        let prioHtml = '';
        if (atual.prioridade === 'superprioridade') {
            prioHtml = '<span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-xs px-2.5 py-1 rounded-full font-black uppercase tracking-wider">⭐ Superprioridade (80+)</span>';
        } else if (atual.prioridade === 'prioridade') {
            prioHtml = '<span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-xs px-2.5 py-1 rounded-full font-bold uppercase tracking-wider">♿ Prioridade Legal</span>';
        }

        let botoesAcao = '';
        if (atual.status === 'chamada') {
            botoesAcao = `
                <form method="POST" class="flex-1 min-w-[140px] form-acao-perito">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="${atual.id}">
                    <input type="hidden" name="action" value="chamar_novamente">
                    <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-3 rounded-xl text-xs transition">
                        📢 Chamar Novamente (${atual.chamadas_count || 1})
                    </button>
                </form>
                <form method="POST" class="flex-1 min-w-[140px] form-acao-perito">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="${atual.id}">
                    <input type="hidden" name="action" value="iniciar_atendimento">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white font-semibold py-3 px-3 rounded-xl text-xs transition font-bold">
                        🚀 Iniciar Perícia
                    </button>
                </form>
                <form method="POST" class="flex-1 min-w-[140px] form-acao-perito" onsubmit="return confirm('Marcar como ausente temporário? O periciado descerá 2 posições na fila para você chamar os próximos.');">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="${atual.id}">
                    <input type="hidden" name="action" value="nao_compareceu">
                    <button type="submit" class="w-full bg-amber-850 hover:bg-amber-900 border border-amber-700/60 text-amber-200 font-semibold py-3 px-2 rounded-xl text-xs transition" title="Dá tolerância reposicionando 2 posições abaixo na fila">
                        ⏳ Ausente (Descer 2 na fila)
                    </button>
                </form>
                <form method="POST" class="flex-1 min-w-[140px] form-acao-perito" onsubmit="return confirm('Confirmar desistência? O paciente será finalizado como desistente e sairá definitivamente da fila.');">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="${atual.id}">
                    <input type="hidden" name="action" value="marcar_desistencia">
                    <button type="submit" class="w-full bg-rose-800 hover:bg-rose-900 text-white font-semibold py-3 px-2 rounded-xl text-xs transition">
                        🚫 Desistência (Foi embora)
                    </button>
                </form>
            `;
        } else {
            botoesAcao = `
                <form method="POST" class="flex-1 form-acao-perito">
                    <input type="hidden" name="csrf" value="${csrfToken}">
                    <input type="hidden" name="atendimento_id" value="${atual.id}">
                    <input type="hidden" name="action" value="concluir">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl text-xs transition">
                        ✓ Concluir Perícia
                    </button>
                </form>
            `;
        }

        elContainer.innerHTML = `
            <div class="flex items-center gap-2 mb-2 flex-wrap justify-center">
                <span class="text-4xl font-black text-white">${escaparHtml(atual.nome || 'Sem Nome')}</span>
                ${prioHtml}
            </div>
            <div class="text-xl font-bold text-blue-400 font-mono mb-2">Senha: ${escaparHtml(atual.senha || '---')}</div>
            <div class="text-xs text-gray-400 mb-6 font-mono">Processo: ${escaparHtml(atual.processo || 'Sem processo')}</div>
            
            <div class="flex flex-col sm:flex-row flex-wrap gap-2.5 w-full max-w-xl">
                ${botoesAcao}
            </div>
            <a href="declaracao_comparecimento?id=${atual.id}&print=1" target="_blank" class="w-full max-w-xl bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 font-semibold py-2.5 px-3 rounded-xl text-xs transition inline-flex items-center justify-center gap-1.5 mt-3">
                🖨️ Emitir Declaração de Comparecimento
            </a>
            <hr class="w-full border-gray-800 my-6">
            <p id="orientacao-atendimento" class="sga-helper mb-3">Conclua o atendimento atual antes de chamar outra pessoa.</p>
            <form method="POST" class="w-full max-w-sm form-acao-perito">
                <input type="hidden" name="csrf" value="${csrfToken}">
                <input type="hidden" name="atendimento_id" value="${atual.id}">
                <input type="hidden" name="action" value="chamar_proximo">
                <button type="submit" disabled aria-describedby="orientacao-atendimento" class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-2xl text-base shadow-lg hover:shadow-blue-500/20 transition transform hover:-translate-y-0.5">
                    🔊 Chamar Próximo da Fila
                </button>
            </form>
        `;
    }

    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.matches('form') && form.querySelector('input[name="action"]')) {
            const action = form.querySelector('input[name="action"]').value;
            const acoesAjax = ['chamar_proximo', 'chamar_novamente', 'iniciar_atendimento', 'concluir', 'nao_compareceu', 'marcar_desistencia', 'chamar_especifico'];
            if (acoesAjax.includes(action)) {
                e.preventDefault();
                executarAcaoPeritoAjax(form);
            }
        }
    });

    async function executarAcaoPeritoAjax(form) {
        const btnSubmit = form.querySelector('button[type="submit"]');
        const textoOriginal = btnSubmit ? btnSubmit.innerHTML : '';
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = 'Processando...';
        }

        try {
            const formData = new FormData(form);
            const resp = await fetch('atendente.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!resp.ok) throw new Error('Erro na comunicação');
            const res = await resp.json();
            if (res.csrf_token) {
                document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
            }
            if (res.success) {
                exibirToastFeedback(res.message, 'success');
                await atualizarFilaPerito();
            } else {
                exibirToastFeedback(res.message || 'Erro ao processar a ação.', 'error');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                }
            }
        } catch (err) {
            form.submit();
        }
    }

    // Renderização imediata da fila com os dados pré-carregados (zero delay)
    if (dadosCache) {
        if (dadosCache.fila) {
            filaIdsConhecidos = new Set(dadosCache.fila.map(f => f.id));
        }
        anteriorTotalAguardando = dadosCache.totais?.aguardando || 0;
        renderizarTabela();
    }

    // Consulta inicial e polling a cada 3 segundos
    atualizarFilaPerito();
    setInterval(atualizarFilaPerito, 3000);
})();
</script>
</body>
</html>