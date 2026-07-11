-- Colonne prix_negociable sur produits (1 = oui par défaut)
ALTER TABLE produits
    ADD COLUMN prix_negociable TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = le client peut négocier le prix, 0 = non'
    AFTER prix_promotion;
