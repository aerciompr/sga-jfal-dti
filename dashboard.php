<?php
session_start();
require_once __DIR__ . '/banco.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
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

if ($_SESSION['usuario_role'] === 'perito') {
    header("Location: atendente.php");
    exit;
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel do Administrador - <?= htmlspecialchars($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="tema.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md">
        <div class="flex items-center space-x-3">
            <img src="assets/catavento-jfal.png" alt="JFAL" class="h-7 w-auto object-contain">
            <div>
                <h1 class="text-base font-black text-blue-500 uppercase leading-none">JFAL - Alagoas</h1>
                <p class="text-[9px] text-gray-400 uppercase tracking-widest font-semibold mt-0.5">Sistemas de Atendimento</p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-300">Olá, <strong class="text-white"><?= htmlspecialchars($_SESSION['usuario_name']) ?></strong></span>
            <a href="logout.php" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</a>
        </div>
    </header>

    <!-- Main Central Hub -->
    <main class="flex-1 max-w-5xl w-full mx-auto p-8 flex flex-col justify-center">
        <div class="text-center mb-10 flex flex-col items-center">
            <div class="bg-white/95 p-3 rounded-2xl shadow-md border border-slate-200 mb-6 transition-transform duration-300 hover:scale-105 inline-block">
                <img src="assets/logo-jfal-completo.png" alt="Justiça Federal em Alagoas" class="h-12 object-contain">
            </div>
            <h2 class="text-3xl font-black text-white leading-tight">Painel Principal</h2>
            <p class="text-sm text-gray-400 mt-2">Selecione o subsistema que deseja acessar abaixo</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
            <!-- Recepção Card -->
            <a href="recepcao.php" class="bg-gray-900 border border-gray-800 hover:border-emerald-500/50 p-6 rounded-3xl shadow-xl hover:shadow-emerald-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-emerald-950/50 border border-emerald-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📋
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-emerald-400 transition-colors">Recepção</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Confirmar chegada de periciados e gerenciar agendamentos do dia.</p>
                <div class="text-xs text-emerald-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Acessar Fila <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
            <!-- Atendente (Perito) Card -->
            <a href="atendente.php" class="bg-gray-900 border border-gray-800 hover:border-blue-500/50 p-6 rounded-3xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🧑‍⚕️
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel do Perito</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Chamar periciados por ordem de chegada para a sua sala de atendimento.</p>
                <div class="text-xs text-blue-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Chamar Paciente <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- TV Panel Card -->
            <a href="painel.php" target="_blank" class="bg-gray-900 border border-gray-800 hover:border-amber-500/50 p-6 rounded-3xl shadow-xl hover:shadow-amber-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-amber-950/50 border border-amber-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📺
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-amber-400 transition-colors">Painel da TV</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Tela de TV da recepção para exibição das chamadas de senhas e vídeo.</p>
                <div class="text-xs text-amber-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Abrir TV <span>→</span>
                </div>
            </a>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Auditoria Card -->
            <a href="auditoria.php" class="bg-gray-900 border border-gray-800 hover:border-teal-500/50 p-6 rounded-3xl shadow-xl hover:shadow-teal-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-teal-950/50 border border-teal-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🔎
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-teal-400 transition-colors">Auditoria de Chamadas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Histórico completo de atendimentos, logs de chamadas, filtros por perito e exportação CSV.</p>
                <div class="text-xs text-teal-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Ver Auditoria <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
            <!-- Perfis e Senhas Card -->
            <a href="usuarios.php" class="bg-gray-900 border border-gray-800 hover:border-indigo-500/50 p-6 rounded-3xl shadow-xl hover:shadow-indigo-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-indigo-950/50 border border-indigo-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🔑
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-indigo-400 transition-colors">Perfis e Senhas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Gerenciar contas de acesso, senhas e perfis de recepção e administrador.</p>
                <div class="text-xs text-indigo-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Contas <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Salas de Atendimento Card -->
            <a href="salas.php" class="bg-gray-900 border border-gray-800 hover:border-pink-500/50 p-6 rounded-3xl shadow-xl hover:shadow-pink-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-pink-950/50 border border-pink-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    🚪
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-pink-400 transition-colors">Salas de Atendimento</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Cadastrar e gerenciar salas físicas que estarão disponíveis para escolha dos peritos.</p>
                <div class="text-xs text-pink-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Salas <span>→</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
            <!-- Gerenciar Pautas Card -->
            <a href="gerenciar_pauta.php" class="bg-gray-900 border border-gray-800 hover:border-blue-500/50 p-6 rounded-3xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    📅
                </div>
                <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Gerenciar Pautas</h3>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Filtrar, excluir e transferir agendamentos de forma massiva por dia ou mês inteiro.</p>
                <div class="text-xs text-blue-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                    Gerenciar Pauta <span>→</span>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>
</body>
</html>
