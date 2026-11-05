<?php
require_once __DIR__ . '/banco.php';
require_once __DIR__ . '/importacao_service.php';

if (!in_array($_SESSION['usuario_role'], ['admin', 'supervisor'], true)) {
    abortar(403, 'Acesso negado.');
}

$previa = $_SESSION['importacao_previa'] ?? null;
if (!$previa || ($previa['criada'] ?? 0) < time() - 1800) {
    abortar(410, 'Prévia expirada ou inexistente. Importe novamente.');
}

$erro = '';
$registros = (array)($previa['registros'] ?? []);
$datasEscopo = (array)($previa['datas_escopo'] ?? array_values(array_unique(array_filter(array_column($registros, 'data_pericia')))));
$sincronizar = (bool)($previa['sincronizar'] ?? true);
$janela = (string)($previa['janela'] ?? 'dia');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelar'])) {
        unset($_SESSION['importacao_previa']);
        header('Location: gerenciar_pauta.php', true, 303);
        exit;
    }

    try {
        $resultado = importarRegistros($pdo, $registros, $datasEscopo, $sincronizar);
        unset($_SESSION['importacao_previa']);

        $partes = [];
        $partes[] = $resultado['novos'] . ' novos agendamentos incluídos';
        $partes[] = $resultado['atualizados'] . ' atualizados';
        if (!empty($resultado['cancelados'])) {
            $partes[] = $resultado['cancelados'] . ' cancelados por atualização de pauta';
        }
        $partes[] = $resultado['preservados'] . ' preservados em andamento/concluídos';
        if (!empty($resultado['usuarios'])) {
            $partes[] = $resultado['usuarios'] . ' novas contas de perito aguardando ativação';
        }

        $_SESSION['flash_pauta'] = [
            'Importação e sincronização concluídas com sucesso: ' . implode('; ', $partes) . '.',
            'success'
        ];
        header('Location: gerenciar_pauta.php', true, 303);
        exit;
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        $erro = erroPublico($e);
    }
}

