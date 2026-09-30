<?php
/**
 * Journal des actions Invoice (création / suppression de factures) avec date et heure.
 * Usage : php migrations/run_add_admin_invoice_journal.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_invoice_journal` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `admin_id` INT NOT NULL,
  `action` ENUM('creation','suppression') NOT NULL,
  `bl_id` INT NULL,
  `numero_bl` VARCHAR(64) NOT NULL DEFAULT '',
  `client_label` VARCHAR(255) NOT NULL DEFAULT '',
  `date_action` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aij_admin_date` (`admin_id`, `date_action`),
  KEY `idx_aij_bl` (`bl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

mig_safe_exec($db, $sql, 'Table admin_invoice_journal');

$backfill = <<<'SQL'
INSERT INTO admin_invoice_journal (admin_id, action, bl_id, numero_bl, client_label, date_action)
SELECT b.admin_createur_id, 'creation', b.id, COALESCE(b.numero_bl, ''),
       COALESCE(c.raison_sociale, ''),
       COALESCE(b.date_creation, NOW())
FROM bons_livraison b
LEFT JOIN clients_b2b c ON c.id = b.client_b2b_id
WHERE b.admin_createur_id IS NOT NULL AND b.admin_createur_id > 0
  AND NOT EXISTS (
    SELECT 1 FROM admin_invoice_journal j
    WHERE j.action = 'creation' AND j.bl_id = b.id
  )
SQL;

try {
    $db->exec($backfill);
    echo "Backfill créations factures OK.\n";
} catch (PDOException $e) {
    echo "Backfill ignoré : " . $e->getMessage() . "\n";
}

mig_mark_applied($db, 'admin_invoice_journal', 'Journal actions factures Invoice (création / suppression)');
echo "Migration admin_invoice_journal terminée.\n";
