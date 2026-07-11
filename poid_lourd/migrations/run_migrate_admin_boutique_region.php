<?php
/**
 * Colonne boutique_region sur admin (région Sénégal du vendeur).
 *
 * Usage : php migrations/run_migrate_admin_boutique_region.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists_abr(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function column_exists_abr(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec_abr(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration admin.boutique_region…\n";

if (!table_exists_abr($db, 'admin')) {
    echo "  Table admin absente.\n";
    exit(1);
}

if (!column_exists_abr($db, 'admin', 'boutique_region')) {
    $after = column_exists_abr($db, 'admin', 'boutique_adresse') ? 'boutique_adresse' : 'boutique_nom';
    safe_exec_abr($db, "
        ALTER TABLE `admin`
        ADD COLUMN `boutique_region` VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'Région Sénégal de la boutique (code slug)'
        AFTER `$after`
    ");
    echo "  + colonne admin.boutique_region\n";
} else {
    echo "  colonne admin.boutique_region déjà présente.\n";
}

echo "Terminé.\n";
