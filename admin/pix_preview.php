<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pix.php';

require_login();

header('Content-Type: application/json');

$raw = trim($_POST['pix_payload'] ?? '');

try {
    if ($raw !== '') {
        // Pix Copia e Cola colado: valida e mostra o que foi lido
        $r = pix_parse_payload($raw);
        if (!$r['ok']) {
            echo json_encode(['ok' => false, 'error' => $r['error']]);
            exit;
        }
        echo json_encode([
            'ok'      => true,
            'payload' => $r['payload'],
            'qr'      => pix_qr_url($r['payload'], 260),
            'key'     => $r['key'],
            'name'    => $r['name'],
            'city'    => $r['city'],
        ]);
        exit;
    }

    $key  = trim($_POST['pix_key'] ?? '');
    $name = trim($_POST['pix_name'] ?? 'RECEBEDOR');
    $city = trim($_POST['pix_city'] ?? 'CIDADE');

    if ($key === '') {
        echo json_encode(['ok' => false, 'error' => 'empty']);
        exit;
    }

    $payload = pix_payload($key, $name, $city);
    echo json_encode([
        'ok'      => true,
        'payload' => $payload,
        'qr'      => pix_qr_url($payload, 260),
        'key'     => pix_normalize_key($key),
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
