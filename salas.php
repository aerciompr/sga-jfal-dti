<?php
/**
 * GERENCIAMENTO DE SALAS - JFAL
 * 
 * salas.php: Interface CRUD restrita a administradores e supervisores.
 * Funcionalidades:
 * - Listagem de salas cadastradas com monitoramento de ocupação em tempo real.
 * - Criação de novas salas.
 * - Exclusão de salas desocupadas.
 */

require_once __DIR__ . '/banco.php';
require_once __DIR__ . '/atendimento_service.php';

// Proteção de Acesso: Apenas administradores e supervisores podem acessar este módulo
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])) {
    header("Location: dashboard.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$message = '';
$message_type = '';

[$message,$message_type]=$_SESSION['flash_salas']??['','']; unset($_SESSION['flash_salas']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $pdo->beginTransaction();
    try {
        if (($_POST['action']??'')==='cadastrar') {
            $nome=trim((string)($_POST['nome']??''));
            if ($nome==='' || mb_strlen($nome)>50) throw new DomainException('Informe nome de até 50 caracteres.');
            $s=$pdo->prepare('INSERT INTO salas (nome) VALUES (?)'); $s->execute([$nome]); registrarLog($pdo,'Cadastrar Sala',"Sala: $nome");
        } elseif (($_POST['action']??'')==='deletar') {
            $s=$pdo->prepare('SELECT nome FROM salas WHERE id=? FOR UPDATE'); $s->execute([(int)($_POST['id']??0)]); $nome=$s->fetchColumn();
            if (!$nome) throw new DomainException('Sala não encontrada.');
            $s=$pdo->prepare("SELECT id FROM atendimentos WHERE sala=? AND data_pauta=CURRENT_DATE() AND status IN ('chamada','atendimento') LIMIT 1 FOR UPDATE");$s->execute([$nome]);
            if ($s->fetchColumn()) throw new DomainException('Não é possível excluir uma sala em uso.');
            $s=$pdo->prepare('DELETE FROM salas_peritos WHERE sala=?');$s->execute([$nome]);
            $s=$pdo->prepare('DELETE FROM salas WHERE nome=?');$s->execute([$nome]); registrarLog($pdo,'Excluir Sala',"Sala: $nome; histórico preservado.");
        } else throw new DomainException('Ação inválida.');
        $pdo->commit(); $message='Salas atualizadas com sucesso.'; $message_type='success';
    } catch (DomainException $e) { $pdo->rollBack(); $message=$e->getMessage(); $message_type='error'; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $message=erroPublico($e); $message_type='error'; }
    $_SESSION['flash_salas']=[$message,$message_type]; header('Location: salas.php',true,303);exit;
}

// Busca todas as salas ordenadas por nome para a listagem
$stmt = $pdo->query("SELECT id, nome FROM salas ORDER BY nome ASC");
$salas = $stmt->fetchAll();

// Obtém status de ocupação das salas hoje
$salasOcupadas = obterSalasOcupadasHoje($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Salas - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
    <script src="tema.js?v=2.5"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/header_menu.php'; ?>

    <main class="flex-1 w-full max-w-[1600px] mx-auto p-4 sm:p-6 lg:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Mensagem de Confirmação em Largura Total no Topo -->
        <?php if ($message): ?>
            <div role="status" aria-live="polite" class="col-span-full sga-feedback p-3.5 rounded-xl border text-xs text-center font-bold shadow-sm <?= $message_type === 'success' ? 'bg-emerald-950/40 border-emerald-800/80 text-emerald-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-800/80 text-blue-300' : 'bg-red-950/40 border-red-800/80 text-red-300') ?>">
                <?= escapar($message) ?>
            </div>
        <?php endif; ?>

        <!-- Formulário Lateral para Cadastro -->
        <div class="md:col-span-1 space-y-6">
            <!-- Card de Formulário -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4 flex items-center gap-2">
                    <span>🚪</span> Cadastrar Nova Sala
                </h2>
                
                <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="cadastrar">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Identificação da Sala</label>
                        <input type="text" name="nome" required placeholder="Ex: Sala 1 ou Consultório A" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <span class="text-[10px] text-gray-500 mt-1 block">A sala ficará disponível na lista de seleção dos médicos peritos.</span>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2.5 rounded-lg transition shadow-md">
                        Cadastrar Sala
                    </button>
                </form>
            </div>
        </div>

        <!-- Grade de Salas Cadastradas com Monitor de Ocupação -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4 flex justify-between items-center">
                    <span>Salas Físicas Cadastradas</span>
                    <span class="bg-gray-800 text-gray-400 text-xs px-2.5 py-0.5 rounded-full font-bold"><?= count($salas) ?></span>
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2.5 w-12">ID</th>
                                <th class="pb-2.5">Sala</th>
                                <th class="pb-2.5">Status Hoje</th>
                                <th class="pb-2.5 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php if (empty($salas)): ?>
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-xs text-gray-500">Nenhuma sala cadastrada no sistema.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salas as $s): ?>
                                    <?php 
                                    $emUso = isset($salasOcupadas[$s['nome']]);
                                    $medicoOcupante = $emUso ? $salasOcupadas[$s['nome']]['perito'] : '';
                                    ?>
                                    <tr class="hover:bg-gray-800/30">
                                        <td class="py-3 font-mono text-xs text-gray-500"><?= $s['id'] ?></td>
                                        <td class="py-3 font-bold text-white"><?= escapar($s['nome']) ?></td>
                                        <td class="py-3">
                                            <?php if ($emUso): ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-950/40 border border-amber-900/60 text-amber-300" title="Sala em atendimento hoje">
                                                    🟡 Em Uso • <?= escapar($medicoOcupante) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-950/40 border border-emerald-900/60 text-emerald-300">
                                                    🟢 Disponível
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-right">
                                            <?php if ($emUso): ?>
                                                <span class="text-[10px] text-gray-500 italic">Em atendimento</span>
                                            <?php else: ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Deseja excluir esta sala?');"><?= csrfCampo() ?>
                                                    <input type="hidden" name="action" value="deletar">
                                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                    <button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1 rounded transition">
                                                        Excluir
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
</body>
</html>
