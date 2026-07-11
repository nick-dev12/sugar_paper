<?php
/**
 * Table de liaison N-N : une sous-catégorie plateforme peut être liée à plusieurs rayons (categories_generales).
 * Remplit la table depuis categories.categorie_generale_id pour les lignes existantes.
 *
 * Usage : php migrations/run_migrate_categories_generales_liaisons.php
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

function col_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT SQL : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration categories_categories_generales (liaisons rayons ↔ sous-catégories)…\n";

if (!table_exists($db, 'categories') || !table_exists($db, 'categories_generales')) {
    echo "Tables categories ou categories_generales absentes.\n";
    exit(1);
}

if (!col_exists($db, 'categories', 'categorie_generale_id')) {
    echo "Colonne categories.categorie_generale_id absente. Exécutez d’abord run_migrate_categories_generales_table.php.\n";
    exit(1);
}

if (!table_exists($db, 'categories_categories_generales')) {
    safe_exec($db, "
        CREATE TABLE `categories_categories_generales` (
            `categorie_id` INT(11) NOT NULL,
            `categorie_generale_id` INT(11) NOT NULL,
            PRIMARY KEY (`categorie_id`, `categorie_generale_id`),
            KEY `idx_ccg_generale` (`categorie_generale_id`),
            KEY `idx_ccg_categorie` (`categorie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table categories_categories_generales\n";
} else {
    echo "  table categories_categories_generales déjà présente.\n";
}

if (table_exists($db, 'categories_categories_generales')) {
    try {
        $n = $db->exec("
            INSERT IGNORE INTO `categories_categories_generales` (`categorie_id`, `categorie_generale_id`)
            SELECT c.`id`, c.`categorie_generale_id`
            FROM `categories` c
            WHERE c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0
              AND (c.`admin_id` IS NULL OR c.`admin_id` = 0)
        ");
        echo "  + rattrapage liaisons depuis categories.categorie_generale_id " . ($n !== false ? '(' . (int) $n . ' lignes)' : '') . "\n";
    } catch (PDOException $e) {
        echo "AVERTISSEMENT rattrapage : " . $e->getMessage() . "\n";
    }
}

echo "Terminé.\n";
