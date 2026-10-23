<?php
require_once __DIR__ . '/banco.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Sair do SGA</title>
    <form method="post"><?= csrfCampo() ?><button type="submit">Confirmar saída</button></form></html><?php
    exit;
}
registrarLog($pdo, 'Logout', 'Sessão encerrada.');
$_SESSION = [];
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
header('Location: index.php'); exit;
