<?php

require_once __DIR__ . '/banco.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SESSION['usuario_role'] === 'perito') {
    header("Location: atendente.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';

$dataBusca = (string)($_GET['data_busca'] ?? date('Y-m-d'));
if (!dataValida($dataBusca)) {
    $dataBusca = date('Y-m-d');
}

// Exportação do Relatório Executivo em CSV para a Diretoria / Coordenação
if (isset($_GET['exportar']) && $_GET['exportar'] === 'metricas_csv' && in_array($_SESSION['usuario_role'], ['admin', 'supervisor'], true)) {
    $rel = obterMetricasDia($pdo, $dataBusca);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_produtividade_' . $dataBusca . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['RELATORIO DE PRODUTIVIDADE E ATENDIMENTOS — JUSTICA FEDERAL EM ALAGOAS'], ';');
    fputcsv($out, ['Data de Referencia', date('d/m/Y', strtotime($dataBusca))], ';');
    fputcsv($out, ['Emitido em', date('d/m/Y H:i:s')], ';');
    fputcsv($out, ['Emitido por', $_SESSION['usuario_name']], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['INDICADOR GERAL', 'VALOR'], ';');
    fputcsv($out, ['Total Agendados na Pauta', $rel['totais']['agendados']], ';');
    fputcsv($out, ['Presentes (Check-in Realizado)', $rel['totais']['presentes']], ';');
    fputcsv($out, ['Pericias Concluidas', $rel['totais']['concluidos']], ';');
    fputcsv($out, ['Em Atendimento no Momento', $rel['totais']['em_atendimento']], ';');
    fputcsv($out, ['Aguardando na Fila', $rel['totais']['aguardando']], ';');
    fputcsv($out, ['Ausentes / Nao Compareceu / Desistentes', $rel['totais']['ausentes']], ';');
    fputcsv($out, ['Taxa de Absenteismo (%)', number_format($rel['totais']['taxa_absenteismo'], 1, ',', '') . '%'], ';');
    fputcsv($out, ['Taxa de Comparecimento (%)', number_format($rel['totais']['taxa_comparecimento'], 1, ',', '') . '%'], ';');
    fputcsv($out, ['Tempo Medio de Espera (min)', $rel['totais']['media_espera_min'] . ' minutos'], ';');
    fputcsv($out, ['Tempo Medio de Pericia (min)', $rel['totais']['media_pericia_min'] . ' minutos'], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['DETALHAMENTO POR MEDICO PERITO'], ';');
    fputcsv($out, ['Perito Judicial', 'Sala em Uso', 'Agendados', 'Presentes', 'Concluidos', 'Aguardando', 'Ausentes', 'Tempo Medio (min)'], ';');
    foreach ($rel['peritos'] as $p) {
        fputcsv($out, [
            $p['perito'],
            $p['sala'],
            $p['agendados'],
            $p['presentes'],
            $p['concluidos'],
            $p['aguardando'],
            $p['ausentes'],
            $p['tempo_medio_min'] !== null ? $p['tempo_medio_min'] : '---'
        ], ';');
    }
    fclose($out);
    exit;
}

$isAdminOuSupervisor = in_array($_SESSION['usuario_role'], ['admin', 'supervisor'], true);
$metricas = $isAdminOuSupervisor ? obterMetricasDia($pdo, $dataBusca) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel do Administrador - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="flex items-center space-x-3 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-300">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <a href="alterar_senha_obrigatorio.php" class="text-sm underline">Minha senha</a>
            <form method="POST" action="logout.php" class="sga-logout"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>

    <!-- Main Central Hub -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-6 space-y-8">
        <?php if ($isAdminOuSupervisor && $metricas): ?>
            <!-- Seção de Indicadores Executivos do Dia -->
            <section class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-2xl space-y-6">
                <!-- Cabeçalho de Métricas com Seletor de Data e Exportação -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-800">
                    <div>
                        <h2 class="text-lg font-black text-white flex items-center gap-2">
                            <span>📊</span> Produtividade & Métricas Diárias das Perícias
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Visão consolidada em tempo real da Seção Judiciária</p>
                    </div>

                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="date" name="data_busca" value="<?= htmlspecialchars($dataBusca, ENT_QUOTES, 'UTF-8') ?>" class="bg-gray-950 border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition">
                            Filtrar
                        </button>
                        <a href="dashboard.php?exportar=metricas_csv&data_busca=<?= urlencode($dataBusca) ?>" class="bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-700/80 text-emerald-300 text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 shadow-sm">
                            📥 Exportar Relatório (.CSV)
                        </a>
                    </form>
                </div>

                <!-- 4 Cards de Topo (KPIs) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Agendados & Presentes -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Pauta & Comparecimento</span>
                        <div class="text-2xl font-black text-white mt-1"><?= $metricas['totais']['presentes'] ?> <span class="text-sm font-normal text-gray-400">/ <?= $metricas['totais']['agendados'] ?></span></div>
                        <div class="text-[11px] text-emerald-400 mt-1 font-semibold flex items-center gap-1">
                            <span>🟢 <?= $metricas['totais']['taxa_comparecimento'] ?>% de presença</span>
                        </div>
                    </div>

                    <!-- Perícias Concluídas -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Perícias Realizadas</span>
                        <div class="text-2xl font-black text-emerald-400 mt-1"><?= $metricas['totais']['concluidos'] ?></div>
                        <div class="text-[11px] text-gray-400 mt-1 flex items-center gap-2">
                            <span>Aguardando: <strong class="text-white"><?= $metricas['totais']['aguardando'] ?></strong></span>
                            <span>•</span>
                            <span>Em curso: <strong class="text-blue-400"><?= $metricas['totais']['em_atendimento'] ?></strong></span>
                        </div>
                    </div>

                    <!-- Tempo Médio de Espera -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Tempo Médio de Espera</span>
                        <div class="text-2xl font-black text-amber-400 mt-1"><?= $metricas['totais']['media_espera_min'] ?> <span class="text-xs font-normal text-gray-400">minutos</span></div>
                        <span class="text-[10px] text-gray-400 mt-1 block">Chegada até 1ª chamada</span>
                    </div>

                    <!-- Tempo Médio de Perícia / Absenteísmo -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Média de Perícia & Falta</span>
                        <div class="text-2xl font-black text-blue-400 mt-1"><?= $metricas['totais']['media_pericia_min'] ?> <span class="text-xs font-normal text-gray-400">min</span></div>
                        <div class="text-[11px] text-rose-400 mt-1 font-medium">
                            <span>Absenteísmo: <?= $metricas['totais']['taxa_absenteismo'] ?>% (<?= $metricas['totais']['ausentes'] ?> faltas)</span>
                        </div>
                    </div>
                </div>

                <!-- Detalhamento por Médico Perito -->
                <?php if (!empty($metricas['peritos'])): ?>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Produtividade por Médico Perito</h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-800">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-950 text-gray-400 uppercase font-bold border-b border-gray-800">
                                <tr>
                                    <th class="py-2.5 px-3">Médico Perito</th>
                                    <th class="py-2.5 px-3">Sala</th>
                                    <th class="py-2.5 px-3 text-center">Agendados</th>
                                    <th class="py-2.5 px-3 text-center">Presentes</th>
                                    <th class="py-2.5 px-3 text-center">Concluídos</th>
                                    <th class="py-2.5 px-3 text-center">Na Fila</th>
                                    <th class="py-2.5 px-3 text-center">Ausentes</th>
                                    <th class="py-2.5 px-3 text-right">Tempo Médio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/60 bg-gray-950/40">
                                <?php foreach ($metricas['peritos'] as $mp): ?>
                                <tr class="hover:bg-gray-800/30">
                                    <td class="py-2.5 px-3 font-semibold text-white"><?= htmlspecialchars((string)$mp['perito'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 px-3 text-gray-400 font-mono"><?= htmlspecialchars((string)$mp['sala'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2.5 px-3 text-center font-bold text-white"><?= (int)$mp['agendados'] ?></td>
                                    <td class="py-2.5 px-3 text-center text-emerald-400 font-bold"><?= (int)$mp['presentes'] ?></td>
                                    <td class="py-2.5 px-3 text-center text-blue-400 font-bold"><?= (int)$mp['concluidos'] ?></td>
                                    <td class="py-2.5 px-3 text-center text-amber-400 font-bold"><?= (int)$mp['aguardando'] ?></td>
                                    <td class="py-2.5 px-3 text-center text-rose-400 font-bold"><?= (int)$mp['ausentes'] ?></td>
                                    <td class="py-2.5 px-3 text-right font-mono text-gray-300">
                                        <?= $mp['tempo_medio_min'] !== null ? $mp['tempo_medio_min'] . ' min' : '---' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="text-center flex flex-col items-center">
            <h2 class="text-xl font-black text-white leading-tight">Módulos do Sistema</h2>
            <p class="text-xs text-gray-400 mt-1">Selecione o subsistema que deseja acessar</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
            <!-- Recepção Card -->
            <a href="recepcao.php" class="bg-gray-900 border border-gray-800 hover:border-emerald-500/50 p-6 rounded-3xl shadow-xl hover:shadow-emerald-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-emerald-950/50 border border-emerald-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📋
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-emerald-400 transition-colors">Recepção</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Confirmar chegada de periciados e gerenciar agendamentos do dia.</p>
                <div class="text-xs text-emerald-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Acessar Fila <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
            <!-- Atendente (Perito) Card -->
            <a href="atendente.php" class="bg-gray-900 border border-gray-800 hover:border-blue-500/50 p-6 rounded-3xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🧑‍⚕️
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel do Perito</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Chamar periciados por ordem de chegada para a sua sala de atendimento.</p>
                <div class="text-xs text-blue-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Chamar Paciente <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- TV Panel Card -->
            <a href="painel.php" target="_blank" class="bg-gray-900 border border-gray-800 hover:border-amber-500/50 p-6 rounded-3xl shadow-xl hover:shadow-amber-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-amber-950/50 border border-amber-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📺
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-amber-400 transition-colors">Painel da TV</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Tela de TV da recepção para exibição das chamadas de senhas e vídeo.</p>
                <div class="text-xs text-amber-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Abrir TV <span>→</span>
                </div>
            </a>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Auditoria Card -->
            <a href="auditoria.php" class="bg-gray-900 border border-gray-800 hover:border-teal-500/50 p-6 rounded-3xl shadow-xl hover:shadow-teal-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-teal-950/50 border border-teal-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🔎
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-teal-400 transition-colors">Auditoria de Chamadas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Histórico completo de atendimentos, logs de chamadas, filtros por perito e exportação CSV.</p>
                <div class="text-xs text-teal-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Ver Auditoria <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
            <!-- Perfis e Senhas Card -->
            <a href="usuarios.php" class="bg-gray-900 border border-gray-800 hover:border-indigo-500/50 p-6 rounded-3xl shadow-xl hover:shadow-indigo-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-indigo-950/50 border border-indigo-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🔑
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-indigo-400 transition-colors">Perfis e Senhas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Gerenciar contas de acesso, senhas e perfis de recepção e administrador.</p>
                <div class="text-xs text-indigo-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Contas <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Salas de Atendimento Card -->
            <a href="salas.php" class="bg-gray-900 border border-gray-800 hover:border-pink-500/50 p-6 rounded-3xl shadow-xl hover:shadow-pink-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-pink-950/50 border border-pink-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🚪
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-pink-400 transition-colors">Salas de Atendimento</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Cadastrar e gerenciar salas físicas que estarão disponíveis para escolha dos peritos.</p>
                <div class="text-xs text-pink-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Salas <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Gerenciar Pautas Card -->
            <a href="gerenciar_pauta.php" class="bg-gray-900 border border-gray-800 hover:border-blue-500/50 p-6 rounded-3xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📅
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Gerenciar Pautas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Filtrar, excluir e transferir agendamentos de forma massiva por dia ou mês inteiro.</p>
                <div class="text-xs text-blue-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Pauta <span>→</span>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body>
</html>
