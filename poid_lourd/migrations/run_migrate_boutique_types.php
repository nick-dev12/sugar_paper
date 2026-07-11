<?php
/**
 * Types de boutique (liste plateforme) + liaison admin.boutique_type_id.
 *
 * Usage : php migrations/run_migrate_boutique_types.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function bt_table_exists(PDO $db, string $table): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function bt_column_exists(PDO $db, string $table, string $col): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function bt_safe_exec(PDO $db, string $sql): bool
{
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration types de boutique…\n";

if (!bt_table_exists($db, 'boutique_types')) {
    bt_safe_exec($db, "
        CREATE TABLE `boutique_types` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nom` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `sort_ordre` INT(11) NOT NULL DEFAULT 0,
            `actif` TINYINT(1) NOT NULL DEFAULT 1,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_boutique_types_nom` (`nom`),
            KEY `idx_boutique_types_actif_sort` (`actif`, `sort_ordre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table boutique_types\n";
} else {
    echo "  table boutique_types déjà présente.\n";
}

if (!bt_table_exists($db, 'admin')) {
    echo "  Table admin absente — colonne boutique_type_id non ajoutée.\n";
    exit(1);
}

if (!bt_column_exists($db, 'admin', 'boutique_type_id')) {
    $after = bt_column_exists($db, 'admin', 'boutique_country')
        ? 'boutique_country'
        : (bt_column_exists($db, 'admin', 'boutique_region')
            ? 'boutique_region'
            : 'boutique_nom');
    bt_safe_exec($db, "
        ALTER TABLE `admin`
        ADD COLUMN `boutique_type_id` INT(11) NULL DEFAULT NULL
        COMMENT 'Type de boutique (référence boutique_types)'
        AFTER `$after`,
        ADD KEY `idx_admin_boutique_type_id` (`boutique_type_id`)
    ");
    echo "  + colonne admin.boutique_type_id\n";
} else {
    echo "  colonne admin.boutique_type_id déjà présente.\n";
}

echo "Terminé.\n";
