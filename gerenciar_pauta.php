<?php
require_once 'banco.php';


// Proteção da página: apenas administradores e supervisores
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])) {
    header("Location: dashboard.php");
    exit;
}

$sys_name = SYS_NAME;

// Função auxiliar de senha PE-xxx

// Lógica de Processamento de Ações POST
$message = '';
$message_type = '';

require_once __DIR__ . '/pauta_service.php';
[$message, $message_type] = $_SESSION['flash_pauta'] ?? ['', ''];
unset($_SESSION['flash_pauta']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    try {
        if ($action === 'importar_pdf' || $action === 'importar_excel') {
            if (!in_array($_SESSION['usuario_role'], ['admin','supervisor'], true)) throw new DomainException('Acesso negado.');
            require_once __DIR__ . '/importacao_service.php';
            $file = $_FILES['arquivo_excel'] ?? $_FILES['arquivo_pdf'] ?? [];
            $janela = (string)($_POST['janela_importacao'] ?? 'dia');
            $sincronizar = isset($_POST['sincronizar_pauta']) ? (bool)$_POST['sincronizar_pauta'] : true;
            $dados = prepararImportacao($file, $janela);
            $resultado = importarRegistros($pdo, $dados['registros'], $dados['datas_escopo'], $sincronizar);
            $partes = [$resultado['novos'] . ' novos incluídos'];
            if (!empty($resultado['atualizados'])) $partes[] = $resultado['atualizados'] . ' atualizados';
            if (!empty($resultado['cancelados'])) $partes[] = $resultado['cancelados'] . ' cancelados da pauta';
            if (!empty($resultado['preservados'])) $partes[] = $resultado['preservados'] . ' preservados em andamento';
            if (!empty($resultado['usuarios'])) $partes[] = $resultado['usuarios'] . ' novos peritos pré-cadastrados';
            $message = 'Importação e sincronização concluídas: ' . implode('; ', $partes) . '.';
            $message_type = 'success';
            $_SESSION['flash_pauta'] = [$message, $message_type];
            $filtrosRetorno = array_intersect_key($_GET, array_flip(['filtro_tipo','data_busca','mes_busca']));
            header('Location: gerenciar_pauta.php' . ($filtrosRetorno ? '?' . http_build_query($filtrosRetorno) : ''), true, 303); exit;
        } elseif (in_array($action, ['confirmar_chegada','confirmar_multiplos','confirmar_chegada_lote'], true)) {
            $ids = isset($_POST['id']) ? [(int)$_POST['id']] : (array)($_POST['selecionados'] ?? []);
            $count = confirmarPresencas($pdo,$ids,$_SESSION['usuario_role']);
            $message = "Presença confirmada para $count novos registros.";
        } else {
            $message = operarPauta($pdo,$action,$_POST,date('Y-m-d'));
        }
        $message_type = 'success';
    } catch (DomainException $e) { $message = $e->getMessage(); $message_type = 'error'; }
    catch (Throwable $e) { $message = erroPublico($e); $message_type = 'error'; }
    $_SESSION['flash_pauta'] = [$message,$message_type];
    $filtrosRetorno = array_intersect_key($_GET, array_flip(['filtro_tipo','data_busca','mes_busca']));
    header('Location: gerenciar_pauta.php' . ($filtrosRetorno ? '?' . http_build_query($filtrosRetorno) : ''), true, 303); exit;
}



// Filtros de Visualização
$filtro_tipo = $_GET['filtro_tipo'] ?? 'dia'; // 'dia' ou 'mes'
$data_busca = $_GET['data_busca'] ?? date('Y-m-d');
$mes_busca = $_GET['mes_busca'] ?? date('Y-m');

