<?php
/**
 * GERENCIAMENTO DE PERFIS E SENHAS - JFAL
 * 
 * usuarios.php: Interface CRUD restrita a administradores e recepcionistas.
 * Funcionalidades:
 * - Listagem de usuários cadastrados com suas permissões (badges).
 * - Criação de novos usuários de recepção ou administração.
 * - Edição de nome de usuário, cargo e redefinição de senha com hash seguro (password_hash).
 * - Exclusão de contas (impedindo o usuário atual de deletar a própria sessão).
 */

session_start();
require_once __DIR__ . '/banco.php';

// Proteção de Acesso: Apenas administradores podem gerenciar perfis e senhas
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Forçar troca de senha de primeiro acesso
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
$message = '';
$message_type = '';

$current_user_id = $_SESSION['usuario_id'];

// Processamento de Ações do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Ação 1: Cadastrar Novo Usuário
        if ($action === 'cadastrar') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'recepcao';
            $nome = trim($_POST['nome'] ?? '');
            
            if ($username && $password) {
                try {
                    // Impede duplicar o nome de usuário no banco de dados
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = "Nome de usuário '$username' já está cadastrado.";
                        $message_type = "error";
                    } else {
                        // Aplica hash bcrypt nativo e insere o registro
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $role, $nome ?: null]);
                        $message = "Usuário '$username' cadastrado com sucesso!";
                        $message_type = "success";
                    }
                } catch (Exception $e) {
                    $message = "Erro ao cadastrar: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = "Preencha todos os campos.";
                $message_type = "error";
            }
        }
        
        // Ação 2: Editar Usuário Existente
        elseif ($action === 'editar') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'recepcao';
            $nome = trim($_POST['nome'] ?? '');
            
            if ($id && $username) {
                try {
                    // Valida se o novo nome de usuário não está em uso por outra conta
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = "Nome de usuário '$username' já está cadastrado para outra pessoa.";
                        $message_type = "error";
                    } else {
                        // Se uma nova senha for informada, gera o hash e atualiza, senão mantém a senha atual
                        if ($password) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, password = ?, role = ?, nome = ? WHERE id = ?");
                            $stmt->execute([$username, $hash, $role, $nome ?: null, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, role = ?, nome = ? WHERE id = ?");
                            $stmt->execute([$username, $role, $nome ?: null, $id]);
                        }
                        
                        // Atualiza os dados da sessão em tempo real se o usuário editado for a conta logada
                        if ($id === $current_user_id) {
                            $_SESSION['usuario_name'] = $username;
                            $_SESSION['usuario_role'] = $role;
                            $_SESSION['usuario_nome'] = $nome ?: $username;
                        }
                        
                        $message = "Usuário '$username' atualizado com sucesso!";
                        $message_type = "success";
                    }
                } catch (Exception $e) {
                    $message = "Erro ao atualizar: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = "Preencha os campos obrigatórios.";
                $message_type = "error";
            }
        }
        
        // Ação 3: Deletar Usuário
        elseif ($action === 'deletar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Impede que o usuário exclua a si mesmo
                if ($id === $current_user_id) {
                    $message = "Você não pode excluir sua própria conta enquanto estiver conectado!";
                    $message_type = "error";
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = "Usuário removido com sucesso.";
                        $message_type = "info";
                    } catch (Exception $e) {
                        $message = "Erro ao excluir: " . $e->getMessage();
                        $message_type = "error";
                    }
                }
            }
        }
    }
}

