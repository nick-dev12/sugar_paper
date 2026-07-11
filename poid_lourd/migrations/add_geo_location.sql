-- =============================================================================
-- Service de localisation exacte (GPS)
-- Colonnes latitude/longitude sur admin (boutiques), commandes (livraison)
-- et users (dernière position connue).
-- Préférer : php migrations/run_add_geo_location.php (idempotent)
-- =============================================================================

-- Boutiques (vendeurs)
ALTER TABLE `admin`
  ADD COLUMN `boutique_latitude` DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude GPS boutique' AFTER `boutique_region`,
  ADD COLUMN `boutique_longitude` DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude GPS boutique' AFTER `boutique_latitude`,
  ADD COLUMN `boutique_geo_source` ENUM('gps','map_pin','adresse','manuel') NULL DEFAULT NULL COMMENT 'Origine des coordonnées' AFTER `boutique_longitude`,
  ADD COLUMN `boutique_geo_maj` DATETIME NULL DEFAULT NULL COMMENT 'Dernière mise à jour position' AFTER `boutique_geo_source`;

-- Commandes (position exacte du client à la validation)
ALTER TABLE `commandes`
  ADD COLUMN `delivery_latitude` DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Latitude GPS client à la commande' AFTER `adresse_livraison`,
  ADD COLUMN `delivery_longitude` DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Longitude GPS client à la commande' AFTER `delivery_latitude`,
  ADD COLUMN `delivery_geo_precision` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'Précision GPS en mètres' AFTER `delivery_longitude`,
  ADD COLUMN `delivery_geo_source` ENUM('gps','map_pin','adresse','ip') NULL DEFAULT NULL COMMENT 'Origine des coordonnées' AFTER `delivery_geo_precision`,
  ADD COLUMN `delivery_geo_date` DATETIME NULL DEFAULT NULL COMMENT 'Date capture position' AFTER `delivery_geo_source`;

-- Utilisateurs (dernière position connue, avec consentement)
ALTER TABLE `users`
  ADD COLUMN `last_latitude` DECIMAL(10,8) NULL DEFAULT NULL COMMENT 'Dernière latitude connue' AFTER `statut`,
  ADD COLUMN `last_longitude` DECIMAL(11,8) NULL DEFAULT NULL COMMENT 'Dernière longitude connue' AFTER `last_latitude`,
  ADD COLUMN `last_geo_precision` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'Précision en mètres' AFTER `last_longitude`,
  ADD COLUMN `last_geo_date` DATETIME NULL DEFAULT NULL COMMENT 'Date dernière position' AFTER `last_geo_precision`;

-- Index pour requêtes de proximité (pré-filtre sur boîte englobante)
CREATE INDEX `idx_admin_boutique_geo` ON `admin` (`boutique_latitude`, `boutique_longitude`);
