<?php
require_once __DIR__ . '/banco.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $cursor = filter_var($_GET['apos'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($cursor === false) abortar(422, 'Cursor inválido.');
    $stmt = $pdo->prepare('SELECT id, nome, senha, sala, perito, criado_em FROM eventos_chamada WHERE data_pauta = CURRENT_DATE() AND id > ? ORDER BY id LIMIT 100');
    $stmt->execute([$cursor]);
    echo json_encode(['success' => true, 'eventos' => $stmt->fetchAll(), ...fonteYouTubePadrao(get_config($pdo, 'youtube_url'))], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
