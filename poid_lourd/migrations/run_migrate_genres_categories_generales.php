<?php
/**
 * Liaison genres ↔ catégories générales (rayons).
 *
 * Usage : php migrations/run_migrate_genres_categories_generales.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration genres ↔ catégories générales…\n";

if (!table_exists($db, 'genres')) {
    echo "Table genres absente. Exécutez d’abord run_migrate_genres.php\n";
    exit(1);
}
if (!table_exists($db, 'categories_generales')) {
    echo "Table categories_generales absente.\n";
    exit(1);
}

if (!table_exists($db, 'genres_categories_generales')) {
    safe_exec($db, "
        CREATE TABLE `genres_categories_generales` (
            `genre_id` INT(11) NOT NULL,
            `categorie_generale_id` INT(11) NOT NULL,
            PRIMARY KEY (`genre_id`, `categorie_generale_id`),
            KEY `idx_gcg_cg` (`categorie_generale_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table genres_categories_generales\n";
} else {
    echo "  table genres_categories_generales déjà présente.\n";
}

echo "Terminé.\n";
