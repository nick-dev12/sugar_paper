<?php
/**
 * Répare les chemins BDD à partir du journal produit par optimize_existing_images.php
 * (cas où les fichiers ont été renommés en img_xxxx.webp).
 *
 * Usage : php scripts/repair_image_paths_from_log.php
 *         php scripts/repair_image_paths_from_log.php chemin/vers/mapping.jsonl
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

$log_file = isset($argv[1]) ? (string) $argv[1] : (__DIR__ . '/optimize_image_mapping.jsonl');
if (!is_file($log_file)) {
    fwrite(STDERR, "Journal introuvable : {$log_file}\n");
    fwrite(STDERR, "Relancez optimize_existing_images.php (version corrigée) ou restaurez upload/ depuis une sauvegarde.\n");
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

$lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    fwrite(STDERR, "Impossible de lire le journal.\n");
    exit(1);
}

$applied = 0;
foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (!is_array($row) || empty($row['old']) || empty($row['new'])) {
        continue;
    }
    image_db_apply_path_mapping($db, (string) $row['old'], (string) $row['new']);
    echo "Mapping : {$row['old']} → {$row['new']}\n";
    $applied++;
}

echo "\nTerminé : {$applied} correspondance(s) appliquée(s).\n";
echo "Exécutez ensuite : php scripts/sync_image_paths_database.php\n";
