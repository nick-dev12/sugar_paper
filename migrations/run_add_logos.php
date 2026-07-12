<?php
/**
 * Table logos partenaires (carrousel accueil).
 * Usage : php migrations/run_add_logos.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `logos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'table logos');

echo "Migration logos terminée.\n";
