<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sessão do formulário expirada. Tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (attempt_login($email, $password)) {
            header('Location: index.php');
            exit;
        }
        $error = 'E-mail ou senha inválidos.';
    }
}

$csrf = csrf_token();
$justCreated = isset($_GET['created']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Entrar — Painel Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  /* Login autossuficiente: não depende de admin.css/style.css (evita cache antigo) */
  *, *::before, *::after { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: 'Public Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: #1B2A28;
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(176,141,87,.18), transparent 60%),
      radial-gradient(700px 500px at 100% 110%, rgba(255,255,255,.06), transparent 60%),
      #0F3D3E;
    -webkit-font-smoothing: antialiased;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    min-height: 100vh;
  }
  .lg-card {
    width: 100%;
    max-width: 400px;
    background: #FAF6ED;
    border-radius: 20px;
    padding: 36px 32px 30px;
    box-shadow: 0 24px 60px rgba(0,0,0,.35);
  }
  .lg-brand {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: #0F3D3E;
    color: #E3C48F;
    display: grid; place-items: center;
    font-family: 'Newsreader', Georgia, serif;
    font-size: 24px; font-weight: 600;
    margin-bottom: 20px;
  }
  .lg-card h1 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 28px; line-height: 1.15; font-weight: 600;
    margin: 0 0 6px; color: #12302F;
  }
  .lg-sub { margin: 0 0 24px; font-size: 14px; color: #5a6b68; line-height: 1.5; }
  .lg-msg {
    padding: 12px 14px; border-radius: 10px; margin-bottom: 18px;
    font-size: 14px; font-weight: 500; line-height: 1.4;
  }
  .lg-msg.success { background: #d9ede0; color: #1a5d3a; border-left: 4px solid #2e8b57; }
  .lg-msg.error   { background: #f7dcdc; color: #8a2222; border-left: 4px solid #c0392b; }
  form { display: flex; flex-direction: column; gap: 16px; }
  .lg-field { display: flex; flex-direction: column; gap: 6px; }
  .lg-field label { font-size: 13px; font-weight: 600; color: #0F3D3E; }
  .lg-field input {
    width: 100%;
    padding: 12px 14px;
    font: inherit; font-size: 15px; color: #1B2A28;
    background: #fff;
    border: 1px solid #d8d2c4;
    border-radius: 10px;
    transition: border-color .15s, box-shadow .15s;
  }
  .lg-field input:focus {
    outline: 0;
    border-color: #B08D57;
    box-shadow: 0 0 0 3px rgba(176,141,87,.22);
  }
  .lg-btn {
    margin-top: 4px;
    width: 100%;
    padding: 13px 20px;
    font: inherit; font-size: 15px; font-weight: 600;
    color: #FAF6ED; background: #0F3D3E;
    border: 0; border-radius: 10px; cursor: pointer;
    transition: background .15s, transform .05s;
  }
  .lg-btn:hover { background: #0a2c2d; }
  .lg-btn:active { transform: translateY(1px); }
  .lg-btn:focus-visible { outline: 3px solid #B08D57; outline-offset: 2px; }
  .lg-note {
    margin: 22px 0 0; padding-top: 18px;
    border-top: 1px solid rgba(15,61,62,.12);
    font-size: 12px; line-height: 1.55; color: #6a7a77;
  }
  .lg-note code {
    background: rgba(15,61,62,.08); padding: 1px 6px; border-radius: 4px; font-size: 12px;
  }
  @media (max-width: 440px) { .lg-card { padding: 28px 22px 24px; border-radius: 16px; } }
</style>
</head>
<body>

  <main class="lg-card">
    <div class="lg-brand" aria-hidden="true">C</div>
    <h1>Entrar no painel</h1>
    <p class="lg-sub">Acesse para editar seu cartão de links.</p>

    <?php if ($justCreated): ?>
      <div class="lg-msg success">Administrador criado. Faça login abaixo.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="lg-msg error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <div class="lg-field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autocomplete="username" autofocus>
      </div>
      <div class="lg-field">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="lg-btn">Entrar</button>
    </form>

    <p class="lg-note">
      Ainda não instalou? Acesse <code>install.php</code> na raiz do site
      para configurar o banco e criar seu usuário.
    </p>
  </main>

</body>
</html>
