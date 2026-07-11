<?php
/**
 * Certification vendeurs (Standard / VIP / Premium).
 *
 * Usage : php migrations/run_migrate_vendeur_certification.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function vc_col_exists(PDO $db, string $table, string $col): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function vc_table_exists(PDO $db, string $table): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

echo "Migration certification vendeurs…\n";

if (vc_table_exists($db, 'admin')) {
    if (!vc_col_exists($db, 'admin', 'certification_niveau')) {
        $db->exec("
            ALTER TABLE `admin`
            ADD COLUMN `certification_niveau` ENUM('standard','vip','premium') NULL DEFAULT NULL
            COMMENT 'Niveau certification approuvé'
            AFTER `boutique_nom`
        ");
        echo "  + admin.certification_niveau\n";
    }
    if (!vc_col_exists($db, 'admin', 'certification_date')) {
        $after = vc_col_exists($db, 'admin', 'certification_niveau') ? 'certification_niveau' : 'boutique_nom';
        $db->exec("
            ALTER TABLE `admin`
            ADD COLUMN `certification_date` DATETIME NULL DEFAULT NULL
            AFTER `$after`
        ");
        echo "  + admin.certification_date\n";
    }
}

if (!vc_table_exists($db, 'vendeur_certification_demandes')) {
    $db->exec("
        CREATE TABLE `vendeur_certification_demandes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `admin_id` INT(11) NOT NULL,
            `niveau` ENUM('standard','vip','premium') NOT NULL,
            `statut` ENUM('en_attente','approuvee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
            `nom` VARCHAR(120) NOT NULL,
            `prenom` VARCHAR(120) NOT NULL DEFAULT '',
            `email` VARCHAR(190) NOT NULL,
            `telephone` VARCHAR(40) NULL DEFAULT NULL,
            `boutique_nom` VARCHAR(190) NOT NULL,
            `boutique_region` VARCHAR(64) NULL DEFAULT NULL,
            `adresse_exacte` TEXT NULL,
            `description_activite` TEXT NULL,
            `numero_registre` VARCHAR(80) NULL DEFAULT NULL COMMENT 'NINEA / RC',
            `photo_local_1` VARCHAR(255) NULL DEFAULT NULL,
            `photo_local_2` VARCHAR(255) NULL DEFAULT NULL,
            `photo_local_3` VARCHAR(255) NULL DEFAULT NULL,
            `photo_document` VARCHAR(255) NULL DEFAULT NULL,
            `photo_piece_identite` VARCHAR(255) NULL DEFAULT NULL,
            `message_demande` TEXT NULL,
            `motif_refus` TEXT NULL,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_traitement` DATETIME NULL DEFAULT NULL,
            `traite_par` INT(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_admin_id` (`admin_id`),
            KEY `idx_statut` (`statut`),
            KEY `idx_niveau` (`niveau`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table vendeur_certification_demandes\n";
} else {
    echo "  table vendeur_certification_demandes déjà présente.\n";
    if (!vc_col_exists($db, 'vendeur_certification_demandes', 'photo_piece_identite')) {
        $db->exec("
            ALTER TABLE `vendeur_certification_demandes`
            ADD COLUMN `photo_piece_identite` VARCHAR(255) NULL DEFAULT NULL
            COMMENT 'Photo pièce d''identité'
            AFTER `photo_document`
        ");
        echo "  + vendeur_certification_demandes.photo_piece_identite\n";
    }
    if (!vc_col_exists($db, 'vendeur_certification_demandes', 'vendeur_notif_lue')) {
        $db->exec("
            ALTER TABLE `vendeur_certification_demandes`
            ADD COLUMN `vendeur_notif_lue` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'Notification vendeur lue (validation/refus)'
            AFTER `traite_par`
        ");
        echo "  + vendeur_certification_demandes.vendeur_notif_lue\n";
        $db->exec("UPDATE vendeur_certification_demandes SET vendeur_notif_lue = 1 WHERE date_traitement IS NOT NULL");
    }
}

echo "Terminé.\n";