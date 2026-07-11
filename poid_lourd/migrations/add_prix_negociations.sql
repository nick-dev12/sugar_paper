-- Negociations de prix client -> vendeur
-- Executer une seule fois. Sauvegarde avant.
-- Recommande: php migrations/run_add_prix_negociations.php

CREATE TABLE IF NOT EXISTS prix_negociations (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  admin_id INT(11) NOT NULL,
  produit_id INT(11) NOT NULL,
  variante_id INT(11) NULL DEFAULT NULL,
  options_json TEXT NULL,
  options_hash VARCHAR(64) NOT NULL DEFAULT '',
  prix_reference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  prix_propose_client DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  prix_contre_vendeur DECIMAL(10,2) NULL DEFAULT NULL,
  prix_convenu DECIMAL(10,2) NULL DEFAULT NULL,
  statut ENUM('en_attente','acceptee','contre_proposee','refusee_finale','commandee') NOT NULL DEFAULT 'en_attente',
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_statut (admin_id, statut, date_maj),
  KEY idx_user_statut (user_id, statut, date_maj),
  KEY idx_produit (produit_id),
  KEY idx_user_produit_hash (user_id, produit_id, options_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE prix_negociations
  ADD CONSTRAINT fk_prix_neg_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

ALTER TABLE prix_negociations
  ADD CONSTRAINT fk_prix_neg_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE;

ALTER TABLE prix_negociations
  ADD CONSTRAINT fk_prix_neg_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE CASCADE;
