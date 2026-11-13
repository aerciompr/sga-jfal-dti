<?php
/**
 * MÓDULO DE RECEPÇÃO - JFAL
 * 
 * recepcao.php: Tela para uso dos recepcionistas do Tribunal.
 * Funcionalidades:
 * - Importação da Pauta de Perícias em Excel (extração de processos, peritos e periciados).
 * - Agendamento manual de periciados.
 * - Confirmação de chegada de periciados (adicionando-os à fila de hoje).
 * - Busca de periciados por texto em tempo real (nome, processo ou perito).
 * - Remoção de agendamentos (manual, múltipla ou limpeza total da pauta de hoje).
 * - Prevenção ativa de duplicidade de peritos e processos.
 */


require_once __DIR__ . '/banco.php';

// Proteção da sessão: exige que o usuário esteja autenticado e possua perfil administrativo, supervisão ou recepção
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'recepcao', 'supervisor'])) {
    header("Location: index.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$message = '';
$message_type = '';
$dataFiltro = $_GET['data_filtro'] ?? date('Y-m-d');

/**
 * Função Auxiliar: getNextSenha
 * 
 * Obtém a próxima senha sequencial do tipo "PE-xxx" (Perícias) para a pauta de hoje.
 * Garante que a sequência reinicie no dia atual.
 *
 * @param PDO $pdo Instância da conexão com o banco.
 * @return string Próxima senha gerada.
 */

require_once __DIR__ . '/pauta_service.php';
require_once __DIR__ . '/atendimento_service.php';
[$message, $message_type] = $_SESSION['flash_recepcao'] ?? ['', ''];
unset($_SESSION['flash_recepcao']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    try {
        if ($action === 'importar_pdf' || $action === 'importar_excel') {
            if (!in_array($_SESSION['usuario_role'], ['admin','supervisor'], true)) throw new DomainException('Acesso negado.');
            require_once __DIR__ . '/importacao_service.php';
            $file = $_FILES['arquivo_excel'] ?? $_FILES['arquivo_pdf'] ?? [];
            $janela = (string)($_POST['janela_importacao'] ?? 'dia');
            $sincronizar = isset($_POST['sincronizar_pauta']) ? (bool)$_POST['sincronizar_pauta'] : true;
            $destSubId = null;
            if (multisubsecaoAtiva($pdo)) {
                if (!empty($_SESSION['is_superadmin']) && !empty($_POST['subsecao_id_destino'])) {
                    $destSubId = (int)$_POST['subsecao_id_destino'];
                } else {
                    $destSubId = subsecaoAtivaId($pdo);
                }
            }
            $dados = prepararImportacao($file, $janela);
            $resultado = importarRegistros($pdo, $dados['registros'], $dados['datas_escopo'], $sincronizar, $destSubId);
            $partes = [$resultado['novos'] . ' novos incluídos'];
            if (!empty($resultado['atualizados'])) $partes[] = $resultado['atualizados'] . ' atualizados';
            if (!empty($resultado['cancelados'])) $partes[] = $resultado['cancelados'] . ' cancelados da pauta';
            if (!empty($resultado['preservados'])) $partes[] = $resultado['preservados'] . ' preservados';
            if (!empty($resultado['usuarios'])) $partes[] = $resultado['usuarios'] . ' novos peritos pré-cadastrados';
            $message = 'Importação concluída: ' . implode('; ', $partes) . '.';
            $message_type = 'success';
            $_SESSION['flash_recepcao'] = [$message, $message_type];
            header('Location: recepcao.php', true, 303); exit;
        } elseif (in_array($action, ['confirmar_chegada','confirmar_multiplos','confirmar_chegada_lote'], true)) {
            $ids = isset($_POST['id']) ? [(int)$_POST['id']] : (array)($_POST['selecionados'] ?? []);
            $prio = isset($_POST['prioridade']) ? (string)$_POST['prioridade'] : null;
            $count = confirmarPresencas($pdo,$ids,$_SESSION['usuario_role'],$prio);
            $message = "Presença confirmada para $count novos registros.";
            if ($action === 'confirmar_chegada' && $count === 1) {
                $s = $pdo->prepare('SELECT nome, senha, prioridade FROM atendimentos WHERE id=?'); $s->execute([(int)$_POST['id']]); $confirmado=$s->fetch();
                $txtPrio = (($confirmado['prioridade'] ?? '') === 'superprioridade') ? ' (Superprioridade 80+)' : ((($confirmado['prioridade'] ?? '') === 'prioridade') ? ' (Prioridade Legal)' : '');
                $message = "Presença confirmada: {$confirmado['nome']}{$txtPrio}. Senha: {$confirmado['senha']}.";
            }
        } elseif ($action === 'alterar_prioridade') {
            $id = (int)($_POST['id'] ?? 0);
            $prio = (string)($_POST['prioridade'] ?? 'normal');
            alterarPrioridadeAtendimento($pdo, $id, $prio);
            $descPrio = $prio === 'superprioridade' ? 'Superprioridade (80+)' : ($prio === 'prioridade' ? 'Prioridade Legal' : 'Normal');
            $message = "Prioridade do periciado atualizada para {$descPrio}.";
        } else {
            $message = operarPauta($pdo,$action,$_POST,$dataFiltro);
        }
        $message_type = 'success';
    } catch (DomainException $e) { $message = $e->getMessage(); $message_type = 'error'; }
    catch (Throwable $e) { $message = erroPublico($e); $message_type = 'error'; }

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $message_type === 'success',
            'message' => $message,
            'message_type' => $message_type,
            'csrf_token' => csrfToken(),
            'action' => $action,
            'confirmado' => $confirmado ?? null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['flash_recepcao'] = [$message,$message_type];
    header('Location: recepcao.php' . '?data_filtro=' . urlencode($dataFiltro), true, 303); exit;
}

// Carrega listas para exibição limitadas à data selecionada (já definida no topo)

// Carrega URL atual da TV (prioriza banco de dados com fallback para arquivo)
$yt_url = get_config($pdo, 'youtube_url');
if (empty($yt_url)) {
    $yt_file = __DIR__ . '/youtube_url.txt';
    $yt_url = file_exists($yt_file) ? trim(file_get_contents($yt_file)) : 'https://www.youtube.com/watch?v=5qap5aO4i9A';
}

$subsecaoId = subsecaoAtivaId($pdo);
$multiAtiva = multisubsecaoAtiva($pdo);
$temSubAtend = colunaExiste($pdo, 'atendimentos', 'subsecao_id');
$filtroSub = ($temSubAtend && $multiAtiva) ? " AND subsecao_id = ?" : "";
$paramsSub = ($temSubAtend && $multiAtiva) ? [$subsecaoId] : [];

$pauta_liberada = isPautaLiberada($pdo, $dataFiltro, $subsecaoId);
$permite_checkin = $dataFiltro === date('Y-m-d') && (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $dataFiltro === date('Y-m-d') && $pauta_liberada));

// Recepção comum não visualiza dados de pautas que não foram homologadas/liberadas pelo supervisor
$agendados = [];
$presentes = [];
$chamados = [];
$concluidos = [];

if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $pauta_liberada)) {
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'agendado' AND data_pauta = ?" . $filtroSub . " ORDER BY perito ASC, nome ASC, id ASC");
    $stmt->execute(array_merge([$dataFiltro], $paramsSub));
    $agendados = $stmt->fetchAll();

    // Ordenação alinhada estritamente com o painel do perito: fila_em ASC, id ASC
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'presente' AND data_pauta = ?" . $filtroSub . " ORDER BY fila_em ASC, id ASC");
    $stmt->execute(array_merge([$dataFiltro], $paramsSub));
    $presentes = $stmt->fetchAll();

    // Sincroniza fila dos peritos respeitando critérios (chegada vs horário) e priorização
    $resumoPeritos = obterResumoFilasPeritos($pdo, $dataFiltro, $subsecaoId);
    $mapFilaPeritos = [];
    foreach ($resumoPeritos as $rp) {
        foreach ($rp['fila'] as $itemFila) {
            $mapFilaPeritos[(int)$itemFila['id']] = [
                'posicao' => (int)$itemFila['posicao'],
                'pessoas_na_frente' => (int)$itemFila['pessoas_na_frente'],
                'criterio_fila' => (string)($rp['criterio_fila'] ?? 'chegada'),
                'atender_prioridade' => !empty($rp['atender_prioridade']),
            ];
        }
    }

    $posicoesPorPerito = [];
    foreach ($presentes as &$p) {
        $id = (int)$p['id'];
        $medico = (string)($p['perito'] ?: 'Sem Perito');
        $posicoesPorPerito[$medico] = ($posicoesPorPerito[$medico] ?? 0) + 1;
        if (isset($mapFilaPeritos[$id])) {
            $p['posicao_perito'] = $mapFilaPeritos[$id]['posicao'];
            $p['pessoas_na_frente'] = $mapFilaPeritos[$id]['pessoas_na_frente'];
            $p['criterio_fila'] = $mapFilaPeritos[$id]['criterio_fila'];
            $p['atender_prioridade'] = $mapFilaPeritos[$id]['atender_prioridade'];
        } else {
            $p['posicao_perito'] = $posicoesPorPerito[$medico];
            $p['pessoas_na_frente'] = $posicoesPorPerito[$medico] - 1;
            $p['criterio_fila'] = 'chegada';
            $p['atender_prioridade'] = false;
        }
    }
    unset($p);

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada','atendimento') AND data_pauta = ?" . $filtroSub . " ORDER BY chamado_em DESC");
    $stmt->execute(array_merge([$dataFiltro], $paramsSub));
    $chamados = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'concluido' AND data_pauta = ?" . $filtroSub . " ORDER BY finalizado_em DESC, chamado_em DESC");
    $stmt->execute(array_merge([$dataFiltro], $paramsSub));
    $concluidos = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'desistente' AND data_pauta = ?" . $filtroSub . " ORDER BY finalizado_em DESC");
    $stmt->execute(array_merge([$dataFiltro], $paramsSub));
    $desistentes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Recepção - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js?v=2.5"></script>
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Container de Toasts de Notificação da Recepção -->
    <div id="toast-container-recepcao" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none *:pointer-events-auto"></div>
    <?php require_once __DIR__ . '/header_menu.php'; ?>

    <main class="flex-1 max-w-[96rem] w-full mx-auto p-6 <?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao']) ? 'grid grid-cols-1 lg:grid-cols-3 gap-6' : 'space-y-6' ?>">
        <!-- Área de Listas (Fila de Hoje) -->
        <div class="sga-queue <?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao']) ? 'lg:col-span-2' : '' ?> space-y-6">
            <?php if ($message): ?>
                <div role="status" aria-live="polite" class="sga-feedback p-3 rounded-lg border text-xs text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= escapar($message) ?>
                </div>
            <?php endif; ?>
            <!-- Filtro de Data da Pauta -->
            <div class="bg-gray-900 p-4 rounded-2xl border border-gray-800 shadow-xl flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-blue-400 font-semibold text-sm">📅</span>
                    <span class="text-xs font-semibold uppercase text-gray-400 tracking-wider">Visualizando Pauta de:</span>
                </div>
                <form method="GET" action="recepcao.php" class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="date" name="data_filtro" id="data-filtro-input" aria-label="Data da pauta" value="<?= escapar($dataFiltro) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 w-full sm:w-auto">
                    <button type="button" onclick="irParaHoje()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shrink-0">
                        Hoje
                    </button>
                    <noscript>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">Filtrar</button>
                    </noscript>
                </form>
            </div>
            
            <?php if ($_SESSION['usuario_role'] === 'recepcao' && !$pauta_liberada && $dataFiltro === date('Y-m-d')): ?>
                <div class="p-4 rounded-xl border bg-amber-950/20 border-amber-900/50 text-amber-300 text-xs font-semibold flex items-center gap-3">
                    <span class="text-lg">⚠️</span>
                    <div>
                        <h4 class="font-bold uppercase tracking-wider text-amber-400">Pauta Pendente de Homologação</h4>
                        <p class="text-amber-500 mt-0.5 font-normal leading-relaxed">
                            A pauta do dia <?= date('d/m/Y', strtotime($dataFiltro)) ?> ainda não foi homologada pelo supervisor da recepção. Os check-ins de pacientes estão temporariamente bloqueados.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 1. Agendados (Ainda não chegaram) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <form method="POST" id="form-agendados"><?= csrfCampo() ?>
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                        <h2 class="text-sm font-semibold uppercase text-amber-400 tracking-wider flex items-center gap-2">
                            <span>Agendados Para <?= date('d/m/Y', strtotime($dataFiltro)) ?></span>
                            <span class="bg-amber-950 text-amber-400 text-xs px-2.5 py-0.5 rounded-full"><?= count($agendados) ?></span>
                        </h2>
                        
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <?php if ($permite_checkin): ?>
                                <button type="submit" name="action" value="confirmar_multiplos" id="btn-confirmar-selecionados" disabled onclick="return confirm('Confirmar chegada de todos os periciados selecionados de uma só vez?');" class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                                    ✅ Confirmar Selecionados
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                <button type="submit" name="action" value="remover_multiplos" id="btn-excluir-selecionados" disabled onclick="return confirm('Cancelar os agendamentos selecionados? O histórico será preservado.');" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                                    🗑️ Cancelar selecionados
                                </button>
                                <button type="button" onclick="limparPautaHoje()" class="bg-orange-950/40 hover:bg-orange-900/40 border border-orange-900/60 text-orange-400 text-xs px-3 py-1.5 rounded-lg transition flex-1 sm:flex-none">
                                    Cancelar pendentes
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Input para busca textual instantânea -->
                    <div class="mb-4">
                        <label for="filtro-busca" class="sr-only">Buscar agendamento</label>
                        <div class="sga-search"><input type="search" id="filtro-busca" autocomplete="off" placeholder="Nome, CPF, processo ou perito" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500"><button type="button" id="limpar-busca" class="sga-neutral">Limpar busca</button></div>
                        <p id="busca-resultado" role="status" aria-live="polite" class="sga-helper"></p>
                    </div>



                    <div class="overflow-x-auto max-h-[460px] overflow-y-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <?php if ($permite_checkin): ?>
                                        <th class="pb-2 w-8">
                                            <input type="checkbox" id="chk-select-all" aria-label="Selecionar agendados visíveis" class="rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                        </th>
                                    <?php endif; ?>
                                    <th class="pb-2">Nome / Processo / CPF</th>
                                    <th class="pb-2">Perito</th>
                                    <th class="pb-2 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50" id="table-agendados-body">
                                <?php if (empty($agendados)): ?>
                                    <tr class="row-sem-registro">
                                        <td colspan="<?= ($_SESSION['usuario_role'] === 'admin' || $dataFiltro === date('Y-m-d')) ? 4 : 3 ?>" class="py-4 text-center text-xs text-gray-500">
                                            <?= $_SESSION['usuario_role'] === 'admin' ? 'Nenhum periciado agendado. Faça o upload do arquivo da Pauta ao lado.' : 'Nenhum periciado agendado para esta data.' ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($agendados as $item): ?>
                                        <tr data-nome="<?= escapar($item['nome']) ?>" onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ id: <?= (int)$item['id'] ?>, nome: <?= jsValor($item['nome']) ?>, cpf: <?= jsValor($item['cpf'] ?? '---') ?>, processo: <?= jsValor($item['processo']) ?>, perito: <?= jsValor($item['perito']) ?>, data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: <?= jsValor($item['status']) ?>, prioridade: <?= jsValor($item['prioridade'] ?? 'normal') ?>, chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="row-agendado cursor-pointer hover:bg-gray-850/40 transition">
                                            <?php if ($permite_checkin): ?>
                                                <td class="sga-select py-3">
                                                    <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" aria-label="Selecionar <?= escapar($item['nome']) ?>" class="chk-item rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                                </td>
                                            <?php endif; ?>
                                            <td class="sga-patient py-3">
                                                <div class="font-semibold text-white truncate max-w-[200px] flex items-center gap-1.5 flex-wrap">
                                                    <span><?= escapar($item['nome']) ?></span>
                                                    <?php if (($item['prioridade'] ?? 'normal') === 'superprioridade'): ?>
                                                        <span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase tracking-wider">⭐ 80+ Superprioridade</span>
                                                    <?php elseif (($item['prioridade'] ?? 'normal') === 'prioridade'): ?>
                                                        <span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-bold uppercase tracking-wider">♿ Prioridade</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= escapar($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-blue-400 ml-1.5">| CPF: <?= escapar($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="sga-doctor py-3 text-xs text-gray-300 truncate max-w-[150px]"><?= escapar($item['perito'] ?: 'Não associado') ?></td>
                                            <td class="sga-actions py-3 text-right flex items-center justify-end gap-1.5">
                                                <?php if ($permite_checkin): ?>
                                                    <button type="button" onclick="confirmarChegada(<?= $item['id'] ?>, this)" class="btn-checkin bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-3.5 py-1.5 rounded-lg shadow-sm transition transform active:scale-95 flex items-center gap-1">
                                                        <span>✅</span> Confirmar Chegada
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                    <button type="button" onclick="removerIndividual(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['status']) ?>)" class="bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        <?= $item['status'] === 'agendado' ? 'Cancelar' : 'Desfazer presença' ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                                                    <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['processo']) ?>, <?= jsValor($item['perito']) ?>, <?= jsValor($item['cpf'] ?? '') ?>, <?= jsValor($item['prioridade'] ?? 'normal') ?>)" aria-label="Editar agendamento de <?= escapar($item['nome']) ?>" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        ✏️
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Formulário oculto para envio de chegada individual -->
                <form id="form-chegada-unica" method="POST" class="hidden"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="confirmar_chegada">
                    <input type="hidden" name="id" id="chegada-id">
                </form>
                <form id="form-remover-unico" method="POST" class="hidden"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="remover">
                    <input type="hidden" name="id" id="remover-id">
                </form>
                <form id="form-limpar-pauta" method="POST" class="hidden"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="limpar_pauta_hoje">
                </form>
            </div>

            <!-- Visão das Salas e Filas por Perito -->
            <?php if (!empty($resumoPeritos)): ?>
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase text-blue-400 tracking-wider flex items-center gap-2">
                        <span>🧑‍⚕️</span> Salas & Filas dos Peritos
                    </h2>
                    <span class="text-xs text-gray-500 font-medium">Tempo real</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="cards-peritos-container">
                    <?php foreach ($resumoPeritos as $rp): ?>
                        <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 space-y-3 card-perito" data-perito="<?= escapar($rp['perito']) ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-white text-xs truncate max-w-[170px]" title="<?= escapar($rp['perito']) ?>"><?= escapar($rp['perito']) ?></div>
                                    <span class="text-[10px] text-gray-400 font-medium mt-0.5 block">🚪 <?= escapar($rp['sala']) ?></span>
                                    <span class="text-[9px] font-semibold block mt-0.5 <?= ($rp['criterio_fila'] ?? 'chegada') === 'horario' ? 'text-amber-400' : 'text-emerald-400' ?>">
                                        <?= ($rp['criterio_fila'] ?? 'chegada') === 'horario' ? '🕒 Fila: Horário PJe' : '🏃 Fila: Ordem de Chegada' ?>
                                    </span>
                                    <span class="text-[9px] font-semibold block mt-0.5 <?= !empty($rp['atender_prioridade']) ? 'text-amber-400' : 'text-gray-500' ?>">
                                        <?= !empty($rp['atender_prioridade']) ? '♿ Fila com Prioridade' : '⚪ Prioridade Opcional' ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="bg-blue-950/70 border border-blue-900/60 text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full block">
                                        Fila: <?= count($rp['fila']) ?>
                                    </span>
                                    <?php if (!empty($rp['totais']['prioritarios'])): ?>
                                        <span class="text-[9px] text-amber-400 font-bold block mt-0.5">
                                            ⭐ <?= $rp['totais']['prioritarios'] ?> prioritário(s)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Situação da Sala -->
                            <div class="text-[11px] p-2.5 rounded-lg border <?= $rp['em_atendimento'] ? 'bg-blue-950/30 border-blue-900/40 text-blue-300' : 'bg-gray-900/40 border-gray-800/60 text-gray-400' ?>">
                                <?php if ($rp['em_atendimento']): ?>
                                    <div class="font-bold text-blue-400 flex items-center justify-between">
                                        <span>🟢 Em Exame: <?= escapar($rp['em_atendimento']['senha']) ?></span>
                                        <span class="text-[9px] text-gray-400"><?= $rp['em_atendimento']['chamado_em'] ?></span>
                                    </div>
                                    <div class="truncate text-white text-[11px] font-medium mt-0.5"><?= escapar($rp['em_atendimento']['nome']) ?></div>
                                <?php else: ?>
                                    <div class="flex items-center gap-1.5 text-gray-500">
                                        <span>⚪</span> Sala livre / aguardando próximo
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Próximos da fila deste médico -->
                            <div class="space-y-1">
                                <span class="text-[9px] uppercase font-bold text-gray-500 tracking-wider">Próximos a Chamar:</span>
                                <?php if (empty($rp['fila'])): ?>
                                    <p class="text-[10px] text-gray-600 italic">Sem pacientes na fila</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($rp['fila'], 0, 2) as $fp): ?>
                                        <div class="flex items-center justify-between text-[11px] bg-gray-900/60 px-2 py-1 rounded border border-gray-800/40">
                                            <span class="font-mono font-bold text-blue-400"><?= $fp['posicao'] ?>º <?= escapar($fp['senha']) ?></span>
                                            <span class="truncate text-gray-300 max-w-[120px] text-[10px]"><?= escapar($fp['nome']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Totais do médico -->
                            <div class="pt-2 border-t border-gray-800/60 flex justify-between text-[10px] text-gray-400">
                                <span>Agendados: <strong><?= $rp['totais']['agendados'] ?></strong></span>
                                <span>Atendidos: <strong><?= $rp['totais']['concluidos'] ?></strong></span>
                                <?php if ($rp['totais']['desistentes'] > 0): ?>
                                    <span class="text-rose-400">Desistências: <strong><?= $rp['totais']['desistentes'] ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. Presentes (Fila de espera ativa para chamada do perito) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl" id="bloco-presentes">
                <form method="POST" id="form-presentes"><?= csrfCampo() ?>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <h2 class="text-sm font-semibold uppercase text-white tracking-wider flex items-center gap-2">
                        <span>Aguardando Chamada (Fila de Espera)</span>
                        <span class="bg-blue-950/70 border border-blue-900/60 text-blue-300 text-xs px-2.5 py-0.5 rounded-full font-bold" id="contador-presentes"><?= count($presentes) ?></span>
                    </h2>
                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                    <button type="submit" name="action" value="remover_multiplos" id="btn-remover-presentes" disabled onclick="return confirm('Retornar todos os periciados selecionados para a lista de agendados?');" class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-xs px-3.5 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        🔄 Voltar Selecionados para Agendados
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($posicoesPorPerito)): ?>
                <!-- Filtro compacto por perito na fila de espera -->
                <div class="flex items-center gap-3 mb-4">
                    <label for="filtro-perito-select" class="text-xs uppercase font-bold text-gray-400 shrink-0">Filtrar por Médico:</label>
                    <select id="filtro-perito-select" onchange="filtrarFilaPerito(this.value)" class="bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-blue-500 max-w-xs">
                        <option value="">Todos os Médicos (<?= count($presentes) ?> aguardando)</option>
                        <?php foreach ($posicoesPorPerito as $medicoNome => $qtd): ?>
                            <option value="<?= escapar($medicoNome) ?>">
                                <?= escapar($medicoNome) ?> (<?= $qtd ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto max-h-[460px] overflow-y-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                    <th class="pb-2 w-8">
                                        <input type="checkbox" id="chk-select-all-presentes" aria-label="Selecionar pessoas na fila" class="rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                    </th>
                                <?php endif; ?>
                                <th class="pb-2 w-28">Posição na Fila</th>
                                <th class="pb-2">Senha</th>
                                <th class="pb-2">Nome / Processo</th>
                                <th class="pb-2">Perito</th>
                                <th class="pb-2">Chegada / Chamada</th>
                                <th class="pb-2 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php if (empty($presentes)): ?>
                                <tr>
                                    <td colspan="<?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 7 : 6 ?>" class="py-4 text-center text-xs text-gray-500">Ninguém aguardando no momento.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($presentes as $item): ?>
                                    <tr data-nome="<?= escapar($item['nome']) ?>" data-perito="<?= escapar($item['perito']) ?>" onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ id: <?= (int)$item['id'] ?>, nome: <?= jsValor($item['nome']) ?>, cpf: <?= jsValor($item['cpf'] ?? '---') ?>, processo: <?= jsValor($item['processo']) ?>, perito: <?= jsValor($item['perito']) ?>, data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: <?= jsValor($item['status']) ?>, prioridade: <?= jsValor($item['prioridade'] ?? 'normal') ?>, chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>', posicao_perito: <?= (int)($item['posicao_perito'] ?? 0) ?>, pessoas_na_frente: <?= (int)($item['pessoas_na_frente'] ?? 0) ?>, chamadas_count: <?= (int)($item['chamadas_count'] ?? 0) ?>, criterio_fila: <?= jsValor($item['criterio_fila'] ?? 'chegada') ?>, atender_prioridade: <?= !empty($item['atender_prioridade']) ? 'true' : 'false' ?> })" class="row-presente cursor-pointer hover:bg-gray-850/40 transition">
                                        <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                            <td class="py-3">
                                                <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" aria-label="Selecionar <?= escapar($item['nome']) ?> na fila" class="chk-presente-item rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                            </td>
                                        <?php endif; ?>
                                        <td class="py-3 text-xs">
                                            <div class="font-bold text-white"><?= (int)($item['posicao_perito'] ?? 1) ?>º lugar</div>
                                            <div class="text-[10px]">
                                                <?= (int)($item['pessoas_na_frente'] ?? 0) === 0 
                                                    ? '<span class="text-blue-400 font-semibold">Próximo (0 na frente)</span>' 
                                                    : "<span class=\"text-gray-400\">{$item['pessoas_na_frente']} na frente</span>" ?>
                                            </div>
                                        </td>
                                        <td class="py-3 font-mono font-bold text-blue-400"><?= escapar($item['senha']) ?></td>
                                        <td class="py-3">
                                            <div class="font-semibold text-white truncate max-w-[200px] flex items-center gap-1.5 flex-wrap">
                                                <span><?= escapar($item['nome']) ?></span>
                                                <?php if (($item['prioridade'] ?? 'normal') === 'superprioridade'): ?>
                                                    <span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase tracking-wider">⭐ 80+ Superprioridade</span>
                                                <?php elseif (($item['prioridade'] ?? 'normal') === 'prioridade'): ?>
                                                    <span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-bold uppercase tracking-wider">♿ Prioridade</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[10px] text-gray-500 font-mono">
                                                <?= escapar($item['processo'] ?: 'Sem processo') ?>
                                                <?php if (!empty($item['cpf'])): ?>
                                                    <span class="text-gray-400 ml-1.5 font-mono">| CPF: <?= escapar($item['cpf']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-3 text-xs text-gray-300 truncate max-w-[120px]"><?= escapar($item['perito'] ?: 'Não associado') ?></td>
                                        <td class="py-3 text-xs text-gray-400">
                                            <div><?= date('H:i:s', strtotime($item['chegada_em'])) ?></div>
                                            <?php if ((int)$item['chamadas_count'] > 0): ?>
                                                <span class="bg-amber-950/70 border border-amber-800/60 text-amber-300 text-[9px] font-bold px-1.5 py-0.2 rounded mt-0.5 inline-block">
                                                    ⚠️ Já chamado <?= $item['chamadas_count'] ?>x
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-right flex items-center justify-end gap-1.5">
                                            <a href="declaracao_comparecimento?id=<?= $item['id'] ?>&print=1" target="_blank" title="Imprimir Declaração de Comparecimento" class="bg-blue-950/70 hover:bg-blue-900 border border-blue-800/60 text-blue-300 text-xs px-2.5 py-1 rounded-lg transition flex items-center gap-1 font-semibold">
                                                🖨️ Declaração
                                            </a>
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) && (int)$item['chamadas_count'] === 0): ?>
                                                <button type="button" onclick="removerIndividual(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['status']) ?>)" class="bg-gray-800 hover:bg-gray-750 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                    Desfazer presença
                                                </button>
                                            <?php endif; ?>
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao']) && (int)$item['chamadas_count'] === 0): ?>
                                                <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['processo']) ?>, <?= jsValor($item['perito']) ?>, <?= jsValor($item['cpf'] ?? '') ?>, <?= jsValor($item['prioridade'] ?? 'normal') ?>)" aria-label="Editar dados de <?= escapar($item['nome']) ?>" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                    ✏️
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </form>
            </div>

            <!-- 3. Atendimentos Chamados e Concluídos de hoje -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Lista de Chamados (Em atendimento nas salas) -->
                <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                    <h3 class="text-sm font-semibold uppercase text-blue-400 tracking-wider mb-4">Em Atendimento</h3>
                    <div class="space-y-3 max-h-[250px] overflow-y-auto">
                        <?php if (empty($chamados)): ?>
                            <p class="text-xs text-gray-500 text-center py-4">Ninguém em atendimento.</p>
                        <?php else: ?>
                            <?php foreach ($chamados as $item): ?>
                                <div class="bg-gray-950/60 border border-gray-800 p-3 rounded-xl flex justify-between items-center">
                                    <div class="truncate flex-1 pr-2">
                                        <div class="text-xs font-semibold text-white truncate"><?= escapar($item['nome']) ?></div>
                                        <div class="text-[10px] text-gray-400 truncate font-mono">Processo: <?= escapar($item['processo']) ?></div>
                                        <div class="text-[10px] text-gray-400 truncate">Perito: <?= escapar($item['perito']) ?></div>
                                        <div class="text-[10px] text-emerald-400 mt-0.5"><?= escapar($item['sala']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-black text-blue-400"><?= escapar($item['senha']) ?></div>
                                        <div class="text-[9px] text-gray-500"><?= date('H:i', strtotime($item['chamado_em'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de Concluídos (Finalizados) -->
                <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                    <h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Finalizados</h3>
                    <div class="space-y-3 max-h-[250px] overflow-y-auto">
                        <?php if (empty($concluidos)): ?>
                            <p class="text-xs text-gray-500 text-center py-4">Nenhum atendimento finalizado.</p>
                        <?php else: ?>
                            <?php foreach ($concluidos as $item): ?>
                                <div class="bg-gray-950/30 border border-gray-800/40 p-3 rounded-xl flex justify-between items-center opacity-85 hover:opacity-100 transition">
                                    <div class="truncate flex-1 pr-2">
                                        <div class="text-xs font-semibold text-gray-200 truncate"><?= escapar($item['nome']) ?></div>
                                        <div class="text-[10px] text-gray-400 truncate">Perito: <?= escapar($item['perito']) ?> &bull; <?= escapar($item['sala']) ?></div>
                                        <div class="text-[9px] text-gray-500 font-mono mt-0.5"><?= escapar($item['processo'] ?: 'Sem processo') ?></div>
                                    </div>
                                    <div class="text-right flex items-center gap-2">
                                        <div>
                                            <div class="text-xs font-black text-blue-400 font-mono"><?= escapar($item['senha']) ?></div>
                                            <div class="text-[9px] text-gray-500">Fim: <?= date('H:i', strtotime($item['finalizado_em'] ?: $item['chamado_em'])) ?></div>
                                        </div>
                                        <a href="declaracao_comparecimento?id=<?= $item['id'] ?>&print=1" target="_blank" title="Imprimir Declaração de Comparecimento" class="bg-blue-950/70 hover:bg-blue-900 border border-blue-800/60 text-blue-300 text-xs px-2.5 py-1 rounded-lg transition flex items-center gap-1 font-semibold">
                                            🖨️ Declaração
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <!-- Modal de Edição de Agendamento -->
    <div id="modal-editar" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
            <h3 class="text-sm font-bold uppercase text-blue-500 tracking-wider mb-4">📝 Editar Periciado</h3>
            
            <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                <input type="hidden" name="action" value="editar_atendimento">
                <input type="hidden" name="id" id="edit-id">
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome do Paciente</label>
                    <input type="text" name="nome" maxlength="100" id="edit-nome" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Processo</label>
                    <input type="text" name="processo" maxlength="50" id="edit-processo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">CPF do Paciente</label>
                    <input type="text" name="cpf" maxlength="14" id="edit-cpf" placeholder="000.000.000-00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Perito</label>
                    <input type="text" name="perito" maxlength="100" id="edit-perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Prioridade Legal</label>
                    <select name="prioridade" id="edit-prioridade" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <option value="normal">Normal (Sem prioridade)</option>
                        <option value="prioridade">♿ Prioridade Legal (60+, PCD, Gestante)</option>
                        <option value="superprioridade">⭐ Superprioridade (80+ / Acamado)</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="fecharModalEdicao()" class="bg-gray-800 hover:bg-gray-750 border border-gray-700 text-gray-300 text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
        <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
        <!-- Sidebar de Operações (Importação e Cadastro Manual) -->
        <div class="sga-operations space-y-6">
            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
            <!-- Formulário de Importação de Arquivo -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4 flex items-center gap-2">
                    <span>📄</span> Importar Pauta (Excel .xlsx)
                </h2>
                <form id="form-importar" method="POST" enctype="multipart/form-data" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="importar_pdf">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Selecione o arquivo da Pauta (Excel .xlsx)</label>
                        <input type="file" name="arquivo_pdf" accept=".xlsx" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white file:bg-gray-700 file:border-0 file:text-white file:text-xs file:font-semibold file:px-2.5 file:py-1 file:rounded file:mr-2 file:hover:bg-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Janela da Pauta</label>
                        <select name="janela_importacao" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500">
                            <option value="dia" selected>📅 Apenas a pauta de hoje (1 dia)</option>
                            <option value="3dias">📅 Hoje e próximos 2 dias (Máximo 3 dias)</option>
                        </select>
                    </div>
                    <?php if ($multiAtiva): ?>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Subseção de Destino</label>
                        <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <select name="subsecao_id_destino" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500">
                                <?php foreach (obterSubsecoes($pdo) as $sub): ?>
                                    <option value="<?= (int)$sub['id'] ?>" <?= $sub['id'] == $subsecaoId ? 'selected' : '' ?>>
                                        🏢 <?= escapar($sub['nome']) ?> (<?= escapar($sub['cidade']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="subsecao_id_destino" value="<?= (int)$subsecaoId ?>">
                            <div class="text-xs text-blue-300 font-bold bg-blue-950/40 border border-blue-800/60 rounded-lg px-2.5 py-1.5">
                                🏢 Subseção: <?= escapar(obterSubsecao($pdo, $subsecaoId)['nome'] ?? 'Local') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="sincronizar_pauta" value="1" checked class="mt-0.5 rounded bg-gray-800 border-gray-700 text-blue-600 focus:ring-0">
                            <div>
                                <span class="text-xs text-gray-200 font-medium block">Sincronizar pauta diária</span>
                                <span class="text-[10px] text-gray-400 block leading-tight">Cancela agendamentos que saíram da planilha. Presenças já confirmadas hoje são preservadas.</span>
                            </div>
                        </label>
                    </div>
                    <button type="submit" id="btn-importar" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                        <span id="btn-importar-texto">Importar Dados</span>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <datalist id="peritos-cadastrados">
            <?php foreach ($pdo->query("SELECT nome FROM usuarios WHERE role='perito' AND nome IS NOT NULL ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN) as $nomePerito): ?>
                <option value="<?= escapar($nomePerito) ?>"></option>
            <?php endforeach; ?>
            </datalist>
            <!-- Cadastro Manual de Periciados -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Adicionar Periciado Manual</h2>
                <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="agendar_unico">
                    <input type="hidden" name="subsecao_id" value="<?= (int)$subsecaoId ?>">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Periciado</label>
                        <input type="text" name="nome" required maxlength="100" placeholder="Nome Completo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Processo</label>
                        <input type="text" name="processo" maxlength="50" placeholder="0000000-00.2026.4.05.8000" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">CPF do Periciado</label>
                        <input type="text" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Perito</label>
                        <input type="text" name="perito" required maxlength="100" list="peritos-cadastrados" placeholder="Nome do Médico/Perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-gray-800 hover:bg-gray-750 border border-gray-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Agendar
                    </button>
                </form>
            </div>

            <!-- Configuração do Vídeo da TV -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4 flex items-center gap-2">
                    <span>📺</span> Vídeo ou Playlist da TV
                </h2>
                <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="set_yt_url">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Link do vídeo ou playlist (YouTube)</label>
                        <input type="url" name="yt_url" value="<?= escapar($yt_url) ?>" required placeholder="https://www.youtube.com/watch?v=..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Atualizar conteúdo
                    </button>
                </form>
                <p class="text-[10px] text-gray-500 mt-3 leading-normal">
                    ⚠️ <strong>Nota:</strong> Cole o link de um vídeo ou de uma playlist institucional pública ou não listada. Links com “list=” reproduzem a playlist inteira em sequência, repetindo ao final. Todos os vídeos precisam permitir incorporação. Vídeos com restrições de idade ou proteção contra bots não carregarão no painel.
                </p>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Rodapé fora da área dos controles -->
    <footer class="py-4 px-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>

    <!-- Script JavaScript local para controle de seleção e filtro -->
    <script>
        const chkSelectAll = document.getElementById('chk-select-all');
        const chkItems = document.querySelectorAll('.chk-item');
        const btnExcluir = document.getElementById('btn-excluir-selecionados');
        const btnConfirmar = document.getElementById('btn-confirmar-selecionados');
        const filtroBusca = document.getElementById('filtro-busca');
        const rows = document.querySelectorAll('.row-agendado');

        // Alterna a disponibilidade dos botões em lote com base na seleção
        function toggleBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.chk-item:checked').length;
            if (btnExcluir) {
                btnExcluir.disabled = checkedCount === 0;
            }
            if (btnConfirmar) {
                btnConfirmar.disabled = checkedCount === 0;
            }
        }

        if (chkSelectAll) {
            chkSelectAll.addEventListener('change', function() {
                // Seleciona apenas os elementos visíveis para facilitar a exclusão filtrada
                rows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const chk = row.querySelector('.chk-item');
                        if (chk) chk.checked = this.checked;
                    }
                });
                toggleBulkDeleteButton();
            });
        }

        chkItems.forEach(item => {
            item.addEventListener('change', function() {
                if (!this.checked) {
                    if (chkSelectAll) chkSelectAll.checked = false;
                } else {
                    const visiveis = [...chkItems].filter(c => c.closest('tr').style.display !== 'none');
                    const allChecked = visiveis.length > 0 && visiveis.every(c => c.checked);
                    if (chkSelectAll) chkSelectAll.checked = allChecked;
                }
                toggleBulkDeleteButton();
            });
        });

        function exibirToastRecepcao(msg, tipo = 'success') {
            const container = document.getElementById('toast-container-recepcao');
            if (!container) return;
            const toast = document.createElement('div');
            const corBg = tipo === 'success' ? 'bg-emerald-950/95 border-emerald-500/80 text-emerald-200' : (tipo === 'error' ? 'bg-rose-950/95 border-rose-500/80 text-rose-200' : 'bg-blue-950/95 border-blue-500/80 text-blue-200');
            toast.className = `p-4 rounded-xl border shadow-2xl flex items-center gap-3 text-xs font-semibold animate-fade-in ${corBg}`;
            toast.innerHTML = `<span>${tipo === 'success' ? '✅' : '⚠️'}</span> <span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s ease';
                setTimeout(() => toast.remove(), 500);
            }, 4500);
        }

        // Recarrega dinamicamente a fila de presentes via DOMParser
        async function recarregarFilaPresentes() {
            try {
                const resp = await fetch('recepcao.php?data_filtro=<?= urlencode($dataFiltro) ?>', { cache: 'no-store' });
                if (!resp.ok) return;
                const html = await resp.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const novoBloco = doc.getElementById('bloco-presentes');
                const atualBloco = document.getElementById('bloco-presentes');
                if (novoBloco && atualBloco) {
                    atualBloco.innerHTML = novoBloco.innerHTML;
                    // Reanexar eventos de checkbox de presentes
                    reancorarEventosPresentes();
                }
            } catch (e) {}
        }

        
        // Intercepta confirmação em lote via AJAX (sem recarregar página)
        document.getElementById('form-agendados')?.addEventListener('submit', async function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.value === 'confirmar_multiplos') {
                e.preventDefault();
                const chks = Array.from(this.querySelectorAll('.chk-item:checked'));
                if (chks.length === 0) return;
                
                submitter.disabled = true;
                const originalText = submitter.innerHTML;
                submitter.innerHTML = '⏳ Confirmando...';
                
                const formData = new FormData(this);
                formData.set('action', 'confirmar_multiplos');
                
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await resp.json();
                    if (res.csrf_token) {
                        document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                    }
                    if (res.success) {
                        exibirToastRecepcao(res.message, 'success');
                        chks.forEach(chk => {
                            const r = chk.closest('tr');
                            if (r) r.remove();
                        });
                        const badgeAgendados = document.querySelector('#form-agendados h2 span:last-child');
                        if (badgeAgendados) {
                            const val = Math.max(0, (parseInt(badgeAgendados.textContent.trim(), 10) || 0) - chks.length);
                            badgeAgendados.textContent = val;
                        }
                        if (typeof recarregarFilaPresentes === 'function') await recarregarFilaPresentes();
                        if (typeof sincronizarPeritosRecepcao === 'function') sincronizarPeritosRecepcao();
                    } else {
                        exibirToastRecepcao(res.message || 'Erro ao confirmar.', 'error');
                    }
                } catch(err) {
                    this.submit();
                } finally {
                    submitter.disabled = false;
                    submitter.innerHTML = originalText;
                }
            }
        });

        // Intercepta retorno em lote da fila via AJAX
        document.getElementById('form-presentes')?.addEventListener('submit', async function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.value === 'remover_multiplos') {
                e.preventDefault();
                const chks = Array.from(this.querySelectorAll('.chk-presente-item:checked'));
                if (chks.length === 0) return;
                
                submitter.disabled = true;
                const originalText = submitter.innerHTML;
                submitter.innerHTML = '⏳ Retornando...';
                
                const formData = new FormData(this);
                formData.set('action', 'remover_multiplos');
                
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await resp.json();
                    if (res.csrf_token) {
                        document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                    }
                    if (res.success) {
                        exibirToastRecepcao(res.message, 'info');
                        chks.forEach(chk => {
                            const r = chk.closest('tr');
                            if (r) r.remove();
                        });
                        if (typeof recarregarFilaPresentes === 'function') await recarregarFilaPresentes();
                        if (typeof sincronizarPeritosRecepcao === 'function') sincronizarPeritosRecepcao();
                    } else {
                        exibirToastRecepcao(res.message || 'Erro ao retornar.', 'error');
                    }
                } catch(err) {
                    this.submit();
                } finally {
                    submitter.disabled = false;
                    submitter.innerHTML = originalText;
                }
            }
        });

        // Confirma chegada individual via AJAX com feedback instantâneo
        async function confirmarChegada(id, btn) {
            const row = btn ? btn.closest('tr') : document.querySelector(`tr[data-id="${id}"]`);
            const btnOriginalHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '⏳ Confirmando...';
            }

            const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf', csrfToken);
            formData.append('action', 'confirmar_chegada');
            formData.append('id', id);

            try {
                const resp = await fetch('recepcao.php?data_filtro=<?= urlencode($dataFiltro) ?>', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!resp.ok) throw new Error('Falha de resposta do servidor.');
                const res = await resp.json();
                if (res.csrf_token) {
                    document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                }

                if (res.success) {
                    exibirToastRecepcao(res.message, 'success');
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            row.remove();
                            // Atualizar contador de agendados
                            const badgeAgendados = document.querySelector('#form-agendados h2 span:last-child');
                            if (badgeAgendados) {
                                const atual = parseInt(badgeAgendados.textContent.trim(), 10) || 0;
                                badgeAgendados.textContent = Math.max(0, atual - 1);
                            }
                        }, 300);
                    }
                    if (typeof sincronizarPeritosRecepcao === 'function') {
                        sincronizarPeritosRecepcao();
                    }
                    await recarregarFilaPresentes();
                } else {
                    exibirToastRecepcao(res.message || 'Erro ao confirmar presença.', 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btnOriginalHtml;
                    }
                }
            } catch (err) {
                // Se der erro ou offline, fallback clássico
                document.getElementById('chegada-id').value = id;
                document.getElementById('form-chegada-unica').submit();
            }
        }

        // Submete ação para limpar agendados do dia
        function limparPautaHoje() {
            if (confirm('Cancelar todos os agendamentos pendentes desta data? Pessoas na fila ou em atendimento serão preservadas.')) {
                document.getElementById('form-limpar-pauta').submit();
            }
        }

        // Filtro em tempo real digitado pelo usuário e busca de outras datas
        let timeoutBuscaOutroDia = null;
        if (filtroBusca) {
            filtroBusca.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const queryClean = query.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
                
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    const textClean = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
                    
                    if (text.includes(query) || (queryClean && textClean.includes(queryClean))) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                        // Desmarca os itens ocultos para não excluir por acidente
                        const chk = row.querySelector('.chk-item');
                        if (chk) chk.checked = false;
                    }
                });
                
                toggleBulkDeleteButton();
                const visiveis = [...rows].filter(r => r.style.display !== 'none');
                const resultado = document.getElementById('busca-resultado');
                resultado.textContent = query ? (visiveis.length ? `${visiveis.length} agendamento(s) encontrado(s).` : 'Nenhum agendamento encontrado. Confira a data ou limpe a busca.') : '';
                resultado.classList.toggle('sga-empty-search', query.length > 0 && visiveis.length === 0);
                if (chkSelectAll) { chkSelectAll.checked = false; chkSelectAll.indeterminate = false; }


            });
        }

        document.getElementById('limpar-busca')?.addEventListener('click', () => {
            filtroBusca.value = ''; filtroBusca.dispatchEvent(new Event('input', {bubbles:true})); filtroBusca.focus();
        });

        filtroBusca?.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });

        // Controle de seleção para a Fila de Espera (Presentes)
        const chkSelectAllPresentes = document.getElementById('chk-select-all-presentes');
        const chkPresentesItems = document.querySelectorAll('.chk-presente-item');
        const btnRemoverPresentes = document.getElementById('btn-remover-presentes');

        function toggleBulkRemoverPresentesButton() {
            if (btnRemoverPresentes) {
                const checkedCount = document.querySelectorAll('.chk-presente-item:checked').length;
                btnRemoverPresentes.disabled = checkedCount === 0;
            }
        }

        if (chkSelectAllPresentes) {
            chkSelectAllPresentes.addEventListener('change', function() {
                chkPresentesItems.forEach(chk => {
                    chk.checked = this.checked && chk.closest('tr').style.display !== 'none';
                });
                toggleBulkRemoverPresentesButton();
            });
        }

        chkPresentesItems.forEach(item => {
            item.addEventListener('change', function() {
                if (!this.checked) {
                    if (chkSelectAllPresentes) chkSelectAllPresentes.checked = false;
                } else {
                    const allChecked = document.querySelectorAll('.chk-presente-item:checked').length === chkPresentesItems.length;
                    if (chkSelectAllPresentes) chkSelectAllPresentes.checked = allChecked;
                }
                toggleBulkRemoverPresentesButton();
            });
        });

        // Remove individualmente da fila de espera e retorna para agendados
        function removerIndividual(id, nome, status) {
            const mensagem = status === 'agendado' ? `Cancelar o agendamento de ${nome}? O histórico será preservado.` : `Desfazer a presença de ${nome} e voltar para agendados?`;
            if (confirm(mensagem)) {
                document.getElementById('remover-id').value = id;
                document.getElementById('form-remover-unico').submit();
            }
        }

        // Inicia a edição exibindo o modal e preenchendo os campos
        function iniciarEdicao(id, nome, processo, perito, cpf, prioridade) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nome').value = nome;
            document.getElementById('edit-processo').value = processo;
            document.getElementById('edit-cpf').value = cpf || '';
            document.getElementById('edit-perito').value = perito;
            const elPrio = document.getElementById('edit-prioridade');
            if (elPrio) elPrio.value = prioridade || 'normal';
            document.getElementById('modal-editar').classList.remove('hidden');
        }

        // Fecha o modal de edição
        function fecharModalEdicao() {
            document.getElementById('modal-editar').classList.add('hidden');
        }

        // Exibe loading e desabilita o botão ao importar pauta
        const formImportar = document.getElementById('form-importar');
        const btnImportar = document.getElementById('btn-importar');
        const btnImportarTexto = document.getElementById('btn-importar-texto');

        if (formImportar) {
            formImportar.addEventListener('submit', function() {
                // Desabilita o botão para evitar cliques múltiplos
                btnImportar.disabled = true;
                btnImportar.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btnImportar.classList.add('bg-blue-800', 'cursor-not-allowed', 'opacity-75');
                
                // Altera o texto e adiciona um spinner de carregamento SVG
                btnImportarTexto.innerHTML = `
                    <svg class="animate-spin h-4 w-4 text-white inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Processando Arquivo...</span>
                `;
            });
        }

        let pacienteDetalheAtualId = 0;

        async function alterarPrioridadeRapida(novaPrioridade) {
            if (!pacienteDetalheAtualId) return;
            const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf', csrfToken);
            formData.append('action', 'alterar_prioridade');
            formData.append('id', pacienteDetalheAtualId);
            formData.append('prioridade', novaPrioridade);

            try {
                const resp = await fetch('recepcao.php', { method: 'POST', body: formData });
                if (resp.ok) window.location.reload();
            } catch (e) {
                alert('Erro ao atualizar prioridade.');
            }
        }

        // Modal de Detalhes do Periciado
        function mostrarModalDetalhes(dados) {
            pacienteDetalheAtualId = dados.id || 0;
            document.getElementById('modal-nome').textContent = dados.nome || '---';
            document.getElementById('modal-cpf').textContent = dados.cpf || '---';
            document.getElementById('modal-processo').textContent = dados.processo || '---';
            document.getElementById('modal-perito').textContent = dados.perito || '---';
            document.getElementById('modal-data').textContent = dados.data_pauta || '---';
            document.getElementById('modal-chegada').textContent = dados.chegada_em || '---';
            
            // Destaque nos botões de prioridade do modal
            const prioAtual = dados.prioridade || 'normal';
            const bNormal = document.getElementById('btn-prio-modal-normal');
            const bPrio = document.getElementById('btn-prio-modal-prioridade');
            const bSuper = document.getElementById('btn-prio-modal-super');
            if (bNormal && bPrio && bSuper) {
                bNormal.className = prioAtual === 'normal' 
                    ? 'px-2.5 py-1 text-xs rounded-lg border border-gray-500 bg-gray-700 text-white font-bold transition'
                    : 'px-2.5 py-1 text-xs rounded-lg border border-gray-700 bg-gray-800 text-gray-400 hover:text-white transition';
                bPrio.className = prioAtual === 'prioridade'
                    ? 'px-2.5 py-1 text-xs rounded-lg border border-sky-400 bg-sky-900 text-white font-bold transition shadow-sm'
                    : 'px-2.5 py-1 text-xs rounded-lg border border-sky-600/40 bg-sky-950/60 text-sky-400 hover:bg-sky-900/60 transition';
                bSuper.className = prioAtual === 'superprioridade'
                    ? 'px-2.5 py-1 text-xs rounded-lg border border-amber-400 bg-amber-900 text-white font-bold transition shadow-sm'
                    : 'px-2.5 py-1 text-xs rounded-lg border border-amber-600/40 bg-amber-950/60 text-amber-400 hover:bg-amber-900/60 transition';
            }

            const statusEl = document.getElementById('modal-status');
            statusEl.innerHTML = '';
            const st = dados.status;
            let badge = '';
            if (st === 'concluido') {
                badge = '<span class="bg-gray-850 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full border border-gray-700 uppercase font-bold">Concluído</span>';
            } else if (st === 'atendimento') {
                badge = '<span class="bg-blue-950 text-blue-400 text-[10px] px-2.5 py-0.5 rounded-full border border-blue-900/50 uppercase font-bold">Em Exame</span>';
            } else if (st === 'chamada') {
                badge = '<span class="bg-amber-950/40 text-amber-400 text-[10px] px-2.5 py-0.5 rounded-full border border-amber-900/40 uppercase font-bold">Chamado</span>';
            } else if (st === 'presente' || st === 'fila') {
                badge = '<span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-bold">Aguardando</span>';
            } else if (st === 'desistente') {
                badge = '<span class="bg-rose-950 text-rose-400 text-[10px] px-2.5 py-0.5 rounded-full border border-rose-900/50 uppercase font-bold">Desistente</span>';
            } else {
                badge = '<span class="bg-gray-900 text-gray-500 text-[10px] px-2.5 py-0.5 rounded-full border border-gray-800 uppercase font-bold">Agendado</span>';
            }
            statusEl.innerHTML = badge;

            const boxFila = document.getElementById('modal-fila-destaque');
            if (boxFila) {
                if (dados.posicao_perito && Number(dados.posicao_perito) > 0) {
                    const naFrente = Number(dados.pessoas_na_frente || 0);
                    const prioExtra = dados.atender_prioridade ? ' • ♿ Perito priorizando preferenciais' : ' • ⚪ Perito na ordem padrão';
                    const criterioTexto = (dados.criterio_fila === 'horario' 
                        ? '🕒 Fila do médico organizada por Horário Agendado (PJe)' 
                        : '🏃 Fila do médico organizada por Ordem de Chegada') + prioExtra;
                    const fraseFrente = naFrente === 0 
                        ? '🟢 Próximo a ser chamado (0 pessoas na frente)' 
                        : `📍 ${naFrente} pessoa(s) na frente dele(a)`;
                    const jaChamadoBadge = Number(dados.chamadas_count || 0) > 0 
                        ? `<span class="bg-amber-950 border border-amber-800 text-amber-300 text-[10px] font-bold px-2 py-0.5 rounded">⚠️ Já chamado ${dados.chamadas_count}x</span>` 
                        : '';
                    boxFila.innerHTML = `
                        <div class="bg-blue-950/40 border border-blue-900/60 p-3.5 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="block text-[10px] uppercase font-bold text-blue-400 tracking-wider">Posição na Fila do Médico</span>
                                <div class="text-base font-black text-white mt-0.5">${dados.posicao_perito}º lugar da fila</div>
                                <span class="text-xs text-gray-200 block mt-0.5 font-medium">${fraseFrente}</span>
                                <span class="text-[10px] text-gray-400 block mt-0.5 italic">${criterioTexto}</span>
                            </div>
                            <div>${jaChamadoBadge}</div>
                        </div>
                    `;
                    boxFila.classList.remove('hidden');
                } else {
                    boxFila.classList.add('hidden');
                    boxFila.innerHTML = '';
                }
            }

            const btnDecl = document.getElementById('modal-btn-declaracao');
            if (btnDecl) {
                if (dados.id && (dados.chegada_em || ['presente','chamada','atendimento','concluido'].includes(dados.status))) {
                    btnDecl.href = `declaracao_comparecimento.php?id=${dados.id}&print=1`;
                    btnDecl.classList.remove('hidden');
                } else {
                    btnDecl.classList.add('hidden');
                }
            }

            const modal = document.getElementById('modal-detalhes');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.firstElementChild.classList.remove('scale-95');
                modal.firstElementChild.classList.add('scale-100');
            }, 10);
        }

        function fecharModalDetalhes() {
            const modal = document.getElementById('modal-detalhes');
            modal.firstElementChild.classList.remove('scale-100');
            modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 150);
        }

        // Vai direto para o dia de hoje
        function irParaHoje() {
            const input = document.getElementById('data-filtro-input');
            if (input) {
                input.value = '<?= date('Y-m-d') ?>';
                input.form.submit();
            }
        }

        // Filtra a fila de espera da recepção por médico
        function filtrarFilaPerito(medico) {
            const rows = document.querySelectorAll('.row-presente');
            const select = document.getElementById('filtro-perito-select');
            if (select && select.value !== (medico || '')) {
                select.value = medico || '';
            }
            rows.forEach(r => {
                r.style.display = (!medico || r.getAttribute('data-perito') === medico) ? '' : 'none';
            });
        }

        function reancorarEventosPresentes() {
            const chkAll = document.getElementById('chk-select-all-presentes');
            const chkItems = document.querySelectorAll('.chk-presente-item');
            const btnRemover = document.getElementById('btn-remover-presentes');
            function toggleBtn() {
                if (btnRemover) {
                    const checkedCount = document.querySelectorAll('.chk-presente-item:checked').length;
                    btnRemover.disabled = checkedCount === 0;
                }
            }
            if (chkAll) {
                chkAll.addEventListener('change', function() {
                    chkItems.forEach(chk => {
                        chk.checked = this.checked && chk.closest('tr').style.display !== 'none';
                    });
                    toggleBtn();
                });
            }
            chkItems.forEach(item => {
                item.addEventListener('change', function() {
                    if (!this.checked && chkAll) {
                        chkAll.checked = false;
                    } else if (chkAll) {
                        const allChecked = document.querySelectorAll('.chk-presente-item:checked').length === chkItems.length;
                        chkAll.checked = allChecked;
                    }
                    toggleBtn();
                });
            });
        }

        // Polling em tempo real para sincronizar os dados dos peritos e salas
        async function sincronizarPeritosRecepcao() {
            try {
                const resp = await fetch(`api_recepcao_peritos.php?data=<?= urlencode($dataFiltro) ?>`, { cache: 'no-store' });
                if (!resp.ok) return;
                const data = await resp.json();
                if (!data || !data.success || !data.peritos) return;
                const container = document.getElementById('cards-peritos-container');
                if (!container) return;
                data.peritos.forEach(rp => {
                    const card = container.querySelector(`.card-perito[data-perito="${rp.perito}"]`);
                    if (card) {
                        const badgeFila = card.querySelector('.bg-blue-950\/70');
                        if (badgeFila) badgeFila.textContent = `Fila: ${rp.fila.length}`;
                    }
                });
            } catch (_) {}
        }
        setInterval(sincronizarPeritosRecepcao, 4000);
    </script>

    <!-- Modal de Detalhes do Periciado -->
    <div id="modal-detalhes" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
                <h3 class="text-sm font-bold uppercase tracking-wider text-blue-500 flex items-center gap-2">
                    🔎 Detalhes do Agendamento
                </h3>
                <button type="button" onclick="fecharModalDetalhes()" class="text-gray-400 hover:text-white transition text-lg">&times;</button>
            </div>
            <div class="p-6 space-y-4 text-sm text-gray-300">
                <div class="grid grid-cols-2 gap-4">
                    <div id="modal-fila-destaque" class="col-span-2 hidden"></div>
                    <div class="col-span-2">
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Nome do Periciado</span>
                        <strong id="modal-nome" class="text-base text-white font-black block mt-0.5">---</strong>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">CPF</span>
                        <span id="modal-cpf" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Número do Processo</span>
                        <span id="modal-processo" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Médico Perito</span>
                        <span id="modal-perito" class="text-white font-medium block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Data da Pauta</span>
                        <span id="modal-data" class="text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Status Atual</span>
                        <span id="modal-status" class="block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Horário de Chegada</span>
                        <span id="modal-chegada" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                    <div class="col-span-2 pt-2 border-t border-gray-800">
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider mb-1.5">Classificação de Prioridade Legal</span>
                        <div class="flex items-center gap-1.5 flex-wrap" id="modal-botoes-prioridade">
                            <button type="button" onclick="alterarPrioridadeRapida('normal')" id="btn-prio-modal-normal" class="px-2.5 py-1 text-xs rounded-lg border border-gray-700 bg-gray-800 text-gray-300 hover:text-white transition">⚪ Normal</button>
                            <button type="button" onclick="alterarPrioridadeRapida('prioridade')" id="btn-prio-modal-prioridade" class="px-2.5 py-1 text-xs rounded-lg border border-sky-600/50 bg-sky-950/60 text-sky-300 hover:bg-sky-900/60 transition">♿ Prioridade (60+, PCD)</button>
                            <button type="button" onclick="alterarPrioridadeRapida('superprioridade')" id="btn-prio-modal-super" class="px-2.5 py-1 text-xs rounded-lg border border-amber-600/50 bg-amber-950/60 text-amber-300 hover:bg-amber-900/60 transition">⭐ Superprioridade (80+)</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-800 bg-gray-950/40 flex justify-between items-center">
                <a id="modal-btn-declaracao" href="#" target="_blank" class="hidden bg-indigo-950/80 hover:bg-indigo-900 border border-indigo-700/80 text-indigo-300 text-xs font-semibold px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                    🖨️ Declaração de Comparecimento
                </a>
                <button type="button" onclick="fecharModalDetalhes()" class="bg-gray-850 hover:bg-gray-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition border border-gray-750">
                    Fechar Detalhes
                </button>
            </div>
        </div>
    </div>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
</body>
</html>
