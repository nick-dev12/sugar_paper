<?php
/**
 * Migration : service de localisation exacte (GPS).
 * Colonnes lat/lng sur admin (boutiques), commandes (livraison), users (dernière position).
 *
 * Usage : php migrations/run_add_geo_location.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function geo_mig_column_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function geo_mig_index_exists(PDO $db, string $table, string $index): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i
    ");
    $q->execute(['t' => $table, 'i' => $index]);
    return (int) $q->fetchColumn() > 0;
}

function geo_mig_add_column(PDO $db, string $table, string $col, string $definition): void {
    if (geo_mig_column_exists($db, $table, $col)) {
        echo "  = $table.$col déjà présente.\n";
        return;
    }
    try {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
        echo "  + $table.$col ajoutée.\n";
    } catch (PDOException $e) {
        echo "  ! ERREUR $table.$col : " . $e->getMessage() . "\n";
    }
}

echo "Migration service de localisation exacte…\n\n";

echo "[1/4] Table admin (boutiques)\n";
geo_mig_add_column($db, 'admin', 'boutique_latitude',
    "DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude GPS boutique'");
geo_mig_add_column($db, 'admin', 'boutique_longitude',
    "DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude GPS boutique'");
geo_mig_add_column($db, 'admin', 'boutique_geo_source',
    "ENUM('gps','map_pin','adresse','manuel') NULL DEFAULT NULL COMMENT 'Origine des coordonnées'");
geo_mig_add_column($db, 'admin', 'boutique_geo_maj',
    "DATETIME NULL DEFAULT NULL COMMENT 'Dernière mise à jour position'");

echo "\n[2/4] Table commandes (position client à la validation)\n";
geo_mig_add_column($db, 'commandes', 'delivery_latitude',
    "DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude GPS client à la commande'");
geo_mig_add_column($db, 'commandes', 'delivery_longitude',
    "DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude GPS client à la commande'");
geo_mig_add_column($db, 'commandes', 'delivery_geo_precision',
    "DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'Précision GPS en mètres'");
geo_mig_add_column($db, 'commandes', 'delivery_geo_source',
    "ENUM('gps','map_pin','adresse','ip') NULL DEFAULT NULL COMMENT 'Origine des coordonnées'");
geo_mig_add_column($db, 'commandes', 'delivery_geo_date',
    "DATETIME NULL DEFAULT NULL COMMENT 'Date capture position'");

echo "\n[3/4] Table users (dernière position connue)\n";
geo_mig_add_column($db, 'users', 'last_latitude',
    "DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Dernière latitude connue'");
geo_mig_add_column($db, 'users', 'last_longitude',
    "DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Dernière longitude connue'");
geo_mig_add_column($db, 'users', 'last_geo_precision',
    "DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'Précision en mètres'");
geo_mig_add_column($db, 'users', 'last_geo_date',
    "DATETIME NULL DEFAULT NULL COMMENT 'Date dernière position'");

echo "\n[4/4] Index proximité\n";
if (!geo_mig_index_exists($db, 'admin', 'idx_admin_boutique_geo')) {
    try {
        $db->exec("CREATE INDEX `idx_admin_boutique_geo` ON `admin` (`boutique_latitude`, `boutique_longitude`)");
        echo "  + index idx_admin_boutique_geo créé.\n";
    } catch (PDOException $e) {
        echo "  ! ERREUR index : " . $e->getMessage() . "\n";
    }
} else {
    echo "  = index idx_admin_boutique_geo déjà présent.\n";
}

echo "\nMigration terminée.\n";
