<?php
/**
 * MÓDULO DE AUDITORIA DE SENHAS E ATENDIMENTOS AVANÇADO - JFAL
 * 
 * auditoria.php: Permite que administradores e recepcionistas consultem o histórico
 * completo de atendimentos e o Log Geral de Auditoria de Ações do sistema.
 */

session_start();
require_once __DIR__ . '/banco.php';

// Proteção de Acesso: Apenas administradores e supervisores podem auditar os dados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])) {
    header("Location: dashboard.php");
    exit;
}

// Verificação de Segurança: Forçar troca de senha de primeiro acesso
if (isset($_SESSION['usuario_id'])) {
    $checkStmt = $pdo->prepare("SELECT cpf, password, role FROM usuarios WHERE id = ?");
    $checkStmt->execute([$_SESSION['usuario_id']]);
    $uData = $checkStmt->fetch();
    if ($uData) {
        $pwdPadrao = '';
        if ($uData['role'] === 'perito' && !empty($uData['cpf'])) {
            $cpfLimpo = preg_replace('/\D/', '', $uData['cpf']);
            if (strlen($cpfLimpo) === 11) {
                $pwdPadrao = substr($cpfLimpo, 0, 6);
            }
        }
        if ($pwdPadrao && password_verify($pwdPadrao, $uData['password'])) {
            header("Location: alterar_senha_obrigatorio.php");
            exit;
        }
    }
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';

// Inicialização dos parâmetros de filtro
$aba = $_GET['aba'] ?? 'atendimentos'; // 'atendimentos' ou 'logs'
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_perito = $_GET['perito'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_sala = $_GET['sala'] ?? '';
$filtro_atraso = $_GET['atraso'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'chegada_desc';
$busca = trim($_GET['busca'] ?? '');

// Consulta de Atendimentos Filtrada
$sql = "SELECT * FROM atendimentos WHERE 1=1";
$params = [];

if ($data_inicio) {
    $sql .= " AND data_pauta >= ?";
    $params[] = $data_inicio;
}
if ($data_fim) {
    $sql .= " AND data_pauta <= ?";
    $params[] = $data_fim;
}
if ($filtro_perito) {
    $sql .= " AND perito = ?";
    $params[] = $filtro_perito;
}
if ($filtro_status) {
    $sql .= " AND status = ?";
    $params[] = $filtro_status;
}
if ($filtro_sala) {
    $sql .= " AND sala = ?";
    $params[] = $filtro_sala;
}

// Filtro por Atraso de Espera (SLA)
if ($filtro_atraso) {
    if ($filtro_atraso === '15') {
        $sql .= " AND (chamado_em IS NOT NULL AND TIMESTAMPDIFF(MINUTE, chegada_em, chamado_em) > 15)";
    } elseif ($filtro_atraso === '30') {
        $sql .= " AND (chamado_em IS NOT NULL AND TIMESTAMPDIFF(MINUTE, chegada_em, chamado_em) > 30)";
    } elseif ($filtro_atraso === '60') {
        $sql .= " AND (chamado_em IS NOT NULL AND TIMESTAMPDIFF(MINUTE, chegada_em, chamado_em) > 60)";
    }
}

if ($busca !== '') {
    $sql .= " AND (nome LIKE ? OR processo LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Ordenação Dinâmica
if ($ordenacao === 'chegada_asc') {
    $sql .= " ORDER BY chegada_em ASC, id ASC";
} elseif ($ordenacao === 'espera_desc') {
    $sql .= " ORDER BY TIMESTAMPDIFF(SECOND, chegada_em, COALESCE(chamado_em, CURRENT_TIMESTAMP)) DESC, id DESC";
} elseif ($ordenacao === 'duracao_desc') {
    $sql .= " ORDER BY TIMESTAMPDIFF(SECOND, iniciado_em, finalizado_em) DESC, id DESC";
} elseif ($ordenacao === 'nome_asc') {
    $sql .= " ORDER BY nome ASC, id DESC";
} else {
    // Padrão: chegada_desc (Mais recente primeiro)
    $sql .= " ORDER BY chegada_em DESC, id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atendimentos = $stmt->fetchAll();

// Consulta de Logs de Auditoria Filtrada
$logs_auditoria_lista = [];
if ($aba === 'logs') {
    $sqlLogs = "SELECT * FROM logs_auditoria WHERE DATE(criado_em) >= ? AND DATE(criado_em) <= ?";
    $paramsLogs = [$data_inicio, $data_fim];
    
    if ($busca !== '') {
        $sqlLogs .= " AND (usuario_nome LIKE ? OR acao LIKE ? OR detalhes LIKE ?)";
        $paramsLogs[] = "%$busca%";
        $paramsLogs[] = "%$busca%";
        $paramsLogs[] = "%$busca%";
    }
    $sqlLogs .= " ORDER BY id DESC LIMIT 500";
    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($paramsLogs);
    $logs_auditoria_lista = $stmtLogs->fetchAll();
}

// Pré-carrega de forma otimizada os logs vinculados aos atendimentos visíveis (Ciclo de Vida)
$logs_por_atendimento = [];
if ($aba === 'atendimentos' && !empty($atendimentos)) {
    $atendimento_ids = array_column($atendimentos, 'id');
    if (!empty($atendimento_ids)) {
        $in_clause = implode(',', array_map('intval', $atendimento_ids));
        try {
            $stmtLogsLink = $pdo->query("SELECT * FROM logs_auditoria WHERE atendimento_id IN ($in_clause) ORDER BY id ASC");
            while ($l = $stmtLogsLink->fetch()) {
                $logs_por_atendimento[$l['atendimento_id']][] = $l;
            }
        } catch (PDOException $e) {}
    }
}

// Ação: EXPORTAÇÃO PARA PLANILHA CSV
if (isset($_GET['action']) && $_GET['action'] === 'exportar') {
    if ($aba === 'atendimentos') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=auditoria_atendimentos_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Senha', 'Paciente', 'Processo', 'Perito', 'Sala', 'Status', 'Data Pauta', 'Chegada', 'Chamada', 'Início Perícia', 'Fim Perícia', 'Duração', 'Chamadas TV']);
        foreach ($atendimentos as $row) {
            $duracao = '---';
            if (!empty($row['iniciado_em']) && !empty($row['finalizado_em'])) {
                $diff = strtotime($row['finalizado_em']) - strtotime($row['iniciado_em']);
                if ($diff >= 0) {
                    $duracao = floor($diff / 60) . 'm ' . ($diff % 60) . 's';
                }
            }
            fputcsv($output, [
                $row['id'],
                $row['senha'],
                $row['nome'] ?: 'N/A',
                $row['processo'] ?: 'N/A',
                $row['perito'] ?: 'N/A',
                $row['sala'] ?: 'N/A',
                ucfirst($row['status']),
                $row['data_pauta'],
                $row['chegada_em'] ?: 'Não confirmada',
                $row['chamado_em'] ?: 'Não chamado',
                $row['iniciado_em'] ?: '---',
                $row['finalizado_em'] ?: '---',
                $duracao,
                $row['chamadas_count']
            ]);
        }
        fclose($output);
        exit;
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=auditoria_logs_acoes_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Data/Hora', 'Operador/Usuário', 'Ação', 'Detalhes Adicionais']);
        foreach ($logs_auditoria_lista as $row) {
            fputcsv($output, [
                $row['id'],
                date('d/m/Y H:i:s', strtotime($row['criado_em'])),
                $row['usuario_nome'],
                $row['acao'],
                $row['detalhes']
            ]);
        }
        fclose($output);
        exit;
    }
}

// Estatísticas Rápidas de SLA (Calculadas sobre o filtro)
$soma_espera = 0;
$total_espera_validos = 0;
$total_chegadas = 0;
$total_chamadas = 0;
$soma_pericia = 0;
$total_pericia_validos = 0;

foreach ($atendimentos as $row) {
    if ($row['status'] === 'presente' || $row['status'] === 'fila' || !empty($row['chegada_em'])) {
        $total_chegadas++;
    }
    if (!empty($row['chamado_em'])) {
        $total_chamadas++;
    }
    
    // SLA de Espera (Chegada até Chamada)
    if (!empty($row['chegada_em']) && !empty($row['chamado_em'])) {
        $t_chegada = strtotime($row['chegada_em']);
        $t_chamada = strtotime($row['chamado_em']);
        if ($t_chamada >= $t_chegada) {
            $soma_espera += ($t_chamada - $t_chegada);
            $total_espera_validos++;
        }
    }
    
    // SLA de Duração da Perícia (Início até Fim)
    if (!empty($row['iniciado_em']) && !empty($row['finalizado_em'])) {
        $t_inicio = strtotime($row['iniciado_em']);
        $t_fim = strtotime($row['finalizado_em']);
        if ($t_fim >= $t_inicio) {
            $soma_pericia += ($t_fim - $t_inicio);
            $total_pericia_validos++;
        }
    }
}

$tempo_medio_espera_txt = 'N/A';
if ($total_espera_validos > 0) {
    $media_minutos = round($soma_espera / 60 / $total_espera_validos, 1);
    $tempo_medio_espera_txt = ($media_minutos < 1) ? round($soma_espera / $total_espera_validos) . ' seg' : $media_minutos . ' min';
}

$tempo_medio_pericia_txt = 'N/A';
if ($total_pericia_validos > 0) {
    $media_minutos = round($soma_pericia / 60 / $total_pericia_validos, 1);
    $tempo_medio_pericia_txt = ($media_minutos < 1) ? round($soma_pericia / $total_pericia_validos) . ' seg' : $media_minutos . ' min';
}

// Dropdowns de filtros dinâmicos
$peritos_lista = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != '' ORDER BY perito ASC")->fetchAll(PDO::FETCH_COLUMN);
$salas_lista = $pdo->query("SELECT DISTINCT sala FROM atendimentos WHERE sala IS NOT NULL AND sala != '' ORDER BY sala ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria Geral - <?= htmlspecialchars($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="flex items-center space-x-2">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-6 w-auto object-contain">
                <span class="text-blue-500 font-bold text-base hover:underline hidden sm:inline-block">JFAL - Alagoas</span>
            </a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= htmlspecialchars(ucfirst($_SESSION['usuario_role'])) ?>
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

            <span class="text-xs text-gray-400 hidden sm:inline">Olá, <strong class="text-white"><?= htmlspecialchars($_SESSION['usuario_name']) ?></strong></span>
            <a href="logout.php" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-black text-white">Auditoria e SLAs do Painel</h2>
                <p class="text-xs text-gray-400">Histórico de pautas, tempo de espera na recepção, duração de perícias e log geral de ações.</p>
            </div>
            
            <a href="?action=exportar&<?= http_build_query($_GET) ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-4 rounded-lg shadow transition flex items-center gap-2">
                📥 Exportar Planilha (CSV)
            </a>
        </div>

        <!-- Painel de Filtros Avançados -->
        <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
            <form method="GET" class="space-y-4">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Data Início -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Data Inicial</label>
                        <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                    </div>
                    
                    <!-- Data Fim -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Data Final</label>
                        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                    </div>
                    
                    <!-- Busca Livre -->
                    <div class="md:col-span-2">
                        <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Busca Livre (Nome/Processo/Ação)</label>
                        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="<?= $aba === 'logs' ? 'Digite a ação ou operador do log...' : 'Digite o nome do paciente, processo ou perito...' ?>" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <?php if ($aba === 'atendimentos'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t border-gray-800 pt-4">
                        <!-- Filtro Perito -->
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Perito Médico</label>
                            <select name="perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Todos --</option>
                                <?php foreach ($peritos_lista as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtro_perito === $p ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filtro Status -->
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Status do Atendimento</label>
                            <select name="status" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Todos --</option>
                                <option value="agendado" <?= $filtro_status === 'agendado' ? 'selected' : '' ?>>Agendado (Pendente)</option>
                                <option value="presente" <?= $filtro_status === 'presente' ? 'selected' : '' ?>>NaFila (Confirmou Presença)</option>
                                <option value="atendimento" <?= $filtro_status === 'atendimento' ? 'selected' : '' ?>>Em Consulta (Perícia Física)</option>
                                <option value="concluido" <?= $filtro_status === 'concluido' ? 'selected' : '' ?>>Finalizado (Concluído)</option>
                            </select>
                        </div>

                        <!-- Filtro Sala -->
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Sala de Atendimento</label>
                            <select name="sala" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Todas --</option>
                                <?php foreach ($salas_lista as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $filtro_sala === $s ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filtro Atraso de Espera (SLA) -->
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Limite de Espera (SLA)</label>
                            <select name="atraso" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="">-- Qualquer Tempo --</option>
                                <option value="15" <?= $filtro_atraso === '15' ? 'selected' : '' ?>>Atraso de Espera > 15 minutos</option>
                                <option value="30" <?= $filtro_atraso === '30' ? 'selected' : '' ?>>Atraso de Espera > 30 minutos</option>
                                <option value="60" <?= $filtro_atraso === '60' ? 'selected' : '' ?>>Atraso de Espera > 1 hora</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t border-gray-850 pt-4 items-end">
                        <!-- Ordenação Dinâmica -->
                        <div class="md:col-span-2">
                            <label class="block text-[10px] text-gray-400 mb-1 font-bold uppercase tracking-wider">Ordenar Resultados por</label>
                            <select name="ordenacao" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="chegada_desc" <?= $ordenacao === 'chegada_desc' ? 'selected' : '' ?>>Horário de Chegada (Mais Recente Primeiro)</option>
                                <option value="chegada_asc" <?= $ordenacao === 'chegada_asc' ? 'selected' : '' ?>>Horário de Chegada (Mais Antigo Primeiro)</option>
                                <option value="espera_desc" <?= $ordenacao === 'espera_desc' ? 'selected' : '' ?>>Maior Tempo de Espera do Paciente (SLA Recepção)</option>
                                <option value="duracao_desc" <?= $ordenacao === 'duracao_desc' ? 'selected' : '' ?>>Maior Duração do Exame Físico (SLA Perícia)</option>
                                <option value="nome_asc" <?= $ordenacao === 'nome_asc' ? 'selected' : '' ?>>Nome do Paciente (Ordem Alfabética A-Z)</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow transition tracking-wider uppercase">
                                aplicar filtros de auditoria
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex justify-end border-t border-gray-850 pt-4">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow transition tracking-wider uppercase">
                            filtrar logs de ações
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Cards de Estatísticas e SLA -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Média de Espera (SLA)</div>
                <div class="text-2xl font-black text-blue-500 mt-1"><?= $tempo_medio_espera_txt ?></div>
                <div class="text-[9px] text-gray-500 mt-1">Tempo entre presença e primeira chamada.</div>
            </div>
            
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Duração Média Perícia</div>
                <div class="text-2xl font-black text-emerald-400 mt-1"><?= $tempo_medio_pericia_txt ?></div>
                <div class="text-[9px] text-gray-500 mt-1">Tempo de atendimento dentro do consultório.</div>
            </div>
            
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Taxa de Conclusão</div>
                <div class="text-2xl font-black text-violet-400 mt-1">
                    <?= $total_chegadas > 0 ? round(($total_chamadas / $total_chegadas) * 100, 1) . '%' : '0%' ?>
                </div>
                <div class="text-[9px] text-gray-500 mt-1">Perícias concluídas sobre presenças do período.</div>
            </div>

            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Registros Filtrados</div>
                <div class="text-2xl font-black text-white mt-1"><?= count($atendimentos) ?></div>
                <div class="text-[9px] text-gray-500 mt-1">Total de registros no período selecionado.</div>
            </div>
        </div>

        <!-- Seletor de Abas de Auditoria -->
        <div class="flex border-b border-gray-850 gap-2">
            <a href="?aba=atendimentos&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>&perito=<?= $filtro_perito ?>&status=<?= $filtro_status ?>&sala=<?= $filtro_sala ?>&atraso=<?= $filtro_atraso ?>&ordenacao=<?= $ordenacao ?>&busca=<?= urlencode($busca) ?>" class="px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition <?= $aba === 'atendimentos' ? 'border-blue-500 text-blue-400 bg-gray-900/40' : 'border-transparent text-gray-500 hover:text-gray-300' ?>">
                📋 Histórico de Atendimentos
            </a>
            <a href="?aba=logs&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>&busca=<?= urlencode($busca) ?>" class="px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition <?= $aba === 'logs' ? 'border-blue-500 text-blue-400 bg-gray-900/40' : 'border-transparent text-gray-500 hover:text-gray-300' ?>">
                🛡️ Log Geral de Ações
            </a>
        </div>

        <?php if ($aba === 'atendimentos'): ?>
            <!-- Tabela com Histórico Auditável -->
            <div class="bg-gray-900 rounded-2xl border border-gray-800 shadow-xl overflow-hidden">
                <div class="p-6 border-b border-gray-850 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-900/50">
                    <div>
                        <h3 class="text-xs font-bold uppercase text-gray-400 tracking-wider">Histórico de Movimentações</h3>
                        <span class="text-[10px] text-blue-400 font-medium block mt-1">💡 Dica: Clique na linha do paciente para ver a Timeline de Auditoria individual.</span>
                    </div>
                    <span class="text-xs bg-gray-850 border border-gray-700 text-gray-300 py-1 px-3 rounded-full font-bold">
                        Total: <?= count($atendimentos) ?> registros
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 bg-gray-950/40 text-[10px] text-gray-400 uppercase font-bold tracking-wider">
                                <th class="py-3.5 px-4 text-center">Senha</th>
                                <th class="py-3.5 px-4">Paciente / Processo</th>
                                <th class="py-3.5 px-4">Perito</th>
                                <th class="py-3.5 px-4 text-center">Sala</th>
                                <th class="py-3.5 px-4 text-center">Status</th>
                                <th class="py-3.5 px-4 text-center">Chegada</th>
                                <th class="py-3.5 px-4 text-center">Chamado</th>
                                <th class="py-3.5 px-4 text-center">Início</th>
                                <th class="py-3.5 px-4 text-center">Conclusão</th>
                                <th class="py-3.5 px-4 text-center">Duração</th>
                                <th class="py-3.5 px-4 text-center">Chamadas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-850 text-xs">
                            <?php if (empty($atendimentos)): ?>
                                <tr>
                                    <td colspan="11" class="py-12 text-center text-sm text-gray-500">Nenhum atendimento corresponde aos filtros aplicados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($atendimentos as $row): ?>
                                    <?php
                                    // Cálculo de duração da perícia individual
                                    $duracao_txt = '---';
                                    if (!empty($row['iniciado_em']) && !empty($row['finalizado_em'])) {
                                        $diff = strtotime($row['finalizado_em']) - strtotime($row['iniciado_em']);
                                        if ($diff >= 0) {
                                            $min = floor($diff / 60);
                                            $seg = $diff % 60;
                                            $duracao_txt = ($min > 0) ? $min . "m " . $seg . "s" : $seg . "s";
                                        }
                                    }
                                    ?>
                                    <!-- Linha Principal Clickável -->
                                    <tr onclick="toggleTimeline(<?= $row['id'] ?>)" class="hover:bg-gray-850/50 cursor-pointer transition select-none">
                                        <!-- Senha -->
                                        <td class="py-4 px-4 font-mono font-bold text-blue-400 text-center text-sm"><?= htmlspecialchars($row['senha'] ?: '---') ?></td>
                                        
                                        <!-- Paciente e Processo -->
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-white flex items-center gap-1.5">
                                                <span><?= htmlspecialchars($row['nome'] ?: 'N/A') ?></span>
                                                <span class="text-[9px] text-gray-500 font-semibold" title="Clique para detalhes">🔍</span>
                                            </div>
                                            <div class="text-[9px] text-gray-500 font-mono mt-0.5"><?= htmlspecialchars($row['processo'] ?: 'Sem processo') ?></div>
                                        </td>
                                        
                                        <!-- Perito -->
                                        <td class="py-4 px-4 text-gray-300 font-medium truncate max-w-[130px]" title="<?= htmlspecialchars($row['perito']) ?>"><?= htmlspecialchars($row['perito'] ?: 'N/A') ?></td>
                                        
                                        <!-- Sala -->
                                        <td class="py-4 px-4 text-gray-300 font-semibold text-center"><?= htmlspecialchars($row['sala'] ?: '---') ?></td>
                                        
                                        <!-- Status -->
                                        <td class="py-4 px-4 text-center">
                                            <?php
                                            $st = $row['status'];
                                            if ($st === 'concluido') {
                                                echo '<span class="bg-gray-850 text-gray-400 text-[9px] px-2 py-0.5 rounded-full border border-gray-700 uppercase font-bold tracking-wider">Concluído</span>';
                                            } elseif ($st === 'atendimento') {
                                                echo '<span class="bg-blue-950 text-blue-400 text-[9px] px-2 py-0.5 rounded-full border border-blue-900/50 uppercase font-bold tracking-wider">Em Exame</span>';
                                            } elseif ($st === 'chamada') {
                                                echo '<span class="bg-amber-950/40 text-amber-400 text-[9px] px-2 py-0.5 rounded-full border border-amber-900/40 uppercase font-bold tracking-wider">Chamado</span>';
                                            } elseif ($st === 'presente' || $st === 'fila') {
                                                echo '<span class="bg-emerald-950 text-emerald-400 text-[9px] px-2 py-0.5 rounded-full border border-emerald-900/50 uppercase font-bold tracking-wider">Aguardando</span>';
                                            } else {
                                                echo '<span class="bg-gray-900 text-gray-500 text-[9px] px-2 py-0.5 rounded-full border border-gray-800 uppercase font-bold tracking-wider">Agendado</span>';
                                            }
                                            ?>
                                        </td>

                                        <!-- Chegada -->
                                        <td class="py-4 px-4 text-center text-gray-400 font-mono"><?= $row['chegada_em'] ? date('H:i:s', strtotime($row['chegada_em'])) : '---' ?></td>
                                        
                                        <!-- Chamada -->
                                        <td class="py-4 px-4 text-center text-gray-400 font-mono"><?= $row['chamado_em'] ? date('H:i:s', strtotime($row['chamado_em'])) : '---' ?></td>
                                        
                                        <!-- Início -->
                                        <td class="py-4 px-4 text-center text-gray-400 font-mono"><?= $row['iniciado_em'] ? date('H:i:s', strtotime($row['iniciado_em'])) : '---' ?></td>
                                        
                                        <!-- Fim -->
                                        <td class="py-4 px-4 text-center text-gray-400 font-mono"><?= $row['finalizado_em'] ? date('H:i:s', strtotime($row['finalizado_em'])) : '---' ?></td>
                                        
                                        <!-- Duração -->
                                        <td class="py-4 px-4 text-center text-emerald-400 font-mono font-bold"><?= $duracao_txt ?></td>
                                        
                                        <!-- Chamadas TV -->
                                        <td class="py-4 px-4 text-center text-amber-500 font-mono font-bold"><?= $row['chamadas_count'] ?></td>
                                    </tr>
                                    
                                    <!-- Accordion da Linha do Tempo (Ciclo de Vida do Paciente) -->
                                    <tr id="timeline-<?= $row['id'] ?>" class="hidden bg-gray-950/40">
                                        <td colspan="11" class="p-6 border-b border-gray-850/80">
                                            <div class="max-w-3xl mx-auto bg-gray-900/40 p-5 rounded-2xl border border-gray-800">
                                                <h4 class="text-xs uppercase font-bold text-gray-400 mb-4 tracking-wider flex items-center gap-2">
                                                    <span>⏱️ Ciclo de Vida do Paciente</span>
                                                    <span class="text-[9px] text-gray-500 font-normal normal-case">(Rastreamento estruturado da auditoria)</span>
                                                </h4>
                                                
                                                <div class="relative border-l-2 border-gray-800 ml-4 space-y-5 py-1">
                                                    <?php if (empty($logs_por_atendimento[$row['id']])): ?>
                                                        <div class="text-xs text-gray-500 pl-4">Nenhum evento registrado no log para este atendimento.</div>
                                                    <?php else: ?>
                                                        <?php foreach ($logs_por_atendimento[$row['id']] as $evt): ?>
                                                            <div class="relative pl-6">
                                                                <!-- Bullet da Linha do Tempo -->
                                                                <?php
                                                                $bullet_color = 'bg-blue-500';
                                                                $ac = $evt['acao'];
                                                                if ($ac === 'Confirmar Presença') $bullet_color = 'bg-emerald-500';
                                                                elseif ($ac === 'Iniciar Perícia') $bullet_color = 'bg-indigo-500';
                                                                elseif ($ac === 'Concluir Perícia') $bullet_color = 'bg-teal-500';
                                                                elseif ($ac === 'Ausência de Paciente') $bullet_color = 'bg-rose-500';
                                                                ?>
                                                                <div class="absolute -left-1.5 mt-1 h-2.5 w-2.5 rounded-full <?= $bullet_color ?> border border-gray-900"></div>
                                                                
                                                                <div class="flex items-center justify-between text-xs">
                                                                    <span class="font-bold text-gray-200 uppercase text-[10px] tracking-wide"><?= htmlspecialchars($evt['acao']) ?></span>
                                                                    <span class="text-[9px] text-gray-500 font-mono"><?= date('d/m/Y H:i:s', strtotime($evt['criado_em'])) ?></span>
                                                                </div>
                                                                <p class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($evt['detalhes']) ?></p>
                                                                <span class="text-[9px] text-gray-600 block mt-0.5">Operador: <strong class="text-gray-500"><?= htmlspecialchars($evt['usuario_nome']) ?></strong></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Tabela com Log de Ações Geral -->
            <div class="bg-gray-900 rounded-2xl border border-gray-800 shadow-xl overflow-hidden">
                <div class="p-6 border-b border-gray-850 flex justify-between items-center bg-gray-900/50">
                    <h3 class="text-xs font-bold uppercase text-gray-400 tracking-wider">Log de Atividades do Sistema</h3>
                    <span class="text-xs bg-gray-850 border border-gray-700 text-gray-300 py-1 px-3 rounded-full font-bold">
                        Mostrando últimos <?= count($logs_auditoria_lista) ?> logs
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 bg-gray-950/40 text-[10px] text-gray-400 uppercase font-bold tracking-wider">
                                <th class="py-3.5 px-6">Data/Hora</th>
                                <th class="py-3.5 px-6">Operador</th>
                                <th class="py-3.5 px-6">Ação Realizada</th>
                                <th class="py-3.5 px-6">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-850 text-xs">
                            <?php if (empty($logs_auditoria_lista)): ?>
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-sm text-gray-500">Nenhum evento registrado no log de auditoria para o filtro aplicado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs_auditoria_lista as $log): ?>
                                    <tr class="hover:bg-gray-850/35 transition">
                                        <!-- Timestamp -->
                                        <td class="py-4 px-6 text-gray-400 font-mono w-[150px]"><?= date('d/m/Y H:i:s', strtotime($log['criado_em'])) ?></td>
                                        
                                        <!-- Operador -->
                                        <td class="py-4 px-6 font-semibold text-white w-[180px]"><?= htmlspecialchars($log['usuario_nome']) ?></td>
                                        
                                        <!-- Ação (Badge Colorido) -->
                                        <td class="py-4 px-6 w-[180px]">
                                            <?php
                                            $ac = $log['acao'];
                                            if ($ac === 'Excluir Agendamento' || $ac === 'Limpar Pauta') {
                                                echo '<span class="bg-red-950 text-red-400 text-[9px] px-2.5 py-0.5 rounded-full border border-red-900/50 uppercase font-bold tracking-wider">Exclusão</span>';
                                            } elseif ($ac === 'Cadastrar Agendamento' || $ac === 'Importação de Pauta') {
                                                echo '<span class="bg-blue-950 text-blue-400 text-[9px] px-2.5 py-0.5 rounded-full border border-blue-900/50 uppercase font-bold tracking-wider">Pauta</span>';
                                            } elseif ($ac === 'Iniciar Perícia') {
                                                echo '<span class="bg-indigo-950 text-indigo-400 text-[9px] px-2.5 py-0.5 rounded-full border border-indigo-900/50 uppercase font-bold tracking-wider">Perícia Iniciada</span>';
                                            } elseif ($ac === 'Concluir Perícia') {
                                                echo '<span class="bg-emerald-950 text-emerald-400 text-[9px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-bold tracking-wider">Concluída</span>';
                                            } elseif ($ac === 'Login') {
                                                echo '<span class="bg-violet-950 text-violet-400 text-[9px] px-2.5 py-0.5 rounded-full border border-violet-900/50 uppercase font-bold tracking-wider">Login</span>';
                                            } else {
                                                echo '<span class="bg-gray-800 text-gray-300 text-[9px] px-2.5 py-0.5 rounded-full border border-gray-700 uppercase font-bold tracking-wider">' . htmlspecialchars($ac) . '</span>';
                                            }
                                            ?>
                                        </td>

                                        <!-- Detalhes -->
                                        <td class="py-4 px-6 text-gray-300 leading-normal" title="<?= htmlspecialchars($log['detalhes']) ?>"><?= htmlspecialchars($log['detalhes']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- JavaScript para controle do Accordion de Ciclo de Vida -->
    <script>
    function toggleTimeline(id) {
        const row = document.getElementById('timeline-' + id);
        if (row) {
            row.classList.toggle('hidden');
        }
    }
    </script>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>
</body>
</html>
