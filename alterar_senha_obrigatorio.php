<?php
/**
 * TELA OPERACIONAL DE ALTERAÇÃO OBRIGATÓRIA DE SENHA - JFAL
 * 
 * alterar_senha_obrigatorio.php: Impede o prosseguimento do fluxo até que o
 * usuário crie uma senha segura diferente do CPF/credencial inicial padrão.
 */

session_start();
require_once __DIR__ . '/banco.php';

// Segurança: Se o usuário sequer está logado, redireciona para a página de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$username = $_SESSION['usuario_name'];
$user_id = $_SESSION['usuario_id'];

$error_msg = '';
$success_msg = '';

// Verifica se o formulário foi postado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    
    if (empty($nova_senha) || empty($confirma_senha)) {
        $error_msg = "Por favor, preencha todos os campos.";
    } elseif (strlen($nova_senha) < 6) {
        $error_msg = "A nova senha deve possuir no mínimo 6 caracteres.";
    } elseif ($nova_senha !== $confirma_senha) {
        $error_msg = "A confirmação de senha não coincide com a nova senha.";
    } else {
        // Busca o CPF do usuário logado para impedir que a nova senha seja igual ao próprio CPF ou os primeiros 6 dígitos
        $stmt = $pdo->prepare("SELECT cpf, role FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $userData = $stmt->fetch();
        
        $senha_insegura = false;
        if ($userData && !empty($userData['cpf'])) {
            $cpfLimpo = preg_replace('/\D/', '', $userData['cpf']);
            $digitosIniciais = substr($cpfLimpo, 0, 6);
            if ($nova_senha === $cpfLimpo || $nova_senha === $digitosIniciais) {
                $senha_insegura = true;
            }
        }
        
        if ($senha_insegura) {
            $error_msg = "Erro: A nova senha não pode ser idêntica ao seu CPF ou aos dígitos iniciais dele.";
        } else {
            // Atualiza a senha no banco de dados com hash Bcrypt seguro
            try {
                $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$hash, $user_id]);
                
                $success_msg = "Senha alterada com sucesso! Redirecionando...";
                
                // Redirecionamento com base na role
                $role = $userData['role'] ?? 'perito';
                $redirect = ($role === 'perito') ? 'atendente.php' : 'dashboard.php';
                
                // Exibe mensagem de sucesso e redireciona após 2 segundos
                echo "<meta http-equiv='refresh' content='2;url=$redirect'>";
            } catch (Exception $e) {
                $error_msg = "Erro técnico ao gravar nova senha: " . $e->getMessage();
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
    <title>Troca de Senha Obrigatória - <?= htmlspecialchars($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-gray-900 rounded-2xl border border-gray-800 p-8 shadow-2xl relative overflow-hidden">
        
        <!-- Header da Caixa de Login -->
        <div class="text-center mb-6 flex flex-col items-center">
            <div class="bg-white/95 p-2 rounded-xl shadow-md border border-slate-200 mb-4 transition-transform duration-300 hover:scale-105 inline-block">
                <img src="assets/logo-jfal-completo.png" alt="Justiça Federal" class="h-10 object-contain">
            </div>
            <h1 class="text-lg font-black text-blue-500 uppercase leading-none">Segurança da Informação</h1>
            <p class="text-[9px] text-gray-400 uppercase tracking-widest font-semibold mt-1">Alteração de Senha Obrigatória</p>
        </div>

        <div class="bg-blue-950/30 border border-blue-900/60 rounded-xl p-4 mb-6">
            <p class="text-xs text-blue-300 leading-relaxed">
                Olá, <strong class="text-white"><?= htmlspecialchars($username) ?></strong>. Identificamos que este é o seu primeiro acesso ou que você está utilizando a credencial padrão do sistema. 
                Para garantir a integridade dos atendimentos, você precisa definir uma senha pessoal segura.
            </p>
        </div>

        <!-- Alertas de Sucesso / Erro -->
        <?php if (!empty($error_msg)): ?>
            <div class="p-3 bg-red-950/45 border border-red-900/60 text-red-400 rounded-lg text-xs mb-4 font-semibold">
                ⚠️ <?= $error_msg ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_msg)): ?>
            <div class="p-3 bg-emerald-950/45 border border-emerald-900/60 text-emerald-400 rounded-lg text-xs mb-4 font-semibold">
                ✔️ <?= $success_msg ?>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Nova Senha</label>
                <input type="password" name="nova_senha" required placeholder="Mínimo 6 caracteres" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Confirme a Nova Senha</label>
                <input type="password" name="confirma_senha" required placeholder="Repita a nova senha" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white font-bold text-sm py-2.5 rounded-lg shadow transition">
                Gravar Senha e Acessar
            </button>
        </form>
    </div>
</body>
</html>
