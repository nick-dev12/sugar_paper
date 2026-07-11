<?php
/**
 * @deprecated Ne pas utiliser — associe des images au hasard.
 * Préférer : restore_images_same_basename.php + reset_produits_image_paths.php
 */
fwrite(STDERR, "Script désactivé : il mélange les images produit/catégorie.\n");
fwrite(STDERR, "Utilisez scripts/restore_images_same_basename.php à la place.\n");
exit(1);

/**
 * Répare les produits dont l'image pointe encore vers produit_*.jpg|.webp supprimé.
 * Apparie les N chemins manquants avec les N premiers img_*.webp (mtime) non référencés.
 *
 * Usage : php scripts/repair_produits_missing_by_order.php --dry-run
 */
if (PHP_SAPI !== 'cli') exit(1);
$dry = in_array('--dry-run', $argv, true);
require __DIR__ . '/../conn/conn.php';
require __DIR__ . '/../includes/image_optimizer_db.php';
$root = dirname(__DIR__) . '/upload/';

$stmt = $db->query("SELECT id, image_principale, images FROM produits WHERE image_principale IS NOT NULL AND image_principale != ''");
$missing = [];
$used = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $p = trim((string) $r['image_principale']);
    if (is_file($root . str_replace('/', DIRECTORY_SEPARATOR, $p))) {
        $used[] = $p;
        continue;
    }
    $missing[] = $r;
}

$imgs = [];
foreach (glob($root . 'produits/img_*.webp') ?: [] as $abs) {
    $base = pathinfo($abs, PATHINFO_FILENAME);
    if (str_ends_with($base, '_md') || str_ends_with($base, '_sm')) continue;
    $rel = 'produits/' . basename($abs);
    if (in_array($rel, $used, true)) continue;
    $imgs[] = ['rel' => $rel, 'mtime' => filemtime($abs)];
}
usort($imgs, fn($a, $b) => $a['mtime'] <=> $b['mtime']);
usort($missing, fn($a, $b) => strcmp((string)$a['image_principale'], (string)$b['image_principale']));

echo 'Manquants: ' . count($missing) . ', img libres: ' . count($imgs) . "\n";
if (count($missing) === 0) {
    echo "Rien à réparer.\n";
    exit(0);
}
if (count($imgs) < count($missing)) {
    fwrite(STDERR, "Pas assez de fichiers img_ libres.\n");
    exit(1);
}

$imgs = array_slice($imgs, 0, count($missing));
$up = $db->prepare('UPDATE produits SET image_principale = :n WHERE id = :id');
$upj = $db->prepare('UPDATE produits SET images = :images WHERE id = :id');

foreach ($missing as $i => $row) {
    $old = (string) $row['image_principale'];
    $new = $imgs[$i]['rel'];
    echo "id {$row['id']} : {$old} → {$new}\n";
    if ($dry) continue;
    $up->execute(['n' => $new, 'id' => (int)$row['id']]);
    $decoded = json_decode((string)($row['images'] ?? ''), true);
    if (is_array($decoded)) {
        $chg = false;
        foreach ($decoded as $k => $v) {
            if (trim((string)$v) === $old) { $decoded[$k] = $new; $chg = true; }
        }
        if ($chg) {
            $upj->execute(['images' => json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE), 'id' => (int)$row['id']]);
        }
    }
}

echo $dry ? "\nDry-run.\n" : "\nTerminé.\n";
