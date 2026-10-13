#!/usr/bin/env php
<?php
/**
 * Cria (ou troca a senha de) um usuário admin direto no Supabase.
 * Use no lugar do install.php, que não funciona no Vercel (disco somente leitura).
 *
 *   DB_HOST=... DB_USER=... DB_PASS=... APP_PREFIX=moc_ \
 *   php tools/create_admin.php voce@email.com 'sua-senha-forte'
 *
 * Passe a senha entre aspas simples. Mínimo de 8 caracteres.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente linha de comando.\n"); }
require_once __DIR__ . '/../includes/db.php';

[$self, $email, $password] = array_pad($argv, 3, '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Uso: php tools/create_admin.php email@dominio.com 'senha-com-8+-caracteres'\n");
    exit(1);
}
try {
    $pdo = get_pdo();
    $st = $pdo->prepare('INSERT INTO ' . table('admin_users') . ' (email, password_hash) VALUES (?, ?)
                         ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash');
    $st->execute([$email, password_hash($password, PASSWORD_BCRYPT)]);
    echo "Admin pronto: $email\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
