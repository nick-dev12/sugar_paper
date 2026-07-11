<?php
/**
 * Rétablit les chemins image_principale d'origine (avant réparation erronée).
 * N'altère que la BDD — les fichiers doivent ensuite être reconvertis avec
 * optimize_existing_images.php depuis une sauvegarde upload/produits.
 *
 * Usage : php scripts/reset_produits_image_paths.php
 */
if (PHP_SAPI !== 'cli') exit(1);

$snapshot = __DIR__ . '/snapshots/produits_image_principale_avant_reparation.json';
if (!is_file($snapshot)) {
    fwrite(STDERR, "Snapshot introuvable.\n");
    exit(1);
}

require __DIR__ . '/../conn/conn.php';
$rows = json_decode(file_get_contents($snapshot), true);
if (!is_array($rows)) {
    fwrite(STDERR, "Snapshot invalide.\n");
    exit(1);
}

$up = $db->prepare('UPDATE produits SET image_principale = :p WHERE id = :id');
foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $path = trim((string) ($row['image_principale'] ?? ''));
    if ($id <= 0 || $path === '') {
        continue;
    }
    $up->execute(['p' => $path, 'id' => $id]);
    echo "id {$id} → {$path}\n";
}
echo "Chemins produits rétablis en BDD.\n";
echo "Restaurez upload/produits depuis une sauvegarde, puis :\n";
echo "  php scripts/optimize_existing_images.php produits\n";
