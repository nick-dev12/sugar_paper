<?php
/**
 * Vide toutes les tables de la base locale (données uniquement, schéma conservé).
 * Usage : php scripts/vider_tables_locales.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db instanceof PDO) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
echo "=== Vidage des tables — base : $dbName ===\n\n";

$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (empty($tables)) {
    echo "Aucune table trouvée.\n";
    exit(0);
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');

$done = 0;
$errors = 0;

foreach ($tables as $table) {
    $table = (string) $table;
    try {
        $db->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
        echo "  OK : $table\n";
        $done++;
    } catch (PDOException $e) {
        echo "  ! $table : " . $e->getMessage() . "\n";
        $errors++;
    }
}

$db->exec('SET FOREIGN_KEY_CHECKS=1');

echo "\n=== Terminé ===\n";
echo "Tables vidées : $done / " . count($tables) . "\n";
if ($errors > 0) {
    echo "Erreurs : $errors\n";
    exit(1);
}

echo "Vous pouvez exporter avec phpMyAdmin ou :\n";
echo "  mysqldump -u root tresor > export_tresor.sql\n";
