<?php
/**
 * Ajoute l'action « modification » au journal des factures Invoice.
 * Usage : php migrations/run_alter_admin_invoice_journal_modification.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$sql = "ALTER TABLE `admin_invoice_journal`
    MODIFY COLUMN `action` ENUM('creation','modification','suppression') NOT NULL";

mig_safe_exec($db, $sql, 'ENUM action modification');
mig_mark_applied($db, 'admin_invoice_journal_modification', 'Journal factures : action modification');
echo "Migration admin_invoice_journal_modification terminée.\n";
