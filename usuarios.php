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

require_once __DIR__ . '/banco.php';

// Proteção de Acesso: Apenas administradores podem gerenciar perfis e senhas
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$message = '';
$message_type = '';

$current_user_id = $_SESSION['usuario_id'];

require_once __DIR__ . '/usuarios_service.php';
[$message,$message_type] = $_SESSION['flash_usuarios'] ?? ['', '']; unset($_SESSION['flash_usuarios']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $message=operarUsuario($pdo,$_POST); $message_type='success'; }
    catch (DomainException $e) { $message=$e->getMessage(); $message_type='error'; }
    catch (Throwable $e) { $message=erroPublico($e); $message_type='error'; }
    $_SESSION['flash_usuarios']=[$message,$message_type]; header('Location: usuarios.php',true,303); exit;
}

// Busca todos os usuários ordenados por ID para a listagem
$stmt = $pdo->query("SELECT id, username, role, nome, must_change_password FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Perfis e Senhas - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css?v=2.3">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.3">
    <script src="tema.js?v=2.3"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header Institucional Unificado JFAL -->
    <header class="sga-header bg-gray-900 border-b border-gray-800 px-4 py-2.5 flex items-center justify-between shadow-xs shrink-0">
        <!-- 1. Identidade Oficial JFAL -->
        <div class="sga-brand-area flex items-center space-x-2 shrink-0">
            <a href="dashboard.php" class="sga-brand-link flex items-center space-x-2 group">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-7 w-auto object-contain transition-transform duration-200 group-hover:scale-105">
                <div class="sga-brand-text leading-tight hidden sm:block">
                    <h1 class="text-xs font-black tracking-wide sga-brand-title uppercase leading-none">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] sga-brand-sub font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="sga-role-badge bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider border border-gray-700">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>

        <!-- 2. Barra de Navegação Central (Abas Limpas) -->
        <nav class="sga-nav-bar sga-nav-bar-clean flex items-center gap-0.5 shrink-1 min-w-0 overflow-x-auto">
            <a href="dashboard.php" class="sga-nav-link sga-nav-item">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="sga-nav-link sga-nav-item">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="sga-nav-link sga-nav-item">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="sga-nav-link sga-nav-item">Pautas</a>
                <a href="salas.php" class="sga-nav-link sga-nav-item">Salas</a>
                <a href="auditoria.php" class="sga-nav-link sga-nav-item">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="sga-nav-link sga-nav-item sga-nav-active">Perfis & Senhas</a>
            <?php endif; ?>
        </nav>

        <!-- 3. Grupo de Utilitários (Direita) -->
        <div class="sga-header-utils flex items-center space-x-1.5 shrink-0">
            <div class="h-4 w-px bg-gray-700/60 mx-1 hidden md:block sga-divider"></div>

            <div class="sga-header-user flex items-center gap-1.5 shrink-0 text-xs text-gray-400">
                <span>Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
                <span class="text-gray-600 sga-bullet">•</span>
                <a href="alterar_senha_obrigatorio.php" class="sga-user-link text-gray-400 hover:text-white underline text-xs">Minha senha</a>
            </div>

            <form method="POST" action="logout.php" class="sga-logout shrink-0 m-0">
                <?= csrfCampo() ?>
                <button type="submit" class="sga-btn-sair">Sair</button>
            </form>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Mensagem de Confirmação em Largura Total no Topo -->
        <?php if ($message): ?>
            <div role="status" aria-live="polite" class="col-span-full sga-feedback p-3.5 rounded-xl border text-xs text-center font-bold shadow-sm <?= $message_type === 'success' ? 'bg-emerald-950/40 border-emerald-800/80 text-emerald-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-800/80 text-blue-300' : 'bg-red-950/40 border-red-800/80 text-red-300') ?>">
                <?= escapar($message) ?>
            </div>
        <?php endif; ?>

        <!-- Formulário Lateral para Cadastro e Edição -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Card de Formulário -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl" id="form-card">
                <h2 id="form-title" class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4">
                    🆕 Cadastrar Novo Usuário
                </h2>
                
                <form method="POST" id="user-form" class="space-y-4"><?= csrfCampo() ?>
                    <input type="hidden" name="action" id="form-action" value="cadastrar">
                    <input type="hidden" name="id" id="user-id" value="">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome de Usuário</label>
                        <input type="text" name="username" maxlength="50" id="form-username" required placeholder="Ex: joao.recepcao" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome Completo</label>
                        <input type="text" name="nome" maxlength="100" id="form-nome" placeholder="Ex: GEORGE ALVES CORDEIRO JUNIOR" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <span class="text-[10px] text-gray-500 mt-1 block">A pauta usa vínculo interno do profissional. Confira o nome e CPF na importação.</span>
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

                    <div class="flex items-center gap-2 pt-1 bg-gray-800/40 p-2.5 rounded-lg border border-gray-750">
                        <input type="checkbox" name="must_change_password" id="form-must-change" value="1" checked class="h-4 w-4 rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                        <label for="form-must-change" class="text-xs text-gray-300 font-medium select-none cursor-pointer">Exigir troca de senha no próximo acesso</label>
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
        <div class="lg:col-span-8 space-y-6">
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider flex items-center gap-2">
                        <span>Usuários Cadastrados</span>
                        <span class="bg-gray-800 text-gray-400 text-xs px-2.5 py-0.5 rounded-full font-bold"><?= count($usuarios) ?></span>
                    </h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" class="inline" onsubmit="return confirm('ATENÇÃO: Deseja exigir a troca de senha de TODOS os peritos médicos no próximo acesso? Todas as sessões abertas de peritos serão revogadas.');"><?= csrfCampo() ?>
                            <input type="hidden" name="action" value="exigir_troca_todos_peritos">
                            <button type="submit" class="bg-amber-950/40 hover:bg-amber-900/50 border border-amber-800/70 text-amber-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                                <span>🔒 Exigir Troca de Todos os Peritos</span>
                            </button>
                        </form>
                        <div class="w-full sm:w-56">
                            <input type="text" id="busca-usuarios" aria-label="Buscar usuário" oninput="filtrarUsuarios()" placeholder="🔍 Filtrar por nome ou login..." class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 transition">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[480px] overflow-y-auto pr-1">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                <th class="pb-2 w-12">ID</th>
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
                                    <td class="py-3 font-bold text-white">
                                        <div><?= escapar($u['username']) ?></div>
                                        <?php if ($u['must_change_password']): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-amber-400 bg-amber-950/40 border border-amber-900/50 px-2 py-0.5 rounded font-medium mt-0.5">⚠️ Troca pendente</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 px-2 py-0.5 rounded font-medium mt-0.5">✓ Senha individual ativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-gray-300"><?= escapar($u['nome'] ?? '') ?></td>
                                    <td class="py-3">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="bg-blue-950 text-blue-400 text-[10px] px-2.5 py-0.5 rounded-full border border-blue-900/50 uppercase font-semibold">Administrador</span>
                                        <?php elseif ($u['role'] === 'supervisor'): ?>
                                            <span class="bg-slate-800 text-slate-300 text-[10px] px-2.5 py-0.5 rounded-full border border-slate-700 uppercase font-semibold">Supervisor</span>
                                        <?php elseif ($u['role'] === 'perito'): ?>
                                            <span class="bg-blue-950/60 text-blue-300 text-[10px] px-2.5 py-0.5 rounded-full border border-blue-800/60 uppercase font-semibold">Perito Médico</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-semibold">Recepção</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex justify-end gap-1.5 flex-wrap">
                                            <button onclick="editUser(<?= $u['id'] ?>, <?= jsValor($u['username']) ?>, <?= jsValor($u['nome'] ?? '') ?>, <?= jsValor($u['role']) ?>, <?= (int)$u['must_change_password'] ?>)" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold px-2.5 py-1 rounded transition border border-gray-750">
                                                Editar
                                            </button>
                                            <?php if (!$u['must_change_password']): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Deseja exigir a troca de senha do usuário <?= escapar($u['username']) ?> no próximo acesso?');"><?= csrfCampo() ?>
                                                    <input type="hidden" name="action" value="exigir_troca">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <button type="submit" title="Exigir troca obrigatória de senha no próximo login" class="bg-amber-950/40 hover:bg-amber-900/50 border border-amber-900/60 text-amber-300 text-xs px-2.5 py-1 rounded transition">
                                                        Exigir Troca
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Deseja excluir este usuário?');"><?= csrfCampo() ?>
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
        function editUser(id, username, nome, role, mustChange = 1) {
            document.getElementById('form-title').textContent = "✏️ Editar Usuário";
            document.getElementById('form-action').value = "editar";
            document.getElementById('user-id').value = id;
            document.getElementById('form-username').value = username;
            document.getElementById('form-nome').value = nome;
            
            const passInput = document.getElementById('form-password');
            passInput.required = false;
            passInput.value = "";
            passInput.setCustomValidity("");
            passInput.placeholder = "Senha (Opcional)";
            document.getElementById('password-help').classList.remove('hidden');
            
            document.getElementById('form-role').value = role;
            document.getElementById('form-must-change').checked = Boolean(mustChange);
            
            document.getElementById('submit-btn').textContent = "Atualizar Usuário";
            document.getElementById('cancel-btn').classList.remove('hidden');
            
            document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = "🆕 Cadastrar Novo Usuário";
            document.getElementById('form-action').value = "cadastrar";
            document.getElementById('user-id').value = "";
            document.getElementById('user-form').reset();
            
            const passInput = document.getElementById('form-password');
            passInput.required = true;
            passInput.placeholder = "Digite a senha";
            document.getElementById('password-help').classList.add('hidden');
            document.getElementById('form-must-change').checked = true;
            
            document.getElementById('submit-btn').textContent = "Cadastrar Usuário";
            document.getElementById('cancel-btn').classList.add('hidden');
        }

        function filtrarUsuarios() {
            const termo = document.getElementById('busca-usuarios').value.toLowerCase().trim();
            const linhas = document.querySelectorAll('tbody tr');
            
            linhas.forEach(linha => {
                const username = linha.children[1].textContent.toLowerCase();
                const nome = linha.children[2].textContent.toLowerCase();
                const cargo = linha.children[3].textContent.toLowerCase();
                
                if (username.includes(termo) || nome.includes(termo) || cargo.includes(termo)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        }
    </script>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.3" defer></script>
</body>
</html>
