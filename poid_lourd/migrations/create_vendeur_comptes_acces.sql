-- =============================================================================
-- Comptes d'accès boutique : collaborateurs créés par un vendeur (connexion téléphone + mot de passe)
-- Exécuter une fois. Le téléphone doit être unique (table admin + cette table).
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `vendeur_comptes_acces` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vendeur_admin_id` INT(11) NOT NULL COMMENT 'ID admin (rôle vendeur) propriétaire de la boutique',
  `nom` VARCHAR(190) NOT NULL,
  `telephone` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vca_telephone` (`telephone`),
  KEY `idx_vca_vendeur` (`vendeur_admin_id`),
  CONSTRAINT `fk_vca_vendeur_admin` FOREIGN KEY (`vendeur_admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
