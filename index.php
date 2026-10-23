<?php

require_once __DIR__ . '/banco.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_role'] === 'perito') {
        header("Location: atendente.php");
    } else {
        header("Location: " . ($_SESSION['usuario_role'] === 'recepcao' ? 'recepcao.php' : 'dashboard.php'));
    }
    exit;
}

$error = '';
$aviso = $_SESSION['login_aviso'] ?? ''; unset($_SESSION['login_aviso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $ipKey = hash('sha256', 'ip|' . ipCliente());
        $ipLimit = $pdo->prepare("INSERT INTO login_tentativas (chave, tentativas, janela) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE tentativas = IF(janela < NOW() - INTERVAL 15 MINUTE, 1, tentativas + 1), janela = IF(janela < NOW() - INTERVAL 15 MINUTE, NOW(), janela)");
        $ipLimit->execute([$ipKey]);
        $ipCheck=$pdo->prepare('SELECT tentativas FROM login_tentativas WHERE chave=?');$ipCheck->execute([$ipKey]);
        if ((int)$ipCheck->fetchColumn()>100) abortar(429,'Muitas tentativas nesta origem. Aguarde 15 minutos.');
        $chave = hash('sha256', ipCliente() . '|' . mb_strtolower($username));
        $tentativa = $pdo->prepare("INSERT INTO login_tentativas (chave, tentativas, janela) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE tentativas = IF(janela < NOW() - INTERVAL 15 MINUTE, 1, tentativas + 1), janela = IF(janela < NOW() - INTERVAL 15 MINUTE, NOW(), janela)");
        $tentativa->execute([$chave]);
        $limite = $pdo->prepare('SELECT tentativas FROM login_tentativas WHERE chave = ?'); $limite->execute([$chave]);
        if ((int)$limite->fetchColumn() > 10) abortar(429, 'Muitas tentativas. Aguarde 15 minutos.');
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $senhaValida = password_verify($password, $user['password'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
        if ($user && $senhaValida) {
            session_regenerate_id(true);
            $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
            $_SESSION['inicio_sessao'] = $_SESSION['ultima_atividade'] = time();
            $_SESSION['credencial'] = hash('sha256', $user['password'] . '|' . $user['session_version']);
            $limpar = $pdo->prepare('DELETE FROM login_tentativas WHERE chave = ?'); $limpar->execute([$chave]);
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_name'] = $user['username'];
            $_SESSION['usuario_role'] = $user['role'];
            $_SESSION['usuario_nome'] = $user['nome'] ?: $user['username'];

            registrarLog($pdo, 'Login', "Usuário logou com perfil de " . ucfirst($user['role']));
            if ($user['must_change_password']) { header('Location: alterar_senha_obrigatorio.php'); exit; }

            if ($user['role'] === 'perito') {
                header("Location: atendente.php");
            } else {
                header("Location: " . ($_SESSION['usuario_role'] === 'recepcao' ? 'recepcao.php' : 'dashboard.php'));
            }
            exit;
        } else {
            $error = "Usuário ou senha inválidos.";
        }
    } else {
        $error = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= escapar(SYS_NAME) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
    <script src="tema.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, rgba(29, 78, 216, 0.12), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(30, 41, 59, 0.3), transparent 40%),
                        #030712;
        }
    </style>
</head>
<body class="text-gray-100 flex flex-col items-center justify-center min-h-screen px-4 py-8 gap-6">
    <div class="bg-slate-900/60 backdrop-blur-xl p-8 rounded-3xl border border-slate-800/80 shadow-2xl w-full max-w-md transition-all duration-300 hover:border-blue-500/30">
        <!-- Logo Oficial da JFAL -->
        <div class="flex flex-col items-center mb-8">
            <div class="bg-white p-4 rounded-2xl shadow-xl mb-4 transition-transform duration-300 hover:scale-[1.02] w-full flex items-center justify-center">
                <img src="assets/logo-jfal-completo.png" alt="Justiça Federal em Alagoas" class="h-14 max-w-full object-contain">
            </div>
            <h1 class="text-xl font-bold text-center bg-gradient-to-r from-blue-400 via-indigo-200 to-blue-400 bg-clip-text text-transparent mb-1">
                <?= escapar(SYS_NAME) ?>
            </h1>
            <p class="text-[10px] text-blue-400 uppercase tracking-widest font-semibold">Sistema de Gestão de Atendimentos</p>
        </div>

        <?php if ($aviso): ?><p role="status" class="sga-helper mb-4"><?= escapar($aviso) ?></p><?php endif; ?>
        <?php if ($error): ?>
            <div role="alert" class="bg-red-500/10 border border-red-500/30 text-red-200 p-3.5 rounded-xl mb-6 text-center text-xs backdrop-blur-sm animate-pulse">
                <?= escapar($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5"><?= csrfCampo() ?>
            <div>
                <label for="login-usuario" class="block text-[11px] uppercase font-semibold text-slate-400 mb-1.5 tracking-wider">Usuário</label>
                <input type="text" name="username" id="login-usuario" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                       class="w-full bg-slate-950/40 border border-slate-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 text-white placeholder-slate-600 transition-all duration-200"
                       placeholder="Digite seu usuário">
            </div>
            <div>
                <label for="login-senha" class="block text-[11px] uppercase font-semibold text-slate-400 mb-1.5 tracking-wider">Senha</label>
                <input type="password" name="password" id="login-senha" required autocomplete="current-password"
                       class="w-full bg-slate-950/40 border border-slate-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 text-white placeholder-slate-600 transition-all duration-200"
                       placeholder="••••••••">
            </div>
            <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 rounded-xl text-sm transition-all duration-300 transform active:scale-[0.98] shadow-lg shadow-blue-900/20 hover:shadow-blue-500/20 mt-2">
                Acessar Painel
            </button>
        </form>
        <p class="sga-helper mt-4">Esqueceu a senha? Solicite a redefinição ao administrador do SGA.</p>
    </div>
    <!-- Rodapé Discreto -->
    <footer class="text-center text-[10px] text-slate-500 tracking-wide">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body>
</html>
