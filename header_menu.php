<?php
// header_menu.php - Componente Único, Canônico e Padronizado do Cabeçalho Institucional JFAL
$paginaAtual = basename($_SERVER['PHP_SELF'] ?? '');
$rotaAtual = preg_replace('/\.php$/', '', $paginaAtual);
$userRole = $_SESSION['usuario_role'] ?? 'visitante';
$userName = $_SESSION['usuario_name'] ?? 'Usuário';

// Mapeamento oficial dos módulos por permissão de acesso (URLs Amigáveis)
$modulos = [
    'dashboard'       => ['label' => 'Painel Principal', 'file' => 'dashboard.php', 'roles' => ['admin', 'supervisor']],
    'recepcao'        => ['label' => 'Recepção',         'file' => 'recepcao.php',  'roles' => ['admin', 'supervisor', 'recepcao']],
    'atendente'       => ['label' => 'Atendimento',      'file' => 'atendente.php', 'roles' => ['admin', 'perito']],
    'gerenciar_pauta' => ['label' => 'Pautas',           'file' => 'gerenciar_pauta.php', 'roles' => ['admin', 'supervisor']],
    'salas'           => ['label' => 'Salas',            'file' => 'salas.php',     'roles' => ['admin', 'supervisor']],
    'auditoria'       => ['label' => 'Auditoria',        'file' => 'auditoria.php', 'roles' => ['admin', 'supervisor']],
    'usuarios'        => ['label' => 'Perfis & Senhas',  'file' => 'usuarios.php',  'roles' => ['admin']],
];

// Destino ao clicar na logo institucional
$homeDestino = 'dashboard';
if ($userRole === 'recepcao') {
    $homeDestino = 'recepcao';
} elseif ($userRole === 'perito') {
    $homeDestino = 'atendente';
}

global $pdo;
$subAtivaMulti = (isset($pdo) && function_exists('multisubsecaoAtiva') && multisubsecaoAtiva($pdo));
$subAtivaId = $subAtivaMulti ? subsecaoAtivaId($pdo) : 1;
$subsecoesLista = $subAtivaMulti ? obterSubsecoes($pdo) : [];
$subAtivaObj = $subAtivaMulti ? (obterSubsecao($pdo, $subAtivaId) ?? ['nome' => 'Maceió', 'sigla' => 'MCZ']) : null;
$podeTrocarSubsecao = $subAtivaMulti && (!empty($_SESSION['is_superadmin']) || $userRole === 'perito' || ($userRole === 'admin' && empty($_SESSION['usuario_subsecao_id'])));
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
        <?php if ($subAtivaMulti && $subAtivaObj): ?>
            <?php if ($podeTrocarSubsecao && count($subsecoesLista) > 1): ?>
                <form method="POST" action="trocar_subsecao.php" class="inline-flex items-center m-0">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="redirect" value="<?= escapar($_SERVER['REQUEST_URI'] ?? '') ?>">
                    <select name="subsecao_id" onchange="this.form.submit()" class="bg-blue-950/70 border border-blue-700/60 text-blue-200 text-[10px] font-bold rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer shadow-xs" title="Alternar subseção judiciária">
                        <?php foreach ($subsecoesLista as $sub): ?>
                            <option value="<?= (int)$sub['id'] ?>" <?= $sub['id'] == $subAtivaId ? 'selected' : '' ?>>
                                <?= $userRole === 'perito' ? '📍' : '🏢' ?> <?= escapar($sub['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <span class="bg-blue-950/50 text-blue-300 border border-blue-800/60 text-[10px] px-2 py-0.5 rounded-md font-bold tracking-wider" title="Subseção Judiciária Ativa">
                    🏢 <?= escapar($subAtivaObj['nome']) ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 2. Barra de Navegação Central (Abas Limpas Padronizadas com Rotas Amigáveis) -->
    <nav class="sga-nav-bar sga-nav-bar-clean flex items-center gap-0.5 shrink-1 min-w-0 overflow-x-auto">
        <?php foreach ($modulos as $slug => $info): ?>
            <?php if (in_array($userRole, $info['roles'], true)): ?>
                <a href="<?= $slug ?>" class="sga-nav-link sga-nav-item<?= ($rotaAtual === $slug || $paginaAtual === $info['file']) ? ' sga-nav-active' : '' ?>">
                    <?= $info['label'] ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- 3. Grupo de Utilitários (Idêntico em todas as páginas) -->
    <div class="sga-header-utils flex items-center space-x-1.5 shrink-0">
        <?php if ($rotaAtual === 'atendente' || $paginaAtual === 'atendente.php'): ?>
            <button type="button" id="btn-audio-perito" onclick="toggleAudioPerito()" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/40 px-2 py-1 rounded-md transition flex items-center gap-1 shrink-0 sga-btn-audio" title="Controle do som de chamadas">
                <span id="icone-audio-perito">🔊</span> <span id="texto-audio-perito" class="hidden sm:inline">Som Ativo</span>
            </button>
        <?php endif; ?>

        <button type="button" id="btn-toggle-theme" class="sga-btn-util shrink-0 cursor-pointer" title="Alternar tema visual">
            🌙 <span class="sga-theme-label">Escuro</span>
        </button>

        <a href="ajuda" class="sga-btn-util sga-nav-ajuda shrink-0<?= ($rotaAtual === 'ajuda' || $paginaAtual === 'ajuda.php') ? ' sga-nav-active' : '' ?>" title="Ajuda rápida e manuais do SGA">Ajuda</a>

        <div class="h-4 w-px bg-gray-700/60 mx-1 hidden md:block sga-divider"></div>

        <div class="sga-header-user flex items-center gap-1.5 shrink-0 text-xs text-gray-400">
            <span>Olá, <strong class="text-white"><?= escapar($userName) ?></strong></span>
            <span class="text-gray-600 sga-bullet">•</span>
            <a href="alterar_senha_obrigatorio" class="sga-user-link text-gray-400 hover:text-white underline text-xs" title="Alterar minha senha de acesso">Minha senha</a>
        </div>

        <form method="POST" action="logout" class="sga-logout shrink-0 m-0">
            <?= csrfCampo() ?>
            <button type="submit" class="sga-btn-sair" title="Encerrar sessão no SGA">Sair</button>
        </form>
    </div>
</header>
