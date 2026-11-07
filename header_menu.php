<?php
// header_menu.php - Componente Único, Canônico e Padronizado do Cabeçalho Institucional JFAL
$paginaAtual = basename($_SERVER['PHP_SELF'] ?? '');
$userRole = $_SESSION['usuario_role'] ?? 'visitante';
$userName = $_SESSION['usuario_name'] ?? 'Usuário';

// Mapeamento oficial dos módulos por permissão de acesso
$modulos = [
    'dashboard.php'       => ['label' => 'Painel Principal', 'roles' => ['admin', 'supervisor']],
    'recepcao.php'        => ['label' => 'Recepção',         'roles' => ['admin', 'supervisor', 'recepcao']],
    'atendente.php'       => ['label' => 'Atendimento',      'roles' => ['admin', 'perito']],
    'gerenciar_pauta.php' => ['label' => 'Pautas',           'roles' => ['admin', 'supervisor']],
    'salas.php'           => ['label' => 'Salas',            'roles' => ['admin', 'supervisor']],
    'auditoria.php'       => ['label' => 'Auditoria',        'roles' => ['admin', 'supervisor']],
    'usuarios.php'        => ['label' => 'Perfis & Senhas',  'roles' => ['admin']],
];

// Destino ao clicar na logo institucional
$homeDestino = 'dashboard.php';
if ($userRole === 'recepcao') {
    $homeDestino = 'recepcao.php';
} elseif ($userRole === 'perito') {
    $homeDestino = 'atendente.php';
}
?>
<!-- Header Institucional Unificado JFAL (Padrão Único em Todas as Páginas) -->
<header class="sga-header bg-gray-900 border-b border-gray-800 px-4 py-2.5 flex items-center justify-between shadow-xs shrink-0">
    <!-- 1. Identidade Oficial JFAL (Sempre presente e idêntica em todas as páginas) -->
    <div class="sga-brand-area flex items-center space-x-2 shrink-0">
        <a href="<?= $homeDestino ?>" class="sga-brand-link flex items-center space-x-2 group">
            <img src="assets/catavento-jfal.png" alt="JFAL" class="h-7 w-auto object-contain transition-transform duration-200 group-hover:scale-105">
            <div class="sga-brand-text leading-tight hidden sm:block">
                <h1 class="text-xs font-black tracking-wide sga-brand-title uppercase leading-none">Justiça Federal em Alagoas</h1>
                <p class="text-[8px] sga-brand-sub font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
            </div>
        </a>
        <span class="sga-role-badge bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider border border-gray-700">
            <?= escapar(ucfirst($userRole)) ?>
        </span>
    </div>

    <!-- 2. Barra de Navegação Central (Abas Limpas Padronizadas) -->
    <nav class="sga-nav-bar sga-nav-bar-clean flex items-center gap-0.5 shrink-1 min-w-0 overflow-x-auto">
        <?php foreach ($modulos as $arquivo => $info): ?>
            <?php if (in_array($userRole, $info['roles'], true)): ?>
                <a href="<?= $arquivo ?>" class="sga-nav-link sga-nav-item<?= $paginaAtual === $arquivo ? ' sga-nav-active' : '' ?>">
                    <?= $info['label'] ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- 3. Grupo de Utilitários (Idêntico em todas as páginas) -->
    <div class="sga-header-utils flex items-center space-x-1.5 shrink-0">
        <?php if ($paginaAtual === 'atendente.php'): ?>
            <button type="button" id="btn-audio-perito" onclick="toggleAudioPerito()" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/40 px-2 py-1 rounded-md transition flex items-center gap-1 shrink-0 sga-btn-audio" title="Controle do som de chamadas">
                <span id="icone-audio-perito">🔊</span> <span id="texto-audio-perito" class="hidden sm:inline">Som Ativo</span>
            </button>
        <?php endif; ?>

        <button type="button" id="btn-toggle-theme" class="sga-btn-util shrink-0 cursor-pointer" title="Alternar tema visual">
            🌙 <span class="sga-theme-label">Escuro</span>
        </button>

        <a href="ajuda.php" class="sga-btn-util sga-nav-ajuda shrink-0<?= $paginaAtual === 'ajuda.php' ? ' sga-nav-active' : '' ?>" title="Ajuda rápida e manuais do SGA">Ajuda</a>

        <div class="h-4 w-px bg-gray-700/60 mx-1 hidden md:block sga-divider"></div>

        <div class="sga-header-user flex items-center gap-1.5 shrink-0 text-xs text-gray-400">
            <span>Olá, <strong class="text-white"><?= escapar($userName) ?></strong></span>
            <span class="text-gray-600 sga-bullet">•</span>
            <a href="alterar_senha_obrigatorio.php" class="sga-user-link text-gray-400 hover:text-white underline text-xs" title="Alterar minha senha de acesso">Minha senha</a>
        </div>

        <form method="POST" action="logout.php" class="sga-logout shrink-0 m-0">
            <?= csrfCampo() ?>
            <button type="submit" class="sga-btn-sair" title="Encerrar sessão no SGA">Sair</button>
        </form>
    </div>
</header>
