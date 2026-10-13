<?php
// includes/config.php
// Lê as credenciais das variáveis de ambiente (Vercel) e, se existir,
// de includes/config.local.php (uso local). NÃO coloque senhas aqui.

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require_once $local; // define('DB_HOST', '...'); etc.
}

if (!function_exists('cfg_env')) {
    function cfg_env(string $key, $default = '') {
        $v = getenv($key);
        if ($v === false || $v === '') {
            $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        return ($v === null || $v === '') ? $default : $v;
    }
}

function cfg_define(string $key, $default = ''): void {
    if (!defined($key)) {
        define($key, cfg_env($key, $default));
    }
}

// --- Supabase (Postgres) ---
cfg_define('DB_HOST');
cfg_define('DB_PORT', '6543');
cfg_define('DB_NAME', 'postgres');
cfg_define('DB_USER');
cfg_define('DB_PASS');
cfg_define('DB_SSLMODE', 'require');
cfg_define('APP_PREFIX', 'moc_');

// Pooler em modo "transaction" (porta 6543) => prepares emulados
if (!defined('DB_POOLER_TRANSACTION')) {
    define('DB_POOLER_TRANSACTION', (string) DB_PORT === '6543');
}

// --- Sessão do painel admin ---
if (!defined('SESSION_COOKIE_NAME')) {
    define('SESSION_COOKIE_NAME', APP_PREFIX . 'admin_session');
}
if (!defined('SESSION_LIFETIME_HOURS')) {
    define('SESSION_LIFETIME_HOURS', (int) cfg_env('SESSION_LIFETIME_HOURS', 168)); // 7 dias
}

// --- Backblaze B2 (API S3) ---
cfg_define('B2_KEY_ID');
cfg_define('B2_APP_KEY');
cfg_define('B2_BUCKET');
cfg_define('B2_REGION', 'us-west-004');

if (!defined('B2_ENDPOINT')) {
    define('B2_ENDPOINT', rtrim((string) cfg_env('B2_ENDPOINT', 'https://s3.' . B2_REGION . '.backblazeb2.com'), '/'));
}
if (!defined('B2_PUBLIC_URL')) {
    // URL pública do bucket. Se não definir B2_PUBLIC_URL, usa o endereço padrão do B2.
    $pub = cfg_env('B2_PUBLIC_URL', '');
    if ($pub === '' && B2_BUCKET !== '') {
        $pub = 'https://' . B2_BUCKET . '.s3.' . B2_REGION . '.backblazeb2.com';
    }
    define('B2_PUBLIC_URL', rtrim((string) $pub, '/'));
}
if (!defined('B2_PREFIX')) {
    $prefix = trim((string) cfg_env('B2_PREFIX', 'uploads/'), '/');
    define('B2_PREFIX', $prefix === '' ? '' : $prefix . '/');
}

// --- Limites de upload (a Vercel aceita ~4 MB por requisição) ---
$onVercel = (bool) cfg_env('VERCEL', '');
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', ($onVercel ? 4 : 5) * 1024 * 1024);
}
if (!defined('UPLOAD_VIDEO_MAX_SIZE')) {
    define('UPLOAD_VIDEO_MAX_SIZE', ($onVercel ? 4 : 20) * 1024 * 1024);
}
