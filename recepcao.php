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
[$message, $message_type] = $_SESSION['flash_recepcao'] ?? ['', ''];
unset($_SESSION['flash_recepcao']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    try {
        if ($action === 'importar_pdf' || $action === 'importar_excel') {
            if (!in_array($_SESSION['usuario_role'], ['admin','supervisor'], true)) throw new DomainException('Acesso negado.');
            require_once __DIR__ . '/importacao_service.php';
            $file = $_FILES['arquivo_excel'] ?? $_FILES['arquivo_pdf'] ?? [];
            $_SESSION['importacao_previa'] = ['criada'=>time(), 'registros'=>prepararImportacao($file)];
            header('Location: preview_importacao.php', true, 303); exit;
        } elseif (in_array($action, ['confirmar_chegada','confirmar_multiplos','confirmar_chegada_lote'], true)) {
            $ids = isset($_POST['id']) ? [(int)$_POST['id']] : (array)($_POST['selecionados'] ?? []);
            $count = confirmarPresencas($pdo,$ids,$_SESSION['usuario_role']);
            $message = "Presença confirmada para $count novos registros.";
            if ($action === 'confirmar_chegada' && $count === 1) {
                $s = $pdo->prepare('SELECT nome, senha FROM atendimentos WHERE id=?'); $s->execute([(int)$_POST['id']]); $confirmado=$s->fetch();
                $message = "Presença confirmada: {$confirmado['nome']}. Senha: {$confirmado['senha']}.";
            }
        } else {
            $message = operarPauta($pdo,$action,$_POST,$dataFiltro);
        }
        $message_type = 'success';
    } catch (DomainException $e) { $message = $e->getMessage(); $message_type = 'error'; }
    catch (Throwable $e) { $message = erroPublico($e); $message_type = 'error'; }
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

$pauta_liberada = isPautaLiberada($pdo, $dataFiltro);
$permite_checkin = $dataFiltro === date('Y-m-d') && (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $dataFiltro === date('Y-m-d') && $pauta_liberada));

// Recepção comum não visualiza dados de pautas que não foram homologadas/liberadas pelo supervisor
$agendados = [];
$presentes = [];
$chamados = [];
$concluidos = [];

