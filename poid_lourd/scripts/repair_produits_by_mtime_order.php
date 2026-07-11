<?php
/**
 * Répare image_principale / images JSON des produits quand les fichiers ont été
 * renommés en img_xxxx.webp sans journal de correspondance.
 * Apparie par ordre de modification (mtime) des WebP ↔ ordre des id produit en BDD.
 *
 * Usage : php scripts/repair_produits_by_mtime_order.php
 *         php scripts/repair_produits_by_mtime_order.php --dry-run
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$dry_run = in_array('--dry-run', $argv, true);

$conn = __DIR__ . '/../conn/conn.php';
if (!is_file($conn)) {
    fwrite(STDERR, "conn/conn.php introuvable.\n");
    exit(1);
}
require_once $conn;
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "PDO indisponible.\n");
    exit(1);
}

$upload_root = realpath(__DIR__ . '/../upload/produits');
if ($upload_root === false) {
    fwrite(STDERR, "Dossier upload/produits introuvable.\n");
    exit(1);
}

$webp_files = [];
foreach (glob($upload_root . DIRECTORY_SEPARATOR . 'img_*.webp') ?: [] as $abs) {
    $base = pathinfo($abs, PATHINFO_FILENAME);
    if (str_ends_with($base, '_md') || str_ends_with($base, '_sm')) {
        continue;
    }
    $webp_files[] = [
        'rel' => 'produits/' . basename($abs),
        'mtime' => (int) filemtime($abs),
    ];
}
usort($webp_files, static function ($a, $b) {
    return $a['mtime'] <=> $b['mtime'];
});

$stmt = $db->query("
    SELECT id, image_principale, images
    FROM produits
    WHERE image_principale IS NOT NULL AND image_principale != ''
    ORDER BY id ASC
");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$needs_fix = [];
foreach ($rows as $row) {
    $old = trim(str_replace('\\', '/', (string) ($row['image_principale'] ?? '')));
    if ($old === '') {
        continue;
    }
    $abs_old = dirname(__DIR__) . '/upload/' . str_replace('/', DIRECTORY_SEPARATOR, $old);
    if (is_file($abs_old)) {
        continue;
    }
    $needs_fix[] = $row;
}

echo 'Produits à réparer : ' . count($needs_fix) . "\n";
echo 'Fichiers img_*.webp : ' . count($webp_files) . "\n";

if (count($needs_fix) !== count($webp_files)) {
    fwrite(STDERR, "Les quantités ne correspondent pas — réparation automatique annulée.\n");
    fwrite(STDERR, "Restaurez upload/ depuis une sauvegarde ou fournissez un log optimize (repair_from_optimize_log.php).\n");
    exit(1);
}

$update_main = $db->prepare('UPDATE produits SET image_principale = :new WHERE id = :id');
$update_json = $db->prepare('UPDATE produits SET images = :images WHERE id = :id');
$fixed = 0;

foreach ($needs_fix as $i => $row) {
    $old = trim(str_replace('\\', '/', (string) ($row['image_principale'] ?? '')));
    $new = (string) $webp_files[$i]['rel'];
    echo "id {$row['id']} : {$old} → {$new}\n";

    if ($dry_run) {
        continue;
    }

    $update_main->execute(['new' => $new, 'id' => (int) $row['id']]);

    $images_raw = (string) ($row['images'] ?? '');
    if ($images_raw !== '') {
        $decoded = json_decode($images_raw, true);
        if (is_array($decoded)) {
            $changed = false;
            foreach ($decoded as $k => $item) {
                if (trim((string) $item) === $old) {
                    $decoded[$k] = $new;
                    $changed = true;
                }
            }
            if ($changed) {
                $update_json->execute([
                    'images' => json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE),
                    'id' => (int) $row['id'],
                ]);
            }
        }
    }

    $fixed++;
}

echo $dry_run
    ? "\nDry-run terminé (aucune écriture BDD).\n"
    : "\n{$fixed} produit(s) réparé(s).\n";