// Busca todos os usuários ordenados por ID para a listagem
$stmt = $pdo->query("SELECT id, username, role, nome FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Gerenciamento de Perfis e Senhas - <?= htmlspecialchars($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="transition hover:opacity-90 flex items-center">
                <img src="assets/logo-jfal-completo.png" id="logo-menu" alt="Justiça Federal em Alagoas" class="h-8 object-contain">
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

    <main class="flex-1 max-w-5xl w-full mx-auto p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Formulário Lateral para Cadastro e Edição -->
        <div class="md:col-span-1 space-y-6">
            <?php if ($message): ?>
                <div class="p-3 rounded-lg border text-xs text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Card de Formulário -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl" id="form-card">
                <h2 id="form-title" class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4">
                    🆕 Cadastrar Novo Usuário
                </h2>
                
                <form method="POST" id="user-form" class="space-y-4">
                    <input type="hidden" name="action" id="form-action" value="cadastrar">
                    <input type="hidden" name="id" id="user-id" value="">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome de Usuário</label>
                        <input type="text" name="username" id="form-username" required placeholder="Ex: joao.recepcao" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome Completo</label>
                        <input type="text" name="nome" id="form-nome" placeholder="Ex: GEORGE ALVES CORDEIRO JUNIOR" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <span class="text-[10px] text-gray-500 mt-1 block">Para peritos, deve ser idêntico ao nome da pauta importada.</span>
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase" id="password-label">Senha</label>
                        <input type="password" name="password" id="form-password" required placeholder="Digite a senha" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <span id="password-help" class="text-[10px] text-gray-500 hidden mt-1 block">Deixe em branco para manter a senha atual.</span>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Perfil / Permissão</label>
                        <select name="role" id="form-role" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            <option value="recepcao">Recepção</option>
                            <option value="supervisor">Supervisor da Recepção</option>
                            <option value="admin">Administrador</option>
                            <option value="perito">Perito Médico</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" id="submit-btn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                            Cadastrar Usuário
                        </button>
                        <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden bg-gray-850 hover:bg-gray-850/80 border border-gray-750 text-gray-400 text-xs font-semibold py-2.5 px-3 rounded-lg transition">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grade de Usuários Cadastrados -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider flex items-center gap-2">
                        <span>Usuários Cadastrados</span>
                        <span class="bg-gray-800 text-gray-400 text-xs px-2.5 py-0.5 rounded-full font-bold"><?= count($usuarios) ?></span>
                    </h2>
                    <div class="w-full sm:w-64">
                        <input type="text" id="busca-usuarios" onkeyup="filtrarUsuarios()" placeholder="🔍 Filtrar por nome ou login..." class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[460px] overflow-y-auto pr-1">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2">ID</th>
                                <th class="pb-2">Nome de Usuário</th>
                                <th class="pb-2">Nome Completo</th>
                                <th class="pb-2">Perfil</th>
                                <th class="pb-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td class="py-3 font-mono text-xs text-gray-500"><?= $u['id'] ?></td>
                                    <td class="py-3 font-bold text-white"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="py-3 text-gray-300"><?= htmlspecialchars($u['nome'] ?? '') ?></td>
                                    <td class="py-3">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="bg-blue-950 text-blue-400 text-[10px] px-2.5 py-0.5 rounded-full border border-blue-900/50 uppercase font-semibold">Administrador</span>
                                        <?php elseif ($u['role'] === 'supervisor'): ?>
                                            <span class="bg-indigo-950 text-indigo-400 text-[10px] px-2.5 py-0.5 rounded-full border border-indigo-900/50 uppercase font-semibold">Supervisor</span>
                                        <?php elseif ($u['role'] === 'perito'): ?>
                                            <span class="bg-purple-950 text-purple-400 text-[10px] px-2.5 py-0.5 rounded-full border border-purple-900/50 uppercase font-semibold">Perito Médico</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-semibold">Recepção</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['nome'] ?? '', ENT_QUOTES) ?>', '<?= $u['role'] ?>')" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold px-2.5 py-1 rounded transition border border-gray-750">
                                                Editar
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Deseja excluir este usuário?');">
                                                <input type="hidden" name="action" value="deletar">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1 rounded transition">
                                                    Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

    <!-- Scripts de Interação das Ações CRUD no lado do Cliente -->
    <script>
        // Transforma o formulário de Cadastro em formulário de Edição com preenchimento prévio
        function editUser(id, username, nome, role) {
            document.getElementById('form-title').textContent = "✏️ Editar Usuário";
            document.getElementById('form-action').value = "editar";
            document.getElementById('user-id').value = id;
            document.getElementById('form-username').value = username;
            document.getElementById('form-nome').value = nome;
            
            const passInput = document.getElementById('form-password');
            passInput.required = false;
            passInput.placeholder = "Senha (Opcional)";
            document.getElementById('password-help').classList.remove('hidden');
            
            document.getElementById('form-role').value = role;
            
            document.getElementById('submit-btn').textContent = "Atualizar Usuário";
            document.getElementById('cancel-btn').classList.remove('hidden');
            
            document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
        }

        // Reseta o formulário para o estado padrão de criação
        function resetForm() {
            document.getElementById('form-title').textContent = "🆕 Cadastrar Novo Usuário";
            document.getElementById('form-action').value = "cadastrar";
            document.getElementById('user-id').value = "";
            document.getElementById('form-username').value = "";
            document.getElementById('form-nome').value = "";
            
            const passInput = document.getElementById('form-password');
            passInput.value = "";
            passInput.required = true;
            passInput.placeholder = "Digite a senha";
            document.getElementById('password-help').classList.add('hidden');
            
            document.getElementById('form-role').value = "recepcao";
            
            document.getElementById('submit-btn').textContent = "Cadastrar Usuário";
            document.getElementById('cancel-btn').classList.add('hidden');
        }

        // Filtro em tempo real no cliente para evitar rolagem excessiva
        function filtrarUsuarios() {
            const input = document.getElementById('busca-usuarios');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const usernameCell = row.cells[1];
                const nomeCell = row.cells[2];
                if (usernameCell && nomeCell) {
                    const username = usernameCell.textContent.toLowerCase();
                    const nome = nomeCell.textContent.toLowerCase();
                    if (username.includes(filter) || nome.includes(filter)) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            });
        }
    </script>
</body>
</html>
