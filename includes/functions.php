<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/storage.php';

function get_profile(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM ' . table('profile') . ' WHERE id = 1');
    return $stmt->fetch() ?: [];
}

function get_settings(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM ' . table('settings') . ' WHERE id = 1');
    return $stmt->fetch() ?: [];
}

function get_active_links(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM ' . table('links') . ' WHERE active = 1 ORDER BY "position" ASC, id ASC');
    return $stmt->fetchAll();
}

function get_all_links(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM ' . table('links') . ' ORDER BY "position" ASC, id ASC');
    return $stmt->fetchAll();
}

function initials(string $name): string {
    $name = trim($name);
    if ($name === '') return '--';
    $parts = preg_split('/\s+/', $name);
    $parts = array_slice($parts, 0, 2);
    $hasMb = function_exists('mb_strtoupper');
    $letters = array_map(function ($p) use ($hasMb) {
        return $hasMb ? mb_strtoupper(mb_substr($p, 0, 1)) : strtoupper(substr($p, 0, 1));
    }, $parts);
    return implode('', $letters);
}

/**
 * Devolve o SVG (ou <img>, se "icon" for uma URL) para o ícone de um link.
 */
function icon_markup(?string $icon): string {
    $raw = trim((string) $icon);

    if (preg_match('#^https?://#i', $raw)) {
        $safe = htmlspecialchars($raw, ENT_QUOTES);
        return "<img src=\"{$safe}\" alt=\"\" style=\"width:100%;height:100%;object-fit:contain;border-radius:4px;\">";
    }

    $aliases = [
        'whats' => 'whatsapp', 'wpp' => 'whatsapp', 'zap' => 'whatsapp',
        'site' => 'default', 'web' => 'default', 'portfolio' => 'default',
        'mail' => 'email', 'x' => 'twitter', 'threads' => 'threads',
        'bluesky' => 'bluesky', 'mastodon' => 'mastodon', 'discord' => 'discord',
        'twitch' => 'twitch', 'spotify' => 'spotify', 'apple' => 'apple',
    ];
    $key = strtolower($raw);
    $key = $aliases[$key] ?? $key;

    $icons = [
        'instagram' =>
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="0.6" fill="currentColor" stroke="none"/></svg>',
        'whatsapp' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm0 18.1a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.1 8.1 0 1 1 12 20.1zm4.5-6.1c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.7.9-.3.1-.5 0a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.5-1.3.1-.1.1-.3 0-.4l-.7-1.6c-.2-.4-.4-.4-.5-.4h-.5a.9.9 0 0 0-.6.3 2.8 2.8 0 0 0-.9 2.1 4.8 4.8 0 0 0 1 2.6 11.1 11.1 0 0 0 4.5 4c1.6.6 2.1.6 2.6.5.6-.1 1.4-.6 1.6-1.1a1.9 1.9 0 0 0 .1-1.1c-.1-.1-.2-.2-.5-.3z"/></svg>',
        'linkedin' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.1c.5-.9 1.8-1.9 3.7-1.9 4 0 4.7 2.6 4.7 6v6.2h-4v-5.5c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21H9z"/></svg>',
        'github' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-3.16 19.5c.5.1.68-.2.68-.5v-1.7c-2.78.6-3.37-1.3-3.37-1.3-.46-1.1-1.1-1.5-1.1-1.5-.9-.6.07-.6.07-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.7.36-1.1.65-1.4-2.2-.3-4.6-1.1-4.6-4.9 0-1.1.38-1.9 1-2.6-.1-.3-.44-1.4.1-2.9 0 0 .84-.3 2.75 1a9.4 9.4 0 0 1 5 0c1.9-1.3 2.75-1 2.75-1 .54 1.5.2 2.6.1 2.9.62.7 1 1.5 1 2.6 0 3.8-2.4 4.6-4.6 4.9.37.3.7 1 .7 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/></svg>',
        'email' =>
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
        'twitter' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2s9 5 20 5a9.5 9.5 0 00-9-5.5c4.75 2.25 7-7 7-7a10.6 10.6 0 01-10 5.5z"/></svg>',
        'youtube' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33A2.78 2.78 0 003.4 19.54c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 001.94-2 29 29 0 00.46-5.25 29 29 0 00-.46-5.33zM9.75 15.02v-6.04l5.75 3.02-5.75 3.02z"/></svg>',
        'telegram' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.59-1.38-.95-2.23-1.52-.99-.66-.35-1.02.21-1.61.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.06-.14-.04-.21-.02-.09.02-1.49.95-4.21 2.79-.4.27-.76.41-1.1.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.15.27-.01.06 0 .27-.04.57z"/></svg>',
        'tiktok' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 3v10.2a2.6 2.6 0 1 1-2-2.5V8.5a5.1 5.1 0 1 0 4.5 5.1V9.8a6.9 6.9 0 0 0 3.5 1V8.3A4.1 4.1 0 0 1 16.5 5H14z"/></svg>',
        'pix' =>
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3 4 12l8 9 8-9z"/><path d="M9 12h6" stroke-width="1.4"/></svg>',
        'threads' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg>',
        'bluesky' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c2.16 0 3.74 1.57 4.5 2.5.34.4.66.84.99 1.35.39.61.77 1.27 1.16 1.94C18.62 5.97 19.02 5.71 19.4 5.41c1.36-1.08 2.66-2.11 4.49-2.11.43 0 .85.05 1.26.17 1.66.53 2.85 1.8 2.85 3.54 0 1.74-1.19 3-2.85 3.53-.41.12-.83.17-1.26.17-1.83 0-3.13-1.03-4.49-2.11-.38-.3-.78-.56-1.14-1.07.39.67.77 1.33 1.16 1.94.33.51.65.95.99 1.35.76.93 2.34 2.5 4.5 2.5 2.16 0 3.74-1.57 4.5-2.5.34-.4.66-.84.99-1.35.39-.61.77-1.27 1.16-1.94.38.51.76.77 1.14 1.07 1.36 1.08 2.66 2.11 4.49 2.11.43 0 .85-.05 1.26-.17 1.66-.53 2.85-1.8 2.85-3.53 0-1.74-1.19-3-2.85-3.54-.41-.12-.83-.17-1.26-.17-1.83 0-3.13 1.03-4.49 2.11-.36.3-.76.56-1.14 1.07"/></svg>',
        'discord' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.27 5.33C17.94 4.71 16.5 4.26 15 4c-.13.25-.27.64-.38 1.24-1.13-.17-2.25-.17-3.37 0-.11-.6-.25-.99-.38-1.24-1.5.26-2.93.71-4.27 1.33.67 3.27 3.35 6.47 7.27 7.16-.44.6-.82 1.27-.93 2.08 1.34.79 2.73 1.26 4.07 1.08 2.3-.25 4.33-1.48 4.33-3.51 0-1.35-1.02-2.52-2.4-3.13 1.08-3.2-4.26-4.65-4.26-4.65"/></svg>',
        'twitch' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11 2H2v15h4v4l4-4h5l4-4V2h-8zm11 11l-3-3h-5v-3h-2v3h-5V5h10v8z"/></svg>',
        'spotify' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.122-.899-.52-.12-.403.052-.82.471-.94 4.5-1.081 8.351-.33 11.574 1.32.36.221.463.645.163 1.001zm1.44-3.3c-.301.466-.841.606-1.306.3-3.26-2.003-8.281-2.582-12.203-1.413-.479.142-1.080-.116-1.221-.594-.141-.479.116-1.08.594-1.221 4.51-1.268 10.019-.101 13.79 1.566.466.301.641.84.3 1.307zm.25-3.43c-3.9-2.354-10.341-2.574-14.061-.857-.588.33-1.309-.127-1.639-.716-.33-.588.127-1.309.716-1.639 4.324-1.992 11.461-1.71 15.847 1.003.532.326.93 1.084.602 1.616-.328.533-1.086.93-1.617.602z"/></svg>',
        'apple' =>
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 13.5c-.91 2.92.37 5.65 2.85 6.02 1.31.19 2.73-.25 3.58-1.15.94-1.06 1.28-2.79.3-4.3-1.112-1.78-3.3-2.26-5.3-1.42-.62.25-.93.68-1.43 1.17m-1.99-3.5c1.395 0 2.45 1.13 2.45 2.55S16.45 15 15.06 15c-1.395 0-2.45-1.13-2.45-2.55S13.67 10 15.06 10m7.14.14c-.92 1.5-.92 3.92 0 5.42.64 1.05 1.93 1.8 3.23 1.8 2.1 0 3.81-1.7 3.81-3.8 0-2.1-1.71-3.8-3.81-3.8-1.3 0-2.59.75-3.23 1.8M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2"/></svg>',
        'default' =>
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9.5 14.5 14.5 9.5"/><path d="M11 6.5 12.3 5.2a3 3 0 0 1 4.3 4.3L15.3 11"/><path d="M13 17.5 11.7 18.8a3 3 0 0 1-4.3-4.3L8.7 13"/></svg>',
    ];

    return $icons[$key] ?? $icons['default'];
}

