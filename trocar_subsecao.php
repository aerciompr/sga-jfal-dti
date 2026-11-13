<?php
declare(strict_types=1);

require_once __DIR__ . '/banco.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!csrfValido($_POST['csrf'] ?? '')) {
    abortar(403, 'Token de segurança CSRF inválido ou expirado.');
}

if (!multisubsecaoAtiva($pdo)) {
    header("Location: dashboard.php");
    exit;
}

$userRole = $_SESSION['usuario_role'] ?? '';
$isSuperAdmin = !empty($_SESSION['is_superadmin']) || ($userRole === 'admin' && empty($_SESSION['usuario_subsecao_id']));
$isPerito = ($userRole === 'perito');

// Apenas Super-admin e Peritos podem alternar entre subseções livremente
if (!$isSuperAdmin && !$isPerito) {
    abortar(403, 'Apenas peritos médicos e administradores gerais podem alternar entre subseções.');
}

$targetSubsecaoId = (int)($_POST['subsecao_id'] ?? 0);
$subsecao = obterSubsecao($pdo, $targetSubsecaoId);
if (!$subsecao) {
    abortar(400, 'Subseção judiciária inválida ou inativa.');
}

$oldSubsecaoId = subsecaoAtivaId($pdo);

// Se for perito e estiver mudando de subseção, libera a sala travada na subseção anterior
if ($isPerito && $oldSubsecaoId !== $targetSubsecaoId) {
    require_once __DIR__ . '/atendimento_service.php';
    $peritoNome = $_SESSION['perito_selecionado'] ?? ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_name']);
    $salaAnterior = $_SESSION['sala'] ?? '';
    if ($peritoNome && $salaAnterior) {
        try {
            liberarSalaPerito($pdo, (string)$peritoNome, (string)$salaAnterior, $oldSubsecaoId);
        } catch (Throwable $e) {
            // Loga mas não interrompe a transição
            error_log("Falha ao liberar sala anterior na troca de subseção: " . $e->getMessage());
        }
    }
    unset($_SESSION['sala']);
}

$_SESSION['subsecao_id_ativa'] = $targetSubsecaoId;
registrarLog($pdo, 'Troca de Subseção', "Usuário alternou contexto para subseção '{$subsecao['nome']}' (ID: {$targetSubsecaoId})", null, $targetSubsecaoId);

// Sanitização de Redirecionamento (prevenção a Open Redirect)
$redirect = trim((string)($_POST['redirect'] ?? ''));
$destinoSeguro = ($userRole === 'perito') ? 'atendente.php' : 'dashboard.php';

if ($redirect !== '') {
    // Extrai apenas o path relativo ignorando esquemas absolutos ou hostnames
    $parsed = parse_url($redirect);
    $path = $parsed['path'] ?? '';
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
    $baseName = basename($path);
    $rotasPermitidas = [
        'dashboard.php', 'dashboard',
        'recepcao.php', 'recepcao',
        'atendente.php', 'atendente',
        'gerenciar_pauta.php', 'gerenciar_pauta',
        'salas.php', 'salas',
        'usuarios.php', 'usuarios',
        'auditoria.php', 'auditoria',
        'ajuda.php', 'ajuda'
    ];
    if (in_array($baseName, $rotasPermitidas, true) && !preg_match('#^https?://#i', $redirect) && !str_starts_with($redirect, '//')) {
        $destinoSeguro = $baseName . $query;
    }
}

header("Location: " . $destinoSeguro, true, 303);
exit;
