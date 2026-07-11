-- Annonces plateforme (super admin → clients / vendeurs)
-- Exécuter : php migrations/run_add_platform_annonces.php

CREATE TABLE IF NOT EXISTS `platform_annonces` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `audience` ENUM('client','vendeur') NOT NULL,
  `lien_url` VARCHAR(500) NULL DEFAULT NULL,
  `super_admin_id` INT(11) NOT NULL,
  `nb_destinataires_cibles` INT(11) NOT NULL DEFAULT 0,
  `nb_push_envoyes` INT(11) NOT NULL DEFAULT 0,
  `nb_push_echecs` INT(11) NOT NULL DEFAULT 0,
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audience_date` (`audience`, `date_envoi`),
  KEY `idx_super_admin` (`super_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_annonce_lectures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `annonce_id` INT(11) NOT NULL,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_lecture` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_annonce_user` (`annonce_id`, `user_id`),
  UNIQUE KEY `uk_annonce_admin` (`annonce_id`, `admin_id`),
  KEY `idx_annonce` (`annonce_id`),
  CONSTRAINT `fk_lecture_annonce` FOREIGN KEY (`annonce_id`)
    REFERENCES `platform_annonces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
