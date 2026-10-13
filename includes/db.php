<?php
require_once __DIR__ . '/config.php';

$_pdo = null;

/**
 * Conexão PDO com o Supabase (PostgreSQL). Exige a extensão pdo_pgsql.
 */
function get_pdo(): PDO {
    global $_pdo;
    if ($_pdo === null) {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('A extensão PHP pdo_pgsql não está instalada/ativada nesta hospedagem.');
        }
        $dsn = 'pgsql:host=' . DB_HOST
             . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME
             . ';sslmode=' . DB_SSLMODE
             . ';connect_timeout=8';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        // Pooler em modo "transaction" (porta 6543) não aceita prepared statements nativos
        if (DB_POOLER_TRANSACTION) {
            $opts[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        $_pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        $_pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    }
    return $_pdo;
}

/**
 * Retorna o nome da tabela com prefixo (sempre minúsculo, sem precisar de aspas).
 */
function table(string $name): string {
    return APP_PREFIX . $name;
}
?>
