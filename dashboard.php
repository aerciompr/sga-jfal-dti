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

$isAdminOuSupervisor = in_array($_SESSION['usuario_role'], ['admin', 'supervisor'], true);
$abaAtiva = (string)($_GET['aba'] ?? (isset($_GET['data_busca']) ? 'metricas' : 'modulos'));
if ($abaAtiva !== 'metricas' || !$isAdminOuSupervisor) {
    $abaAtiva = 'modulos';
}

// Exportação do Relatório Executivo em CSV para a Diretoria / Coordenação
if (isset($_GET['exportar']) && $_GET['exportar'] === 'metricas_csv' && $isAdminOuSupervisor) {
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

$metricas = $isAdminOuSupervisor ? obterMetricasDia($pdo, $dataBusca) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Administrador - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
    <script src="tema.js?v=2.5"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/header_menu.php'; ?>

    <!-- Main Central Hub com Abas -->
    <main class="flex-1 w-full max-w-[1600px] mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Barra de Abas do Dashboard -->
        <div class="flex items-center justify-between border-b border-gray-800 pb-3 mb-6">
            <div class="flex items-center gap-2">
                <button type="button" id="tab-btn-modulos" onclick="alternarAbaDashboard('modulos')" class="tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border <?= $abaAtiva === 'modulos' ? 'bg-blue-600 text-white border-blue-500 shadow-md tab-ativo' : 'bg-gray-900/70 text-gray-400 border-gray-800 hover:text-white hover:bg-gray-800/50' ?>">
                    <span>🏛️</span> Módulos Principais
                </button>
                <?php if ($isAdminOuSupervisor): ?>
                <button type="button" id="tab-btn-metricas" onclick="alternarAbaDashboard('metricas')" class="tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border <?= $abaAtiva === 'metricas' ? 'bg-blue-600 text-white border-blue-500 shadow-md tab-ativo' : 'bg-gray-900/70 text-gray-400 border-gray-800 hover:text-white hover:bg-gray-800/50' ?>">
                    <span>📊</span> Métricas & Produtividade
                </button>
                <?php endif; ?>
            </div>
            <div class="text-[11px] text-gray-500 hidden sm:flex items-center gap-1.5 font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> Sistema Ativo • Justiça Federal em Alagoas
            </div>
        </div>

        <!-- ABA 1: Módulos do Sistema (Principal) -->
        <div id="aba-conteudo-modulos" class="<?= $abaAtiva === 'modulos' ? '' : 'hidden' ?> space-y-6">
            <div class="text-center flex flex-col items-center">
                <h2 class="text-xl font-black text-white leading-tight">Módulos do Sistema</h2>
                <p class="text-xs text-gray-400 mt-1">Selecione o subsistema que deseja acessar</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <!-- Recepção Card -->
                <a href="recepcao" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📋
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Recepção</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Confirmar chegada de periciados e gerenciar agendamentos do dia.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Acessar Fila <span>→</span>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <!-- Atendente (Perito) Card -->
                <a href="atendente" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🧑‍⚕️
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel do Perito</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Chamar periciados por ordem de chegada para a sua sala de atendimento.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Chamar Paciente <span>→</span>
                    </div>
                </a>
                <?php endif; ?>

                <!-- TV Panel Card -->
                <a href="painel" target="_blank" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📺
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel da TV</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Tela de TV da recepção para exibição das chamadas de senhas e vídeo.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Abrir TV <span>→</span>
                    </div>
                </a>

                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <!-- Gerenciar Pautas Card -->
                <a href="gerenciar_pauta" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📅
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Gerenciar Pautas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Filtrar, excluir e transferir agendamentos de forma massiva por dia ou mês inteiro.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar Pauta <span>→</span>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <!-- Salas de Atendimento Card -->
                <a href="salas" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🚪
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Salas & Desbloqueio</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Cadastrar e gerenciar salas, monitorar ocupação e forçar desbloqueio de travas em tempo real.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar e Desbloquear Salas <span>→</span>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <!-- Auditoria Card -->
                <a href="auditoria" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🔎
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Auditoria de Chamadas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Histórico completo de atendimentos, logs de chamadas, filtros por perito e exportação CSV.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Ver Auditoria <span>→</span>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <!-- Perfis e Senhas Card -->
                <a href="usuarios" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🔑
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Perfis e Senhas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Gerenciar contas de acesso, senhas e perfis de recepção e administrador.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar Contas <span>→</span>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdminOuSupervisor && $metricas): ?>
        <!-- ABA 2: Métricas & Produtividade -->
        <div id="aba-conteudo-metricas" class="<?= $abaAtiva === 'metricas' ? '' : 'hidden' ?> space-y-6">
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
                        <input type="hidden" name="aba" value="metricas">
                        <input type="date" name="data_busca" value="<?= htmlspecialchars($dataBusca, ENT_QUOTES, 'UTF-8') ?>" class="bg-gray-950 border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition">
                            Filtrar
                        </button>
                        <a href="dashboard?exportar=metricas_csv&data_busca=<?= urlencode($dataBusca) ?>" class="bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-700/80 text-emerald-300 text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 shadow-sm">
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
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>

<script>
function alternarAbaDashboard(aba) {
    const modulosConteudo = document.getElementById('aba-conteudo-modulos');
    const metricasConteudo = document.getElementById('aba-conteudo-metricas');
    const btnModulos = document.getElementById('tab-btn-modulos');
    const btnMetricas = document.getElementById('tab-btn-metricas');

    if (aba === 'metricas' && metricasConteudo) {
        modulosConteudo.classList.add('hidden');
        metricasConteudo.classList.remove('hidden');

        btnModulos.className = 'tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border bg-gray-900/70 text-gray-400 border-gray-800 hover:text-white hover:bg-gray-800/50';
        btnMetricas.className = 'tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border bg-blue-600 text-white border-blue-500 shadow-md tab-ativo';
        localStorage.setItem('sga_dashboard_aba', 'metricas');
    } else {
        if (metricasConteudo) metricasConteudo.classList.add('hidden');
        modulosConteudo.classList.remove('hidden');

        btnModulos.className = 'tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border bg-blue-600 text-white border-blue-500 shadow-md tab-ativo';
        if (btnMetricas) {
            btnMetricas.className = 'tab-btn flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition border bg-gray-900/70 text-gray-400 border-gray-800 hover:text-white hover:bg-gray-800/50';
        }
        localStorage.setItem('sga_dashboard_aba', 'modulos');
    }

    const url = new URL(window.location);
    url.searchParams.set('aba', aba);
    window.history.replaceState({}, '', url);
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const abaParam = params.get('aba');
    if (!abaParam && !params.get('data_busca')) {
        const salva = localStorage.getItem('sga_dashboard_aba');
        if (salva === 'metricas' && document.getElementById('aba-conteudo-metricas')) {
            alternarAbaDashboard('metricas');
        }
    }
});
</script>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
</body>
</html>