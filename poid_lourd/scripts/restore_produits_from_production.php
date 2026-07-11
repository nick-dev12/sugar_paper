<?php
/**
 * Télécharge les images produits depuis la production (fichiers originaux)
 * puis lance la conversion WebP en conservant le nom de base.
 *
 * Usage : php scripts/restore_produits_from_production.php
 *         php scripts/restore_produits_from_production.php --dry-run
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$dry = in_array('--dry-run', $argv, true);
$base_url = 'https://colobanes.com/upload/';

require __DIR__ . '/../conn/conn.php';

$paths = [];
$stmt = $db->query('SELECT image_principale, images FROM produits');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ([$r['image_principale'], $r['images']] as $raw) {
        if ($raw === null || $raw === '') {
            continue;
        }
        if (str_starts_with((string) $raw, '[')) {
            $arr = json_decode((string) $raw, true);
            if (is_array($arr)) {
                foreach ($arr as $p) {
                    $p = trim(str_replace('\\', '/', (string) $p));
                    if ($p !== '' && str_starts_with($p, 'produits/')) {
                        $paths[$p] = true;
                    }
                }
            }
        } else {
            $p = trim(str_replace('\\', '/', (string) $raw));
            if ($p !== '' && str_starts_with($p, 'produits/')) {
                $paths[$p] = true;
            }
        }
    }
}

$upload_root = realpath(__DIR__ . '/../upload');
if ($upload_root === false) {
    fwrite(STDERR, "upload/ introuvable.\n");
    exit(1);
}

$downloaded = 0;
$skipped = 0;
$failed = 0;

foreach (array_keys($paths) as $rel) {
    $dest = $upload_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($dest);
    if (!is_dir($dir) && !$dry) {
        mkdir($dir, 0755, true);
    }

    $stem = pathinfo($rel, PATHINFO_FILENAME);
    $webp_dest = $dir . DIRECTORY_SEPARATOR . $stem . '.webp';
    if (is_file($dest) || is_file($webp_dest)) {
        $skipped++;
        echo "Déjà présent : {$rel}\n";
        continue;
    }

    $url = $base_url . str_replace(' ', '%20', $rel);
    echo ($dry ? '[dry-run] ' : '') . "GET {$url}\n";

    if ($dry) {
        continue;
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'follow_location' => 1],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 100) {
        $failed++;
        fwrite(STDERR, "Échec : {$url}\n");
        continue;
    }

    if (file_put_contents($dest, $data) === false) {
        $failed++;
        fwrite(STDERR, "Écriture impossible : {$dest}\n");
        continue;
    }
    $downloaded++;
    echo "OK → {$rel} (" . strlen($data) . " o)\n";
}

echo "\nTéléchargement : {$downloaded} ok, {$skipped} ignoré(s), {$failed} échec(s).\n";

if ($dry || $downloaded === 0 && $skipped === 0) {
    exit($failed > 0 ? 1 : 0);
}

if (!$dry && $downloaded > 0) {
    echo "\nLancement conversion (même nom de base)…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/optimize_existing_images.php') . ' produits', $code);
    exit($code);
}

if (!$dry && $skipped > 0 && $downloaded === 0) {
    echo "Fichiers déjà là — conversion seulement si des jpg/png sans .webp :\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/optimize_existing_images.php') . ' produits', $code);
    exit($code);
}
