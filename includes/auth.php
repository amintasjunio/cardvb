<?php
require_once __DIR__ . '/db.php';

/** HTTPS direto ou atrás de proxy (Vercel envia X-Forwarded-Proto). */
function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Retorna os dados do admin logado (via cookie + tabela admin_sessions),
 * ou null se não houver sessão válida. Não depende de session_start()/
 * arquivos de sessão do PHP, então funciona em qualquer hospedagem.
 */
function current_admin(): ?array {
    if (empty($_COOKIE[SESSION_COOKIE_NAME])) {
        return null;
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email FROM ' . table('admin_sessions') . ' s
         JOIN ' . table('admin_users') . ' a ON a.id = s.admin_id
         WHERE s.token = ? AND s.expires_at > NOW()'
    );
    $stmt->execute([$_COOKIE[SESSION_COOKIE_NAME]]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Redireciona para o login se não houver admin autenticado.
 */
function require_login(): array {
    $admin = current_admin();
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}

/**
 * Confere e-mail/senha, cria uma sessão (token) no banco e seta o cookie.
 */
function attempt_login(string $email, string $password): bool {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM ' . table('admin_users') . ' WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('c', time() + SESSION_LIFETIME_HOURS * 3600); // ISO 8601 com fuso (coluna TIMESTAMPTZ)

    // Limpa sessões vencidas (evita a tabela crescer para sempre)
    $pdo->exec('DELETE FROM ' . table('admin_sessions') . ' WHERE expires_at < NOW()');

    $ins = $pdo->prepare('INSERT INTO ' . table('admin_sessions') . ' (token, admin_id, expires_at) VALUES (?, ?, ?)');
    $ins->execute([$token, $user['id'], $expiresAt]);

    setcookie(SESSION_COOKIE_NAME, $token, [
        'expires'  => time() + SESSION_LIFETIME_HOURS * 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_is_https(),
    ]);

    return true;
}

/**
 * Apaga a sessão atual (banco + cookie).
 */
function logout_admin(): void {
    if (!empty($_COOKIE[SESSION_COOKIE_NAME])) {
        $pdo = get_pdo();
        $pdo->prepare('DELETE FROM ' . table('admin_sessions') . ' WHERE token = ?')
            ->execute([$_COOKIE[SESSION_COOKIE_NAME]]);
        setcookie(SESSION_COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
    }
}

/**
 * Gera (ou reaproveita) um token CSRF simples via cookie, para incluir
 * como campo oculto nos formulários do admin.
 */
function csrf_token(): string {
    $cookieName = APP_PREFIX . 'csrf_token';
    if (!empty($_COOKIE[$cookieName])) {
        return $_COOKIE[$cookieName];
    }
    $token = bin2hex(random_bytes(16));
    setcookie($cookieName, $token, [
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => request_is_https(),
    ]);
    return $token;
}

function verify_csrf(): bool {
    $cookieName = APP_PREFIX . 'csrf_token';
    return !empty($_POST['csrf_token'])
        && !empty($_COOKIE[$cookieName])
        && hash_equals($_COOKIE[$cookieName], $_POST['csrf_token']);
}
?>
