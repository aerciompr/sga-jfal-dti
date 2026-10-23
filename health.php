<?php
// Readiness sem sessão, DDL, dados pessoais ou detalhes de infraestrutura na resposta.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    if (!extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) throw new RuntimeException('Extensão ausente');
    $db = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
    if (!$db->query("SELECT versao FROM schema_migrations WHERE versao='20261022_hardening'")->fetchColumn()) throw new RuntimeException('Migração pendente');
    echo '{"status":"ok"}';
} catch (Throwable $e) { http_response_code(503); echo '{"status":"unavailable"}'; }
