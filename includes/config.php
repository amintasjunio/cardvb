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

// --- Backblaze B2 ---
cfg_define('B2_KEY_ID');
cfg_define('B2_APP_KEY');
cfg_define('B2_BUCKET');
cfg_define('B2_REGION', 'us-west-004');
cfg_define('B2_PUBLIC_URL');
cfg_define('B2_PREFIX', 'uploads/');
