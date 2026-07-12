<?php
/**
 * Traçabilité admin sur devis, commandes, clients B2B, factures devis.
 * Ajouts uniquement — aucune suppression de données.
 *
 * Usage : php migrations/run_add_admin_tracabilite_interactions.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

echo "=== Traçabilité interactions admin ===\n\n";

try {
    if (!mig_table_exists($db, 'devis')) {
        echo "! Table devis absente — exécutez d'abord run_add_devis.php\n";
    } else {
        mig_add_column_if_missing($db, 'devis', 'admin_createur_id', "INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant créé le devis' AFTER `user_id`");
        if (!mig_index_exists($db, 'devis', 'idx_devis_admin_createur')) {
            mig_safe_exec($db, 'ALTER TABLE `devis` ADD KEY `idx_devis_admin_createur` (`admin_createur_id`)', 'index devis.admin_createur');
        }
        mig_safe_exec($db, 'ALTER TABLE `devis` ADD CONSTRAINT `fk_devis_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'FK devis.admin_createur');
    }

    if (!mig_table_exists($db, 'commandes')) {
        echo "! Table commandes absente\n";
    } else {
        mig_add_column_if_missing($db, 'commandes', 'admin_createur_id', "INT(11) NULL DEFAULT NULL COMMENT 'Saisie manuelle admin' AFTER `user_id`");
        mig_add_column_if_missing($db, 'commandes', 'admin_dernier_traitement_id', "INT(11) NULL DEFAULT NULL COMMENT 'Dernier changement de statut' AFTER `admin_createur_id`");
        if (!mig_index_exists($db, 'commandes', 'idx_cmd_admin_createur')) {
            mig_safe_exec($db, 'ALTER TABLE `commandes` ADD KEY `idx_cmd_admin_createur` (`admin_createur_id`)', 'index commandes.admin_createur');
        }
        if (!mig_index_exists($db, 'commandes', 'idx_cmd_admin_traitement')) {
            mig_safe_exec($db, 'ALTER TABLE `commandes` ADD KEY `idx_cmd_admin_traitement` (`admin_dernier_traitement_id`)', 'index commandes.admin_traitement');
        }
        mig_safe_exec($db, 'ALTER TABLE `commandes` ADD CONSTRAINT `fk_cmd_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'FK commandes.admin_createur');
        mig_safe_exec($db, 'ALTER TABLE `commandes` ADD CONSTRAINT `fk_cmd_admin_traitement` FOREIGN KEY (`admin_dernier_traitement_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'FK commandes.admin_traitement');
    }

    if (mig_table_exists($db, 'clients_b2b')) {
        mig_add_column_if_missing($db, 'clients_b2b', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL AFTER `notes`');
        if (!mig_index_exists($db, 'clients_b2b', 'idx_cb2b_admin_createur')) {
            mig_safe_exec($db, 'ALTER TABLE `clients_b2b` ADD KEY `idx_cb2b_admin_createur` (`admin_createur_id`)', 'index clients_b2b.admin_createur');
        }
        mig_safe_exec($db, 'ALTER TABLE `clients_b2b` ADD CONSTRAINT `fk_cb2b_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'FK clients_b2b.admin_createur');
    }

    if (mig_table_exists($db, 'factures_devis')) {
        mig_add_column_if_missing($db, 'factures_devis', 'admin_createur_id', "INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant généré la facture' AFTER `token`");
        if (!mig_index_exists($db, 'factures_devis', 'idx_factures_devis_admin')) {
            mig_safe_exec($db, 'ALTER TABLE `factures_devis` ADD KEY `idx_factures_devis_admin` (`admin_createur_id`)', 'index factures_devis.admin_createur');
        }
        mig_safe_exec($db, 'ALTER TABLE `factures_devis` ADD CONSTRAINT `fk_factures_devis_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'FK factures_devis.admin_createur');
    }

    echo "\n=== Traçabilité interactions admin terminée ===\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
