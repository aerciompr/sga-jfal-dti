<?php
// Conexão de runtime: nenhuma migração ou reescrita automática de dados.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/atendimento_service.php';
require_once __DIR__ . '/pauta_service.php';
date_default_timezone_set('America/Maceio');
define('SGA_REQUEST_ID', bin2hex(random_bytes(8)));
set_exception_handler(static function (Throwable $e) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) $GLOBALS['pdo']->rollBack();
    error_log('SGA [' . SGA_REQUEST_ID . '] ' . $e);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Operação falhou. Consulte o log: " . SGA_REQUEST_ID . PHP_EOL); exit(1); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não foi possível concluir a operação. Referência: ' . SGA_REQUEST_ID;
});
$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("SET time_zone = '-03:00'");
protegerRequisicao($pdo);

function simplifyName($name) {
    $name = mb_strtolower($name, 'UTF-8');
    // Mapeamento de caracteres acentuados para suas versões limpas
    $utf8 = [
        '/[áàâãä]/u'   =>   'a',
        '/[éèêë]/u'    =>   'e',
        '/[íìîï]/u'    =>   'i',
        '/[óòôõö]/u'   =>   'o',
        '/[úùûü]/u'    =>   'u',
        '/[ç]/u'       =>   'c',
        '/[ñ]/u'       =>   'n',
    ];
    $name = preg_replace(array_keys($utf8), array_values($utf8), $name);
    // Remove todos os caracteres não-alfanuméricos e espaços
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

/**
 * Simplifica uma string mantendo os espaços para separar as palavras.
 */
function simplifyNameWithSpaces($name) {
    $name = mb_strtolower($name, 'UTF-8');
    $utf8 = [
        '/[áàâãä]/u'   =>   'a',
        '/[éèêë]/u'    =>   'e',
        '/[íìîï]/u'    =>   'i',
        '/[óòôõö]/u'   =>   'o',
        '/[úùûü]/u'    =>   'u',
        '/[ç]/u'       =>   'c',
        '/[ñ]/u'       =>   'n',
    ];
    $name = preg_replace(array_keys($utf8), array_values($utf8), $name);
    // Substitui caracteres não-alfanuméricos e espaços extras por um único espaço
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

/**
 * Função para registrar ações no log de auditoria
 */
function registrarLog($pdo, $acao, $detalhes, $atendimentoId = null) {
    $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
    $userName = isset($_SESSION['usuario_name']) ? $_SESSION['usuario_name'] : 'sistema';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, detalhes, atendimento_id, ip, request_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userName, $acao, $detalhes, $atendimentoId, ipCliente() ?: null, SGA_REQUEST_ID]);
    } catch (PDOException $e) {
        error_log("Falha de auditoria: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Verifica se a pauta de uma determinada data está liberada
 */
function isPautaLiberada($pdo, $data) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pautas_liberadas WHERE data_pauta = ?");
        $stmt->execute([$data]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recupera um valor de configuração global do banco
 */
function get_config($pdo, $chave, $padrao = '') {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $padrao;
    } catch (Exception $e) {
        return $padrao;
    }
}

/**
 * Define ou atualiza um valor de configuração global no banco
 */
function set_config($pdo, $chave, $valor) {
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    return $stmt->execute([$chave, $valor, $valor]);
}

function videoId(string $url): string {
    if (preg_match('~(?:youtube\.com/(?:watch\?.*v=|embed/)|youtu\.be/)([a-zA-Z0-9_-]{11})(?:[^a-zA-Z0-9_-]|$)~', $url, $m)) return $m[1];
    return '5qap5aO4i9A';
}
