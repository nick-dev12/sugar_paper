<?php
/**
 * Complète les colonnes devis manquantes (adresse client, TVA).
 * Ajouts uniquement — idempotent.
 *
 * Usage : php migrations/run_add_devis_bl_columns_complement.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

if (!mig_table_exists($db, 'devis')) {
    fwrite(STDERR, "Table devis absente — exécutez run_add_devis.php\n");
    exit(1);
}

echo "=== Complément colonnes devis ===\n\n";

try {
    mig_add_column_if_missing($db, 'devis', 'adresse_client', "TEXT NULL DEFAULT NULL COMMENT 'Adresse postale ou siège du client (optionnel)' AFTER `client_email`");
    mig_add_column_if_missing($db, 'devis', 'tva_incluse', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = total TTC' AFTER `remise_globale_pct`");
    mig_add_column_if_missing($db, 'devis', 'taux_tva_pourcent', "DECIMAL(5,2) NOT NULL DEFAULT 18.00 AFTER `tva_incluse`");

    if (mig_table_exists($db, 'bons_livraison')) {
        mig_add_column_if_missing($db, 'bons_livraison', 'adresse_client', "TEXT NULL DEFAULT NULL COMMENT 'Adresse client affichée sur facture BL (optionnel)' AFTER `notes`");
        mig_add_column_if_missing($db, 'bons_livraison', 'tva_incluse', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = affichage TTC' AFTER `total_ht`");
        mig_add_column_if_missing($db, 'bons_livraison', 'taux_tva_pourcent', "DECIMAL(5,2) NOT NULL DEFAULT 18.00 AFTER `tva_incluse`");
    }

    if (mig_table_exists($db, 'factures_devis')) {
        mig_add_column_if_missing($db, 'factures_devis', 'tva_incluse', 'TINYINT(1) NOT NULL DEFAULT 0');
        mig_add_column_if_missing($db, 'factures_devis', 'montant_ht', 'DECIMAL(10,2) NULL DEFAULT NULL');
        mig_add_column_if_missing($db, 'factures_devis', 'montant_tva', 'DECIMAL(10,2) NULL DEFAULT NULL');
        mig_add_column_if_missing($db, 'factures_devis', 'taux_tva_pourcent', 'DECIMAL(5,2) NULL DEFAULT NULL');
    }

    echo "\n=== Complément devis terminé ===\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
