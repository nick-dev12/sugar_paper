-- =============================================================================
-- OPTIMISATION PERFORMANCE - INDEX COMPOSITES
-- =============================================================================
-- Le schéma de base possède déjà les index simples (clés étrangères, statut...).
-- Ce script ajoute des INDEX COMPOSITES pour accélérer les requêtes combinées
-- les plus fréquentes du catalogue et de l'historique des commandes.
--
-- A exécuter une seule fois (phpMyAdmin de Webuzo ou ligne de commande mysql).
-- Idempotent : "IF NOT EXISTS" (nécessite MariaDB >= 10.5 — tu es en 10.11, OK).
-- Aucune donnée modifiée, uniquement des index ajoutés.
-- =============================================================================

-- PRODUITS : catalogue trié par date avec filtre statut
--   (ex. get_all_produits : WHERE statut=... ORDER BY date_creation DESC)
ALTER TABLE `produits` ADD INDEX IF NOT EXISTS `idx_statut_date` (`statut`, `date_creation`);

-- PRODUITS : page catégorie (WHERE categorie_id IN (...) AND statut IN (...))
ALTER TABLE `produits` ADD INDEX IF NOT EXISTS `idx_categorie_statut` (`categorie_id`, `statut`);

-- COMMANDES : historique d'un client (WHERE user_id=... ORDER BY date_commande DESC)
ALTER TABLE `commandes` ADD INDEX IF NOT EXISTS `idx_user_date` (`user_id`, `date_commande`);

-- COMMANDES : tableau de bord admin (WHERE statut=... ORDER BY date_commande DESC)
ALTER TABLE `commandes` ADD INDEX IF NOT EXISTS `idx_statut_date` (`statut`, `date_commande`);

-- PANIER : recherche d'une ligne existante (WHERE user_id=... AND produit_id=...)
ALTER TABLE `panier` ADD INDEX IF NOT EXISTS `idx_user_produit` (`user_id`, `produit_id`);

-- =============================================================================
-- SECTION OPTIONNELLE - MARKETPLACE (vendeurs)
-- =============================================================================
-- À exécuter UNIQUEMENT si la colonne produits.admin_id existe chez toi
-- (ajoutée par migrations/alter_marketplace_vendeur.sql).
-- Si la colonne n'existe pas, ignore cette ligne (elle renverra une erreur sans danger).
--
-- ALTER TABLE `produits` ADD INDEX IF NOT EXISTS `idx_admin_statut` (`admin_id`, `statut`);

-- =============================================================================
-- VÉRIFICATION (optionnel) : lister les index d'une table
--   SHOW INDEX FROM produits;
--   SHOW INDEX FROM commandes;
-- =============================================================================
