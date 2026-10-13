<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pix.php';

$pdo = get_pdo();
$profile  = get_profile($pdo);
$settings = get_settings($pdo);
$links    = array_values(array_filter(get_all_links($pdo), fn($l) => !empty($l['active'])));

/* Fontes */
$fontDisplayMap = [
    'newsreader' => "'Newsreader', Georgia, serif",
    'playfair'   => "'Playfair Display', Georgia, serif",
    'fraunces'   => "'Fraunces', Georgia, serif",
    'poppins'    => "'Poppins', system-ui, sans-serif",
];
$fontBodyMap = [
    'public' => "'Public Sans', system-ui, sans-serif",
    'inter'  => "'Inter', system-ui, sans-serif",
    'work'   => "'Work Sans', system-ui, sans-serif",
    'nunito' => "'Nunito Sans', system-ui, sans-serif",
];
$fontDisplay = $fontDisplayMap[$settings['font_display'] ?? 'newsreader'] ?? $fontDisplayMap['newsreader'];
$fontBody    = $fontBodyMap[$settings['font_body'] ?? 'public']         ?? $fontBodyMap['public'];

/* Avatar */
$avatarSrc = avatar_src($profile);   // URL do B2, link externo ou arquivo local antigo
$initials = !empty($profile['initials_override'])
    ? $profile['initials_override']
    : mb_substr(preg_replace('/\s+/', '', $profile['name'] ?? 'SN'), 0, 2);

/* Banner */
$bannerType   = $settings['banner_type'] ?? (empty($settings['banner_url']) ? 'none' : 'image');
$bannerUrl    = trim($settings['banner_url'] ?? '');
$bannerVideo  = trim($settings['banner_video_url'] ?? '');
$bannerSlides = [];
if (!empty($settings['banner_slides'])) {
    $decoded = json_decode($settings['banner_slides'], true);
    if (is_array($decoded)) $bannerSlides = array_values(array_filter($decoded));
}
$bannerSpeed  = (int) ($settings['banner_slide_speed'] ?? 4000);
$bannerHeight = $settings['banner_height'] ?? 'md';
$bannerOverlay= !empty($settings['banner_overlay']);

if ($bannerType === 'slideshow' && count($bannerSlides) < 2) $bannerType = $bannerUrl ? 'image' : 'none';
if ($bannerType === 'video' && $bannerVideo === '')          $bannerType = $bannerUrl ? 'image' : 'none';
if ($bannerType === 'image' && $bannerUrl === '')            $bannerType = 'none';
$hasBanner = $bannerType !== 'none';

/* Layout */
$avatarShape  = $settings['avatar_shape'] ?? 'circle';
$buttonStyle  = $settings['button_style'] ?? 'soft';
$layout       = $settings['layout'] ?? 'card';

/* PIX */
$pixShow    = !empty($settings['pix_show']) && (!empty($settings['pix_payload']) || !empty($settings['pix_key']));
$pixPayload = null;
$pixQr      = null;
if ($pixShow) {
    try {
        $pixPayload = pix_resolve_payload($settings);
        if (!$pixPayload) { $pixShow = false; } else { $pixQr = pix_qr_url($pixPayload, 300); }
    } catch (Throwable $e) { $pixShow = false; }
}

/* Ícone */
function render_icon(?string $icon): string
{
    if (!$icon) return '';
    $icon = trim($icon);
    if ($icon === '') return '';

    if (preg_match('#^https?://#i', $icon)) {
        return '<img class="link-icon-img" src="' . e($icon) . '" alt="" loading="lazy">';
    }

    $slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $icon));
    if ($slug === '') return '';

    $path = __DIR__ . '/assets/icons/' . $slug . '.svg';
    if (is_file($path)) {
        return '<span class="link-icon">' . file_get_contents($path) . '</span>';
    }

    $txt = mb_strtoupper(mb_substr($icon, 0, 2));
    return '<span class="link-icon link-icon-text">' . e($txt) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="<?= e($settings['bg_color'] ?? '#0F3D3E') ?>">

<title><?= e($profile['name'] ?? 'Cartão Digital') ?><?= !empty($profile['title']) ? ' — ' . e($profile['title']) : '' ?></title>

