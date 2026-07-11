-- Journal des recherches sur le catalogue (produits.php) — alimente la section « Recherches fréquentes » (index).
-- La table est aussi créée automatiquement au premier enregistrement (PHP) si absente.
CREATE TABLE IF NOT EXISTS `recherches_catalogue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terme` varchar(500) NOT NULL DEFAULT '',
  `date_recherche` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date_recherche` (`date_recherche`),
  KEY `idx_terme` (`terme`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