$FONT_DISPLAY_STACKS = [
    'newsreader' => '"Newsreader", Georgia, serif',
    'playfair'   => '"Playfair Display", Georgia, serif',
    'fraunces'   => '"Fraunces", Georgia, serif',
    'poppins'    => '"Poppins", -apple-system, sans-serif',
];
$FONT_BODY_STACKS = [
    'public' => '"Public Sans", -apple-system, sans-serif',
    'inter'  => '"Inter", -apple-system, sans-serif',
    'work'   => '"Work Sans", -apple-system, sans-serif',
    'nunito' => '"Nunito Sans", -apple-system, sans-serif',
];

function font_display_stack(?string $key): string {
    global $FONT_DISPLAY_STACKS;
    return $FONT_DISPLAY_STACKS[$key] ?? $FONT_DISPLAY_STACKS['newsreader'];
}
function font_body_stack(?string $key): string {
    global $FONT_BODY_STACKS;
    return $FONT_BODY_STACKS[$key] ?? $FONT_BODY_STACKS['public'];
}

function hex_adjust(string $hex, int $amount): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return '#' . $hex;
    $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
    $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
    $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

// ---------------------------------------------------------------
// Fallback para hospedagens sem a extensão mbstring
// (index.php e includes/pix.php usam mb_substr / mb_strtoupper)
// ---------------------------------------------------------------
if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (preg_match_all('/./us', $string, $m) === false) {
            return $length === null ? substr($string, $start) : substr($string, $start, $length);
        }
        return implode('', array_slice($m[0], $start, $length));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        return strtr(strtoupper($string), [
            'á' => 'Á', 'à' => 'À', 'â' => 'Â', 'ã' => 'Ã', 'ä' => 'Ä',
            'é' => 'É', 'è' => 'È', 'ê' => 'Ê', 'ë' => 'Ë',
            'í' => 'Í', 'ì' => 'Ì', 'î' => 'Î', 'ï' => 'Ï',
            'ó' => 'Ó', 'ò' => 'Ò', 'ô' => 'Ô', 'õ' => 'Õ', 'ö' => 'Ö',
            'ú' => 'Ú', 'ù' => 'Ù', 'û' => 'Û', 'ü' => 'Ü', 'ç' => 'Ç', 'ñ' => 'Ñ',
        ]);
    }
}

