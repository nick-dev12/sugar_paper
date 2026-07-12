-- Traçabilité : liaison des actions métier au compte admin (personnel)
-- Préférer : php migrations/run_add_admin_tracabilite_interactions.php (vérifie avant d'ajouter)

ALTER TABLE `devis`
  ADD COLUMN `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant créé le devis' AFTER `user_id`;

ALTER TABLE `devis`
  ADD KEY `idx_devis_admin_createur` (`admin_createur_id`);

ALTER TABLE `devis`
  ADD CONSTRAINT `fk_devis_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `commandes`
  ADD COLUMN `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Saisie manuelle admin' AFTER `user_id`;

ALTER TABLE `commandes`
  ADD COLUMN `admin_dernier_traitement_id` INT(11) NULL DEFAULT NULL COMMENT 'Dernier changement de statut' AFTER `admin_createur_id`;

ALTER TABLE `commandes`
  ADD KEY `idx_cmd_admin_createur` (`admin_createur_id`);

ALTER TABLE `commandes`
  ADD KEY `idx_cmd_admin_traitement` (`admin_dernier_traitement_id`);

ALTER TABLE `commandes`
  ADD CONSTRAINT `fk_cmd_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `commandes`
  ADD CONSTRAINT `fk_cmd_admin_traitement` FOREIGN KEY (`admin_dernier_traitement_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `clients_b2b`
  ADD COLUMN `admin_createur_id` INT(11) NULL DEFAULT NULL AFTER `notes`;

ALTER TABLE `clients_b2b`
  ADD KEY `idx_cb2b_admin_createur` (`admin_createur_id`);

ALTER TABLE `clients_b2b`
  ADD CONSTRAINT `fk_cb2b_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `factures_devis`
  ADD COLUMN `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant généré la facture' AFTER `token`;

ALTER TABLE `factures_devis`
  ADD KEY `idx_factures_devis_admin` (`admin_createur_id`);

ALTER TABLE `factures_devis`
  ADD CONSTRAINT `fk_factures_devis_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
