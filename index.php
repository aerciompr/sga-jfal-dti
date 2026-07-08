<?php
session_start();
require_once __DIR__ . '/banco.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_role'] === 'perito') {
        header("Location: atendente.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_name'] = $user['username'];
            $_SESSION['usuario_role'] = $user['role'];
            $_SESSION['usuario_nome'] = $user['nome'] ?: $user['username'];
            
            registrarLog($pdo, 'Login', "Usuário logou com perfil de " . ucfirst($user['role']));
            
            if ($user['role'] === 'perito') {
                header("Location: atendente.php");
            } else {
                header("Location: dashboard.php");
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
    <title>Login - <?= htmlspecialchars(SYS_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-gray-900 p-8 rounded-2xl border border-gray-800 shadow-2xl w-full max-w-sm">
        <h1 class="text-2xl font-bold text-center text-blue-500 mb-2"><?= htmlspecialchars(SYS_NAME) ?></h1>
        <p class="text-xs text-gray-400 text-center uppercase tracking-wider mb-6">Painel de Senhas</p>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-3 rounded-lg mb-4 text-center text-xs">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Usuário</label>
                <input type="text" name="username" required autofocus class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 text-white">
            </div>
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Senha</label>
                <input type="password" name="password" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 text-white">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm transition duration-200">
                Entrar
            </button>
        </form>
    </div>
</body>
</html>
