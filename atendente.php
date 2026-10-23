<?php
/**
 * PAINEL DE CHAMADA DO PERITO - JFAL
 * 
 * atendente.php: Interface utilizada pelos peritos médicos do Tribunal para chamar pacientes.
 * Funcionalidades:
 * - Acesso autenticado, com autorização pelo identificador do profissional.
 * - Escolha de nome próprio (filtrado apenas pelos peritos escalados no dia).
 * - Definição da sala de atendimento (salva na sessão do navegador).
 * - Ações de controle da chamada: Chamar Próximo (FIFO), Chamar Novamente (Repetir voz) e Concluir.
 * - Fila de espera detalhada do perito e histórico dos últimos atendimentos daquela sala.
 */


require_once __DIR__ . '/banco.php';

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

// Carrega ou altera o nome do perito selecionado
if ($user_role === 'perito') {
    $nome_cadastro = $_SESSION['usuario_nome'] ?? $_SESSION['usuario_name'];
    $perito_selecionado = $nome_cadastro;
    
    $_SESSION['perito_selecionado'] = $perito_selecionado;
    
    // Auto-carrega a sala salva para este perito se não estiver na sessão
    if (!isset($_SESSION['sala']) && $perito_selecionado) {
        $stmt = $pdo->prepare("SELECT sala FROM salas_peritos WHERE perito = ?");
        $stmt->execute([$perito_selecionado]);
        $saved_sala = $stmt->fetchColumn();
        if ($saved_sala) {
            $_SESSION['sala'] = $saved_sala;
        }
    }
} else {
    $perito_selecionado = $_SESSION['perito_selecionado'] ?? '';
    if (isset($_POST['set_perito'])) {
        $perito_selecionado = trim($_POST['perito_selecionado'] ?? '');
        $_SESSION['perito_selecionado'] = $perito_selecionado;
        
        // Se selecionou um perito, carrega a última sala vinculada a ele no banco de dados
        if ($perito_selecionado) {
            $stmt = $pdo->prepare("SELECT sala FROM salas_peritos WHERE perito = ?");
            $stmt->execute([$perito_selecionado]);
            $saved_sala = $stmt->fetchColumn();
            if ($saved_sala) {
                $_SESSION['sala'] = $saved_sala;
            }
        }
    }
}

// Verifica se o nome completo do perito logado existe na pauta de hoje
$nome_nao_encontrado_pauta = false;
if ($user_role === 'perito' && $perito_selecionado) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?))");
    $stmt->execute([$ownerId, $ownerAdmin, $perito_selecionado]);
    if ($stmt->fetchColumn() == 0) {
        $nome_nao_encontrado_pauta = true;
    }
}

// Consulta todas as salas cadastradas no banco de dados para a escolha do perito
$salas_lista = $pdo->query("SELECT nome FROM salas ORDER BY nome ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($salas_lista)) {
    $salas_lista = ['Sala 1', 'Sala 2', 'Sala 3', 'Sala 4'];
}

// Carrega ou altera a sala ativa do perito
$sala = $_SESSION['sala'] ?? '';
if (!in_array($sala, $salas_lista)) {
    $sala = $salas_lista[0];
    $_SESSION['sala'] = $sala;
}

if (isset($_POST['set_sala'])) {
    $sala_post = trim($_POST['sala'] ?? '');
    if (in_array($sala_post, $salas_lista)) {
        $sala = $sala_post;
        $_SESSION['sala'] = $sala;
        
        // Salva a associação da sala com o perito no banco de dados
        if ($perito_selecionado) {
            $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala) VALUES (?, ?) ON DUPLICATE KEY UPDATE sala = ?, atualizado_em = CURRENT_TIMESTAMP");
            $stmt->execute([$perito_selecionado, $sala, $sala]);
        }
    }
}

$message = '';
$message_type = '';

// Toda transição é autorizada e serializada no serviço.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $message = agirAtendimento($pdo, (string)$_POST['action'], $perito_selecionado, $sala, $_POST);
        $message_type = 'success';
    } catch (DomainException $e) {
        $message = $e->getMessage(); $message_type = 'error';
    } catch (Throwable $e) {
        $message = erroPublico($e); $message_type = 'error';
    }
    $_SESSION['flash_atendimento'] = [$message, $message_type];
    header('Location: atendente.php', true, 303); exit;
}
[$message, $message_type] = $_SESSION['flash_atendimento'] ?? ['', ''];
unset($_SESSION['flash_atendimento']);

// Consulta todos os peritos escalados para trabalhar HOJE (vinculados à pauta importada)
$peritos_lista = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != '' AND data_pauta = CURRENT_DATE() ORDER BY perito ASC")->fetchAll(PDO::FETCH_COLUMN);

// Consulta o paciente que está sendo atendido ou chamado nesta sala de perícia no momento
$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY chamado_em DESC LIMIT 1");
$stmt->execute([$sala, $ownerId, $ownerAdmin, $perito_selecionado]);
$atendimento_atual = $stmt->fetch();

