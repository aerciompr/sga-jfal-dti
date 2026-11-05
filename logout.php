<?php
require_once __DIR__ . '/banco.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Sair do SGA</title>
    <form method="post"><?= csrfCampo() ?><button type="submit">Confirmar saída</button></form></html><?php
    exit;
}

// Liberação automática da sala ao sair se o usuário for perito
if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'perito') {
    $peritoNome = (string)($_SESSION['usuario_nome'] ?? $_SESSION['usuario_name'] ?? '');
    if ($peritoNome !== '') {
        try {
            liberarSalaPerito($pdo, $peritoNome);
            registrarLog($pdo, 'Liberar Sala por Logout', "Sala liberada no encerramento da sessão do perito: $peritoNome");
        } catch (Throwable $e) {
            // Ignora para não travar a saída do perito caso haja atendimento ativo
        }
    }
}

registrarLog($pdo, 'Logout', 'Sessão encerrada.');
$_SESSION = [];
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
header('Location: index.php');
exit;
