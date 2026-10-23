<?php
declare(strict_types=1);

function abortar(int $codigo, string $mensagem): never {
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensagem);
}

function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
        'secure' => getenv('SESSION_SECURE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function ipCliente(): string {
    $origem = $_SERVER['REMOTE_ADDR'] ?? '';
    $proxies = array_filter(array_map('trim', explode(',', getenv('TRUSTED_PROXY_IPS') ?: '')));
    $encaminhado = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    return in_array($origem, $proxies, true) && filter_var($encaminhado, FILTER_VALIDATE_IP) ? $encaminhado : $origem;
}

function csrfCampo(): string {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function dataValida(string $valor, string $formato = 'Y-m-d'): bool {
    $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
    return $data !== false && $data->format($formato) === $valor;
}

function escapar($valor, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8'): string {
    return htmlspecialchars((string)($valor ?? ''), $flags, $encoding);
}

function jsValor($valor): string {
    return htmlspecialchars(json_encode($valor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
}

function erroPublico(Throwable $erro): string {
    $id = bin2hex(random_bytes(6));
    error_log("SGA [$id] " . $erro);
    return "Não foi possível concluir a operação. Referência: $id.";
}

function csvSeguro($arquivo, array $valores): void {
    $valores = array_map(static function ($valor) {
        $valor = (string)($valor ?? '');
        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $valor) ? "'" . $valor : $valor;
    }, $valores);
    fputcsv($arquivo, $valores, ',', '"', '');
}

function protegerRequisicao(PDO $pdo): void {
    if (PHP_SAPI === 'cli') return;
    header('X-Content-Type-Options: nosniff');
    // O player YouTube exige identificação da origem (sem enviar caminho ou parâmetros).
    header('Referrer-Policy: ' . (basename($_SERVER['SCRIPT_NAME'])==='painel.php' ? 'strict-origin-when-cross-origin' : 'same-origin'));
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://i.ytimg.com; frame-src https://www.youtube.com https://www.youtube-nocookie.com; connect-src 'self' https://www.youtube.com; media-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    header('Cache-Control: no-store');
    $pagina = basename($_SERVER['SCRIPT_NAME']);
    if (in_array($pagina, ['painel.php', 'api_senhas.php', 'api_senhas_recentes.php'], true)) {
        // Painel e consultas de chamadas abertos para exibição na intranet.
        return;
    }
    iniciarSessao();
    if (isset($_SESSION['usuario_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $user = $stmt->fetch();
        $expirou = time() - ($_SESSION['ultima_atividade'] ?? 0) > 1800 || time() - ($_SESSION['inicio_sessao'] ?? 0) > 28800;
        if (!$user || $expirou || !isset($_SESSION['credencial']) ||
            !hash_equals($_SESSION['credencial'], hash('sha256', $user['password'] . '|' . $user['session_version']))) {
            $_SESSION = []; session_regenerate_id(true);
            $_SESSION['login_aviso'] = 'Sua sessão foi encerrada por segurança. Entre novamente para continuar.';
            header('Location: index.php'); exit;
        }
        $_SESSION['ultima_atividade'] = time();
        $_SESSION['usuario_role'] = $user['role'];
        $_SESSION['usuario_nome'] = $user['nome'] ?: $user['username'];
        $_SESSION['usuario_name'] = $user['username'];
        $_SESSION['troca_obrigatoria'] = (bool)$user['must_change_password'];
        if ($user['must_change_password'] && !in_array($pagina, ['alterar_senha_obrigatorio.php', 'logout.php'], true)) {
            header('Location: alterar_senha_obrigatorio.php'); exit;
        }
    } elseif ($pagina !== 'index.php') {
        header('Location: index.php'); exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!isset($_SESSION['csrf'], $_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            abortar(403, 'Formulário expirado. Atualize a página e tente novamente.');
        }
        foreach ($_POST as $chave => $valor) {
            if (!is_string($valor)) continue;
            $formatoData = $chave==='nova_data' && ($_POST['action']??'')==='mover_pauta' && ($_POST['mover_tipo']??'')==='mes' && strlen($valor)===7 ? 'Y-m' : 'Y-m-d';
            if (preg_match('/(^data_|^nova_data$|_data_|_data$)/', $chave) && $valor !== '' && !dataValida($valor,$formatoData)) abortar(422, 'Data inválida.');
            if ((str_contains($chave, 'mes_') || str_ends_with($chave, '_mes')) && $valor !== '' && !dataValida($valor, 'Y-m')) abortar(422, 'Mês inválido.');
        }
    }
    foreach (['data_filtro', 'data_busca', 'data_inicio', 'data_fim', 'hoje'] as $chave) {
        if (isset($_GET[$chave]) && !dataValida((string)$_GET[$chave])) abortar(422, 'Data inválida.');
    }
    if (isset($_GET['mes_busca']) && !dataValida((string)$_GET['mes_busca'], 'Y-m')) abortar(422, 'Mês inválido.');
}
