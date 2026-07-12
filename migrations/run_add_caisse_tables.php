<?php
/**
 * Tables caisse magasin (ventes au comptoir).
 * Usage : php migrations/run_add_caisse_tables.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$admin_id_type = mig_get_column_type($db, 'admin', 'id');
if ($admin_id_type === '') {
    $admin_id_type = 'int(11)';
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');

mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` $admin_id_type NOT NULL,
  `caissier_id` $admin_id_type NULL DEFAULT NULL,
  `numero_ticket` VARCHAR(32) NOT NULL,
  `reference` VARCHAR(64) NULL DEFAULT NULL,
  `montant_total` DECIMAL(12,2) NOT NULL,
  `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `mode_paiement` ENUM('especes','carte','orange_money','wave','cheque','mixte','autre') NOT NULL DEFAULT 'especes',
  `montant_especes` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_carte` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_mobile_money` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_recu` DECIMAL(12,2) NULL DEFAULT NULL,
  `monnaie_rendue` DECIMAL(12,2) NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `statut` ENUM('valide','annule') NOT NULL DEFAULT 'valide',
  `date_vente` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ticket` (`numero_ticket`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_date` (`date_vente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table caisse_ventes');

mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `caisse_vente_lignes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vente_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `designation` VARCHAR(500) NOT NULL,
  `quantite` INT(11) NOT NULL,
  `prix_unitaire` DECIMAL(12,2) NOT NULL,
  `remise_ligne_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total_ligne` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table caisse_vente_lignes');

$db->exec('SET FOREIGN_KEY_CHECKS=1');

if (mig_table_exists($db, 'admin')) {
    mig_try_add_foreign_key($db, 'caisse_ventes', 'fk_caisse_ventes_admin',
        'ALTER TABLE `caisse_ventes` ADD CONSTRAINT `fk_caisse_ventes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
}
if (mig_table_exists($db, 'caisse_ventes')) {
    mig_try_add_foreign_key($db, 'caisse_vente_lignes', 'fk_cvl_vente',
        'ALTER TABLE `caisse_vente_lignes` ADD CONSTRAINT `fk_cvl_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
}
if (mig_table_exists($db, 'produits')) {
    mig_try_add_foreign_key($db, 'caisse_vente_lignes', 'fk_cvl_produit',
        'ALTER TABLE `caisse_vente_lignes` ADD CONSTRAINT `fk_cvl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
}

echo "Migration caisse terminée.\n";