// ---------------------------------------------------------------
// Upload de imagens
// ---------------------------------------------------------------

/**
 * Garante que uma pasta exista e seja gravável.
 * Cria recursivamente se necessário e tenta ajustar permissões.
 */
function ensure_dir(string $path): bool
{
    if (is_dir($path)) {
        return is_writable($path);
    }
    if (!@mkdir($path, 0755, true) && !is_dir($path)) {
        return false;
    }
    @chmod($path, 0755);
    return is_writable($path);
}

/**
 * Faz upload de imagem com validação robusta.
 * Cria a pasta de destino automaticamente se não existir.
 */
function upload_image(array $file): array
{
    if (empty($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errs = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Não foi possível gravar no disco.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por uma extensão do PHP.',
        ];
        $msg = $errs[$file['error']] ?? ('Erro no upload (código ' . $file['error'] . ').');
        return ['success' => false, 'error' => $msg];
    }

    $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo: ' . round($maxSize / 1024 / 1024, 1) . 'MB.'];
    }

    // Detecção de MIME com fallback em 3 camadas
    $mime = null;

    if (function_exists('finfo_open')) {
        $const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : 16;
        $finfo = @finfo_open($const);
        if ($finfo) {
            $mime = @finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }

    if (!$mime && function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && !empty($info['mime'])) $mime = $info['mime'];
    }

    if (!$mime && function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']) ?: null;
    }

    if (!$mime) {
        return ['success' => false, 'error' => 'Não foi possível identificar o tipo da imagem.'];
    }

    $allowed = defined('UPLOAD_ALLOWED_TYPES')
        ? UPLOAD_ALLOWED_TYPES
        : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($mime, $allowed, true)) {
        return ['success' => false, 'error' => 'Tipo não permitido: ' . $mime];
    }

    $map = [
        'image/jpeg'  => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'   => 'png',
        'image/webp'  => 'webp',
        'image/gif'   => 'gif',
    ];
    $ext = $map[$mime] ?? 'jpg';

    $filename = 'avatar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Backblaze B2: devolve a URL pública completa (vai direto para avatar_file)
    if (b2_enabled()) {
        $up = b2_put_file($file['tmp_name'], B2_PREFIX . $filename, $mime);
        if (!$up['success']) return ['success' => false, 'error' => $up['error']];
        return ['success' => true, 'filename' => $up['url']];
    }

    // Sem B2: grava em disco, como antes
    $dir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads';
    if (!ensure_dir($dir)) {
        return ['success' => false, 'error' => 'Não foi possível criar/gravar em: ' . $dir];
    }
    $dest = rtrim($dir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Falha ao mover o arquivo.'];
    }

    return ['success' => true, 'filename' => $filename];
}

