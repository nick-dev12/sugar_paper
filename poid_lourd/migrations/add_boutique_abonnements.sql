-- Abonnements client → boutique vendeur (notifications push)
-- Exécuter une seule fois. Sauvegarde avant.

CREATE TABLE IF NOT EXISTS `boutique_abonnements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `admin_id` INT(11) NOT NULL,
  `date_abonnement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_admin` (`user_id`, `admin_id`),
  KEY `idx_admin_id` (`admin_id`),
  CONSTRAINT `fk_boutique_abo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boutique_abo_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