<meta name="description" content="<?= e($profile['bio'] ?? $profile['title'] ?? '') ?>">
<meta property="og:title" content="<?= e($profile['name'] ?? 'Cartão Digital') ?>">
<meta property="og:description" content="<?= e($profile['bio'] ?? $profile['title'] ?? '') ?>">
<meta property="og:type" content="profile">
<?php if ($avatarSrc): ?>
<meta property="og:image" content="<?= e($avatarSrc) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&family=Fraunces:wght@500;600&family=Poppins:wght@500;600&family=Inter:wght@400;500;600&family=Work+Sans:wght@400;500;600&family=Nunito+Sans:wght@400;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css">

<style>
:root {
  --font-display: <?= $fontDisplay ?>;
  --font-body: <?= $fontBody ?>;
}
</style>
</head>
<body class="public-body
             layout-<?= e($layout) ?>
             shape-<?= e($avatarShape) ?>
             btn-<?= e($buttonStyle) ?>
             <?= $hasBanner ? 'has-banner banner-' . e($bannerHeight) : '' ?>
             <?= $hasBanner && $bannerOverlay ? 'banner-overlay' : '' ?>"
      style="
        --bg-color: <?= e($settings['bg_color'] ?? '#0F3D3E') ?>;
        --card-color: <?= e($settings['card_color'] ?? '#FAF6ED') ?>;
        --text-color: <?= e($settings['text_color'] ?? '#1B2A28') ?>;
        --accent-color: <?= e($settings['accent_color'] ?? '#B08D57') ?>;
        <?= !empty($settings['bg_image']) ? '--bg-image: url(' . e($settings['bg_image']) . ');' : '' ?>
      ">

<div class="page-wrap">

  <?php if ($hasBanner): ?>
    <?php if ($bannerType === 'image'): ?>
      <div class="banner" style="background-image:url('<?= e(media_src($bannerUrl)) ?>')"></div>

    <?php elseif ($bannerType === 'video'): ?>
      <?php $ytId = youtube_id($bannerVideo); ?>
      <?php if ($ytId): ?>
        <div class="banner banner-video banner-yt">
          <iframe src="<?= e(youtube_embed_url($ytId)) ?>" title="Vídeo do banner" loading="lazy"
                  allow="autoplay; encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"
                  tabindex="-1" aria-hidden="true"></iframe>
        </div>
      <?php else: ?>
        <div class="banner banner-video">
          <video autoplay muted loop playsinline preload="metadata">
            <source src="<?= e(media_src($bannerVideo)) ?>" type="<?= e(video_mime($bannerVideo)) ?>">
          </video>
        </div>
      <?php endif; ?>

    <?php elseif ($bannerType === 'slideshow'): ?>
      <div class="banner banner-slideshow" data-speed="<?= (int) $bannerSpeed ?>">
        <?php foreach ($bannerSlides as $i => $url): ?>
          <div class="banner-slide <?= $i === 0 ? 'active' : '' ?>"
               style="background-image:url('<?= e(media_src($url)) ?>')"></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <main class="card <?= $hasBanner ? 'card-with-banner' : '' ?>">

    <header class="profile">
      <div class="avatar">
        <?php if ($avatarSrc): ?>
          <img src="<?= e($avatarSrc) ?>" alt="<?= e($profile['name'] ?? '') ?>" loading="lazy">
        <?php else: ?>
          <span class="avatar-initials"><?= e($initials) ?></span>
        <?php endif; ?>
      </div>

      <h1 class="name"><?= e($profile['name'] ?? 'Seu Nome') ?></h1>

      <?php if (!empty($profile['title'])): ?>
        <p class="title"><?= e($profile['title']) ?></p>
      <?php endif; ?>

      <?php if (!empty($profile['bio'])): ?>
        <p class="bio"><?= nl2br(e($profile['bio'])) ?></p>
      <?php endif; ?>
    </header>

    <section class="links">
      <?php foreach ($links as $link):
        $isFeatured = !empty($link['featured']);
        $cover     = $isFeatured ? media_src($link['banner_image'] ?? null) : '';
        $coverKind = $cover ? media_kind($cover) : '';
      ?>
        <a class="link-btn<?= $isFeatured ? ' link-featured' : '' ?><?= $cover ? ' has-cover' : '' ?>"
           href="<?= e($link['url']) ?>"
           target="_blank"
           rel="noopener noreferrer"
           <?= ($cover && $coverKind === 'image') ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
          <?php if ($coverKind === 'youtube'): ?>
            <span class="link-cover-media link-cover-yt" aria-hidden="true">
              <iframe src="<?= e(youtube_embed_url(youtube_id($cover))) ?>" title="" loading="lazy" tabindex="-1"
                      allow="autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </span>
          <?php elseif ($coverKind === 'video'): ?>
            <span class="link-cover-media" aria-hidden="true">
              <video autoplay muted loop playsinline preload="metadata">
                <source src="<?= e($cover) ?>" type="<?= e(video_mime($cover)) ?>">
              </video>
            </span>
          <?php endif; ?>
          <?php if ($cover): ?><span class="link-cover-overlay"></span><?php endif; ?>
          <?= render_icon($link['icon'] ?? null) ?>
          <span class="link-label"><?= e($link['label']) ?></span>
        </a>
      <?php endforeach; ?>

      <?php if ($pixShow): ?>
        <button type="button" class="link-btn link-pix" data-pix-open>
          <span class="link-icon link-icon-text" style="background: rgba(255,255,255,.25);">R$</span>
          <span class="link-label">Pagar com PIX</span>
        </button>
      <?php endif; ?>
    </section>

    <footer class="foot">
      <span>© <?= date('Y') ?> · <?= e($profile['name'] ?? '') ?></span>
    </footer>

  </main>
