<?php
/**
 * Roteador único para o Vercel (runtime vercel-php).
 *
 * O runtime só executa arquivos dentro de api/, então todas as URLs chegam aqui
 * (veja vercel.json) e este arquivo decide qual página PHP carregar.
 * Só o que está na lista abaixo é servido: includes/, sql/, tools/ etc. nunca.
 *
 * Também serve para rodar localmente:  php -S localhost:8000 api/index.php
 */
$root = dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim(preg_replace('~/+~', '/', $path), '/');

// Páginas PHP permitidas: URL => arquivo
$pages = [
    '/'                      => 'index.php',
    '/index.php'             => 'index.php',
    '/admin/'                => 'admin/index.php',
    '/admin/index.php'       => 'admin/index.php',
    '/admin/login.php'       => 'admin/login.php',
    '/admin/logout.php'      => 'admin/logout.php',
    '/admin/pix_preview.php' => 'admin/pix_preview.php',
];

// /admin sem barra: os redirecionamentos relativos do painel (login.php) dependem dela
if ($path === '/admin') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /admin/' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}

if (isset($pages[$path])) {
    // Os arquivos usam __DIR__ e caminhos relativos; ajusta o ambiente como se fossem a página
    $file = $root . '/' . $pages[$path];
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $path;
    chdir(dirname($file));
    require $file;
    exit;
}

// Arquivos estáticos (CSS, JS, ícones): só dentro de assets/ e icons/
if (preg_match('~^/(assets|icons)/~', $path) && strpos($path, '..') === false) {
    $full = realpath($root . $path);
    $allowed = [
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
        'woff2' => 'font/woff2', 'woff' => 'font/woff',
    ];
    $ext = strtolower(pathinfo((string) $full, PATHINFO_EXTENSION));
    $inside = $full !== false && (strpos($full, $root . '/assets/') === 0 || strpos($full, $root . '/icons/') === 0);
    if ($inside && is_file($full) && isset($allowed[$ext])) {
        header('Content-Type: ' . $allowed[$ext]);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 - Página não encontrada\n";
