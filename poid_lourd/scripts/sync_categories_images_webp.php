<?php
/**
 * Met à jour categories.image et categories_generales.image :
 * foo.png / foo.jpg → foo.webp quand le fichier .webp existe sur le disque.
 *
 * Usage : php scripts/sync_categories_images_webp.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$conn = __DIR__ . '/../conn/conn.php';
if (!is_file($conn)) {
    fwrite(STDERR, "conn/conn.php introuvable.\n");
    exit(1);
}
require_once $conn;
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion PDO indisponible.\n");
    exit(1);
}

$db_name = image_db_current_database($db);
echo 'Base connectée : ' . ($db_name !== '' ? $db_name : '(inconnue)') . "\n";

$details = [];
$total = 0;
$total += image_db_sync_table_column($db, 'categories', 'image', $details);
$total += image_db_sync_table_column($db, 'categories_generales', 'image', $details);

echo "Synchronisation catégories : {$total} enregistrement(s) mis à jour.\n";
foreach ($details as $label => $count) {
    if ((int) $count > 0) {
        echo "  - {$label} : {$count}\n";
    }
}

if ($total === 0) {
    echo "\nAucune mise à jour. Vérifiez :\n";
    echo "  1) conn/conn.php pointe vers la bonne base (ex. jomas_colobane1)\n";
    echo "  2) les fichiers .webp existent dans upload/categories/\n";
    echo "  3) php scripts/diagnose_image_paths.php --missing\n";
}
