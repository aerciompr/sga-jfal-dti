<?php
/**
 * TELA OPERACIONAL DE ALTERAÇÃO OBRIGATÓRIA DE SENHA - JFAL
 *
 * alterar_senha_obrigatorio.php: Impede o prosseguimento do fluxo até que o
 * usuário crie uma senha segura diferente do CPF/credencial inicial padrão.
 */

require_once __DIR__ . '/banco.php';

// Segurança: Se o usuário sequer está logado, redireciona para a página de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$username = $_SESSION['usuario_name'];
$user_id = (int)$_SESSION['usuario_id'];

// Obtém os dados mais recentes do usuário
$stmtUser = $pdo->prepare("SELECT cpf, role, password, username, must_change_password FROM usuarios WHERE id = ?");
$stmtUser->execute([$user_id]);
$userData = $stmtUser->fetch();

if (!$userData) {
    header("Location: logout.php");
    exit;
}

$trocaObrigatoria = !empty($_SESSION['troca_obrigatoria']) || !empty($userData['must_change_password']) || $userData['role'] === 'perito';

$error_msg = '';
$success_msg = '';

// Verifica se o formulário foi postado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    if (empty($nova_senha) || empty($confirma_senha)) {
        $error_msg = "Por favor, preencha todos os campos.";
    } elseif (strlen($nova_senha) < 12 || strlen($nova_senha) > 72) {
        $error_msg = "A nova senha deve possuir de 12 a 72 bytes.";
    } elseif ($nova_senha !== $confirma_senha) {
        $error_msg = "A confirmação de senha não coincide com a nova senha.";
    } else {
        $senha_insegura = false;

        // Se não for troca obrigatória por primeiro acesso, exige validar a senha atual digitada
        if (!$trocaObrigatoria && !password_verify($_POST['senha_atual'] ?? '', $userData['password'])) {
            $senha_insegura = true;
            $error_msg = "A senha atual informada está incorreta.";
        }

        // Impede reutilização da senha anterior
        if (password_verify($nova_senha, $userData['password'])) {
            $senha_insegura = true;
            $error_msg = "A nova senha não pode ser igual à senha anterior.";
        }

        // Impede uso da senha genérica 'admin'
        if (mb_strtolower($nova_senha) === 'admin') {
            $senha_insegura = true;
            $error_msg = "A nova senha não pode ser uma senha genérica ('admin').";
        }

        // Impede que a nova senha seja igual ao nome de usuário
        if (mb_strtolower($nova_senha) === mb_strtolower($userData['username'])) {
            $senha_insegura = true;
            $error_msg = "A nova senha não pode ser igual ao seu nome de usuário/login.";
        }

        // Impede que a nova senha seja igual ao próprio CPF ou os primeiros 6 dígitos
        if (!empty($userData['cpf'])) {
            $cpfLimpo = preg_replace('/\D/', '', $userData['cpf']);
            $digitosIniciais = substr($cpfLimpo, 0, 6);
            if ($nova_senha === $cpfLimpo || $nova_senha === $digitosIniciais) {
                $senha_insegura = true;
                $error_msg = "A nova senha não pode conter o seu CPF ou os primeiros dígitos dele.";
            }
        }

        // Impede senhas triviais conhecidas
        $senhasProibidas = [
            '123456', '12345678', '123456789', 'admin', 'admin123',
            'jfal123', 'jfal@123', 'mudar123', 'mudar@123', 'perito123',
            'perito@123', 'senha123', 'senha@123', 'trocar123', 'trocar@123',
            'primeiroacesso', 'trocarsenha'
        ];
        if (in_array(mb_strtolower($nova_senha), $senhasProibidas, true)) {
            $senha_insegura = true;
            $error_msg = "A nova senha não pode ser uma senha comum ou previsível.";
        }

        if (!$senha_insegura) {
            // Atualiza a senha no banco de dados com hash Bcrypt seguro
            try {
                $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?");
                $stmtUpdate->execute([$hash, $user_id]);
                registrarLog($pdo, 'Alterar Senha', 'Credencial atualizada com sucesso e demais sessões revogadas.');
                $pdo->commit();
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['login_aviso'] = 'Senha atualizada com sucesso! Entre agora com sua nova senha individual.';
                header('Location: index.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = erroPublico($e);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troca de Senha Obrigatória - <?= escapar($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js?v=2.5"></script>
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex items-center justify-center p-4 relative <?= ($_COOKIE['tema_sga'] ?? '') === 'jf' ? 'theme-jf' : '' ?>">
    <!-- Alternador de Tema -->
    <div class="fixed top-4 right-4 z-10">
        <button type="button" id="btn-toggle-theme" class="sga-btn-util text-xs px-3 py-1.5 rounded-lg border shadow-xs cursor-pointer" title="Alternar tema visual">
            🌙 <span class="sga-theme-label">Escuro</span>
        </button>
    </div>
    <div class="login-card max-w-md w-full bg-gray-900 rounded-3xl border border-gray-800 p-8 sm:p-10 shadow-2xl relative overflow-hidden">

        <!-- Header da Caixa de Login -->
        <div class="text-center mb-6 flex flex-col items-center">
            <div class="login-logo-container w-full flex items-center justify-center mb-4 p-2.5 rounded-2xl">
                <img src="assets/logo-jfal-completo.png" alt="Justiça Federal" class="h-10 object-contain">
            </div>
            <h1 class="login-title text-base sm:text-lg font-black text-blue-500 uppercase leading-none">Segurança da Informação</h1>
            <p class="login-subtitle text-[9px] text-gray-400 uppercase tracking-widest font-semibold mt-1">Alteração de Senha Obrigatória</p>
        </div>

        <div class="login-aviso bg-blue-950/40 border border-blue-900/60 rounded-xl p-4 mb-5">
            <p class="text-xs text-blue-300 leading-relaxed">
                Olá, <strong class="text-white"><?= escapar($username) ?></strong>. Identificamos que este é o seu primeiro acesso ou que você está utilizando a credencial padrão do sistema.
                Para garantir a integridade dos atendimentos, você precisa definir uma senha pessoal segura.
            </p>
        </div>

        <!-- Alertas de Sucesso / Erro -->
        <?php if (!empty($error_msg)): ?>
            <div class="login-error p-3.5 bg-red-950/40 border border-red-900/60 text-red-300 rounded-xl text-xs mb-4 font-semibold text-center">
                ⚠️ <?= escapar($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="p-3.5 bg-emerald-950/40 border border-emerald-900/60 text-emerald-300 rounded-xl text-xs mb-4 font-semibold text-center">
                ✔️ <?= $success_msg ?>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form method="POST" class="space-y-4"><?= csrfCampo() ?>
            <?php if (!$trocaObrigatoria): ?>
            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Senha Atual</label>
                <input type="password" name="senha_atual" required autocomplete="current-password" placeholder="Digite sua senha atual" class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Nova Senha</label>
                <input type="password" name="nova_senha" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="Mínimo 12 caracteres" class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Confirme a Nova Senha</label>
                <input type="password" name="confirma_senha" required placeholder="Repita a nova senha" class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
            </div>

            <button type="submit" class="login-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3 rounded-xl shadow-md transition">
                Salvar nova senha
            </button>
        </form>
    </div>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
</body>
</html>
