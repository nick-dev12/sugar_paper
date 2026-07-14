<?php
/**
 * Migration : token public pour factures B2B (bons_livraison).
 *
 * Usage : php migrations/run_add_bl_facture_token.php
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
        'facture_token',
        "VARCHAR(64) NULL DEFAULT NULL COMMENT 'Lien public facture BL' AFTER `date_modification`"
    );

    if (!mig_index_exists($db, 'bons_livraison', 'idx_bons_livraison_facture_token')) {
        mig_safe_exec(
            $db,
            'ALTER TABLE `bons_livraison` ADD UNIQUE KEY `idx_bons_livraison_facture_token` (`facture_token`)',
            'index unique bons_livraison.facture_token'
        );
    }

    $stmt = $db->query("SELECT id FROM bons_livraison WHERE facture_token IS NULL OR facture_token = ''");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows) {
        $upd = $db->prepare('UPDATE bons_livraison SET facture_token = :token WHERE id = :id');
        foreach ($rows as $row) {
            $upd->execute([
                'token' => bin2hex(random_bytes(32)),
                'id' => (int) $row['id'],
            ]);
        }
        echo count($rows) . " BL mis à jour avec un token facture.\n";
    }

    echo "Migration bons_livraison.facture_token terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
