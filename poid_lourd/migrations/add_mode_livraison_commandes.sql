-- Mode de réception : livraison à domicile ou retrait en boutique
ALTER TABLE `commandes`
    ADD COLUMN `mode_livraison` ENUM('livraison','retrait') NOT NULL DEFAULT 'livraison'
    COMMENT 'livraison = adresse client, retrait = pickup en boutique'
    AFTER `adresse_livraison`;
