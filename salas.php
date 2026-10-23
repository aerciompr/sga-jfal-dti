<?php
/**
 * GERENCIAMENTO DE SALAS - JFAL
 * 
 * salas.php: Interface CRUD restrita a administradores e recepcionistas.
 * Funcionalidades:
 * - Listagem de salas cadastradas.
 * - Criação de novas salas.
 * - Exclusão de salas.
 */


require_once __DIR__ . '/banco.php';

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
        $pdo->commit(); $message='Salas atualizadas.'; $message_type='success';
    } catch (DomainException $e) { $pdo->rollBack(); $message=$e->getMessage(); $message_type='error'; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $message=erroPublico($e); $message_type='error'; }
    $_SESSION['flash_salas']=[$message,$message_type]; header('Location: salas.php',true,303);exit;
}

// Busca todas as salas ordenadas por nome para a listagem
$stmt = $pdo->query("SELECT id, nome FROM salas ORDER BY nome ASC");
$salas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Salas - <?= escapar($sys_name) ?></title>
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
            <form method="POST" action="logout.php" class="sga-logout"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Formulário Lateral para Cadastro -->
        <div class="md:col-span-1 space-y-6">
            <?php if ($message): ?>
                <div role="status" aria-live="polite" class="sga-feedback p-3 rounded-lg border text-xs text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= escapar($message) ?>
                </div>
            <?php endif; ?>

            <!-- Card de Formulário -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4">
                    🆕 Cadastrar Nova Sala
                </h2>
                
                <form method="POST" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" value="cadastrar">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome da Sala</label>
                        <input type="text" name="nome" required placeholder="Ex: Sala 1" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Cadastrar Sala
                    </button>
                </form>
            </div>
        </div>

        <!-- Grade de Salas Cadastradas -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4 flex justify-between items-center">
                    <span>Salas Cadastradas</span>
                    <span class="bg-gray-800 text-gray-400 text-xs px-2.5 py-0.5 rounded-full font-bold"><?= count($salas) ?></span>
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2">ID</th>
                                <th class="pb-2">Identificação / Nome da Sala</th>
                                <th class="pb-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php if (empty($salas)): ?>
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-xs text-gray-500">Nenhuma sala cadastrada.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salas as $s): ?>
                                    <tr>
                                        <td class="py-3 font-mono text-xs text-gray-500"><?= $s['id'] ?></td>
                                        <td class="py-3 font-bold text-white"><?= escapar($s['nome']) ?></td>
                                        <td class="py-3 text-right">
                                            <form method="POST" class="inline" onsubmit="return confirm('Deseja excluir esta sala?');"><?= csrfCampo() ?>
                                                <input type="hidden" name="action" value="deletar">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1 rounded transition">
                                                    Excluir
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
