<?php
require_once __DIR__ . '/banco.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $cursor = filter_var($_GET['apos'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($cursor === false) abortar(422, 'Cursor inválido.');

    $subsecaoId = 1;
    if (multisubsecaoAtiva($pdo)) {
        if (!empty($_GET['token'])) {
            $sTok = $pdo->prepare("SELECT id FROM subsecoes WHERE token_painel = ? AND ativo = 1");
            $sTok->execute([trim((string)$_GET['token'])]);
            $sId = $sTok->fetchColumn();
            if ($sId) $subsecaoId = (int)$sId;
        } elseif (!empty($_GET['subsecao'])) {
            $sSub = $pdo->prepare("SELECT id FROM subsecoes WHERE (sigla = ? OR id = ?) AND ativo = 1");
            $sSub->execute([trim((string)$_GET['subsecao']), (int)$_GET['subsecao']]);
            $sId = $sSub->fetchColumn();
            if ($sId) $subsecaoId = (int)$sId;
        } elseif (isset($_SESSION['subsecao_id_ativa'])) {
            $subsecaoId = (int)$_SESSION['subsecao_id_ativa'];
        }
    }

    $temPrio = colunaExiste($pdo, 'eventos_chamada', 'prioridade') || colunaExiste($pdo, 'atendimentos', 'prioridade');
    $colPrio = $temPrio ? "COALESCE(ec.prioridade, a.prioridade, 'normal') AS prioridade," : "'normal' AS prioridade,";
    $temSub = colunaExiste($pdo, 'eventos_chamada', 'subsecao_id');
    $whereSub = $temSub ? "ec.subsecao_id = ? AND " : "";
    $params = $temSub ? [$subsecaoId, $cursor] : [$cursor];

    $stmt = $pdo->prepare("
        SELECT ec.id, ec.nome, ec.senha, ec.sala, ec.perito, $colPrio ec.criado_em, COALESCE(a.chamadas_count, 1) AS chamadas_count 
        FROM eventos_chamada ec
        LEFT JOIN atendimentos a ON a.id = ec.atendimento_id
        WHERE $whereSub ec.data_pauta = CURRENT_DATE() AND ec.id > ? 
        ORDER BY ec.id LIMIT 100
    ");
    $stmt->execute($params);
    $ytUrl = get_config($pdo, "youtube_url_{$subsecaoId}") ?: get_config($pdo, 'youtube_url');
    echo json_encode(['success' => true, 'subsecao_id' => $subsecaoId, 'eventos' => $stmt->fetchAll(), ...fonteYouTubePadrao($ytUrl)], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