// ---------------------------------------------------------------
// Mídia do banner e dos botões (upload + link)
// ---------------------------------------------------------------

/**
 * Envia imagem ou vídeo para /uploads e devolve a referência relativa
 * (ex.: "uploads/banner_20260928_101500_ab12cd34.jpg").
 * $kind = 'image' | 'video'   |   $prefix = 'banner' | 'slide' | 'link'
 */
function save_media_upload(array $file, string $kind, string $prefix): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        $errs = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite do servidor (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente de novo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Não foi possível gravar no disco.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por uma extensão do PHP.',
        ];
        return ['success' => false, 'error' => $errs[$file['error']] ?? ('Erro no upload (código ' . $file['error'] . ').')];
    }

    $isVideo = ($kind === 'video');
    $maxSize = $isVideo
        ? (defined('UPLOAD_VIDEO_MAX_SIZE') ? UPLOAD_VIDEO_MAX_SIZE : 20 * 1024 * 1024)
        : (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5 * 1024 * 1024);
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo: ' . round($maxSize / 1024 / 1024, 1) . 'MB.'];
    }

    // Tipo real do arquivo (pelo conteúdo, não pela extensão)
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : 16);
        if ($finfo) {
            $mime = @finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }
    if (!$mime && !$isVideo && function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && !empty($info['mime'])) $mime = $info['mime'];
    }
    if (!$mime && function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']) ?: null;
    }
    if (!$mime) {
        return ['success' => false, 'error' => 'Não foi possível identificar o tipo do arquivo.'];
    }

    $map = $isVideo
        ? ['video/mp4' => 'mp4', 'video/x-m4v' => 'mp4', 'video/webm' => 'webm']
        : ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    if (!isset($map[$mime])) {
        return ['success' => false, 'error' => 'Tipo não permitido (' . $mime . '). ' .
            ($isVideo ? 'Use MP4 ou WebM.' : 'Use JPG, PNG, WebP ou GIF.')];
    }

    $prefix   = preg_replace('/[^a-z]/', '', strtolower($prefix)) ?: 'media';
    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];

    // Backblaze B2: a referência guardada no banco é a URL pública completa
    if (b2_enabled()) {
        $up = b2_put_file($file['tmp_name'], B2_PREFIX . $filename, $mime);
        if (!$up['success']) return ['success' => false, 'error' => $up['error']];
        return ['success' => true, 'ref' => $up['url']];
    }

    // Sem B2: grava em disco, como antes
    $dir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads';
    if (!ensure_dir($dir)) {
        return ['success' => false, 'error' => 'Não foi possível criar/gravar a pasta uploads.'];
    }
    if (!move_uploaded_file($file['tmp_name'], rtrim($dir, '/') . '/' . $filename)) {
        return ['success' => false, 'error' => 'Falha ao mover o arquivo enviado.'];
    }
    return ['success' => true, 'ref' => 'uploads/' . $filename];
}

/**
 * Valida o que foi digitado no campo de mídia: aceita link http(s) completo
 * ou um arquivo já enviado ("uploads/nome.ext"). Qualquer outra coisa vira null.
 */
function clean_media_ref($value): ?string
{
    $v = trim((string) $value);
    if ($v === '') return null;
    if (preg_match('~^uploads/[A-Za-z0-9._-]+$~', $v) && strpos($v, '..') === false) return $v;
    if (preg_match('~^https?://~i', $v) && filter_var($v, FILTER_VALIDATE_URL)) return mb_substr($v, 0, 500);
    return null;
}

