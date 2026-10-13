<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = get_pdo();
$profile = get_profile($pdo);
$settings = get_settings($pdo);
$links = get_active_links($pdo);

$name = $profile['name'] ?? 'Seu Nome';
$bgColor = $settings['bg_color'] ?? '#0F3D3E';
$accent = $settings['accent_color'] ?? '#B08D57';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($name) ?></title>
<meta name="description" content="Cartão de visita digital">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,500&family=Playfair+Display:wght@500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Poppins:wght@400;500;600;700&family=Public+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Work+Sans:wght@400;500;600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  :root {
    --stage: <?= e($bgColor) ?>;
    --stage-dark: <?= e(hex_adjust($bgColor, -25)) ?>;
    --card: <?= e($settings['card_color'] ?? '#FAF6ED') ?>;
    --ink: <?= e($settings['text_color'] ?? '#1B2A28') ?>;
    --gold: <?= e($accent) ?>;
    --gold-dim: <?= e(hex_adjust($accent, 45)) ?>;
    --font-display: <?= font_display_stack($settings['font_display'] ?? null) ?>;
    --font-body: <?= font_body_stack($settings['font_body'] ?? null) ?>;
  }
</style>
</head>
<body data-layout="<?= e($settings['layout'] ?? 'card') ?>">

  <div class="stage">
    <main class="card">
      <div class="card-inner">

        <div class="card-top">
          <div class="avatar" data-shape="<?= e($settings['avatar_shape'] ?? 'circle') ?>">
            <?php if (!empty($profile['avatar_url'])): ?>
              <img src="<?= e($profile['avatar_url']) ?>" alt="<?= e($name) ?>">
            <?php else: ?>
              <span><?= e($profile['initials_override'] ?: initials($name)) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="name"><?= e($name) ?></h1>
          <?php if (!empty($profile['title'])): ?>
            <p class="title"><?= e($profile['title']) ?></p>
          <?php endif; ?>
        </div>

        <?php if (!empty($profile['bio'])): ?>
          <p class="bio"><?= e($profile['bio']) ?></p>
        <?php endif; ?>

        <div class="seal" aria-hidden="true">
          <svg viewBox="0 0 40 40" width="28" height="28">
            <circle cx="20" cy="20" r="18" fill="none" stroke="currentColor" stroke-width="1.4"/>
            <circle cx="20" cy="20" r="12" fill="none" stroke="currentColor" stroke-width="1"/>
          </svg>
        </div>

        <nav class="links" data-button-style="<?= e($settings['button_style'] ?? 'soft') ?>" aria-label="Links">
          <?php if (empty($links)): ?>
            <p class="empty-state">Nenhum link publicado ainda.</p>
          <?php else: ?>
            <?php foreach ($links as $link): ?>
              <a class="link-item" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                <span class="link-icon"><?= icon_markup($link['icon']) ?></span>
                <span><?= e($link['label']) ?></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </nav>

        <footer class="card-footer">
          <span>cartão digital</span>
        </footer>

      </div>
    </main>
  </div>

</body>
</html>
