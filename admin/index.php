<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pix.php';

$admin = require_login();
$pdo = get_pdo();

// Aplica migrações pendentes sozinho (idempotente): banco antigo ganha as colunas novas sem precisar do upgrade.php
require_once __DIR__ . '/../includes/migrations.php';
try { run_all_pending_migrations($pdo); } catch (Throwable $e) { error_log('migrations: ' . $e->getMessage()); }

/** Converte "8M" / "512K" / "1G" do php.ini em bytes. */
/** True se o navegador enviou um arquivo neste campo (mesmo que o PHP tenha rejeitado por tamanho). */
function file_was_sent(?array $file): bool {
    return $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function ini_bytes(string $v): int {
    $v = trim($v); if ($v === '') return 0;
    $n = (float) $v;
    switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; }
    return (int) $n;
}

// Garante pasta de uploads
$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// -----------------------------------------------------------------
// POST (PRG)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Arquivo maior que post_max_size: o PHP descarta TODO o formulário (inclusive o token)
    $postMax = ini_bytes((string) ini_get('post_max_size'));
    if (empty($_POST) && empty($_FILES) && $postMax > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMax) {
        header('Location: index.php?type=error&msg=' . rawurlencode(
            'O arquivo enviado é maior que o limite do servidor (' . ini_get('post_max_size') . '). Use um arquivo menor ou um link.'
        ));
        exit;
    }
    if (!verify_csrf()) {
        header('Location: index.php?type=error&msg=' . rawurlencode('Sessão expirada, tente novamente.'));
        exit;
    }

    $action = $_POST['form_action'] ?? '';
    $msg = '';
    $type = 'success';

    try {
        switch ($action) {

            case 'save_profile':
                $avatar_url = trim($_POST['avatar_url'] ?? '');

                // Foto atual: só muda se enviar outra ou marcar "remover" (antes era apagada a cada salvamento)
                $q = $pdo->prepare('SELECT avatar_file FROM ' . table('profile') . ' WHERE id = 1');
                $q->execute();
                $currentAvatar = $q->fetchColumn() ?: null;
                $avatar_file   = $currentAvatar;

                if (!empty($_FILES['avatar_upload']['tmp_name'])) {
                    $result = upload_image($_FILES['avatar_upload']);
                    if ($result['success']) $avatar_file = $result['filename'];
                    else { $msg = $result['error']; $type = 'error'; break; }
                } elseif (!empty($_POST['remove_avatar'])) {
                    $avatar_file = null;
                }
                $sigla = strtoupper(trim($_POST['initials_override'] ?? ''));
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . table('profile') . ' (id, name, title, bio, avatar_url, avatar_file, initials_override)
                     VALUES (1, ?, ?, ?, ?, ?, ?)
                     ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, title = EXCLUDED.title,
                       bio = EXCLUDED.bio, avatar_url = EXCLUDED.avatar_url,
                       avatar_file = EXCLUDED.avatar_file, initials_override = EXCLUDED.initials_override'
                );
                $stmt->execute([
                    trim($_POST['name'] ?? ''),
                    trim($_POST['title'] ?? ''),
                    trim($_POST['bio'] ?? ''),
                    $avatar_url ?: null,
                    $avatar_file,
                    $sigla !== '' ? substr($sigla, 0, 4) : null,
                ]);
                if ($currentAvatar && $currentAvatar !== $avatar_file) {
                    // arquivo antigo do disco guarda só o nome; normaliza para "uploads/nome"
                    $oldRef = preg_match('~^https?://~i', $currentAvatar) ? $currentAvatar : 'uploads/' . basename($currentAvatar);
                    delete_unused_media($pdo, [$oldRef]);
                }
                $msg = 'Perfil salvo.';
                break;

            case 'save_theme':
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . table('settings') . ' (id, bg_color, bg_image, card_color, text_color, accent_color,
                        font_display, font_body, avatar_shape, button_style, layout)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON CONFLICT (id) DO UPDATE SET
                        bg_color = EXCLUDED.bg_color, bg_image = EXCLUDED.bg_image, card_color = EXCLUDED.card_color,
                        text_color = EXCLUDED.text_color, accent_color = EXCLUDED.accent_color,
                        font_display = EXCLUDED.font_display, font_body = EXCLUDED.font_body,
                        avatar_shape = EXCLUDED.avatar_shape, button_style = EXCLUDED.button_style,
                        layout = EXCLUDED.layout'
                );
                $stmt->execute([
                    $_POST['bg_color'] ?? '#0F3D3E',
                    trim($_POST['bg_image'] ?? '') ?: null,
                    $_POST['card_color'] ?? '#FAF6ED',
                    $_POST['text_color'] ?? '#1B2A28',
                    $_POST['accent_color'] ?? '#B08D57',
                    $_POST['font_display'] ?? 'newsreader',
                    $_POST['font_body'] ?? 'public',
                    $_POST['avatar_shape'] ?? 'circle',
                    $_POST['button_style'] ?? 'soft',
                    $_POST['layout'] ?? 'card',
                ]);
                $msg = 'Aparência salva.';
                break;

            case 'save_banner_advanced':
                $bType = $_POST['banner_type'] ?? 'none';
                if (!in_array($bType, ['none', 'image', 'video', 'slideshow'], true)) $bType = 'none';

                // Valores antigos (para apagar do disco o que deixar de ser usado)
                $old = [];
                try { $old = $pdo->query('SELECT banner_url, banner_video_url, banner_slides FROM ' . table('settings') . ' WHERE id = 1')->fetch() ?: []; } catch (Throwable $e) {}
                $oldRefs = [$old['banner_url'] ?? null, $old['banner_video_url'] ?? null];
                $oldSlides = json_decode((string) ($old['banner_slides'] ?? ''), true);
                if (is_array($oldSlides)) $oldRefs = array_merge($oldRefs, $oldSlides);

                $upErrors = [];
                $newFiles = [];   // enviados agora (removidos se algo falhar)
                $pick = function (?array $file, string $kind, string $prefix, ?string $fallback) use (&$upErrors, &$newFiles) {
                    if (!file_was_sent($file)) return $fallback;
                    $r = save_media_upload($file, $kind, $prefix);
                    if ($r['success']) { $newFiles[] = $r['ref']; return $r['ref']; }
                    $upErrors[] = ($file['name'] ?? 'arquivo') . ': ' . $r['error'];
                    return $fallback;
                };

                // Imagem única e vídeo: arquivo enviado tem prioridade sobre o link digitado
                $bannerUrl   = $pick($_FILES['banner_file'] ?? null,       'image', 'banner', clean_media_ref($_POST['banner_url'] ?? ''));
                $bannerVideo = $pick($_FILES['banner_video_file'] ?? null, 'video', 'banner', clean_media_ref($_POST['banner_video_url'] ?? ''));

                // Slideshow: cada linha tem um link e (opcional) um arquivo, na mesma ordem
                $slides = [];
                $slideLinks = is_array($_POST['banner_slides'] ?? null) ? $_POST['banner_slides'] : [];
                $sf = $_FILES['banner_slide_files'] ?? null;
                foreach ($slideLinks as $i => $link) {
                    $one = null;
                    if ($sf && isset($sf['tmp_name'][$i])) {
                        $one = ['name' => $sf['name'][$i], 'type' => $sf['type'][$i], 'tmp_name' => $sf['tmp_name'][$i],
                                'error' => $sf['error'][$i], 'size' => $sf['size'][$i]];
                    }
                    $ref = $pick($one, 'image', 'slide', clean_media_ref($link));
                    if ($ref) $slides[] = $ref;
                }

                // Link digitado mas inválido: avisa em vez de apagar o banner sem dizer nada
                foreach ([['banner_url', 'da imagem'], ['banner_video_url', 'do vídeo']] as [$fld, $nm]) {
                    if (trim((string) ($_POST[$fld] ?? '')) !== '' && clean_media_ref($_POST[$fld]) === null) {
                        $upErrors[] = "O link $nm é inválido (use um endereço completo começando com https://).";
                    }
                }
                foreach ($slideLinks as $sl) {
                    if (trim((string) $sl) !== '' && clean_media_ref($sl) === null) {
                        $upErrors[] = 'Um dos links do slideshow é inválido (use https://…).';
                        break;
                    }
                }

                if ($upErrors) {
                    delete_unused_media($pdo, $newFiles); // ainda não estão no banco: some do disco/B2
                    $msg = 'Nada foi salvo. ' . implode(' | ', $upErrors); $type = 'error';
                    break;
                }
                $slidesJson = $slides ? json_encode($slides, JSON_UNESCAPED_SLASHES) : null;

                $speed = (int) ($_POST['banner_slide_speed'] ?? 4000);
                if ($speed < 1000)  $speed = 1000;
                if ($speed > 30000) $speed = 30000;
                $height = in_array($_POST['banner_height'] ?? 'md', ['sm', 'md', 'lg'], true) ? $_POST['banner_height'] : 'md';

                $stmt = $pdo->prepare(
                    'INSERT INTO ' . table('settings') . '
                        (id, banner_type, banner_url, banner_video_url, banner_slides, banner_slide_speed, banner_height, banner_overlay)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                     ON CONFLICT (id) DO UPDATE SET
                        banner_type = EXCLUDED.banner_type,
                        banner_url = EXCLUDED.banner_url,
                        banner_video_url = EXCLUDED.banner_video_url,
                        banner_slides = EXCLUDED.banner_slides,
                        banner_slide_speed = EXCLUDED.banner_slide_speed,
                        banner_height = EXCLUDED.banner_height,
                        banner_overlay = EXCLUDED.banner_overlay'
                );
                $stmt->execute([
                    $bType, $bannerUrl, $bannerVideo, $slidesJson, $speed, $height,
                    isset($_POST['banner_overlay']) ? 1 : 0,
                ]);
                delete_unused_media($pdo, $oldRefs);
                $msg = 'Banner salvo.';
                break;

            case 'save_pix':
                $rawPayload = trim($_POST['pix_payload'] ?? '');
                $pixShow    = isset($_POST['pix_show']) ? 1 : 0;
                $pixPayload = null;

                if ($rawPayload !== '') {
                    // Caminho recomendado: Pix Copia e Cola colado do app do banco
                    $r = pix_parse_payload($rawPayload);
                    if (!$r['ok']) { $msg = $r['error']; $type = 'error'; break; }
                    $pixPayload = $r['payload'];
                    $pixKey     = $r['key'];
                    $pixName    = $r['name'];
                    $pixCity    = $r['city'];
                } else {
                    // Alternativa: digitar chave + nome + cidade
                    $pixKey  = pix_normalize_key($_POST['pix_key'] ?? '');
                    $pixName = trim($_POST['pix_name'] ?? '');
                    $pixCity = trim($_POST['pix_city'] ?? '');
                    if ($pixShow && $pixKey === '') {
                        $msg = 'Cole o Pix Copia e Cola (ou digite a chave) para mostrar o botão no cartão.';
                        $type = 'error'; break;
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO ' . table('settings') . ' (id, pix_key, pix_name, pix_city, pix_show, pix_payload)
                     VALUES (1, ?, ?, ?, ?, ?)
                     ON CONFLICT (id) DO UPDATE SET
                        pix_key = EXCLUDED.pix_key, pix_name = EXCLUDED.pix_name,
                        pix_city = EXCLUDED.pix_city, pix_show = EXCLUDED.pix_show,
                        pix_payload = EXCLUDED.pix_payload'
                );
                $stmt->execute([
                    $pixKey !== '' ? $pixKey : null,
                    $pixName !== '' ? $pixName : null,
                    $pixCity !== '' ? $pixCity : null,
                    $pixShow,
                    $pixPayload,
                ]);
                $msg = 'PIX salvo.';
                break;

            case 'add_link':
                $label = trim($_POST['label'] ?? '');
                $url = trim($_POST['url'] ?? '');
                if ($label === '' || $url === '') {
                    $msg = 'Preencha o texto e a URL do link.'; $type = 'error'; break;
                }
                $cover = clean_media_ref($_POST['banner_image'] ?? '');
                if (file_was_sent($_FILES['banner_image_file'] ?? null)) {
                    $r = save_media_upload($_FILES['banner_image_file'], 'image', 'link');
                    if (!$r['success']) { $msg = 'Banner do botão: ' . $r['error']; $type = 'error'; break; }
                    $cover = $r['ref'];
                }
                $nextPos = (int) $pdo->query('SELECT COALESCE(MAX("position"), -1) + 1 AS p FROM ' . table('links'))->fetch()['p'];
                $stmt = $pdo->prepare('INSERT INTO ' . table('links') . ' (label, url, icon, "position", active, featured, banner_image) VALUES (?, ?, ?, ?, 1, ?, ?)');
                $stmt->execute([$label, $url, trim($_POST['icon'] ?? '') ?: null, $nextPos, isset($_POST['featured']) ? 1 : 0, $cover]);
                $msg = 'Link adicionado.';
                break;

            case 'update_link':
                $id = (int) ($_POST['link_id'] ?? 0);
                $oldCover = null;
                $q = $pdo->prepare('SELECT banner_image FROM ' . table('links') . ' WHERE id = ?');
                $q->execute([$id]);
                $oldCover = $q->fetchColumn() ?: null;

                if (trim($_POST['label'] ?? '') === '' || trim($_POST['url'] ?? '') === '') {
                    $msg = 'O texto e a URL do link não podem ficar vazios.'; $type = 'error'; break;
                }
                $cover = clean_media_ref($_POST['banner_image'] ?? '');
                if (trim((string) ($_POST['banner_image'] ?? '')) !== '' && $cover === null) {
                    $msg = 'O link do banner do botão é inválido (use https://…).'; $type = 'error'; break;
                }
                if (file_was_sent($_FILES['banner_image_file'] ?? null)) {
                    $r = save_media_upload($_FILES['banner_image_file'], 'image', 'link');
                    if (!$r['success']) { $msg = 'Banner do botão: ' . $r['error']; $type = 'error'; break; }
                    $cover = $r['ref'];
                }
                $stmt = $pdo->prepare('UPDATE ' . table('links') . ' SET label = ?, url = ?, icon = ?, active = ?, featured = ?, banner_image = ? WHERE id = ?');
                $stmt->execute([
                    trim($_POST['label'] ?? ''),
                    trim($_POST['url'] ?? ''),
                    trim($_POST['icon'] ?? '') ?: null,
                    isset($_POST['active']) ? 1 : 0,
                    isset($_POST['featured']) ? 1 : 0,
                    $cover,
                    $id,
                ]);
                if ($oldCover && $oldCover !== $cover) delete_unused_media($pdo, [$oldCover]);
                $msg = 'Link atualizado.';
                break;

            case 'delete_link':
                $id = (int) ($_POST['link_id'] ?? 0);
                $q = $pdo->prepare('SELECT banner_image FROM ' . table('links') . ' WHERE id = ?');
                $q->execute([$id]);
                $oldCover = $q->fetchColumn() ?: null;
                $pdo->prepare('DELETE FROM ' . table('links') . ' WHERE id = ?')->execute([$id]);
                if ($oldCover) delete_unused_media($pdo, [$oldCover]);
                $msg = 'Link excluído.';
                break;

            case 'move_link_up':
            case 'move_link_down':
                $id = (int) ($_POST['link_id'] ?? 0);
                $all = get_all_links($pdo);
                $idx = null;
                foreach ($all as $i => $l) if ((int) $l['id'] === $id) { $idx = $i; break; }
                if ($idx !== null) {
                    $swapIdx = $action === 'move_link_up' ? $idx - 1 : $idx + 1;
                    if (isset($all[$swapIdx])) {
                        $a = $all[$idx]; $b = $all[$swapIdx];
                        $pdo->beginTransaction();
                        $pdo->prepare('UPDATE ' . table('links') . ' SET "position" = ? WHERE id = ?')->execute([$b['position'], $a['id']]);
                        $pdo->prepare('UPDATE ' . table('links') . ' SET "position" = ? WHERE id = ?')->execute([$a['position'], $b['id']]);
                        $pdo->commit();
                    }
                }
                break;
        }
    } catch (Throwable $e) {
        $msg = 'Erro ao salvar: ' . $e->getMessage();
        $type = 'error';
    }

    header('Location: index.php' . ($msg ? '?type=' . $type . '&msg=' . rawurlencode($msg) : ''));
    exit;
}

