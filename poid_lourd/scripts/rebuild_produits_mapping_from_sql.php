<?php
/**
 * Reconstruit le mapping produit_xxx.jpg → img_yyy.webp quand le log batch est perdu.
 * Hypothèse : le batch a traité les fichiers dans l'ordre du scan disque (alphabétique)
 * et créé les img_*.webp dans l'ordre chronologique (mtime).
 *
 * Usage : php scripts/rebuild_produits_mapping_from_sql.php --dry-run
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$dry = in_array('--dry-run', $argv, true);
require __DIR__ . '/../conn/conn.php';

$sql_file = __DIR__ . '/../tresor_afri (6).sql';
if (!is_file($sql_file)) {
    fwrite(STDERR, "Dump SQL introuvable.\n");
    exit(1);
}

$content = file_get_contents($sql_file);
if ($content === false) {
    exit(1);
}

$paths = [];
if (preg_match_all("/'produits\\/produit_[^']+\\.(jpg|jpeg|png|gif)'/i", $content, $m)) {
    foreach ($m[0] as $quoted) {
        $p = trim($quoted, "'");
        $p = str_replace('\\/', '/', $p);
        $paths[$p] = true;
    }
}

$originals = array_keys($paths);
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

echo 'Sources (jpg/png/gif) : ' . count($originals) . "\n";
echo 'Cibles img_*.webp : ' . count($imgs) . "\n";

if (count($originals) !== count($imgs)) {
    fwrite(STDERR, "Comptes différents — mapping par ordre impossible sans ajustement.\n");
    $n = min(count($originals), count($imgs));
    echo "On tente les {$n} premières paires.\n";
} else {
    $n = count($originals);
}

$mappings = [];
for ($i = 0; $i < $n; $i++) {
    $old = $originals[$i];
    $wrong = $imgs[$i]['rel'];
    $stem = pathinfo($old, PATHINFO_FILENAME);
    $target = 'produits/' . $stem . '.webp';
    $mappings[] = ['old' => $old, 'wrong' => $wrong, 'target' => $target];
}

$log_lines = [];
foreach ($mappings as $map) {
    $log_lines[] = 'OK ' . $map['old'] . ' → ' . $map['wrong'];
    echo $map['old'] . ' → ' . $map['wrong'] . ' → ' . $map['target'] . "\n";
}

if ($dry) {
    echo "\nDry-run — écrivez le log puis :\n";
    echo "  php scripts/restore_images_same_basename.php scripts/rebuilt_produits_mapping.log\n";
    exit(0);
}

$log_path = __DIR__ . '/rebuilt_produits_mapping.log';
file_put_contents($log_path, implode("\n", $log_lines) . "\n");
echo "\nLog écrit : {$log_path}\n";
echo "Exécutez : php scripts/restore_images_same_basename.php {$log_path}\n";
