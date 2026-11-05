<?php
require_once __DIR__ . '/banco.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $cursor = filter_var($_GET['apos'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($cursor === false) abortar(422, 'Cursor inválido.');
    $stmt = $pdo->prepare('
        SELECT ec.id, ec.nome, ec.senha, ec.sala, ec.perito, ec.criado_em, COALESCE(a.chamadas_count, 1) AS chamadas_count 
        FROM eventos_chamada ec
        LEFT JOIN atendimentos a ON a.id = ec.atendimento_id
        WHERE ec.data_pauta = CURRENT_DATE() AND ec.id > ? 
        ORDER BY ec.id LIMIT 100
    ');
    $stmt->execute([$cursor]);
    echo json_encode(['success' => true, 'eventos' => $stmt->fetchAll(), ...fonteYouTubePadrao(get_config($pdo, 'youtube_url'))], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
