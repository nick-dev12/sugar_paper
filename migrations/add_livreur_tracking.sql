-- Suivi GPS livreurs en temps réel (Socket.io + PHP)
-- Exécution : php migrations/run_add_livreur_tracking.php

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `livreur_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `livreur_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `commande_id` INT DEFAULT NULL COMMENT 'Commande en cours de livraison',
  `expires_at` DATETIME NOT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_livreur_sessions_token` (`token_hash`),
  KEY `idx_livreur_sessions_livreur` (`livreur_id`),
  KEY `idx_livreur_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_livreur_sessions_livreur` FOREIGN KEY (`livreur_id`) REFERENCES `livreurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
