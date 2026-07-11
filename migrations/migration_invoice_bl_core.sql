-- Structure B2B : clients, bons de livraison, factures mensuelles (module Invoice)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_bl` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `devis_id` INT(11) NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
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
  CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bl_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_produit` (`produit_id`),
  CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `uniq_client_mois` (`client_b2b_id`,`annee`,`mois`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_fm_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_fm_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facture_mensuelle_bl` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `facture_mensuelle_id` INT(11) NOT NULL,
  `bl_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bl` (`bl_id`),
  KEY `idx_facture` (`facture_mensuelle_id`),
  CONSTRAINT `fk_fmb_facture` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
