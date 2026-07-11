-- Slides du hero : rattachement optionnel à un vendeur (boutique).
-- NULL = réservé à un usage plateforme / ancien comportement.
-- Exécuter une fois sur la base (ou via run_migrate_slider_admin_id.php).

ALTER TABLE `slider`
  ADD COLUMN `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Vendeur (admin.id) propriétaire du slide pour la vitrine' AFTER `id`,
  ADD KEY `idx_slider_admin_id` (`admin_id`);
