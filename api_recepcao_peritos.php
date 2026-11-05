<?php
declare(strict_types=1);

require_once __DIR__ . '/banco.php';
require_once __DIR__ . '/atendimento_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

try {
    $data = (string)($_GET['data'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $data = date('Y-m-d');
    }

    $resumo = obterResumoFilasPeritos($pdo, $data);
    $salasOcupadas = obterSalasOcupadasHoje($pdo);

    echo json_encode([
        'success' => true,
        'data_filtro' => $data,
        'peritos' => $resumo,
        'salas_ocupadas' => $salasOcupadas,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
