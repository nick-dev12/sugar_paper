-- =============================================================================
-- Vitrine vendeur : logo, couleurs, adresse affichée (contact / footer)
-- Exécuter une fois après sauvegarde. Ignorer les erreurs "Duplicate column" si déjà appliqué.
-- =============================================================================

ALTER TABLE `admin`
  ADD COLUMN `boutique_logo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Chemin relatif sous upload/' AFTER `telephone`,
  ADD COLUMN `boutique_couleur_principale` VARCHAR(7) NULL DEFAULT NULL COMMENT 'Hex #RRGGBB' AFTER `boutique_logo`,
  ADD COLUMN `boutique_couleur_accent` VARCHAR(7) NULL DEFAULT NULL COMMENT 'Hex #RRGGBB' AFTER `boutique_couleur_principale`,
  ADD COLUMN `boutique_adresse` TEXT NULL DEFAULT NULL AFTER `boutique_couleur_accent`;
