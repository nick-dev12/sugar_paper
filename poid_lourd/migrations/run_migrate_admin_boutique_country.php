<?php
/**
 * Colonne boutique_country sur admin (pays du vendeur, détecté à l'inscription).
 *
 * Usage : php migrations/run_migrate_admin_boutique_country.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists_abc(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function column_exists_abc(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec_abc(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration admin.boutique_country…\n";

if (!table_exists_abc($db, 'admin')) {
    echo "  Table admin absente.\n";
    exit(1);
}

if (!column_exists_abc($db, 'admin', 'boutique_country')) {
    $after = column_exists_abc($db, 'admin', 'boutique_region')
        ? 'boutique_region'
        : (column_exists_abc($db, 'admin', 'boutique_adresse') ? 'boutique_adresse' : 'boutique_nom');
    safe_exec_abc($db, "
        ALTER TABLE `admin`
        ADD COLUMN `boutique_country` CHAR(2) NOT NULL DEFAULT 'SN'
        COMMENT 'Pays de la boutique (ISO 3166-1 alpha-2)'
        AFTER `$after`
    ");
    echo "  + colonne admin.boutique_country\n";
} else {
    echo "  colonne admin.boutique_country déjà présente.\n";
}

safe_exec_abc($db, "
    UPDATE `admin`
    SET `boutique_country` = 'SN'
    WHERE `role` = 'vendeur' AND (`boutique_country` IS NULL OR TRIM(`boutique_country`) = '')
");

echo "Terminé.\n";