// Consulta de Atendimentos
$atendimentos = [];
try {
    if ($filtro_tipo === 'dia') {
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE data_pauta = ? ORDER BY perito ASC, nome ASC, id ASC");
        $stmt->execute([$data_busca]);
        $atendimentos = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE DATE_FORMAT(data_pauta, '%Y-%m') = ? ORDER BY data_pauta ASC, perito ASC, nome ASC, id ASC");
        $stmt->execute([$mes_busca]);
        $atendimentos = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $message = "Erro ao buscar pauta: " . erroPublico($e);
    $message_type = 'error';
}

// Estatísticas Rápidas
$total = count($atendimentos);
$agendados = 0;
$fila = 0;
$finalizados = 0;
$cancelados = 0;
foreach ($atendimentos as $item) {
    if ($item['status'] === 'agendado') $agendados++;
    elseif ($item['status'] === 'presente' || $item['status'] === 'fila' || $item['status'] === 'chamada' || $item['status'] === 'atendimento') $fila++;
    elseif ($item['status'] === 'concluido') $finalizados++;
    elseif ($item['status'] === 'cancelado') $cancelados++;
}

// Lista de peritos para remanejamento facilitado
$peritos_cadastrados = [];
try {
    $stmt = $pdo->query("SELECT nome FROM usuarios WHERE role = 'perito' AND nome IS NOT NULL AND nome != '' ORDER BY nome ASC");
    $peritos_cadastrados = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pautas - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js"></script>
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3 shrink-0">
            <a href="dashboard.php" class="flex items-center space-x-2.5 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block leading-tight">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>
        <div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Lado Esquerdo: Formulários de Ações em Lote e Importação -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Importar Planilha Card -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-blue-400 tracking-wider mb-3">Importar Planilha (Excel)</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="var btn=this.querySelector('button[type=submit]'); if(btn){btn.disabled=true;btn.textContent='Importando e sincronizando...';}"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="importar_excel">
                    
                    <div>
                        <label class="block text-xs text-gray-300 font-semibold mb-1.5">Selecione a Planilha (.xlsx)</label>
                        <div class="border border-dashed border-gray-700 hover:border-blue-500 rounded-xl p-3 text-center bg-gray-950/60 transition cursor-pointer group" onclick="document.getElementById('arquivo_excel_input').click()">
                            <span class="text-base block">📊</span>
                            <span id="excel_file_label" class="block text-xs text-gray-300 mt-1 font-medium truncate group-hover:text-blue-400 transition">Clique para escolher o arquivo</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5">Formato oficial PJe (.xlsx)</span>
                            <input type="file" id="arquivo_excel_input" name="arquivo_excel" accept=".xlsx" required class="hidden" onchange="const l=document.getElementById('excel_file_label'); if(this.files && this.files[0]){ l.textContent=this.files[0].name; l.classList.add('text-blue-400','font-bold'); }">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Janela da Pauta</label>
                        <select name="janela_importacao" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                            <option value="dia" selected>📅 Apenas a pauta de hoje (1 dia)</option>
                            <option value="3dias">📅 Hoje e próximos 2 dias (Máximo 3 dias)</option>
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">Datas fora deste limite são descartadas automaticamente.</p>
                    </div>

                    <div class="pt-1">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="sincronizar_pauta" value="1" checked class="mt-0.5 rounded bg-gray-800 border-gray-700 text-blue-600 focus:ring-0">
                            <div>
                                <span class="text-xs text-gray-200 font-medium block">Sincronizar pauta diária</span>
                                <span class="text-[10px] text-gray-400 block leading-tight">Cancela agendamentos do sistema que foram retirados da nova planilha oficial. Presenças confirmadas hoje nunca são alteradas.</span>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        📥 Iniciar Importação
                    </button>
                </form>
            </div>

            <!-- Estatísticas do Período -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Estatísticas Filtradas</h2>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-white"><?= $total ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Total</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-blue-400"><?= $agendados ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Agendados</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-emerald-400"><?= $fila ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Presenças</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-gray-400"><?= $finalizados + $cancelados ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Concluídos</div>
                    </div>
                </div>
            </div>

            <!-- Transferir Pauta (Mover Data) -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Transferir / Mover Pauta</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('Confirmar o remanejamento da pauta de dados conforme os parâmetros informados?');"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="mover_pauta">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Mover quem?</label>
                        <select name="mover_tipo" id="mover-tipo" onchange="toggleMoverInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="selecionados">Selecionados na Tabela</option>
                            <option value="dia">Todo um Dia Específico</option>
                            <option value="mes">Todo um Mês Inteiro</option>
                        </select>
                    </div>

                    <div id="mover-origem-dia" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Dia de Origem</label>
                        <input type="date" name="mover_data_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="mover-origem-mes" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Mês de Origem</label>
                        <input type="month" name="mover_mes_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1" id="mover-destino-label">Nova Data de Destino</label>
                        <input type="date" name="nova_data" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="mover-selecionados-container"></div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        Transferir Agendamentos
                    </button>
                </form>
            </div>

            <!-- Alterar Perito em Lote -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Substituir Perito</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('Substituir o perito associado aos registros selecionados?');"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="alterar_perito_lote">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Alterar de onde?</label>
                        <select name="perito_tipo" id="perito-tipo" onchange="togglePeritoInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="selecionados">Selecionados na Tabela</option>
                            <option value="dia">De um Dia Inteiro</option>
                        </select>
                    </div>

                    <div id="perito-origem-dia" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Dia Alvo</label>
                        <input type="date" name="perito_data_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Novo Perito</label>
                        <input type="text" name="novo_perito" list="lista-peritos" required placeholder="Nome do Médico/Perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                        <datalist id="lista-peritos">
                            <?php foreach ($peritos_cadastrados as $pName): ?>
                                <option value="<?= escapar($pName) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div id="perito-selecionados-container"></div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        Substituir Perito
                    </button>
                </form>
            </div>

            <!-- Excluir Pauta em Lote -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl border-red-950/30">
                <h2 class="text-xs font-semibold uppercase text-red-400 tracking-wider mb-3">Cancelar Pauta</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('ATENÇÃO: Isso cancelará os agendamentos ainda não confirmados do dia ou mês preservando o histórico. Continuar?');"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="excluir_periodo">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Cancelar em qual período?</label>
                        <select name="periodo_tipo" id="excluir-tipo" onchange="toggleExcluirInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="dia">Todo um Dia Específico</option>
                            <option value="mes">Todo um Mês Inteiro</option>
                        </select>
                    </div>

                    <div id="excluir-origem-dia">
                        <label class="block text-xs text-gray-400 mb-1">Data Alvo</label>
                        <input type="date" name="data_alvo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="excluir-origem-mes" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Mês Alvo</label>
                        <input type="month" name="mes_alvo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <button type="submit" class="w-full bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs font-semibold py-2 rounded-lg transition">
                        Cancelar agendamentos pendentes
                    </button>
                </form>
            </div>
        </div>

        <!-- Lado Direito: Filtro e Tabela de Listagem -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Mensagens -->
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-xl text-sm border <?= $message_type === 'success' ? 'bg-emerald-950/40 border-emerald-900/60 text-emerald-400' : 'bg-red-950/40 border-red-900/60 text-red-400' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Painel de Tabela -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl flex flex-col h-full">
                <?php if ($filtro_tipo === 'dia'): ?>
                    <?php
                    $pauta_liberada = isPautaLiberada($pdo, $data_busca);
                    ?>
                    <div class="mb-6 p-4 rounded-xl border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 <?= $pauta_liberada ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-200' : 'bg-amber-950/40 border-amber-800/60 text-amber-200' ?> sga-pauta-status-banner">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl"><?= $pauta_liberada ? '🟢' : '⚠️' ?></span>
                            <div>
                                <h4 class="text-xs uppercase font-black tracking-wider <?= $pauta_liberada ? 'text-emerald-300' : 'text-amber-300' ?>">Status da Pauta • <?= date('d/m/Y', strtotime($data_busca)) ?></h4>
                                <p class="text-xs <?= $pauta_liberada ? 'text-emerald-300' : 'text-amber-200' ?> mt-0.5 font-medium">
                                    <?= $pauta_liberada ? 'Homologada e liberada. A recepção comum pode confirmar a chegada dos pacientes.' : 'Pendente de homologação. O check-in da recepção está bloqueado para este dia.' ?>
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="gerenciar_pauta.php?filtro_tipo=dia&data_busca=<?= $data_busca ?>" class="w-full sm:w-auto shrink-0"><?= csrfCampo() ?>
                            <input type="hidden" name="data_alvo" value="<?= escapar($data_busca) ?>">
                            <?php if ($pauta_liberada): ?>
                                <input type="hidden" name="action" value="bloquear_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-amber-900/60 hover:bg-amber-800/80 border border-amber-700/80 text-amber-200 text-xs font-bold py-2 px-4 rounded-lg transition shadow-sm">
                                    🔒 Bloquear Pauta
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="liberar_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-[#007A33] hover:bg-[#006028] text-white text-xs font-bold py-2.5 px-5 rounded-lg shadow-md transition">
                                    🔓 Homologar e Liberar Pauta
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Filtros de Busca superiores -->
                <form method="GET" action="gerenciar_pauta.php" class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold uppercase text-gray-400 tracking-wider">Filtrar por:</label>
                        <select name="filtro_tipo" id="filtro-tipo-busca" onchange="toggleFiltroBuscaInputs(); this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none">
                            <option value="dia" <?= $filtro_tipo === 'dia' ? 'selected' : '' ?>>Dia</option>
                            <option value="mes" <?= $filtro_tipo === 'mes' ? 'selected' : '' ?>>Mês</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div id="busca-dia-container" class="<?= $filtro_tipo === 'dia' ? 'flex items-center gap-2' : 'hidden' ?> w-full sm:w-auto">
                            <input type="date" name="data_busca" id="data-busca-input" value="<?= escapar($data_busca) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none w-full">
                            <button type="button" onclick="irParaHoje()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shrink-0">
                                Hoje
                            </button>
                        </div>
                        <div id="busca-mes-container" class="<?= $filtro_tipo === 'mes' ? '' : 'hidden' ?> w-full sm:w-auto">
                            <input type="month" name="mes_busca" value="<?= escapar($mes_busca) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none w-full">
                        </div>
                    </div>
                </form>

                <form method="POST" id="form-tabela-pauta" class="flex-1 flex flex-col justify-between"><?= csrfCampo() ?>
                    <!-- Tabela de agendados -->
                    <div class="overflow-x-auto max-h-[720px] overflow-y-auto pr-1">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <th class="pb-3 w-8">
                                        <input type="checkbox" id="chk-select-all" class="rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                    </th>
                                    <th class="pb-3">Data</th>
                                    <th class="pb-3">Nome / Processo / CPF</th>
                                    <th class="pb-3">Perito</th>
                                    <th class="pb-3 text-center">Status / Senha</th>
                                    <th class="pb-3 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                                <?php if (empty($atendimentos)): ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                <div class="w-10 h-10 rounded-full bg-gray-800/60 border border-gray-700/60 flex items-center justify-center text-lg text-gray-400">
                                                    📅
                                                </div>
                                                <p class="text-sm font-semibold text-gray-300">Nenhum agendamento encontrado para o período</p>
                                                <p class="text-xs text-gray-500">Utilize o formulário ao lado para importar a planilha (.xlsx) da pauta pericial.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($atendimentos as $item): ?>
                                        <tr onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: <?= jsValor($item['nome']) ?>, cpf: <?= jsValor($item['cpf'] ?? '---') ?>, processo: <?= jsValor($item['processo']) ?>, perito: <?= jsValor($item['perito']) ?>, data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: <?= jsValor($item['status']) ?>, chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="hover:bg-gray-900/30 cursor-pointer transition">
                                            <td class="py-3">
                                                <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" data-status="<?= escapar($item['status']) ?>" data-chamadas="<?= (int)$item['chamadas_count'] ?>" aria-label="Selecionar <?= escapar($item['nome']) ?>" class="chk-item rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                            </td>
                                            <td class="py-3 text-xs font-mono text-gray-400">
                                                <?= date('d/m/Y', strtotime($item['data_pauta'])) ?>
                                            </td>
                                            <td class="py-3">
                                                <div class="font-semibold text-white truncate max-w-[220px]"><?= escapar($item['nome']) ?></div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= escapar($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-blue-400 ml-1.5">| CPF: <?= escapar($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-xs text-gray-300 truncate max-w-[150px]">
                                                <?= escapar($item['perito'] ?: 'Não associado') ?>
                                            </td>
                                            <td class="py-3 text-center text-xs font-semibold">
                                                <?php
                                                $status = $item['status'];
                                                $senha_badge = !empty($item['senha']) && $item['senha'] !== '---' ? " <strong class='text-white ml-1'>({$item['senha']})</strong>" : "";
                                                if ($status === 'agendado') {
                                                    echo '<span class="text-blue-400 bg-blue-950/30 border border-blue-900/60 px-2 py-0.5 rounded-full text-[10px]">Agendado</span>';
                                                } elseif ($status === 'presente' || $status === 'fila') {
                                                    echo '<span class="text-emerald-400 bg-emerald-950/40 border border-emerald-900/60 px-2 py-0.5 rounded-full text-[10px]">Na Fila' . $senha_badge . '</span>';
                                                } elseif ($status === 'chamada') {
                                                    echo '<span class="text-amber-400 bg-amber-950/40 border border-amber-900/60 px-2 py-0.5 rounded-full text-[10px]">Chamado' . $senha_badge . '</span>';
                                                } elseif ($status === 'atendimento') {
                                                    echo '<span class="text-purple-400 bg-purple-950/40 border border-purple-900/60 px-2 py-0.5 rounded-full text-[10px]">Consulta' . $senha_badge . '</span>';
                                                } elseif ($status === 'concluido') {
                                                    echo '<span class="text-gray-400 bg-gray-800 border border-gray-700 px-2 py-0.5 rounded-full text-[10px]">Finalizado</span>';
                                                } else {
                                                    echo '<span class="text-red-400 bg-red-950/40 border border-red-900/60 px-2 py-0.5 rounded-full text-[10px]">Cancelado</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="py-3 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <?php if ($status === 'agendado'): ?>
                                                        <button type="button" onclick="confirmarChegadaIndividual(<?= $item['id'] ?>)" title="Confirmar Presença" class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-[10px] px-2 py-1 rounded font-bold transition">
                                                            ✔️ Confirmar
                                                        </button>
                                                    <?php elseif ($status === 'presente' && (int)$item['chamadas_count'] === 0): ?>
                                                        <button type="button" onclick="removerChegadaIndividual(<?= $item['id'] ?>)" title="Retornar para Agendados" class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-[10px] px-2 py-1 rounded font-bold transition">
                                                            ↩️ Desfazer
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($status === 'agendado'): ?>
                                                    <button type="button" onclick="excluirIndividual(<?= $item['id'] ?>)" title="Cancelar agendamento" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-[10px] px-2 py-1 rounded transition">
                                                        🗑️
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botões de Ações Rápidas em Lote -->
                    <div class="mt-6 pt-4 border-t border-gray-800 flex flex-wrap gap-3 items-center justify-between">
                        <span class="text-xs text-gray-500 font-medium" id="lbl-selecionados">Nenhum registro selecionado.</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="action" value="confirmar_chegada_lote" id="btn-chegada-lote" disabled class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                ✔️ Confirmar Chegada
                            </button>
                            <button type="submit" name="action" value="remover_chegada_lote" id="btn-cancelar-lote" disabled class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                ↩️ Voltar para Agendado
                            </button>
                            <button type="submit" name="action" value="excluir_selecionados" id="btn-excluir-lote" disabled onclick="return confirm('Deseja excluir os agendamentos ainda não confirmados selecionados na tabela?');" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                🗑️ Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Formulários Ocultos Auxiliares para Ações Individuais (evita conflito de checkboxes) -->
    <form id="form-action-individual" method="POST" class="hidden"><?= csrfCampo() ?>
        <input type="hidden" name="action" id="ind-action">
        <input type="hidden" name="id" id="ind-id">
        <input type="hidden" name="selecionados[]" id="ind-selecionados">
    </form>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide mt-6">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>

    <script>
        // Vai direto para o dia de hoje
        function irParaHoje() {
            const input = document.getElementById('data-busca-input');
            if (input) {
                input.value = '<?= date('Y-m-d') ?>';
                input.form.submit();
            }
        }

        // Funções para Ações Individuais rápidas
        function confirmarChegadaIndividual(id) {
            document.getElementById('ind-action').value = 'confirmar_chegada_lote';
            document.getElementById('ind-id').value = id;
            document.getElementById('form-action-individual').submit();
        }

        function removerChegadaIndividual(id) {
            document.getElementById('ind-action').value = 'remover_chegada_lote';
            document.getElementById('ind-id').value = id;
            document.getElementById('form-action-individual').submit();
        }

        function excluirIndividual(id) {
            if (confirm('Cancelar este agendamento, preservando o histórico?')) {
                document.getElementById('ind-action').value = 'excluir_selecionados';
                document.getElementById('ind-id').value = id;
                document.getElementById('ind-selecionados').value = id;
                document.getElementById('form-action-individual').submit();
            }
        }

        // Lógica de formulários adicionais
        function toggleMoverInputs() {
            const val = document.getElementById('mover-tipo').value;
            const origemDia = document.getElementById('mover-origem-dia');
            const origemMes = document.getElementById('mover-origem-mes');
            const labelDestino = document.getElementById('mover-destino-label');
            const inputDestino = labelDestino.nextElementSibling;
            
            origemDia.classList.add('hidden');
            origemMes.classList.add('hidden');
            
            if (val === 'dia') {
                origemDia.classList.remove('hidden');
                labelDestino.innerText = "Nova Data de Destino";
                inputDestino.type = 'date';
            } else if (val === 'mes') {
                origemMes.classList.remove('hidden');
                labelDestino.innerText = "Novo Mês de Destino";
                inputDestino.type = 'month';
            } else {
                labelDestino.innerText = "Nova Data de Destino";
                inputDestino.type = 'date';
            }
        }

        function togglePeritoInputs() {
            const val = document.getElementById('perito-tipo').value;
            const origemDia = document.getElementById('perito-origem-dia');
            origemDia.classList.add('hidden');
            if (val === 'dia') {
                origemDia.classList.remove('hidden');
            }
        }

        function toggleExcluirInputs() {
            const val = document.getElementById('excluir-tipo').value;
            const dia = document.getElementById('excluir-origem-dia');
            const mes = document.getElementById('excluir-origem-mes');
            
            dia.classList.add('hidden');
            mes.classList.add('hidden');
            
            if (val === 'dia') {
                dia.classList.remove('hidden');
            } else {
                mes.classList.remove('hidden');
            }
        }

        function toggleFiltroBuscaInputs() {
            const val = document.getElementById('filtro-tipo-busca').value;
            const diaContainer = document.getElementById('busca-dia-container');
            const mesContainer = document.getElementById('busca-mes-container');
            
            diaContainer.classList.add('hidden');
            mesContainer.classList.add('hidden');
            
            if (val === 'dia') {
                diaContainer.classList.remove('hidden');
            } else {
                mesContainer.classList.remove('hidden');
            }
        }

        // Checkbox "Selecionar Todos"
        const chkAll = document.getElementById('chk-select-all');
        const chks = document.querySelectorAll('.chk-item');
        
        const btnChegada = document.getElementById('btn-chegada-lote');
        const btnCancelar = document.getElementById('btn-cancelar-lote');
        const btnExcluir = document.getElementById('btn-excluir-lote');
        const lblSelecionados = document.getElementById('lbl-selecionados');
        
        const moverContainer = document.getElementById('mover-selecionados-container');
        const peritoContainer = document.getElementById('perito-selecionados-container');

        if (chkAll) {
            chkAll.addEventListener('change', function() {
                chks.forEach(chk => chk.checked = chkAll.checked);
                atualizarBotoesLote();
            });
        }

        chks.forEach(chk => {
            chk.addEventListener('change', atualizarBotoesLote);
        });

        function atualizarBotoesLote() {
            const selecionados = Array.from(chks).filter(chk => chk.checked).map(chk => chk.value);
            const count = selecionados.length;
            
            if (count > 0) {
                const itens=Array.from(chks).filter(chk=>chk.checked);
                btnChegada.disabled = !itens.some(chk=>chk.dataset.status==='agendado');
                btnCancelar.disabled = !itens.some(chk=>chk.dataset.status==='presente' && chk.dataset.chamadas==='0');
                btnExcluir.disabled = !itens.some(chk=>chk.dataset.status==='agendado');
                lblSelecionados.innerText = `${count} registro(s) selecionado(s).`;
            } else {
                btnChegada.disabled = true;
                btnCancelar.disabled = true;
                btnExcluir.disabled = true;
                lblSelecionados.innerText = 'Nenhum registro selecionado.';
            }

            // Injeta os inputs ocultos nos outros formulários
            moverContainer.innerHTML = '';
            peritoContainer.innerHTML = '';
            
            selecionados.forEach(id => {
                const inp1 = document.createElement('input');
                inp1.type = 'hidden';
                inp1.name = 'selecionados[]';
                inp1.value = id;
                moverContainer.appendChild(inp1);

                const inp2 = document.createElement('input');
                inp2.type = 'hidden';
                inp2.name = 'selecionados[]';
                inp2.value = id;
                peritoContainer.appendChild(inp2);
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
