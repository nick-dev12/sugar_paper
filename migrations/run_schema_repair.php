<?php
/**
 * Réparation automatique du schéma : complète tables/colonnes manquantes.
 * Idempotent, sans suppression de données.
 *
 * Usage : php migrations/run_schema_repair.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();
$dir = __DIR__;

echo "=== Réparation automatique du schéma ===\n";
echo 'Base : ' . $db->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

$gaps_before = mig_scan_gaps($db);
echo 'Avant : ' . count($gaps_before['missing_tables']) . ' table(s), '
    . count($gaps_before['missing_columns']) . " colonne(s) manquante(s)\n\n";

$repair_scripts = [
    'run_add_logos.php',
    'run_add_caisse_tables.php',
    'run_migrate_invoice_bl.php',
    'run_add_devis_bl_columns_complement.php',
    'run_add_devis_bl_remise_globale.php',
    'run_add_types_client_bl.php',
    'run_add_contacts_adresse_plafond_bl.php',
    'run_add_bl_facture_paiement.php',
    'run_add_admin_tracabilite_interactions.php',
    'run_add_tracabilite_produits_categories_stock.php',
    'run_migrate_comptes_rh.php',
    'run_add_livreur_tracking.php',
    'run_fix_livreur_positions_fk.php',
];

foreach ($repair_scripts as $script) {
    $path = $dir . '/' . $script;
    if (!is_file($path)) {
        continue;
    }
    echo ">> $script\n";
    $code = mig_run_php_script($path);
    if ($code !== 0) {
        echo "!! Avertissement : $script code $code (on continue)\n";
    }
    echo "\n";
}

$column_repairs = [
    ['commandes', 'admin_createur_id', "INT(11) NULL DEFAULT NULL", ['user_id', 'client_telephone']],
    ['commandes', 'admin_dernier_traitement_id', "INT(11) NULL DEFAULT NULL", ['admin_createur_id', 'user_id']],
    ['produits', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL', ['statut', 'date_modification']],
    ['produits', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL', ['admin_createur_id', 'statut']],
    ['categories', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL', ['date_creation', 'image']],
    ['categories', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL', ['admin_createur_id', 'date_creation']],
    ['devis', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL', ['user_id', 'montant_total']],
    ['factures_devis', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL', ['token', 'montant_total']],
    ['clients_b2b', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL', ['notes', 'statut']],
    ['contacts', 'adresse', 'TEXT NULL', ['email', 'telephone']],
    ['contacts', 'plafond_bl_cumul_ht', 'DECIMAL(14,2) NOT NULL DEFAULT 0', ['adresse', 'email']],
    ['stock_mouvements', 'admin_id', 'INT(11) NULL DEFAULT NULL', ['notes', 'date_mouvement']],
    ['bons_livraison', 'livreur_id', 'INT(11) NULL DEFAULT NULL', ['notes', 'tracking_started_at']],
];

echo "→ Colonnes manquantes (réparation directe)…\n";
foreach ($column_repairs as $item) {
    mig_add_column_smart($db, $item[0], $item[1], $item[2], $item[3]);
}

$gaps_after = mig_scan_gaps($db);
echo "\n=== Résumé réparation ===\n";
echo 'Après : ' . count($gaps_after['missing_tables']) . ' table(s), '
    . count($gaps_after['missing_columns']) . " colonne(s) manquante(s)\n";

if (!empty($gaps_after['missing_tables'])) {
    echo "\nTables encore absentes :\n";
    foreach ($gaps_after['missing_tables'] as $t) {
        echo "  ! $t\n";
    }
}
if (!empty($gaps_after['missing_columns'])) {
    echo "\nColonnes encore absentes :\n";
    foreach ($gaps_after['missing_columns'] as $item) {
        echo '  ! ' . $item['table'] . '.' . $item['column'] . "\n";
    }
}

exit(count($gaps_after['missing_tables']) + count($gaps_after['missing_columns']) > 0 ? 1 : 0);