if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $pauta_liberada)) {
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'agendado' AND data_pauta = ? ORDER BY perito ASC, nome ASC, id ASC");
    $stmt->execute([$dataFiltro]);
    $agendados = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'presente' AND data_pauta = ? ORDER BY chegada_em ASC");
    $stmt->execute([$dataFiltro]);
    $presentes = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada','atendimento') AND data_pauta = ? ORDER BY chamado_em DESC");
    $stmt->execute([$dataFiltro]);
    $chamados = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'concluido' AND data_pauta = ? ORDER BY chamado_em DESC");
    $stmt->execute([$dataFiltro]);
    $concluidos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Recepção - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js"></script>
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
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
            <form method="POST" action="logout.php" class="sga-logout"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 <?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 'grid grid-cols-1 lg:grid-cols-3 gap-6' : 'space-y-6' ?>">
        <!-- Área de Listas (Fila de Hoje) -->
        <div class="sga-queue <?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 'lg:col-span-2' : '' ?> space-y-6">
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

                    <!-- Alerta de agendamentos em outras datas -->
                    <div id="alerta-outras-datas" class="hidden mb-4 p-4 rounded-xl border bg-blue-950/40 border-blue-900/60 text-blue-300 text-xs space-y-2 animate-fade-in">
                        <div class="font-bold flex items-center gap-1.5 uppercase text-blue-400">
                            <span>📅</span> Agendamento Encontrado em Outra Data!
                        </div>
                        <div id="lista-outras-datas" class="space-y-1.5 font-medium">
                            <!-- Injetado via JS -->
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
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
                                        <tr data-nome="<?= escapar($item['nome']) ?>" onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: <?= jsValor($item['nome']) ?>, cpf: <?= jsValor($item['cpf'] ?? '---') ?>, processo: <?= jsValor($item['processo']) ?>, perito: <?= jsValor($item['perito']) ?>, data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: <?= jsValor($item['status']) ?>, chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="row-agendado cursor-pointer hover:bg-gray-850/40 transition">
                                            <?php if ($permite_checkin): ?>
                                                <td class="sga-select py-3">
                                                    <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" aria-label="Selecionar <?= escapar($item['nome']) ?>" class="chk-item rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                                </td>
                                            <?php endif; ?>
                                            <td class="sga-patient py-3">
                                                <div class="font-semibold text-white truncate max-w-[200px]"><?= escapar($item['nome']) ?></div>
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
                                                    <button type="button" onclick="confirmarChegada(<?= $item['id'] ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 py-1 rounded transition">
                                                        Confirmar Chegada
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                    <button type="button" onclick="removerIndividual(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['status']) ?>)" class="bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        <?= $item['status'] === 'agendado' ? 'Cancelar' : 'Desfazer presença' ?>
                                                    </button>
                                                    <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['processo']) ?>, <?= jsValor($item['perito']) ?>, <?= jsValor($item['cpf'] ?? '') ?>)" aria-label="Editar agendamento de <?= escapar($item['nome']) ?>" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
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

            <!-- 2. Presentes (Fila de espera ativa para chamada do perito) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-emerald-400 tracking-wider mb-4 flex justify-between items-center">
                    <span>Aguardando Chamada (Fila de Espera)</span>
                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                    <div class="flex flex-col sm:flex-row gap-3 mb-4">
                        <button type="submit" name="action" value="remover_multiplos" id="btn-remover-presentes" disabled onclick="return confirm('Retornar todos os periciados selecionados para a lista de agendados?');" class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                            🔄 Voltar Selecionados para Agendados
                        </button>
                    </div>
                    <?php endif; ?>
                </h2>

                    <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                        <th class="pb-2 w-8">
                                            <input type="checkbox" id="chk-select-all-presentes" aria-label="Selecionar pessoas na fila" class="rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                        </th>
                                    <?php endif; ?>
                                    <th class="pb-2">Senha</th>
                                    <th class="pb-2">Nome / Processo</th>
                                    <th class="pb-2">Perito</th>
                                    <th class="pb-2">Chegada</th>
                                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                        <th class="pb-2 text-right">Ação</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                                <?php if (empty($presentes)): ?>
                                    <tr>
                                        <td colspan="<?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 6 : 4 ?>" class="py-4 text-center text-xs text-gray-500">Ninguém aguardando no momento.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($presentes as $item): ?>
                                        <tr data-nome="<?= escapar($item['nome']) ?>" onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: <?= jsValor($item['nome']) ?>, cpf: <?= jsValor($item['cpf'] ?? '---') ?>, processo: <?= jsValor($item['processo']) ?>, perito: <?= jsValor($item['perito']) ?>, data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: <?= jsValor($item['status']) ?>, chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="cursor-pointer hover:bg-gray-850/40 transition">
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                <td class="py-3">
                                                    <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" aria-label="Selecionar <?= escapar($item['nome']) ?> na fila" class="chk-presente-item rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                                </td>
                                            <?php endif; ?>
                                            <td class="py-3 font-mono font-bold text-blue-400"><?= escapar($item['senha']) ?></td>
                                            <td class="py-3">
                                                <div class="font-semibold text-white truncate max-w-[200px]"><?= escapar($item['nome']) ?></div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= escapar($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-emerald-400 ml-1.5">| CPF: <?= escapar($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-xs text-gray-300 truncate max-w-[120px]"><?= escapar($item['perito'] ?: 'Não associado') ?></td>
                                            <td class="py-3 text-xs text-gray-400"><?= date('H:i:s', strtotime($item['chegada_em'])) ?></td>
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                <td class="py-3 text-right flex items-center justify-end gap-1.5">
                                                    <button type="button" onclick="removerIndividual(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['status']) ?>)" class="bg-gray-800 hover:bg-gray-750 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        <?= $item['status'] === 'agendado' ? 'Cancelar' : 'Desfazer presença' ?>
                                                    </button>
                                                    <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, <?= jsValor($item['nome']) ?>, <?= jsValor($item['processo']) ?>, <?= jsValor($item['perito']) ?>, <?= jsValor($item['cpf'] ?? '') ?>)" aria-label="Editar agendamento de <?= escapar($item['nome']) ?>" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        ✏️
                                                    </button>
                                                </td>
                                            <?php endif; ?>
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
                                <div class="bg-gray-950/30 border border-gray-800/40 p-3 rounded-xl flex justify-between items-center opacity-70">
                                    <div class="truncate flex-1 pr-2">
                                        <div class="text-xs font-semibold text-gray-300 truncate"><?= escapar($item['nome']) ?></div>
                                        <div class="text-[10px] text-gray-500 truncate">Perito: <?= escapar($item['perito']) ?></div>
                                        <div class="text-[10px] text-gray-500 mt-0.5"><?= escapar($item['sala']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-gray-400"><?= escapar($item['senha']) ?></div>
                                        <div class="text-[9px] text-gray-600 font-semibold">Fim: <?= date('H:i', strtotime($item['chamado_em'])) ?></div>
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
                    <input type="text" name="nome" id="edit-nome" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Processo</label>
                    <input type="text" name="processo" id="edit-processo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">CPF do Paciente</label>
                    <input type="text" name="cpf" id="edit-cpf" placeholder="000.000.000-00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Perito</label>
                    <input type="text" name="perito" id="edit-perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
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
        <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
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
                    <span>📺</span> Vídeo de Fundo da TV
                </h2>
                <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="set_yt_url">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Link do Vídeo (YouTube)</label>
                        <input type="url" name="yt_url" value="<?= escapar($yt_url) ?>" required placeholder="https://www.youtube.com/watch?v=..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Atualizar Vídeo
                    </button>
                </form>
                <p class="text-[10px] text-gray-500 mt-3 leading-normal">
                    ⚠️ <strong>Nota:</strong> Certifique-se de usar um vídeo público do YouTube que permita reprodução incorporada (incorporação). Vídeos com restrições de idade ou proteção contra bots não carregarão no painel.
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
            btnExcluir.disabled = checkedCount === 0;
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
                    chkSelectAll.checked = false;
                } else {
                    const visiveis = [...chkItems].filter(c => c.closest('tr').style.display !== 'none');
                    const allChecked = visiveis.length > 0 && visiveis.every(c => c.checked);
                    chkSelectAll.checked = allChecked;
                }
                toggleBulkDeleteButton();
            });
        });

        // Confirma chegada individual preenchendo formulário oculto
        function confirmarChegada(id) {
            document.getElementById('chegada-id').value = id;
            document.getElementById('form-chegada-unica').submit();
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

                // Busca em outras datas via AJAX
                clearTimeout(timeoutBuscaOutroDia);
                const alertaEl = document.getElementById('alerta-outras-datas');
                const listaEl = document.getElementById('lista-outras-datas');
                
                if (query.length >= 3) {
                    timeoutBuscaOutroDia = setTimeout(() => {
                        fetch(`api_verificar_outro_dia.php?busca=${encodeURIComponent(query)}&hoje=<?= $dataFiltro ?>`)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    listaEl.innerHTML = '';
                                    data.forEach(item => {
                                        const div = document.createElement('div');
                                        div.className = 'bg-blue-900/30 border border-blue-800/40 p-2.5 rounded-lg flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5';
                                        const texto = document.createElement('span');
                                        texto.textContent = `${item.nome} | Perito: ${item.perito || 'Não associado'} | ${item.status_formatado} | ${item.data_pauta_formatada}`;
                                        div.appendChild(texto);
                                        listaEl.appendChild(div);
                                    });
                                    alertaEl.classList.remove('hidden');
                                } else {
                                    alertaEl.classList.add('hidden');
                                    listaEl.innerHTML = '';
                                }
                            })
                            .catch(() => {
                                alertaEl.classList.add('hidden');
                            });
                    }, 350); // Debounce de 350ms
                } else {
                    alertaEl.classList.add('hidden');
                    listaEl.innerHTML = '';
                }
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
        function iniciarEdicao(id, nome, processo, perito, cpf) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nome').value = nome;
            document.getElementById('edit-processo').value = processo;
            document.getElementById('edit-cpf').value = cpf || '';
            document.getElementById('edit-perito').value = perito;
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

        // Modal de Detalhes do Periciado
        function mostrarModalDetalhes(dados) {
            document.getElementById('modal-nome').textContent = dados.nome || '---';
            document.getElementById('modal-cpf').textContent = dados.cpf || '---';
            document.getElementById('modal-processo').textContent = dados.processo || '---';
            document.getElementById('modal-perito').textContent = dados.perito || '---';
            document.getElementById('modal-data').textContent = dados.data_pauta || '---';
            document.getElementById('modal-chegada').textContent = dados.chegada_em || '---';
            
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
            } else {
                badge = '<span class="bg-gray-900 text-gray-500 text-[10px] px-2.5 py-0.5 rounded-full border border-gray-800 uppercase font-bold">Agendado</span>';
            }
            statusEl.innerHTML = badge;

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
                </div>
            </div>
            <div class="p-4 border-t border-gray-800 bg-gray-950/40 flex justify-end">
                <button type="button" onclick="fecharModalDetalhes()" class="bg-gray-850 hover:bg-gray-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition border border-gray-750">
                    Fechar Detalhes
                </button>
            </div>
        </div>
    </div>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body>
</html>
