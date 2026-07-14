<?php
/**
 * Migration : marqueur livraison terminée (factures B2B).
 *
 * Usage : php migrations/run_add_livraison_terminee.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function livraison_terminee_mig_column_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function livraison_terminee_mig_table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

foreach (['commandes', 'bons_livraison'] as $table) {
    if (!livraison_terminee_mig_table_exists($db, $table)) {
        echo "Table $table absente — ignorée.\n";
        continue;
    }
    if (livraison_terminee_mig_column_exists($db, $table, 'livraison_terminee_at')) {
        echo "$table.livraison_terminee_at déjà présente.\n";
        continue;
    }
    $after = $table === 'commandes' ? ' AFTER `tracking_started_at`' : ' AFTER `tracking_started_at`';
    if (!livraison_terminee_mig_column_exists($db, $table, 'tracking_started_at')) {
        $after = '';
    }
    $db->exec(
        "ALTER TABLE `$table` ADD COLUMN `livraison_terminee_at` DATETIME NULL DEFAULT NULL"
        . " COMMENT 'Livraison GPS terminée par le livreur'"
        . $after
    );
    echo "Colonne $table.livraison_terminee_at ajoutée.\n";
}

echo "Migration livraison_terminee_at terminée.\n";
