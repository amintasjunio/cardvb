#!/usr/bin/env php
<?php
/**
 * Migração dos dados existentes: MySQL -> Supabase (Postgres) e uploads/ -> Backblaze B2.
 * Só roda pela linha de comando.
 *
 * Pré-requisitos:
 *   - sql/schema.sql já aplicado no Supabase (com o MESMO prefixo do APP_PREFIX)
 *   - DB_* e B2_* configurados (env ou includes/config.local.php) apontando para o DESTINO
 *   - para copiar do MySQL: extensão pdo_mysql e as variáveis MYSQL_* abaixo
 *
 * Uso:
 *   MYSQL_HOST=127.0.0.1 MYSQL_NAME=card_mocidade MYSQL_USER=root MYSQL_PASS=xxx \
 *   MYSQL_PREFIX=moc_ php tools/migrate_to_supabase_b2.php [--dry-run] [--skip-db] [--skip-media]
 *
 *   --dry-run     mostra o que seria feito, sem gravar nada (nem no banco nem no B2)
 *   --skip-db     não copia o MySQL (só envia as imagens e corrige as referências no Supabase)
 *   --skip-media  só copia o banco
 *
 * É seguro rodar de novo: usa UPSERT e só envia arquivos que ainda são locais.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente linha de comando.\n"); }

require_once __DIR__ . '/../includes/functions.php';

$dry       = in_array('--dry-run', $argv, true);
$skipDb    = in_array('--skip-db', $argv, true);
$skipMedia = in_array('--skip-media', $argv, true);
if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo preg_replace('~^\s*\* ?~m', '', substr(file_get_contents(__FILE__), 20, 1500)); exit(0);
}

function say(string $m): void { echo $m . "\n"; }
function warn(string $m): void { fwrite(STDERR, "  ! $m\n"); }

say($dry ? '== SIMULAÇÃO (nada será gravado) ==' : '== Migração ==');

try {
    $pg = get_pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "Não consegui conectar no Supabase (destino): {$e->getMessage()}\nConfira DB_HOST, DB_PORT, DB_USER, DB_PASS.\n");
    exit(1);
}
$prefix = APP_PREFIX;

// --- confere se o schema existe no destino -----------------------------
$q = $pg->prepare('SELECT to_regclass(?)');
$q->execute([$prefix . 'settings']);
if (!$q->fetchColumn()) {
    fwrite(STDERR, "A tabela {$prefix}settings não existe no Supabase. Aplique sql/schema.sql (prefixo {$prefix}) primeiro.\n");
    exit(1);
}

/** Colunas que existem na tabela de destino. */
function pg_columns(PDO $pg, string $table): array {
    $s = $pg->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?');
    $s->execute([$table]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

// Uma transação só para tudo: em --dry-run ela é desfeita no final, então a
// simulação da mídia enxerga as linhas que a cópia do banco "criou".
$pg->beginTransaction();
register_shutdown_function(function () use ($pg) { if ($pg->inTransaction()) $pg->rollBack(); });

// =======================================================================
// 1) BANCO: MySQL -> Postgres
// =======================================================================
if (!$skipDb) {
    if (!extension_loaded('pdo_mysql')) { fwrite(STDERR, "Ative a extensão pdo_mysql (ou use --skip-db).\n"); exit(1); }
    $mHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
    $mPort = getenv('MYSQL_PORT') ?: '3306';
    $mName = getenv('MYSQL_NAME') ?: '';
    $mUser = getenv('MYSQL_USER') ?: 'root';
    $mPass = getenv('MYSQL_PASS') ?: '';
    $mPref = getenv('MYSQL_PREFIX') ?: $prefix;
    if ($mName === '') { fwrite(STDERR, "Informe MYSQL_NAME (e MYSQL_HOST/USER/PASS/PREFIX se precisar).\n"); exit(1); }

    try {
        $my = new PDO("mysql:host=$mHost;port=$mPort;dbname=$mName;charset=utf8mb4", $mUser, $mPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, "Não consegui conectar no MySQL de origem: {$e->getMessage()}\n");
        exit(1);
    }
    say("Origem : MySQL $mHost/$mName (prefixo $mPref)");
    say("Destino: Postgres " . DB_HOST . '/' . DB_NAME . " (prefixo $prefix)");

    // admin_sessions fica de fora de propósito: quem estava logado só entra de novo.
    foreach (['admin_users', 'profile', 'settings', 'links'] as $t) {
        try {
            $rows = $my->query("SELECT * FROM `{$mPref}{$t}`")->fetchAll();
        } catch (Throwable $e) {
            warn("tabela {$mPref}{$t} não encontrada no MySQL, pulando ({$e->getMessage()})");
            continue;
        }
        $dstCols = pg_columns($pg, $prefix . $t);
        $n = 0;
        try {
            foreach ($rows as $row) {
                $cols = array_values(array_intersect(array_keys($row), $dstCols));
                $vals = [];
                foreach ($cols as $c) {
                    $v = $row[$c];
                    if (is_string($v) && strncmp($v, '0000-00-00', 10) === 0) $v = null; // data zero do MySQL
                    $vals[] = $v;
                }
                $set = [];
                foreach ($cols as $c) if ($c !== 'id') $set[] = "$c = EXCLUDED.$c";
                $sql = 'INSERT INTO ' . $prefix . $t . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')'
                     . ' ON CONFLICT (id) DO ' . ($set ? 'UPDATE SET ' . implode(', ', $set) : 'NOTHING');
                $pg->prepare($sql)->execute($vals);
                $n++;
            }
            // corrige o contador de IDs (importamos IDs explícitos)
            if (in_array('id', $dstCols, true) && in_array($t, ['admin_users', 'links'], true)) {
                $pg->query("SELECT setval(pg_get_serial_sequence('{$prefix}{$t}', 'id'), COALESCE((SELECT MAX(id) FROM {$prefix}{$t}), 1), (SELECT MAX(id) IS NOT NULL FROM {$prefix}{$t}))");
            }
        } catch (Throwable $e) {
            if ($pg->inTransaction()) $pg->rollBack();
            fwrite(STDERR, "Erro em {$t}: {$e->getMessage()}\n"); exit(1);
        }
        say(sprintf("  %-12s %d linha(s) %s", $t, $n, $dry ? '(simulado)' : 'copiada(s)'));
    }
}

// =======================================================================
// 2) MÍDIA: uploads/ -> Backblaze B2 e reescrita das referências
// =======================================================================
if (!$skipMedia) {
    if (!b2_enabled()) {
        warn('B2 não configurado (B2_KEY_ID, B2_APP_KEY, B2_BUCKET): pulando as imagens.');
        $dry ? $pg->rollBack() : $pg->commit();
        exit(0);
    }
    say("\nImagens -> B2 bucket " . B2_BUCKET . ' (' . B2_PUBLIC_URL . ')');

    $dir   = rtrim(UPLOAD_DIR, '/');
    $cache = [];          // nome do arquivo -> URL nova
    $stats = ['enviados' => 0, 'ja_no_b2' => 0, 'faltando' => 0, 'refs' => 0];

    /** Devolve a URL nova para uma referência local; null = nada a mudar. */
    $moveRef = function (?string $ref, bool $bareName) use ($dir, $dry, &$cache, &$stats): ?string {
        $ref = trim((string) $ref);
        if ($ref === '') return null;
        if (preg_match('~^https?://~i', $ref)) { if (b2_key_from_url($ref) !== null) $stats['ja_no_b2']++; return null; }

        if (preg_match('~^uploads/([A-Za-z0-9._-]+)$~', $ref, $m))      $name = $m[1];
        elseif ($bareName && preg_match('~^[A-Za-z0-9._-]+$~', $ref))   $name = $ref;
        else return null;

        if (isset($cache[$name])) return $cache[$name];
        $path = $dir . '/' . $name;
        if (!is_file($path)) { warn("arquivo local não encontrado: $path (referência mantida)"); $stats['faltando']++; return null; }

        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        $key  = B2_PREFIX . $name;
        if ($dry) { $url = b2_public_url($key); }
        else {
            $up = b2_put_file($path, $key, $mime);
            if (!$up['success']) { fwrite(STDERR, "Falha ao enviar $name: {$up['error']}\n"); exit(1); }
            $url = $up['url'];
        }
        $stats['enviados']++;
        say("  + $name -> $url");
        return $cache[$name] = $url;
    };

    try {
        $p = $prefix;
        // avatar (guardado como nome simples)
        foreach ($pg->query("SELECT id, avatar_file FROM {$p}profile")->fetchAll() as $r) {
            $new = $moveRef($r['avatar_file'], true);
            if ($new) { $pg->prepare("UPDATE {$p}profile SET avatar_file = ? WHERE id = ?")->execute([$new, $r['id']]); $stats['refs']++; }
        }
        // banner da página
        foreach ($pg->query("SELECT id, banner_url, banner_video_url, banner_slides FROM {$p}settings")->fetchAll() as $r) {
            $u = $moveRef($r['banner_url'], false) ?? $r['banner_url'];
            $v = $moveRef($r['banner_video_url'], false) ?? $r['banner_video_url'];
            $slides = $r['banner_slides'];
            $arr = json_decode((string) $slides, true);
            if (is_array($arr)) {
                $arr = array_map(fn($s) => is_string($s) ? ($moveRef($s, false) ?? $s) : $s, $arr);
                $slides = json_encode($arr, JSON_UNESCAPED_SLASHES);
            }
            if ($u !== $r['banner_url'] || $v !== $r['banner_video_url'] || $slides !== $r['banner_slides']) {
                $pg->prepare("UPDATE {$p}settings SET banner_url = ?, banner_video_url = ?, banner_slides = ? WHERE id = ?")
                   ->execute([$u, $v, $slides, $r['id']]);
                $stats['refs']++;
            }
        }
        // capas dos botões
        foreach ($pg->query("SELECT id, banner_image FROM {$p}links")->fetchAll() as $r) {
            $new = $moveRef($r['banner_image'], false);
            if ($new) { $pg->prepare("UPDATE {$p}links SET banner_image = ? WHERE id = ?")->execute([$new, $r['id']]); $stats['refs']++; }
        }
    } catch (Throwable $e) {
        if ($pg->inTransaction()) $pg->rollBack();
        fwrite(STDERR, "Erro na migração de mídia: {$e->getMessage()}\n"); exit(1);
    }

    say(sprintf("\nArquivos enviados: %d | referências atualizadas: %d | já no B2: %d | arquivos faltando: %d",
        $stats['enviados'], $stats['refs'], $stats['ja_no_b2'], $stats['faltando']));
    if (!$dry && $stats['faltando'] === 0) say('Confira o site e, se estiver tudo certo, a pasta uploads/ local pode ser apagada (guarde um backup).');
}
if ($pg->inTransaction()) { $dry ? $pg->rollBack() : $pg->commit(); }
say($dry ? "\nSimulação concluída. Nada foi gravado." : "\nConcluído.");
