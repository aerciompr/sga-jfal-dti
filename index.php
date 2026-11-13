<?php

require_once __DIR__ . '/banco.php';

if (isset($_SESSION['usuario_id'])) {
    if (!empty($_SESSION['troca_obrigatoria'])) {
        header("Location: alterar_senha_obrigatorio.php");
        exit;
    }
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

            $_SESSION['data_sessao'] = date('Y-m-d');
            $multiAtiva = multisubsecaoAtiva($pdo);
            $subsecaoEscolhida = isset($_POST['subsecao_id']) && is_numeric($_POST['subsecao_id']) ? (int)$_POST['subsecao_id'] : 0;

            if ($user['role'] === 'admin') {
                if (empty($user['subsecao_id'])) {
                    $_SESSION['is_superadmin'] = true;
                    $_SESSION['usuario_subsecao_id'] = null;
                    if ($multiAtiva && $subsecaoEscolhida > 0 && obterSubsecao($pdo, $subsecaoEscolhida)) {
                        $_SESSION['subsecao_id_ativa'] = $subsecaoEscolhida;
                    } else {
                        $_SESSION['subsecao_id_ativa'] = 1;
                    }
                } else {
                    $_SESSION['is_superadmin'] = false;
                    $_SESSION['usuario_subsecao_id'] = (int)$user['subsecao_id'];
                    $_SESSION['subsecao_id_ativa'] = (int)$user['subsecao_id'];
                }
            } elseif ($user['role'] === 'recepcao' || $user['role'] === 'supervisor') {
                $_SESSION['is_superadmin'] = false;
                $_SESSION['usuario_subsecao_id'] = (int)($user['subsecao_id'] ?? 1);
                $_SESSION['subsecao_id_ativa'] = (int)($user['subsecao_id'] ?? 1);
            } elseif ($user['role'] === 'perito') {
                $_SESSION['is_superadmin'] = false;
                $_SESSION['usuario_subsecao_id'] = null;
                if ($multiAtiva) {
                    if ($subsecaoEscolhida > 0 && obterSubsecao($pdo, $subsecaoEscolhida)) {
                        $_SESSION['subsecao_id_ativa'] = $subsecaoEscolhida;
                    } else {
                        $subsecaoDetectada = null;
                        try {
                            $stmtSub = $pdo->prepare("SELECT DISTINCT subsecao_id FROM atendimentos WHERE (perito_id = ? OR perito = ?) AND data_pauta = CURRENT_DATE() AND subsecao_id IS NOT NULL");
                            $stmtSub->execute([(int)$user['id'], $user['nome'] ?: $user['username']]);
                            $subsHoje = $stmtSub->fetchAll(PDO::FETCH_COLUMN);
                            if (count($subsHoje) === 1) {
                                $subsecaoDetectada = (int)$subsHoje[0];
                            }
                        } catch (Throwable $e) {}
                        $_SESSION['subsecao_id_ativa'] = $subsecaoDetectada ?: 1;
                    }
                } else {
                    $_SESSION['subsecao_id_ativa'] = 1;
                }
            } else {
                $_SESSION['is_superadmin'] = false;
                $_SESSION['usuario_subsecao_id'] = 1;
                $_SESSION['subsecao_id_ativa'] = 1;
            }

            // Detecção ativa de credencial inicial ou pendência de troca obrigatória
            $cpfLimpo = preg_replace('/\D/', '', $user['cpf'] ?? '');
            $senhasPadraoIniciais = [
                '123456', '12345678', '123456789', 'admin', 'admin123',
                'jfal123', 'jfal@123', 'mudar123', 'mudar@123', 'perito123',
                'perito@123', 'senha123', 'senha@123', 'trocar123', 'trocar@123',
                'primeiroacesso', 'trocarsenha'
            ];
            $ehSenhaInicial = (
                mb_strtolower($password) === mb_strtolower($user['username']) ||
                ($cpfLimpo !== '' && ($password === $cpfLimpo || (strlen($cpfLimpo) >= 6 && $password === substr($cpfLimpo, 0, 6)))) ||
                in_array(mb_strtolower($password), $senhasPadraoIniciais, true)
            );

            $deveTrocarSenha = (bool)$user['must_change_password'] || $ehSenhaInicial;

            if ($deveTrocarSenha) {
                if (!(int)$user['must_change_password']) {
                    $pdo->prepare('UPDATE usuarios SET must_change_password = 1 WHERE id = ?')->execute([$user['id']]);
                }
                $_SESSION['troca_obrigatoria'] = true;
                registrarLog($pdo, 'Login', "Usuário logou com perfil de " . ucfirst($user['role']) . " (troca de senha obrigatória acionada)");
                header('Location: alterar_senha_obrigatorio.php');
                exit;
            }

            registrarLog($pdo, 'Login', "Usuário logou com perfil de " . ucfirst($user['role']));

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
    <link rel="stylesheet" href="assets/tailwind.css?v=2.5">
    <link rel="stylesheet" href="assets/usabilidade.css?v=2.5">
    <script src="tema.js?v=2.5"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at top right, rgba(0, 47, 108, 0.12), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(15, 23, 42, 0.3), transparent 40%),
                        #030712;
        }
        body.theme-jf {
            background: #f8fafc !important;
        }
    </style>
