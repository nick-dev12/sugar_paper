-- Réduction globale (%) sur devis et bons de livraison / factures
ALTER TABLE `devis`
  ADD COLUMN `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total (produits + livraison)';

ALTER TABLE `bons_livraison`
  ADD COLUMN `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total HT';
