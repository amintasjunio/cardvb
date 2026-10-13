<?php
/**
 * Gerador de payload PIX (BR Code / EMV) + QR Code via API pública.
 * Não requer dependências externas.
 */

function pix_payload(string $key, string $name, string $city): string
{
    $key  = pix_normalize_key($key);
    $name = mb_substr(trim(preg_replace('/[^A-Za-z0-9 ]/', '', pix_ascii($name))), 0, 25);
    $city = mb_substr(trim(preg_replace('/[^A-Za-z0-9 ]/', '', pix_ascii($city))), 0, 15);
    if ($name === '') $name = 'RECEBEDOR';
    if ($city === '') $city = 'CIDADE';

    $emit = function (string $id, string $value): string {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    };

    // Merchant Account Information (26)
    $gui = $emit('00', 'br.gov.bcb.pix');
    $mai = $emit('26', $gui . $emit('01', $key));

    $payload  = $emit('00', '01');            // Payload Format Indicator
    $payload .= $mai;                         // Merchant Account Info
    $payload .= $emit('52', '0000');          // Merchant Category Code
    $payload .= $emit('53', '986');           // Moeda (BRL)
    $payload .= $emit('58', 'BR');            // País
    $payload .= $emit('59', $name);           // Nome do recebedor
    $payload .= $emit('60', $city);           // Cidade
    $payload .= $emit('62', $emit('05', '***')); // TXID
    $payload .= '6304';                        // CRC placeholder

    return $payload . crc16_ccitt($payload);
}

/** Remove acentos: "João" -> "Joao" (antes virava "Joo"). */
function pix_ascii(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';
    if (function_exists('transliterator_transliterate')) {
        $t = @transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        if (is_string($t) && $t !== '') return $t;
    }
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C','Ñ'=>'N'];
    return strtr($s, $map);
}

/**
 * Deixa a chave no formato que o banco espera:
 *  - e-mail: minúsculo, sem espaços
 *  - chave aleatória (UUID): minúsculo
 *  - telefone: +55DDDNUMERO
 *  - CPF/CNPJ: só dígitos
 */
function pix_normalize_key(string $key): string
{
    $key = trim($key);
    if ($key === '') return '';
    if (strpos($key, '@') !== false) return strtolower(str_replace(' ', '', $key));
    if (preg_match('/^[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{12}$/', $key)) {
        $h = strtolower(str_replace('-', '', $key));
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
    if ($key[0] === '+') return '+' . preg_replace('/\D/', '', $key);
    $digits = preg_replace('/\D/', '', $key);
    if (strlen($digits) === 11 || strlen($digits) === 14) {
        // 11 dígitos pode ser CPF ou celular; sem +55 tratamos como CPF (formato mais comum).
        // Para telefone, o usuário deve digitar com "+55".
        return $digits;
    }
    if (strlen($digits) === 13 && strpos($digits, '55') === 0) return '+' . $digits; // 55 + DDD + 9 dígitos
    return $key;
}

/**
 * Limpa o texto colado: tira quebras de linha, tabs, caracteres invisíveis e aspas nas pontas.
 * Os espaços do meio são mantidos (o nome do recebedor pode ter espaços).
 */
function pix_clean_payload(string $raw): string
{
    $s = preg_replace('/[\r\n\t\x{200B}-\x{200D}\x{FEFF}\x{00A0}]+/u', '', $raw);
    return trim((string) $s, " \"'");
}

/** Lê um trecho TLV (id 2 dígitos + tamanho 2 dígitos + valor). */
function pix_tlv_parse(string $data): array
{
    $out = []; $i = 0; $n = strlen($data);
    while ($i + 4 <= $n) {
        $id  = substr($data, $i, 2);
        $len = substr($data, $i + 2, 2);
        if (!ctype_digit($len)) return [];
        $len = (int) $len;
        if ($i + 4 + $len > $n) return [];
        $out[$id] = substr($data, $i + 4, $len);
        $i += 4 + $len;
    }
    return $out;
}

/**
 * Valida e interpreta um "Pix Copia e Cola".
 * Retorna ['ok'=>bool, 'error'=>string, 'payload'=>string, 'key'=>, 'name'=>, 'city'=>, 'amount'=>]
 */
function pix_parse_payload(string $raw): array
{
    $payload = pix_clean_payload($raw);

    // Se o CRC não bater, pode ser que o texto tenha sido quebrado em linhas trocadas por espaço
    if ($payload !== '' && strlen($payload) >= 30 && substr($payload, -8, 4) === '6304'
        && crc16_ccitt(substr($payload, 0, -4)) !== strtoupper(substr($payload, -4))) {
        $noSpaces = preg_replace('/\s+/', '', $payload);
        if (crc16_ccitt(substr($noSpaces, 0, -4)) === strtoupper(substr($noSpaces, -4))) $payload = $noSpaces;
    }
    $fail = function (string $m) use ($payload) { return ['ok' => false, 'error' => $m, 'payload' => $payload]; };

    if ($payload === '') return $fail('Cole o código Pix Copia e Cola.');
    if (strpos($payload, '000201') !== 0) {
        return $fail('Isso não parece um Pix Copia e Cola (ele começa com 000201). Copie o código inteiro no app do seu banco.');
    }
    $len = strlen($payload);
    if ($len < 30 || substr($payload, -8, 4) !== '6304') {
        return $fail('O código parece cortado. Copie o Pix Copia e Cola completo, até o final.');
    }
    $crcInformed = strtoupper(substr($payload, -4));
    if (crc16_ccitt(substr($payload, 0, -4)) !== $crcInformed) {
        return $fail('O código está incompleto ou foi alterado (verificação CRC falhou). Copie novamente do app do banco.');
    }

    $tlv  = pix_tlv_parse($payload);
    $key  = '';
    // Campos 26..51 = Merchant Account Information; o PIX é o que tem GUI br.gov.bcb.pix
    foreach ($tlv as $id => $val) {
        if ((int) $id < 26 || (int) $id > 51) continue;
        $sub = pix_tlv_parse($val);
        if (isset($sub['00']) && stripos($sub['00'], 'br.gov.bcb.pix') !== false) {
            $key = $sub['01'] ?? ($sub['25'] ?? '');   // 01 = chave (estático), 25 = URL (dinâmico)
            break;
        }
    }
    if ($key === '') {
        return $fail('Não encontrei uma chave PIX nesse código. Confirme que é um Pix Copia e Cola.');
    }

    return [
        'ok'      => true,
        'error'   => '',
        'payload' => $payload,
        'key'     => $key,
        'name'    => $tlv['59'] ?? '',
        'city'    => $tlv['60'] ?? '',
        'amount'  => $tlv['54'] ?? '',
    ];
}

/**
 * Qual código o cartão deve mostrar?
 * 1) Copia e Cola colado pelo dono (usado exatamente como veio do banco)
 * 2) Senão, gerado a partir de chave + nome + cidade
 */
function pix_resolve_payload(array $settings): ?string
{
    $raw = trim((string) ($settings['pix_payload'] ?? ''));
    if ($raw !== '') {
        $r = pix_parse_payload($raw);
        if ($r['ok']) return $r['payload'];
    }
    if (!empty($settings['pix_key'])) {
        return pix_payload($settings['pix_key'], $settings['pix_name'] ?? 'RECEBEDOR', $settings['pix_city'] ?? 'CIDADE');
    }
    return null;
}

function crc16_ccitt(string $data): string
{
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/** Gera URL de QR Code usando api.qrserver.com (sem chave) */
function pix_qr_url(string $payload, int $size = 260): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
        . '&margin=8&data=' . urlencode($payload);
}