// Calcula a contagem de fila de espera
$fila_espera = 0;
if ($perito_selecionado) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE status = 'presente' AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?))");
    $stmt->execute([$ownerId, $ownerAdmin, $perito_selecionado]);
    $fila_espera = $stmt->fetchColumn();
} else {
    $fila_espera = $pdo->query("SELECT COUNT(*) FROM atendimentos WHERE status = 'presente' AND data_pauta = CURRENT_DATE()")->fetchColumn();
}

// Obtém a lista dos pacientes agendados para este perito que já chegaram (aguardando)
$presentes_perito = [];
if ($perito_selecionado) {
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'presente' AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY fila_em ASC, id ASC");
    $stmt->execute([$ownerId, $ownerAdmin, $perito_selecionado]);
    $presentes_perito = $stmt->fetchAll();
}

// Lista os últimos atendimentos chamados por esta sala de perícia hoje (histórico local)
$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE sala = ? AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY id DESC LIMIT 5");
$stmt->execute([$sala, $ownerId, $ownerAdmin, $perito_selecionado]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel do Perito - <?= escapar(SYS_NAME) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="flex items-center space-x-3 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/30 px-3 py-1.5 rounded-lg transition">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-emerald-400 hover:text-emerald-300 border border-emerald-900/60 bg-emerald-950/30 px-3 py-1.5 rounded-lg transition">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/30 px-3 py-1.5 rounded-lg transition">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-sky-400 hover:text-sky-300 border border-sky-900/60 bg-sky-950/30 px-3 py-1.5 rounded-lg transition">Pautas</a>
                <a href="salas.php" class="text-xs text-pink-400 hover:text-pink-300 border border-pink-900/60 bg-pink-950/30 px-3 py-1.5 rounded-lg transition">Salas</a>
                <a href="auditoria.php" class="text-xs text-teal-400 hover:text-teal-300 border border-teal-900/60 bg-teal-950/30 px-3 py-1.5 rounded-lg transition">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-indigo-400 hover:text-indigo-300 border border-indigo-900/60 bg-indigo-950/30 px-3 py-1.5 rounded-lg transition">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <a href="alterar_senha_obrigatorio.php" class="text-sm underline">Minha senha</a>
            <form method="POST" action="logout.php" class="sga-logout"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Controles Lateral (Identificação e Escolha de Sala) -->
        <div class="space-y-6">
            <!-- Seleção de Nome do Perito (Dropdown dinâmico de hoje) -->
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

            <!-- Seleção e Edição do Número da Sala -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Sala de Atendimento</h2>
                <form method="POST" class="space-y-3"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                    <input type="hidden" name="set_sala" value="1">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Escolha a Sala</label>
                        <select name="sala" onchange="this.form.submit()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            <?php foreach ($salas_lista as $s_nome): ?>
                                <option value="<?= escapar($s_nome) ?>" <?= $sala === $s_nome ? 'selected' : '' ?>>
                                    <?= escapar($s_nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Contador da fila de espera -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl text-center">
                <h2 class="text-xs uppercase font-bold text-gray-400 tracking-widest mb-2">Seus Pacientes Aguardando</h2>
                <div class="text-5xl font-black text-amber-500 my-4"><?= $fila_espera ?></div>
                <p class="text-xs text-gray-400">pacientes confirmados na recepção</p>
            </div>
        </div>

        <!-- Área Central (Paciente Ativo, Ações de Chamada e Fila) -->
        <div class="md:col-span-2 space-y-6">
            <?php if ($nome_nao_encontrado_pauta): ?>
                <div class="bg-yellow-950/40 border border-yellow-900/80 text-yellow-300 p-4 rounded-xl text-xs leading-normal mb-6">
                    ⚠️ <strong>Aviso:</strong> O seu Nome Completo cadastrado (<strong><?= escapar($perito_selecionado) ?></strong>) não foi localizado em nenhum atendimento da pauta de hoje. Solicite à supervisão a conferência do vínculo do profissional na pauta do dia.
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div role="status" aria-live="polite" class="sga-feedback p-4 rounded-xl border text-sm text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= escapar($message) ?>
                </div>
            <?php endif; ?>

            <!-- Informações do Paciente em Atendimento na Sala -->
            <div class="bg-gray-900 p-8 rounded-3xl border border-gray-800 shadow-xl flex flex-col items-center justify-center text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-blue-600"></div>
                <h2 class="text-xs uppercase font-bold text-gray-400 tracking-wider mb-4">Periciado em Atendimento</h2>
                
                <?php if ($atendimento_atual): ?>
                    <div class="text-4xl font-black text-white mb-2"><?= escapar($atendimento_atual['nome'] ?: 'Sem Nome') ?></div>
                    <div class="text-xl font-bold text-blue-400 font-mono mb-2">Senha: <?= escapar($atendimento_atual['senha']) ?></div>
                    <div class="text-xs text-gray-400 mb-6 font-mono">Processo: <?= escapar($atendimento_atual['processo'] ?: 'Sem processo') ?></div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 w-full max-w-xl">
                        <?php if ($atendimento_atual['status'] === 'chamada'): ?>
                            <!-- Paciente chamado, aguardando entrada na sala -->
                            <form method="POST" class="flex-1"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="chamar_novamente">
                                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-3 rounded-xl text-xs transition">
                                    📢 Chamar Novamente (<?= $atendimento_atual['chamadas_count'] ?>)
                                </button>
                            </form>
                            <form method="POST" class="flex-1"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="nao_compareceu">
                                <button type="submit" onclick="return confirm('Marcar este paciente como ausente (não compareceu)? Ele voltará para a sua fila de espera.');" class="w-full bg-rose-700 hover:bg-rose-800 text-white font-semibold py-3 px-3 rounded-xl text-xs transition">
                                    ❌ Não Compareceu
                                </button>
                            </form>
                            <form method="POST" class="flex-1"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="iniciar_atendimento">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white font-semibold py-3 px-3 rounded-xl text-xs transition font-bold">
                                    🚀 Iniciar Perícia
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Paciente em perícia (status 'atendimento') -->
                            <form method="POST" class="flex-1 sm:col-span-2"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                <input type="hidden" name="action" value="concluir">
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl text-xs transition">
                                    ✓ Concluir Perícia
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-2xl font-bold text-gray-500 my-8">Nenhum atendimento em andamento</div>
                <?php endif; ?>

                <hr class="w-full border-gray-800 my-6">

                <?php if ($atendimento_atual): ?><p id="orientacao-atendimento" class="sga-helper mb-3">Conclua o atendimento atual antes de chamar outra pessoa.</p><?php endif; ?>
                <!-- Botão Principal: Chamar o próximo da fila de espera -->
                <form method="POST" class="w-full max-w-sm"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="chamar_proximo">
                    <button type="submit" <?= $atendimento_atual ? 'disabled aria-describedby="orientacao-atendimento"' : '' ?> class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-2xl text-base shadow-lg hover:shadow-blue-500/20 transition transform hover:-translate-y-0.5">
                        🔊 Chamar Próximo da Fila
                    </button>
                </form>
            </div>

            <!-- Fila de Espera Detalhada deste Médico hoje -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="sga-toolbar mb-4"><h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider">Sua fila de espera</h3><a href="atendente.php" class="sga-neutral">Atualizar fila</a></div>
                <div class="overflow-x-auto max-h-[200px] overflow-y-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2">Senha</th>
                                <th class="pb-2">Nome / Processo</th>
                                <th class="pb-2">Chegada</th>
                                <th class="pb-2 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php if (empty($presentes_perito)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-xs text-gray-500">Nenhum paciente seu na fila de espera.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($presentes_perito as $p): ?>
                                    <tr>
                                        <td class="py-3 font-mono font-bold text-blue-400"><?= escapar($p['senha']) ?></td>
                                        <td class="py-3">
                                            <div class="font-semibold text-white"><?= escapar($p['nome']) ?></div>
                                            <div class="text-[10px] text-gray-500 font-mono"><?= escapar($p['processo']) ?></div>
                                        </td>
                                        <td class="py-3 text-xs text-gray-400"><?= date('H:i:s', strtotime($p['chegada_em'])) ?></td>
                                        <td class="py-3 text-right">
                                            <form method="POST" class="inline" onsubmit="return confirm('Chamar este periciado fora da fila?');"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                                                <input type="hidden" name="action" value="chamar_especifico">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold px-2.5 py-1 rounded transition">
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

            <!-- Chamada Manual/Avulsa em caso de emergência ou desvio de fila -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Chamada Manual / Avulsa</h3>
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3"><?= csrfCampo() ?><input type="hidden" name="atendimento_id" value="<?= (int)($atendimento_atual['id'] ?? 0) ?>">
                    <input type="hidden" name="action" value="chamar_avulsa">
                    <input type="text" value="Gerada automaticamente" disabled aria-label="Senha gerada automaticamente" placeholder="Senha (ex: M-101)" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    <input type="text" name="nome_avulso" required maxlength="100" placeholder="Nome completo do periciado" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-medium">
                    <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 text-xs font-semibold py-2 rounded-lg transition">
                        Chamar Manual
                    </button>
                </form>
            </div>

            <!-- Histórico Local dos últimos atendimentos desta sala -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Seus Últimos Atendimentos</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2">Senha</th>
                                <th class="pb-2">Nome</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2 text-right">Chamado às</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-xs text-gray-500">Nenhum atendimento realizado por esta sala hoje.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="py-3 font-semibold text-blue-400"><?= escapar($h['senha']) ?></td>
                                        <td class="py-3 text-white font-medium"><?= escapar($h['nome'] ?: 'Sem nome') ?></td>
                                        <td class="py-3">
                                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold <?= $h['status'] === 'chamada' ? 'bg-blue-950 text-blue-400' : 'bg-gray-850 text-gray-400' ?>">
                                                <?= ucfirst(escapar($h['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right text-xs text-gray-500"><?= date('H:i:s', strtotime($h['chamado_em'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Rodapé fora da área dos controles -->
    <footer class="py-4 px-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body>
</html>
