-- =============================================================================
-- Vider UNE table dans phpMyAdmin (sans erreur #1701)
-- =============================================================================
-- Remplacez `admin` par le nom de votre table.
-- Ne lancez PAS uniquement « TRUNCATE admin » : MySQL bloque si d'autres
-- tables référencent admin (ex. bons_livraison.fk_bl_admin).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `admin`;

SET FOREIGN_KEY_CHECKS = 1;

-- Alternative après installation de truncate_table_safe_procedure.sql :
-- CALL truncate_table_safe('admin');
