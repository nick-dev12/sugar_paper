<?php
/**
 * Table marketplace_hero_affiches
 * php migrations/run_marketplace_hero_table.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;
if (!$db) {
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/create_marketplace_hero_affiches.sql');
try {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $db->exec($stmt);
        }
    }
    echo "Table marketplace_hero_affiches OK.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