// -----------------------------------------------------------------
// Dados
// -----------------------------------------------------------------
$profile  = get_profile($pdo);
$settings = get_settings($pdo);
$links    = get_all_links($pdo);
$csrf     = csrf_token();
$flashMsg = $_GET['msg'] ?? '';
$flashType= $_GET['type'] ?? 'success';

// PIX preview inicial
$initialPixPayload = null;
$initialPixQr = null;
try {
    $initialPixPayload = pix_resolve_payload($settings);
    if ($initialPixPayload) $initialPixQr = pix_qr_url($initialPixPayload, 260);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Admin — Cartão Digital</title>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-shell">

  <header class="admin-topbar">
    <div class="admin-brand">
      <span class="admin-logo">◆</span>
      <div>
        <h1>Painel do cartão</h1>
        <p class="admin-sub">Gerencie perfil, aparência, links e PIX</p>
      </div>
    </div>
    <div class="admin-topbar-actions">
      <a class="btn btn-ghost" href="../index.php" target="_blank">Ver cartão ↗</a>
      <a class="btn btn-ghost" href="logout.php">Sair</a>
    </div>
  </header>

  <?php if ($flashMsg): ?>
    <div class="msg <?= e($flashType) ?>" style="white-space: pre-line;"><?= e($flashMsg) ?></div>
  <?php endif; ?>

  <nav class="tabs">
    <button class="tab-btn active" data-tab="perfil">👤 <span>Perfil</span></button>
    <button class="tab-btn" data-tab="aparencia">🎨 <span>Aparência</span></button>
    <button class="tab-btn" data-tab="banner">🖼️ <span>Banner</span></button>
    <button class="tab-btn" data-tab="links">🔗 <span>Links</span></button>
    <button class="tab-btn" data-tab="pix">💠 <span>PIX</span></button>
  </nav>

  <!-- ================= PERFIL ================= -->
  <section class="tab-panel active" data-panel="perfil">
    <div class="panel">
      <h2>Perfil</h2>
      <p class="panel-desc">Informações exibidas no topo do seu cartão digital.</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="form_action" value="save_profile">

        <div class="form-grid">
          <div class="field span-2">
            <label for="p-name">Nome</label>
            <input type="text" id="p-name" name="name" placeholder="Seu nome completo" value="<?= e($profile['name'] ?? '') ?>" required>
          </div>

          <div class="field span-2">
            <label for="p-title">Profissão / Frase de efeito</label>
            <input type="text" id="p-title" name="title" value="<?= e($profile['title'] ?? '') ?>" placeholder="Sua profissão ou frase de efeito">
          </div>

          <div class="field span-2">
            <label for="p-bio">Bio curta</label>
            <textarea id="p-bio" name="bio" rows="3" placeholder="Duas ou três linhas sobre você"><?= e($profile['bio'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label for="p-avatar-upload">Foto do avatar</label>
            <input type="file" id="p-avatar-upload" name="avatar_upload" accept="image/*">
            <?php if (!empty($profile['avatar_file'])): ?>
              <p class="small-note">✓ Foto atual: <?= e($profile['avatar_file']) ?> — ela é mantida ao salvar, a menos que você envie outra.</p>
              <label class="check-inline">
                <input type="checkbox" name="remove_avatar" value="1">
                <span>Remover a foto atual</span>
              </label>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="p-initials">Sigla (até 4 letras)</label>
            <input type="text" id="p-initials" name="initials_override" maxlength="4" style="text-transform:uppercase" placeholder="Ex: JF" value="<?= e($profile['initials_override'] ?? '') ?>">
            <p class="small-note">Usada enquanto não houver foto.</p>
          </div>

          <div class="field span-2">
            <label for="p-avatar-url">Ou URL da foto (opcional)</label>
            <input type="url" id="p-avatar-url" name="avatar_url" placeholder="https://..." value="<?= e($profile['avatar_url'] ?? '') ?>">
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Salvar perfil</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ================= APARÊNCIA ================= -->
  <section class="tab-panel" data-panel="aparencia">
    <div class="panel">
      <h2>Aparência</h2>
      <p class="panel-desc">Cores, tipografia, formato e layout.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="form_action" value="save_theme">

        <h3 class="sub-title">Cores</h3>
        <div class="form-grid">
          <div class="field">
            <label for="t-bg">Cor de fundo</label>
            <input type="color" id="t-bg" name="bg_color" value="<?= e($settings['bg_color'] ?? '#0F3D3E') ?>">
          </div>
          <div class="field">
            <label for="t-card">Cor do cartão</label>
            <input type="color" id="t-card" name="card_color" value="<?= e($settings['card_color'] ?? '#FAF6ED') ?>">
          </div>
          <div class="field">
            <label for="t-text">Cor do texto</label>
            <input type="color" id="t-text" name="text_color" value="<?= e($settings['text_color'] ?? '#1B2A28') ?>">
          </div>
          <div class="field">
            <label for="t-accent">Cor de destaque</label>
            <input type="color" id="t-accent" name="accent_color" value="<?= e($settings['accent_color'] ?? '#B08D57') ?>">
          </div>
          <div class="field span-2">
            <label for="t-bg-image">URL da imagem de fundo (opcional)</label>
            <input type="url" id="t-bg-image" name="bg_image" placeholder="https://..." value="<?= e($settings['bg_image'] ?? '') ?>">
          </div>
        </div>

        <h3 class="sub-title">Tipografia</h3>
        <div class="form-grid">
          <div class="field">
            <label for="t-font-display">Fonte do nome/título</label>
            <select id="t-font-display" name="font_display">
              <?php foreach ([
                'newsreader' => 'Newsreader (serifada, editorial)',
                'playfair'   => 'Playfair Display (serifada, clássica)',
                'fraunces'   => 'Fraunces (serifada, expressiva)',
                'poppins'    => 'Poppins (geométrica, moderna)',
              ] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['font_display'] ?? 'newsreader') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="t-font-body">Fonte do corpo</label>
            <select id="t-font-body" name="font_body">
              <?php foreach ([
                'public' => 'Public Sans', 'inter' => 'Inter',
                'work' => 'Work Sans', 'nunito' => 'Nunito Sans',
              ] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['font_body'] ?? 'public') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <h3 class="sub-title">Componentes</h3>
        <div class="form-grid">
          <div class="field">
            <label for="t-avatar-shape">Formato do avatar</label>
            <select id="t-avatar-shape" name="avatar_shape">
              <?php foreach (['circle' => 'Círculo', 'rounded' => 'Quadrado arredondado', 'square' => 'Quadrado'] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['avatar_shape'] ?? 'circle') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="t-button-style">Estilo dos botões</label>
            <select id="t-button-style" name="button_style">
              <?php foreach (['soft' => 'Retângulo suave', 'pill' => 'Pílula', 'minimal' => 'Minimalista'] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['button_style'] ?? 'soft') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field span-2">
            <label for="t-layout">Layout da página</label>
            <select id="t-layout" name="layout">
              <?php foreach (['card' => 'Cartão flutuante', 'full' => 'Página cheia (estilo Linktree)'] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['layout'] ?? 'card') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Salvar aparência</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ================= BANNER ================= -->
  <section class="tab-panel" data-panel="banner">
    <div class="panel">
      <h2>Banner</h2>
      <p class="panel-desc">
        Imagem, vídeo ou slideshow no topo do cartão. Você pode <strong>enviar o arquivo</strong> direto daqui ou colar um <strong>link</strong>.
      </p>

      <?php
      // Limite real do servidor (o menor entre upload_max_filesize e post_max_size)
      $srvLimit = min(ini_bytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, ini_bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
      $srvLimitTxt = $srvLimit === PHP_INT_MAX ? '' : round($srvLimit / 1024 / 1024, 1) . 'MB';
      $vidMax = defined('UPLOAD_VIDEO_MAX_SIZE') ? UPLOAD_VIDEO_MAX_SIZE : 20 * 1024 * 1024;
      $bType = $settings['banner_type'] ?? (empty($settings['banner_url']) ? 'none' : 'image');
      $bSlides = [];
      if (!empty($settings['banner_slides'])) {
          $decoded = json_decode($settings['banner_slides'], true);
          if (is_array($decoded)) $bSlides = $decoded;
      }
      ?>

      <form method="post" id="banner-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="form_action" value="save_banner_advanced">

        <h3 class="sub-title">Tipo de banner</h3>
        <div class="banner-type-tabs">
          <?php foreach ([
            'none'      => '🚫 Sem banner',
            'image'     => '🖼️ Imagem',
            'video'     => '🎬 Vídeo',
            'slideshow' => '🎞️ Slideshow',
          ] as $val => $label): ?>
            <label class="banner-type-opt <?= $bType === $val ? 'active' : '' ?>">
              <input type="radio" name="banner_type" value="<?= e($val) ?>" <?= $bType === $val ? 'checked' : '' ?>>
              <span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <!-- Imagem -->
        <div class="banner-pane" data-pane="image" style="<?= $bType === 'image' ? '' : 'display:none' ?>">
          <div class="field">
            <label for="banner_url">Imagem do banner</label>
            <div class="media-field">
              <input type="text" id="banner_url" name="banner_url" placeholder="Cole um link https://… ou envie um arquivo"
                     value="<?= e($settings['banner_url'] ?? '') ?>" autocomplete="off">
              <label class="btn-file">📁 Enviar
                <input type="file" id="banner_file" name="banner_file" accept="image/jpeg,image/png,image/webp,image/gif">
              </label>
            </div>
            <p class="small-note file-name" data-for="banner_file"></p>
            <p class="small-note">JPG, PNG, WebP ou GIF, até <?= round((defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880) / 1048576, 1) ?>MB. Se enviar um arquivo, ele vale no lugar do link.</p>
          </div>
        </div>

        <!-- Vídeo -->
        <div class="banner-pane" data-pane="video" style="<?= $bType === 'video' ? '' : 'display:none' ?>">
          <div class="field">
            <label for="banner_video_url">Vídeo do banner (MP4, WebM ou YouTube)</label>
            <div class="media-field">
              <input type="text" id="banner_video_url" name="banner_video_url" placeholder="Link do YouTube, link https://…/video.mp4 ou envie um arquivo"
                     value="<?= e($settings['banner_video_url'] ?? '') ?>" autocomplete="off">
              <label class="btn-file">📁 Enviar
                <input type="file" id="banner_video_file" name="banner_video_file" accept="video/mp4,video/webm">
              </label>
            </div>
            <p class="small-note file-name" data-for="banner_video_file"></p>
            <p class="small-note">Aceita <strong>link do YouTube</strong> (watch, youtu.be ou shorts — o vídeo precisa permitir incorporação; “não listado” funciona). Roda em loop, sem som, autoplay. Arquivo até <?= round($vidMax / 1048576) ?>MB<?= $srvLimitTxt ? ' (limite do seu servidor: ' . e($srvLimitTxt) . ')' : '' ?>. Recomendado até 5MB para carregar rápido.</p>
          </div>
        </div>

        <!-- Slideshow -->
        <div class="banner-pane" data-pane="slideshow" style="<?= $bType === 'slideshow' ? '' : 'display:none' ?>">
          <div class="field">
            <label>Imagens do slideshow</label>
            <p class="small-note" style="margin-bottom:8px">Em cada linha: cole um link <strong>ou</strong> clique em 📁 para enviar a imagem. Use pelo menos 2.</p>
            <div id="slides-list">
              <?php $slidesToShow = $bSlides ?: ['']; foreach ($slidesToShow as $i => $sl): ?>
                <div class="slide-row">
                  <span class="slide-num"><?= $i + 1 ?></span>
                  <span class="slide-thumb"></span>
                  <input type="text" name="banner_slides[]" value="<?= e($sl) ?>" placeholder="Link https://… ou envie →" autocomplete="off">
                  <label class="btn-file btn-file-sm" title="Enviar imagem">📁
                    <input type="file" name="banner_slide_files[]" accept="image/jpeg,image/png,image/webp,image/gif">
                  </label>
                  <button type="button" class="icon-btn slide-remove" title="Remover">✕</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost" id="slide-add" style="background:#0F3D3E;margin-top:10px">
              + Adicionar slide
            </button>
          </div>

          <div class="field">
            <label for="banner_slide_speed">Velocidade da transição</label>
            <select id="banner_slide_speed" name="banner_slide_speed">
              <?php foreach ([2000 => 'Rápida (2s)', 4000 => 'Normal (4s)', 6000 => 'Lenta (6s)', 8000 => 'Muito lenta (8s)'] as $ms => $label): ?>
                <option value="<?= $ms ?>" <?= (int)($settings['banner_slide_speed'] ?? 4000) === $ms ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <h3 class="sub-title">Configurações gerais</h3>
        <div class="form-grid">
          <div class="field">
            <label for="banner_height">Altura do banner</label>
            <select id="banner_height" name="banner_height">
              <?php foreach (['sm' => 'Pequena (120px)', 'md' => 'Média (180px)', 'lg' => 'Grande (240px)'] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($settings['banner_height'] ?? 'md') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="switch-inline">
              <input type="checkbox" name="banner_overlay" value="1" <?= !empty($settings['banner_overlay']) ? 'checked' : '' ?>>
              <span>Escurecer banner</span>
            </label>
          </div>
        </div>

        <h3 class="sub-title">Pré-visualização</h3>
        <div class="banner-preview-wrap">
          <div id="banner-preview" class="banner-preview"></div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Salvar banner</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ================= LINKS ================= -->
  <section class="tab-panel" data-panel="links">
    <div class="panel">
      <h2>Links</h2>
      <p class="panel-desc">
        Clique no quadradinho do ícone para escolher um da lista, ou cole a URL de uma imagem personalizada.
        Os botões ↑ ↓ reordenam a lista. Abra <strong>⭐ Destaque</strong> em qualquer link para deixá-lo maior
        e, se quiser, colocar um <strong>banner</strong> (envie a imagem ou cole um link).
      </p>

      <?php if (empty($links)): ?>
        <p class="empty-state">Nenhum link cadastrado ainda.</p>
      <?php else: ?>
        <div class="links-list">
          <?php foreach ($links as $idx => $link): ?>
            <div class="link-row">
              <form method="post" class="link-main-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                <input type="hidden" name="form_action" value="update_link">

                <label class="switch" title="Ativo">
                  <input type="checkbox" name="active" <?= $link['active'] ? 'checked' : '' ?>>
                  <span class="track"></span>
                </label>

                <input class="l-label" type="text" name="label" value="<?= e($link['label']) ?>" placeholder="Rótulo">

                <div class="icon-field">
                  <input class="l-icon" type="hidden" name="icon" value="<?= e($link['icon'] ?? '') ?>">
                  <button type="button" class="icon-preview icon-trigger" title="Escolher ícone" aria-label="Escolher ícone"></button>
                </div>

                <input class="l-url" type="url" name="url" value="<?= e($link['url']) ?>" placeholder="https://...">

                <button type="submit" class="btn btn-primary btn-save-row" title="Salvar alterações deste link">Salvar</button>

                <details class="link-extra" <?= !empty($link['featured']) ? 'open' : '' ?>>
                  <summary>⭐ Destaque<?= !empty($link['featured']) ? ' <em>(ativo)</em>' : '' ?></summary>
                  <div class="link-extra-body">
                    <label class="check-inline">
                      <input type="checkbox" name="featured" <?= !empty($link['featured']) ? 'checked' : '' ?>>
                      <span>Destacar este botão (maior e com cor de destaque)</span>
                    </label>
                    <div class="media-field">
                      <span class="cover-thumb" data-src="<?= e($link['banner_image'] ?? '') ?>"></span>
                      <input type="text" name="banner_image" class="cover-input" value="<?= e($link['banner_image'] ?? '') ?>"
                             placeholder="Banner do botão: link (imagem, vídeo ou YouTube) ou envie uma imagem →" autocomplete="off">
                      <label class="btn-file btn-file-sm" title="Enviar imagem">📁
                        <input type="file" name="banner_image_file" class="cover-file" accept="image/jpeg,image/png,image/webp,image/gif">
                      </label>
                    </div>
                    <p class="small-note">O banner só aparece se o botão estiver destacado. Aceita imagem (link ou envio), link de <strong>vídeo .mp4/.webm</strong> ou link do <strong>YouTube</strong> (toca sem som, em loop). Para tirar o banner, apague o campo e salve.</p>
                    <div><button type="submit" class="btn btn-primary btn-save-row">Salvar destaque</button></div>
                  </div>
                </details>
              </form>

              <div class="actions">
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                  <button type="submit" name="form_action" value="move_link_up" class="icon-btn" title="Mover para cima" <?= $idx === 0 ? 'disabled' : '' ?>>↑</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                  <button type="submit" name="form_action" value="move_link_down" class="icon-btn" title="Mover para baixo" <?= $idx === count($links) - 1 ? 'disabled' : '' ?>>↓</button>
                </form>
                <form method="post" onsubmit="return confirm('Excluir o link &quot;<?= e(addslashes($link['label'])) ?>&quot;?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                  <input type="hidden" name="form_action" value="delete_link">
                  <button type="submit" class="icon-btn" title="Excluir">✕</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3 class="sub-title">Adicionar novo link</h3>
      <form class="add-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="form_action" value="add_link">

        <div class="form-grid">
          <div class="field">
            <label for="l-label">Texto do link</label>
            <input type="text" id="l-label" name="label" placeholder="Ex: Instagram" required>
          </div>

          <div class="field">
            <label id="l-icon-label">Ícone</label>
            <div class="icon-field">
              <input type="hidden" id="l-icon" name="icon" value="">
              <button type="button" class="icon-preview icon-trigger" title="Escolher ícone" aria-labelledby="l-icon-label"></button>
            </div>
          </div>

          <div class="field span-2">
            <label for="l-url">URL</label>
            <input type="url" id="l-url" name="url" placeholder="https://..." required>
          </div>

          <div class="field span-2">
            <details class="link-extra">
              <summary>⭐ Destaque (opcional)</summary>
              <div class="link-extra-body">
                <label class="check-inline">
                  <input type="checkbox" name="featured">
                  <span>Destacar este botão (maior e com cor de destaque)</span>
                </label>
                <div class="media-field">
                  <span class="cover-thumb" data-src=""></span>
                  <input type="text" name="banner_image" class="cover-input" placeholder="Banner do botão: link (imagem, vídeo ou YouTube) ou envie uma imagem →" autocomplete="off">
                  <label class="btn-file btn-file-sm" title="Enviar imagem">📁
                    <input type="file" name="banner_image_file" class="cover-file" accept="image/jpeg,image/png,image/webp,image/gif">
                  </label>
                </div>
              </div>
            </details>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Adicionar link</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ================= PIX ================= -->
  <section class="tab-panel" data-panel="pix">
    <div class="panel">
      <h2>PIX</h2>
      <p class="panel-desc">
        Exibe um botão "Pagar com PIX" no cartão. Ao clicar, abre um modal com o QR Code
        e o código copia-e-cola.
      </p>

      <div class="pix-grid">
        <form method="post" id="pix-preview-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="form_action" value="save_pix">

          <div class="form-grid">
            <div class="field span-2">
              <label for="pix_payload">Pix Copia e Cola</label>
              <textarea id="pix_payload" name="pix_payload" rows="4" spellcheck="false" autocomplete="off"
                        placeholder="00020126…6304ABCD"><?= e($settings['pix_payload'] ?? '') ?></textarea>
              <p class="small-note">
                No app do seu banco, abra <strong>Pix → Receber → Copia e Cola</strong> (ou "Meu QR Code"),
                copie o código e cole aqui. Ele é usado exatamente como o banco gerou.
              </p>
              <p class="pix-status" id="pix-status" hidden></p>
            </div>

            <div class="field span-2">
              <details class="link-extra" <?= (empty($settings['pix_payload']) && !empty($settings['pix_key'])) ? 'open' : '' ?>>
                <summary>Não tenho o código — prefiro digitar a chave</summary>
                <div class="link-extra-body">
                  <div class="form-grid">
                    <div class="field span-2">
                      <label for="pix_key">Chave PIX</label>
                      <input type="text" id="pix_key" name="pix_key" placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"
                             value="<?= e(empty($settings['pix_payload']) ? ($settings['pix_key'] ?? '') : '') ?>">
                      <p class="small-note">Telefone: use +5511999999999. CPF/CNPJ: pode digitar com ou sem pontos. Chave aleatória: cole o código completo.</p>
                    </div>
                    <div class="field">
                      <label for="pix_name">Nome do recebedor</label>
                      <input type="text" id="pix_name" name="pix_name" maxlength="25" placeholder="Seu nome"
                             value="<?= e(empty($settings['pix_payload']) ? ($settings['pix_name'] ?? '') : '') ?>">
                    </div>
                    <div class="field">
                      <label for="pix_city">Cidade</label>
                      <input type="text" id="pix_city" name="pix_city" maxlength="15" placeholder="Sao Paulo"
                             value="<?= e(empty($settings['pix_payload']) ? ($settings['pix_city'] ?? '') : '') ?>">
                    </div>
                  </div>
                </div>
              </details>
            </div>

            <div class="field span-2">
              <label class="switch-inline">
                <input type="checkbox" name="pix_show" value="1" <?= !empty($settings['pix_show']) ? 'checked' : '' ?>>
                <span>Mostrar botão PIX no cartão público</span>
              </label>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar PIX</button>
          </div>
        </form>

        <div class="pix-preview-box">
          <h3 class="sub-title" style="margin-top:0">Pré-visualização</h3>
          <div class="qr-wrap">
            <img id="pix-qr-preview"
                 src="<?= e($initialPixQr ?? '') ?>"
                 alt="QR Code PIX"
                 style="<?= $initialPixQr ? '' : 'display:none' ?>">
          </div>
          <label class="small-note">Código copia-e-cola:</label>
          <textarea id="pix-payload-out" readonly rows="4"><?= e($initialPixPayload ?? '') ?></textarea>
          <button type="button" class="btn btn-primary" style="justify-content:center"
                  onclick="navigator.clipboard.writeText(document.getElementById('pix-payload-out').value); this.textContent='✓ Copiado!'; setTimeout(()=>this.textContent='Copiar código',1500)">
            Copiar código
          </button>
        </div>
      </div>
    </div>
  </section>

</div><!-- /admin-shell -->

<script src="../assets/js/admin.js"></script>
</body>
</html>