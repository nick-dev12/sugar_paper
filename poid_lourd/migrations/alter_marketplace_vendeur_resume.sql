-- Reprise si alter_marketplace_vendeur.sql a échoué après ajout des colonnes admin.
-- Utilisez DROP INDEX adapté : `email` ou `idx_email` (voir SHOW INDEX FROM admin)

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `admin` DROP INDEX `email`;
ALTER TABLE `admin` MODIFY COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `admin` ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `admin` MODIFY COLUMN `role` ENUM(
  'admin',
  'gestion_stock',
  'commercial',
  'comptabilite',
  'rh',
  'caissier',
  'vendeur',
  'plateforme'
) NOT NULL DEFAULT 'admin';

INSERT INTO `admin` (
  `nom`, `prenom`, `email`, `password`, `date_creation`, `statut`, `role`,
  `boutique_slug`, `boutique_nom`, `telephone`
)
SELECT
  'Plateforme',
  '',
  'plateforme-interne@marketplace.local',
  '$2y$10$.dH9ww95UXAvhGlb3kerKe2oPtHP1re1tUbCQjkdrZYE4d.L6Zg9W',
  NOW(),
  'actif',
  'plateforme',
  'plateforme',
  'Marketplace',
  '+10000000000'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `admin` WHERE `role` = 'plateforme' LIMIT 1);

SET @platform_admin_id := (SELECT `id` FROM `admin` WHERE `role` = 'plateforme' ORDER BY `id` ASC LIMIT 1);

ALTER TABLE `produits`
  ADD COLUMN `admin_id` INT(11) NULL DEFAULT NULL AFTER `id`,
  ADD KEY `idx_produits_admin` (`admin_id`);

UPDATE `produits` SET `admin_id` = @platform_admin_id WHERE `admin_id` IS NULL;

ALTER TABLE `produits`
  MODIFY COLUMN `admin_id` INT(11) NOT NULL,
  ADD CONSTRAINT `fk_produits_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `panier`
  ADD COLUMN `vendeur_id` INT(11) NULL DEFAULT NULL AFTER `user_id`,
  ADD KEY `idx_panier_vendeur` (`vendeur_id`);

UPDATE `panier` `pan`
INNER JOIN `produits` `p` ON `p`.`id` = `pan`.`produit_id`
SET `pan`.`vendeur_id` = `p`.`admin_id`
WHERE `pan`.`vendeur_id` IS NULL;

UPDATE `panier` SET `vendeur_id` = @platform_admin_id WHERE `vendeur_id` IS NULL;

ALTER TABLE `panier`
  MODIFY COLUMN `vendeur_id` INT(11) NOT NULL,
  ADD CONSTRAINT `fk_panier_vendeur` FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `commandes`
  ADD COLUMN `vendeur_id` INT(11) NULL DEFAULT NULL AFTER `user_id`,
  ADD KEY `idx_commandes_vendeur` (`vendeur_id`);

UPDATE `commandes` `c`
INNER JOIN (
  SELECT `cp`.`commande_id`, MIN(`p`.`admin_id`) AS `vid`
  FROM `commande_produits` `cp`
  INNER JOIN `produits` `p` ON `p`.`id` = `cp`.`produit_id`
  GROUP BY `cp`.`commande_id`
) `j` ON `j`.`commande_id` = `c`.`id`
SET `c`.`vendeur_id` = `j`.`vid`;

UPDATE `commandes` SET `vendeur_id` = @platform_admin_id WHERE `vendeur_id` IS NULL;

ALTER TABLE `commandes`
  MODIFY COLUMN `vendeur_id` INT(11) NOT NULL,
  ADD CONSTRAINT `fk_commandes_vendeur` FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
