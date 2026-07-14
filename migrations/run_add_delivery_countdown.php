<?php
/**
 * Migration : compte à rebours livraison (pause / reprise / retard).
 *
 * Usage : php migrations/run_add_delivery_countdown.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function countdown_mig_column_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function countdown_mig_table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function countdown_mig_safe_exec(PDO $db, string $sql, string $label): bool {
    try {
        $db->exec($sql);
        echo "  OK : $label\n";
        return true;
    } catch (PDOException $e) {
        echo "  ERREUR ($label) : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "=== Migration compte à rebours livraison ===\n";

$columns = [
    'delivery_countdown_initial_sec' => "INT NULL DEFAULT NULL COMMENT 'Durée ETA initiale (s)' AFTER `tracking_started_at`",
    'delivery_countdown_remaining_sec' => "INT NULL DEFAULT NULL COMMENT 'Temps restant stocké (s)' AFTER `delivery_countdown_initial_sec`",
    'delivery_countdown_running_at' => "DATETIME NULL DEFAULT NULL COMMENT 'Reprise du compte à rebours' AFTER `delivery_countdown_remaining_sec`",
];

foreach (['commandes', 'bons_livraison'] as $table) {
    if ($table === 'bons_livraison' && !countdown_mig_table_exists($db, $table)) {
        echo "  Table $table absente, ignorée.\n";
        continue;
    }
    foreach ($columns as $col => $definition) {
        if (!countdown_mig_column_exists($db, $table, $col)) {
            countdown_mig_safe_exec($db, "ALTER TABLE `$table` ADD COLUMN `$col` $definition", "$table.$col");
        } else {
            echo "  $table.$col déjà présente.\n";
        }
    }
}

echo "=== Migration terminée ===\n";
