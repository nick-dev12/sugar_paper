<?php
/**
 * Migration : tables suivi GPS livreurs + colonnes commandes.
 *
 * Usage : php migrations/run_add_livreur_tracking.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function livreur_mig_column_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function livreur_mig_table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function livreur_mig_safe_exec(PDO $db, string $sql, string $label): bool {
    try {
        $db->exec($sql);
        echo "  OK : $label\n";
        return true;
    } catch (PDOException $e) {
        echo "  ERREUR ($label) : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "=== Migration suivi livreurs ===\n";

livreur_mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `livreurs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_livreurs_email` (`email`),
  KEY `idx_livreurs_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table livreurs');

livreur_mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `livreur_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `livreur_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `commande_id` INT DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_livreur_sessions_token` (`token_hash`),
  KEY `idx_livreur_sessions_livreur` (`livreur_id`),
  KEY `idx_livreur_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_livreur_sessions_livreur` FOREIGN KEY (`livreur_id`) REFERENCES `livreurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table livreur_sessions');

livreur_mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `livreur_positions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `livreur_id` INT NOT NULL,
  `commande_id` INT DEFAULT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `accuracy` DECIMAL(8,2) DEFAULT NULL,
  `speed` DECIMAL(8,2) DEFAULT NULL,
  `heading` DECIMAL(6,2) DEFAULT NULL,
  `recorded_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_livreur_positions_livreur_date` (`livreur_id`, `recorded_at`),
  KEY `idx_livreur_positions_commande` (`commande_id`),
  CONSTRAINT `fk_livreur_positions_livreur` FOREIGN KEY (`livreur_id`) REFERENCES `livreurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table livreur_positions');

livreur_mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `tracking_watch_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `token_hash` CHAR(64) NOT NULL,
  `commande_id` INT NOT NULL,
  `type` ENUM('admin','client') NOT NULL DEFAULT 'admin',
  `admin_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tracking_watch_token` (`token_hash`),
  KEY `idx_tracking_watch_commande` (`commande_id`),
  KEY `idx_tracking_watch_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table tracking_watch_tokens');

$commande_columns = [
    'livreur_id' => "INT NULL DEFAULT NULL COMMENT 'Livreur assigné' AFTER `notes`",
    'delivery_latitude' => "DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude livraison client' AFTER `livreur_id`",
    'delivery_longitude' => "DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude livraison client' AFTER `delivery_latitude`",
    'tracking_active' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Suivi GPS actif' AFTER `delivery_longitude`",
    'tracking_started_at' => "DATETIME NULL DEFAULT NULL COMMENT 'Début suivi livreur' AFTER `tracking_active`",
];

foreach ($commande_columns as $col => $definition) {
    if (!livreur_mig_column_exists($db, 'commandes', $col)) {
        livreur_mig_safe_exec($db, "ALTER TABLE `commandes` ADD COLUMN `$col` $definition", "commandes.$col");
    } else {
        echo "  commandes.$col déjà présente.\n";
    }
}

$idx_q = $db->prepare("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes' AND INDEX_NAME = 'idx_commandes_livreur'
");
$idx_q->execute();
if ((int) $idx_q->fetchColumn() === 0) {
    livreur_mig_safe_exec($db, "ALTER TABLE `commandes` ADD KEY `idx_commandes_livreur` (`livreur_id`)", 'index idx_commandes_livreur');
}

if (livreur_mig_table_exists($db, 'bons_livraison')) {
    $bl_columns = [
        'livreur_id' => "INT NULL DEFAULT NULL COMMENT 'Livreur assigné (admin.id)' AFTER `notes`",
        'adresse_livraison' => "TEXT NULL DEFAULT NULL COMMENT 'Adresse livraison facture' AFTER `livreur_id`",
        'delivery_latitude' => "DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude livraison client' AFTER `adresse_livraison`",
        'delivery_longitude' => "DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude livraison client' AFTER `delivery_latitude`",
        'tracking_active' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Suivi GPS actif' AFTER `delivery_longitude`",
        'tracking_started_at' => "DATETIME NULL DEFAULT NULL COMMENT 'Début suivi livreur' AFTER `tracking_active`",
    ];
    foreach ($bl_columns as $col => $definition) {
        if (!livreur_mig_column_exists($db, 'bons_livraison', $col)) {
            livreur_mig_safe_exec($db, "ALTER TABLE `bons_livraison` ADD COLUMN `$col` $definition", "bons_livraison.$col");
        } else {
            echo "  bons_livraison.$col déjà présente.\n";
        }
    }

    $idx_bl = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bons_livraison' AND INDEX_NAME = 'idx_bons_livraison_livreur'
    ");
    $idx_bl->execute();
    if ((int) $idx_bl->fetchColumn() === 0) {
        livreur_mig_safe_exec($db, "ALTER TABLE `bons_livraison` ADD KEY `idx_bons_livraison_livreur` (`livreur_id`)", 'index idx_bons_livraison_livreur');
    }
}

if (livreur_mig_table_exists($db, 'tracking_watch_tokens')) {
    if (!livreur_mig_column_exists($db, 'tracking_watch_tokens', 'bl_id')) {
        livreur_mig_safe_exec($db, "ALTER TABLE `tracking_watch_tokens` ADD COLUMN `bl_id` INT NULL DEFAULT NULL COMMENT 'Facture B2B' AFTER `commande_id`", 'tracking_watch_tokens.bl_id');
        livreur_mig_safe_exec($db, "ALTER TABLE `tracking_watch_tokens` ADD KEY `idx_tracking_watch_bl` (`bl_id`)", 'index idx_tracking_watch_bl');
    }
    try {
        $db->exec("ALTER TABLE `tracking_watch_tokens` MODIFY COLUMN `commande_id` INT NULL DEFAULT NULL");
        echo "  OK : tracking_watch_tokens.commande_id nullable\n";
    } catch (PDOException $e) {
        echo "  tracking_watch_tokens.commande_id : " . $e->getMessage() . "\n";
    }
}

if (livreur_mig_table_exists($db, 'livreur_positions')) {
    if (!livreur_mig_column_exists($db, 'livreur_positions', 'bl_id')) {
        livreur_mig_safe_exec($db, "ALTER TABLE `livreur_positions` ADD COLUMN `bl_id` INT NULL DEFAULT NULL COMMENT 'Facture B2B' AFTER `commande_id`", 'livreur_positions.bl_id');
        livreur_mig_safe_exec($db, "ALTER TABLE `livreur_positions` ADD KEY `idx_livreur_positions_bl` (`bl_id`)", 'index idx_livreur_positions_bl');
    }
}

if (!livreur_mig_table_exists($db, 'livreurs')) {
    echo "Échec : table livreurs absente.\n";
    exit(1);
}

echo "=== Migration terminée avec succès ===\n";
