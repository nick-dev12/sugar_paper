<?php
/**
 * Notes clients sur livreurs + moyenne cache sur admin.
 * Usage : php migrations/run_add_livreur_notes_client.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `livreur_notes_client` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `livreur_id` INT NOT NULL COMMENT 'admin.id du livreur',
  `commande_id` INT NULL DEFAULT NULL,
  `bl_id` INT NULL DEFAULT NULL,
  `livraison_type` ENUM('commande','facture') NOT NULL DEFAULT 'commande',
  `note` TINYINT UNSIGNED NOT NULL COMMENT '1 à 5',
  `client_user_id` INT NULL DEFAULT NULL,
  `client_nom` VARCHAR(200) NOT NULL DEFAULT '',
  `client_telephone` VARCHAR(50) NOT NULL DEFAULT '',
  `numero_reference` VARCHAR(80) NOT NULL DEFAULT '',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_livreur_note_commande` (`commande_id`),
  UNIQUE KEY `uniq_livreur_note_bl` (`bl_id`),
  KEY `idx_livreur_notes_livreur` (`livreur_id`),
  KEY `idx_livreur_notes_date` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

mig_safe_exec($db, $sql, 'Table livreur_notes_client');

mig_add_column_smart(
    $db,
    'admin',
    'livreur_note_moyenne',
    "DECIMAL(3,2) NULL DEFAULT NULL COMMENT 'Moyenne notes clients 1-5'",
    ['photo_profil', 'role', 'statut']
);

mig_add_column_smart(
    $db,
    'admin',
    'livreur_nb_notes',
    "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nombre de notes clients reçues'",
    ['livreur_note_moyenne', 'photo_profil', 'role']
);

mig_mark_applied($db, 'livreur_notes_client', 'Notes clients livreurs');
echo "Migration livreur_notes_client terminée.\n";
