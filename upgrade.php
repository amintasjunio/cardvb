<?php
/**
 * upgrade.php — Migração para bancos já instalados.
 * Roda as migrações pendentes de includes/migrations.php (colunas de Banner,
 * vídeo/slideshow e PIX na tabela de settings).
 * Idempotente: pode rodar várias vezes sem erro.
 * Apague este arquivo depois de usar.
 */
require_once __DIR__ . '/includes/db.php';          // carrega config.php + get_pdo() + table()
require_once __DIR__ . '/includes/migrations.php';

$errors  = [];
$success = null;
$done    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = get_pdo();

        $t = table('settings');

        // Verifica se a tabela existe
        $check = $pdo->prepare('SELECT to_regclass(?)');
        $check->execute([$t]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException("A tabela `$t` não existe. Você instalou o sistema?");
        }

        // Roda só o que ainda não foi aplicado (cada migração é idempotente)
        $results = run_all_pending_migrations($pdo);

        $done = true;
        if ($results) {
            $lines = [];
            foreach ($results as $name => $msg) $lines[] = $name . ': ' . $msg;
            $success = implode("\n", $lines);
        } else {
            $success = 'Nada a fazer — o banco já está atualizado.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upgrade — Banner, vídeo & PIX</title>
<style>
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(160deg,#0F3D3E,#0A2B2C);color:#1B2A28;margin:0;display:grid;place-items:center;min-height:100vh;padding:20px}
.box{background:#FAF6ED;border-radius:18px;padding:36px 32px;max-width:520px;width:100%;box-shadow:0 30px 60px -20px rgba(0,0,0,.55)}
h1{font-family:Georgia,serif;font-size:22px;margin:0 0 12px}
p{font-size:14px;color:#4B5B58;line-height:1.65;margin:0 0 14px}
code{background:#E9F2E8;padding:2px 6px;border-radius:5px;font-size:12.5px}
.ok{white-space:pre-line;background:#E9F2E8;color:#2F6B3A;padding:14px 16px;border-radius:10px;font-size:13.5px;border:1px solid #D0E8CC;margin-bottom:16px}
.err{background:#FBEAE6;color:#B4432F;padding:12px 14px;border-radius:10px;font-size:13px;border:1px solid #F5C6BC;margin-bottom:10px}
button{background:#0F3D3E;color:#fff;border:0;padding:12px 22px;border-radius:9px;font-weight:600;cursor:pointer;font-size:14px}
button:hover{background:#0A2B2C}
a{color:#0F3D3E;font-size:13px;text-decoration:none;font-weight:600}
.muted{color:#7C9385;font-size:12px;margin-top:20px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h1>🔧 Atualizar banco</h1>
  <p>Adiciona as colunas de <strong>Banner</strong> (imagem, vídeo, slideshow) e <strong>PIX</strong> na tabela
     <code><?= htmlspecialchars(APP_PREFIX) ?>settings</code>.</p>

  <?php foreach ($errors as $e): ?>
    <div class="err">❌ <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <?php if ($done): ?>
    <div class="ok">✓ <?= htmlspecialchars($success) ?></div>
    <p><a href="admin/index.php">→ Ir para o painel</a></p>
    <p class="muted">Apague o arquivo <code>upgrade.php</code> agora.</p>
  <?php else: ?>
    <form method="post">
      <button type="submit">Rodar migração</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>