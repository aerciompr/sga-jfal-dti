<?php
// router.php - Roteador para desenvolvimento com o servidor embutido do PHP (php -S)
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1. Arquivos estáticos existentes (assets, imagens, css, js, etc.)
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// 2. Proteção de arquivos internos de configuração e serviços
$slug = trim($uri, '/');
$blocked = ['config', 'banco', 'youtube', 'seguranca', 'atendimento_service', 'pauta_service', 'usuarios_service', 'importacao_service', 'tv_autorizacao'];
if (in_array(preg_replace('/\.php$/', '', $slug), $blocked, true)) {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
}

// 3. Raiz ou /index
if ($uri === '/' || $uri === '/index') {
    require __DIR__ . '/index.php';
    return true;
}

// 4. Mapeia rota sem extensão para arquivo .php correspondente
$phpFile = __DIR__ . '/' . $slug . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

return false;
