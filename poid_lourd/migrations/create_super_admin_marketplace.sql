-- =============================================================================
-- Espace Super Administrateur (marketplace)
-- Tables dédiées : comptes super_admin + journal d'audit
-- À exécuter une fois (phpMyAdmin, mysql CLI ou migrations/run_super_admin_tables.php)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `super_admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(120) NOT NULL,
  `prenom` VARCHAR(120) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` DATETIME NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_super_admin_email` (`email`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `super_admin_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `super_admin_id` INT(11) NOT NULL,
  `action` VARCHAR(120) NOT NULL,
  `cible_type` VARCHAR(60) NULL DEFAULT NULL COMMENT 'ex: boutique, user, config',
  `cible_id` INT(11) NULL DEFAULT NULL,
  `details` TEXT NULL DEFAULT NULL,
  `date_action` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_super_admin` (`super_admin_id`),
  KEY `idx_date` (`date_action`),
  CONSTRAINT `fk_logs_super_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
