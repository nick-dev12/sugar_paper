<?php
/**
 * Migration : archivage soft des commandes.
 *
 * Usage : php migrations/run_add_commandes_archived.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

if (!mig_table_exists($db, 'commandes')) {
    echo "Table commandes absente — migration ignorée.\n";
    exit(0);
}

try {
    mig_add_column_if_missing(
        $db,
        'commandes',
        'archived',
        "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = commande archivée' AFTER `statut`"
    );
    mig_add_column_if_missing(
        $db,
        'commandes',
        'date_archivage',
        "DATETIME NULL DEFAULT NULL COMMENT 'Date d\\'archivage' AFTER `archived`"
    );
    mig_add_column_if_missing(
        $db,
        'commandes',
        'archived_by_admin_id',
        "INT NULL DEFAULT NULL COMMENT 'Admin ayant archivé' AFTER `date_archivage`"
    );

    if (!mig_index_exists($db, 'commandes', 'idx_commandes_archived')) {
        mig_safe_exec(
            $db,
            'ALTER TABLE `commandes` ADD KEY `idx_commandes_archived` (`archived`)',
            'index commandes.archived'
        );
    }

    echo "Migration commandes.archived terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