// Calcular resumo da prévia para visualização
$resumo = calcularResumoPrevia($pdo, $registros, $datasEscopo, $sincronizar);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conferir e Sincronizar Pauta — SGA JFAL</title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen p-6">
    <main class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <span>📊</span> Conferir e Sincronizar Importação da Pauta
                </h1>
                <p class="text-xs text-gray-400 mt-1">
                    Confira abaixo os dados filtrados pela janela selecionada e o impacto na pauta do sistema antes de confirmar.
                </p>
            </div>
            <a href="gerenciar_pauta.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-900 px-3 py-1.5 rounded-lg transition">
                ← Voltar para Pautas
            </a>
        </div>

        <!-- Badges de Configuração -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-950/80 text-blue-300 border border-blue-800/80">
                <?= $janela === '3dias' ? '📅 Janela: Hoje e próximos 2 dias (Máximo 3 dias)' : '📅 Janela: Apenas a pauta de hoje (1 dia)' ?>
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-900 text-gray-300 border border-gray-800">
                Datas no escopo: <?= implode(', ', array_map(static fn($d) => date('d/m/Y', strtotime($d)), $datasEscopo)) ?>
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $sincronizar ? 'bg-amber-950/80 text-amber-300 border border-amber-800/80' : 'bg-gray-900 text-gray-400 border border-gray-800' ?>">
                <?= $sincronizar ? '⚡ Sincronização Diária Ativa' : 'Apenas Adicionar / Atualizar' ?>
            </span>
        </div>

        <?php if ($erro): ?>
        <div role="alert" class="bg-red-950/80 border border-red-500 text-red-200 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-400"></span>
            <span><?= escapar($erro) ?></span>
        </div>
        <?php endif; ?>

        <!-- Cards de Estatísticas da Importação -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-900 p-4 rounded-xl border border-gray-800">
                <div class="text-2xl font-black text-emerald-400"><?= $resumo['novos'] ?></div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-1">Novos a Incluir</div>
                <div class="text-[10px] text-gray-400 mt-0.5">Inseridos como agendado</div>
            </div>
            <div class="bg-gray-900 p-4 rounded-xl border border-gray-800">
                <div class="text-2xl font-black text-blue-400"><?= $resumo['mantidos'] ?></div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-1">Mantidos / Atualizados</div>
                <div class="text-[10px] text-gray-400 mt-0.5">Já constavam na pauta</div>
            </div>
            <div class="bg-gray-900 p-4 rounded-xl border <?= $resumo['total_cancelar'] > 0 ? 'border-amber-700/60 bg-amber-950/20' : 'border-gray-800' ?>">
                <div class="text-2xl font-black <?= $resumo['total_cancelar'] > 0 ? 'text-amber-400' : 'text-gray-400' ?>"><?= $resumo['total_cancelar'] ?></div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-1">A Cancelar da Pauta</div>
                <div class="text-[10px] text-gray-400 mt-0.5">Removidos da planilha oficial</div>
            </div>
            <div class="bg-gray-900 p-4 rounded-xl border border-gray-800">
                <div class="text-2xl font-black text-purple-400"><?= $resumo['preservados_andamento'] ?></div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-1">Preservados</div>
                <div class="text-[10px] text-gray-400 mt-0.5">Presença confirmada / concluídos</div>
            </div>
        </div>

        <!-- Alerta e Tabela de Cancelamentos Previstos -->
        <?php if ($resumo['total_cancelar'] > 0): ?>
        <div class="bg-amber-950/30 border border-amber-600/50 rounded-2xl p-5 space-y-3">
            <div class="flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <h3 class="text-sm font-bold text-amber-300">
                        <?= $resumo['total_cancelar'] ?> agendamento(s) no sistema não constam nesta planilha e serão cancelados:
                    </h3>
                    <p class="text-xs text-amber-200/80 mt-1">
                        Ao confirmar, o status será alterado para <strong>"Cancelado"</strong> e esses registros sairão das telas de chamada e da recepção, com registro na auditoria. Atendimentos com presença confirmada hoje nunca são cancelados.
                    </p>
                </div>
            </div>

            <div class="max-h-52 overflow-y-auto rounded-xl border border-amber-900/40 bg-gray-950/60">
                <table class="w-full text-xs text-left">
                    <thead class="bg-amber-950/80 text-amber-200 sticky top-0">
                        <tr>
                            <th class="p-2.5">Processo</th>
                            <th class="p-2.5">Periciado</th>
                            <th class="p-2.5">Perito</th>
                            <th class="p-2.5">Data da Pauta</th>
                            <th class="p-2.5">Status Atual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-900/30">
                        <?php foreach ($resumo['cancelar'] as $c): ?>
                        <tr class="hover:bg-amber-900/20">
                            <td class="p-2.5 font-mono text-gray-300"><?= escapar($c['processo'] ?: 'Sem processo') ?></td>
                            <td class="p-2.5 text-white font-medium"><?= escapar($c['nome'] ?: '(Sem nome)') ?></td>
                            <td class="p-2.5 text-gray-300"><?= escapar($c['perito'] ?: 'Sem perito') ?></td>
                            <td class="p-2.5 text-gray-400"><?= date('d/m/Y', strtotime($c['data_pauta'])) ?></td>
                            <td class="p-2.5 text-amber-400 font-semibold uppercase text-[10px]"><?= escapar($c['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabela com Amostra dos Registros da Planilha -->
        <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-200">
                    Registros da Planilha: <?= count($registros) ?> agendamento(s) validados para o escopo
                </h3>
                <span class="text-xs text-gray-400">
                    <?= count($registros) > 100 ? 'Exibindo os primeiros 100 registros' : 'Exibindo todos os registros' ?>
                </span>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-800 max-h-96 overflow-y-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-950 text-gray-400 sticky top-0">
                        <tr>
                            <th class="p-2.5">Data</th>
                            <th class="p-2.5">Processo</th>
                            <th class="p-2.5">Periciado</th>
                            <th class="p-2.5">CPF</th>
                            <th class="p-2.5">Perito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 bg-gray-950/40">
                        <?php foreach (array_slice($registros, 0, 100) as $r): ?>
                        <tr class="hover:bg-gray-800/40">
                            <td class="p-2.5 text-gray-400"><?= escapar(date('d/m/Y', strtotime($r['data_pericia']))) ?></td>
                            <td class="p-2.5 font-mono text-gray-300"><?= escapar($r['processo'] ?: '-') ?></td>
                            <td class="p-2.5 font-medium text-white"><?= escapar($r['periciado'] ?: 'Conferir nome') ?></td>
                            <td class="p-2.5 text-gray-400"><?= escapar($r['cpf'] ?: '-') ?></td>
                            <td class="p-2.5 text-gray-300"><?= escapar($r['perito'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <form method="POST" class="flex items-center gap-3 pt-2" onsubmit="var btn=this.querySelector('button[type=submit]'); if(btn){btn.disabled=true;btn.textContent='Importando e sincronizando...';}">
            <?= csrfCampo() ?>
            <button type="submit" class="bg-blue-600 hover:bg-blue-750 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition shadow-lg shadow-blue-900/30">
                ✅ Confirmar Importação e Sincronização
            </button>
            <button type="submit" name="cancelar" value="1" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-semibold px-5 py-2.5 rounded-lg transition border border-gray-700">
                Cancelar
            </button>
        </form>
    </main>

    <script src="assets/acessibilidade.js" defer></script>
    <script src="assets/usabilidade.js" defer></script>
</body>
</html>
