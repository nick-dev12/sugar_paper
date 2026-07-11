-- =============================================================================
-- Assouplir les FK « RESTRICT » vers admin (suppression de lignes, pas TRUNCATE)
-- =============================================================================
-- Utile si vous supprimez des lignes admin avec DELETE (pas pour TRUNCATE).
-- Pour vider une table : utilisez SET FOREIGN_KEY_CHECKS = 0 ou
-- migrations/vider_base_donnees.sql / CALL truncate_table_safe('nom_table');
-- =============================================================================

-- caisse_ventes.admin_id : RESTRICT -> SET NULL (la vente reste, admin_id devient NULL)
ALTER TABLE `caisse_ventes` DROP FOREIGN KEY `fk_caisse_ventes_admin`;
ALTER TABLE `caisse_ventes`
  ADD CONSTRAINT `fk_caisse_ventes_admin`
  FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Marketplace (si les colonnes existent déjà sur votre base)
-- Décommenter uniquement si SHOW CREATE TABLE produits montre admin_id + fk_produits_admin

-- ALTER TABLE `produits` DROP FOREIGN KEY `fk_produits_admin`;
-- ALTER TABLE `produits`
--   ADD CONSTRAINT `fk_produits_admin`
--   FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
--   ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `panier` DROP FOREIGN KEY `fk_panier_vendeur`;
-- ALTER TABLE `panier`
--   ADD CONSTRAINT `fk_panier_vendeur`
--   FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`)
--   ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `commandes` DROP FOREIGN KEY `fk_commandes_vendeur`;
-- ALTER TABLE `commandes`
--   ADD CONSTRAINT `fk_commandes_vendeur`
--   FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`)
--   ON DELETE SET NULL ON UPDATE CASCADE;
