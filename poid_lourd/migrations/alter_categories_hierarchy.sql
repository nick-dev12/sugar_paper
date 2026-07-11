-- Catégories marketplace : catégories générales (plateforme) + sous-catégories vendeur (parent_id, admin_id)
-- Exécuter via run_migrate_categories_hierarchy.php (recommandé) ou manuellement après sauvegarde.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'parent_id') = 0,
  'ALTER TABLE `categories` ADD COLUMN `parent_id` INT NULL DEFAULT NULL COMMENT \'FK catégorie générale\' AFTER `id`',
  'SELECT 1'
);
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'admin_id') = 0,
  'ALTER TABLE `categories` ADD COLUMN `admin_id` INT NULL DEFAULT NULL COMMENT \'Propriétaire sous-cat (vendeur)\' AFTER `parent_id`',
  'SELECT 1'
);
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'icone') = 0,
  'ALTER TABLE `categories` ADD COLUMN `icone` VARCHAR(80) NULL DEFAULT NULL COMMENT \'Classe Font Awesome\' AFTER `image`',
  'SELECT 1'
);
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'sort_ordre') = 0,
  'ALTER TABLE `categories` ADD COLUMN `sort_ordre` INT NOT NULL DEFAULT 0 AFTER `icone`',
  'SELECT 1'
);
PREPARE s4 FROM @sql; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'est_plateforme') = 0,
  'ALTER TABLE `categories` ADD COLUMN `est_plateforme` TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = rayon officiel\' AFTER `sort_ordre`',
  'SELECT 1'
);
PREPARE s4b FROM @sql; EXECUTE s4b; DEALLOCATE PREPARE s4b;

-- Index si absents
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND INDEX_NAME = 'idx_categories_parent') = 0,
  'ALTER TABLE `categories` ADD INDEX `idx_categories_parent` (`parent_id`)',
  'SELECT 1'
);
PREPARE s5 FROM @sql; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'categories' AND INDEX_NAME = 'idx_categories_admin') = 0,
  'ALTER TABLE `categories` ADD INDEX `idx_categories_admin` (`admin_id`)',
  'SELECT 1'
);
PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

-- Contraintes (échouent si données invalides — à ajuster manuellement si besoin)
-- FK parent
SET @fkp := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'categories' AND CONSTRAINT_NAME = 'fk_categories_parent');
SET @sql := IF(@fkp = 0,
  'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s7 FROM @sql; EXECUTE s7; DEALLOCATE PREPARE s7;

-- FK admin (table admin doit exister)
SET @fka := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'categories' AND CONSTRAINT_NAME = 'fk_categories_admin');
SET @sql := IF(@fka = 0,
  'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s8 FROM @sql; EXECUTE s8; DEALLOCATE PREPARE s8;

-- Sous-catégories vendeur : jamais rayons plateforme
UPDATE `categories` SET `est_plateforme` = 0 WHERE `admin_id` IS NOT NULL OR (`parent_id` IS NOT NULL AND `parent_id` > 0);

UPDATE `categories` SET `sort_ordre` = `id` WHERE `sort_ordre` = 0 OR `sort_ordre` IS NULL;
