<?php
/**
 * Met à jour les chemins d'images en BDD vers leurs équivalents WebP.
 *
 * Usage :
 *   php scripts/sync_image_paths_database.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion PDO indisponible.\n");
    exit(1);
}

$result = image_db_sync_all_image_paths($db);
echo "Chemins mis à jour : {$result['updated']}\n";
foreach ($result['details'] as $column => $count) {
    echo " - {$column} : {$count}\n";
}
exit(0);