</head>
<body class="text-gray-100 flex flex-col items-center justify-center min-h-screen px-4 py-8 gap-6 relative <?= ($_COOKIE['tema_sga'] ?? '') === 'jf' ? 'theme-jf' : '' ?>">
    <!-- Alternador de Tema na Tela de Login -->
    <div class="fixed top-4 right-4 z-10">
        <button type="button" id="btn-toggle-theme" class="sga-btn-util text-xs px-3 py-1.5 rounded-lg border shadow-xs cursor-pointer" title="Alternar tema visual">
            🌙 <span class="sga-theme-label">Escuro</span>
        </button>
    </div>
    <div class="login-card bg-gray-900 p-8 sm:p-10 rounded-3xl border border-gray-800 shadow-2xl w-full max-w-md transition-all duration-300">
        <!-- Logo Oficial da JFAL -->
        <div class="flex flex-col items-center mb-6">
            <div class="login-logo-container w-full flex items-center justify-center mb-4 p-2.5 rounded-2xl">
                <img src="assets/logo-jfal-completo.png" alt="Justiça Federal em Alagoas" class="h-12 max-w-full object-contain">
            </div>
            <h1 class="login-title text-base sm:text-lg font-black text-center text-blue-500 uppercase tracking-wide leading-tight">
                <?= escapar(SYS_NAME) ?>
            </h1>
            <p class="login-subtitle text-[10px] text-gray-400 uppercase tracking-widest font-semibold mt-1">Sistema de Gestão de Atendimentos</p>
        </div>

        <?php if ($aviso): ?>
            <div role="status" class="login-aviso p-3.5 rounded-xl mb-5 text-center text-xs font-semibold bg-blue-950/40 border border-blue-900/60 text-blue-300">
                ℹ️ <?= escapar($aviso) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div role="alert" class="login-error p-3.5 rounded-xl mb-5 text-center text-xs font-semibold bg-red-950/40 border border-red-900/60 text-red-300">
                ⚠️ <?= escapar($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4"><?= csrfCampo() ?>
            <div>
                <label for="login-usuario" class="block text-xs uppercase font-semibold text-gray-400 mb-1.5 tracking-wider">Usuário</label>
                <input type="text" name="username" id="login-usuario" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                       class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 text-white placeholder-gray-500 transition-all duration-200"
                       placeholder="Digite seu usuário">
            </div>
            <div>
                <label for="login-senha" class="block text-xs uppercase font-semibold text-gray-400 mb-1.5 tracking-wider">Senha</label>
                <input type="password" name="password" id="login-senha" required autocomplete="current-password"
                       class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 text-white placeholder-gray-500 transition-all duration-200"
                       placeholder="••••••••">
            </div>
            <?php if (multisubsecaoAtiva($pdo)): ?>
                <div>
                    <label for="login-subsecao" class="block text-xs uppercase font-semibold text-gray-400 mb-1.5 tracking-wider">Subseção Judiciária</label>
                    <select name="subsecao_id" id="login-subsecao"
                            class="w-full bg-gray-950/60 border border-gray-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 text-white transition-all duration-200">
                        <option value="0">Detectar automaticamente pela pauta de hoje</option>
                        <?php foreach (obterSubsecoes($pdo) as $sub): ?>
                            <option value="<?= (int)$sub['id'] ?>">🏢 <?= escapar($sub['nome']) ?> (<?= escapar($sub['cidade']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-[10px] text-gray-400 mt-1 block">Médicos peritos são direcionados automaticamente para a cidade da sua perícia de hoje.</span>
                </div>
            <?php endif; ?>
            <button type="submit"
                    class="login-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-sm transition-all duration-200 shadow-md shadow-blue-900/20 mt-2">
                Acessar Painel
            </button>
        </form>
        <p class="login-helper text-center text-xs text-gray-500 mt-5">Esqueceu a senha? Solicite a redefinição ao administrador do SGA.</p>
    </div>
    <!-- Rodapé Discreto -->
    <footer class="text-center text-[10px] text-gray-500 tracking-wide">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </footer>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js?v=2.5" defer></script>
</body>
</html>
