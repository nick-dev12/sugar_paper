<?php
/**
 * Restaure produits/*.webp (même nom de base) via mapping batch reconstruit :
 * toutes les sources connues (BDD + sitemap + dump SQL) triées alphabétiquement
 * ↔ img_*.webp triés par mtime.
 *
 * Usage : php scripts/restore_produits_from_all_sources.php --dry-run
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$dry = in_array('--dry-run', $argv, true);

require __DIR__ . '/../conn/conn.php';
require __DIR__ . '/../includes/image_optimizer_db.php';

$sources = [];

function collect_paths_from_text($text, array &$sources) {
    if (preg_match_all('#produits/produit_[a-zA-Z0-9._-]+\.(jpg|jpeg|png|gif|webp)#i', $text, $m)) {
        foreach ($m[0] as $p) {
            $p = strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'webp'
                ? $p
                : $p;
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                $sources[$p] = true;
            }
        }
    }
}

$stmt = $db->query('SELECT image_principale, images FROM produits');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ([$r['image_principale'], $r['images']] as $raw) {
        if ($raw === null || $raw === '') {
            continue;
        }
        if (str_starts_with((string) $raw, '[')) {
            $arr = json_decode((string) $raw, true);
            if (is_array($arr)) {
                collect_paths_from_text(implode(' ', $arr), $sources);
            }
        } else {
            collect_paths_from_text((string) $raw, $sources);
        }
    }
}

foreach (['../tresor_afri (6).sql', '../sitemap.xml'] as $rel) {
    $f = __DIR__ . '/' . $rel;
    if (is_file($f)) {
        $t = file_get_contents($f);
        if ($t !== false) {
            $t = str_replace(['\\/', '\\\\/'], '/', $t);
            collect_paths_from_text($t, $sources);
        }
    }
}

$originals = array_keys($sources);
sort($originals, SORT_STRING);

$root = realpath(__DIR__ . '/../upload');
$imgs = [];
foreach (glob($root . DIRECTORY_SEPARATOR . 'produits' . DIRECTORY_SEPARATOR . 'img_*.webp') ?: [] as $abs) {
    $base = pathinfo($abs, PATHINFO_FILENAME);
    if (str_ends_with($base, '_md') || str_ends_with($base, '_sm')) {
        continue;
    }
    $imgs[] = ['rel' => 'produits/' . basename($abs), 'mtime' => (int) filemtime($abs)];
}
usort($imgs, fn($a, $b) => $a['mtime'] <=> $b['mtime']);

echo 'Sources connues (jpg/png/gif) : ' . count($originals) . "\n";
echo 'Fichiers img_*.webp : ' . count($imgs) . "\n";

if (count($originals) !== count($imgs)) {
    fwrite(STDERR, "Écart de " . abs(count($originals) - count($imgs)) . " — mapping partiel.\n");
}

$n = min(count($originals), count($imgs));
$log_lines = [];
$renamed = 0;
$db_updates = 0;

for ($i = 0; $i < $n; $i++) {
    $old = $originals[$i];
    $wrong = $imgs[$i]['rel'];
    $stem = pathinfo($old, PATHINFO_FILENAME);
    $target = 'produits/' . $stem . '.webp';

    $log_lines[] = 'OK ' . $old . ' → ' . $wrong;
    echo $old . "\n  → " . $wrong . "\n  → " . $target . "\n";

    if ($dry) {
        continue;
    }

    $wrong_base = pathinfo($wrong, PATHINFO_FILENAME);
    $dir_abs = $root . DIRECTORY_SEPARATOR . 'produits';
    foreach (['', '_md', '_sm'] as $suffix) {
        $src = $dir_abs . DIRECTORY_SEPARATOR . $wrong_base . $suffix . '.webp';
        $tgt = $dir_abs . DIRECTORY_SEPARATOR . $stem . $suffix . '.webp';
        if (!is_file($src)) {
            continue;
        }
        if (is_file($tgt) && realpath($src) !== realpath($tgt)) {
            fwrite(STDERR, "Conflit : {$tgt}\n");
            continue;
        }
        if (@rename($src, $tgt)) {
            $renamed++;
        }
    }
    image_db_apply_path_mapping($db, $old, $target);
    image_db_apply_path_mapping($db, $wrong, $target);
    $db_updates++;
}

echo "\nTerminé : {$renamed} renommage(s), {$db_updates} mise(s) à jour BDD.\n";
if ($dry) {
    echo "(Dry-run)\n";
    $log = __DIR__ . '/rebuilt_produits_mapping.log';
    file_put_contents($log, implode("\n", $log_lines) . "\n");
    echo "Log : {$log}\n";
}
