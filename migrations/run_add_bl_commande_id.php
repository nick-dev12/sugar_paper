<?php
/**
 * Lien facture (bons_livraison) ↔ commande du site.
 * Usage : php migrations/run_add_bl_commande_id.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

mig_add_column_smart($db, 'bons_livraison', 'commande_id', 'INT(11) NULL DEFAULT NULL', ['devis_id']);

if (!mig_index_exists($db, 'bons_livraison', 'idx_bl_commande')) {
    mig_safe_exec($db, 'ALTER TABLE `bons_livraison` ADD INDEX `idx_bl_commande` (`commande_id`)', 'index idx_bl_commande');
}

mig_mark_applied($db, 'bl_commande_id', 'Factures Invoice issues des commandes du site');
echo "Migration bl_commande_id terminée.\n";
