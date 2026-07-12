<?php
/**
 * Migration module Invoice — bons de livraison B2B (production-safe, sans FK bloquantes).
 * php migrations/run_migrate_invoice_bl.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();
$migrations_dir = __DIR__;

echo "=== Migration Invoice / BL ===\n\n";

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('SET FOREIGN_KEY_CHECKS=0');

    $admin_id_type = mig_get_column_type($db, 'admin', 'id');
    if ($admin_id_type === '') {
        $admin_id_type = 'int(11)';
    }

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `clients_b2b` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `raison_sociale` VARCHAR(255) NOT NULL,
  `nom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `prenom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `adresse` TEXT NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_raison` (`raison_sociale`(100)),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table clients_b2b');

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_bl` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `devis_id` INT(11) NULL DEFAULT NULL,
  `admin_createur_id` $admin_id_type NULL DEFAULT NULL,
  `statut` ENUM('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `date_bl` DATE NOT NULL,
  `total_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_bl` (`numero_bl`),
  KEY `idx_client` (`client_b2b_id`),
  KEY `idx_statut_date` (`statut`,`date_bl`),
  KEY `idx_devis` (`devis_id`),
  KEY `idx_bl_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table bons_livraison');

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `bl_lignes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bl_id` INT(11) NOT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `designation` VARCHAR(500) NOT NULL,
  `quantite` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `prix_unitaire_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_ligne_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bl` (`bl_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table bl_lignes');

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `factures_mensuelles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_facture` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `annee` SMALLINT(4) NOT NULL,
  `mois` TINYINT(2) NOT NULL,
  `statut` ENUM('brouillon','validee','payee') NOT NULL DEFAULT 'brouillon',
  `total_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `date_emission` DATE NULL DEFAULT NULL,
  `date_paiement` DATE NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `admin_createur_id` $admin_id_type NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `uniq_client_mois` (`client_b2b_id`,`annee`,`mois`),
  KEY `idx_statut` (`statut`),
  KEY `idx_fm_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table factures_mensuelles');

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `facture_mensuelle_bl` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `facture_mensuelle_id` INT(11) NOT NULL,
  `bl_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bl` (`bl_id`),
  KEY `idx_facture` (`facture_mensuelle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table facture_mensuelle_bl');

    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    echo "\n→ Clés étrangères (optionnelles)…\n";
    if (mig_table_exists($db, 'clients_b2b')) {
        mig_try_add_foreign_key($db, 'bons_livraison', 'fk_bl_client',
            'ALTER TABLE `bons_livraison` ADD CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
        mig_try_add_foreign_key($db, 'factures_mensuelles', 'fk_fm_client',
            'ALTER TABLE `factures_mensuelles` ADD CONSTRAINT `fk_fm_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
    }
    if (mig_table_exists($db, 'admin')) {
        mig_try_add_foreign_key($db, 'bons_livraison', 'fk_bl_admin',
            'ALTER TABLE `bons_livraison` ADD CONSTRAINT `fk_bl_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        mig_try_add_foreign_key($db, 'factures_mensuelles', 'fk_fm_admin',
            'ALTER TABLE `factures_mensuelles` ADD CONSTRAINT `fk_fm_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }
    if (mig_table_exists($db, 'bons_livraison')) {
        mig_try_add_foreign_key($db, 'bl_lignes', 'fk_bl_lignes_bl',
            'ALTER TABLE `bl_lignes` ADD CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        mig_try_add_foreign_key($db, 'facture_mensuelle_bl', 'fk_fmb_facture',
            'ALTER TABLE `facture_mensuelle_bl` ADD CONSTRAINT `fk_fmb_facture` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        mig_try_add_foreign_key($db, 'facture_mensuelle_bl', 'fk_fmb_bl',
            'ALTER TABLE `facture_mensuelle_bl` ADD CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
    if (mig_table_exists($db, 'produits')) {
        mig_try_add_foreign_key($db, 'bl_lignes', 'fk_bl_lignes_produit',
            'ALTER TABLE `bl_lignes` ADD CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    echo "\n→ Remise globale devis / BL…\n";
    $code_remise = mig_run_php_script($migrations_dir . '/run_add_devis_bl_remise_globale.php');
    if ($code_remise !== 0) {
        throw new RuntimeException('run_add_devis_bl_remise_globale.php a échoué');
    }

    echo "\n→ Colonnes complémentaires devis / BL…\n";
    $complement = $migrations_dir . '/run_add_devis_bl_columns_complement.php';
    if (is_file($complement)) {
        $code = mig_run_php_script($complement);
        if ($code !== 0) {
            throw new RuntimeException('run_add_devis_bl_columns_complement.php a échoué');
        }
    } else {
        mig_exec_sql_file($db, $migrations_dir . '/add_devis_bl_adresse_client.sql', 'Adresse client devis/BL');
        mig_exec_sql_file($db, $migrations_dir . '/add_devis_bl_factures_tva.sql', 'Colonnes TVA devis/BL');
    }

    echo "\n→ Types client BL + plafonds contacts…\n";
    $code1 = mig_run_php_script($migrations_dir . '/run_add_types_client_bl.php');
    if ($code1 !== 0) {
        throw new RuntimeException('run_add_types_client_bl.php a échoué');
    }

    echo "\n→ Adresse / plafond contacts…\n";
    $code2 = mig_run_php_script($migrations_dir . '/run_add_contacts_adresse_plafond_bl.php');
    if ($code2 !== 0) {
        throw new RuntimeException('run_add_contacts_adresse_plafond_bl.php a échoué');
    }

    echo "\n→ Paiement facture BL…\n";
    $code3 = mig_run_php_script($migrations_dir . '/run_add_bl_facture_paiement.php');
    if ($code3 !== 0) {
        throw new RuntimeException('run_add_bl_facture_paiement.php a échoué');
    }

    mig_exec_sql_file($db, $migrations_dir . '/bl_statut_unify_valide.sql', 'Statuts BL unifiés');

    echo "\nMigration Invoice / BL terminée avec succès.\n";
} catch (Throwable $e) {
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
