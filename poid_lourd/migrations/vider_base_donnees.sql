-- =============================================================================
-- VIDAGE COMPLET DE LA BASE (MySQL / MariaDB) — toutes les tables
-- =============================================================================
-- ATTENTION : supprime TOUTES les données listées ci-dessous.
-- La structure des tables est conservée ; AUTO_INCREMENT remis à zéro (TRUNCATE).
--
-- Pourquoi phpMyAdmin affiche #1701 sur TRUNCATE ?
--   D'autres tables ont des clés étrangères vers la table ciblée (ex. admin
--   est référencée par bons_livraison, caisse_ventes, devis, etc.).
--   MySQL interdit TRUNCATE dans ce cas, même avec ON DELETE CASCADE.
--   Ce script désactive temporairement les contrôles FK (méthode officielle).
--
-- Pour vider UNE seule table dans phpMyAdmin :
--   migrations/truncate_une_table_exemple.sql
--   ou installer puis : CALL truncate_table_safe('nom_table');
--
-- Pour vider automatiquement TOUTES les tables (recommandé si la base évolue) :
--   1. Exécuter une fois : migrations/truncate_table_safe_procedure.sql
--   2. Puis : CALL truncate_all_tables();
--
-- Avant d’exécuter :
--   1. Sauvegarde (mysqldump ou export phpMyAdmin).
--   2. USE nom_de_votre_base;  (ex. tresor_afri)
--   3. Recréer un compte administrateur après coup.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Tables enfants / liaisons (ordre conseillé : enfants avant parents)
TRUNCATE TABLE `admin_password_reset`;
TRUNCATE TABLE `user_password_reset`;
TRUNCATE TABLE `super_admin_logs`;
TRUNCATE TABLE `vendeur_comptes_acces`;
TRUNCATE TABLE `bl_lignes`;
TRUNCATE TABLE `facture_mensuelle_bl`;
TRUNCATE TABLE `bons_livraison`;
TRUNCATE TABLE `caisse_vente_lignes`;
TRUNCATE TABLE `caisse_ventes`;
TRUNCATE TABLE `commande_produits`;
TRUNCATE TABLE `factures`;
TRUNCATE TABLE `factures_personnalisees`;
TRUNCATE TABLE `factures_devis`;
TRUNCATE TABLE `devis_produits`;
TRUNCATE TABLE `commandes`;
TRUNCATE TABLE `commandes_personnalisees`;
TRUNCATE TABLE `devis`;
TRUNCATE TABLE `factures_mensuelles`;
TRUNCATE TABLE `depenses`;
TRUNCATE TABLE `employes`;
TRUNCATE TABLE `panier`;
TRUNCATE TABLE `favoris`;
TRUNCATE TABLE `produits_visites`;
TRUNCATE TABLE `produits_variantes`;
TRUNCATE TABLE `stock_mouvements`;
TRUNCATE TABLE `recherches_catalogue`;
TRUNCATE TABLE `produits`;
TRUNCATE TABLE `stock_articles`;
TRUNCATE TABLE `clients_b2b`;
TRUNCATE TABLE `categories_depenses`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `zones_livraison`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `admin`;
TRUNCATE TABLE `super_admin`;

-- Contenu / CMS / technique (sans FK bloquantes vers le cœur métier)
TRUNCATE TABLE `contacts`;
TRUNCATE TABLE `fcm_tokens`;
TRUNCATE TABLE `logos`;
TRUNCATE TABLE `slider`;
TRUNCATE TABLE `section4_config`;
TRUNCATE TABLE `trending_config`;
TRUNCATE TABLE `videos`;
TRUNCATE TABLE `marketplace_hero_affiches`;

SET FOREIGN_KEY_CHECKS = 1;

-- Fin du script
