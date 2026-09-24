<?php
/**
 * Profil livraison par téléphone (adresse + GPS exacts, mis à jour par les livreurs).
 * Usage : php migrations/run_add_client_livraison_profil.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `client_livraison_profil` (
  `telephone_normalized` VARCHAR(32) NOT NULL COMMENT 'Chiffres uniquement (clé)',
  `adresse_livraison` TEXT NOT NULL,
  `delivery_latitude` DECIMAL(10, 8) NOT NULL,
  `delivery_longitude` DECIMAL(11, 8) NOT NULL,
  `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`telephone_normalized`),
  KEY `idx_client_livraison_profil_date` (`date_maj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

mig_safe_exec($db, $sql, 'Table client_livraison_profil');
mig_mark_applied($db, 'client_livraison_profil', 'Profil livraison client par téléphone (adresse + GPS)');
echo "Migration client_livraison_profil terminée.\n";
