<?php
/**
 * Colonne slider.admin_id pour slides dédiés à une boutique.
 * Usage : php migrations/run_migrate_slider_admin_id.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function col_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

echo "Migration slider.admin_id…\n";

if (!col_exists($db, 'slider', 'admin_id')) {
    try {
        $db->exec("
            ALTER TABLE `slider`
              ADD COLUMN `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Vendeur (admin.id) pour vitrine boutique' AFTER `id`,
              ADD KEY `idx_slider_admin_id` (`admin_id`)
        ");
        echo "OK : colonne admin_id ajoutée.\n";
    } catch (PDOException $e) {
        echo "ERREUR : " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Déjà présent : admin_id ignoré.\n";
}

exit(0);
