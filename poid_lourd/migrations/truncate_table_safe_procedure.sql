-- =============================================================================
-- Procédure : vider une table malgré les clés étrangères (MySQL / MariaDB)
-- =============================================================================
-- MySQL refuse TRUNCATE (#1701) si une autre table a une FK vers cette table,
-- même avec ON DELETE CASCADE. La seule solution fiable : désactiver temporairement
-- la vérification des FK pour la session.
--
-- Installation (phpMyAdmin > SQL, une fois) :
--   Exécuter ce fichier entier sur la base tresor_afri
--
-- Utilisation pour vider UNE table (ex. admin) :
--   CALL truncate_table_safe('admin');
--
-- Pour vider TOUTE la base : migrations/vider_base_donnees.sql
-- =============================================================================

DROP PROCEDURE IF EXISTS `truncate_table_safe`;

DELIMITER $$

CREATE PROCEDURE `truncate_table_safe`(IN `p_table` VARCHAR(64))
BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SELECT COUNT(*) INTO v_exists
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = `p_table`;

  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Table introuvable dans la base courante.';
  END IF;

  SET @sql_truncate = CONCAT('TRUNCATE TABLE `', REPLACE(`p_table`, '`', ''), '`');

  SET FOREIGN_KEY_CHECKS = 0;
  PREPARE stmt FROM @sql_truncate;
  EXECUTE stmt;
  DEALLOCATE PREPARE stmt;
  SET FOREIGN_KEY_CHECKS = 1;
END$$

DELIMITER ;

-- -----------------------------------------------------------------------------
-- Vider TOUTES les tables de la base courante (aucune table oubliée)
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `truncate_all_tables`;

DELIMITER $$

CREATE PROCEDURE `truncate_all_tables`()
BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_table VARCHAR(64);
  DECLARE cur_tables CURSOR FOR
    SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_TYPE` = 'BASE TABLE';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  SET FOREIGN_KEY_CHECKS = 0;

  OPEN cur_tables;
  tables_loop: LOOP
    FETCH cur_tables INTO v_table;
    IF v_done THEN
      LEAVE tables_loop;
    END IF;

    SET @sql_truncate = CONCAT('TRUNCATE TABLE `', REPLACE(v_table, '`', ''), '`');
    PREPARE stmt FROM @sql_truncate;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur_tables;

  SET FOREIGN_KEY_CHECKS = 1;
END$$

DELIMITER ;

-- Après installation :
--   CALL truncate_all_tables();     -- vide toute la base
--   CALL truncate_table_safe('admin'); -- vide une seule table