</div>

<?php if ($pixShow): ?>
<div class="pix-modal" id="pix-modal" hidden>
  <div class="pix-modal-backdrop" data-pix-close></div>
  <div class="pix-modal-box" role="dialog" aria-modal="true" aria-labelledby="pix-title">
    <button class="pix-close" type="button" data-pix-close aria-label="Fechar">✕</button>

    <h2 id="pix-title">Pagar com PIX</h2>
    <p class="pix-sub">Escaneie o QR Code ou copie o código abaixo.</p>

    <div class="pix-qr-wrap">
      <img src="<?= e($pixQr) ?>" alt="QR Code PIX">
    </div>

    <label class="pix-label" for="pix-code">Código copia-e-cola</label>
    <textarea id="pix-code" readonly rows="3"><?= e($pixPayload) ?></textarea>

    <button type="button" class="pix-copy" id="pix-copy-btn">Copiar código PIX</button>
    <p class="pix-hint" id="pix-hint"></p>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('pix-modal');
  if (!modal) return;

  const open  = () => { modal.hidden = false; document.body.style.overflow = 'hidden'; };
  const close = () => { modal.hidden = true;  document.body.style.overflow = ''; };

  document.querySelectorAll('[data-pix-open]').forEach(b => b.addEventListener('click', open));
  modal.querySelectorAll('[data-pix-close]').forEach(b => b.addEventListener('click', close));

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.hidden) close();
  });

  const btn  = document.getElementById('pix-copy-btn');
  const hint = document.getElementById('pix-hint');
  const code = document.getElementById('pix-code');

  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(code.value);
      hint.textContent = '✓ Código copiado!';
      btn.textContent  = '✓ Copiado';
      setTimeout(() => {
        hint.textContent = '';
        btn.textContent  = 'Copiar código PIX';
      }, 2000);
    } catch (err) {
      code.select();
      code.setSelectionRange(0, 99999);
      try {
        document.execCommand('copy');
        hint.textContent = '✓ Código copiado!';
      } catch (e) {
        hint.textContent = 'Não foi possível copiar. Selecione manualmente.';
      }
    }
  });
})();
</script>
<?php endif; ?>

<script>
(function () {
  const ss = document.querySelector('.banner-slideshow');
  if (!ss) return;
  const slides = ss.querySelectorAll('.banner-slide');
  if (slides.length < 2) return;
  const speed = parseInt(ss.dataset.speed || '4000', 10);
  let idx = 0;
  setInterval(() => {
    slides[idx].classList.remove('active');
    idx = (idx + 1) % slides.length;
    slides[idx].classList.add('active');
  }, speed);
})();
</script>

</body>
</html>