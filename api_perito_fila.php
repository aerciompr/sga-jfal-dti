<?php
declare(strict_types=1);

require_once __DIR__ . '/banco.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'perito'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

try {
    $user_role = (string)$_SESSION['usuario_role'];
    $ownerId = (int)$_SESSION['usuario_id'];
    $ownerAdmin = $user_role === 'admin' ? 1 : 0;

    if ($user_role === 'perito') {
        $perito_selecionado = (string)($_SESSION['usuario_nome'] ?? $_SESSION['usuario_name']);
    } else {
        $perito_selecionado = (string)($_SESSION['perito_selecionado'] ?? '');
    }

    $sala = (string)($_SESSION['sala'] ?? 'Sala 1');

    // Atendimento atual (sendo chamado ou atendido agora nesta sala)
    $stmtAtual = $pdo->prepare("SELECT id, nome, processo, senha, status, sala, chamado_em, chamadas_count FROM atendimentos WHERE status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY chamado_em DESC LIMIT 1");
    $stmtAtual->execute([$sala, $ownerId, $ownerAdmin, $perito_selecionado]);
    $atendimentoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

    // Fila de espera de pacientes que já chegaram hoje para este perito
    if ($perito_selecionado !== '') {
        $stmtFila = $pdo->prepare("SELECT id, nome, processo, senha, status, chegada_em, fila_em FROM atendimentos WHERE status = 'presente' AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY fila_em ASC, id ASC");
        $stmtFila->execute([$ownerId, $ownerAdmin, $perito_selecionado]);
        $presentes = $stmtFila->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtFila = $pdo->query("SELECT id, nome, processo, senha, status, chegada_em, fila_em FROM atendimentos WHERE status = 'presente' AND data_pauta = CURRENT_DATE() ORDER BY fila_em ASC, id ASC");
        $presentes = $stmtFila->fetchAll(PDO::FETCH_ASSOC);
    }

    $filaFormatada = [];
    foreach ($presentes as $p) {
        $filaFormatada[] = [
            'id' => (int)$p['id'],
            'senha' => (string)$p['senha'],
            'nome' => (string)($p['nome'] ?: 'Sem Nome'),
            'processo' => (string)($p['processo'] ?: 'Sem processo'),
            'chegada' => !empty($p['chegada_em']) ? date('H:i:s', strtotime($p['chegada_em'])) : '--:--:--',
        ];
    }

    echo json_encode([
        'success' => true,
        'total_aguardando' => count($filaFormatada),
        'fila' => $filaFormatada,
        'atendimento_atual_id' => $atendimentoAtual ? (int)$atendimentoAtual['id'] : 0,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
