-- =============================================================================
-- Fichier préparé pour import phpMyAdmin (base déjà existante)
-- Généré par migrations/preparer_import_sql.php
-- =============================================================================
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 04 juin 2026 à 14:06
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `tresor_afri`
--
-- Import VPS / MariaDB : procédures truncate_* retirées (évite DEFINER root #1227).
-- Pour les réinstaller en local : migrations/truncate_table_safe_procedure.sql
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firebase_uid` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_provider` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `role` enum('admin','gestion_stock','commercial','comptabilite','rh','caissier','vendeur','plateforme') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `boutique_slug` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boutique_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certification_niveau` enum('standard','vip','premium') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Niveau certification approuvé',
  `certification_date` datetime DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boutique_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin relatif sous upload/',
  `boutique_couleur_principale` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex #RRGGBB',
  `boutique_couleur_accent` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex #RRGGBB',
  `boutique_adresse` text COLLATE utf8mb4_unicode_ci,
  `boutique_region` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Région Sénégal de la boutique (code slug)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_boutique_slug` (`boutique_slug`),
  UNIQUE KEY `uk_admin_telephone` (`telephone`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `idx_admin_firebase_uid` (`firebase_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admin`
--

INSERT INTO `admin` (`id`, `nom`, `prenom`, `email`, `password`, `firebase_uid`, `auth_provider`, `date_creation`, `derniere_connexion`, `statut`, `role`, `boutique_slug`, `boutique_nom`, `certification_niveau`, `certification_date`, `telephone`, `boutique_logo`, `boutique_couleur_principale`, `boutique_couleur_accent`, `boutique_adresse`, `boutique_region`) VALUES
(1, 'Plateforme', '', 'plateforme-interne@marketplace.local', '$2y$10$.dH9ww95UXAvhGlb3kerKe2oPtHP1re1tUbCQjkdrZYE4d.L6Zg9W', NULL, NULL, '2026-04-08 14:51:35', NULL, 'actif', 'plateforme', 'plateforme', 'Marketplace', NULL, NULL, '+10000000000', NULL, NULL, NULL, NULL, NULL),
(2, 'je suis', '', 'webgeniuses12@gmail.com', '$2y$10$vbACH.O94W3TZGVhmAH5o.JotHiWH8rn1xxyCGQwVGtKLCvm.ZprK', 'VleU4dw8QeMOCnDINyJYJJpY9uC2', 'google', '2026-04-08 15:20:12', '2026-05-25 10:56:44', 'inactif', 'vendeur', 'bussnes', 'bussnes', NULL, NULL, '785303879', NULL, NULL, NULL, NULL, NULL),
(3, 'JUJU LOLO', '', NULL, '$2y$10$jY9ei1iIB4UlnPsrLXf0ROmMuCFqYou//l8ONMrX3C8QCsotvw9Yi', NULL, NULL, '2026-04-09 20:44:49', '2026-04-10 17:11:57', 'actif', 'vendeur', 'simbag', 'SIMBAG', NULL, NULL, '709701541', NULL, NULL, NULL, NULL, NULL),
(4, 'guirassi', '', 'vrolingmendy0@gmail.com', '$2y$10$0hZjdp01yocEbWlr9Y88Pe3Wn8JQh2E/ozREl2pVS52j7exoIsGZq', 'TCcTqWOlMeajYYH3Gc2qbruD2mm1', 'google', '2026-04-19 08:24:50', '2026-05-25 05:13:51', 'actif', 'vendeur', 'guirassi', 'guirassi', NULL, NULL, '7871345670', 'boutique_branding/v_4_7f66d3c2a0eb6ba9.png', '#3564a6', '#ff6b35', 'Hann Maristes 002', NULL),
(5, 'Touré', 'mariama', 'touremariama660@gmail.com', '$2y$10$O7f7mTZTsY5RlhYUBZEO6.NFuPAK0DNL0TebAT0Z92A9YCQI1AAtO', NULL, NULL, '2026-04-19 19:21:06', '2026-05-04 10:34:05', 'actif', 'vendeur', 'mounashop', 'MOUNASHOP', NULL, NULL, '775035940', 'boutique_branding/v_5_3ab0283ada39f8c5.jpg', '#3564a6', '#ff6b35', 'Sénégal région de ziguinchor', NULL),
(6, 'Henriette', '', 'henriettenango4@gamil.com', '$2y$10$xMKQiK1ORaIxHbgKf85wKuljWdJCNI6j/9MZ6BfyQL7HMAht9J8Hq', NULL, NULL, '2026-04-21 05:57:17', '2026-04-21 05:58:18', 'actif', 'vendeur', 'senelux', 'Sénélux', NULL, NULL, '782706015', NULL, NULL, NULL, NULL, NULL),
(7, 'Sugar-Paper', '', 'test@colobanes.com', '$2y$10$73DrqeL1H7.8Gk6HUBhiRO4oR9MYrqO.uIVt.g0QW3wvB6gqD2bo6', NULL, NULL, '2026-04-22 04:43:48', '2026-06-04 13:52:08', 'actif', 'vendeur', 'sugar-paper', 'Sugar-Paper', 'standard', '2026-06-02 22:53:25', '+221770000000', 'boutique_branding/v_7_4f2ff59eb6f8e064.png', '#3564a6', '#ff6b35', 'Hann Maristes 002', 'dakar'),
(8, 'teste vendeur', '', NULL, '$2y$10$E3yI21cdL4MjbT1hjjGIpeTGW9lob01aT46yMXdKbpyzviQlCZadS', NULL, NULL, '2026-05-14 15:18:28', '2026-05-22 09:01:59', 'actif', 'vendeur', 'boutique', 'boutique', NULL, NULL, '+221888888888', NULL, NULL, NULL, NULL, NULL),
(9, 'OSI', '', NULL, '$2y$10$u7bbqV0u9/hgT1CKf/CdJOd.tGyHjqY6LV3x21yrl1A8opzOkTsOy', NULL, NULL, '2026-05-19 16:09:45', '2026-06-02 19:59:17', 'actif', 'vendeur', 'osi', 'OSI', NULL, NULL, '+221780000000', NULL, NULL, NULL, NULL, NULL),
(10, 'Thimbane', '', NULL, '$2y$10$ykfxV9NC5GEhVD1UOJthGejHbdLFjot.Uk92CK8NWghxl9tfpdTPO', NULL, NULL, '2026-05-20 21:11:18', '2026-05-20 21:21:43', 'actif', 'vendeur', 'thimbane-apple', 'Thimbane Apple', NULL, NULL, '+221771040885', NULL, NULL, NULL, NULL, NULL),
(12, 'Eva Nyingone essingone', '', 'nyingoneeva077@gmail.com', '$2y$10$5wvXMU30fuQkuS5IVDcvHOrS3Cp3M/3oX1Ec/v661FkaPX4.KzUAu', 'lI5T0eygAKd8NHQPenjUHb658Dc2', 'google', '2026-05-25 11:14:06', NULL, 'actif', 'vendeur', 'carmishop', 'Carmishop', NULL, NULL, '221781112234', NULL, '#ae9d32', '#692277', NULL, 'dakar'),
(13, 'sadio sylla', '', 'syllasadio867@gmail.com', '$2y$10$ya7ERkbxLF8TQ9P15iOuVOganUOq7YNde7fXhjhNVPQ1ayS2MT4i.', NULL, NULL, '2026-05-26 17:44:18', '2026-05-27 11:36:08', 'actif', 'vendeur', 'dioz-service', 'Dioz service', NULL, NULL, '+221778316915', NULL, NULL, NULL, NULL, 'dakar'),
(14, 'Carmi', '', 'carmishop74@gmail.com', '$2y$10$QfNpROHd75z4E5HITIDF7OZTk9RKzORS94WCdCIz0Bdo9/gSkNPae', NULL, NULL, '2026-05-27 07:52:04', '2026-05-27 08:50:24', 'actif', 'vendeur', 'carmi-shop', 'Carmi Shop', NULL, NULL, '+221781112234', 'boutique_branding/v_14_1ec926d57afc6495.jpeg', '#9ca000', '#b2008c', NULL, 'dakar'),
(15, 'christel', '', NULL, '$2y$10$lmu26V1TTPJ..tWRGoyhqOr8Rr1BVuPMVVZouqv8yQaeykNG.AI4u', NULL, NULL, '2026-05-27 19:48:29', '2026-05-30 20:44:12', 'actif', 'vendeur', 'glory-shop', 'glory shop', NULL, NULL, '+221334444444', 'boutique_branding/v_15_69c43415dac3bef1.png', '#595959', '#7c7804', NULL, 'dakar');

-- --------------------------------------------------------

--
-- Structure de la table `admin_password_reset`
--

DROP TABLE IF EXISTS `admin_password_reset`;
CREATE TABLE IF NOT EXISTS `admin_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bl_lignes`
--

DROP TABLE IF EXISTS `bl_lignes`;
CREATE TABLE IF NOT EXISTS `bl_lignes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bl_id` int NOT NULL,
  `produit_id` int DEFAULT NULL,
  `designation` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` decimal(12,3) NOT NULL DEFAULT '1.000',
  `prix_unitaire_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_ligne_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bl` (`bl_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bons_livraison`
--

DROP TABLE IF EXISTS `bons_livraison`;
CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_bl` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_b2b_id` int NOT NULL,
  `devis_id` int DEFAULT NULL COMMENT 'Si issu d une conversion devis',
  `admin_createur_id` int DEFAULT NULL COMMENT 'Commercial / admin ayant cr???? le BL',
  `statut` enum('brouillon','valide') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_bl` date NOT NULL,
  `total_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_bl` (`numero_bl`),
  KEY `idx_client` (`client_b2b_id`),
  KEY `idx_statut_date` (`statut`,`date_bl`),
  KEY `idx_devis` (`devis_id`),
  KEY `fk_bl_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisse_ventes`
--

DROP TABLE IF EXISTS `caisse_ventes`;
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `caissier_id` int DEFAULT NULL,
  `numero_ticket` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Recherche caisse (ticket non pay?? uniquement)',
  `montant_total` decimal(12,2) NOT NULL,
  `remise_globale_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `mode_paiement` enum('especes','carte','mobile_money','cheque','mixte','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `montant_especes` decimal(12,2) DEFAULT NULL,
  `montant_carte` decimal(12,2) DEFAULT NULL,
  `montant_mobile_money` decimal(12,2) DEFAULT NULL,
  `montant_recu` decimal(12,2) DEFAULT NULL,
  `monnaie_rendue` decimal(12,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('en_attente','paye') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paye',
  `date_vente` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_encaissement` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ticket` (`numero_ticket`),
  UNIQUE KEY `uk_caisse_ventes_reference` (`reference`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_date` (`date_vente`),
  KEY `idx_caissier` (`caissier_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisse_vente_lignes`
--

DROP TABLE IF EXISTS `caisse_vente_lignes`;
CREATE TABLE IF NOT EXISTS `caisse_vente_lignes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vente_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `designation` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(12,2) NOT NULL,
  `remise_ligne_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `total_ligne` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL COMMENT 'Catégorie parente (générale)',
  `categorie_generale_id` int DEFAULT NULL COMMENT 'FK rayon (categories_generales)',
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur propriétaire (sous-catégorie)',
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icone` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Font Awesome',
  `sort_ordre` int NOT NULL DEFAULT '0',
  `est_plateforme` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = rayon officiel (mega-menu, formulaire vendeur)',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_admin` (`admin_id`),
  KEY `idx_categories_categorie_generale` (`categorie_generale_id`),
  KEY `idx_categories_categorie_generale_id` (`categorie_generale_id`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `categorie_generale_id`, `admin_id`, `nom`, `description`, `image`, `icone`, `sort_ordre`, `est_plateforme`, `date_creation`) VALUES
(39, NULL, 82, NULL, 'Vêtements enfants', NULL, 'categories/categorie_6a1210f7d803e0.01375248.png', NULL, 0, 0, '2026-04-28 08:08:07'),
(40, NULL, 82, NULL, 'Jouets', NULL, 'categories/categorie_6a1210695d0d51.98255111.png', NULL, 0, 0, '2026-04-28 08:09:02'),
(42, NULL, 82, NULL, 'Puériculture (bébé)', NULL, 'categories/categorie_6a121de8cb0e70.47192484.png', NULL, 0, 0, '2026-04-28 08:09:51'),
(43, NULL, 82, NULL, 'Équipement scolaire', NULL, 'categories/categorie_6a121051a1c4b7.54246743.png', NULL, 0, 0, '2026-04-28 08:10:05'),
(44, NULL, 80, NULL, 'Chaussures', NULL, 'categories/categorie_6a1213051340a9.89666534.png', NULL, 0, 0, '2026-04-28 08:14:42'),
(45, NULL, 80, NULL, 'Sacs & Bagages', NULL, 'categories/categorie_6a1214883f96a6.88063751.png', NULL, 0, 0, '2026-04-28 08:14:56'),
(46, NULL, 80, NULL, 'Accessoires (montres, ceintures, lunettes…)', NULL, 'categories/categorie_6a1212ad3c98e9.18037327.png', NULL, 0, 0, '2026-04-28 08:15:22'),
(47, NULL, 80, NULL, 'Bijoux', NULL, 'categories/categorie_6a1212c534eb21.36784609.png', NULL, 0, 0, '2026-04-28 08:15:38'),
(48, NULL, 80, NULL, 'Vêtements de sport', NULL, 'categories/categorie_6a121517663c91.47023803.png', NULL, 0, 0, '2026-04-28 08:15:51'),
(49, NULL, 80, NULL, 'Sous-vêtements & Lingerie', NULL, 'categories/categorie_6a1214c36dcc87.44666066.png', NULL, 0, 0, '2026-04-28 08:16:03'),
(50, NULL, 80, NULL, 'Tenues traditionnelles', NULL, 'categories/categorie_6a1214dd70e526.18827390.png', NULL, 0, 0, '2026-04-28 08:16:14'),
(51, NULL, 71, NULL, 'Tablette & Téléphones', NULL, 'categories/categorie_6a120e62c7a494.73174404.png', NULL, 0, 0, '2026-04-28 08:17:18'),
(52, NULL, 71, NULL, 'Ordinateurs & Laptops', NULL, 'categories/categorie_6a120dac0af422.41837395.png', NULL, 0, 0, '2026-04-28 08:17:34'),
(54, NULL, 71, NULL, 'TV, Audio & Vidéo', NULL, 'categories/categorie_6a120e817f1d90.08478107.png', NULL, 0, 0, '2026-04-28 08:18:00'),
(55, NULL, 71, NULL, 'Accessoires électroniques', NULL, 'categories/categorie_6a1221c60f29b0.54828370.png', NULL, 0, 0, '2026-04-28 08:18:14'),
(56, NULL, 71, NULL, 'Gaming & Consoles', NULL, 'categories/categorie_6a120d5e710910.58686150.png', NULL, 0, 0, '2026-04-28 08:18:30'),
(57, NULL, 71, NULL, 'Appareils photo & Caméras', NULL, 'categories/categorie_6a120d3ca2af39.40145880.png', NULL, 0, 0, '2026-04-28 08:18:41'),
(58, NULL, 71, NULL, 'Objets connectés (montres, smart home…)', NULL, 'categories/categorie_6a120d95d0edc3.61033263.png', NULL, 0, 0, '2026-04-28 08:18:56'),
(59, NULL, 71, NULL, 'Réseaux & Internet (routeurs, switch…)', NULL, 'categories/categorie_6a120e11cd97a4.22477667.png', NULL, 0, 0, '2026-04-28 08:19:07'),
(60, NULL, 71, NULL, 'Stockage (disques durs, clés USB…)', NULL, 'categories/categorie_6a120e3db0df54.96366037.png', NULL, 0, 0, '2026-04-28 08:19:18'),
(61, NULL, 72, NULL, 'Meubles', NULL, 'categories/categorie_6a1215f08f3cf7.66172451.png', NULL, 0, 0, '2026-04-28 08:21:26'),
(62, NULL, 72, NULL, 'Décoration', NULL, 'categories/categorie_6a1215522523c0.40979696.png', NULL, 0, 0, '2026-04-28 08:21:45'),
(63, NULL, 72, NULL, 'Cuisine & Ustensiles', NULL, 'categories/categorie_6a12153a5e9bb7.77428728.png', NULL, 0, 0, '2026-04-28 08:21:59'),
(64, NULL, 72, NULL, 'Électroménager', NULL, 'categories/categorie_6a121580821fc7.92606415.png', NULL, 0, 0, '2026-04-28 08:22:12'),
(65, NULL, 72, NULL, 'Literie & Linge de maison', NULL, 'categories/categorie_6a121f5fc5b851.47334734.png', NULL, 0, 0, '2026-04-28 08:22:31'),
(66, NULL, 72, NULL, 'Salle de bain', NULL, 'categories/categorie_6a12169ad3c989.87675301.png', NULL, 0, 0, '2026-04-28 08:23:03'),
(67, NULL, 72, NULL, 'Éclairage', NULL, 'categories/categorie_6a12156a2a9827.28873251.png', NULL, 0, 0, '2026-04-28 08:24:27'),
(68, NULL, 72, NULL, 'Rangement & Organisation', NULL, 'categories/categorie_6a12164d387b45.98162842.png', NULL, 0, 0, '2026-04-28 08:24:38'),
(69, NULL, 72, NULL, 'Jardin & Extérieur', NULL, 'categories/categorie_6a1215c3b85a39.88160675.png', NULL, 0, 0, '2026-04-28 08:24:51'),
(70, NULL, 72, NULL, 'Sécurité domestique', NULL, 'categories/categorie_6a121e0d5452a1.52018986.png', NULL, 0, 0, '2026-04-28 08:25:02'),
(71, NULL, 73, NULL, 'Outillage manuel', NULL, 'categories/categorie_6a121ac02f2144.35668807.png', NULL, 0, 0, '2026-04-28 10:50:51'),
(72, NULL, 73, NULL, 'Outillage électrique', NULL, 'categories/categorie_6a122209802fc3.82423775.png', NULL, 0, 0, '2026-04-28 10:51:10'),
(73, NULL, 73, NULL, 'Matériaux de construction', NULL, 'categories/categorie_6a121a9260a080.17369372.png', NULL, 0, 0, '2026-04-28 10:51:22'),
(74, NULL, 73, NULL, 'Électricité', NULL, 'categories/categorie_6a1220229d10b7.52520268.png', NULL, 0, 0, '2026-04-28 10:51:34'),
(75, NULL, 73, NULL, 'Plomberie', NULL, 'categories/categorie_6a122235b9b1c6.56163128.png', NULL, 0, 0, '2026-04-28 10:51:43'),
(76, NULL, 73, NULL, 'Peinture & Revêtements', NULL, 'categories/categorie_6a121aed8170e3.27669303.png', NULL, 0, 0, '2026-04-28 10:51:53'),
(77, NULL, 73, NULL, 'Quincaillerie', NULL, 'categories/categorie_6a121b1e0432d9.56736640.png', NULL, 0, 0, '2026-04-28 10:52:04'),
(78, NULL, 73, NULL, 'Jardinage', NULL, 'categories/categorie_6a121a6d265f20.98762363.png', NULL, 0, 0, '2026-04-28 10:52:13'),
(79, NULL, 73, NULL, 'Aménagement extérieur', NULL, 'categories/categorie_6a122186165ef2.11169062.png', NULL, 0, 0, '2026-04-28 10:52:24'),
(80, NULL, 73, NULL, 'Équipements de protection (EPI)', NULL, 'categories/categorie_6a121a48057681.15076055.png', NULL, 0, 0, '2026-04-28 10:52:37'),
(81, NULL, 74, NULL, 'Jeux vidéo et Consoles', NULL, 'categories/categorie_6a1219bd1979c2.49614162.png', NULL, 0, 0, '2026-04-28 10:53:54'),
(82, NULL, 74, NULL, 'Accessoires gaming', NULL, 'categories/categorie_6a12193d8034a6.86372310.png', NULL, 0, 0, '2026-04-28 10:54:37'),
(83, NULL, 74, NULL, 'Jeux de société', NULL, 'categories/categorie_6a121991d1deb3.86431715.png', NULL, 0, 0, '2026-04-28 10:54:49'),
(84, NULL, 74, NULL, 'Jouets & divertissement', NULL, 'categories/categorie_6a121fbf481e96.50838105.png', NULL, 0, 0, '2026-04-28 10:55:02'),
(85, NULL, 74, NULL, 'Loisirs créatifs (dessin, DIY…)', NULL, 'categories/categorie_6a1219e670d990.19595863.png', NULL, 0, 0, '2026-04-28 10:55:15'),
(86, NULL, 74, NULL, 'Sports & activités', NULL, 'categories/categorie_6a121a2da2f511.62032353.png', NULL, 0, 0, '2026-04-28 10:55:29'),
(87, NULL, 74, NULL, 'Musique & instruments', NULL, 'categories/categorie_6a121a07157f10.93321903.png', NULL, 0, 0, '2026-04-28 10:55:38'),
(88, NULL, 74, NULL, 'Films & séries', NULL, 'categories/categorie_6a121973690b67.14293688.png', NULL, 0, 0, '2026-04-28 10:55:47'),
(89, NULL, 74, NULL, 'Cartes cadeaux & contenus digitaux', NULL, 'categories/categorie_6a12195ba04dd7.75260892.png', NULL, 0, 0, '2026-04-28 10:55:59'),
(90, NULL, 75, NULL, 'Voitures', NULL, 'categories/categorie_6a121eb986e3a7.70277288.png', NULL, 0, 0, '2026-04-28 10:56:22'),
(91, NULL, 75, NULL, 'Motos & scooters', NULL, 'categories/categorie_6a121212e1e8d1.27183408.png', NULL, 0, 0, '2026-04-28 10:56:32'),
(92, NULL, 75, NULL, 'Pièces détachées', NULL, 'categories/categorie_6a12128509fca1.78432839.png', NULL, 0, 0, '2026-04-28 10:56:42'),
(93, NULL, 75, NULL, 'Accessoires auto', NULL, 'categories/categorie_6a121121ad11a8.38678475.png', NULL, 0, 0, '2026-04-28 10:56:59'),
(94, NULL, 75, NULL, 'Pneus & jantes', NULL, 'categories/categorie_6a121268c08068.37914360.png', NULL, 0, 0, '2026-04-28 10:57:10'),
(95, NULL, 75, NULL, 'Entretien & nettoyage', NULL, 'categories/categorie_6a12115dedb053.89408604.png', NULL, 0, 0, '2026-04-28 10:57:19'),
(96, NULL, 75, NULL, 'Équipements moto', NULL, 'categories/categorie_6a1211a3535c48.38104952.png', NULL, 0, 0, '2026-04-28 10:57:29'),
(97, NULL, 75, NULL, 'Audio & multimédia auto', NULL, 'categories/categorie_6a12113da4ef44.36681476.png', NULL, 0, 0, '2026-04-28 10:57:39'),
(98, NULL, 75, NULL, 'GPS & navigation', NULL, 'categories/categorie_6a1211ef518826.13800902.png', NULL, 0, 0, '2026-04-28 10:57:49'),
(99, NULL, 75, NULL, 'Outillage automobile', NULL, 'categories/categorie_6a1212432d1998.12390011.png', NULL, 0, 0, '2026-04-28 10:58:01'),
(100, NULL, 76, NULL, 'Soins du visage', NULL, 'categories/categorie_6a12103246a391.25831954.png', NULL, 0, 0, '2026-04-28 10:58:38'),
(101, NULL, 76, NULL, 'Soins du corps', NULL, 'categories/categorie_6a1210189594a6.53161658.png', NULL, 0, 0, '2026-04-28 10:58:47'),
(102, NULL, 76, NULL, 'Cheveux & coiffure', NULL, 'categories/categorie_6a120e9ab3ff30.30561231.png', NULL, 0, 0, '2026-04-28 10:58:57'),
(103, NULL, 76, NULL, 'Maquillage', NULL, 'categories/categorie_6a120f177b69f8.06940322.png', NULL, 0, 0, '2026-04-28 10:59:11'),
(105, NULL, 76, NULL, 'Parfums', NULL, 'categories/categorie_6a120f81224be5.28014316.png', NULL, 0, 0, '2026-04-28 10:59:52'),
(106, NULL, 76, NULL, 'Hygiène personnelle', NULL, 'categories/categorie_6a120ef5dfce86.74431642.png', NULL, 0, 0, '2026-04-28 11:00:03'),
(107, NULL, 76, NULL, 'Santé & bien-être', NULL, 'categories/categorie_6a120ff14b05b7.78687000.png', NULL, 0, 0, '2026-04-28 11:00:14'),
(108, NULL, 76, NULL, 'Compléments alimentaires', NULL, 'categories/categorie_6a120ebdbd4a28.42682329.png', NULL, 0, 0, '2026-04-28 11:00:23'),
(109, NULL, 76, NULL, 'Produits naturels & bio', NULL, 'categories/categorie_6a120f9a688be4.63403616.png', NULL, 0, 0, '2026-04-28 11:00:33'),
(110, NULL, 76, NULL, 'Matériel médical', NULL, 'categories/categorie_6a120f3dba4cf6.83378841.png', NULL, 0, 0, '2026-04-28 11:00:43'),
(111, NULL, 77, NULL, 'Chiens', NULL, 'categories/categorie_6a1220b1e7abf5.85564269.png', NULL, 0, 0, '2026-04-28 11:01:06'),
(112, NULL, 77, NULL, 'Chats', NULL, 'categories/categorie_6a1220967e4c29.04793836.png', NULL, 0, 0, '2026-04-28 11:01:16'),
(113, NULL, 77, NULL, 'Oiseaux', NULL, 'categories/categorie_6a121c9118c0a8.82366778.png', NULL, 0, 0, '2026-04-28 11:01:30'),
(114, NULL, 77, NULL, 'Poissons & aquariums', NULL, 'categories/categorie_6a121cad3f8a96.09570661.png', NULL, 0, 0, '2026-04-28 11:01:42'),
(115, NULL, 77, NULL, 'Rongeurs', NULL, 'categories/categorie_6a121cdf23e040.63826633.png', NULL, 0, 0, '2026-04-28 11:01:53'),
(116, NULL, 77, NULL, 'Nourriture pour animaux', NULL, 'categories/categorie_6a121c61162fe2.82170085.png', NULL, 0, 0, '2026-04-28 11:02:03'),
(117, NULL, 77, NULL, 'Accessoires (laisses, cages…)', NULL, 'categories/categorie_6a1222d6a6da78.67049497.png', NULL, 0, 0, '2026-04-28 11:02:15'),
(118, NULL, 77, NULL, 'Jouets pour animaux', NULL, 'categories/categorie_6a121c359b7a04.78502283.png', NULL, 0, 0, '2026-04-28 11:02:26'),
(119, NULL, 77, NULL, 'Hygiène & soins', NULL, 'categories/categorie_6a1220dd08cee2.42815457.png', NULL, 0, 0, '2026-04-28 11:02:39'),
(120, NULL, 77, NULL, 'Transport & habitat', NULL, 'categories/categorie_6a121cf904aed6.31560211.png', NULL, 0, 0, '2026-04-28 11:02:49'),
(121, NULL, 78, NULL, 'Livres', NULL, 'categories/categorie_6a12183cd41ea4.66450143.png', NULL, 0, 0, '2026-04-28 11:03:35'),
(122, NULL, 78, NULL, 'E-books', NULL, 'categories/categorie_6a1218fd6cc827.73762611.png', NULL, 0, 0, '2026-04-28 11:03:46'),
(123, NULL, 78, NULL, 'Magazines', NULL, 'categories/categorie_6a121880148fa0.60440464.png', NULL, 0, 0, '2026-04-28 11:03:58'),
(124, NULL, 78, NULL, 'Musique', NULL, 'categories/categorie_6a1219214cc442.30454024.png', NULL, 0, 0, '2026-04-28 11:04:08'),
(125, NULL, 78, NULL, 'Films & vidéos', NULL, 'categories/categorie_6a1217f8bbdee8.59464401.png', NULL, 0, 0, '2026-04-28 11:04:21'),
(126, NULL, 78, NULL, 'Formation en ligne', NULL, 'categories/categorie_6a12181836ec74.04959863.png', NULL, 0, 0, '2026-04-28 11:04:30'),
(127, NULL, 78, NULL, 'Cours & tutoriels', NULL, 'categories/categorie_6a121eed95be21.63453399.png', NULL, 0, 0, '2026-04-28 11:04:40'),
(128, NULL, 78, NULL, 'Matériel éducatif', NULL, 'categories/categorie_6a121d6cd684d6.88511971.png', NULL, 0, 0, '2026-04-28 11:04:52'),
(129, NULL, 78, NULL, 'Logiciels éducatifs', NULL, 'categories/categorie_6a12186444ce35.17929828.png', NULL, 0, 0, '2026-04-28 11:05:10'),
(130, NULL, 78, NULL, 'Papeterie', NULL, 'categories/categorie_6a121f1c80d407.69295871.png', NULL, 0, 0, '2026-04-28 11:05:19'),
(131, NULL, 81, NULL, 'Boissons sans alcool', NULL, 'categories/categorie_6a1217094e71c2.69226035.png', NULL, 0, 0, '2026-04-28 11:06:01'),
(132, NULL, 81, NULL, 'Eau', NULL, 'categories/categorie_6a12178380a202.76854589.png', NULL, 0, 0, '2026-04-28 11:06:10'),
(133, NULL, 81, NULL, 'Jus & smoothies', NULL, 'categories/categorie_6a12179f0849f8.38891593.png', NULL, 0, 0, '2026-04-28 11:06:19'),
(135, NULL, 81, NULL, 'Café', NULL, 'categories/categorie_6a12174a0b4e38.09319836.png', NULL, 0, 0, '2026-04-28 11:06:38'),
(136, NULL, 81, NULL, 'Thé & infusions', NULL, 'categories/categorie_6a1217c8531944.24004045.png', NULL, 0, 0, '2026-04-28 11:06:47'),
(137, NULL, 81, NULL, 'Boissons gazeuses et énergétiques', NULL, 'categories/categorie_6a1216e3e378d7.51225925.png', NULL, 0, 0, '2026-04-28 11:06:57'),
(138, NULL, 81, NULL, 'Boissons bio & naturelles', NULL, 'categories/categorie_6a1216cebd0e48.74883630.png', NULL, 0, 0, '2026-04-28 11:07:07'),
(139, NULL, 81, NULL, 'Boissons alcoolisées', NULL, 'categories/categorie_6a1216ba837af6.72339108.png', NULL, 0, 0, '2026-04-28 11:07:23'),
(140, NULL, 80, NULL, 'Pantanon et Jeans', NULL, 'categories/categorie_6a1213877cbb90.62899964.png', NULL, 0, 0, '2026-05-23 15:40:18'),
(141, NULL, 80, NULL, 'Jupes', NULL, 'categories/categorie_6a1214522dbc07.79203144.png', NULL, 0, 0, '2026-05-23 15:40:55'),
(142, NULL, 80, NULL, 'Chemises', NULL, 'categories/categorie_6a1213211e8c43.85806402.png', NULL, 0, 0, '2026-05-23 15:41:25'),
(143, NULL, 80, NULL, 'Shorts', NULL, 'categories/categorie_6a1214a6abf9b3.83861918.png', NULL, 0, 0, '2026-05-23 15:42:16'),
(144, NULL, 80, NULL, 'Tshirts', NULL, 'categories/categorie_6a1214f47c2657.83027495.png', NULL, 0, 0, '2026-05-23 15:43:49'),
(145, NULL, 80, NULL, 'Chaussettes', NULL, 'categories/categorie_6a1212e1146fc1.15861613.png', NULL, 0, 0, '2026-05-23 15:44:58'),
(146, NULL, 80, NULL, 'Pull', NULL, 'categories/categorie_6a121422214441.50496227.png', NULL, 0, 0, '2026-05-23 15:45:34');

-- --------------------------------------------------------

--
-- Structure de la table `categories_categories_generales`
--

DROP TABLE IF EXISTS `categories_categories_generales`;
CREATE TABLE IF NOT EXISTS `categories_categories_generales` (
  `categorie_id` int NOT NULL,
  `categorie_generale_id` int NOT NULL,
  PRIMARY KEY (`categorie_id`,`categorie_generale_id`),
  KEY `idx_ccg_generale` (`categorie_generale_id`),
  KEY `idx_ccg_categorie` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories_categories_generales`
--

INSERT INTO `categories_categories_generales` (`categorie_id`, `categorie_generale_id`) VALUES
(51, 71),
(52, 71),
(54, 71),
(55, 71),
(56, 71),
(57, 71),
(58, 71),
(59, 71),
(60, 71),
(61, 72),
(62, 72),
(63, 72),
(64, 72),
(65, 72),
(66, 72),
(67, 72),
(68, 72),
(69, 72),
(70, 72),
(71, 73),
(72, 73),
(73, 73),
(74, 73),
(75, 73),
(76, 73),
(77, 73),
(78, 73),
(79, 73),
(80, 73),
(81, 74),
(82, 74),
(83, 74),
(84, 74),
(85, 74),
(86, 74),
(87, 74),
(88, 74),
(89, 74),
(90, 75),
(91, 75),
(92, 75),
(93, 75),
(94, 75),
(95, 75),
(96, 75),
(97, 75),
(98, 75),
(99, 75),
(100, 76),
(101, 76),
(102, 76),
(103, 76),
(105, 76),
(106, 76),
(107, 76),
(108, 76),
(109, 76),
(110, 76),
(111, 77),
(112, 77),
(113, 77),
(114, 77),
(115, 77),
(116, 77),
(117, 77),
(118, 77),
(119, 77),
(120, 77),
(121, 78),
(122, 78),
(123, 78),
(124, 78),
(125, 78),
(126, 78),
(127, 78),
(128, 78),
(129, 78),
(130, 78),
(44, 80),
(45, 80),
(46, 80),
(47, 80),
(48, 80),
(49, 80),
(50, 80),
(140, 80),
(141, 80),
(142, 80),
(143, 80),
(144, 80),
(145, 80),
(146, 80),
(131, 81),
(132, 81),
(133, 81),
(135, 81),
(136, 81),
(137, 81),
(138, 81),
(139, 81),
(39, 82),
(40, 82),
(42, 82),
(43, 82);

-- --------------------------------------------------------

--
-- Structure de la table `categories_depenses`
--

DROP TABLE IF EXISTS `categories_depenses`;
CREATE TABLE IF NOT EXISTS `categories_depenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_tva` enum('sans_tva','avec_tva','mixte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mixte',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories_depenses`
--

INSERT INTO `categories_depenses` (`id`, `nom`, `type_tva`, `date_creation`) VALUES
(6, 'Loyer & charges', 'mixte', '2026-05-20 21:13:36'),
(7, 'Fournitures & matériel', 'mixte', '2026-05-20 21:13:36'),
(8, 'Transport & logistique', 'mixte', '2026-05-20 21:13:36'),
(9, 'Services extérieurs', 'mixte', '2026-05-20 21:13:36'),
(10, 'Autres charges', 'mixte', '2026-05-20 21:13:36');

-- --------------------------------------------------------

--
-- Structure de la table `categories_generales`
--

DROP TABLE IF EXISTS `categories_generales`;
CREATE TABLE IF NOT EXISTS `categories_generales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icone` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Visuel rayon (upload/categories/…)',
  `sort_ordre` int NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attr_poids` tinyint(1) NOT NULL DEFAULT '1',
  `attr_taille` tinyint(1) NOT NULL DEFAULT '1',
  `attr_mesure` tinyint(1) NOT NULL DEFAULT '1',
  `attr_couleur` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_generales_nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories_generales`
--

INSERT INTO `categories_generales` (`id`, `nom`, `description`, `icone`, `image`, `sort_ordre`, `date_creation`, `attr_poids`, `attr_taille`, `attr_mesure`, `attr_couleur`) VALUES
(71, 'Produits technologiques', NULL, NULL, 'categories/categorie_6a12cc092e88b7.87103191.png', 0, '2026-04-28 07:43:53', 0, 0, 0, 0),
(72, 'Mobilier et décoration', NULL, NULL, 'categories/categorie_6a12ccb2557680.22533330.png', 5, '2026-04-28 07:47:42', 0, 0, 0, 0),
(73, 'Bricolage & Jardin', NULL, NULL, 'categories/categorie_6a12cd3ab599f0.13400592.png', 9, '2026-04-28 07:48:02', 0, 0, 0, 0),
(74, 'Jeux & Loisirs', NULL, NULL, 'categories/categorie_6a12cd219e2572.21142977.png', 8, '2026-04-28 07:48:15', 0, 0, 0, 0),
(75, 'Automobile & Moto', NULL, NULL, 'categories/categorie_6a12cc3ccbb425.40563713.png', 3, '2026-04-28 07:48:32', 0, 0, 0, 0),
(76, 'Beauté & Santé', NULL, NULL, 'categories/categorie_6a12cc195144a7.68213896.png', 1, '2026-04-28 07:48:52', 0, 0, 0, 0),
(77, 'Animaux', NULL, NULL, 'categories/categorie_6a12cd602f90a0.94271681.png', 11, '2026-04-28 07:49:06', 0, 0, 0, 0),
(78, 'Livres, Médias & Formation', NULL, NULL, 'categories/categorie_6a12cce51bf368.66449176.png', 7, '2026-04-28 07:49:31', 0, 0, 0, 0),
(79, 'Services', NULL, NULL, 'categories/categorie_6a120b2a116a84.17199488.png', 10, '2026-04-28 07:49:58', 0, 0, 0, 0),
(80, 'Vêtements et accessoires', NULL, NULL, 'categories/categorie_6a12cc56204847.88705164.png', 4, '2026-04-28 07:50:19', 0, 0, 0, 0),
(81, 'Boissons', NULL, NULL, 'categories/categorie_6a12ccca27b9c2.94319230.png', 6, '2026-04-28 07:58:03', 0, 1, 0, 0),
(82, 'Enfants & Bébés', NULL, NULL, 'categories/categorie_6a12cc2c149c19.38199405.png', 2, '2026-04-28 08:01:50', 0, 0, 0, 0),
(83, 'TestRayon_17804297532', 'd', NULL, NULL, 0, '2026-06-02 19:49:13', 1, 1, 1, 1),
(84, 'TestFn_1780429823', 'd', NULL, NULL, 0, '2026-06-02 19:50:23', 1, 1, 1, 1),
(85, 'TestRayonFix_1780429832', 'desc', NULL, NULL, 0, '2026-06-02 19:50:32', 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `clients_b2b`
--

DROP TABLE IF EXISTS `clients_b2b`;
CREATE TABLE IF NOT EXISTS `clients_b2b` (
  `id` int NOT NULL AUTO_INCREMENT,
  `raison_sociale` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_contact` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom_contact` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_raison` (`raison_sociale`(100)),
  KEY `idx_statut` (`statut`),
  KEY `idx_cb2b_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `vendeur_id` int NOT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Saisie manuelle admin',
  `admin_dernier_traitement_id` int DEFAULT NULL COMMENT 'Dernier changement de statut',
  `client_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_commande` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `adresse_livraison` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `telephone_livraison` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('en_attente','confirmee','prise_en_charge','en_preparation','livraison_en_cours','expediee','livree','paye','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `date_commande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_commande` (`date_commande`),
  KEY `idx_cmd_admin_createur` (`admin_createur_id`),
  KEY `idx_cmd_admin_traitement` (`admin_dernier_traitement_id`),
  KEY `idx_commandes_vendeur` (`vendeur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `commandes`
--

INSERT INTO `commandes` (`id`, `user_id`, `vendeur_id`, `admin_createur_id`, `admin_dernier_traitement_id`, `client_nom`, `client_prenom`, `client_email`, `client_telephone`, `numero_commande`, `montant_total`, `adresse_livraison`, `zone_livraison_id`, `frais_livraison`, `telephone_livraison`, `statut`, `date_commande`, `date_livraison`, `notes`) VALUES
(1, 2, 2, NULL, NULL, NULL, NULL, NULL, NULL, 'CMD-20260410-734358', 1000.00, 'Coordonnées de livraison communiquées au client par téléphone.', NULL, 0.00, '777777777', 'en_attente', '2026-04-10 17:07:51', NULL, NULL),
(2, 2, 3, NULL, 3, NULL, NULL, NULL, NULL, 'CMD-20260410-73578F', 1000.00, 'Coordonnées de livraison communiquées au client par téléphone.', NULL, 0.00, '777777777', 'prise_en_charge', '2026-04-10 17:07:51', NULL, NULL),
(3, 3, 2, NULL, 2, NULL, NULL, NULL, NULL, 'CMD-20260418-EE936B', 1000.00, 'Coordonnées de livraison communiquées au client par téléphone.', NULL, 0.00, '88888888', 'livree', '2026-04-18 13:58:38', '2026-05-25 11:03:06', NULL),
(4, 9, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260519-FC26CE', 125000.00, 'Coordonnées de livraison communiquées au client par téléphone.', NULL, 0.00, '221771111111', 'livree', '2026-05-19 15:50:39', '2026-05-25 13:09:13', NULL),
(5, 12, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260524-F24CDF', 1450000.00, '', NULL, 0.00, '+221336666666', 'livree', '2026-05-24 06:29:51', '2026-05-24 06:30:50', NULL),
(6, 14, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260525-811724', 1700000.00, '', NULL, 0.00, '+24177879701', 'livree', '2026-05-25 12:10:48', '2026-05-25 13:08:30', NULL),
(7, 2, 15, NULL, NULL, NULL, NULL, NULL, NULL, 'CMD-20260527-543C49', 16000.00, 'Liberte 6', NULL, 0.00, '+221777777777', 'en_attente', '2026-05-27 20:23:01', NULL, NULL),
(8, 18, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260602-6E4DB6', 975000.00, 'Liberte 6', NULL, 0.00, '+221333333333', 'livree', '2026-06-02 20:02:30', '2026-06-02 20:20:32', NULL),
(9, 18, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260602-85AAAD', 1600000.00, '', NULL, 0.00, '+221333333333', 'livree', '2026-06-02 20:37:12', '2026-06-02 20:37:39', NULL),
(10, 2, 7, NULL, 7, NULL, NULL, NULL, NULL, 'CMD-20260603-A26AEB', 1600000.00, '', NULL, 0.00, '+221777777777', 'prise_en_charge', '2026-06-03 19:19:54', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commandes_personnalisees`
--

DROP TABLE IF EXISTS `commandes_personnalisees`;
CREATE TABLE IF NOT EXISTS `commandes_personnalisees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_souhaitee` date DEFAULT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','devis_envoye','acceptee','refusee','terminee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `notes_admin` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  KEY `idx_zone_livraison` (`zone_livraison_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande_produits`
--

DROP TABLE IF EXISTS `commande_produits`;
CREATE TABLE IF NOT EXISTS `commande_produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `produit_id` int DEFAULT NULL,
  `nom_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `couleur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poids` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surcout_poids` decimal(10,2) DEFAULT '0.00',
  `surcout_taille` decimal(10,2) DEFAULT '0.00',
  `variante_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commande_id` (`commande_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `commande_produits`
--

INSERT INTO `commande_produits` (`id`, `commande_id`, `produit_id`, `nom_produit`, `quantite`, `prix_unitaire`, `prix_total`, `couleur`, `poids`, `taille`, `surcout_poids`, `surcout_taille`, `variante_nom`, `variante_id`) VALUES
(4, 4, 34, NULL, 1, 125000.00, 125000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(5, 5, 34, NULL, 1, 1450000.00, 1450000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(6, 6, 36, NULL, 1, 1700000.00, 1700000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(7, 7, 46, NULL, 1, 16000.00, 16000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(8, 8, 37, NULL, 1, 975000.00, 975000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(9, 9, 36, NULL, 1, 1600000.00, 1600000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(10, 10, 36, NULL, 1, 1600000.00, 1600000.00, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_telephone` (`telephone`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

DROP TABLE IF EXISTS `depenses`;
CREATE TABLE IF NOT EXISTS `depenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categorie_id` int DEFAULT NULL,
  `type_depense` enum('sans_tva','avec_tva') COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `taux_tva` decimal(5,2) DEFAULT NULL COMMENT 'Ex: 20.00 pour 20%',
  `montant_tva` decimal(12,2) DEFAULT NULL,
  `montant_ttc` decimal(12,2) DEFAULT NULL,
  `date_depense` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_depense`),
  KEY `idx_type` (`type_depense`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `fk_dep_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devis`
--

DROP TABLE IF EXISTS `devis`;
CREATE TABLE IF NOT EXISTS `devis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_devis` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_livraison` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `user_id` int DEFAULT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Admin ayant cr???? le devis',
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','envoye','accepte','refuse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_devis` (`numero_devis`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_zone_livraison_id` (`zone_livraison_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  KEY `idx_devis_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devis_produits`
--

DROP TABLE IF EXISTS `devis_produits`;
CREATE TABLE IF NOT EXISTS `devis_produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devis_id` int NOT NULL,
  `produit_id` int DEFAULT NULL,
  `nom_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prix_unitaire` decimal(10,2) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devis_id` (`devis_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employes`
--

DROP TABLE IF EXISTS `employes`;
CREATE TABLE IF NOT EXISTS `employes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poste` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_embauche` date DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_id` int DEFAULT NULL COMMENT 'Compte d acc??s interne li?? (optionnel)',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

DROP TABLE IF EXISTS `factures`;
CREATE TABLE IF NOT EXISTS `factures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_commande` (`commande_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_numero` (`numero_facture`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`id`, `commande_id`, `numero_facture`, `date_facture`, `montant_total`, `date_creation`, `token`) VALUES
(1, 2, 'INV00001', '2026-04-10', 1000.00, '2026-04-10 17:13:46', 'd938143cc82ddf2569aba3e2f6bdb9e045fca51222d3603d0f9e148b2b37db59'),
(2, 3, 'INV00002', '2026-04-18', 1000.00, '2026-04-18 14:56:34', '7a6d01e2f14e9401fe17b7bad38ffee18e5c3876510d96af70fab2de110cb6b3'),
(3, 4, 'INV00003', '2026-05-24', 125000.00, '2026-05-24 06:25:54', 'acada571a36e46f8aceebc04b61743a03f1db1ad11409359b939121d73c1837f');

-- --------------------------------------------------------

--
-- Structure de la table `factures_devis`
--

DROP TABLE IF EXISTS `factures_devis`;
CREATE TABLE IF NOT EXISTS `factures_devis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devis_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Admin ayant g??n??r?? la facture',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `idx_devis_id` (`devis_id`),
  KEY `idx_token` (`token`),
  KEY `idx_factures_devis_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures_mensuelles`
--

DROP TABLE IF EXISTS `factures_mensuelles`;
CREATE TABLE IF NOT EXISTS `factures_mensuelles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_b2b_id` int NOT NULL,
  `annee` smallint NOT NULL,
  `mois` tinyint NOT NULL COMMENT '1 ?? 12',
  `statut` enum('brouillon','validee','payee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `total_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `date_emission` date DEFAULT NULL,
  `date_paiement` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `uniq_client_mois` (`client_b2b_id`,`annee`,`mois`),
  KEY `idx_statut` (`statut`),
  KEY `fk_fm_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures_personnalisees`
--

DROP TABLE IF EXISTS `factures_personnalisees`;
CREATE TABLE IF NOT EXISTS `factures_personnalisees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_personnalisee_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cp` (`commande_personnalisee_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_numero` (`numero_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `facture_mensuelle_bl`
--

DROP TABLE IF EXISTS `facture_mensuelle_bl`;
CREATE TABLE IF NOT EXISTS `facture_mensuelle_bl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `facture_mensuelle_id` int NOT NULL,
  `bl_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bl` (`bl_id`),
  KEY `idx_facture` (`facture_mensuelle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
CREATE TABLE IF NOT EXISTS `favoris` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_produit` (`user_id`,`produit_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fcm_tokens`
--

DROP TABLE IF EXISTS `fcm_tokens`;
CREATE TABLE IF NOT EXISTS `fcm_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`(191)),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fcm_tokens`
--

INSERT INTO `fcm_tokens` (`id`, `user_id`, `admin_id`, `token`, `type`, `user_agent`, `date_creation`) VALUES
(1, NULL, NULL, 'ddeeclLB8ZMwd6YSOPZTON:APA91bFb9lBWrmDjyHQcu9OAdgbIj9wxtbZp2gIuGjKf95T2UpHqMHijPvxalsP7VmmTqW-iEjT1Szz-0oA_3jEmL8338pBXg-dVZT51igXAbt6fx_zBRfc', 'user', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-04-24 03:36:19'),
(2, NULL, NULL, 'ddeeclLB8ZMwd6YSOPZTON:APA91bF5JPwC0RDTuWzLE3lpQZV0xnc248fvHJsXl45UAQGGl19PwWhPcLEvfLGiFuSIqhGiXIWdeQUDPw6mTTJK8gIBKql3WR5wZfXfpajLtITzsXsj8j8', 'admin', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 05:29:03'),
(3, NULL, 5, 'fKCsDv1FHyM6cEIcZHPuKg:APA91bHoMmuuGtg9VSNI5o5PT9yW1Qnhy08_-s7UQqLz6e91dh4T5Aqbeqbv3GNY8-L8TTRo_3ycj9APUQ5ZBKVEMbtNbnozUXLCty5OlcGxujSxMofn_JQ', 'admin', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-27 08:32:24'),
(4, NULL, NULL, 'cF_q7OajwxGxrDzc26MsPu:APA91bHa5cNN2gVO9RRGnKNXi2mIhEz2lPASxuIKT-3S4Q2Ps8KKKFDqg-b7krpprP2aPAoRahKxVRV422-xbN3iYlPOQvwxuftkG6bq-jBvZMaT5idVSWk', 'admin', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-05-19 16:13:22'),
(5, NULL, NULL, 'f6h9OHxv7ZMJKseYdZDrxp:APA91bGzZiR1khE3ixn8SFXIue5SS4DEZQ-xzIsEBPmVgTxgS6AkKEYuo0p5j77gCLy94LDU-6C7bfI0WYesfRierIcQNB5iI_GjvmfwDc2iDwe1kCu_S9Q', 'admin', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-22 08:20:11'),
(6, NULL, 8, 'dy2D-NA0jdzoHvF07yKHeV:APA91bFbu6Qg8zHmweuYtbSgh0OmbKc1E8KlWPREjyrhotp9QQdrtiFdSGgfMPzUcDnRZHmXydeoggMJe8wTkuitFEo0Ni-AvFkLJ7A_KJysV29hi7KYaOM', 'admin', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-22 09:03:26'),
(7, NULL, NULL, 'c9oWAE_vRye1aGGetA7Gqo:APA91bFjTmZvOzcxTAGWRinT8f6bAQjua80XOPxmGjqQN_Q72nJl-NmkZrUjdXN-PZflMukZiyj8fXne-2x6orAxhLP85YCjaf-PShRhEZQGQcYpgMwZW6c', 'user', 'Mozilla/5.0 (Linux; Android 16; 24117RN76G Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/148.0.7778.120 Mobile Safari/537.36 ColobanesApp', '2026-05-25 10:56:09'),
(8, NULL, NULL, 'clP7-VlwTIKwUP0mnPZ-Lk:APA91bEPM_Nsda8j53i1LZZ-JZRiLv6Vp70yxcKcrapNElbeIBZfuXY2m931NUw04kka8fACoJlrEQ75wQmHslOO99jdLuzkt-YpYFbnu_cFygCpor2gBok', 'user', 'Mozilla/5.0 (Linux; Android 16; sdk_gphone64_x86_64 Build/BP41.250916.009.A1; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/142.0.7444.106 Mobile Safari/537.36 ColobanesApp', '2026-05-24 11:27:49'),
(9, NULL, NULL, 'fOpMywbpRJ2zDFMhC7JAf5:APA91bFVTXO3BKRPA3QjsqJhnV83OkeGPwkq9-17dbmMq2joISYhHRgOyNBQFZtVDajbdv4Wi-q4NemUWsp5DJA7kUergVZNZZ4LKRzH15Dxy2045X_kDLE', 'user', 'Mozilla/5.0 (Linux; Android 13; SM-A032F Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/148.0.7778.120 Mobile Safari/537.36 ColobanesApp', '2026-05-26 17:40:52');

-- --------------------------------------------------------

--
-- Structure de la table `genres`
--

DROP TABLE IF EXISTS `genres`;
CREATE TABLE IF NOT EXISTS `genres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_ordre` int NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_genres_nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `genres`
--

INSERT INTO `genres` (`id`, `nom`, `description`, `image`, `sort_ordre`, `date_creation`) VALUES
(130, 'HOMME', NULL, NULL, 0, '2026-04-28 07:59:06'),
(131, 'FEMME', NULL, NULL, 0, '2026-04-28 07:59:41'),
(135, 'FILLE', NULL, NULL, 0, '2026-04-28 08:06:13'),
(136, 'GARÇON', NULL, NULL, 0, '2026-04-28 08:06:34');

-- --------------------------------------------------------

--
-- Structure de la table `genres_categories_generales`
--

DROP TABLE IF EXISTS `genres_categories_generales`;
CREATE TABLE IF NOT EXISTS `genres_categories_generales` (
  `genre_id` int NOT NULL,
  `categorie_generale_id` int NOT NULL,
  PRIMARY KEY (`genre_id`,`categorie_generale_id`),
  KEY `idx_gcg_cg` (`categorie_generale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `genres_categories_generales`
--

INSERT INTO `genres_categories_generales` (`genre_id`, `categorie_generale_id`) VALUES
(130, 76),
(131, 76),
(135, 76),
(136, 76),
(130, 80),
(131, 80),
(135, 82),
(136, 82);

-- --------------------------------------------------------

--
-- Structure de la table `logos`
--

DROP TABLE IF EXISTS `logos`;
CREATE TABLE IF NOT EXISTS `logos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id)',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`),
  KEY `idx_logos_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `marketplace_hero_affiches`
--

DROP TABLE IF EXISTS `marketplace_hero_affiches`;
CREATE TABLE IF NOT EXISTS `marketplace_hero_affiches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  `actif` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ordre` (`ordre`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marketplace_hero_affiches`
--

INSERT INTO `marketplace_hero_affiches` (`id`, `image`, `alt_text`, `ordre`, `actif`, `date_creation`) VALUES
(16, 'hero_6efb70dc8a3036fd398f9599.png', '1', 0, 'actif', '2026-05-14 17:09:48'),
(18, 'hero_7ac330f02cf1723d6d2c5db9.png', '3', 2, 'actif', '2026-05-14 17:11:10'),
(19, 'hero_329871913c64ca56533e95f2.png', '4', 3, 'actif', '2026-05-14 17:11:50'),
(20, 'hero_6ffb8844c7756e59d1b7860b.png', '5', 4, 'actif', '2026-05-14 17:14:29'),
(21, 'hero_c7f0e5b94a9077590e53d4e9.png', '6', 5, 'actif', '2026-05-14 17:15:07'),
(22, 'hero_3f0823798892297f5e713da6.png', 'Bannière marketplace', 6, 'actif', '2026-05-14 17:23:38');

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vendeur_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `couleur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poids` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surcout_poids` decimal(10,2) DEFAULT '0.00',
  `surcout_taille` decimal(10,2) DEFAULT '0.00',
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `variante_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_ajout` (`date_ajout`),
  KEY `idx_panier_vendeur` (`vendeur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id`, `user_id`, `vendeur_id`, `produit_id`, `quantite`, `couleur`, `poids`, `taille`, `surcout_poids`, `surcout_taille`, `prix_unitaire`, `date_ajout`, `variante_nom`, `variante_image`, `variante_id`) VALUES
(6, 9, 7, 42, 1, NULL, NULL, NULL, 0.00, 0.00, 9000.00, '2026-05-19 15:51:09', NULL, NULL, NULL),
(13, 2, 7, 33, 1, NULL, NULL, NULL, 0.00, 0.00, 125000.00, '2026-06-03 22:05:59', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `identifiant_interne` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `prix_promotion` decimal(10,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `categorie_id` int DEFAULT NULL,
  `categorie_generale_id` int DEFAULT NULL COMMENT 'Rayon plateforme (categories_generales.id)',
  `stock_article_id` int DEFAULT NULL,
  `image_principale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` text COLLATE utf8mb4_unicode_ci,
  `poids` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unite` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit??',
  `mesure` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etage` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '??tage entrep??t / magasin',
  `numero_rayon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Num??ro ou code rayon',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif','rupture_stock','bloque') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `bloque_motif` text COLLATE utf8mb4_unicode_ci,
  `bloque_champs` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_nom_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_image_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloque_date` datetime DEFAULT NULL,
  `couleurs` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Couleurs disponibles (ex: Rouge, Bleu, Vert)',
  `taille` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tailles disponibles (ex: S, M, L ou 21cm, 14.8cm)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_identifiant_interne` (`identifiant_interne`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_stock_article` (`stock_article_id`),
  KEY `idx_produits_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `admin_id`, `identifiant_interne`, `nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `categorie_generale_id`, `stock_article_id`, `image_principale`, `images`, `poids`, `unite`, `mesure`, `etage`, `numero_rayon`, `date_creation`, `date_modification`, `statut`, `bloque_motif`, `bloque_champs`, `bloque_nom_ref`, `bloque_image_ref`, `bloque_date`, `couleurs`, `taille`) VALUES
(33, 7, 'FPL000001', 'Portable Windows 11 Full HD 1080p', 'Ordinateur Portable Windows 11 Full HD 1080p 14,1\'\' RAM 4GB ROM 64GB YONIS', 175000.00, 125000.00, 20, 52, 71, NULL, 'produits/produit_6a05fee54a29f1.03110934.jpg', '[\"produits\\/produit_6a05fee54a29f1.03110934.jpg\",\"produits\\/produit_6a05fee54ac5b3.77808648.jpeg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 12:57:09', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 7, 'FPL000002', 'Iphone 17 Pro Max', 'Iphone 17 Pro Max 512 GO', 1700000.00, 1450000.00, 40, 51, 71, NULL, 'produits/produit_6a05fff1489127.69267177.webp', '[\"produits\\/produit_6a05fff1489127.69267177.webp\",\"produits\\/produit_6a05fff148ece5.93451386.jpg\",\"produits\\/produit_6a05fff148f6a0.83921689.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:01:37', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 7, 'FPL000003', 'Xiaomi 15 Ultra - DXOMARK', 'Xiaomi 15 Ultra - DXOMARK', 1350000.00, 1200000.00, 10, 51, 71, NULL, 'produits/produit_6a0600b9166ed1.06559114.jpg', '[\"produits\\/produit_6a0600b9166ed1.06559114.jpg\",\"produits\\/produit_6a0600b9169106.31693251.jpg\",\"produits\\/produit_6a0600b916a6e4.54978607.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:04:57', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 7, 'FPL000004', 'Macbook Pro 2020', 'Macbook Pro 2020', 1700000.00, 1600000.00, 10, 52, 71, NULL, 'produits/produit_6a060190663e50.38960881.webp', '[\"produits\\/produit_6a060190663e50.38960881.webp\",\"produits\\/produit_6a060190664fb5.70695502.jpg\",\"produits\\/produit_6a060190665fa7.82840778.webp\",\"produits\\/produit_6a0601906671a4.50179838.png\",\"produits\\/produit_6a0601c0284696.95002637.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:08:32', '2026-05-14 13:09:20', 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 7, 'FPL000005', 'iPhone 16 128 GB', 'iPhone 16 128 GB', 1000000.00, 975000.00, 10, 51, 71, NULL, 'produits/produit_6a06027d8f0111.76299695.webp', '[\"produits\\/produit_6a06027d8f0111.76299695.webp\",\"produits\\/produit_6a06027d8f14c1.82024963.jpg\",\"produits\\/produit_6a06027d8f1f53.83349419.webp\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:12:29', '2026-05-14 13:17:54', 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 7, 'FPL000006', 'iphone 11 Pro Max 128 Go', 'iphone 11 Pro Max 128 Go', 90000.00, 88000.00, 100, 51, 71, NULL, 'produits/produit_6a06041b6a5571.48941407.jpg', '[\"produits\\/produit_6a06041b6a5571.48941407.jpg\",\"produits\\/produit_6a06041b6a9341.76529267.webp\",\"produits\\/produit_6a06041b6a9b41.82488109.jpg\",\"produits\\/produit_6a06041b6aad22.25373985.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:19:23', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 7, 'FPL000007', 'Samsung Galaxy S23 Ultra', 'Samsung Galaxy S23 Ultra', 500000.00, 475000.00, 10, 51, 71, NULL, 'produits/produit_6a0605151afb93.07573529.jpg', '[\"produits\\/produit_6a0605151afb93.07573529.jpg\",\"produits\\/produit_6a0605151b1243.14936455.jpg\",\"produits\\/produit_6a0605151b1f46.61840949.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:23:33', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 7, 'FPL000008', 'Sh 125 Abs Scheda Tecnica Scooter', 'Sh 125 Abs Scheda Tecnica Scooter', 1800000.00, NULL, 20, 91, 75, NULL, 'produits/produit_6a0605d14569f3.41862365.jpg', '[\"produits\\/produit_6a0605d14569f3.41862365.jpg\",\"produits\\/produit_6a0605d1458642.68723887.jpg\",\"produits\\/produit_6a0605d145a5d7.31227862.webp\",\"produits\\/produit_6a0605d145b1b5.49293377.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:26:41', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 7, 'FPL000009', 'Cecotec Machine à café 1550W', 'Cecotec Machine à café 1550W', 300000.00, 250000.00, 10, 55, 71, NULL, 'produits/produit_6a0606bb65e1e8.90365256.jpg', '[\"produits\\/produit_6a0606bb65e1e8.90365256.jpg\",\"produits\\/produit_6a0606bb660995.52886560.webp\",\"produits\\/produit_6a0606bb662468.17871218.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:30:35', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 7, 'FPL000010', 'Jean large taille mi-haute, extensible bleu', 'Jean large taille mi-haute, extensible bleu', 10000.00, NULL, 16, 140, 80, NULL, 'produits/produit_6a0607df5beee8.77539184.jpg', '[\"produits\\/produit_6a0607df5beee8.77539184.jpg\",\"produits\\/produit_6a0607df5c0a31.65913338.webp\",\"produits\\/produit_6a0607df5c1854.73172070.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:35:27', '2026-05-24 06:22:45', 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 7, 'FPL000011', 'Chemise Homme Col Revers Manches', 'Chemise Homme Col Revers Manches', 11000.00, 9000.00, 20, 142, 80, NULL, 'produits/produit_6a06088cc85e38.69391572.jpg', '[\"produits\\/produit_6a06088cc85e38.69391572.jpg\",\"produits\\/produit_6a06088cc87327.66319418.webp\",\"produits\\/produit_6a06088cc87c17.82621478.webp\",\"produits\\/produit_6a06088cc883b6.22269903.webp\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-14 13:38:20', '2026-05-24 06:23:12', 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 13, 'DIO761054', 'Chaussures pour hommes', 'Très joli bonne qualité', 25000.00, 20000.00, 20, 44, 80, NULL, 'produits/produit_6a1617b00ce346.99785761.jpg', '[\"produits\\/produit_6a1617b00ce346.99785761.jpg\",\"produits\\/produit_6a16452a0e98d6.68352354.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-26 17:59:12', '2026-05-26 21:13:14', 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(46, 15, 'GLO595282', 'mesche brazil', 'des meches', 20000.00, 16000.00, 5, 102, 76, NULL, 'produits/produit_6a174b30c58672.86103719.jpg', '[\"produits\\/produit_6a174b30c58672.86103719.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-27 19:51:12', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(47, 15, 'GLO096717', 'meches Bouclé Braiding Hair For Boho Braids', 'Bouclé Braiding Hair For Boho Braids', 12000.00, 10000.00, 6, 102, 76, NULL, 'produits/produit_6a174bbc373650.13898354.jpg', '[\"produits\\/produit_6a174bbc373650.13898354.jpg\"]', NULL, 'unité', NULL, NULL, NULL, '2026-05-27 19:53:32', NULL, 'actif', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `produits_avis`
--

DROP TABLE IF EXISTS `produits_avis`;
CREATE TABLE IF NOT EXISTS `produits_avis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `commande_id` int NOT NULL,
  `note` decimal(4,2) NOT NULL COMMENT 'Note de 0.33 à 5.00',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_produit_commande` (`user_id`,`produit_id`,`commande_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_commande_id` (`commande_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_avis`
--

INSERT INTO `produits_avis` (`id`, `user_id`, `produit_id`, `commande_id`, `note`, `date_creation`) VALUES
(1, 18, 36, 9, 4.00, '2026-06-02 20:42:38'),
(2, 18, 37, 8, 5.00, '2026-06-02 20:42:43');

-- --------------------------------------------------------

--
-- Structure de la table `produits_avis_popup_snooze`
--

DROP TABLE IF EXISTS `produits_avis_popup_snooze`;
CREATE TABLE IF NOT EXISTS `produits_avis_popup_snooze` (
  `user_id` int NOT NULL,
  `snooze_until` datetime NOT NULL,
  `date_maj` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits_genres`
--

DROP TABLE IF EXISTS `produits_genres`;
CREATE TABLE IF NOT EXISTS `produits_genres` (
  `produit_id` int NOT NULL,
  `genre_id` int NOT NULL,
  PRIMARY KEY (`produit_id`,`genre_id`),
  KEY `idx_pg_genre` (`genre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_genres`
--

INSERT INTO `produits_genres` (`produit_id`, `genre_id`) VALUES
(42, 130),
(43, 130),
(45, 130),
(44, 131),
(45, 131),
(46, 131),
(47, 131),
(47, 135);

-- --------------------------------------------------------

--
-- Structure de la table `produits_sous_categories`
--

DROP TABLE IF EXISTS `produits_sous_categories`;
CREATE TABLE IF NOT EXISTS `produits_sous_categories` (
  `produit_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  PRIMARY KEY (`produit_id`,`categorie_id`),
  KEY `idx_psc_categorie` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_sous_categories`
--

INSERT INTO `produits_sous_categories` (`produit_id`, `categorie_id`) VALUES
(45, 44),
(45, 45),
(45, 46),
(45, 47),
(45, 48),
(45, 49),
(45, 50),
(34, 51),
(35, 51),
(37, 51),
(38, 51),
(39, 51),
(33, 52),
(36, 52),
(41, 55),
(40, 91),
(46, 102),
(47, 102),
(42, 140),
(45, 140),
(45, 141),
(43, 142),
(44, 142),
(45, 142),
(45, 143),
(45, 144),
(45, 145),
(45, 146);

-- --------------------------------------------------------

--
-- Structure de la table `produits_variantes`
--

DROP TABLE IF EXISTS `produits_variantes`;
CREATE TABLE IF NOT EXISTS `produits_variantes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `prix_promotion` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits_visites`
--

DROP TABLE IF EXISTS `produits_visites`;
CREATE TABLE IF NOT EXISTS `produits_visites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `date_visite` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_produit` (`user_id`,`produit_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_visite` (`date_visite`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_visites`
--

INSERT INTO `produits_visites` (`id`, `user_id`, `produit_id`, `date_visite`) VALUES
(10, 8, 35, '2026-05-16 11:24:30'),
(11, 8, 42, '2026-05-16 11:25:06'),
(12, 9, 34, '2026-05-19 15:50:25'),
(13, 9, 43, '2026-05-19 15:51:03'),
(14, 9, 42, '2026-05-19 15:51:06'),
(15, 13, 40, '2026-05-23 17:24:13'),
(16, 12, 33, '2026-05-24 06:10:41'),
(17, 12, 38, '2026-05-24 06:12:46'),
(18, 12, 36, '2026-05-24 06:12:55'),
(19, 12, 42, '2026-05-24 06:13:39'),
(20, 12, 34, '2026-05-24 06:29:36'),
(21, 12, 39, '2026-05-24 06:51:44'),
(22, 14, 39, '2026-05-25 12:05:50'),
(23, 2, 46, '2026-05-27 20:22:38'),
(24, 18, 37, '2026-06-02 20:01:16'),
(26, 18, 36, '2026-06-02 20:36:50'),
(27, 2, 36, '2026-06-03 19:19:45'),
(28, 2, 33, '2026-06-03 22:05:56');

-- --------------------------------------------------------

--
-- Structure de la table `recherches_catalogue`
--

DROP TABLE IF EXISTS `recherches_catalogue`;
CREATE TABLE IF NOT EXISTS `recherches_catalogue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `terme` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_recherche` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date_recherche` (`date_recherche`),
  KEY `idx_terme` (`terme`(191))
) ENGINE=InnoDB AUTO_INCREMENT=786 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recherches_catalogue`
--

INSERT INTO `recherches_catalogue` (`id`, `terme`, `date_recherche`, `user_id`) VALUES
(1, 'Informatique & bureautique', '2026-04-26 03:50:55', NULL),
(2, 'Femme', '2026-04-26 03:50:55', NULL),
(3, 'Informatique & bureautique', '2026-04-26 03:50:58', NULL),
(4, 'Femme', '2026-04-26 03:50:58', NULL),
(5, 'Informatique & bureautique', '2026-04-26 03:50:58', NULL),
(6, 'Femme', '2026-04-26 03:50:59', NULL),
(7, 'Homme', '2026-04-26 03:51:03', NULL),
(8, 'Homme', '2026-04-26 03:51:06', NULL),
(9, 'Homme', '2026-04-26 03:51:06', NULL),
(10, 'Bébé & puériculture', '2026-04-26 03:51:09', NULL),
(11, 'Bébé & puériculture', '2026-04-26 03:51:12', NULL),
(12, 'Bébé & puériculture', '2026-04-26 03:51:13', NULL),
(13, 'Électroménager', '2026-04-26 03:51:16', NULL),
(14, 'Électronique & high-tech', '2026-04-26 03:51:16', NULL),
(15, 'Électroménager', '2026-04-26 03:51:19', NULL),
(16, 'Électronique & high-tech', '2026-04-26 03:51:19', NULL),
(17, 'Électroménager', '2026-04-26 03:51:19', NULL),
(18, 'Électronique & high-tech', '2026-04-26 03:51:20', NULL),
(19, 'Jouets & jeux', '2026-04-26 03:51:23', NULL),
(20, 'Jouets & jeux', '2026-04-26 03:51:26', NULL),
(21, 'Jouets & jeux', '2026-04-26 03:51:26', NULL),
(22, 'Téléphonie & accessoires', '2026-04-26 03:51:28', NULL),
(23, 'Maison & jardin', '2026-04-26 03:51:30', NULL),
(24, 'Téléphonie & accessoires', '2026-04-26 03:51:30', NULL),
(25, 'Téléphonie & accessoires', '2026-04-26 03:51:31', NULL),
(26, 'Maison & jardin', '2026-04-26 03:51:32', NULL),
(27, 'Maison & jardin', '2026-04-26 03:51:33', NULL),
(28, 'Auto & moto', '2026-04-26 03:51:35', NULL),
(29, 'Sport & loisirs', '2026-04-26 03:51:36', NULL),
(30, 'Auto & moto', '2026-04-26 03:51:38', NULL),
(31, 'Auto & moto', '2026-04-26 03:51:39', NULL),
(32, 'Sport & loisirs', '2026-04-26 03:51:43', NULL),
(33, 'Sport & loisirs', '2026-04-26 03:51:43', NULL),
(34, 'Bricolage & outillage', '2026-04-26 03:51:45', NULL),
(35, 'Bricolage & outillage', '2026-04-26 03:51:48', NULL),
(36, 'Bricolage & outillage', '2026-04-26 03:51:49', NULL),
(37, 'Alimentation & boissons', '2026-04-26 03:51:51', NULL),
(38, 'Alimentation & boissons', '2026-04-26 03:51:54', NULL),
(39, 'Alimentation & boissons', '2026-04-26 03:51:54', NULL),
(40, 'Beauté & parfums', '2026-04-26 03:52:03', NULL),
(41, 'Beauté & parfums', '2026-04-26 03:52:06', NULL),
(42, 'Beauté & parfums', '2026-04-26 03:52:09', NULL),
(43, 'Téléphonie & accessoires', '2026-04-27 03:50:56', NULL),
(44, 'Beauté & parfums', '2026-04-27 03:51:03', NULL),
(45, 'Maison & jardin', '2026-04-27 03:51:04', NULL),
(46, 'Alimentation & boissons', '2026-04-27 03:51:04', NULL),
(47, 'Sport & loisirs', '2026-04-27 03:51:05', NULL),
(48, 'Bricolage & outillage', '2026-04-27 03:51:07', NULL),
(49, 'Auto & moto', '2026-04-27 03:51:09', NULL),
(50, 'Électroménager', '2026-04-27 03:51:11', NULL),
(51, 'Bébé & puériculture', '2026-04-27 03:51:13', NULL),
(52, 'Bébé & puériculture', '2026-04-27 03:51:20', NULL),
(53, 'Femme', '2026-04-27 03:51:21', NULL),
(54, 'Électronique & high-tech', '2026-04-27 03:51:23', NULL),
(55, 'Femme', '2026-04-27 03:51:24', NULL),
(56, 'Électronique & high-tech', '2026-04-27 03:51:26', NULL),
(57, 'Homme', '2026-04-27 03:51:26', NULL),
(58, 'Jouets & jeux', '2026-04-27 03:51:27', NULL),
(59, 'Homme', '2026-04-27 03:51:29', NULL),
(60, 'Jouets & jeux', '2026-04-27 03:51:30', NULL),
(61, 'Informatique & bureautique', '2026-04-27 03:51:32', NULL),
(62, 'Informatique & bureautique', '2026-04-27 03:51:35', NULL),
(63, 'Informatique & bureautique', '2026-04-28 03:51:02', NULL),
(64, 'Auto & moto', '2026-04-28 03:51:05', NULL),
(65, 'Auto & moto', '2026-04-28 03:51:08', NULL),
(66, 'Homme', '2026-04-28 03:51:08', NULL),
(67, 'Auto & moto', '2026-04-28 03:51:08', NULL),
(68, 'Bébé & puériculture', '2026-04-28 03:51:11', NULL),
(69, 'Jouets & jeux', '2026-04-28 03:51:12', NULL),
(70, 'Maison & jardin', '2026-04-28 03:51:13', NULL),
(71, 'Maison & jardin', '2026-04-28 03:51:16', NULL),
(72, 'Maison & jardin', '2026-04-28 03:51:17', NULL),
(73, 'Électroménager', '2026-04-28 03:51:22', NULL),
(74, 'Bricolage & outillage', '2026-04-28 03:51:23', NULL),
(75, 'Électroménager', '2026-04-28 03:51:24', NULL),
(76, 'Électroménager', '2026-04-28 03:51:25', NULL),
(77, 'Bricolage & outillage', '2026-04-28 03:51:25', NULL),
(78, 'Bricolage & outillage', '2026-04-28 03:51:26', NULL),
(79, 'Femme', '2026-04-28 03:51:30', NULL),
(80, 'Femme', '2026-04-28 03:51:33', NULL),
(81, 'Femme', '2026-04-28 03:51:34', NULL),
(82, 'Beauté & parfums', '2026-04-28 03:51:35', NULL),
(83, 'Beauté & parfums', '2026-04-28 03:51:38', NULL),
(84, 'Beauté & parfums', '2026-04-28 03:51:38', NULL),
(85, 'Électronique & high-tech', '2026-04-28 03:51:53', NULL),
(86, 'Électronique & high-tech', '2026-04-28 03:51:56', NULL),
(87, 'Électronique & high-tech', '2026-04-28 03:51:56', NULL),
(88, 'Sport & loisirs', '2026-04-28 03:52:10', NULL),
(89, 'Sport & loisirs', '2026-04-28 03:52:14', NULL),
(90, 'Sport & loisirs', '2026-04-28 03:52:14', NULL),
(91, 'Alimentation & boissons', '2026-04-28 03:52:18', NULL),
(92, 'Alimentation & boissons', '2026-04-28 03:52:22', NULL),
(93, 'Alimentation & boissons', '2026-04-28 03:52:23', NULL),
(94, 'Téléphonie & accessoires', '2026-04-28 03:53:18', NULL),
(95, 'Téléphonie & accessoires', '2026-04-28 03:53:22', NULL),
(96, 'Téléphonie & accessoires', '2026-04-28 03:53:22', NULL),
(97, 'iphone', '2026-04-28 11:33:56', 7),
(98, 'ecran', '2026-04-28 11:34:11', 7),
(99, 'promo', '2026-04-28 11:34:28', 7),
(100, 'Produits technologiques', '2026-04-29 00:11:19', NULL),
(101, 'Beauté & Santé', '2026-04-29 00:11:19', NULL),
(102, 'Mobilier et décoration', '2026-04-29 00:11:19', NULL),
(103, 'Vêtements et accessoires', '2026-04-29 00:11:19', NULL),
(104, 'Enfants & Bébés', '2026-04-29 00:11:19', NULL),
(105, 'Livres, Médias & Formation', '2026-04-29 00:11:19', NULL),
(106, 'Beauté & Santé', '2026-04-29 00:11:19', NULL),
(107, 'Produits technologiques', '2026-04-29 00:11:20', NULL),
(108, 'Mobilier et décoration', '2026-04-29 00:11:20', NULL),
(109, 'Vêtements et accessoires', '2026-04-29 00:11:20', NULL),
(110, 'Enfants & Bébés', '2026-04-29 00:11:20', NULL),
(111, 'Beauté & Santé', '2026-04-29 00:11:20', NULL),
(112, 'Produits technologiques', '2026-04-29 00:11:20', NULL),
(113, 'Livres, Médias & Formation', '2026-04-29 00:11:21', NULL),
(114, 'Mobilier et décoration', '2026-04-29 00:11:21', NULL),
(115, 'Vêtements et accessoires', '2026-04-29 00:11:21', NULL),
(116, 'Enfants & Bébés', '2026-04-29 00:11:21', NULL),
(117, 'Produits technologiques', '2026-04-29 00:11:22', NULL),
(118, 'Beauté & Santé', '2026-04-29 00:11:22', NULL),
(119, 'Livres, Médias & Formation', '2026-04-29 00:11:22', NULL),
(120, 'Vêtements et accessoires', '2026-04-29 00:11:22', NULL),
(121, 'Enfants & Bébés', '2026-04-29 00:11:23', NULL),
(122, 'Mobilier et décoration', '2026-04-29 00:11:23', NULL),
(123, 'Produits technologiques', '2026-04-29 00:11:23', NULL),
(124, 'Beauté & Santé', '2026-04-29 00:11:23', NULL),
(125, 'Livres, Médias & Formation', '2026-04-29 00:11:24', NULL),
(126, 'Vêtements et accessoires', '2026-04-29 00:11:24', NULL),
(127, 'Mobilier et décoration', '2026-04-29 00:11:24', NULL),
(128, 'Enfants & Bébés', '2026-04-29 00:11:24', NULL),
(129, 'Produits technologiques', '2026-04-29 00:11:25', NULL),
(130, 'Beauté & Santé', '2026-04-29 00:11:25', NULL),
(131, 'Vêtements et accessoires', '2026-04-29 00:11:25', NULL),
(132, 'Mobilier et décoration', '2026-04-29 00:11:25', NULL),
(133, 'Livres, Médias & Formation', '2026-04-29 00:11:25', NULL),
(134, 'Beauté & Santé', '2026-04-29 00:11:26', NULL),
(135, 'Enfants & Bébés', '2026-04-29 00:11:26', NULL),
(136, 'Produits technologiques', '2026-04-29 00:11:26', NULL),
(137, 'Vêtements et accessoires', '2026-04-29 00:11:27', NULL),
(138, 'Mobilier et décoration', '2026-04-29 00:11:27', NULL),
(139, 'Enfants & Bébés', '2026-04-29 00:11:28', NULL),
(140, 'Produits technologiques', '2026-04-29 00:11:28', NULL),
(141, 'Livres, Médias & Formation', '2026-04-29 00:11:28', NULL),
(142, 'Beauté & Santé', '2026-04-29 00:11:29', NULL),
(143, 'Vêtements et accessoires', '2026-04-29 00:11:29', NULL),
(144, 'Mobilier et décoration', '2026-04-29 00:11:29', NULL),
(145, 'Enfants & Bébés', '2026-04-29 00:11:29', NULL),
(146, 'Livres, Médias & Formation', '2026-04-29 00:11:29', NULL),
(147, 'Beauté & Santé', '2026-04-29 00:11:29', NULL),
(148, 'Produits technologiques', '2026-04-29 00:11:30', NULL),
(149, 'Vêtements et accessoires', '2026-04-29 00:11:30', NULL),
(150, 'Mobilier et décoration', '2026-04-29 00:11:30', NULL),
(151, 'Enfants & Bébés', '2026-04-29 00:11:30', NULL),
(152, 'Livres, Médias & Formation', '2026-04-29 00:11:30', NULL),
(153, 'Beauté & Santé', '2026-04-29 00:11:30', NULL),
(154, 'Produits technologiques', '2026-04-29 00:11:30', NULL),
(155, 'Vêtements et accessoires', '2026-04-29 00:11:31', NULL),
(156, 'Mobilier et décoration', '2026-04-29 00:11:31', NULL),
(157, 'Enfants & Bébés', '2026-04-29 00:11:31', NULL),
(158, 'Livres, Médias & Formation', '2026-04-29 00:11:31', NULL),
(159, 'Produits technologiques', '2026-04-29 00:11:31', NULL),
(160, 'Vêtements et accessoires', '2026-04-29 00:11:31', NULL),
(161, 'Mobilier et décoration', '2026-04-29 00:11:31', NULL),
(162, 'Beauté & Santé', '2026-04-29 00:11:32', NULL),
(163, 'Enfants & Bébés', '2026-04-29 00:11:32', NULL),
(164, 'Bricolage & Jardin', '2026-04-29 00:11:32', NULL),
(165, 'Bricolage & Jardin', '2026-04-29 00:11:35', NULL),
(166, 'Automobile & Moto', '2026-04-29 00:11:39', NULL),
(167, 'Services', '2026-04-29 00:11:40', NULL),
(168, 'Services', '2026-04-29 00:11:41', NULL),
(169, 'Services', '2026-04-29 00:11:41', NULL),
(170, 'Services', '2026-04-29 00:11:42', NULL),
(171, 'Services', '2026-04-29 00:11:43', NULL),
(172, 'Services', '2026-04-29 00:11:44', NULL),
(173, 'Services', '2026-04-29 00:11:44', NULL),
(174, 'Services', '2026-04-29 00:11:45', NULL),
(175, 'Services', '2026-04-29 00:11:46', NULL),
(176, 'Services', '2026-04-29 00:11:48', NULL),
(177, 'Services', '2026-04-29 00:11:49', NULL),
(178, 'Boissons', '2026-04-29 00:11:51', NULL),
(179, 'Animaux', '2026-04-29 00:11:51', NULL),
(180, 'Boissons', '2026-04-29 00:11:52', NULL),
(181, 'Boissons', '2026-04-29 00:11:53', NULL),
(182, 'Animaux', '2026-04-29 00:11:53', NULL),
(183, 'Boissons', '2026-04-29 00:11:54', NULL),
(184, 'Boissons', '2026-04-29 00:11:54', NULL),
(185, 'Animaux', '2026-04-29 00:11:56', NULL),
(186, 'Animaux', '2026-04-29 00:11:58', NULL),
(187, 'Animaux', '2026-04-29 00:12:00', NULL),
(188, 'Boissons', '2026-04-29 00:12:00', NULL),
(189, 'Téléphonie & accessoires', '2026-04-29 03:51:02', NULL),
(190, 'Beauté & parfums', '2026-04-29 03:51:04', NULL),
(191, 'Maison & jardin', '2026-04-29 03:51:05', NULL),
(192, 'Alimentation & boissons', '2026-04-29 03:51:08', NULL),
(193, 'Bricolage & outillage', '2026-04-29 03:51:10', NULL),
(194, 'Sport & loisirs', '2026-04-29 03:51:12', NULL),
(195, 'Auto & moto', '2026-04-29 03:51:14', NULL),
(196, 'Auto & moto', '2026-04-29 03:51:17', NULL),
(197, 'Auto & moto', '2026-04-29 03:51:17', NULL),
(198, 'Électronique & high-tech', '2026-04-29 03:51:21', NULL),
(199, 'Femme', '2026-04-29 03:51:21', NULL),
(200, 'Électroménager', '2026-04-29 03:51:23', NULL),
(201, 'Bébé & puériculture', '2026-04-29 03:51:25', NULL),
(202, 'Bébé & puériculture', '2026-04-29 03:51:27', NULL),
(203, 'Bébé & puériculture', '2026-04-29 03:51:28', NULL),
(204, 'Jouets & jeux', '2026-04-29 03:51:33', NULL),
(205, 'Jouets & jeux', '2026-04-29 03:51:36', NULL),
(206, 'Jouets & jeux', '2026-04-29 03:51:36', NULL),
(207, 'Informatique & bureautique', '2026-04-29 03:51:41', NULL),
(208, 'Informatique & bureautique', '2026-04-29 03:51:44', NULL),
(209, 'Informatique & bureautique', '2026-04-29 03:51:44', NULL),
(210, 'Homme', '2026-04-29 03:51:54', NULL),
(211, 'Homme', '2026-04-29 03:51:56', NULL),
(212, 'Homme', '2026-04-29 03:51:57', NULL),
(213, 'Mobilier et décoration', '2026-04-29 09:12:49', NULL),
(214, 'Vêtements et accessoires', '2026-04-29 09:12:49', NULL),
(215, 'Informatique & bureautique', '2026-04-30 03:51:04', NULL),
(216, 'Homme', '2026-04-30 03:51:06', NULL),
(217, 'Jouets & jeux', '2026-04-30 03:51:08', NULL),
(218, 'Électroménager', '2026-04-30 03:51:09', NULL),
(219, 'Bébé & puériculture', '2026-04-30 03:51:10', NULL),
(220, 'Électroménager', '2026-04-30 03:51:12', NULL),
(221, 'Femme', '2026-04-30 03:51:13', NULL),
(222, 'Électroménager', '2026-04-30 03:51:13', NULL),
(223, 'Femme', '2026-04-30 03:51:15', NULL),
(224, 'Femme', '2026-04-30 03:51:16', NULL),
(225, 'Électronique & high-tech', '2026-04-30 03:51:23', NULL),
(226, 'Électronique & high-tech', '2026-04-30 03:51:26', NULL),
(227, 'Électronique & high-tech', '2026-04-30 03:51:27', NULL),
(228, 'Auto & moto', '2026-04-30 03:51:33', NULL),
(229, 'Auto & moto', '2026-04-30 03:51:36', NULL),
(230, 'Maison & jardin', '2026-04-30 03:51:39', NULL),
(231, 'Téléphonie & accessoires', '2026-04-30 03:51:40', NULL),
(232, 'Maison & jardin', '2026-04-30 03:51:42', NULL),
(233, 'Maison & jardin', '2026-04-30 03:51:43', NULL),
(234, 'Téléphonie & accessoires', '2026-04-30 03:51:43', NULL),
(235, 'Téléphonie & accessoires', '2026-04-30 03:51:45', NULL),
(236, 'Sport & loisirs', '2026-04-30 03:51:48', NULL),
(237, 'Sport & loisirs', '2026-04-30 03:51:54', NULL),
(238, 'Sport & loisirs', '2026-04-30 03:51:55', NULL),
(239, 'Alimentation & boissons', '2026-04-30 03:51:56', NULL),
(240, 'Alimentation & boissons', '2026-04-30 03:51:58', NULL),
(241, 'Alimentation & boissons', '2026-04-30 03:51:59', NULL),
(242, 'Bricolage & outillage', '2026-04-30 03:52:03', NULL),
(243, 'Bricolage & outillage', '2026-04-30 03:52:07', NULL),
(244, 'Bricolage & outillage', '2026-04-30 03:52:07', NULL),
(245, 'Beauté & parfums', '2026-04-30 03:52:14', NULL),
(246, 'Beauté & parfums', '2026-04-30 03:52:17', NULL),
(247, 'Beauté & parfums', '2026-04-30 03:52:18', NULL),
(248, 'Services', '2026-04-30 05:27:22', NULL),
(249, 'Livres, Médias', '2026-04-30 05:27:23', NULL),
(250, 'Produits technologiques', '2026-04-30 05:27:34', NULL),
(251, 'Vêtements et accessoires', '2026-04-30 05:27:48', NULL),
(252, 'Mobilier et décoration', '2026-04-30 05:27:54', NULL),
(253, 'Boissons', '2026-04-30 05:28:00', NULL),
(254, 'Automobile', '2026-04-30 05:28:06', NULL),
(255, 'Beauté', '2026-04-30 05:28:09', NULL),
(256, 'Jeux', '2026-04-30 05:28:10', NULL),
(257, 'Enfants', '2026-04-30 07:09:50', NULL),
(258, 'Vêtements et accessoires', '2026-04-30 07:10:11', NULL),
(259, 'Automobile', '2026-04-30 07:10:22', NULL),
(260, 'Livres, Médias', '2026-04-30 07:10:23', NULL),
(261, 'Animaux', '2026-04-30 07:10:43', NULL),
(262, 'Mobilier et décoration', '2026-04-30 07:10:44', NULL),
(263, 'Boissons', '2026-04-30 07:10:58', NULL),
(264, 'Téléphonie & accessoires', '2026-05-01 03:51:08', NULL),
(265, 'Maison & jardin', '2026-05-01 03:51:10', NULL),
(266, 'Beauté & parfums', '2026-05-01 03:51:10', NULL),
(267, 'Alimentation & boissons', '2026-05-01 03:51:12', NULL),
(268, 'Auto & moto', '2026-05-01 03:51:13', NULL),
(269, 'Électronique & high-tech', '2026-05-01 03:51:14', NULL),
(270, 'Bricolage & outillage', '2026-05-01 03:51:14', NULL),
(271, 'Sport & loisirs', '2026-05-01 03:51:15', NULL),
(272, 'Électroménager', '2026-05-01 03:51:18', NULL),
(273, 'Électroménager', '2026-05-01 03:51:20', NULL),
(274, 'Femme', '2026-05-01 03:51:23', NULL),
(275, 'Femme', '2026-05-01 03:51:30', NULL),
(276, 'Bébé & puériculture', '2026-05-01 03:51:31', NULL),
(277, 'Bébé & puériculture', '2026-05-01 03:51:35', NULL),
(278, 'Jouets & jeux', '2026-05-01 03:51:37', NULL),
(279, 'Jouets & jeux', '2026-05-01 03:51:45', NULL),
(280, 'Informatique & bureautique', '2026-05-01 03:51:48', NULL),
(281, 'Informatique & bureautique', '2026-05-01 03:51:51', NULL),
(282, 'Homme', '2026-05-01 03:51:52', NULL),
(283, 'Homme', '2026-05-01 03:51:55', NULL),
(284, 'Informatique & bureautique', '2026-05-02 03:51:08', NULL),
(285, 'Électronique & high-tech', '2026-05-02 03:51:09', NULL),
(286, 'Femme', '2026-05-02 03:51:10', NULL),
(287, 'Électronique & high-tech', '2026-05-02 03:51:12', NULL),
(288, 'Auto & moto', '2026-05-02 03:51:12', NULL),
(289, 'Homme', '2026-05-02 03:51:14', NULL),
(290, 'Auto & moto', '2026-05-02 03:51:15', NULL),
(291, 'Bébé & puériculture', '2026-05-02 03:51:16', NULL),
(292, 'Sport & loisirs', '2026-05-02 03:51:17', NULL),
(293, 'Jouets & jeux', '2026-05-02 03:51:18', NULL),
(294, 'Sport & loisirs', '2026-05-02 03:51:19', NULL),
(295, 'Bricolage & outillage', '2026-05-02 03:51:21', NULL),
(296, 'Électroménager', '2026-05-02 03:51:22', NULL),
(297, 'Bricolage & outillage', '2026-05-02 03:51:23', NULL),
(298, 'Électroménager', '2026-05-02 03:51:24', NULL),
(299, 'Alimentation & boissons', '2026-05-02 03:51:27', NULL),
(300, 'Alimentation & boissons', '2026-05-02 03:51:30', NULL),
(301, 'Maison & jardin', '2026-05-02 03:53:02', NULL),
(302, 'Maison & jardin', '2026-05-02 03:53:05', NULL),
(303, 'Beauté & parfums', '2026-05-02 03:53:07', NULL),
(304, 'Beauté & parfums', '2026-05-02 03:53:10', NULL),
(305, 'Téléphonie & accessoires', '2026-05-02 03:54:34', NULL),
(306, 'Téléphonie & accessoires', '2026-05-02 03:54:37', NULL),
(307, 'Téléphonie & accessoires', '2026-05-03 03:51:10', NULL),
(308, 'Informatique & bureautique', '2026-05-03 03:51:14', NULL),
(309, 'Informatique & bureautique', '2026-05-03 03:51:17', NULL),
(310, 'Auto & moto', '2026-05-03 03:51:18', NULL),
(311, 'Homme', '2026-05-03 03:51:19', NULL),
(312, 'Auto & moto', '2026-05-03 03:51:21', NULL),
(313, 'Homme', '2026-05-03 03:51:22', NULL),
(314, 'Maison & jardin', '2026-05-03 03:51:22', NULL),
(315, 'Sport & loisirs', '2026-05-03 03:51:23', NULL),
(316, 'Bébé & puériculture', '2026-05-03 03:51:23', NULL),
(317, 'Sport & loisirs', '2026-05-03 03:51:26', NULL),
(318, 'Bébé & puériculture', '2026-05-03 03:51:26', NULL),
(319, 'Bricolage & outillage', '2026-05-03 03:51:29', NULL),
(320, 'Jouets & jeux', '2026-05-03 03:51:29', NULL),
(321, 'Jouets & jeux', '2026-05-03 03:51:31', NULL),
(322, 'Femme', '2026-05-03 03:51:33', NULL),
(323, 'Bricolage & outillage', '2026-05-03 03:51:35', NULL),
(324, 'Femme', '2026-05-03 03:51:35', NULL),
(325, 'Alimentation & boissons', '2026-05-03 03:51:36', NULL),
(326, 'Électroménager', '2026-05-03 03:51:37', NULL),
(327, 'Alimentation & boissons', '2026-05-03 03:51:39', NULL),
(328, 'Électroménager', '2026-05-03 03:51:40', NULL),
(329, 'Électronique & high-tech', '2026-05-03 03:51:42', NULL),
(330, 'Électronique & high-tech', '2026-05-03 03:51:44', NULL),
(331, 'Beauté & parfums', '2026-05-03 03:51:46', NULL),
(332, 'Informatique & bureautique', '2026-05-04 03:51:12', NULL),
(333, 'Homme', '2026-05-04 03:51:12', NULL),
(334, 'Électronique & high-tech', '2026-05-04 03:51:13', NULL),
(335, 'Bébé & puériculture', '2026-05-04 03:51:15', NULL),
(336, 'Jouets & jeux', '2026-05-04 03:51:18', NULL),
(337, 'Femme', '2026-05-04 03:51:20', NULL),
(338, 'Bricolage & outillage', '2026-05-04 03:52:00', NULL),
(339, 'Bricolage & outillage', '2026-05-04 03:52:03', NULL),
(340, 'Sport & loisirs', '2026-05-04 03:52:07', NULL),
(341, 'Sport & loisirs', '2026-05-04 03:52:10', NULL),
(342, 'Électroménager', '2026-05-04 03:52:11', NULL),
(343, 'Électroménager', '2026-05-04 03:52:14', NULL),
(344, 'Auto & moto', '2026-05-04 03:52:32', NULL),
(345, 'Auto & moto', '2026-05-04 03:52:35', NULL),
(346, 'Maison & jardin', '2026-05-04 03:52:44', NULL),
(347, 'Maison & jardin', '2026-05-04 03:52:47', NULL),
(348, 'Alimentation & boissons', '2026-05-04 03:52:53', NULL),
(349, 'Alimentation & boissons', '2026-05-04 03:52:57', NULL),
(350, 'Téléphonie & accessoires', '2026-05-04 03:53:02', NULL),
(351, 'Beauté & parfums', '2026-05-04 03:53:02', NULL),
(352, 'Beauté & parfums', '2026-05-04 03:53:05', NULL),
(353, 'Téléphonie & accessoires', '2026-05-04 03:53:05', NULL),
(354, 'Informatique & bureautique', '2026-05-05 03:51:16', NULL),
(355, 'Téléphonie & accessoires', '2026-05-05 03:51:18', NULL),
(356, 'Informatique & bureautique', '2026-05-05 03:51:19', NULL),
(357, 'Jouets & jeux', '2026-05-05 03:51:20', NULL),
(358, 'Homme', '2026-05-05 03:51:20', NULL),
(359, 'Jouets & jeux', '2026-05-05 03:51:23', NULL),
(360, 'Homme', '2026-05-05 03:51:23', NULL),
(361, 'Femme', '2026-05-05 03:51:27', NULL),
(362, 'Femme', '2026-05-05 03:51:31', NULL),
(363, 'Électronique & high-tech', '2026-05-05 03:51:34', NULL),
(364, 'Électronique & high-tech', '2026-05-05 03:51:37', NULL),
(365, 'Sport & loisirs', '2026-05-05 03:51:38', NULL),
(366, 'Alimentation & boissons', '2026-05-05 03:51:40', NULL),
(367, 'Beauté & parfums', '2026-05-05 03:51:42', NULL),
(368, 'Bébé & puériculture', '2026-05-05 03:52:23', NULL),
(369, 'Bébé & puériculture', '2026-05-05 03:52:26', NULL),
(370, 'Électroménager', '2026-05-05 03:52:27', NULL),
(371, 'Électroménager', '2026-05-05 03:52:30', NULL),
(372, 'Auto & moto', '2026-05-05 03:52:32', NULL),
(373, 'Maison & jardin', '2026-05-05 03:52:34', NULL),
(374, 'Bricolage & outillage', '2026-05-05 03:52:36', NULL),
(375, 'Bricolage & outillage', '2026-05-05 03:52:39', NULL),
(376, 'Homme', '2026-05-06 03:51:16', NULL),
(377, 'Informatique & bureautique', '2026-05-06 03:51:18', NULL),
(378, 'Bébé & puériculture', '2026-05-06 03:51:19', NULL),
(379, 'Femme', '2026-05-06 03:51:20', NULL),
(380, 'Électroménager', '2026-05-06 03:51:21', NULL),
(381, 'Électronique & high-tech', '2026-05-06 03:51:29', NULL),
(382, 'Jouets & jeux', '2026-05-06 03:51:32', NULL),
(383, 'Jouets & jeux', '2026-05-06 03:51:35', NULL),
(384, 'Maison & jardin', '2026-05-06 03:51:43', NULL),
(385, 'Maison & jardin', '2026-05-06 03:51:45', NULL),
(386, 'Bricolage & outillage', '2026-05-06 03:51:46', NULL),
(387, 'Auto & moto', '2026-05-06 03:51:47', NULL),
(388, 'Auto & moto', '2026-05-06 03:51:49', NULL),
(389, 'Sport & loisirs', '2026-05-06 03:51:56', NULL),
(390, 'Sport & loisirs', '2026-05-06 03:51:59', NULL),
(391, 'Alimentation & boissons', '2026-05-06 03:52:03', NULL),
(392, 'Alimentation & boissons', '2026-05-06 03:52:07', NULL),
(393, 'Téléphonie & accessoires', '2026-05-06 03:52:09', NULL),
(394, 'Téléphonie & accessoires', '2026-05-06 03:52:12', NULL),
(395, 'Beauté & parfums', '2026-05-06 03:52:31', NULL),
(396, 'Beauté & parfums', '2026-05-06 03:52:34', NULL),
(397, 'Téléphonie & accessoires', '2026-05-07 03:51:17', NULL),
(398, 'Beauté & parfums', '2026-05-07 03:51:20', NULL),
(399, 'Électronique & high-tech', '2026-05-07 03:51:20', NULL),
(400, 'Électronique & high-tech', '2026-05-07 03:51:23', NULL),
(401, 'Informatique & bureautique', '2026-05-07 03:51:24', NULL),
(402, 'Bébé & puériculture', '2026-05-07 03:51:25', NULL),
(403, 'Bébé & puériculture', '2026-05-07 03:51:27', NULL),
(404, 'Informatique & bureautique', '2026-05-07 03:51:27', NULL),
(405, 'Jouets & jeux', '2026-05-07 03:51:29', NULL),
(406, 'Homme', '2026-05-07 03:51:37', NULL),
(407, 'Homme', '2026-05-07 03:51:40', NULL),
(408, 'Femme', '2026-05-07 03:51:44', NULL),
(409, 'Femme', '2026-05-07 03:51:47', NULL),
(410, 'Bricolage & outillage', '2026-05-07 03:51:54', NULL),
(411, 'Bricolage & outillage', '2026-05-07 03:51:57', NULL),
(412, 'Sport & loisirs', '2026-05-07 03:52:03', NULL),
(413, 'Sport & loisirs', '2026-05-07 03:52:06', NULL),
(414, 'Alimentation & boissons', '2026-05-07 03:52:08', NULL),
(415, 'Alimentation & boissons', '2026-05-07 03:52:11', NULL),
(416, 'Électroménager', '2026-05-07 03:52:21', NULL),
(417, 'Électroménager', '2026-05-07 03:52:24', NULL),
(418, 'Maison & jardin', '2026-05-07 03:52:30', NULL),
(419, 'Maison & jardin', '2026-05-07 03:52:33', NULL),
(420, 'Auto & moto', '2026-05-07 03:52:34', NULL),
(421, 'Auto & moto', '2026-05-07 03:52:37', NULL),
(422, 'Électronique & high-tech', '2026-05-08 03:51:20', NULL),
(423, 'Informatique & bureautique', '2026-05-08 03:51:21', NULL),
(424, 'Bébé & puériculture', '2026-05-08 03:51:25', NULL),
(425, 'Jouets & jeux', '2026-05-08 03:51:34', NULL),
(426, 'Jouets & jeux', '2026-05-08 03:51:38', NULL),
(427, 'Électroménager', '2026-05-08 03:51:39', NULL),
(428, 'Alimentation & boissons', '2026-05-08 03:51:46', NULL),
(429, 'Bricolage & outillage', '2026-05-08 03:51:47', NULL),
(430, 'Sport & loisirs', '2026-05-08 03:51:52', NULL),
(431, 'Auto & moto', '2026-05-08 03:52:14', NULL),
(432, 'Maison & jardin', '2026-05-08 03:52:17', NULL),
(433, 'Femme', '2026-05-08 03:52:18', NULL),
(434, 'Femme', '2026-05-08 03:52:21', NULL),
(435, 'Homme', '2026-05-08 03:52:22', NULL),
(436, 'Homme', '2026-05-08 03:52:25', NULL),
(437, 'Téléphonie & accessoires', '2026-05-08 03:52:29', NULL),
(438, 'Téléphonie & accessoires', '2026-05-08 03:52:32', NULL),
(439, 'Beauté & parfums', '2026-05-08 03:52:45', NULL),
(440, 'Beauté & parfums', '2026-05-08 03:52:48', NULL),
(441, 'Beauté & parfums', '2026-05-09 03:51:21', NULL),
(442, 'Homme', '2026-05-09 03:51:22', NULL),
(443, 'Téléphonie & accessoires', '2026-05-09 03:51:24', NULL),
(444, 'Alimentation & boissons', '2026-05-09 03:51:27', NULL),
(445, 'Jouets & jeux', '2026-05-09 03:51:28', NULL),
(446, 'Alimentation & boissons', '2026-05-09 03:51:30', NULL),
(447, 'Informatique & bureautique', '2026-05-09 03:51:32', NULL),
(448, 'Informatique & bureautique', '2026-05-09 03:51:35', NULL),
(449, 'Électronique & high-tech', '2026-05-09 03:51:36', NULL),
(450, 'Électronique & high-tech', '2026-05-09 03:51:39', NULL),
(451, 'Maison & jardin', '2026-05-09 03:51:52', NULL),
(452, 'Maison & jardin', '2026-05-09 03:51:58', NULL),
(453, 'Électroménager', '2026-05-09 03:52:09', NULL),
(454, 'Électroménager', '2026-05-09 03:52:12', NULL),
(455, 'Bébé & puériculture', '2026-05-09 03:52:13', NULL),
(456, 'Bébé & puériculture', '2026-05-09 03:52:16', NULL),
(457, 'Auto & moto', '2026-05-09 03:53:06', NULL),
(458, 'Auto & moto', '2026-05-09 03:53:08', NULL),
(459, 'Bricolage & outillage', '2026-05-09 03:53:10', NULL),
(460, 'Bricolage & outillage', '2026-05-09 03:53:13', NULL),
(461, 'Sport & loisirs', '2026-05-09 03:53:14', NULL),
(462, 'Sport & loisirs', '2026-05-09 03:53:17', NULL),
(463, 'Femme', '2026-05-09 03:53:19', NULL),
(464, 'Femme', '2026-05-09 03:53:22', NULL),
(465, 'Jouets & jeux', '2026-05-10 03:51:24', NULL),
(466, 'Jouets & jeux', '2026-05-10 03:51:28', NULL),
(467, 'Électroménager', '2026-05-10 03:51:30', NULL),
(468, 'Informatique & bureautique', '2026-05-10 03:51:43', NULL),
(469, 'Informatique & bureautique', '2026-05-10 03:51:47', NULL),
(470, 'Bébé & puériculture', '2026-05-10 03:51:57', NULL),
(471, 'Maison & jardin', '2026-05-10 03:52:03', NULL),
(472, 'Maison & jardin', '2026-05-10 03:52:05', NULL),
(473, 'Sport & loisirs', '2026-05-10 03:52:10', NULL),
(474, 'Électronique & high-tech', '2026-05-10 03:52:10', NULL),
(475, 'Électronique & high-tech', '2026-05-10 03:52:13', NULL),
(476, 'Alimentation & boissons', '2026-05-10 03:52:16', NULL),
(477, 'Alimentation & boissons', '2026-05-10 03:52:19', NULL),
(478, 'Auto & moto', '2026-05-10 03:52:20', NULL),
(479, 'Bricolage & outillage', '2026-05-10 03:52:21', NULL),
(480, 'Femme', '2026-05-10 03:52:24', NULL),
(481, 'Beauté & parfums', '2026-05-10 03:52:27', NULL),
(482, 'Beauté & parfums', '2026-05-10 03:52:30', NULL),
(483, 'Homme', '2026-05-10 03:52:33', NULL),
(484, 'Homme', '2026-05-10 03:52:36', NULL),
(485, 'Téléphonie & accessoires', '2026-05-10 03:52:44', NULL),
(486, 'Téléphonie & accessoires', '2026-05-10 03:52:46', NULL),
(487, 'Homme', '2026-05-11 03:51:25', NULL),
(488, 'Beauté & parfums', '2026-05-11 03:51:25', NULL),
(489, 'Électronique & high-tech', '2026-05-11 03:51:28', NULL),
(490, 'Femme', '2026-05-11 03:51:32', NULL),
(491, 'Femme', '2026-05-11 03:51:37', NULL),
(492, 'Téléphonie & accessoires', '2026-05-11 03:51:45', NULL),
(493, 'Informatique & bureautique', '2026-05-11 03:51:47', NULL),
(494, 'Jouets & jeux', '2026-05-11 03:51:49', NULL),
(495, 'Jouets & jeux', '2026-05-11 03:51:52', NULL),
(496, 'Électroménager', '2026-05-11 03:52:04', NULL),
(497, 'Électroménager', '2026-05-11 03:52:08', NULL),
(498, 'Alimentation & boissons', '2026-05-11 03:52:11', NULL),
(499, 'Bricolage & outillage', '2026-05-11 03:52:13', NULL),
(500, 'Bricolage & outillage', '2026-05-11 03:52:19', NULL),
(501, 'Bébé & puériculture', '2026-05-11 03:52:21', NULL),
(502, 'Bébé & puériculture', '2026-05-11 03:52:25', NULL),
(503, 'Auto & moto', '2026-05-11 03:53:16', NULL),
(504, 'Auto & moto', '2026-05-11 03:53:21', NULL),
(505, 'Maison & jardin', '2026-05-11 03:53:23', NULL),
(506, 'Maison & jardin', '2026-05-11 03:53:25', NULL),
(507, 'Sport & loisirs', '2026-05-11 03:53:29', NULL),
(508, 'Sport & loisirs', '2026-05-11 03:53:32', NULL),
(509, 'Électronique & high-tech', '2026-05-12 03:51:26', NULL),
(510, 'Électronique & high-tech', '2026-05-12 03:51:29', NULL),
(511, 'Bébé & puériculture', '2026-05-12 03:51:30', NULL),
(512, 'Informatique & bureautique', '2026-05-12 03:51:51', NULL),
(513, 'Informatique & bureautique', '2026-05-12 03:51:54', NULL),
(514, 'Jouets & jeux', '2026-05-12 03:52:10', NULL),
(515, 'Alimentation & boissons', '2026-05-12 03:52:10', NULL),
(516, 'Jouets & jeux', '2026-05-12 03:52:12', NULL),
(517, 'Alimentation & boissons', '2026-05-12 03:52:13', NULL),
(518, 'Électroménager', '2026-05-12 03:52:14', NULL),
(519, 'Électroménager', '2026-05-12 03:52:17', NULL),
(520, 'Beauté & parfums', '2026-05-12 03:52:18', NULL),
(521, 'Auto & moto', '2026-05-12 03:52:19', NULL),
(522, 'Maison & jardin', '2026-05-12 03:52:20', NULL),
(523, 'Beauté & parfums', '2026-05-12 03:52:22', NULL),
(524, 'Homme', '2026-05-12 03:52:26', NULL),
(525, 'Homme', '2026-05-12 03:52:29', NULL),
(526, 'Femme', '2026-05-12 03:52:49', NULL),
(527, 'Femme', '2026-05-12 03:52:52', NULL),
(528, 'Téléphonie & accessoires', '2026-05-12 03:52:56', NULL),
(529, 'Téléphonie & accessoires', '2026-05-12 03:52:59', NULL),
(530, 'Bricolage & outillage', '2026-05-12 03:53:04', NULL),
(531, 'Bricolage & outillage', '2026-05-12 03:53:07', NULL),
(532, 'Sport & loisirs', '2026-05-12 03:53:09', NULL),
(533, 'Électroménager', '2026-05-13 03:51:28', NULL),
(534, 'Maison & jardin', '2026-05-13 03:51:29', NULL),
(535, 'Bébé & puériculture', '2026-05-13 03:51:30', NULL),
(536, 'Maison & jardin', '2026-05-13 03:51:33', NULL),
(537, 'Maison & jardin', '2026-05-13 03:51:34', NULL),
(538, 'Bébé & puériculture', '2026-05-13 03:51:34', NULL),
(539, 'Bébé & puériculture', '2026-05-13 03:51:34', NULL),
(540, 'Beauté & parfums', '2026-05-13 03:51:43', NULL),
(541, 'Informatique & bureautique', '2026-05-13 03:51:43', NULL),
(542, 'Femme', '2026-05-13 03:51:55', NULL),
(543, 'Jouets & jeux', '2026-05-13 03:52:19', NULL),
(544, 'Jouets & jeux', '2026-05-13 03:52:22', NULL),
(545, 'Jouets & jeux', '2026-05-13 03:52:23', NULL),
(546, 'Électronique & high-tech', '2026-05-13 03:52:28', NULL),
(547, 'Électronique & high-tech', '2026-05-13 03:52:31', NULL),
(548, 'Électronique & high-tech', '2026-05-13 03:52:31', NULL),
(549, 'Sport & loisirs', '2026-05-13 03:52:32', NULL),
(550, 'Sport & loisirs', '2026-05-13 03:52:36', NULL),
(551, 'Sport & loisirs', '2026-05-13 03:52:36', NULL),
(552, 'Alimentation & boissons', '2026-05-13 03:53:08', NULL),
(553, 'Alimentation & boissons', '2026-05-13 03:53:11', NULL),
(554, 'Alimentation & boissons', '2026-05-13 03:53:11', NULL),
(555, 'Auto & moto', '2026-05-13 03:53:15', NULL),
(556, 'Auto & moto', '2026-05-13 03:53:18', NULL),
(557, 'Auto & moto', '2026-05-13 03:53:18', NULL),
(558, 'Homme', '2026-05-13 03:53:22', NULL),
(559, 'Homme', '2026-05-13 03:53:25', NULL),
(560, 'Homme', '2026-05-13 03:53:25', NULL),
(561, 'Téléphonie & accessoires', '2026-05-13 03:53:29', NULL),
(562, 'Téléphonie & accessoires', '2026-05-13 03:53:32', NULL),
(563, 'Téléphonie & accessoires', '2026-05-13 03:53:33', NULL),
(564, 'Bricolage & outillage', '2026-05-13 03:53:37', NULL),
(565, 'Bricolage & outillage', '2026-05-13 03:53:39', NULL),
(566, 'Bricolage & outillage', '2026-05-13 03:53:40', NULL),
(567, 'Boissons', '2026-05-13 07:11:44', NULL),
(568, 'Mobilier et décoration', '2026-05-13 07:19:39', NULL),
(569, 'Jeux & Loisirs', '2026-05-13 10:44:17', NULL),
(570, 'Animaux', '2026-05-13 10:55:46', NULL),
(571, 'Services', '2026-05-13 10:55:47', NULL),
(572, 'Bricolage & Jardin', '2026-05-13 10:55:49', NULL),
(573, 'Jeux & Loisirs', '2026-05-13 10:55:50', NULL),
(574, 'Livres, Médias & Formation', '2026-05-13 10:55:51', NULL),
(575, 'Boissons', '2026-05-13 10:55:53', NULL),
(576, 'Produits technologiques', '2026-05-13 10:55:53', NULL),
(577, 'Mobilier et décoration', '2026-05-13 10:55:54', NULL),
(578, 'Beauté & Santé', '2026-05-13 10:55:55', NULL),
(579, 'Vêtements et accessoires', '2026-05-13 10:55:55', NULL),
(580, 'Enfants & Bébés', '2026-05-13 10:55:56', NULL),
(581, 'Automobile & Moto', '2026-05-13 10:55:57', NULL),
(582, 'Animaux', '2026-05-13 11:04:51', NULL),
(583, 'Beauté & Santé', '2026-05-13 14:54:16', NULL),
(584, 'Enfants & Bébés', '2026-05-13 15:03:34', NULL),
(585, 'Automobile & Moto', '2026-05-13 15:12:50', NULL),
(586, 'Livres, Médias & Formation', '2026-05-13 15:23:20', NULL),
(587, 'Services', '2026-05-13 15:33:15', NULL),
(588, 'Produits technologiques', '2026-05-13 15:43:16', NULL),
(589, 'Bricolage & Jardin', '2026-05-13 16:12:10', NULL),
(590, 'Vêtements et accessoires', '2026-05-13 16:20:42', NULL),
(591, 'Livres, Médias & Formation', '2026-05-13 16:31:30', NULL),
(592, 'Bricolage & Jardin', '2026-05-13 16:39:21', NULL),
(593, 'Enfants & Bébés', '2026-05-13 16:59:50', NULL),
(594, 'Automobile & Moto', '2026-05-13 17:58:57', NULL),
(595, 'Animaux', '2026-05-13 17:58:57', NULL),
(596, 'Boissons', '2026-05-13 17:58:57', NULL),
(597, 'Jeux & Loisirs', '2026-05-13 17:58:57', NULL),
(598, 'Beauté & Santé', '2026-05-13 17:58:57', NULL),
(599, 'Vêtements et accessoires', '2026-05-13 17:58:57', NULL),
(600, 'Bricolage & Jardin', '2026-05-13 17:58:57', NULL),
(601, 'Produits technologiques', '2026-05-13 17:58:58', NULL),
(602, 'Enfants & Bébés', '2026-05-13 17:58:58', NULL),
(603, 'Mobilier et décoration', '2026-05-13 17:58:58', NULL),
(604, 'Services', '2026-05-13 17:58:58', NULL),
(605, 'Livres, Médias & Formation', '2026-05-13 17:58:58', NULL),
(606, 'Mobilier et décoration', '2026-05-13 20:37:53', NULL),
(607, 'Automobile & Moto', '2026-05-13 20:47:11', NULL),
(608, 'Beauté & Santé', '2026-05-13 20:58:18', NULL),
(609, 'Boissons', '2026-05-13 21:05:56', NULL),
(610, 'Jeux & Loisirs', '2026-05-13 21:17:46', NULL),
(611, 'Vêtements et accessoires', '2026-05-13 21:27:45', NULL),
(612, 'Produits technologiques', '2026-05-13 21:48:54', NULL),
(613, 'Services', '2026-05-13 21:59:39', NULL),
(614, 'Animaux', '2026-05-13 22:09:05', NULL),
(615, 'Services', '2026-05-14 00:18:33', NULL),
(616, 'Vêtements et accessoires', '2026-05-14 00:18:33', NULL),
(617, 'Produits technologiques', '2026-05-14 00:18:33', NULL),
(618, 'Automobile & Moto', '2026-05-14 00:18:33', NULL),
(619, 'Livres, Médias & Formation', '2026-05-14 00:18:33', NULL),
(620, 'Bricolage & Jardin', '2026-05-14 00:18:33', NULL),
(621, 'Animaux', '2026-05-14 00:18:33', NULL),
(622, 'Beauté & Santé', '2026-05-14 00:18:33', NULL),
(623, 'Jeux & Loisirs', '2026-05-14 00:18:33', NULL),
(624, 'Enfants & Bébés', '2026-05-14 00:18:33', NULL),
(625, 'Mobilier et décoration', '2026-05-14 00:18:33', NULL),
(626, 'Boissons', '2026-05-14 00:18:33', NULL),
(627, 'Électroménager', '2026-05-14 03:51:29', NULL),
(628, 'Jouets & jeux', '2026-05-14 03:51:29', NULL),
(629, 'Électroménager', '2026-05-14 03:51:32', NULL),
(630, 'Électroménager', '2026-05-14 03:51:32', NULL),
(631, 'Électronique & high-tech', '2026-05-14 03:51:35', NULL),
(632, 'Bébé & puériculture', '2026-05-14 03:51:37', NULL),
(633, 'Bébé & puériculture', '2026-05-14 03:51:40', NULL),
(634, 'Bébé & puériculture', '2026-05-14 03:51:41', NULL),
(635, 'Maison & jardin', '2026-05-14 03:51:43', NULL),
(636, 'Maison & jardin', '2026-05-14 03:51:46', NULL),
(637, 'Maison & jardin', '2026-05-14 03:51:47', NULL),
(638, 'Beauté & parfums', '2026-05-14 03:51:52', NULL),
(639, 'Beauté & parfums', '2026-05-14 03:51:55', NULL),
(640, 'Beauté & parfums', '2026-05-14 03:51:55', NULL),
(641, 'Informatique & bureautique', '2026-05-14 03:52:01', NULL),
(642, 'Informatique & bureautique', '2026-05-14 03:52:03', NULL),
(643, 'Informatique & bureautique', '2026-05-14 03:52:04', NULL),
(644, 'Alimentation & boissons', '2026-05-14 03:52:14', NULL),
(645, 'Femme', '2026-05-14 03:52:16', NULL),
(646, 'Auto & moto', '2026-05-14 03:52:16', NULL),
(647, 'Homme', '2026-05-14 03:52:19', NULL),
(648, 'Femme', '2026-05-14 03:52:19', NULL),
(649, 'Femme', '2026-05-14 03:52:19', NULL),
(650, 'Téléphonie & accessoires', '2026-05-14 03:52:22', NULL),
(651, 'Sport & loisirs', '2026-05-14 03:52:24', NULL),
(652, 'Bricolage & outillage', '2026-05-14 03:52:24', NULL),
(653, 'iphone', '2026-05-14 09:42:35', NULL),
(654, 'Beauté & parfums', '2026-05-15 03:51:31', NULL),
(655, 'Femme', '2026-05-15 03:51:31', NULL),
(656, 'Électroménager', '2026-05-15 03:51:33', NULL),
(657, 'Électroménager', '2026-05-15 03:51:36', NULL),
(658, 'Jouets & jeux', '2026-05-15 03:51:38', NULL),
(659, 'Jouets & jeux', '2026-05-15 03:51:41', NULL),
(660, 'Électronique & high-tech', '2026-05-15 03:51:41', NULL),
(661, 'Jouets & jeux', '2026-05-15 03:51:41', NULL),
(662, 'Électronique & high-tech', '2026-05-15 03:51:44', NULL),
(663, 'Électronique & high-tech', '2026-05-15 03:51:44', NULL),
(664, 'Homme', '2026-05-15 03:51:48', NULL),
(665, 'Homme', '2026-05-15 03:51:56', NULL),
(666, 'Homme', '2026-05-15 03:51:56', NULL),
(667, 'Informatique & bureautique', '2026-05-15 03:52:03', NULL),
(668, 'Téléphonie & accessoires', '2026-05-15 03:52:03', NULL),
(669, 'Téléphonie & accessoires', '2026-05-15 03:52:06', NULL),
(670, 'Téléphonie & accessoires', '2026-05-15 03:52:07', NULL),
(671, 'Bricolage & outillage', '2026-05-15 03:52:11', NULL),
(672, 'Bricolage & outillage', '2026-05-15 03:52:14', NULL),
(673, 'Bricolage & outillage', '2026-05-15 03:52:15', NULL),
(674, 'Bébé & puériculture', '2026-05-15 03:52:28', NULL),
(675, 'Bébé & puériculture', '2026-05-15 03:52:31', NULL),
(676, 'Alimentation & boissons', '2026-05-15 03:53:21', NULL),
(677, 'Alimentation & boissons', '2026-05-15 03:53:24', NULL),
(678, 'Alimentation & boissons', '2026-05-15 03:53:25', NULL),
(679, 'Auto & moto', '2026-05-15 03:53:29', NULL),
(680, 'Auto & moto', '2026-05-15 03:53:32', NULL),
(681, 'Auto & moto', '2026-05-15 03:53:33', NULL),
(682, 'Maison & jardin', '2026-05-15 03:53:37', NULL),
(683, 'Maison & jardin', '2026-05-15 03:53:41', NULL),
(684, 'Sport & loisirs', '2026-05-15 03:53:42', NULL),
(685, 'Sport & loisirs', '2026-05-15 03:53:45', NULL),
(686, 'Sport & loisirs', '2026-05-15 03:53:46', NULL),
(687, 'Boîte rangement', '2026-05-15 04:44:20', NULL),
(688, 'Boîte rangement', '2026-05-15 04:46:56', NULL),
(689, 'Électronique & high-tech', '2026-05-16 03:51:33', NULL),
(690, 'Bébé & puériculture', '2026-05-16 03:51:33', NULL),
(691, 'Auto & moto', '2026-05-16 03:51:35', NULL),
(692, 'Maison & jardin', '2026-05-16 03:51:35', NULL),
(693, 'Téléphonie & accessoires', '2026-05-16 03:51:36', NULL),
(694, 'Alimentation & boissons', '2026-05-16 03:51:38', NULL),
(695, 'Beauté & parfums', '2026-05-16 03:51:38', NULL),
(696, 'Homme', '2026-05-16 03:51:40', NULL),
(697, 'Beauté & parfums', '2026-05-16 03:51:41', NULL),
(698, 'Sport & loisirs', '2026-05-16 03:51:42', NULL),
(699, 'Femme', '2026-05-16 03:51:43', NULL),
(700, 'Femme', '2026-05-16 03:51:46', NULL),
(701, 'Électroménager', '2026-05-16 03:51:56', NULL),
(702, 'Électroménager', '2026-05-16 03:52:00', NULL),
(703, 'Jouets & jeux', '2026-05-16 03:52:03', NULL),
(704, 'Jouets & jeux', '2026-05-16 03:52:06', NULL),
(705, 'Informatique & bureautique', '2026-05-16 03:52:24', NULL),
(706, 'Informatique & bureautique', '2026-05-16 03:52:27', NULL),
(707, 'Bricolage & outillage', '2026-05-16 03:53:03', NULL),
(708, 'Bricolage & outillage', '2026-05-16 03:53:06', NULL),
(709, 'Sport & loisirs', '2026-05-17 03:51:34', NULL),
(710, 'Sport & loisirs', '2026-05-17 03:51:36', NULL),
(711, 'Électroménager', '2026-05-17 03:51:37', NULL),
(712, 'Beauté & parfums', '2026-05-17 03:51:39', NULL),
(713, 'Femme', '2026-05-17 03:51:41', NULL),
(714, 'Informatique & bureautique', '2026-05-17 03:51:41', NULL),
(715, 'Jouets & jeux', '2026-05-17 03:51:43', NULL),
(716, 'Auto & moto', '2026-05-17 03:51:43', NULL),
(717, 'Auto & moto', '2026-05-17 03:51:46', NULL),
(718, 'Maison & jardin', '2026-05-17 03:51:47', NULL),
(719, 'Bébé & puériculture', '2026-05-17 03:51:49', NULL),
(720, 'Maison & jardin', '2026-05-17 03:51:50', NULL),
(721, 'Bébé & puériculture', '2026-05-17 03:51:52', NULL),
(722, 'Électronique & high-tech', '2026-05-17 03:52:07', NULL),
(723, 'Électronique & high-tech', '2026-05-17 03:52:10', NULL),
(724, 'Téléphonie & accessoires', '2026-05-17 03:52:20', NULL),
(725, 'Téléphonie & accessoires', '2026-05-17 03:52:23', NULL),
(726, 'Alimentation & boissons', '2026-05-17 03:52:34', NULL),
(727, 'Alimentation & boissons', '2026-05-17 03:52:37', NULL),
(728, 'Homme', '2026-05-17 03:52:39', NULL),
(729, 'Homme', '2026-05-17 03:52:46', NULL),
(730, 'Bricolage & outillage', '2026-05-17 03:52:48', NULL),
(731, 'Électronique & high-tech', '2026-05-18 03:51:35', NULL),
(732, 'Alimentation & boissons', '2026-05-18 03:51:36', NULL),
(733, 'Bébé & puériculture', '2026-05-18 03:51:37', NULL),
(734, 'Maison & jardin', '2026-05-18 03:51:38', NULL),
(735, 'Sport & loisirs', '2026-05-18 03:51:38', NULL),
(736, 'Sport & loisirs', '2026-05-18 03:51:41', NULL),
(737, 'Auto & moto', '2026-05-18 03:51:41', NULL),
(738, 'Homme', '2026-05-18 03:51:43', NULL),
(739, 'Femme', '2026-05-18 03:51:43', NULL),
(740, 'Beauté & parfums', '2026-05-18 03:51:45', NULL),
(741, 'Femme', '2026-05-18 03:51:46', NULL),
(742, 'Électroménager', '2026-05-18 03:51:47', NULL),
(743, 'Beauté & parfums', '2026-05-18 03:51:47', NULL),
(744, 'Électroménager', '2026-05-18 03:51:49', NULL),
(745, 'Jouets & jeux', '2026-05-18 03:52:02', NULL),
(746, 'Jouets & jeux', '2026-05-18 03:52:05', NULL),
(747, 'Informatique & bureautique', '2026-05-18 03:52:25', NULL),
(748, 'Informatique & bureautique', '2026-05-18 03:52:28', NULL),
(749, 'Téléphonie & accessoires', '2026-05-18 03:53:10', NULL),
(750, 'Téléphonie & accessoires', '2026-05-18 03:53:13', NULL),
(751, 'Bricolage & outillage', '2026-05-18 03:53:15', NULL),
(752, 'Bricolage & outillage', '2026-05-18 03:53:22', NULL),
(753, 'Habit', '2026-05-20 08:55:42', NULL),
(754, 'Phone', '2026-05-20 08:58:10', NULL),
(755, 'Phone', '2026-05-20 08:58:21', NULL),
(756, 'Phone', '2026-05-20 08:58:27', NULL),
(757, 'Phone', '2026-05-20 08:59:24', NULL),
(758, 'Phone', '2026-05-20 08:59:26', NULL),
(759, 'Phone', '2026-05-20 08:59:30', NULL),
(760, 'Phone', '2026-05-20 08:59:34', NULL),
(761, 'Phone', '2026-05-20 08:59:40', NULL),
(762, 'Phone', '2026-05-20 09:00:04', NULL),
(763, 'Phone', '2026-05-20 09:00:10', NULL),
(764, 'Phone', '2026-05-20 09:00:15', NULL),
(765, 'Phone', '2026-05-20 09:00:23', NULL),
(766, 'Telephone', '2026-05-20 09:00:55', NULL),
(767, 'Telephone', '2026-05-20 09:01:19', NULL),
(768, 'Telephone', '2026-05-20 09:01:32', NULL),
(769, 'Ipho', '2026-05-20 10:17:27', NULL),
(770, 'Ipho', '2026-05-20 10:18:00', NULL),
(771, 'Lonovo t490', '2026-05-20 16:53:58', NULL),
(772, 'Lonovo', '2026-05-20 16:54:05', NULL),
(773, 'Hp', '2026-05-20 16:54:14', NULL),
(774, 'Thimbane apple', '2026-05-20 21:20:58', NULL),
(775, 'Thimbane apple', '2026-05-20 21:26:56', NULL),
(776, 'Thimbane apple', '2026-05-20 21:27:03', NULL),
(777, 'Ipho', '2026-05-21 07:24:19', NULL),
(778, 'macbook', '2026-05-25 05:33:02', NULL),
(779, 'iphone', '2026-05-25 05:33:13', NULL),
(780, 'samsung', '2026-05-25 05:33:21', NULL),
(781, 'cafe', '2026-05-25 05:33:26', NULL),
(782, '17', '2026-05-25 05:33:31', NULL),
(783, 'Short', '2026-05-26 21:55:16', NULL),
(784, 'Short', '2026-05-26 21:55:18', NULL),
(785, 'Boissons', '2026-05-26 22:40:42', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `section4_config`
--

DROP TABLE IF EXISTS `section4_config`;
CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id)',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_fond` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_section4_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `slider`
--

DROP TABLE IF EXISTS `slider`;
CREATE TABLE IF NOT EXISTS `slider` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id) pour vitrine boutique',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paragraphe` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bouton_texte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bouton_lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`),
  KEY `idx_slider_admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_articles`
--

DROP TABLE IF EXISTS `stock_articles`;
CREATE TABLE IF NOT EXISTS `stock_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_principale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '0',
  `categorie_id` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_mouvements`
--

DROP TABLE IF EXISTS `stock_mouvements`;
CREATE TABLE IF NOT EXISTS `stock_mouvements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('entree','sortie','inventaire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_article_id` int DEFAULT NULL,
  `produit_id` int DEFAULT NULL,
  `quantite` int NOT NULL,
  `quantite_avant` int DEFAULT NULL,
  `quantite_apres` int DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_numero` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_stock_article` (`stock_article_id`),
  KEY `idx_produit` (`produit_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date_mouvement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `super_admin`
--

DROP TABLE IF EXISTS `super_admin`;
CREATE TABLE IF NOT EXISTS `super_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` datetime DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_super_admin_email` (`email`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `super_admin`
--

INSERT INTO `super_admin` (`id`, `nom`, `prenom`, `email`, `password`, `date_creation`, `derniere_connexion`, `statut`) VALUES
(1, 'OYONO EFFE', 'Nick Ludvann', 'superadmin@gmail.com', '$2y$10$zVuFpBgYqzjJhCurZCbkg.vsEGYmWOYFTBeR7PaCYzrF.Jz1LJR1e', '2026-04-17 01:02:05', '2026-06-03 16:44:26', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `super_admin_logs`
--

DROP TABLE IF EXISTS `super_admin_logs`;
CREATE TABLE IF NOT EXISTS `super_admin_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `super_admin_id` int NOT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cible_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cible_id` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_super_admin` (`super_admin_id`),
  KEY `idx_date` (`date_action`)
) ENGINE=InnoDB AUTO_INCREMENT=784 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `super_admin_logs`
--

INSERT INTO `super_admin_logs` (`id`, `super_admin_id`, `action`, `cible_type`, `cible_id`, `details`, `date_action`, `ip`) VALUES
(1, 1, 'hero_affiche_ajoutée', 'hero_affiche', 1, 'hero_13e48e9079999ded460a3cbb.jpg', '2026-04-17 01:51:02', '::1'),
(2, 1, 'hero_affiche_ajoutée', 'hero_affiche', 2, 'hero_e640b4de4b9b502ea6431cd4.jpg', '2026-04-17 01:55:24', '::1'),
(3, 1, 'hero_affiche_ajoutée', 'hero_affiche', 3, 'hero_01bf86a70e3097a289be8fdf.jpg', '2026-04-17 01:56:17', '::1'),
(4, 1, 'hero_affiche_ajoutée', 'hero_affiche', 4, 'hero_f9d4095019ddef7ea42116f2.jpg', '2026-04-17 02:09:02', '::1'),
(5, 1, 'hero_affiche_supprimée', 'hero_affiche', 2, 'hero_e640b4de4b9b502ea6431cd4.jpg', '2026-04-17 02:09:11', '::1'),
(6, 1, 'hero_affiche_supprimée', 'hero_affiche', 3, 'hero_01bf86a70e3097a289be8fdf.jpg', '2026-04-17 02:09:15', '::1'),
(7, 1, 'categorie_generale_creee', 'categories_generales', 67, 'teste', '2026-04-18 12:48:23', '::1'),
(8, 1, 'categorie_generale_supprimee', 'categories_generales', 67, '', '2026-04-18 12:48:29', '::1'),
(9, 1, 'categorie_generale_supprimee', 'categories_generales', 11, '', '2026-04-18 12:48:59', '::1'),
(10, 1, 'categorie_generale_supprimee', 'categories_generales', 12, '', '2026-04-18 12:49:03', '::1'),
(11, 1, 'categorie_generale_creee', 'categories_generales', 68, 'teste', '2026-04-18 12:55:20', '::1'),
(12, 1, 'sous_categorie_plateforme_creee', 'categories', 37, 'HOMME', '2026-04-18 13:11:14', '::1'),
(13, 1, 'sous_categorie_plateforme_creee', 'categories', 38, 'FEMME', '2026-04-18 13:12:07', '::1'),
(14, 1, 'genre_cree', 'genres', 1, 'HOMME', '2026-04-18 13:29:12', '::1'),
(15, 1, 'genre_cree', 'genres', 2, 'FEMME', '2026-04-18 13:31:56', '::1'),
(16, 1, 'genre_supprime', 'genres', 2, 'FEMME', '2026-04-18 13:59:16', '::1'),
(17, 1, 'genre_supprime', 'genres', 1, 'HOMME', '2026-04-18 13:59:19', '::1'),
(18, 1, 'genre_cree', 'genres', 3, 'HOMME', '2026-04-18 14:00:32', '::1'),
(19, 1, 'genre_cree', 'genres', 4, 'FILLE', '2026-04-18 14:01:47', '::1'),
(20, 1, 'genre_cree', 'genres', 5, 'FEMME', '2026-04-18 14:02:07', '::1'),
(21, 1, 'genre_cree', 'genres', 6, 'Garçon', '2026-04-18 14:03:10', '::1'),
(22, 1, 'categorie_generale_modifiee', 'categories_generales', 68, 'teste', '2026-04-18 14:30:25', '::1'),
(23, 1, 'categorie_generale_modifiee', 'categories_generales', 68, 'teste', '2026-04-18 14:32:12', '::1'),
(24, 1, 'hero_affiche_supprimée', 'hero_affiche', 1, 'hero_13e48e9079999ded460a3cbb.jpg', '2026-04-18 13:36:58', '176.79.77.210'),
(25, 1, 'hero_affiche_supprimée', 'hero_affiche', 4, 'hero_f9d4095019ddef7ea42116f2.jpg', '2026-04-18 13:37:02', '176.79.77.210'),
(26, 1, 'hero_affiche_ajoutée', 'hero_affiche', 5, 'hero_0d22935e63681f532bdfc81b.png', '2026-04-19 07:36:53', '176.79.77.210'),
(27, 1, 'hero_affiche_supprimée', 'hero_affiche', 5, 'hero_0d22935e63681f532bdfc81b.png', '2026-04-19 07:37:07', '176.79.77.210'),
(28, 1, 'hero_affiche_ajoutée', 'hero_affiche', 6, 'hero_d3a8c672b0bc5c2090423b6b.png', '2026-04-19 07:44:15', '176.79.77.210'),
(29, 1, 'hero_affiche_ajoutée', 'hero_affiche', 7, 'hero_38ba2fdc0841d65007b80d09.png', '2026-04-19 07:47:00', '176.79.77.210'),
(30, 1, 'hero_affiche_ajoutée', 'hero_affiche', 8, 'hero_a8693b8e6b02fd0441031023.png', '2026-04-19 07:49:09', '176.79.77.210'),
(31, 1, 'hero_affiche_ajoutée', 'hero_affiche', 9, 'hero_b4042cd0a605905d67637ce9.png', '2026-04-19 07:49:33', '176.79.77.210'),
(32, 1, 'categorie_generale_supprimee', 'categories_generales', 68, '', '2026-04-19 08:00:24', '176.79.77.210'),
(33, 1, 'genre_cree', 'genres', 7, 'Iphone', '2026-04-20 06:04:15', '195.23.211.117'),
(34, 1, 'genre_cree', 'genres', 8, 'endroid', '2026-04-20 06:04:34', '195.23.211.117'),
(35, 1, 'genre_cree', 'genres', 9, 'accessoires', '2026-04-20 06:05:56', '195.23.211.117'),
(36, 1, 'genre_cree', 'genres', 10, 'Ordinateur desktop', '2026-04-20 06:07:01', '195.23.211.117'),
(37, 1, 'genre_cree', 'genres', 11, 'Ordinateur', '2026-04-20 06:07:32', '195.23.211.117'),
(38, 1, 'genre_cree', 'genres', 12, 'Office 365', '2026-04-20 06:07:59', '195.23.211.117'),
(39, 1, 'genre_cree', 'genres', 13, 'Autre Logiciel', '2026-04-20 06:08:18', '195.23.211.117'),
(40, 1, 'genre_modifie', 'genres', 8, 'endroid', '2026-04-20 06:09:33', '195.23.211.117'),
(41, 1, 'genre_modifie', 'genres', 7, 'Iphone', '2026-04-20 06:09:42', '195.23.211.117'),
(42, 1, 'genre_modifie', 'genres', 13, 'Autre', '2026-04-20 06:10:13', '195.23.211.117'),
(43, 1, 'genre_modifie', 'genres', 7, 'TELEPHONE IPHONE', '2026-04-20 06:11:59', '195.23.211.117'),
(44, 1, 'genre_modifie', 'genres', 11, 'Ordinateurs portables', '2026-04-20 06:15:50', '195.23.211.117'),
(45, 1, 'genre_modifie', 'genres', 10, 'PC de bureau / workstation', '2026-04-20 06:16:07', '195.23.211.117'),
(46, 1, 'genre_cree', 'genres', 14, 'Composants PC (RAM, GPU, SSD)', '2026-04-20 06:16:24', '195.23.211.117'),
(47, 1, 'genre_cree', 'genres', 15, 'Périphériques (clavier, souris, écran)', '2026-04-20 06:16:38', '195.23.211.117'),
(48, 1, 'genre_cree', 'genres', 16, 'Imprimantes & scanners', '2026-04-20 06:16:53', '195.23.211.117'),
(49, 1, 'genre_cree', 'genres', 17, 'Logiciels (Office, antivirus, licences)', '2026-04-20 06:19:04', '195.23.211.117'),
(50, 1, 'genre_cree', 'genres', 18, 'Réseaux (routeurs, switch, WiFi)', '2026-04-20 06:19:27', '195.23.211.117'),
(51, 1, 'genre_modifie', 'genres', 7, 'Smartphones', '2026-04-20 06:21:05', '195.23.211.117'),
(52, 1, 'genre_modifie', 'genres', 7, 'Smartphones', '2026-04-20 06:21:05', '195.23.211.117'),
(53, 1, 'genre_modifie', 'genres', 8, 'Téléphones fixes', '2026-04-20 06:23:23', '195.23.211.117'),
(54, 1, 'genre_cree', 'genres', 19, 'Coques & protections', '2026-04-20 06:23:51', '195.23.211.117'),
(55, 1, 'genre_cree', 'genres', 20, 'Chargeurs & câbles', '2026-04-20 06:24:15', '195.23.211.117'),
(56, 1, 'genre_cree', 'genres', 21, 'Écouteurs & casques', '2026-04-20 06:24:35', '195.23.211.117'),
(57, 1, 'genre_cree', 'genres', 22, 'Smartwatch', '2026-04-20 06:24:53', '195.23.211.117'),
(58, 1, 'genre_cree', 'genres', 23, 'Mobilier intérieur', '2026-04-20 07:03:10', '195.23.211.117'),
(59, 1, 'genre_cree', 'genres', 24, 'Décoration', '2026-04-20 07:03:26', '195.23.211.117'),
(60, 1, 'genre_cree', 'genres', 25, 'Linge de maison', '2026-04-20 07:03:41', '195.23.211.117'),
(61, 1, 'genre_cree', 'genres', 26, 'Cuisine & arts de la table', '2026-04-20 07:03:59', '195.23.211.117'),
(62, 1, 'genre_cree', 'genres', 27, 'Jardinage (outils, plantes)', '2026-04-20 07:04:15', '195.23.211.117'),
(63, 1, 'genre_cree', 'genres', 28, 'Bricolage & outillage', '2026-04-20 07:04:32', '195.23.211.117'),
(64, 1, 'genre_cree', 'genres', 29, 'Éclairage', '2026-04-20 07:04:45', '195.23.211.117'),
(65, 1, 'genre_cree', 'genres', 30, 'Voitures', '2026-04-20 07:05:10', '195.23.211.117'),
(66, 1, 'genre_cree', 'genres', 31, 'Motos & scooters', '2026-04-20 07:05:23', '195.23.211.117'),
(67, 1, 'genre_cree', 'genres', 32, 'Pièces détachées', '2026-04-20 07:05:39', '195.23.211.117'),
(68, 1, 'genre_cree', 'genres', 33, 'Pneus & jantes', '2026-04-20 07:05:55', '195.23.211.117'),
(69, 1, 'genre_cree', 'genres', 34, 'Accessoires auto (GPS, dashcam)', '2026-04-20 07:06:13', '195.23.211.117'),
(70, 1, 'genre_cree', 'genres', 35, 'Entretien & huiles', '2026-04-20 07:06:26', '195.23.211.117'),
(71, 1, 'genre_cree', 'genres', 36, 'Sécurité & signalisation', '2026-04-20 07:06:42', '195.23.211.117'),
(72, 1, 'genre_cree', 'genres', 37, 'TV & home cinéma', '2026-04-20 07:09:26', '195.23.211.117'),
(73, 1, 'genre_cree', 'genres', 38, 'Audio (enceintes, casques, barres de son)', '2026-04-20 07:09:42', '195.23.211.117'),
(74, 1, 'genre_cree', 'genres', 39, 'Caméras & photographie', '2026-04-20 07:10:58', '195.23.211.117'),
(75, 1, 'genre_cree', 'genres', 40, 'Objets connectés (montres, domotique)', '2026-04-20 07:11:14', '195.23.211.117'),
(76, 1, 'genre_cree', 'genres', 41, 'Gaming (consoles, accessoires)', '2026-04-20 07:11:27', '195.23.211.117'),
(77, 1, 'genre_cree', 'genres', 42, 'Gros électroménager (frigo, lave-linge, four)', '2026-04-20 07:11:49', '195.23.211.117'),
(78, 1, 'genre_cree', 'genres', 43, 'Petit électroménager (bouilloire, grille-pain, mixeur)', '2026-04-20 07:12:04', '195.23.211.117'),
(79, 1, 'genre_cree', 'genres', 44, 'Cuisine (robots, cafetières, air fryer)', '2026-04-20 07:12:18', '195.23.211.117'),
(80, 1, 'genre_cree', 'genres', 45, 'Entretien maison (aspirateurs, nettoyeurs vapeur)', '2026-04-20 07:12:32', '195.23.211.117'),
(81, 1, 'genre_cree', 'genres', 46, 'Confort maison (climatisation, chauffage)', '2026-04-20 07:12:46', '195.23.211.117'),
(82, 1, 'genre_cree', 'genres', 47, 'Jouets pour bébés', '2026-04-20 07:13:12', '195.23.211.117'),
(83, 1, 'genre_cree', 'genres', 48, 'Jeux éducatifs', '2026-04-20 07:13:28', '195.23.211.117'),
(84, 1, 'genre_cree', 'genres', 49, 'Jeux de société', '2026-04-20 07:13:39', '195.23.211.117'),
(85, 1, 'genre_cree', 'genres', 50, 'Figurines & poupées', '2026-04-20 07:13:54', '195.23.211.117'),
(86, 1, 'genre_cree', 'genres', 51, 'Jeux de construction (type LEGO)', '2026-04-20 07:14:07', '195.23.211.117'),
(87, 1, 'genre_cree', 'genres', 52, 'Jeux vidéo', '2026-04-20 07:14:19', '195.23.211.117'),
(88, 1, 'genre_cree', 'genres', 53, 'Jeux d’extérieur', '2026-04-20 07:14:31', '195.23.211.117'),
(89, 1, 'genre_cree', 'genres', 54, 'Poussettes & landaus', '2026-04-20 07:14:55', '195.23.211.117'),
(90, 1, 'genre_cree', 'genres', 55, 'Sièges auto bébé', '2026-04-20 07:15:09', '195.23.211.117'),
(91, 1, 'genre_cree', 'genres', 56, 'Lits bébé & berceaux', '2026-04-20 07:15:20', '195.23.211.117'),
(92, 1, 'genre_cree', 'genres', 57, 'Vêtements bébé', '2026-04-20 07:15:36', '195.23.211.117'),
(93, 1, 'genre_cree', 'genres', 58, 'Alimentation bébé (biberons, chauffe-biberons)', '2026-04-20 07:15:49', '195.23.211.117'),
(94, 1, 'genre_cree', 'genres', 59, 'Hygiène & soins bébé', '2026-04-20 07:16:00', '195.23.211.117'),
(95, 1, 'genre_cree', 'genres', 60, 'Jouets d’éveil', '2026-04-20 07:16:12', '195.23.211.117'),
(96, 1, 'genre_cree', 'genres', 61, 'Fitness & musculation', '2026-04-20 07:25:44', '195.23.211.117'),
(97, 1, 'genre_cree', 'genres', 62, 'Sports collectifs (football, basket…)', '2026-04-20 07:26:04', '195.23.211.117'),
(98, 1, 'genre_cree', 'genres', 63, 'Sports individuels (tennis, running…)', '2026-04-20 07:26:25', '195.23.211.117'),
(99, 1, 'genre_cree', 'genres', 64, 'Sports de plein air (randonnée, camping)', '2026-04-20 07:26:40', '195.23.211.117'),
(100, 1, 'genre_cree', 'genres', 65, 'Vélo & trottinette', '2026-04-20 07:26:55', '195.23.211.117'),
(101, 1, 'genre_cree', 'genres', 66, 'Sports aquatiques', '2026-04-20 07:27:05', '195.23.211.117'),
(102, 1, 'genre_cree', 'genres', 67, 'Accessoires & équipements sportifs', '2026-04-20 07:27:29', '195.23.211.117'),
(103, 1, 'genre_cree', 'genres', 68, 'Parfums homme', '2026-04-20 07:28:06', '195.23.211.117'),
(104, 1, 'genre_cree', 'genres', 69, 'Parfums femme', '2026-04-20 07:28:28', '195.23.211.117'),
(105, 1, 'genre_cree', 'genres', 70, 'Maquillage', '2026-04-20 07:28:43', '195.23.211.117'),
(106, 1, 'genre_cree', 'genres', 71, 'Soins visage', '2026-04-20 07:28:56', '195.23.211.117'),
(107, 1, 'genre_cree', 'genres', 72, 'Soins corps', '2026-04-20 07:29:12', '195.23.211.117'),
(108, 1, 'genre_cree', 'genres', 73, 'Soins cheveux', '2026-04-20 07:29:24', '195.23.211.117'),
(109, 1, 'genre_cree', 'genres', 74, 'Appareils de beauté (lisseurs, tondeuses)', '2026-04-20 07:29:41', '195.23.211.117'),
(110, 1, 'genre_cree', 'genres', 75, 'Produits frais', '2026-04-20 07:29:56', '195.23.211.117'),
(111, 1, 'genre_cree', 'genres', 76, 'Épicerie salée', '2026-04-20 07:30:06', '195.23.211.117'),
(112, 1, 'genre_cree', 'genres', 77, 'Épicerie sucrée', '2026-04-20 07:30:18', '195.23.211.117'),
(113, 1, 'genre_cree', 'genres', 78, 'Boissons non alcoolisées', '2026-04-20 07:30:32', '195.23.211.117'),
(114, 1, 'genre_cree', 'genres', 79, 'Café & thé', '2026-04-20 07:30:45', '195.23.211.117'),
(115, 1, 'genre_cree', 'genres', 80, 'Produits bio', '2026-04-20 07:31:38', '195.23.211.117'),
(116, 1, 'genre_cree', 'genres', 81, 'Produits du monde', '2026-04-20 07:31:51', '195.23.211.117'),
(117, 1, 'genre_cree', 'genres', 82, 'Outillage à main', '2026-04-20 07:32:13', '195.23.211.117'),
(118, 1, 'genre_cree', 'genres', 83, 'Outillage électroportatif', '2026-04-20 07:32:25', '195.23.211.117'),
(119, 1, 'genre_cree', 'genres', 84, 'Matériel de chantier', '2026-04-20 07:32:34', '195.23.211.117'),
(120, 1, 'genre_cree', 'genres', 85, 'Plomberie', '2026-04-20 07:32:48', '195.23.211.117'),
(121, 1, 'genre_cree', 'genres', 86, 'Électricité', '2026-04-20 07:33:04', '195.23.211.117'),
(122, 1, 'genre_modifie', 'genres', 24, 'Peinture & décoration', '2026-04-20 07:33:51', '195.23.211.117'),
(123, 1, 'genre_cree', 'genres', 87, 'Quincaillerie', '2026-04-20 07:34:07', '195.23.211.117'),
(124, 1, 'genre_cree', 'genres', 88, 'Chiens', '2026-04-20 07:34:23', '195.23.211.117'),
(125, 1, 'genre_cree', 'genres', 89, 'Chats', '2026-04-20 07:34:35', '195.23.211.117'),
(126, 1, 'genre_cree', 'genres', 90, 'Oiseaux', '2026-04-20 07:34:49', '195.23.211.117'),
(127, 1, 'genre_cree', 'genres', 91, 'Poissons & aquariophilie', '2026-04-20 07:35:13', '195.23.211.117'),
(128, 1, 'genre_cree', 'genres', 92, 'Rongeurs', '2026-04-20 07:35:23', '195.23.211.117'),
(129, 1, 'genre_cree', 'genres', 93, 'Nourriture animale', '2026-04-20 07:35:40', '195.23.211.117'),
(130, 1, 'genre_cree', 'genres', 94, 'Accessoires animaux', '2026-04-20 07:35:53', '195.23.211.117'),
(131, 1, 'genre_cree', 'genres', 95, 'Romans & littérature', '2026-04-20 07:36:52', '195.23.211.117'),
(132, 1, 'genre_cree', 'genres', 96, 'Livres scolaires', '2026-04-20 07:37:02', '195.23.211.117'),
(133, 1, 'genre_cree', 'genres', 97, 'Livres professionnels', '2026-04-20 07:37:16', '195.23.211.117'),
(134, 1, 'genre_cree', 'genres', 98, 'BD & mangas', '2026-04-20 07:37:34', '195.23.211.117'),
(135, 1, 'genre_cree', 'genres', 99, 'Papeterie scolaire', '2026-04-20 07:37:43', '195.23.211.117'),
(136, 1, 'genre_cree', 'genres', 100, 'Fournitures de bureau', '2026-04-20 07:37:53', '195.23.211.117'),
(137, 1, 'genre_cree', 'genres', 101, 'Carnets & agendas', '2026-04-20 07:38:05', '195.23.211.117'),
(138, 1, 'genre_cree', 'genres', 102, 'Montres homme', '2026-04-20 07:38:30', '195.23.211.117'),
(139, 1, 'genre_cree', 'genres', 103, 'Montres femme', '2026-04-20 07:38:44', '195.23.211.117'),
(140, 1, 'genre_cree', 'genres', 104, 'Bijoux fantaisie', '2026-04-20 07:39:15', '195.23.211.117'),
(141, 1, 'genre_cree', 'genres', 105, 'Bijoux en or', '2026-04-20 07:39:29', '195.23.211.117'),
(142, 1, 'genre_cree', 'genres', 106, 'Bijoux en argent', '2026-04-20 07:39:42', '195.23.211.117'),
(143, 1, 'genre_cree', 'genres', 107, 'Bagues', '2026-04-20 07:39:56', '195.23.211.117'),
(144, 1, 'genre_cree', 'genres', 108, 'Bracelets & colliers', '2026-04-20 07:40:31', '195.23.211.117'),
(145, 1, 'genre_cree', 'genres', 109, 'Valises', '2026-04-20 07:40:46', '195.23.211.117'),
(146, 1, 'genre_cree', 'genres', 110, 'Sacs à dos', '2026-04-20 07:40:56', '195.23.211.117'),
(147, 1, 'genre_cree', 'genres', 111, 'Sacs à main', '2026-04-20 07:41:06', '195.23.211.117'),
(148, 1, 'genre_cree', 'genres', 112, 'Portefeuilles', '2026-04-20 07:41:17', '195.23.211.117'),
(149, 1, 'genre_cree', 'genres', 113, 'Sacs de voyage', '2026-04-20 07:41:27', '195.23.211.117'),
(150, 1, 'genre_cree', 'genres', 114, 'Cartables', '2026-04-20 07:41:41', '195.23.211.117'),
(151, 1, 'genre_cree', 'genres', 115, 'Accessoires de voyage', '2026-04-20 07:41:52', '195.23.211.117'),
(152, 1, 'genre_supprime', 'genres', 5, 'FEMME', '2026-04-20 07:42:54', '195.23.211.117'),
(153, 1, 'genre_supprime', 'genres', 3, 'HOMME', '2026-04-20 07:43:07', '195.23.211.117'),
(154, 1, 'categorie_generale_creee', 'categories_generales', 69, 'Homme', '2026-04-20 07:57:12', '195.23.211.117'),
(155, 1, 'categorie_generale_creee', 'categories_generales', 70, 'Femme', '2026-04-20 07:57:40', '195.23.211.117'),
(156, 1, 'categorie_generale_modifiee', 'categories_generales', 69, 'Homme', '2026-04-20 07:57:47', '195.23.211.117'),
(157, 1, 'genre_cree', 'genres', 116, 'Vestes légères', '2026-04-20 08:00:37', '195.23.211.117'),
(158, 1, 'genre_cree', 'genres', 117, 'Sweats & Hoodies', '2026-04-20 08:00:48', '195.23.211.117'),
(159, 1, 'genre_cree', 'genres', 118, 'T-shirts', '2026-04-20 08:01:02', '195.23.211.117'),
(160, 1, 'genre_cree', 'genres', 119, 'Jeans', '2026-04-20 08:01:13', '195.23.211.117'),
(161, 1, 'genre_cree', 'genres', 120, 'Pantalons', '2026-04-20 08:01:25', '195.23.211.117'),
(162, 1, 'genre_cree', 'genres', 121, 'Sportswear', '2026-04-20 08:01:34', '195.23.211.117'),
(163, 1, 'genre_cree', 'genres', 122, 'Sous-vêtements & chaussettes', '2026-04-20 08:01:45', '195.23.211.117'),
(164, 1, 'genre_cree', 'genres', 123, 'Chemises', '2026-04-20 08:02:10', '195.23.211.117'),
(165, 1, 'genre_cree', 'genres', 124, 'Maillots de football', '2026-04-20 08:02:45', '195.23.211.117'),
(166, 1, 'genre_cree', 'genres', 125, 'Costumes', '2026-04-20 08:02:58', '195.23.211.117'),
(167, 1, 'genre_cree', 'genres', 126, 'Robes', '2026-04-20 08:03:14', '195.23.211.117'),
(168, 1, 'genre_cree', 'genres', 127, 'Jupes', '2026-04-20 08:03:24', '195.23.211.117'),
(169, 1, 'genre_cree', 'genres', 128, 'Tops & Blouses', '2026-04-20 08:03:34', '195.23.211.117'),
(170, 1, 'genre_cree', 'genres', 129, 'Lingerie', '2026-04-20 08:03:47', '195.23.211.117'),
(171, 1, 'genre_modifie', 'genres', 74, 'Appareils de beauté (lisseurs, tondeuses)', '2026-04-20 08:05:45', '195.23.211.117'),
(172, 1, 'genre_modifie', 'genres', 107, 'Bagues', '2026-04-20 08:06:07', '195.23.211.117'),
(173, 1, 'genre_modifie', 'genres', 108, 'Bracelets & colliers', '2026-04-20 08:06:23', '195.23.211.117'),
(174, 1, 'genre_modifie', 'genres', 105, 'Bijoux en or', '2026-04-20 08:06:45', '195.23.211.117'),
(175, 1, 'genre_modifie', 'genres', 106, 'Bijoux en argent', '2026-04-20 08:07:00', '195.23.211.117'),
(176, 1, 'genre_modifie', 'genres', 104, 'Bijoux fantaisie', '2026-04-20 08:07:22', '195.23.211.117'),
(177, 1, 'genre_supprime', 'genres', 4, 'FILLE', '2026-04-20 08:08:01', '195.23.211.117'),
(178, 1, 'genre_modifie', 'genres', 70, 'Maquillage', '2026-04-20 08:08:45', '195.23.211.117'),
(179, 1, 'genre_supprime', 'genres', 6, 'Garçon', '2026-04-20 08:08:59', '195.23.211.117'),
(180, 1, 'genre_modifie', 'genres', 69, 'Parfums', '2026-04-20 08:10:18', '195.23.211.117'),
(181, 1, 'genre_supprime', 'genres', 68, 'Parfums homme', '2026-04-20 08:10:46', '195.23.211.117'),
(182, 1, 'genre_modifie', 'genres', 72, 'Soins corps', '2026-04-20 08:11:20', '195.23.211.117'),
(183, 1, 'genre_modifie', 'genres', 73, 'Soins cheveux', '2026-04-20 08:11:43', '195.23.211.117'),
(184, 1, 'genre_modifie', 'genres', 71, 'Soins visage', '2026-04-20 08:12:18', '195.23.211.117'),
(185, 1, 'categorie_generale_modifiee', 'categories_generales', 13, 'Bébé & puériculture', '2026-04-20 13:20:26', '176.79.77.210'),
(186, 1, 'categorie_generale_modifiee', 'categories_generales', 14, 'Jouets & jeux', '2026-04-20 13:22:25', '176.79.77.210'),
(187, 1, 'categorie_generale_modifiee', 'categories_generales', 15, 'Électroménager', '2026-04-20 13:22:42', '176.79.77.210'),
(188, 1, 'categorie_generale_modifiee', 'categories_generales', 16, 'Électronique & high-tech', '2026-04-20 13:22:56', '176.79.77.210'),
(189, 1, 'categorie_generale_modifiee', 'categories_generales', 18, 'Téléphonie & accessoires', '2026-04-20 13:23:09', '176.79.77.210'),
(190, 1, 'categorie_generale_modifiee', 'categories_generales', 22, 'Beauté & parfums', '2026-04-20 13:23:30', '176.79.77.210'),
(191, 1, 'categorie_generale_modifiee', 'categories_generales', 20, 'Auto & moto', '2026-04-20 13:23:45', '176.79.77.210'),
(192, 1, 'categorie_generale_modifiee', 'categories_generales', 23, 'Alimentation & boissons', '2026-04-20 13:24:14', '176.79.77.210'),
(193, 1, 'categorie_generale_modifiee', 'categories_generales', 24, 'Bricolage & outillage', '2026-04-20 13:25:03', '176.79.77.210'),
(194, 1, 'categorie_generale_modifiee', 'categories_generales', 3, 'Pièces & poids lourds', '2026-04-20 13:25:50', '176.79.77.210'),
(195, 1, 'categorie_generale_modifiee', 'categories_generales', 28, 'Bagagerie & maroquinerie', '2026-04-20 13:26:03', '176.79.77.210'),
(196, 1, 'categorie_generale_modifiee', 'categories_generales', 27, 'Bijoux & montres', '2026-04-20 13:26:14', '176.79.77.210'),
(197, 1, 'categorie_generale_modifiee', 'categories_generales', 26, 'Livres & papeterie', '2026-04-20 13:26:31', '176.79.77.210'),
(198, 1, 'categorie_generale_modifiee', 'categories_generales', 25, 'Animalerie', '2026-04-20 13:26:45', '176.79.77.210'),
(199, 1, 'categorie_generale_modifiee', 'categories_generales', 23, 'Alimentation & boissons', '2026-04-20 13:28:19', '176.79.77.210'),
(200, 1, 'categorie_generale_modifiee', 'categories_generales', 21, 'Sport & loisirs', '2026-04-20 13:28:35', '176.79.77.210'),
(201, 1, 'categorie_generale_supprimee', 'categories_generales', 69, '', '2026-04-27 16:26:05', '37.189.14.76'),
(202, 1, 'categorie_generale_supprimee', 'categories_generales', 70, '', '2026-04-27 16:26:10', '37.189.14.76'),
(203, 1, 'categorie_generale_supprimee', 'categories_generales', 13, '', '2026-04-27 16:32:15', '37.189.14.76'),
(204, 1, 'categorie_generale_supprimee', 'categories_generales', 14, '', '2026-04-27 16:32:19', '37.189.14.76'),
(205, 1, 'categorie_generale_supprimee', 'categories_generales', 15, '', '2026-04-27 16:32:24', '37.189.14.76'),
(206, 1, 'categorie_generale_supprimee', 'categories_generales', 16, '', '2026-04-27 16:32:28', '37.189.14.76'),
(207, 1, 'categorie_generale_supprimee', 'categories_generales', 24, '', '2026-04-27 16:32:33', '37.189.14.76'),
(208, 1, 'categorie_generale_supprimee', 'categories_generales', 26, '', '2026-04-27 16:32:38', '37.189.14.76'),
(209, 1, 'categorie_generale_supprimee', 'categories_generales', 17, '', '2026-04-27 16:32:42', '37.189.14.76'),
(210, 1, 'categorie_generale_supprimee', 'categories_generales', 3, '', '2026-04-27 16:33:17', '37.189.14.76'),
(211, 1, 'genre_supprime', 'genres', 9, 'accessoires', '2026-04-27 16:33:47', '37.189.14.76'),
(212, 1, 'genre_supprime', 'genres', 67, 'Accessoires & équipements sportifs', '2026-04-27 16:33:52', '37.189.14.76'),
(213, 1, 'genre_supprime', 'genres', 94, 'Accessoires animaux', '2026-04-27 16:33:57', '37.189.14.76'),
(214, 1, 'genre_supprime', 'genres', 34, 'Accessoires auto (GPS, dashcam)', '2026-04-27 16:34:00', '37.189.14.76'),
(215, 1, 'genre_supprime', 'genres', 115, 'Accessoires de voyage', '2026-04-27 16:34:04', '37.189.14.76'),
(216, 1, 'genre_supprime', 'genres', 58, 'Alimentation bébé (biberons, chauffe-biberons)', '2026-04-27 16:34:07', '37.189.14.76'),
(217, 1, 'genre_supprime', 'genres', 74, 'Appareils de beauté (lisseurs, tondeuses)', '2026-04-27 16:34:10', '37.189.14.76'),
(218, 1, 'genre_supprime', 'genres', 38, 'Audio (enceintes, casques, barres de son)', '2026-04-27 16:34:13', '37.189.14.76'),
(219, 1, 'genre_supprime', 'genres', 107, 'Bagues', '2026-04-27 16:34:20', '37.189.14.76'),
(220, 1, 'genre_supprime', 'genres', 98, 'BD & mangas', '2026-04-27 16:34:24', '37.189.14.76'),
(221, 1, 'genre_supprime', 'genres', 106, 'Bijoux en argent', '2026-04-27 16:34:33', '37.189.14.76'),
(222, 1, 'genre_supprime', 'genres', 105, 'Bijoux en or', '2026-04-27 16:34:37', '37.189.14.76'),
(223, 1, 'genre_supprime', 'genres', 104, 'Bijoux fantaisie', '2026-04-27 16:34:40', '37.189.14.76'),
(224, 1, 'genre_supprime', 'genres', 78, 'Boissons non alcoolisées', '2026-04-27 16:34:45', '37.189.14.76'),
(225, 1, 'genre_supprime', 'genres', 108, 'Bracelets & colliers', '2026-04-27 16:34:48', '37.189.14.76'),
(226, 1, 'genre_supprime', 'genres', 28, 'Bricolage & outillage', '2026-04-27 16:34:52', '37.189.14.76'),
(227, 1, 'genre_supprime', 'genres', 79, 'Café & thé', '2026-04-27 16:34:55', '37.189.14.76'),
(228, 1, 'genre_supprime', 'genres', 39, 'Caméras & photographie', '2026-04-27 16:34:59', '37.189.14.76'),
(229, 1, 'genre_supprime', 'genres', 101, 'Carnets & agendas', '2026-04-27 16:35:02', '37.189.14.76'),
(230, 1, 'genre_supprime', 'genres', 30, 'Voitures', '2026-04-27 16:35:10', '37.189.14.76'),
(231, 1, 'genre_supprime', 'genres', 114, 'Cartables', '2026-04-27 16:35:14', '37.189.14.76'),
(232, 1, 'genre_supprime', 'genres', 20, 'Chargeurs & câbles', '2026-04-27 16:35:19', '37.189.14.76'),
(233, 1, 'genre_supprime', 'genres', 89, 'Chats', '2026-04-27 16:35:22', '37.189.14.76'),
(234, 1, 'genre_supprime', 'genres', 123, 'Chemises', '2026-04-27 16:35:26', '37.189.14.76'),
(235, 1, 'genre_supprime', 'genres', 88, 'Chiens', '2026-04-27 16:35:30', '37.189.14.76'),
(236, 1, 'genre_supprime', 'genres', 46, 'Confort maison (climatisation, chauffage)', '2026-04-27 16:35:34', '37.189.14.76'),
(237, 1, 'genre_supprime', 'genres', 19, 'Coques & protections', '2026-04-27 16:35:39', '37.189.14.76'),
(238, 1, 'genre_supprime', 'genres', 125, 'Costumes', '2026-04-27 16:35:44', '37.189.14.76'),
(239, 1, 'genre_supprime', 'genres', 21, 'Écouteurs & casques', '2026-04-27 16:35:48', '37.189.14.76'),
(240, 1, 'genre_supprime', 'genres', 44, 'Cuisine (robots, cafetières, air fryer)', '2026-04-27 16:35:52', '37.189.14.76'),
(241, 1, 'genre_supprime', 'genres', 26, 'Cuisine & arts de la table', '2026-04-27 16:36:03', '37.189.14.76'),
(242, 1, 'genre_supprime', 'genres', 86, 'Électricité', '2026-04-27 16:36:07', '37.189.14.76'),
(243, 1, 'genre_supprime', 'genres', 29, 'Éclairage', '2026-04-27 16:36:12', '37.189.14.76'),
(244, 1, 'genre_supprime', 'genres', 35, 'Entretien & huiles', '2026-04-27 16:36:17', '37.189.14.76'),
(245, 1, 'genre_supprime', 'genres', 45, 'Entretien maison (aspirateurs, nettoyeurs vapeur)', '2026-04-27 16:36:27', '37.189.14.76'),
(246, 1, 'genre_supprime', 'genres', 76, 'Épicerie salée', '2026-04-27 16:36:34', '37.189.14.76'),
(247, 1, 'genre_supprime', 'genres', 50, 'Figurines & poupées', '2026-04-27 16:36:43', '37.189.14.76'),
(248, 1, 'genre_supprime', 'genres', 61, 'Fitness & musculation', '2026-04-27 16:36:52', '37.189.14.76'),
(249, 1, 'genre_supprime', 'genres', 100, 'Fournitures de bureau', '2026-04-27 16:37:09', '37.189.14.76'),
(250, 1, 'genre_supprime', 'genres', 41, 'Gaming (consoles, accessoires)', '2026-04-27 16:37:17', '37.189.14.76'),
(251, 1, 'genre_supprime', 'genres', 27, 'Jardinage (outils, plantes)', '2026-04-27 16:37:23', '37.189.14.76'),
(252, 1, 'genre_supprime', 'genres', 42, 'Gros électroménager (frigo, lave-linge, four)', '2026-04-27 16:37:27', '37.189.14.76'),
(253, 1, 'genre_supprime', 'genres', 59, 'Hygiène & soins bébé', '2026-04-27 16:37:33', '37.189.14.76'),
(254, 1, 'genre_supprime', 'genres', 16, 'Imprimantes & scanners', '2026-04-27 16:37:39', '37.189.14.76'),
(255, 1, 'genre_supprime', 'genres', 119, 'Jeans', '2026-04-27 16:37:45', '37.189.14.76'),
(256, 1, 'genre_supprime', 'genres', 53, 'Jeux d’extérieur', '2026-04-27 16:37:51', '37.189.14.76'),
(257, 1, 'genre_supprime', 'genres', 49, 'Jeux de société', '2026-04-27 16:37:55', '37.189.14.76'),
(258, 1, 'genre_supprime', 'genres', 48, 'Jeux éducatifs', '2026-04-27 16:38:00', '37.189.14.76'),
(259, 1, 'genre_supprime', 'genres', 51, 'Jeux de construction (type LEGO)', '2026-04-27 16:38:07', '37.189.14.76'),
(260, 1, 'genre_supprime', 'genres', 52, 'Jeux vidéo', '2026-04-27 16:38:11', '37.189.14.76'),
(261, 1, 'genre_supprime', 'genres', 60, 'Jouets d’éveil', '2026-04-27 16:38:21', '37.189.14.76'),
(262, 1, 'genre_supprime', 'genres', 47, 'Jouets pour bébés', '2026-04-27 16:38:27', '37.189.14.76'),
(263, 1, 'genre_supprime', 'genres', 127, 'Jupes', '2026-04-27 16:38:32', '37.189.14.76'),
(264, 1, 'genre_supprime', 'genres', 25, 'Linge de maison', '2026-04-27 16:38:37', '37.189.14.76'),
(265, 1, 'genre_supprime', 'genres', 96, 'Livres scolaires', '2026-04-27 16:38:42', '37.189.14.76'),
(266, 1, 'genre_supprime', 'genres', 129, 'Lingerie', '2026-04-27 16:38:48', '37.189.14.76'),
(267, 1, 'genre_supprime', 'genres', 56, 'Lits bébé & berceaux', '2026-04-27 16:38:55', '37.189.14.76'),
(268, 1, 'genre_supprime', 'genres', 84, 'Matériel de chantier', '2026-04-27 16:39:09', '37.189.14.76'),
(269, 1, 'genre_supprime', 'genres', 17, 'Logiciels (Office, antivirus, licences)', '2026-04-27 16:39:14', '37.189.14.76'),
(270, 1, 'genre_supprime', 'genres', 103, 'Montres femme', '2026-04-27 16:39:19', '37.189.14.76'),
(271, 1, 'genre_supprime', 'genres', 90, 'Oiseaux', '2026-04-27 16:39:23', '37.189.14.76'),
(272, 1, 'genre_supprime', 'genres', 97, 'Livres professionnels', '2026-04-27 16:39:27', '37.189.14.76'),
(273, 1, 'genre_supprime', 'genres', 124, 'Maillots de football', '2026-04-27 16:39:33', '37.189.14.76'),
(274, 1, 'genre_supprime', 'genres', 40, 'Objets connectés (montres, domotique)', '2026-04-27 16:39:38', '37.189.14.76'),
(275, 1, 'genre_supprime', 'genres', 23, 'Mobilier intérieur', '2026-04-27 16:39:42', '37.189.14.76'),
(276, 1, 'genre_supprime', 'genres', 70, 'Maquillage', '2026-04-27 16:39:47', '37.189.14.76'),
(277, 1, 'genre_supprime', 'genres', 12, 'Office 365', '2026-04-27 16:39:50', '37.189.14.76'),
(278, 1, 'genre_supprime', 'genres', 102, 'Montres homme', '2026-04-27 16:39:54', '37.189.14.76'),
(279, 1, 'genre_supprime', 'genres', 31, 'Motos & scooters', '2026-04-27 16:39:59', '37.189.14.76'),
(280, 1, 'genre_supprime', 'genres', 93, 'Nourriture animale', '2026-04-27 16:40:03', '37.189.14.76'),
(281, 1, 'genre_supprime', 'genres', 82, 'Outillage à main', '2026-04-27 16:40:09', '37.189.14.76'),
(282, 1, 'genre_supprime', 'genres', 83, 'Outillage électroportatif', '2026-04-27 16:40:14', '37.189.14.76'),
(283, 1, 'genre_supprime', 'genres', 120, 'Pantalons', '2026-04-27 16:40:18', '37.189.14.76'),
(284, 1, 'genre_supprime', 'genres', 99, 'Papeterie scolaire', '2026-04-27 16:40:22', '37.189.14.76'),
(285, 1, 'genre_supprime', 'genres', 69, 'Parfums', '2026-04-27 16:40:27', '37.189.14.76'),
(286, 1, 'genre_supprime', 'genres', 24, 'Peinture & décoration', '2026-04-27 16:40:33', '37.189.14.76'),
(287, 1, 'genre_supprime', 'genres', 43, 'Petit électroménager (bouilloire, grille-pain, mixeur)', '2026-04-27 16:40:38', '37.189.14.76'),
(288, 1, 'genre_supprime', 'genres', 32, 'Pièces détachées', '2026-04-27 16:40:42', '37.189.14.76'),
(289, 1, 'genre_supprime', 'genres', 85, 'Plomberie', '2026-04-27 16:40:47', '37.189.14.76'),
(290, 1, 'genre_supprime', 'genres', 33, 'Pneus & jantes', '2026-04-27 16:40:52', '37.189.14.76'),
(291, 1, 'genre_supprime', 'genres', 91, 'Poissons & aquariophilie', '2026-04-27 16:40:56', '37.189.14.76'),
(292, 1, 'genre_supprime', 'genres', 112, 'Portefeuilles', '2026-04-27 16:41:02', '37.189.14.76'),
(293, 1, 'genre_supprime', 'genres', 95, 'Romans & littérature', '2026-04-27 16:41:10', '37.189.14.76'),
(294, 1, 'genre_supprime', 'genres', 54, 'Poussettes & landaus', '2026-04-27 16:41:15', '37.189.14.76'),
(295, 1, 'genre_supprime', 'genres', 80, 'Produits bio', '2026-04-27 16:41:21', '37.189.14.76'),
(296, 1, 'genre_supprime', 'genres', 81, 'Produits du monde', '2026-04-27 16:41:26', '37.189.14.76'),
(297, 1, 'genre_supprime', 'genres', 75, 'Produits frais', '2026-04-27 16:41:35', '37.189.14.76'),
(298, 1, 'genre_supprime', 'genres', 126, 'Robes', '2026-04-27 16:41:39', '37.189.14.76'),
(299, 1, 'genre_supprime', 'genres', 87, 'Quincaillerie', '2026-04-27 16:41:45', '37.189.14.76'),
(300, 1, 'genre_supprime', 'genres', 18, 'Réseaux (routeurs, switch, WiFi)', '2026-04-27 16:41:49', '37.189.14.76'),
(301, 1, 'genre_supprime', 'genres', 92, 'Rongeurs', '2026-04-27 16:41:53', '37.189.14.76'),
(302, 1, 'genre_supprime', 'genres', 110, 'Sacs à dos', '2026-04-27 16:41:58', '37.189.14.76'),
(303, 1, 'genre_supprime', 'genres', 111, 'Sacs à main', '2026-04-27 16:42:02', '37.189.14.76'),
(304, 1, 'genre_supprime', 'genres', 113, 'Sacs de voyage', '2026-04-27 16:42:06', '37.189.14.76'),
(305, 1, 'genre_supprime', 'genres', 36, 'Sécurité & signalisation', '2026-04-27 16:42:10', '37.189.14.76'),
(306, 1, 'genre_supprime', 'genres', 55, 'Sièges auto bébé', '2026-04-27 16:42:14', '37.189.14.76'),
(307, 1, 'genre_supprime', 'genres', 22, 'Smartwatch', '2026-04-27 16:42:21', '37.189.14.76'),
(308, 1, 'genre_supprime', 'genres', 73, 'Soins cheveux', '2026-04-27 16:42:26', '37.189.14.76'),
(309, 1, 'genre_supprime', 'genres', 72, 'Soins corps', '2026-04-27 16:42:30', '37.189.14.76'),
(310, 1, 'genre_supprime', 'genres', 71, 'Soins visage', '2026-04-27 16:42:34', '37.189.14.76'),
(311, 1, 'genre_supprime', 'genres', 122, 'Sous-vêtements & chaussettes', '2026-04-27 16:42:39', '37.189.14.76'),
(312, 1, 'genre_supprime', 'genres', 66, 'Sports aquatiques', '2026-04-27 16:42:44', '37.189.14.76'),
(313, 1, 'genre_supprime', 'genres', 57, 'Vêtements bébé', '2026-04-27 16:42:52', '37.189.14.76'),
(314, 1, 'genre_supprime', 'genres', 62, 'Sports collectifs (football, basket…)', '2026-04-27 16:42:56', '37.189.14.76'),
(315, 1, 'genre_supprime', 'genres', 64, 'Sports de plein air (randonnée, camping)', '2026-04-27 16:43:01', '37.189.14.76'),
(316, 1, 'genre_supprime', 'genres', 63, 'Sports individuels (tennis, running…)', '2026-04-27 16:43:05', '37.189.14.76'),
(317, 1, 'genre_supprime', 'genres', 121, 'Sportswear', '2026-04-27 16:43:10', '37.189.14.76'),
(318, 1, 'genre_supprime', 'genres', 117, 'Sweats & Hoodies', '2026-04-27 16:43:15', '37.189.14.76'),
(319, 1, 'genre_supprime', 'genres', 118, 'T-shirts', '2026-04-27 16:43:27', '37.189.14.76'),
(320, 1, 'genre_supprime', 'genres', 8, 'Téléphones fixes', '2026-04-27 16:43:31', '37.189.14.76'),
(321, 1, 'genre_supprime', 'genres', 128, 'Tops & Blouses', '2026-04-27 16:43:35', '37.189.14.76'),
(322, 1, 'genre_supprime', 'genres', 37, 'TV & home cinéma', '2026-04-27 16:43:42', '37.189.14.76'),
(323, 1, 'genre_supprime', 'genres', 109, 'Valises', '2026-04-27 16:43:46', '37.189.14.76'),
(324, 1, 'genre_supprime', 'genres', 65, 'Vélo & trottinette', '2026-04-27 16:43:50', '37.189.14.76'),
(325, 1, 'genre_supprime', 'genres', 116, 'Vestes légères', '2026-04-27 16:43:54', '37.189.14.76'),
(326, 1, 'categorie_generale_modifiee', 'categories_generales', 27, 'Bijoux & montres', '2026-04-27 16:45:34', '37.189.14.76'),
(327, 1, 'categorie_generale_creee', 'categories_generales', 71, 'Électronique', '2026-04-28 07:43:53', '195.23.211.117'),
(328, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Électronique', '2026-04-28 07:46:18', '195.23.211.117'),
(329, 1, 'categorie_generale_creee', 'categories_generales', 72, 'Maison & Intérieur', '2026-04-28 07:47:42', '195.23.211.117'),
(330, 1, 'categorie_generale_creee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-04-28 07:48:02', '195.23.211.117'),
(331, 1, 'categorie_generale_creee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-04-28 07:48:15', '195.23.211.117'),
(332, 1, 'categorie_generale_creee', 'categories_generales', 75, 'Automobile & Moto', '2026-04-28 07:48:32', '195.23.211.117'),
(333, 1, 'categorie_generale_creee', 'categories_generales', 76, 'Beauté & Santé', '2026-04-28 07:48:52', '195.23.211.117'),
(334, 1, 'categorie_generale_creee', 'categories_generales', 77, 'Animaux', '2026-04-28 07:49:06', '195.23.211.117'),
(335, 1, 'categorie_generale_creee', 'categories_generales', 78, 'Livres, Médias & Formation', '2026-04-28 07:49:31', '195.23.211.117'),
(336, 1, 'categorie_generale_creee', 'categories_generales', 79, 'Services', '2026-04-28 07:49:58', '195.23.211.117'),
(337, 1, 'categorie_generale_creee', 'categories_generales', 80, 'Mode & Style', '2026-04-28 07:50:19', '195.23.211.117'),
(338, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Mode & Style', '2026-04-28 07:51:12', '195.23.211.117'),
(339, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Maison & Intérieur', '2026-04-28 07:51:28', '195.23.211.117'),
(340, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-04-28 07:51:35', '195.23.211.117'),
(341, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Électronique', '2026-04-28 07:52:53', '195.23.211.117'),
(342, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Électronique', '2026-04-28 07:55:19', '195.23.211.117'),
(343, 1, 'categorie_generale_creee', 'categories_generales', 81, 'Boissons', '2026-04-28 07:58:03', '195.23.211.117'),
(344, 1, 'categorie_generale_modifiee', 'categories_generales', 77, 'Animaux', '2026-04-28 07:58:12', '195.23.211.117'),
(345, 1, 'genre_cree', 'genres', 130, 'HOMME', '2026-04-28 07:59:06', '195.23.211.117'),
(346, 1, 'genre_cree', 'genres', 131, 'FEMME', '2026-04-28 07:59:41', '195.23.211.117'),
(347, 1, 'categorie_generale_creee', 'categories_generales', 82, 'Enfants', '2026-04-28 08:01:50', '195.23.211.117'),
(348, 1, 'genre_cree', 'genres', 132, 'Bébé (0–2 ans)', '2026-04-28 08:02:51', '195.23.211.117'),
(349, 1, 'genre_cree', 'genres', 133, 'Enfants (3–10 ans)', '2026-04-28 08:03:26', '195.23.211.117'),
(350, 1, 'genre_cree', 'genres', 134, 'Ados (11–16 ans)', '2026-04-28 08:03:46', '195.23.211.117'),
(351, 1, 'genre_supprime', 'genres', 134, 'Ados (11–16 ans)', '2026-04-28 08:05:53', '195.23.211.117'),
(352, 1, 'genre_supprime', 'genres', 132, 'Bébé (0–2 ans)', '2026-04-28 08:05:56', '195.23.211.117'),
(353, 1, 'genre_supprime', 'genres', 133, 'Enfants (3–10 ans)', '2026-04-28 08:05:58', '195.23.211.117'),
(354, 1, 'genre_cree', 'genres', 135, 'FILLE', '2026-04-28 08:06:13', '195.23.211.117'),
(355, 1, 'genre_cree', 'genres', 136, 'GARÇON', '2026-04-28 08:06:34', '195.23.211.117'),
(356, 1, 'sous_categorie_creee', 'categories', 39, 'Vêtements enfants', '2026-04-28 08:08:07', '195.23.211.117'),
(357, 1, 'sous_categorie_creee', 'categories', 40, 'Jouets', '2026-04-28 08:09:02', '195.23.211.117'),
(358, 1, 'sous_categorie_creee', 'categories', 42, 'Puériculture (bébé)', '2026-04-28 08:09:51', '195.23.211.117'),
(359, 1, 'sous_categorie_creee', 'categories', 43, 'Équipement scolaire', '2026-04-28 08:10:05', '195.23.211.117'),
(360, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-04-28 08:11:41', '195.23.211.117'),
(361, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Vêtements et accessoires', '2026-04-28 08:12:55', '195.23.211.117'),
(362, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-04-28 08:13:23', '195.23.211.117'),
(363, 1, 'sous_categorie_creee', 'categories', 44, 'Chaussures', '2026-04-28 08:14:42', '195.23.211.117'),
(364, 1, 'sous_categorie_creee', 'categories', 45, 'Sacs & Bagages', '2026-04-28 08:14:56', '195.23.211.117'),
(365, 1, 'sous_categorie_creee', 'categories', 46, 'Accessoires (montres, ceintures, lunettes…)', '2026-04-28 08:15:22', '195.23.211.117'),
(366, 1, 'sous_categorie_creee', 'categories', 47, 'Bijoux', '2026-04-28 08:15:38', '195.23.211.117'),
(367, 1, 'sous_categorie_creee', 'categories', 48, 'Vêtements de sport', '2026-04-28 08:15:51', '195.23.211.117'),
(368, 1, 'sous_categorie_creee', 'categories', 49, 'Sous-vêtements & Lingerie', '2026-04-28 08:16:03', '195.23.211.117'),
(369, 1, 'sous_categorie_creee', 'categories', 50, 'Tenues traditionnelles', '2026-04-28 08:16:14', '195.23.211.117'),
(370, 1, 'sous_categorie_creee', 'categories', 51, 'Smartphones & Téléphones', '2026-04-28 08:17:18', '195.23.211.117'),
(371, 1, 'sous_categorie_creee', 'categories', 52, 'Ordinateurs & Laptops', '2026-04-28 08:17:34', '195.23.211.117'),
(372, 1, 'sous_categorie_creee', 'categories', 53, 'Tablettes', '2026-04-28 08:17:48', '195.23.211.117'),
(373, 1, 'sous_categorie_creee', 'categories', 54, 'TV, Audio & Vidéo', '2026-04-28 08:18:00', '195.23.211.117'),
(374, 1, 'sous_categorie_creee', 'categories', 55, 'Accessoires électroniques', '2026-04-28 08:18:14', '195.23.211.117'),
(375, 1, 'sous_categorie_creee', 'categories', 56, 'Gaming & Consoles', '2026-04-28 08:18:30', '195.23.211.117'),
(376, 1, 'sous_categorie_creee', 'categories', 57, 'Appareils photo & Caméras', '2026-04-28 08:18:41', '195.23.211.117'),
(377, 1, 'sous_categorie_creee', 'categories', 58, 'Objets connectés (montres, smart home…)', '2026-04-28 08:18:56', '195.23.211.117'),
(378, 1, 'sous_categorie_creee', 'categories', 59, 'Réseaux & Internet (routeurs, switch…)', '2026-04-28 08:19:07', '195.23.211.117'),
(379, 1, 'sous_categorie_creee', 'categories', 60, 'Stockage (disques durs, clés USB…)', '2026-04-28 08:19:18', '195.23.211.117'),
(380, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-04-28 08:19:57', '195.23.211.117'),
(381, 1, 'sous_categorie_creee', 'categories', 61, 'Meubles', '2026-04-28 08:21:26', '195.23.211.117'),
(382, 1, 'sous_categorie_creee', 'categories', 62, 'Décoration', '2026-04-28 08:21:45', '195.23.211.117'),
(383, 1, 'sous_categorie_creee', 'categories', 63, 'Cuisine & Ustensiles', '2026-04-28 08:21:59', '195.23.211.117'),
(384, 1, 'sous_categorie_creee', 'categories', 64, 'Électroménager', '2026-04-28 08:22:12', '195.23.211.117'),
(385, 1, 'sous_categorie_creee', 'categories', 65, 'Literie & Linge de maison', '2026-04-28 08:22:31', '195.23.211.117'),
(386, 1, 'sous_categorie_creee', 'categories', 66, 'Salle de bain', '2026-04-28 08:23:03', '195.23.211.117'),
(387, 1, 'sous_categorie_creee', 'categories', 67, 'Éclairage', '2026-04-28 08:24:27', '195.23.211.117'),
(388, 1, 'sous_categorie_creee', 'categories', 68, 'Rangement & Organisation', '2026-04-28 08:24:38', '195.23.211.117'),
(389, 1, 'sous_categorie_creee', 'categories', 69, 'Jardin & Extérieur', '2026-04-28 08:24:51', '195.23.211.117'),
(390, 1, 'sous_categorie_creee', 'categories', 70, 'Sécurité domestique', '2026-04-28 08:25:02', '195.23.211.117'),
(391, 1, 'sous_categorie_creee', 'categories', 71, 'Outillage manuel', '2026-04-28 10:50:51', '195.23.211.117'),
(392, 1, 'sous_categorie_creee', 'categories', 72, 'Outillage électrique', '2026-04-28 10:51:10', '195.23.211.117'),
(393, 1, 'sous_categorie_creee', 'categories', 73, 'Matériaux de construction', '2026-04-28 10:51:22', '195.23.211.117'),
(394, 1, 'sous_categorie_creee', 'categories', 74, 'Électricité', '2026-04-28 10:51:34', '195.23.211.117'),
(395, 1, 'sous_categorie_creee', 'categories', 75, 'Plomberie', '2026-04-28 10:51:43', '195.23.211.117'),
(396, 1, 'sous_categorie_creee', 'categories', 76, 'Peinture & Revêtements', '2026-04-28 10:51:53', '195.23.211.117'),
(397, 1, 'sous_categorie_creee', 'categories', 77, 'Quincaillerie', '2026-04-28 10:52:04', '195.23.211.117'),
(398, 1, 'sous_categorie_creee', 'categories', 78, 'Jardinage', '2026-04-28 10:52:13', '195.23.211.117'),
(399, 1, 'sous_categorie_creee', 'categories', 79, 'Aménagement extérieur', '2026-04-28 10:52:24', '195.23.211.117'),
(400, 1, 'sous_categorie_creee', 'categories', 80, 'Équipements de protection (EPI)', '2026-04-28 10:52:37', '195.23.211.117'),
(401, 1, 'sous_categorie_creee', 'categories', 81, 'Jeux vidéo', '2026-04-28 10:53:54', '195.23.211.117'),
(402, 1, 'sous_categorie_modifiee', 'categories', 81, 'Jeux vidéo et Consoles', '2026-04-28 10:54:21', '195.23.211.117'),
(403, 1, 'sous_categorie_creee', 'categories', 82, 'Accessoires gaming', '2026-04-28 10:54:37', '195.23.211.117'),
(404, 1, 'sous_categorie_creee', 'categories', 83, 'Jeux de société', '2026-04-28 10:54:49', '195.23.211.117'),
(405, 1, 'sous_categorie_creee', 'categories', 84, 'Jouets & divertissement', '2026-04-28 10:55:02', '195.23.211.117'),
(406, 1, 'sous_categorie_creee', 'categories', 85, 'Loisirs créatifs (dessin, DIY…)', '2026-04-28 10:55:15', '195.23.211.117'),
(407, 1, 'sous_categorie_creee', 'categories', 86, 'Sports & activités', '2026-04-28 10:55:29', '195.23.211.117'),
(408, 1, 'sous_categorie_creee', 'categories', 87, 'Musique & instruments', '2026-04-28 10:55:38', '195.23.211.117'),
(409, 1, 'sous_categorie_creee', 'categories', 88, 'Films & séries', '2026-04-28 10:55:47', '195.23.211.117'),
(410, 1, 'sous_categorie_creee', 'categories', 89, 'Cartes cadeaux & contenus digitaux', '2026-04-28 10:55:59', '195.23.211.117'),
(411, 1, 'sous_categorie_creee', 'categories', 90, 'Voitures', '2026-04-28 10:56:22', '195.23.211.117'),
(412, 1, 'sous_categorie_creee', 'categories', 91, 'Motos & scooters', '2026-04-28 10:56:32', '195.23.211.117'),
(413, 1, 'sous_categorie_creee', 'categories', 92, 'Pièces détachées', '2026-04-28 10:56:42', '195.23.211.117'),
(414, 1, 'sous_categorie_creee', 'categories', 93, 'Accessoires auto', '2026-04-28 10:56:59', '195.23.211.117'),
(415, 1, 'sous_categorie_creee', 'categories', 94, 'Pneus & jantes', '2026-04-28 10:57:10', '195.23.211.117'),
(416, 1, 'sous_categorie_creee', 'categories', 95, 'Entretien & nettoyage', '2026-04-28 10:57:19', '195.23.211.117'),
(417, 1, 'sous_categorie_creee', 'categories', 96, 'Équipements moto', '2026-04-28 10:57:29', '195.23.211.117'),
(418, 1, 'sous_categorie_creee', 'categories', 97, 'Audio & multimédia auto', '2026-04-28 10:57:39', '195.23.211.117'),
(419, 1, 'sous_categorie_creee', 'categories', 98, 'GPS & navigation', '2026-04-28 10:57:49', '195.23.211.117'),
(420, 1, 'sous_categorie_creee', 'categories', 99, 'Outillage automobile', '2026-04-28 10:58:01', '195.23.211.117'),
(421, 1, 'sous_categorie_creee', 'categories', 100, 'Soins du visage', '2026-04-28 10:58:38', '195.23.211.117'),
(422, 1, 'sous_categorie_creee', 'categories', 101, 'Soins du corps', '2026-04-28 10:58:48', '195.23.211.117'),
(423, 1, 'sous_categorie_creee', 'categories', 102, 'Cheveux & coiffure', '2026-04-28 10:58:57', '195.23.211.117'),
(424, 1, 'sous_categorie_creee', 'categories', 103, 'Maquillage', '2026-04-28 10:59:11', '195.23.211.117'),
(425, 1, 'sous_categorie_creee', 'categories', 105, 'Parfums', '2026-04-28 10:59:52', '195.23.211.117'),
(426, 1, 'sous_categorie_creee', 'categories', 106, 'Hygiène personnelle', '2026-04-28 11:00:03', '195.23.211.117'),
(427, 1, 'sous_categorie_creee', 'categories', 107, 'Santé & bien-être', '2026-04-28 11:00:14', '195.23.211.117'),
(428, 1, 'sous_categorie_creee', 'categories', 108, 'Compléments alimentaires', '2026-04-28 11:00:23', '195.23.211.117'),
(429, 1, 'sous_categorie_creee', 'categories', 109, 'Produits naturels & bio', '2026-04-28 11:00:33', '195.23.211.117'),
(430, 1, 'sous_categorie_creee', 'categories', 110, 'Matériel médical', '2026-04-28 11:00:43', '195.23.211.117'),
(431, 1, 'sous_categorie_creee', 'categories', 111, 'Chiens', '2026-04-28 11:01:07', '195.23.211.117'),
(432, 1, 'sous_categorie_creee', 'categories', 112, 'Chats', '2026-04-28 11:01:16', '195.23.211.117'),
(433, 1, 'sous_categorie_creee', 'categories', 113, 'Oiseaux', '2026-04-28 11:01:30', '195.23.211.117'),
(434, 1, 'sous_categorie_creee', 'categories', 114, 'Poissons & aquariums', '2026-04-28 11:01:42', '195.23.211.117'),
(435, 1, 'sous_categorie_creee', 'categories', 115, 'Rongeurs', '2026-04-28 11:01:53', '195.23.211.117'),
(436, 1, 'sous_categorie_creee', 'categories', 116, 'Nourriture pour animaux', '2026-04-28 11:02:03', '195.23.211.117'),
(437, 1, 'sous_categorie_creee', 'categories', 117, 'Accessoires (laisses, cages…)', '2026-04-28 11:02:15', '195.23.211.117'),
(438, 1, 'sous_categorie_creee', 'categories', 118, 'Jouets pour animaux', '2026-04-28 11:02:26', '195.23.211.117'),
(439, 1, 'sous_categorie_creee', 'categories', 119, 'Hygiène & soins', '2026-04-28 11:02:39', '195.23.211.117'),
(440, 1, 'sous_categorie_creee', 'categories', 120, 'Transport & habitat', '2026-04-28 11:02:49', '195.23.211.117'),
(441, 1, 'sous_categorie_creee', 'categories', 121, 'Livres', '2026-04-28 11:03:35', '195.23.211.117'),
(442, 1, 'sous_categorie_creee', 'categories', 122, 'E-books', '2026-04-28 11:03:46', '195.23.211.117'),
(443, 1, 'sous_categorie_creee', 'categories', 123, 'Magazines', '2026-04-28 11:03:58', '195.23.211.117'),
(444, 1, 'sous_categorie_creee', 'categories', 124, 'Musique', '2026-04-28 11:04:08', '195.23.211.117'),
(445, 1, 'sous_categorie_creee', 'categories', 125, 'Films & vidéos', '2026-04-28 11:04:21', '195.23.211.117'),
(446, 1, 'sous_categorie_creee', 'categories', 126, 'Formation en ligne', '2026-04-28 11:04:30', '195.23.211.117'),
(447, 1, 'sous_categorie_creee', 'categories', 127, 'Cours & tutoriels', '2026-04-28 11:04:40', '195.23.211.117'),
(448, 1, 'sous_categorie_creee', 'categories', 128, 'Matériel éducatif', '2026-04-28 11:04:52', '195.23.211.117'),
(449, 1, 'sous_categorie_creee', 'categories', 129, 'Logiciels éducatifs', '2026-04-28 11:05:10', '195.23.211.117'),
(450, 1, 'sous_categorie_creee', 'categories', 130, 'Papeterie', '2026-04-28 11:05:19', '195.23.211.117'),
(451, 1, 'sous_categorie_creee', 'categories', 131, 'Boissons sans alcool', '2026-04-28 11:06:01', '195.23.211.117'),
(452, 1, 'sous_categorie_creee', 'categories', 132, 'Eau', '2026-04-28 11:06:10', '195.23.211.117'),
(453, 1, 'sous_categorie_creee', 'categories', 133, 'Jus & smoothies', '2026-04-28 11:06:19', '195.23.211.117'),
(454, 1, 'sous_categorie_creee', 'categories', 134, 'Boissons gazeuses', '2026-04-28 11:06:29', '195.23.211.117'),
(455, 1, 'sous_categorie_creee', 'categories', 135, 'Café', '2026-04-28 11:06:38', '195.23.211.117'),
(456, 1, 'sous_categorie_creee', 'categories', 136, 'Thé & infusions', '2026-04-28 11:06:47', '195.23.211.117'),
(457, 1, 'sous_categorie_creee', 'categories', 137, 'Boissons énergétiques', '2026-04-28 11:06:57', '195.23.211.117'),
(458, 1, 'sous_categorie_creee', 'categories', 138, 'Boissons bio & naturelles', '2026-04-28 11:07:07', '195.23.211.117'),
(459, 1, 'sous_categorie_creee', 'categories', 139, 'Boissons alcoolisées', '2026-04-28 11:07:23', '195.23.211.117'),
(460, 1, 'hero_affiche_ajoutée', 'hero_affiche', 10, 'hero_b5c1e3c4c0831dd73c92efa1.png', '2026-04-28 11:14:37', '195.23.211.117'),
(461, 1, 'hero_affiche_ajoutée', 'hero_affiche', 11, 'hero_d8a42df391fe96b2e2f326db.png', '2026-04-28 11:15:00', '195.23.211.117'),
(462, 1, 'hero_affiche_ajoutée', 'hero_affiche', 12, 'hero_792bab4f4bedb4fa73dd1ce3.png', '2026-04-28 11:15:23', '195.23.211.117'),
(463, 1, 'hero_affiche_ajoutée', 'hero_affiche', 13, 'hero_740bd6efab7336907307071e.png', '2026-04-28 11:15:57', '195.23.211.117'),
(464, 1, 'hero_affiche_ajoutée', 'hero_affiche', 14, 'hero_af2f31e4fe9652f4423fe8c7.png', '2026-04-28 11:16:32', '195.23.211.117'),
(465, 1, 'hero_affiche_ajoutée', 'hero_affiche', 15, 'hero_7c36f36078ee2e63de56c024.png', '2026-04-28 11:17:20', '195.23.211.117'),
(466, 1, 'genre_modifie', 'genres', 135, 'FILLE', '2026-04-28 11:23:00', '195.23.211.117'),
(467, 1, 'genre_modifie', 'genres', 136, 'GARÇON', '2026-04-28 11:23:08', '195.23.211.117'),
(468, 1, 'genre_modifie', 'genres', 130, 'HOMME', '2026-04-28 11:23:15', '195.23.211.117'),
(469, 1, 'genre_modifie', 'genres', 131, 'FEMME', '2026-04-28 11:23:20', '195.23.211.117'),
(470, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-04-28 11:26:56', '195.23.211.117');
INSERT INTO `super_admin_logs` (`id`, `super_admin_id`, `action`, `cible_type`, `cible_id`, `details`, `date_action`, `ip`) VALUES
(471, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-13 06:20:32', '195.23.211.117'),
(472, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-13 06:21:17', '195.23.211.117'),
(473, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-05-13 06:21:30', '195.23.211.117'),
(474, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-05-13 06:21:42', '195.23.211.117'),
(475, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-13 06:21:58', '195.23.211.117'),
(476, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-13 06:22:12', '195.23.211.117'),
(477, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-13 06:22:37', '195.23.211.117'),
(478, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-13 06:22:50', '195.23.211.117'),
(479, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-13 06:23:04', '195.23.211.117'),
(480, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-13 06:23:16', '195.23.211.117'),
(481, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-13 06:23:29', '195.23.211.117'),
(482, 1, 'categorie_generale_modifiee', 'categories_generales', 78, 'Livres, Médias & Formation', '2026-05-13 06:23:46', '195.23.211.117'),
(483, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-13 06:23:59', '195.23.211.117'),
(484, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-13 06:24:15', '195.23.211.117'),
(485, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-13 06:24:29', '195.23.211.117'),
(486, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Vêtements et accessoires', '2026-05-13 06:24:48', '195.23.211.117'),
(487, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-05-13 06:25:09', '195.23.211.117'),
(488, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-05-13 06:25:23', '195.23.211.117'),
(489, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-05-13 06:25:38', '195.23.211.117'),
(490, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-05-13 06:25:53', '195.23.211.117'),
(491, 1, 'categorie_generale_modifiee', 'categories_generales', 79, 'Services', '2026-05-13 06:26:06', '195.23.211.117'),
(492, 1, 'categorie_generale_modifiee', 'categories_generales', 77, 'Animaux', '2026-05-13 06:26:21', '195.23.211.117'),
(493, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-13 06:36:38', '195.23.211.117'),
(494, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Vêtements et accessoires', '2026-05-13 06:36:55', '195.23.211.117'),
(495, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-13 06:37:09', '195.23.211.117'),
(496, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-05-13 06:37:22', '195.23.211.117'),
(497, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-05-13 06:40:58', '195.23.211.117'),
(498, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-13 06:41:07', '195.23.211.117'),
(499, 1, 'categorie_generale_modifiee', 'categories_generales', 78, 'Livres, Médias & Formation', '2026-05-13 06:41:15', '195.23.211.117'),
(500, 1, 'client_désactivé', 'user', 7, '', '2026-05-13 06:42:42', '195.23.211.117'),
(501, 1, 'client_désactivé', 'user', 3, '', '2026-05-13 06:43:25', '195.23.211.117'),
(502, 1, 'client_activé', 'user', 7, '', '2026-05-13 09:18:34', '195.23.211.117'),
(503, 1, 'client_activé', 'user', 3, '', '2026-05-13 09:18:36', '195.23.211.117'),
(504, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-13 09:23:19', '195.23.211.117'),
(505, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-13 09:23:37', '195.23.211.117'),
(506, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-13 09:26:11', '195.23.211.117'),
(507, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-13 09:26:20', '195.23.211.117'),
(508, 1, 'hero_affiche_supprimée', 'hero_affiche', 10, 'hero_b5c1e3c4c0831dd73c92efa1.png', '2026-05-14 17:08:41', '2.83.182.140'),
(509, 1, 'hero_affiche_supprimée', 'hero_affiche', 11, 'hero_d8a42df391fe96b2e2f326db.png', '2026-05-14 17:08:46', '2.83.182.140'),
(510, 1, 'hero_affiche_supprimée', 'hero_affiche', 12, 'hero_792bab4f4bedb4fa73dd1ce3.png', '2026-05-14 17:08:51', '2.83.182.140'),
(511, 1, 'hero_affiche_supprimée', 'hero_affiche', 13, 'hero_740bd6efab7336907307071e.png', '2026-05-14 17:08:55', '2.83.182.140'),
(512, 1, 'hero_affiche_supprimée', 'hero_affiche', 14, 'hero_af2f31e4fe9652f4423fe8c7.png', '2026-05-14 17:09:00', '2.83.182.140'),
(513, 1, 'hero_affiche_supprimée', 'hero_affiche', 15, 'hero_7c36f36078ee2e63de56c024.png', '2026-05-14 17:09:05', '2.83.182.140'),
(514, 1, 'hero_affiche_ajoutée', 'hero_affiche', 16, 'hero_6efb70dc8a3036fd398f9599.png', '2026-05-14 17:09:48', '2.83.182.140'),
(515, 1, 'hero_affiche_ajoutée', 'hero_affiche', 17, 'hero_ae7667b038848f4e49297792.png', '2026-05-14 17:10:30', '2.83.182.140'),
(516, 1, 'hero_affiche_ajoutée', 'hero_affiche', 18, 'hero_7ac330f02cf1723d6d2c5db9.png', '2026-05-14 17:11:10', '2.83.182.140'),
(517, 1, 'hero_affiche_ajoutée', 'hero_affiche', 19, 'hero_329871913c64ca56533e95f2.png', '2026-05-14 17:11:50', '2.83.182.140'),
(518, 1, 'hero_affiche_ajoutée', 'hero_affiche', 20, 'hero_6ffb8844c7756e59d1b7860b.png', '2026-05-14 17:14:29', '2.83.182.140'),
(519, 1, 'hero_affiche_ajoutée', 'hero_affiche', 21, 'hero_c7f0e5b94a9077590e53d4e9.png', '2026-05-14 17:15:07', '2.83.182.140'),
(520, 1, 'hero_affiche_supprimée', 'hero_affiche', 17, 'hero_ae7667b038848f4e49297792.png', '2026-05-14 17:23:13', '2.83.182.140'),
(521, 1, 'hero_affiche_ajoutée', 'hero_affiche', 22, 'hero_3f0823798892297f5e713da6.png', '2026-05-14 17:23:38', '2.83.182.140'),
(522, 1, 'sous_categorie_modifiee', 'categories', 51, 'Tablette & Téléphones', '2026-05-23 13:49:57', '2.83.182.140'),
(523, 1, 'sous_categorie_modifiee', 'categories', 51, 'Tablette & Téléphones', '2026-05-23 13:50:26', '2.83.182.140'),
(524, 1, 'sous_categorie_modifiee', 'categories', 60, 'Stockage (disques durs, clés USB…)', '2026-05-23 13:50:50', '2.83.182.140'),
(525, 1, 'sous_categorie_supprimee', 'categories', 53, 'Tablettes', '2026-05-23 13:51:26', '2.83.182.140'),
(526, 1, 'sous_categorie_modifiee', 'categories', 55, 'Accessoires électroniques', '2026-05-23 13:56:46', '2.83.182.140'),
(527, 1, 'sous_categorie_modifiee', 'categories', 56, 'Gaming & Consoles', '2026-05-23 13:57:14', '2.83.182.140'),
(528, 1, 'sous_categorie_modifiee', 'categories', 59, 'Réseaux & Internet (routeurs, switch…)', '2026-05-23 14:01:03', '2.83.182.140'),
(529, 1, 'sous_categorie_modifiee', 'categories', 58, 'Objets connectés (montres, smart home…)', '2026-05-23 14:01:37', '2.83.182.140'),
(530, 1, 'sous_categorie_modifiee', 'categories', 54, 'TV, Audio & Vidéo', '2026-05-23 14:05:47', '2.83.182.140'),
(531, 1, 'sous_categorie_modifiee', 'categories', 57, 'Appareils photo & Caméras', '2026-05-23 14:06:04', '2.83.182.140'),
(532, 1, 'sous_categorie_modifiee', 'categories', 52, 'Ordinateurs & Laptops', '2026-05-23 14:06:25', '2.83.182.140'),
(533, 1, 'sous_categorie_modifiee', 'categories', 102, 'Cheveux & coiffure', '2026-05-23 14:10:07', '2.83.182.140'),
(534, 1, 'sous_categorie_modifiee', 'categories', 108, 'Compléments alimentaires', '2026-05-23 14:10:30', '2.83.182.140'),
(535, 1, 'sous_categorie_modifiee', 'categories', 106, 'Hygiène personnelle', '2026-05-23 14:11:05', '2.83.182.140'),
(536, 1, 'sous_categorie_modifiee', 'categories', 103, 'Maquillage', '2026-05-23 14:11:26', '2.83.182.140'),
(537, 1, 'sous_categorie_modifiee', 'categories', 110, 'Matériel médical', '2026-05-23 14:11:43', '2.83.182.140'),
(538, 1, 'sous_categorie_modifiee', 'categories', 105, 'Parfums', '2026-05-23 14:12:05', '2.83.182.140'),
(539, 1, 'sous_categorie_modifiee', 'categories', 109, 'Produits naturels & bio', '2026-05-23 14:12:31', '2.83.182.140'),
(540, 1, 'sous_categorie_modifiee', 'categories', 107, 'Santé & bien-être', '2026-05-23 14:18:51', '2.83.182.140'),
(541, 1, 'sous_categorie_modifiee', 'categories', 101, 'Soins du corps', '2026-05-23 14:20:21', '2.83.182.140'),
(542, 1, 'sous_categorie_modifiee', 'categories', 100, 'Soins du visage', '2026-05-23 14:21:03', '2.83.182.140'),
(543, 1, 'sous_categorie_modifiee', 'categories', 43, 'Équipement scolaire', '2026-05-23 14:21:49', '2.83.182.140'),
(544, 1, 'sous_categorie_modifiee', 'categories', 42, 'Puériculture (bébé)', '2026-05-23 14:23:57', '2.83.182.140'),
(545, 1, 'sous_categorie_modifiee', 'categories', 43, 'Équipement scolaire', '2026-05-23 14:24:50', '2.83.182.140'),
(546, 1, 'sous_categorie_modifiee', 'categories', 40, 'Jouets', '2026-05-23 14:25:50', '2.83.182.140'),
(547, 1, 'sous_categorie_modifiee', 'categories', 42, 'Puériculture (bébé)', '2026-05-23 14:27:04', '2.83.182.140'),
(548, 1, 'sous_categorie_modifiee', 'categories', 40, 'Jouets', '2026-05-23 14:27:32', '2.83.182.140'),
(549, 1, 'sous_categorie_modifiee', 'categories', 39, 'Vêtements enfants', '2026-05-23 14:29:54', '2.83.182.140'),
(550, 1, 'sous_categorie_modifiee', 'categories', 97, 'Audio & multimédia auto', '2026-05-23 14:32:02', '2.83.182.140'),
(551, 1, 'sous_categorie_modifiee', 'categories', 93, 'Accessoires auto', '2026-05-23 14:32:24', '2.83.182.140'),
(552, 1, 'sous_categorie_modifiee', 'categories', 95, 'Entretien & nettoyage', '2026-05-23 14:33:05', '2.83.182.140'),
(553, 1, 'sous_categorie_modifiee', 'categories', 96, 'Équipements moto', '2026-05-23 14:34:11', '2.83.182.140'),
(554, 1, 'sous_categorie_modifiee', 'categories', 91, 'Motos & scooters', '2026-05-23 14:36:36', '2.83.182.140'),
(555, 1, 'sous_categorie_modifiee', 'categories', 93, 'Accessoires auto', '2026-05-23 14:37:37', '2.83.182.140'),
(556, 1, 'sous_categorie_modifiee', 'categories', 99, 'Outillage automobile', '2026-05-23 14:39:48', '2.83.182.140'),
(557, 1, 'sous_categorie_modifiee', 'categories', 98, 'GPS & navigation', '2026-05-23 14:40:19', '2.83.182.140'),
(558, 1, 'sous_categorie_modifiee', 'categories', 94, 'Pneus & jantes', '2026-05-23 14:40:54', '2.83.182.140'),
(559, 1, 'sous_categorie_modifiee', 'categories', 90, 'Voitures', '2026-05-23 14:42:18', '2.83.182.140'),
(560, 1, 'sous_categorie_modifiee', 'categories', 92, 'Pièces détachées', '2026-05-23 14:43:08', '2.83.182.140'),
(561, 1, 'sous_categorie_modifiee', 'categories', 47, 'Bijoux', '2026-05-23 14:45:43', '2.83.182.140'),
(562, 1, 'sous_categorie_modifiee', 'categories', 44, 'Chaussures', '2026-05-23 14:47:02', '2.83.182.140'),
(563, 1, 'sous_categorie_modifiee', 'categories', 45, 'Sacs & Bagages', '2026-05-23 14:47:55', '2.83.182.140'),
(564, 1, 'sous_categorie_modifiee', 'categories', 49, 'Sous-vêtements & Lingerie', '2026-05-23 14:48:50', '2.83.182.140'),
(565, 1, 'sous_categorie_modifiee', 'categories', 50, 'Tenues traditionnelles', '2026-05-23 14:50:04', '2.83.182.140'),
(566, 1, 'sous_categorie_modifiee', 'categories', 48, 'Vêtements de sport', '2026-05-23 14:51:09', '2.83.182.140'),
(567, 1, 'sous_categorie_modifiee', 'categories', 63, 'Cuisine & Ustensiles', '2026-05-23 14:58:30', '2.83.182.140'),
(568, 1, 'sous_categorie_modifiee', 'categories', 62, 'Décoration', '2026-05-23 14:58:57', '2.83.182.140'),
(569, 1, 'sous_categorie_modifiee', 'categories', 67, 'Éclairage', '2026-05-23 15:00:12', '2.83.182.140'),
(570, 1, 'sous_categorie_modifiee', 'categories', 64, 'Électroménager', '2026-05-23 15:00:49', '2.83.182.140'),
(571, 1, 'sous_categorie_modifiee', 'categories', 69, 'Jardin & Extérieur', '2026-05-23 15:01:15', '2.83.182.140'),
(572, 1, 'sous_categorie_modifiee', 'categories', 65, 'Literie & Linge de maison', '2026-05-23 15:01:40', '2.83.182.140'),
(573, 1, 'sous_categorie_modifiee', 'categories', 61, 'Meubles', '2026-05-23 15:02:02', '2.83.182.140'),
(574, 1, 'sous_categorie_modifiee', 'categories', 68, 'Rangement & Organisation', '2026-05-23 15:02:29', '2.83.182.140'),
(575, 1, 'sous_categorie_modifiee', 'categories', 66, 'Salle de bain', '2026-05-23 15:02:48', '2.83.182.140'),
(576, 1, 'sous_categorie_modifiee', 'categories', 70, 'Sécurité domestique', '2026-05-23 15:04:07', '2.83.182.140'),
(577, 1, 'sous_categorie_modifiee', 'categories', 139, 'Boissons alcoolisées', '2026-05-23 15:05:03', '2.83.182.140'),
(578, 1, 'sous_categorie_modifiee', 'categories', 138, 'Boissons bio & naturelles', '2026-05-23 15:05:25', '2.83.182.140'),
(579, 1, 'sous_categorie_modifiee', 'categories', 137, 'Boissons énergétiques', '2026-05-23 15:06:15', '2.83.182.140'),
(580, 1, 'sous_categorie_modifiee', 'categories', 137, 'Boissons gazeuses et énergétiques', '2026-05-23 15:06:49', '2.83.182.140'),
(581, 1, 'sous_categorie_supprimee', 'categories', 134, 'Boissons gazeuses', '2026-05-23 15:07:02', '2.83.182.140'),
(582, 1, 'sous_categorie_modifiee', 'categories', 131, 'Boissons sans alcool', '2026-05-23 15:07:28', '2.83.182.140'),
(583, 1, 'sous_categorie_modifiee', 'categories', 135, 'Café', '2026-05-23 15:07:59', '2.83.182.140'),
(584, 1, 'sous_categorie_modifiee', 'categories', 132, 'Eau', '2026-05-23 15:08:23', '2.83.182.140'),
(585, 1, 'sous_categorie_modifiee', 'categories', 133, 'Jus & smoothies', '2026-05-23 15:08:55', '2.83.182.140'),
(586, 1, 'sous_categorie_modifiee', 'categories', 136, 'Thé & infusions', '2026-05-23 15:09:16', '2.83.182.140'),
(587, 1, 'sous_categorie_modifiee', 'categories', 127, 'Cours & tutoriels', '2026-05-23 15:12:09', '2.83.182.140'),
(588, 1, 'sous_categorie_modifiee', 'categories', 122, 'E-books', '2026-05-23 15:12:28', '2.83.182.140'),
(589, 1, 'sous_categorie_modifiee', 'categories', 125, 'Films & vidéos', '2026-05-23 15:12:56', '2.83.182.140'),
(590, 1, 'sous_categorie_modifiee', 'categories', 126, 'Formation en ligne', '2026-05-23 15:13:15', '2.83.182.140'),
(591, 1, 'sous_categorie_modifiee', 'categories', 121, 'Livres', '2026-05-23 15:13:43', '2.83.182.140'),
(592, 1, 'sous_categorie_modifiee', 'categories', 129, 'Logiciels éducatifs', '2026-05-23 15:14:10', '2.83.182.140'),
(593, 1, 'sous_categorie_modifiee', 'categories', 123, 'Magazines', '2026-05-23 15:14:29', '2.83.182.140'),
(594, 1, 'sous_categorie_modifiee', 'categories', 128, 'Matériel éducatif', '2026-05-23 15:14:53', '2.83.182.140'),
(595, 1, 'sous_categorie_modifiee', 'categories', 124, 'Musique', '2026-05-23 15:15:16', '2.83.182.140'),
(596, 1, 'sous_categorie_modifiee', 'categories', 130, 'Papeterie', '2026-05-23 15:15:37', '2.83.182.140'),
(597, 1, 'sous_categorie_modifiee', 'categories', 82, 'Accessoires gaming', '2026-05-23 15:16:29', '2.83.182.140'),
(598, 1, 'sous_categorie_modifiee', 'categories', 89, 'Cartes cadeaux & contenus digitaux', '2026-05-23 15:17:24', '2.83.182.140'),
(599, 1, 'sous_categorie_modifiee', 'categories', 88, 'Films & séries', '2026-05-23 15:17:33', '2.83.182.140'),
(600, 1, 'sous_categorie_modifiee', 'categories', 88, 'Films & séries', '2026-05-23 15:18:07', '2.83.182.140'),
(601, 1, 'sous_categorie_modifiee', 'categories', 83, 'Jeux de société', '2026-05-23 15:18:30', '2.83.182.140'),
(602, 1, 'sous_categorie_modifiee', 'categories', 81, 'Jeux vidéo et Consoles', '2026-05-23 15:18:51', '2.83.182.140'),
(603, 1, 'sous_categorie_modifiee', 'categories', 84, 'Jouets & divertissement', '2026-05-23 15:19:18', '2.83.182.140'),
(604, 1, 'sous_categorie_modifiee', 'categories', 85, 'Loisirs créatifs (dessin, DIY…)', '2026-05-23 15:19:42', '2.83.182.140'),
(605, 1, 'sous_categorie_modifiee', 'categories', 87, 'Musique & instruments', '2026-05-23 15:20:17', '2.83.182.140'),
(606, 1, 'sous_categorie_modifiee', 'categories', 86, 'Sports & activités', '2026-05-23 15:20:41', '2.83.182.140'),
(607, 1, 'sous_categorie_modifiee', 'categories', 79, 'Aménagement extérieur', '2026-05-23 15:23:16', '2.83.182.140'),
(608, 1, 'sous_categorie_modifiee', 'categories', 74, 'Électricité', '2026-05-23 15:23:42', '2.83.182.140'),
(609, 1, 'sous_categorie_modifiee', 'categories', 80, 'Équipements de protection (EPI)', '2026-05-23 15:24:03', '2.83.182.140'),
(610, 1, 'sous_categorie_modifiee', 'categories', 78, 'Jardinage', '2026-05-23 15:24:24', '2.83.182.140'),
(611, 1, 'sous_categorie_modifiee', 'categories', 73, 'Matériaux de construction', '2026-05-23 15:24:45', '2.83.182.140'),
(612, 1, 'sous_categorie_modifiee', 'categories', 72, 'Outillage électrique', '2026-05-23 15:25:08', '2.83.182.140'),
(613, 1, 'sous_categorie_modifiee', 'categories', 71, 'Outillage manuel', '2026-05-23 15:25:34', '2.83.182.140'),
(614, 1, 'sous_categorie_modifiee', 'categories', 76, 'Peinture & Revêtements', '2026-05-23 15:25:58', '2.83.182.140'),
(615, 1, 'sous_categorie_modifiee', 'categories', 75, 'Plomberie', '2026-05-23 15:26:24', '2.83.182.140'),
(616, 1, 'sous_categorie_modifiee', 'categories', 77, 'Quincaillerie', '2026-05-23 15:27:07', '2.83.182.140'),
(617, 1, 'sous_categorie_modifiee', 'categories', 117, 'Accessoires (laisses, cages…)', '2026-05-23 15:28:31', '2.83.182.140'),
(618, 1, 'sous_categorie_modifiee', 'categories', 112, 'Chats', '2026-05-23 15:29:08', '2.83.182.140'),
(619, 1, 'sous_categorie_modifiee', 'categories', 111, 'Chiens', '2026-05-23 15:29:36', '2.83.182.140'),
(620, 1, 'sous_categorie_modifiee', 'categories', 119, 'Hygiène & soins', '2026-05-23 15:29:58', '2.83.182.140'),
(621, 1, 'sous_categorie_modifiee', 'categories', 118, 'Jouets pour animaux', '2026-05-23 15:30:24', '2.83.182.140'),
(622, 1, 'sous_categorie_modifiee', 'categories', 116, 'Nourriture pour animaux', '2026-05-23 15:30:49', '2.83.182.140'),
(623, 1, 'sous_categorie_modifiee', 'categories', 113, 'Oiseaux', '2026-05-23 15:31:13', '2.83.182.140'),
(624, 1, 'sous_categorie_modifiee', 'categories', 114, 'Poissons & aquariums', '2026-05-23 15:31:39', '2.83.182.140'),
(625, 1, 'sous_categorie_modifiee', 'categories', 115, 'Rongeurs', '2026-05-23 15:32:10', '2.83.182.140'),
(626, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-23 15:32:10', '196.207.231.244'),
(627, 1, 'sous_categorie_modifiee', 'categories', 120, 'Transport & habitat', '2026-05-23 15:32:35', '2.83.182.140'),
(628, 1, 'sous_categorie_creee', 'categories', 140, 'Pantanon et Jeans', '2026-05-23 15:40:18', '2.83.182.140'),
(629, 1, 'sous_categorie_creee', 'categories', 141, 'Jupes', '2026-05-23 15:40:55', '2.83.182.140'),
(630, 1, 'sous_categorie_creee', 'categories', 142, 'Chemises', '2026-05-23 15:41:25', '2.83.182.140'),
(631, 1, 'sous_categorie_creee', 'categories', 143, 'Shorts', '2026-05-23 15:42:16', '2.83.182.140'),
(632, 1, 'sous_categorie_creee', 'categories', 144, 'Tshirts', '2026-05-23 15:43:49', '2.83.182.140'),
(633, 1, 'sous_categorie_creee', 'categories', 145, 'Chaussettes', '2026-05-23 15:44:58', '2.83.182.140'),
(634, 1, 'sous_categorie_creee', 'categories', 146, 'Pull', '2026-05-23 15:45:34', '2.83.182.140'),
(635, 1, 'sous_categorie_modifiee', 'categories', 46, 'Accessoires (montres, ceintures, lunettes…)', '2026-05-23 15:50:13', '2.83.182.140'),
(636, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-23 15:55:49', '2.83.182.140'),
(637, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-23 15:56:07', '2.83.182.140'),
(638, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-23 16:01:00', '2.83.182.140'),
(639, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-23 16:01:17', '2.83.182.140'),
(640, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-23 16:01:44', '2.83.182.140'),
(641, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-23 16:06:27', '2.83.182.140'),
(642, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-23 16:06:45', '2.83.182.140'),
(643, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-23 16:07:11', '2.83.182.140'),
(644, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Vêtements et accessoires', '2026-05-23 16:08:04', '2.83.182.140'),
(645, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-05-23 16:08:21', '2.83.182.140'),
(646, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-05-23 16:08:41', '2.83.182.140'),
(647, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-05-23 16:09:14', '2.83.182.140'),
(648, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-05-23 16:09:34', '2.83.182.140'),
(649, 1, 'categorie_generale_modifiee', 'categories_generales', 78, 'Livres, Médias & Formation', '2026-05-23 16:10:36', '2.83.182.140'),
(650, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-23 16:10:56', '2.83.182.140'),
(651, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-23 16:11:14', '2.83.182.140'),
(652, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-23 16:11:40', '2.83.182.140'),
(653, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-23 16:12:20', '2.83.182.140'),
(654, 1, 'categorie_generale_modifiee', 'categories_generales', 77, 'Animaux', '2026-05-23 16:13:09', '2.83.182.140'),
(655, 1, 'categorie_generale_modifiee', 'categories_generales', 79, 'Services', '2026-05-23 16:16:42', '2.83.182.140'),
(656, 1, 'sous_categorie_modifiee', 'categories', 55, 'Accessoires électroniques', '2026-05-23 16:25:09', '2.83.182.140'),
(657, 1, 'sous_categorie_modifiee', 'categories', 57, 'Appareils photo & Caméras', '2026-05-23 16:25:32', '2.83.182.140'),
(658, 1, 'sous_categorie_modifiee', 'categories', 56, 'Gaming & Consoles', '2026-05-23 16:26:06', '2.83.182.140'),
(659, 1, 'sous_categorie_modifiee', 'categories', 58, 'Objets connectés (montres, smart home…)', '2026-05-23 16:27:01', '2.83.182.140'),
(660, 1, 'sous_categorie_modifiee', 'categories', 52, 'Ordinateurs & Laptops', '2026-05-23 16:27:24', '2.83.182.140'),
(661, 1, 'sous_categorie_modifiee', 'categories', 59, 'Réseaux & Internet (routeurs, switch…)', '2026-05-23 16:28:13', '2.83.182.140'),
(662, 1, 'sous_categorie_modifiee', 'categories', 59, 'Réseaux & Internet (routeurs, switch…)', '2026-05-23 16:29:05', '2.83.182.140'),
(663, 1, 'sous_categorie_modifiee', 'categories', 60, 'Stockage (disques durs, clés USB…)', '2026-05-23 16:29:49', '2.83.182.140'),
(664, 1, 'sous_categorie_modifiee', 'categories', 51, 'Tablette & Téléphones', '2026-05-23 16:30:26', '2.83.182.140'),
(665, 1, 'sous_categorie_modifiee', 'categories', 54, 'TV, Audio & Vidéo', '2026-05-23 16:30:57', '2.83.182.140'),
(666, 1, 'sous_categorie_modifiee', 'categories', 102, 'Cheveux & coiffure', '2026-05-23 16:31:22', '2.83.182.140'),
(667, 1, 'sous_categorie_modifiee', 'categories', 108, 'Compléments alimentaires', '2026-05-23 16:31:57', '2.83.182.140'),
(668, 1, 'sous_categorie_modifiee', 'categories', 106, 'Hygiène personnelle', '2026-05-23 16:32:53', '2.83.182.140'),
(669, 1, 'sous_categorie_modifiee', 'categories', 103, 'Maquillage', '2026-05-23 16:33:27', '2.83.182.140'),
(670, 1, 'sous_categorie_modifiee', 'categories', 110, 'Matériel médical', '2026-05-23 16:34:05', '2.83.182.140'),
(671, 1, 'sous_categorie_modifiee', 'categories', 105, 'Parfums', '2026-05-23 16:35:13', '2.83.182.140'),
(672, 1, 'sous_categorie_modifiee', 'categories', 109, 'Produits naturels & bio', '2026-05-23 16:35:38', '2.83.182.140'),
(673, 1, 'sous_categorie_modifiee', 'categories', 107, 'Santé & bien-être', '2026-05-23 16:37:05', '2.83.182.140'),
(674, 1, 'sous_categorie_modifiee', 'categories', 101, 'Soins du corps', '2026-05-23 16:37:44', '2.83.182.140'),
(675, 1, 'sous_categorie_modifiee', 'categories', 100, 'Soins du visage', '2026-05-23 16:38:10', '2.83.182.140'),
(676, 1, 'sous_categorie_modifiee', 'categories', 43, 'Équipement scolaire', '2026-05-23 16:38:41', '2.83.182.140'),
(677, 1, 'sous_categorie_modifiee', 'categories', 40, 'Jouets', '2026-05-23 16:39:05', '2.83.182.140'),
(678, 1, 'sous_categorie_modifiee', 'categories', 39, 'Vêtements enfants', '2026-05-23 16:41:27', '2.83.182.140'),
(679, 1, 'sous_categorie_modifiee', 'categories', 93, 'Accessoires auto', '2026-05-23 16:42:09', '2.83.182.140'),
(680, 1, 'sous_categorie_modifiee', 'categories', 97, 'Audio & multimédia auto', '2026-05-23 16:42:37', '2.83.182.140'),
(681, 1, 'sous_categorie_modifiee', 'categories', 95, 'Entretien & nettoyage', '2026-05-23 16:43:09', '2.83.182.140'),
(682, 1, 'sous_categorie_modifiee', 'categories', 96, 'Équipements moto', '2026-05-23 16:44:19', '2.83.182.140'),
(683, 1, 'sous_categorie_modifiee', 'categories', 98, 'GPS & navigation', '2026-05-23 16:44:32', '2.83.182.140'),
(684, 1, 'sous_categorie_modifiee', 'categories', 98, 'GPS & navigation', '2026-05-23 16:45:35', '2.83.182.140'),
(685, 1, 'sous_categorie_modifiee', 'categories', 91, 'Motos & scooters', '2026-05-23 16:46:10', '2.83.182.140'),
(686, 1, 'sous_categorie_modifiee', 'categories', 99, 'Outillage automobile', '2026-05-23 16:46:59', '2.83.182.140'),
(687, 1, 'sous_categorie_modifiee', 'categories', 94, 'Pneus & jantes', '2026-05-23 16:47:36', '2.83.182.140'),
(688, 1, 'sous_categorie_modifiee', 'categories', 92, 'Pièces détachées', '2026-05-23 16:48:05', '2.83.182.140'),
(689, 1, 'sous_categorie_modifiee', 'categories', 46, 'Accessoires (montres, ceintures, lunettes…)', '2026-05-23 16:48:45', '2.83.182.140'),
(690, 1, 'sous_categorie_modifiee', 'categories', 47, 'Bijoux', '2026-05-23 16:49:09', '2.83.182.140'),
(691, 1, 'sous_categorie_modifiee', 'categories', 145, 'Chaussettes', '2026-05-23 16:49:37', '2.83.182.140'),
(692, 1, 'sous_categorie_modifiee', 'categories', 44, 'Chaussures', '2026-05-23 16:50:13', '2.83.182.140'),
(693, 1, 'sous_categorie_modifiee', 'categories', 142, 'Chemises', '2026-05-23 16:50:41', '2.83.182.140'),
(694, 1, 'sous_categorie_modifiee', 'categories', 141, 'Jupes et shorts', '2026-05-23 16:51:39', '2.83.182.140'),
(695, 1, 'sous_categorie_modifiee', 'categories', 140, 'Pantanon et Jeans', '2026-05-23 16:52:23', '2.83.182.140'),
(696, 1, 'sous_categorie_modifiee', 'categories', 146, 'Pull', '2026-05-23 16:53:19', '2.83.182.140'),
(697, 1, 'sous_categorie_modifiee', 'categories', 146, 'Pull', '2026-05-23 16:54:58', '2.83.182.140'),
(698, 1, 'sous_categorie_modifiee', 'categories', 141, 'Jupes', '2026-05-23 16:55:46', '2.83.182.140'),
(699, 1, 'sous_categorie_modifiee', 'categories', 45, 'Sacs & Bagages', '2026-05-23 16:56:40', '2.83.182.140'),
(700, 1, 'sous_categorie_modifiee', 'categories', 143, 'Shorts', '2026-05-23 16:57:10', '2.83.182.140'),
(701, 1, 'sous_categorie_modifiee', 'categories', 49, 'Sous-vêtements & Lingerie', '2026-05-23 16:57:39', '2.83.182.140'),
(702, 1, 'sous_categorie_modifiee', 'categories', 50, 'Tenues traditionnelles', '2026-05-23 16:58:05', '2.83.182.140'),
(703, 1, 'sous_categorie_modifiee', 'categories', 144, 'Tshirts', '2026-05-23 16:58:28', '2.83.182.140'),
(704, 1, 'sous_categorie_modifiee', 'categories', 48, 'Vêtements de sport', '2026-05-23 16:59:03', '2.83.182.140'),
(705, 1, 'sous_categorie_modifiee', 'categories', 63, 'Cuisine & Ustensiles', '2026-05-23 16:59:38', '2.83.182.140'),
(706, 1, 'sous_categorie_modifiee', 'categories', 62, 'Décoration', '2026-05-23 17:00:02', '2.83.182.140'),
(707, 1, 'sous_categorie_modifiee', 'categories', 67, 'Éclairage', '2026-05-23 17:00:26', '2.83.182.140'),
(708, 1, 'sous_categorie_modifiee', 'categories', 64, 'Électroménager', '2026-05-23 17:00:48', '2.83.182.140'),
(709, 1, 'sous_categorie_modifiee', 'categories', 69, 'Jardin & Extérieur', '2026-05-23 17:01:55', '2.83.182.140'),
(710, 1, 'sous_categorie_modifiee', 'categories', 61, 'Meubles', '2026-05-23 17:02:40', '2.83.182.140'),
(711, 1, 'sous_categorie_modifiee', 'categories', 68, 'Rangement & Organisation', '2026-05-23 17:04:13', '2.83.182.140'),
(712, 1, 'sous_categorie_modifiee', 'categories', 66, 'Salle de bain', '2026-05-23 17:05:30', '2.83.182.140'),
(713, 1, 'sous_categorie_modifiee', 'categories', 139, 'Boissons alcoolisées', '2026-05-23 17:06:02', '2.83.182.140'),
(714, 1, 'sous_categorie_modifiee', 'categories', 138, 'Boissons bio & naturelles', '2026-05-23 17:06:22', '2.83.182.140'),
(715, 1, 'sous_categorie_modifiee', 'categories', 137, 'Boissons gazeuses et énergétiques', '2026-05-23 17:06:43', '2.83.182.140'),
(716, 1, 'sous_categorie_modifiee', 'categories', 131, 'Boissons sans alcool', '2026-05-23 17:07:21', '2.83.182.140'),
(717, 1, 'sous_categorie_modifiee', 'categories', 135, 'Café', '2026-05-23 17:08:02', '2.83.182.140'),
(718, 1, 'sous_categorie_modifiee', 'categories', 135, 'Café', '2026-05-23 17:08:26', '2.83.182.140'),
(719, 1, 'sous_categorie_modifiee', 'categories', 132, 'Eau', '2026-05-23 17:09:23', '2.83.182.140'),
(720, 1, 'sous_categorie_modifiee', 'categories', 133, 'Jus & smoothies', '2026-05-23 17:09:51', '2.83.182.140'),
(721, 1, 'sous_categorie_modifiee', 'categories', 136, 'Thé & infusions', '2026-05-23 17:10:32', '2.83.182.140'),
(722, 1, 'sous_categorie_modifiee', 'categories', 125, 'Films & vidéos', '2026-05-23 17:11:20', '2.83.182.140'),
(723, 1, 'sous_categorie_modifiee', 'categories', 126, 'Formation en ligne', '2026-05-23 17:11:52', '2.83.182.140'),
(724, 1, 'sous_categorie_modifiee', 'categories', 121, 'Livres', '2026-05-23 17:12:28', '2.83.182.140'),
(725, 1, 'sous_categorie_modifiee', 'categories', 129, 'Logiciels éducatifs', '2026-05-23 17:13:08', '2.83.182.140'),
(726, 1, 'sous_categorie_modifiee', 'categories', 123, 'Magazines', '2026-05-23 17:13:36', '2.83.182.140'),
(727, 1, 'sous_categorie_modifiee', 'categories', 122, 'E-books', '2026-05-23 17:15:41', '2.83.182.140'),
(728, 1, 'sous_categorie_modifiee', 'categories', 124, 'Musique', '2026-05-23 17:16:17', '2.83.182.140'),
(729, 1, 'sous_categorie_modifiee', 'categories', 82, 'Accessoires gaming', '2026-05-23 17:16:45', '2.83.182.140'),
(730, 1, 'sous_categorie_modifiee', 'categories', 89, 'Cartes cadeaux & contenus digitaux', '2026-05-23 17:17:15', '2.83.182.140'),
(731, 1, 'sous_categorie_modifiee', 'categories', 88, 'Films & séries', '2026-05-23 17:17:39', '2.83.182.140'),
(732, 1, 'sous_categorie_modifiee', 'categories', 83, 'Jeux de société', '2026-05-23 17:18:09', '2.83.182.140'),
(733, 1, 'sous_categorie_modifiee', 'categories', 81, 'Jeux vidéo et Consoles', '2026-05-23 17:18:53', '2.83.182.140'),
(734, 1, 'sous_categorie_modifiee', 'categories', 85, 'Loisirs créatifs (dessin, DIY…)', '2026-05-23 17:19:34', '2.83.182.140'),
(735, 1, 'sous_categorie_modifiee', 'categories', 87, 'Musique & instruments', '2026-05-23 17:20:07', '2.83.182.140'),
(736, 1, 'sous_categorie_modifiee', 'categories', 86, 'Sports & activités', '2026-05-23 17:20:45', '2.83.182.140'),
(737, 1, 'sous_categorie_modifiee', 'categories', 80, 'Équipements de protection (EPI)', '2026-05-23 17:21:12', '2.83.182.140'),
(738, 1, 'sous_categorie_modifiee', 'categories', 78, 'Jardinage', '2026-05-23 17:21:49', '2.83.182.140'),
(739, 1, 'sous_categorie_modifiee', 'categories', 73, 'Matériaux de construction', '2026-05-23 17:22:26', '2.83.182.140'),
(740, 1, 'sous_categorie_modifiee', 'categories', 71, 'Outillage manuel', '2026-05-23 17:23:12', '2.83.182.140'),
(741, 1, 'sous_categorie_modifiee', 'categories', 76, 'Peinture & Revêtements', '2026-05-23 17:23:57', '2.83.182.140'),
(742, 1, 'sous_categorie_modifiee', 'categories', 75, 'Plomberie', '2026-05-23 17:24:19', '2.83.182.140'),
(743, 1, 'sous_categorie_modifiee', 'categories', 77, 'Quincaillerie', '2026-05-23 17:24:46', '2.83.182.140'),
(744, 1, 'sous_categorie_modifiee', 'categories', 118, 'Jouets pour animaux', '2026-05-23 17:29:25', '2.83.182.140'),
(745, 1, 'sous_categorie_modifiee', 'categories', 116, 'Nourriture pour animaux', '2026-05-23 17:30:09', '2.83.182.140'),
(746, 1, 'sous_categorie_modifiee', 'categories', 113, 'Oiseaux', '2026-05-23 17:30:57', '2.83.182.140'),
(747, 1, 'sous_categorie_modifiee', 'categories', 114, 'Poissons & aquariums', '2026-05-23 17:31:25', '2.83.182.140'),
(748, 1, 'sous_categorie_modifiee', 'categories', 115, 'Rongeurs', '2026-05-23 17:32:15', '2.83.182.140'),
(749, 1, 'sous_categorie_modifiee', 'categories', 120, 'Transport & habitat', '2026-05-23 17:32:41', '2.83.182.140'),
(750, 1, 'sous_categorie_modifiee', 'categories', 128, 'Matériel éducatif', '2026-05-23 17:34:36', '2.83.182.140'),
(751, 1, 'sous_categorie_modifiee', 'categories', 42, 'Puériculture (bébé)', '2026-05-23 17:36:40', '2.83.182.140'),
(752, 1, 'sous_categorie_modifiee', 'categories', 70, 'Sécurité domestique', '2026-05-23 17:37:17', '2.83.182.140'),
(753, 1, 'sous_categorie_modifiee', 'categories', 90, 'Voitures', '2026-05-23 17:40:09', '2.83.182.140'),
(754, 1, 'sous_categorie_modifiee', 'categories', 127, 'Cours & tutoriels', '2026-05-23 17:41:01', '2.83.182.140'),
(755, 1, 'sous_categorie_modifiee', 'categories', 130, 'Papeterie', '2026-05-23 17:41:48', '2.83.182.140'),
(756, 1, 'sous_categorie_modifiee', 'categories', 65, 'Literie & Linge de maison', '2026-05-23 17:42:55', '2.83.182.140'),
(757, 1, 'sous_categorie_modifiee', 'categories', 84, 'Jouets & divertissement', '2026-05-23 17:44:31', '2.83.182.140'),
(758, 1, 'sous_categorie_modifiee', 'categories', 74, 'Électricité', '2026-05-23 17:46:10', '2.83.182.140'),
(759, 1, 'sous_categorie_modifiee', 'categories', 112, 'Chats', '2026-05-23 17:48:06', '2.83.182.140'),
(760, 1, 'sous_categorie_modifiee', 'categories', 111, 'Chiens', '2026-05-23 17:48:33', '2.83.182.140'),
(761, 1, 'sous_categorie_modifiee', 'categories', 119, 'Hygiène & soins', '2026-05-23 17:49:17', '2.83.182.140'),
(762, 1, 'sous_categorie_modifiee', 'categories', 79, 'Aménagement extérieur', '2026-05-23 17:52:06', '2.83.182.140'),
(763, 1, 'sous_categorie_modifiee', 'categories', 55, 'Accessoires électroniques', '2026-05-23 17:53:10', '2.83.182.140'),
(764, 1, 'sous_categorie_modifiee', 'categories', 72, 'Outillage électrique', '2026-05-23 17:54:17', '2.83.182.140'),
(765, 1, 'sous_categorie_modifiee', 'categories', 75, 'Plomberie', '2026-05-23 17:55:01', '2.83.182.140'),
(766, 1, 'sous_categorie_modifiee', 'categories', 117, 'Accessoires (laisses, cages…)', '2026-05-23 17:57:42', '2.83.182.140'),
(767, 1, 'categorie_generale_modifiee', 'categories_generales', 71, 'Produits technologiques', '2026-05-24 05:59:37', '2.83.182.140'),
(768, 1, 'categorie_generale_modifiee', 'categories_generales', 76, 'Beauté & Santé', '2026-05-24 05:59:53', '2.83.182.140'),
(769, 1, 'categorie_generale_modifiee', 'categories_generales', 82, 'Enfants & Bébés', '2026-05-24 06:00:12', '2.83.182.140'),
(770, 1, 'categorie_generale_modifiee', 'categories_generales', 75, 'Automobile & Moto', '2026-05-24 06:00:28', '2.83.182.140'),
(771, 1, 'categorie_generale_modifiee', 'categories_generales', 80, 'Vêtements et accessoires', '2026-05-24 06:00:54', '2.83.182.140'),
(772, 1, 'categorie_generale_modifiee', 'categories_generales', 72, 'Mobilier et décoration', '2026-05-24 06:02:26', '2.83.182.140'),
(773, 1, 'categorie_generale_modifiee', 'categories_generales', 81, 'Boissons', '2026-05-24 06:02:50', '2.83.182.140'),
(774, 1, 'categorie_generale_modifiee', 'categories_generales', 78, 'Livres, Médias & Formation', '2026-05-24 06:03:17', '2.83.182.140'),
(775, 1, 'categorie_generale_modifiee', 'categories_generales', 74, 'Jeux & Loisirs', '2026-05-24 06:04:17', '2.83.182.140'),
(776, 1, 'categorie_generale_modifiee', 'categories_generales', 73, 'Bricolage & Jardin', '2026-05-24 06:04:42', '2.83.182.140'),
(777, 1, 'categorie_generale_modifiee', 'categories_generales', 77, 'Animaux', '2026-05-24 06:05:20', '2.83.182.140'),
(778, 1, 'boutique_désactivée', 'boutique', 2, 'Boutique : bussnes', '2026-05-27 05:43:24', '195.23.211.117'),
(779, 1, 'categorie_generale_creee', 'categories_generales', 86, 'teste de l\'ajout', '2026-06-02 19:51:21', '::1'),
(780, 1, 'categorie_generale_supprimee', 'categories_generales', 86, '', '2026-06-02 19:51:27', '::1'),
(781, 1, 'sous_categorie_creee', 'categories', 148, 'teste !!', '2026-06-02 19:51:57', '::1'),
(782, 1, 'sous_categorie_supprimee', 'categories', 147, 'TestSousCat_1780429832', '2026-06-02 19:52:28', '::1'),
(783, 1, 'sous_categorie_supprimee', 'categories', 148, 'teste !!', '2026-06-02 19:52:54', '::1');

-- --------------------------------------------------------

--
-- Structure de la table `trending_config`
--

DROP TABLE IF EXISTS `trending_config`;
CREATE TABLE IF NOT EXISTS `trending_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id)',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'categories',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Enhance Your Music Experience',
  `bouton_texte` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Buy Now!',
  `bouton_lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'speaker.png',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trending_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firebase_uid` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_provider` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `accepte_conditions` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Acceptation des conditions d''utilisation (0 = non accept??, 1 = accept??)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  UNIQUE KEY `idx_users_firebase_uid` (`firebase_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `email`, `telephone`, `password`, `firebase_uid`, `auth_provider`, `date_creation`, `statut`, `accepte_conditions`) VALUES
(1, 'Jomas', 'Nick', 'webgeniuses12@gmail.com', '+221703083027', '$2y$10$Pl7ZIozne4FBB1wSkg7qd.p3vTZgUzjzGBjfuEoImRgEm4LKt6KSS', 'VleU4dw8QeMOCnDINyJYJJpY9uC2', 'google', '2026-04-08 14:26:56', 'actif', 1),
(2, 'jomas', 'nick', 'oyonoeffe11@gmail.com', '777777777', '$2y$10$6QNbJIBVXPoWV.QrZYhLxuHr5aJ6BnNWzwhwAwkkw3Zxn05d6Qy2C', 'UX5QoOG0FEUJHsTAVK0rYlSQZVi1', 'google', '2026-04-10 15:46:57', 'actif', 1),
(3, 'teste', 'teste', 'ludvanne@gmail.com', '88888888', '$2y$10$SA65nsGn1CvSVjol3w/./Oy3mNAbOzQOJhuD9NZwXF0AnNp1RlmJC', NULL, NULL, '2026-04-18 13:58:05', 'actif', 1),
(4, 'Touré', 'mariama', 'touremariama660@gmail.com', '775035940', '$2y$10$jPUH3KXy7zOuzkZJO.n1ge8Um5FD6yibH8PeaOO3X24hOMmwkv.UW', NULL, NULL, '2026-04-19 18:37:06', 'actif', 1),
(5, 'Mendes', 'vroling', 'vrolingmendy0@gmail.com', '776110061', '$2y$10$TQKeEDGdyo7UBiu4JTNFUO74dewx.Bv.MlBs4TC8BedQi7jWaTUVu', 'TCcTqWOlMeajYYH3Gc2qbruD2mm1', 'google', '2026-04-24 03:28:26', 'actif', 1),
(6, 'mendy', 'alvin', 'vrolingmendes@gmail.com', '770000000', '$2y$10$ab34yJ0Eyoo1lq23f/6xVOnzQldxR3hWu9.DlQoLDRuVeFFZSIAMe', 'zm2UWWbLF7eDUlJUsEGuf3Fy8Tt2', 'google', '2026-04-24 03:31:01', 'actif', 1),
(7, 'sego', 'mendy', 'vroling@groupeisi.com', '780000000', '$2y$10$SLu/A0vW/LN9U9BFOkCvj.6DEy39HdoMpQml85K4W3o3QI.TubXeq', NULL, NULL, '2026-04-24 03:32:34', 'actif', 1),
(8, 'teste user', '', NULL, '+221777777777', '$2y$10$DxbLuVq2rAswjTywfaNJd.KlC2Rr.VojEvWqTOtdT5e5QIB9HWHMi', NULL, NULL, '2026-05-14 15:11:38', 'actif', 1),
(9, 'joucancia Mendy', '', 'joucancia@gmail.com', '221771111111', '$2y$10$gKphfjVSTbd/oAv6bIuW2uPZWi55UlSNPaMW4CDaLARZixuMa2P5u', NULL, NULL, '2026-05-19 15:48:48', 'actif', 1),
(10, 'Diallo', '', 'mounas153246@gmail.com', '221779037179', '$2y$10$fwEd4SSpLMg1lGEOAu697OA3Jd2MRU0hS/2fiOEcAfZq.ZWQyAlK.', NULL, NULL, '2026-05-20 09:36:13', 'actif', 0),
(11, 'Gaye', '', 'dadagaye1996@gmail.com', '221777624085', '$2y$10$sdSrDfrJ6jxSsPRCXMbjyu8gLDhMMB.w7Gc.qFQW7S0VGXnusIX8q', NULL, NULL, '2026-05-23 09:29:53', 'actif', 1),
(12, 'Vroling Mendy', '', 'vrolingmendy22@icloud.com', '221336666666', '$2y$10$2QllmongJtfVeL4e5y6PKOFb73vu97xyNCHlTQoGurHuHAYEHg7rW', '1pwp4UKrg7ffTA4x1mfJAlOvaqn2', 'apple', '2026-05-23 11:16:36', 'actif', 1),
(13, 'Nick Effe', '', 'oyonoeffe09@gmail.com', '221337777777', '$2y$10$V9OILMSkDhfMzUEa47Y66edI0HOZ4Ci0sQsoSqnZ9Sqydsgl04fQC', '8dviVToGTndXUtuz4q5TNgcPgJ72', 'google', '2026-05-23 16:37:37', 'actif', 1),
(14, 'MVE EFFE', '', NULL, '24177879701', '$2y$10$FFT2S.JRCab3fsNyANa4OuTUUd9UzmbzIAIay7Z/jc7LSTruWZRn6', NULL, NULL, '2026-05-25 12:04:38', 'actif', 1),
(15, 'sadio sylla', '', 'syllasadio867@gmail.com', '221778316915', '$2y$10$7bSrXM2ZSilmXDDCO0xi6ezICUUOEEvMkoNC8xIDHkMlXBEuL8xqq', NULL, NULL, '2026-05-26 17:35:49', 'actif', 1),
(16, 'Guy Nedrick', '', 'nedrickguy@gmail.com', '33758444871', '$2y$10$6sNNdjYb/q9HRHqC0dprA.wpIUgYYcMqaLt1Z7i69bk7uvhVBE2qO', 'FUA8gWh5H9W9v7imH5psRU1KluG3', 'google', '2026-05-27 07:58:46', 'actif', 1),
(17, 'Megneng', '', 'mihindouesther15@gmail.com', '33698160407', '$2y$10$hUKznUUzfv6umC9cVLfbj.ZO7a4COk7Drec1aUd.YUwQbBLexLkKC', NULL, NULL, '2026-05-27 10:45:52', 'actif', 0),
(18, 'justine', '', NULL, '221333333333', '$2y$10$iOX8ef25EzpO5PXwD7TPseh1cX6Ea/0/IDdVbpXRNJmFsx9/zeiuS', NULL, NULL, '2026-06-02 20:00:10', 'actif', 1);

-- --------------------------------------------------------

--
-- Structure de la table `user_password_reset`
--

DROP TABLE IF EXISTS `user_password_reset`;
CREATE TABLE IF NOT EXISTS `user_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_password_reset`
--

INSERT INTO `user_password_reset` (`id`, `email`, `token`, `expires_at`, `used`, `date_creation`) VALUES
(1, 'vrolingmendy0@gmail.com', '86f5622e3bad4859e7537e0ff3f0a9d685715616d0373e97e67062378ea5246e', '2026-05-15 10:38:08', 0, '2026-05-15 04:38:08'),
(2, 'mounas153246@gmail.com', '2930798ade332fd85ad95f9e1cbe68e6d7c25ab5b783bb5cf15921804c3b495c', '2026-05-20 15:41:46', 0, '2026-05-20 09:41:46'),
(3, 'mihindouesther15@gmail.com', 'da380239db71e161419d3443e394b791d1ddcea5d60289812c6027ea73427c86', '2026-05-27 16:46:14', 0, '2026-05-27 10:46:14');

-- --------------------------------------------------------

--
-- Structure de la table `vendeur_certification_demandes`
--

DROP TABLE IF EXISTS `vendeur_certification_demandes`;
CREATE TABLE IF NOT EXISTS `vendeur_certification_demandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `niveau` enum('standard','vip','premium') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('en_attente','approuvee','refusee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boutique_nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `boutique_region` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_exacte` text COLLATE utf8mb4_unicode_ci,
  `description_activite` text COLLATE utf8mb4_unicode_ci,
  `numero_registre` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NINEA / RC',
  `photo_local_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_local_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_local_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_piece_identite` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Photo pièce d''identité',
  `message_demande` text COLLATE utf8mb4_unicode_ci,
  `motif_refus` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int DEFAULT NULL,
  `vendeur_notif_lue` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Notification vendeur lue (validation/refus)',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_niveau` (`niveau`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `vendeur_certification_demandes`
--

INSERT INTO `vendeur_certification_demandes` (`id`, `admin_id`, `niveau`, `statut`, `nom`, `prenom`, `email`, `telephone`, `boutique_nom`, `boutique_region`, `adresse_exacte`, `description_activite`, `numero_registre`, `photo_local_1`, `photo_local_2`, `photo_local_3`, `photo_document`, `photo_piece_identite`, `message_demande`, `motif_refus`, `date_creation`, `date_traitement`, `traite_par`, `vendeur_notif_lue`) VALUES
(1, 7, 'standard', 'approuvee', 'effe oyono', 'nick jomas', 'test@colobanes.com', '+221770000000', 'Sugar-Paper', 'dakar', '', '', '', '', '', '', '', 'certifications/7/piece_f6a0cf4907272426.jpeg', '', NULL, '2026-06-02 21:50:04', '2026-06-02 22:53:25', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `vendeur_comptes_acces`
--

DROP TABLE IF EXISTS `vendeur_comptes_acces`;
CREATE TABLE IF NOT EXISTS `vendeur_comptes_acces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendeur_admin_id` int NOT NULL COMMENT 'ID admin (rôle vendeur) propriétaire de la boutique',
  `nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vca_telephone` (`telephone`),
  KEY `idx_vca_vendeur` (`vendeur_admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `vendeur_comptes_acces`
--

INSERT INTO `vendeur_comptes_acces` (`id`, `vendeur_admin_id`, `nom`, `telephone`, `password`, `statut`, `date_creation`, `derniere_connexion`) VALUES
(1, 4, 'alvin', '771234567', '$2y$10$yc61E5VBHGs1vbgwJuVga.Jq9Vq5wc3bM/I3qGolmzupHc8EctujO', 'actif', '2026-04-20 09:27:53', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id)',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `fichier_video` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du fichier vid??o upload??',
  `image_preview` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Image de pr??visualisation de la vid??o',
  `overlay_texte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texte overlay (Prix, D??tails, Avis, etc.)',
  `ordre` int NOT NULL DEFAULT '0' COMMENT 'Ordre d''affichage dans le carrousel',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`),
  KEY `idx_videos_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `zones_livraison`
--

DROP TABLE IF EXISTS `zones_livraison`;
CREATE TABLE IF NOT EXISTS `zones_livraison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL COMMENT 'Vendeur (admin.id)',
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quartier` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_ville` (`ville`),
  KEY `idx_statut` (`statut`),
  KEY `idx_zones_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bl_lignes`
--
-- [FK reportées en fin de fichier] `bl_lignes`

--
-- Contraintes pour la table `bons_livraison`
--
-- [FK reportées en fin de fichier] `bons_livraison`

--
-- Contraintes pour la table `caisse_ventes`
--
-- [FK reportées en fin de fichier] `caisse_ventes`

--
-- Contraintes pour la table `caisse_vente_lignes`
--
-- [FK reportées en fin de fichier] `caisse_vente_lignes`

--
-- Contraintes pour la table `categories`
--
-- [FK reportées en fin de fichier] `categories`

--
-- Contraintes pour la table `clients_b2b`
--
-- [FK reportées en fin de fichier] `clients_b2b`

--
-- Contraintes pour la table `commandes`
--
-- [FK reportées en fin de fichier] `commandes`

--
-- Contraintes pour la table `commandes_personnalisees`
--
-- [FK reportées en fin de fichier] `commandes_personnalisees`

--
-- Contraintes pour la table `commande_produits`
--
-- [FK reportées en fin de fichier] `commande_produits`

--
-- Contraintes pour la table `depenses`
--
-- [FK reportées en fin de fichier] `depenses`

--
-- Contraintes pour la table `devis`
--
-- [FK reportées en fin de fichier] `devis`

--
-- Contraintes pour la table `devis_produits`
--
-- [FK reportées en fin de fichier] `devis_produits`

--
-- Contraintes pour la table `employes`
--
-- [FK reportées en fin de fichier] `employes`

--
-- Contraintes pour la table `factures`
--
-- [FK reportées en fin de fichier] `factures`

--
-- Contraintes pour la table `factures_devis`
--
-- [FK reportées en fin de fichier] `factures_devis`

--
-- Contraintes pour la table `factures_mensuelles`
--
-- [FK reportées en fin de fichier] `factures_mensuelles`

--
-- Contraintes pour la table `factures_personnalisees`
--
-- [FK reportées en fin de fichier] `factures_personnalisees`

--
-- Contraintes pour la table `facture_mensuelle_bl`
--
-- [FK reportées en fin de fichier] `facture_mensuelle_bl`

--
-- Contraintes pour la table `favoris`
--
-- [FK reportées en fin de fichier] `favoris`

--
-- Contraintes pour la table `panier`
--
-- [FK reportées en fin de fichier] `panier`

--
-- Contraintes pour la table `produits`
--
-- [FK reportées en fin de fichier] `produits`

--
-- Contraintes pour la table `produits_variantes`
--
-- [FK reportées en fin de fichier] `produits_variantes`

--
-- Contraintes pour la table `produits_visites`
--
-- [FK reportées en fin de fichier] `produits_visites`

--
-- Contraintes pour la table `stock_articles`
--
-- [FK reportées en fin de fichier] `stock_articles`

--
-- Contraintes pour la table `stock_mouvements`
--
-- [FK reportées en fin de fichier] `stock_mouvements`

--
-- Contraintes pour la table `super_admin_logs`
--
-- [FK reportées en fin de fichier] `super_admin_logs`

--
-- Contraintes pour la table `vendeur_comptes_acces`
--
-- [FK reportées en fin de fichier] `vendeur_comptes_acces`
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- =============================================================================
-- Contraintes FOREIGN KEY (après tables + clés primaires)
-- Corrige l'erreur #6125 (ex. fk_bl_admin -> admin.id)
-- =============================================================================

ALTER TABLE `bl_lignes` ADD CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `bl_lignes` ADD CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `bons_livraison` ADD CONSTRAINT `fk_bl_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `bons_livraison` ADD CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON UPDATE CASCADE;
ALTER TABLE `caisse_ventes` ADD CONSTRAINT `fk_caisse_ventes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON UPDATE CASCADE;
ALTER TABLE `caisse_ventes` ADD CONSTRAINT `fk_caisse_ventes_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `caisse_vente_lignes` ADD CONSTRAINT `fk_cvl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE;
ALTER TABLE `caisse_vente_lignes` ADD CONSTRAINT `fk_cvl_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_categorie_generale` FOREIGN KEY (`categorie_generale_id`) REFERENCES `categories_generales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;
ALTER TABLE `clients_b2b` ADD CONSTRAINT `fk_cb2b_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commandes` ADD CONSTRAINT `fk_cmd_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commandes` ADD CONSTRAINT `fk_cmd_admin_traitement` FOREIGN KEY (`admin_dernier_traitement_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commandes` ADD CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commandes` ADD CONSTRAINT `fk_commandes_vendeur` FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`) ON UPDATE CASCADE;
ALTER TABLE `commandes_personnalisees` ADD CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commandes_personnalisees` ADD CONSTRAINT `fk_cp_zone_livraison` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `commande_produits` ADD CONSTRAINT `fk_commande_produits_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `commande_produits` ADD CONSTRAINT `fk_commande_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `depenses` ADD CONSTRAINT `fk_dep_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `depenses` ADD CONSTRAINT `fk_dep_cat` FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `devis` ADD CONSTRAINT `fk_devis_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `devis` ADD CONSTRAINT `fk_devis_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `devis` ADD CONSTRAINT `fk_devis_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `devis_produits` ADD CONSTRAINT `fk_devis_produits_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `devis_produits` ADD CONSTRAINT `fk_devis_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `employes` ADD CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `factures` ADD CONSTRAINT `fk_factures_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `factures_devis` ADD CONSTRAINT `fk_factures_devis_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `factures_devis` ADD CONSTRAINT `fk_factures_devis_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `factures_mensuelles` ADD CONSTRAINT `fk_fm_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `factures_mensuelles` ADD CONSTRAINT `fk_fm_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON UPDATE CASCADE;
ALTER TABLE `factures_personnalisees` ADD CONSTRAINT `fk_fp_commande_perso` FOREIGN KEY (`commande_personnalisee_id`) REFERENCES `commandes_personnalisees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `facture_mensuelle_bl` ADD CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `facture_mensuelle_bl` ADD CONSTRAINT `fk_fmb_facture` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `favoris` ADD CONSTRAINT `fk_favoris_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `favoris` ADD CONSTRAINT `fk_favoris_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `panier` ADD CONSTRAINT `fk_panier_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `panier` ADD CONSTRAINT `fk_panier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `panier` ADD CONSTRAINT `fk_panier_vendeur` FOREIGN KEY (`vendeur_id`) REFERENCES `admin` (`id`) ON UPDATE CASCADE;
ALTER TABLE `produits` ADD CONSTRAINT `fk_produits_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON UPDATE CASCADE;
ALTER TABLE `produits` ADD CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `produits` ADD CONSTRAINT `fk_produits_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `produits_variantes` ADD CONSTRAINT `fk_variantes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `produits_visites` ADD CONSTRAINT `fk_visites_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `produits_visites` ADD CONSTRAINT `fk_visites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `stock_articles` ADD CONSTRAINT `fk_stock_articles_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;
ALTER TABLE `stock_mouvements` ADD CONSTRAINT `fk_mouvements_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `stock_mouvements` ADD CONSTRAINT `fk_mouvements_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `super_admin_logs` ADD CONSTRAINT `fk_logs_super_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `vendeur_comptes_acces` ADD CONSTRAINT `fk_vca_vendeur_admin` FOREIGN KEY (`vendeur_admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
-- Fin import préparé
