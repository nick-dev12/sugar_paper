<?php
/**
 * Migration: Ajouter colonne token à factures (accès public sécurisé)
 * Exécuter: php migrations/run_add_factures_token.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

try {
    mig_add_column_if_missing($db, 'factures', 'token', 'VARCHAR(64) NULL DEFAULT NULL AFTER `date_creation`');

    if (!mig_index_exists($db, 'factures', 'idx_token') && !mig_index_exists($db, 'factures', 'token')) {
        mig_safe_exec($db, 'ALTER TABLE `factures` ADD UNIQUE KEY `idx_token` (`token`)', 'index unique factures.token');
    }

    $stmt = $db->query("SELECT id FROM factures WHERE token IS NULL OR token = ''");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $upd = $db->prepare('UPDATE factures SET token = :token WHERE id = :id');
    foreach ($rows as $r) {
        $upd->execute(['token' => bin2hex(random_bytes(32)), 'id' => $r['id']]);
    }
    if (count($rows) > 0) {
        echo count($rows) . " facture(s) mise(s) à jour avec un token.\n";
    }
    echo "Migration factures.token terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
