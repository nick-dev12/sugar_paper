<?php
/**
 * Met à jour les chemins d'images en BDD (.png/.jpg → .webp quand le fichier existe).
 *
 * Usage : php scripts/sync_image_paths_database.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

$conn = __DIR__ . '/../conn/conn.php';
if (!is_file($conn)) {
    fwrite(STDERR, "Fichier conn/conn.php introuvable.\n");
    exit(1);
}
require_once $conn;
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion PDO indisponible.\n");
    exit(1);
}

$db_name = function_exists('image_db_current_database') ? image_db_current_database($db) : '';
if ($db_name !== '') {
    echo "Base connectée : {$db_name}\n";
}

$result = image_db_sync_all_image_paths($db);

echo "Synchronisation terminée : " . (int) $result['updated'] . " enregistrement(s) mis à jour.\n";
foreach ($result['details'] as $label => $count) {
    if ((int) $count > 0) {
        echo "  - {$label} : {$count}\n";
    }
}
