<?php
require_once __DIR__ . '/banco.php';
require_once __DIR__ . '/importacao_service.php';
if (!in_array($_SESSION['usuario_role'], ['admin','supervisor'], true)) abortar(403, 'Acesso negado.');
$previa = $_SESSION['importacao_previa'] ?? null;
if (!$previa || $previa['criada'] < time() - 1800) abortar(410, 'Prévia expirada. Importe novamente.');
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelar'])) { unset($_SESSION['importacao_previa']); header('Location: gerenciar_pauta.php', true, 303); exit; }
    try {
        $resultado = importarRegistros($pdo, $previa['registros']);
        unset($_SESSION['importacao_previa']);
        $_SESSION['flash_pauta'] = ['Importação concluída: ' . $resultado['novos'] . ' novos; ' . $resultado['atualizados'] . ' atualizados; ' . $resultado['preservados'] . ' em andamento/concluídos preservados; ' . $resultado['usuarios'] . ' contas aguardando ativação em Usuários.', 'success'];
        header('Location: gerenciar_pauta.php', true, 303); exit;
    } catch (DomainException $e) { $erro = $e->getMessage(); }
    catch (Throwable $e) { $erro = erroPublico($e); }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conferir importação — SGA</title><link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css"></head>
<body class="bg-gray-950 text-gray-100 p-6"><main class="max-w-5xl mx-auto"><h1 class="text-xl font-bold">Conferir importação</h1>
<p class="my-4"><?= count($previa['registros']) ?> agendamentos de hoje em diante. Datas passadas foram descartadas. Registros já iniciados serão preservados. Novas contas precisam de senha individual atribuída pelo administrador.</p>
<?php if ($erro): ?><p role="alert"><?= escapar($erro) ?></p><?php endif; ?>
<p>Abaixo, até 100 registros. A confirmação abrange todos os <?= count($previa['registros']) ?> registros validados.</p>
<div class="overflow-x-auto"><table class="w-full my-4 text-sm"><thead><tr><th>Data</th><th>Processo</th><th>Periciado</th><th>CPF</th><th>Perito</th></tr></thead><tbody>
<?php foreach (array_slice($previa['registros'], 0, 100) as $r): ?><tr><?php foreach (['data_pericia','processo','periciado','cpf','perito'] as $campo): ?><td class="p-2"><?= escapar($r[$campo] ?: 'Conferir nome') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div><form method="post"><?= csrfCampo() ?><button class="bg-blue-600 p-3 rounded" type="submit">Confirmar importação</button> <button class="bg-gray-700 p-3 rounded" name="cancelar" value="1">Cancelar</button></form></main><script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body></html>