/** Endereço para usar em src / url() na página pública. */
function media_src(?string $ref): string
{
    return (string) $ref;
}

/**
 * Extrai o ID de um link do YouTube (watch, youtu.be, shorts, embed, live). Retorna null se não for YouTube.
 */
function youtube_id(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '' || !preg_match('~^https?://~i', $url)) return null;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('~^(www\.|m\.|music\.)~', '', $host);
    $path = (string) parse_url($url, PHP_URL_PATH);
    $id = null;

    if ($host === 'youtu.be') {
        $id = trim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        if (preg_match('~^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~', $path, $m)) {
            $id = $m[1];
        } else {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $id = $q['v'] ?? null;
        }
    }
    $id = is_string($id) ? substr($id, 0, 11) : null;
    return ($id !== null && preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) ? $id : null;
}

/** Endereço do player do YouTube: sem som, em loop, sem controles. */
function youtube_embed_url(string $id): string
{
    return 'https://www.youtube-nocookie.com/embed/' . $id
        . '?autoplay=1&mute=1&loop=1&playlist=' . $id
        . '&controls=0&modestbranding=1&playsinline=1&rel=0&iv_load_policy=3&disablekb=1';
}

/**
 * Que tipo de mídia é este link? 'youtube' | 'video' (mp4/webm/ogv por extensão) | 'image'.
 */
function media_kind(?string $ref): string
{
    $ref = trim((string) $ref);
    if ($ref === '') return 'image';
    if (youtube_id($ref)) return 'youtube';
    $path = strtolower((string) parse_url($ref, PHP_URL_PATH));
    if (preg_match('~\.(mp4|m4v|webm|ogv)$~', $path)) return 'video';
    return 'image';
}

/** Tipo do vídeo pela extensão (ignora ?query). Padrão: mp4. */
function video_mime(string $ref): string
{
    $path = strtolower((string) parse_url($ref, PHP_URL_PATH));
    if (substr($path, -5) === '.webm') return 'video/webm';
    if (substr($path, -4) === '.ogv' || substr($path, -4) === '.ogg') return 'video/ogg';
    return 'video/mp4';
}

/**
 * Apaga os arquivos enviados por este sistema que não são mais usados em lugar nenhum.
 * Aceita referências locais ("uploads/banner_x.jpg") e URLs do nosso bucket B2.
 * Links externos digitados pela pessoa nunca são tocados.
 */
function delete_unused_media(PDO $pdo, array $refs): void
{
    $s = table('settings');
    $l = table('links');
    $p = table('profile');
    foreach (array_unique(array_filter($refs)) as $ref) {
        $ref = (string) $ref;
        $b2Key   = b2_key_from_url($ref);
        $isLocal = (bool) preg_match('~^uploads/(avatar|banner|slide|link)_[A-Za-z0-9._-]+$~', $ref);
        if ($b2Key === null && !$isLocal) continue;

        try {
            $q = $pdo->prepare(
                "SELECT
                   (SELECT COUNT(*) FROM $s WHERE banner_url = :a OR banner_video_url = :b OR banner_slides LIKE :c)
                 + (SELECT COUNT(*) FROM $l WHERE banner_image = :d)
                 + (SELECT COUNT(*) FROM $p WHERE avatar_file = :e)"
            );
            $q->execute([':a' => $ref, ':b' => $ref, ':c' => '%"' . $ref . '"%', ':d' => $ref, ':e' => $ref]);
            if ((int) $q->fetchColumn() > 0) continue; // ainda em uso
        } catch (Throwable $e) {
            continue; // na dúvida, não apaga
        }

        if ($b2Key !== null) {
            b2_delete($b2Key);
            continue;
        }
        $dir  = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads';
        $path = rtrim($dir, '/') . '/' . basename($ref);
        if (is_file($path)) @unlink($path);
    }
}

/** Resolve a foto do avatar: URL do B2/externa ou arquivo local antigo (só o nome). */
function avatar_src(array $profile): ?string
{
    $file = trim((string) ($profile['avatar_file'] ?? ''));
    if ($file !== '') {
        return preg_match('~^https?://~i', $file) ? $file : 'uploads/' . rawurlencode($file);
    }
    $url = trim((string) ($profile['avatar_url'] ?? ''));
    return $url !== '' ? $url : null;
}

function e(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
