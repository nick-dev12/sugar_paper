<?php
/**
 * Migration : archivage soft des factures B2B (bons_livraison).
 *
 * Usage : php migrations/run_add_bl_archived.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

if (!mig_table_exists($db, 'bons_livraison')) {
    echo "Table bons_livraison absente — migration ignorée.\n";
    exit(0);
}

try {
    mig_add_column_if_missing(
        $db,
        'bons_livraison',
        'archived',
        "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = facture archivée' AFTER `statut`"
    );
    mig_add_column_if_missing(
        $db,
        'bons_livraison',
        'date_archivage',
        "DATETIME NULL DEFAULT NULL COMMENT 'Date d\\'archivage' AFTER `archived`"
    );
    mig_add_column_if_missing(
        $db,
        'bons_livraison',
        'archived_by_admin_id',
        "INT NULL DEFAULT NULL COMMENT 'Admin ayant archivé' AFTER `date_archivage`"
    );

    if (!mig_index_exists($db, 'bons_livraison', 'idx_bons_livraison_archived')) {
        mig_safe_exec(
            $db,
            'ALTER TABLE `bons_livraison` ADD KEY `idx_bons_livraison_archived` (`archived`)',
            'index bons_livraison.archived'
        );
    }

    echo "Migration bons_livraison.archived terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
