<?php
/**
 * Complète les colonnes devis / BL manquantes (adresse, remise, TVA).
 * Ajouts uniquement — idempotent, sans dépendance rigide sur AFTER.
 *
 * Usage : php migrations/run_add_devis_bl_columns_complement.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

if (!mig_table_exists($db, 'devis')) {
    fwrite(STDERR, "Table devis absente — exécutez run_add_devis.php\n");
    exit(1);
}

echo "=== Complément colonnes devis / BL ===\n\n";

try {
    mig_add_column_smart($db, 'devis', 'adresse_client',
        "TEXT NULL DEFAULT NULL COMMENT 'Adresse postale ou siège du client (optionnel)'",
        ['client_email', 'client_telephone', 'montant_total']);

    mig_add_column_smart($db, 'devis', 'remise_globale_pct',
        "DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total'",
        ['montant_total', 'notes']);

    mig_add_column_smart($db, 'devis', 'tva_incluse',
        "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = total TTC'",
        ['remise_globale_pct', 'montant_total', 'notes']);

    mig_add_column_smart($db, 'devis', 'taux_tva_pourcent',
        "DECIMAL(5,2) NOT NULL DEFAULT 18.00",
        ['tva_incluse', 'remise_globale_pct', 'montant_total']);

    if (mig_table_exists($db, 'bons_livraison')) {
        mig_add_column_smart($db, 'bons_livraison', 'adresse_client',
            "TEXT NULL DEFAULT NULL COMMENT 'Adresse client affichée sur facture BL (optionnel)'",
            ['notes', 'total_ht']);

        mig_add_column_smart($db, 'bons_livraison', 'remise_globale_pct',
            "DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total HT'",
            ['total_ht', 'notes']);

        mig_add_column_smart($db, 'bons_livraison', 'tva_incluse',
            "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = affichage TTC'",
            ['total_ht', 'remise_globale_pct', 'notes']);

        mig_add_column_smart($db, 'bons_livraison', 'taux_tva_pourcent',
            "DECIMAL(5,2) NOT NULL DEFAULT 18.00",
            ['tva_incluse', 'total_ht']);
    }

    if (mig_table_exists($db, 'factures_devis')) {
        mig_add_column_smart($db, 'factures_devis', 'tva_incluse', 'TINYINT(1) NOT NULL DEFAULT 0', ['montant_total', 'token']);
        mig_add_column_smart($db, 'factures_devis', 'montant_ht', 'DECIMAL(10,2) NULL DEFAULT NULL', ['tva_incluse', 'montant_total']);
        mig_add_column_smart($db, 'factures_devis', 'montant_tva', 'DECIMAL(10,2) NULL DEFAULT NULL', ['montant_ht', 'montant_total']);
        mig_add_column_smart($db, 'factures_devis', 'taux_tva_pourcent', 'DECIMAL(5,2) NULL DEFAULT NULL', ['montant_tva', 'montant_total']);
    }

    echo "\n=== Complément devis / BL terminé ===\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
