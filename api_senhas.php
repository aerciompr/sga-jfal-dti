<?php
/**
 * API DE POLING DE SENHAS - TV RECEPÇÃO
 * 
 * api_senhas.php: Endpoint consultado via AJAX a cada 1 segundo pela TV.
 * Retorna em formato JSON os detalhes da última senha/paciente chamado hoje (status = 'chamada')
 * e a URL ativa do vídeo do YouTube cadastrado.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/banco.php';

try {
    // Consulta a última chamada ativa no dia de hoje
    $stmt = $pdo->query("SELECT id, senha, nome, sala, perito, chamado_em FROM atendimentos WHERE status = 'chamada' AND DATE(chamado_em) = CURRENT_DATE() ORDER BY chamado_em DESC LIMIT 1");
    $ultima = $stmt->fetch();
    
    // Recupera a URL de vídeo configurada (prioriza banco de dados com fallback para arquivo)
    $yt_url = get_config($pdo, 'youtube_url');
    if (empty($yt_url)) {
        $yt_file = __DIR__ . '/youtube_url.txt';
        $yt_url = file_exists($yt_file) ? trim(file_get_contents($yt_file)) : 'https://www.youtube.com/watch?v=5qap5aO4i9A';
    }

    // Extrai o ID de 11 caracteres da URL do YouTube
    $video_id = '5qap5aO4i9A';
    if (preg_match('%(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $yt_url, $match)) {
        $video_id = $match[1];
    }

    if ($ultima) {
        echo json_encode([
            "success" => true,
            "id" => $ultima['id'],
            "senha" => $ultima['senha'],
            "nome" => $ultima['nome'] ?: '',
            "sala" => $ultima['sala'],
            "perito" => $ultima['perito'] ?: '',
            "video_id" => $video_id,
            "chamado_em" => $ultima['chamado_em']
        ]);
    } else {
        // Retorna estado vazio se não houver chamadas
        echo json_encode([
            "success" => true,
            "id" => 0,
            "senha" => "---",
            "nome" => "",
            "sala" => "---",
            "perito" => "",
            "video_id" => $video_id,
            "chamado_em" => ""
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
