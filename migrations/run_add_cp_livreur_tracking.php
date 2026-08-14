<?php
/**
 * Livraison GPS des commandes personnalisées (colonnes + tokens/positions).
 * Usage : php migrations/run_add_cp_livreur_tracking.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function cp_livreur_mig_column_exists(PDO $db, string $table, string $col): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function cp_livreur_mig_table_exists(PDO $db, string $table): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function cp_livreur_mig_exec(PDO $db, string $sql, string $label): void
{
    try {
        $db->exec($sql);
        echo "  OK : $label\n";
    } catch (PDOException $e) {
        echo "  ERREUR ($label) : " . $e->getMessage() . "\n";
    }
}

echo "=== Migration livraison commandes personnalisées ===\n";

if (!cp_livreur_mig_table_exists($db, 'commandes_personnalisees')) {
    echo "Table commandes_personnalisees absente.\n";
    exit(1);
}

$cp_columns = [
    'livreur_id' => "INT NULL DEFAULT NULL COMMENT 'Livreur assigné (admin.id)' AFTER `notes_admin`",
    'adresse_livraison' => "TEXT NULL DEFAULT NULL COMMENT 'Adresse livraison GPS' AFTER `livreur_id`",
    'delivery_latitude' => "DECIMAL(10,8) NULL DEFAULT NULL AFTER `adresse_livraison`",
    'delivery_longitude' => "DECIMAL(11,8) NULL DEFAULT NULL AFTER `delivery_latitude`",
    'tracking_active' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `delivery_longitude`",
    'tracking_started_at' => "DATETIME NULL DEFAULT NULL AFTER `tracking_active`",
    'livraison_arrivee_at' => "DATETIME NULL DEFAULT NULL AFTER `tracking_started_at`",
    'livraison_terminee_at' => "DATETIME NULL DEFAULT NULL AFTER `livraison_arrivee_at`",
    'delivery_countdown_initial_sec' => "INT NULL DEFAULT NULL AFTER `livraison_terminee_at`",
    'delivery_countdown_remaining_sec' => "INT NULL DEFAULT NULL AFTER `delivery_countdown_initial_sec`",
    'delivery_countdown_running_at' => "DATETIME NULL DEFAULT NULL AFTER `delivery_countdown_remaining_sec`",
];

foreach ($cp_columns as $col => $definition) {
    if (!cp_livreur_mig_column_exists($db, 'commandes_personnalisees', $col)) {
        cp_livreur_mig_exec($db, "ALTER TABLE `commandes_personnalisees` ADD COLUMN `$col` $definition", "commandes_personnalisees.$col");
    } else {
        echo "  commandes_personnalisees.$col déjà présente.\n";
    }
}

cp_livreur_mig_exec(
    $db,
    "ALTER TABLE `commandes_personnalisees`
     MODIFY COLUMN `statut` ENUM(
        'en_attente','confirmee','en_preparation','devis_envoye','acceptee',
        'refusee','terminee','annulee','livraison_en_cours','livree'
     ) NOT NULL DEFAULT 'en_attente'",
    'statuts livraison_en_cours / livree'
);

$idx = $db->prepare("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes_personnalisees' AND INDEX_NAME = 'idx_cp_livreur'
");
$idx->execute();
if ((int) $idx->fetchColumn() === 0) {
    cp_livreur_mig_exec($db, "ALTER TABLE `commandes_personnalisees` ADD KEY `idx_cp_livreur` (`livreur_id`)", 'index idx_cp_livreur');
}

if (cp_livreur_mig_table_exists($db, 'tracking_watch_tokens') && !cp_livreur_mig_column_exists($db, 'tracking_watch_tokens', 'cp_id')) {
    cp_livreur_mig_exec(
        $db,
        "ALTER TABLE `tracking_watch_tokens` ADD COLUMN `cp_id` INT NULL DEFAULT NULL COMMENT 'Commande personnalisée' AFTER `bl_id`",
        'tracking_watch_tokens.cp_id'
    );
    cp_livreur_mig_exec($db, "ALTER TABLE `tracking_watch_tokens` ADD KEY `idx_tracking_watch_cp` (`cp_id`)", 'index idx_tracking_watch_cp');
}

if (cp_livreur_mig_table_exists($db, 'livreur_positions') && !cp_livreur_mig_column_exists($db, 'livreur_positions', 'cp_id')) {
    cp_livreur_mig_exec(
        $db,
        "ALTER TABLE `livreur_positions` ADD COLUMN `cp_id` INT NULL DEFAULT NULL COMMENT 'Commande personnalisée' AFTER `bl_id`",
        'livreur_positions.cp_id'
    );
    cp_livreur_mig_exec($db, "ALTER TABLE `livreur_positions` ADD KEY `idx_livreur_positions_cp` (`cp_id`)", 'index idx_livreur_positions_cp');
}

echo "=== Migration CP livreur terminée ===\n";
