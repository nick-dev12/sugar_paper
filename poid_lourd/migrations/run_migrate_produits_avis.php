<?php
/**
 * Avis clients sur produits commandés (notes 0.33 à 5.00).
 *
 * Usage : php migrations/run_migrate_produits_avis.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function pa_table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

echo "Migration produits_avis…\n";

if (!pa_table_exists($db, 'produits_avis')) {
    $db->exec("
        CREATE TABLE `produits_avis` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `produit_id` INT(11) NOT NULL,
            `commande_id` INT(11) NOT NULL,
            `note` DECIMAL(4,2) NOT NULL COMMENT 'Note de 0.33 à 5.00',
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_produit_commande` (`user_id`, `produit_id`, `commande_id`),
            KEY `idx_produit_id` (`produit_id`),
            KEY `idx_commande_id` (`commande_id`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table produits_avis\n";
} else {
    echo "  table produits_avis déjà présente.\n";
}

if (!pa_table_exists($db, 'produits_avis_popup_snooze')) {
    $db->exec("
        CREATE TABLE `produits_avis_popup_snooze` (
            `user_id` INT(11) NOT NULL,
            `snooze_until` DATETIME NOT NULL,
            `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table produits_avis_popup_snooze\n";
} else {
    echo "  table produits_avis_popup_snooze déjà présente.\n";
}

echo "Terminé.\n";
