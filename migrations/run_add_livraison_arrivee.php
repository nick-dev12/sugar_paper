<?php
/**
 * Colonne livraison_arrivee_at (commande + bons_livraison).
 * Usage : php migrations/run_add_livraison_arrivee.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$definition = "DATETIME NULL DEFAULT NULL COMMENT 'Horodatage confirmation arrivée livreur chez le client'";

mig_add_column_smart(
    $db,
    'commandes',
    'livraison_arrivee_at',
    $definition,
    ['livraison_terminee_at', 'tracking_started_at', 'tracking_active', 'livreur_id']
);

if (mig_table_exists($db, 'bons_livraison')) {
    mig_add_column_smart(
        $db,
        'bons_livraison',
        'livraison_arrivee_at',
        $definition,
        ['livraison_terminee_at', 'tracking_started_at', 'tracking_active', 'livreur_id']
    );
}

mig_mark_applied($db, 'livraison_arrivee', 'Confirmation arrivée livreur');
echo "Migration livraison_arrivee_at terminée.\n";
