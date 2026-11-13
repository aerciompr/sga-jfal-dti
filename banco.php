<?php
// Conexão de runtime: nenhuma migração ou reescrita automática de dados.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/youtube.php';
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

if (!function_exists('colunaExiste')) {
    function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool {
        static $cache = [];
        $key = "$tabela.$coluna";
        if (!isset($cache[$key])) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $s->execute([$tabela, $coluna]);
            $cache[$key] = (bool)$s->fetchColumn();
        }
        return $cache[$key];
    }
}

if (!function_exists('tabelaExiste')) {
    function tabelaExiste(PDO $pdo, string $tabela): bool {
        static $cache = [];
        if (!isset($cache[$tabela])) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $s->execute([$tabela]);
            $cache[$tabela] = (bool)$s->fetchColumn();
        }
        return $cache[$tabela];
    }
}


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
function registrarLog($pdo, $acao, $detalhes, $atendimentoId = null, ?int $subsecaoId = null) {
    $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
    $userName = isset($_SESSION['usuario_name']) ? $_SESSION['usuario_name'] : 'sistema';
    $subId = $subsecaoId ?? ($_SESSION['subsecao_id_ativa'] ?? $_SESSION['subsecao_id'] ?? 1);
    try {
        if (colunaExiste($pdo, 'logs_auditoria', 'subsecao_id')) {
            $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, detalhes, atendimento_id, ip, request_id, subsecao_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $userName, $acao, $detalhes, $atendimentoId, ipCliente() ?: null, SGA_REQUEST_ID, $subId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, detalhes, atendimento_id, ip, request_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $userName, $acao, $detalhes, $atendimentoId, ipCliente() ?: null, SGA_REQUEST_ID]);
        }
    } catch (PDOException $e) {
        error_log("Falha de auditoria: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Verifica se a pauta de uma determinada data está liberada
 */
function isPautaLiberada($pdo, $data, ?int $subsecaoId = null) {
    try {
        if (colunaExiste($pdo, 'pautas_liberadas', 'subsecao_id')) {
            $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pautas_liberadas WHERE data_pauta = ? AND subsecao_id = ?");
            $stmt->execute([$data, $subId]);
            return $stmt->fetchColumn() > 0;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pautas_liberadas WHERE data_pauta = ?");
        $stmt->execute([$data]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helpers para o ecossistema multi-subseção
 */
function multisubsecaoAtiva(PDO $pdo): bool {
    static $ativa = null;
    if ($ativa === null) {
        $ativa = (get_config($pdo, 'multisubsecao_ativa', '0') === '1');
    }
    return $ativa;
}

function subsecaoAtivaId(PDO $pdo): int {
    if (!multisubsecaoAtiva($pdo)) {
        return 1;
    }
    if (isset($_SESSION['subsecao_id_ativa']) && (int)$_SESSION['subsecao_id_ativa'] > 0) {
        return (int)$_SESSION['subsecao_id_ativa'];
    }
    if (isset($_SESSION['subsecao_id']) && (int)$_SESSION['subsecao_id'] > 0) {
        return (int)$_SESSION['subsecao_id'];
    }
    return 1;
}

function obterSubsecoes(PDO $pdo): array {
    try {
        if (!tabelaExiste($pdo, 'subsecoes')) return [];
        return $pdo->query("SELECT id, nome, cidade, sigla, endereco, telefone, token_painel FROM subsecoes WHERE ativo = 1 ORDER BY id ASC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function obterSubsecao(PDO $pdo, int $id): ?array {
    try {
        if (!tabelaExiste($pdo, 'subsecoes')) return null;
        $s = $pdo->prepare("SELECT id, nome, cidade, sigla, endereco, telefone, token_painel FROM subsecoes WHERE id = ?");
        $s->execute([$id]);
        $res = $s->fetch();
        return $res ?: null;
    } catch (Throwable $e) {
        return null;
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
    return fonteYouTubePadrao($url)['video_id'];
}
