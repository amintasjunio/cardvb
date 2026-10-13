<?php
/**
 * Armazenamento de mídia no Backblaze B2 (API compatível com S3).
 * Sem SDK e sem Composer: só cURL + assinatura AWS Signature V4.
 *
 * Funções públicas:
 *   b2_enabled()                       B2 está configurado?
 *   b2_put_file($arquivo, $chave, $mime)  envia um arquivo -> ['success'=>bool, 'url'=>..., 'error'=>...]
 *   b2_delete($chave)                  apaga um objeto
 *   b2_public_url($chave)              URL pública de uma chave
 *   b2_key_from_url($url)              chave de uma URL do nosso bucket (ou null)
 */
require_once __DIR__ . '/config.php';

function b2_enabled(): bool
{
    return B2_KEY_ID !== '' && B2_APP_KEY !== '' && B2_BUCKET !== '';
}

/** Codifica cada segmento da chave, mantendo as barras. */
function b2_encode_key(string $key): string
{
    return implode('/', array_map('rawurlencode', explode('/', $key)));
}

function b2_public_url(string $key): string
{
    return B2_PUBLIC_URL . '/' . b2_encode_key($key);
}

/**
 * Se $url aponta para o NOSSO bucket (dentro de B2_PREFIX), devolve a chave.
 * Qualquer outra coisa (link externo, caminho local) devolve null, então
 * nunca apagamos algo que não foi enviado por este sistema.
 */
function b2_key_from_url(?string $url): ?string
{
    $url  = trim((string) $url);
    $base = B2_PUBLIC_URL . '/';
    if ($url === '' || strncmp($url, $base, strlen($base)) !== 0) return null;

    $path = explode('?', substr($url, strlen($base)), 2)[0];
    $key  = rawurldecode($path);
    if ($key === '' || strpos($key, '..') !== false) return null;
    if (strncmp($key, B2_PREFIX, strlen(B2_PREFIX)) !== 0) return null;
    return $key;
}

/**
 * Faz uma requisição assinada (SigV4) ao endpoint S3 do B2.
 * @return array{status:int, body:string, error:string}
 */
function b2_request(string $method, string $key, ?string $filePath = null, array $extraHeaders = []): array
{
    if (!function_exists('curl_init')) {
        return ['status' => 0, 'body' => '', 'error' => 'A extensão PHP cURL não está ativada.'];
    }

    $scheme = parse_url(B2_ENDPOINT, PHP_URL_SCHEME) ?: 'https';
    $host   = parse_url(B2_ENDPOINT, PHP_URL_HOST);
    $port   = parse_url(B2_ENDPOINT, PHP_URL_PORT);
    if ($port && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
        $host .= ':' . $port; // o cabeçalho Host (assinado) inclui a porta quando não é a padrão
    }
    $uri    = '/' . rawurlencode(B2_BUCKET) . '/' . b2_encode_key($key);

    $amzDate = gmdate('Ymd\THis\Z');
    $day     = gmdate('Ymd');
    $payloadHash = $filePath !== null ? hash_file('sha256', $filePath) : hash('sha256', '');

    // Cabeçalhos assinados (nomes minúsculos, em ordem alfabética)
    $signed = [
        'host'                 => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date'           => $amzDate,
    ];
    ksort($signed);
    $canonicalHeaders = '';
    foreach ($signed as $k => $v) $canonicalHeaders .= $k . ':' . trim($v) . "\n";
    $signedHeaders = implode(';', array_keys($signed));

    $canonicalRequest = implode("\n", [$method, $uri, '', $canonicalHeaders, $signedHeaders, $payloadHash]);

    $scope        = $day . '/' . B2_REGION . '/s3/aws4_request';
    $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

    $kDate    = hash_hmac('sha256', $day, 'AWS4' . B2_APP_KEY, true);
    $kRegion  = hash_hmac('sha256', B2_REGION, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = 'AWS4-HMAC-SHA256 Credential=' . B2_KEY_ID . '/' . $scope
                   . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $headers = [
        'Authorization: ' . $authorization,
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
        'Expect:', // evita o "100-continue", que o B2 não precisa
    ];
    foreach ($extraHeaders as $name => $value) $headers[] = $name . ': ' . $value;

    $ch = curl_init($scheme . '://' . $host . $uri);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $fh = null;
    if ($filePath !== null) {
        $fh = fopen($filePath, 'rb');
        if (!$fh) return ['status' => 0, 'body' => '', 'error' => 'Não consegui ler o arquivo para enviar.'];
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
    }

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($fh) fclose($fh);

    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $err];
}

/** Extrai a mensagem de erro do XML que o B2/S3 devolve. */
function b2_error_message(array $res): string
{
    if ($res['error'] !== '') return 'Falha de conexão com o B2: ' . $res['error'];
    $msg = '';
    if (preg_match('~<Message>(.*?)</Message>~s', $res['body'], $m)) $msg = trim(html_entity_decode($m[1]));
    $code = '';
    if (preg_match('~<Code>(.*?)</Code>~s', $res['body'], $m)) $code = trim($m[1]);
    return 'B2 respondeu ' . $res['status'] . ($code ? " ($code)" : '') . ($msg ? ': ' . $msg : '.');
}

/**
 * Envia um arquivo local para o bucket. A $chave deve começar com B2_PREFIX.
 * Os nomes têm sufixo aleatório e nunca mudam, então o cache pode ser longo.
 */
function b2_put_file(string $localPath, string $key, string $contentType): array
{
    if (!b2_enabled()) return ['success' => false, 'error' => 'Backblaze B2 não configurado.'];

    $res = b2_request('PUT', $key, $localPath, [
        'Content-Type'  => $contentType,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
    if ($res['status'] >= 200 && $res['status'] < 300) {
        return ['success' => true, 'url' => b2_public_url($key)];
    }
    return ['success' => false, 'error' => b2_error_message($res)];
}

/** Apaga um objeto do bucket. true = ok (o S3 também devolve ok se ele já não existia). */
function b2_delete(string $key): bool
{
    if (!b2_enabled()) return false;
    if (strncmp($key, B2_PREFIX, strlen(B2_PREFIX)) !== 0) return false; // só mexe na nossa pasta
    $res = b2_request('DELETE', $key);
    if (!($res['status'] >= 200 && $res['status'] < 300)) {
        error_log('b2_delete(' . $key . '): ' . b2_error_message($res));
        return false;
    }
    return true;
}
