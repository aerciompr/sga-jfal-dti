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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, rgba(29, 78, 216, 0.12), transparent 45%), 
                        radial-gradient(circle at bottom left, rgba(30, 41, 59, 0.3), transparent 40%),
                        #030712;
        }
    </style>
</head>
<body class="text-gray-100 flex items-center justify-center min-h-screen px-4">
    <div class="bg-slate-900/60 backdrop-blur-xl p-8 rounded-3xl border border-slate-800/80 shadow-2xl w-full max-w-md transition-all duration-300 hover:border-blue-500/30">
        <!-- Logo Oficial da JFAL -->
        <div class="flex flex-col items-center mb-8">
            <div class="bg-white/95 p-3.5 rounded-2xl shadow-lg border border-slate-200 mb-4 transition-transform duration-300 hover:scale-105">
                <img src="assets/logo-jfal.png" alt="Justiça Federal em Alagoas" class="h-14 object-contain">
            </div>
            <h1 class="text-xl font-bold text-center bg-gradient-to-r from-blue-400 via-indigo-200 to-blue-400 bg-clip-text text-transparent mb-1">
                <?= htmlspecialchars(SYS_NAME) ?>
            </h1>
            <p class="text-[10px] text-blue-400 uppercase tracking-widest font-semibold">Sistema de Gestão de Atendimentos</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-200 p-3.5 rounded-xl mb-6 text-center text-xs backdrop-blur-sm animate-pulse">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[11px] uppercase font-semibold text-slate-400 mb-1.5 tracking-wider">Usuário</label>
                <input type="text" name="username" required autofocus 
                       class="w-full bg-slate-950/40 border border-slate-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 text-white placeholder-slate-600 transition-all duration-200"
                       placeholder="Digite seu usuário">
            </div>
            <div>
                <label class="block text-[11px] uppercase font-semibold text-slate-400 mb-1.5 tracking-wider">Senha</label>
                <input type="password" name="password" required 
                       class="w-full bg-slate-950/40 border border-slate-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 text-white placeholder-slate-600 transition-all duration-200"
                       placeholder="••••••••">
            </div>
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 rounded-xl text-sm transition-all duration-300 transform active:scale-[0.98] shadow-lg shadow-blue-900/20 hover:shadow-blue-500/20 mt-2">
                Acessar Painel
            </button>
        </form>
    </div>
    <!-- Rodapé Discreto -->
    <footer class="absolute bottom-4 left-0 right-0 text-center text-[10px] text-slate-500 tracking-wide">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>
</body>
</html>
