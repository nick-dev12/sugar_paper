<?php
/**
 * Migration — redirections anciens slugs boutique (renommage vendeur).
 *
 * Usage : php migrations/run_migrate_boutique_slug_redirects.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function bsr_table_exists(PDO $db, string $table): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

echo "Migration redirections slugs boutique…\n";

if (!bsr_table_exists($db, 'boutique_slug_redirects')) {
    $db->exec("
        CREATE TABLE boutique_slug_redirects (
            old_slug VARCHAR(191) NOT NULL,
            admin_id INT NOT NULL,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (old_slug),
            KEY idx_bsr_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table boutique_slug_redirects\n";
} else {
    echo "  = table boutique_slug_redirects déjà présente\n";
}

echo "Terminé.\n";
