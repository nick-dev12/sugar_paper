<?php
/**
 * @deprecated Ne pas utiliser — associe des images au hasard.
 */
fwrite(STDERR, "Script désactivé : il mélange les images.\n");
fwrite(STDERR, "Utilisez scripts/restore_images_same_basename.php à la place.\n");
exit(1);

/**
 * Répare les catégories restantes en appariant par ordre alphabétique / mtime.
 */
if (PHP_SAPI !== 'cli') exit(1);
$dry = in_array('--dry-run', $argv, true);
require __DIR__ . '/../conn/conn.php';
require __DIR__ . '/../includes/image_optimizer_db.php';
$root = dirname(__DIR__) . '/upload/';
$cat_dir = $root . 'categories/';

$stmt = $db->query("SELECT id, image FROM categories WHERE image IS NOT NULL AND image != ''");
$missing = [];
$used_webp = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $p = trim((string) $r['image']);
    if (is_file($root . str_replace('/', DIRECTORY_SEPARATOR, $p))) {
        if (str_ends_with(strtolower($p), '.webp')) {
            $used_webp[] = $p;
        }
        continue;
    }
    $missing[] = $r;
}

$imgs = [];
foreach (glob($cat_dir . 'img_*.webp') ?: [] as $abs) {
    $base = pathinfo($abs, PATHINFO_FILENAME);
    if (str_ends_with($base, '_md') || str_ends_with($base, '_sm')) continue;
    $rel = 'categories/' . basename($abs);
    if (in_array($rel, $used_webp, true)) continue;
    $imgs[] = ['rel' => $rel, 'mtime' => filemtime($abs)];
}
usort($imgs, fn($a, $b) => $a['mtime'] <=> $b['mtime']);

usort($missing, fn($a, $b) => strcmp((string)$a['image'], (string)$b['image']));

echo 'Manquantes: ' . count($missing) . ', img libres: ' . count($imgs) . "\n";
if (count($missing) > count($imgs)) {
    fwrite(STDERR, "Pas assez de fichiers img_ libres.\n");
    exit(1);
}

$up = $db->prepare('UPDATE categories SET image = :n WHERE id = :id');
$n = min(count($missing), count($imgs));
for ($i = 0; $i < $n; $i++) {
    $old = $missing[$i]['image'];
    $new = $imgs[$i]['rel'];
    echo "id {$missing[$i]['id']} : {$old} → {$new}\n";
    if (!$dry) {
        $up->execute(['n' => $new, 'id' => (int)$missing[$i]['id']]);
    }
}
