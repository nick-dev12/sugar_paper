<?php
/**
 * Colonne image sur categories_generales (rayons catalogue — visuel menu).
 *
 * Usage : php migrations/run_migrate_categories_generales_image.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists_cgi(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function column_exists_cgi(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec_cgi(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration categories_generales.image…\n";

if (!table_exists_cgi($db, 'categories_generales')) {
    echo "  Table categories_generales absente — exécutez d’abord run_migrate_categories_generales_table.php.\n";
    exit(1);
}

if (!column_exists_cgi($db, 'categories_generales', 'image')) {
    safe_exec_cgi($db, "
        ALTER TABLE `categories_generales`
        ADD COLUMN `image` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Visuel rayon (upload/categories/…)'
        AFTER `icone`
    ");
    echo "  + colonne categories_generales.image\n";
} else {
    echo "  colonne categories_generales.image déjà présente.\n";
}

echo "Terminé.\n";
