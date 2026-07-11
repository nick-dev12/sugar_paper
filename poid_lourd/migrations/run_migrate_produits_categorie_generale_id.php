<?php
/**
 * Colonne produits.categorie_generale_id (lien produit → categories_generales).
 * Usage : php migrations/run_migrate_produits_categorie_generale_id.php
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

function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT SQL : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration produits.categorie_generale_id…\n";

if (!col_exists($db, 'produits', 'categorie_generale_id')) {
    safe_exec($db, "
        ALTER TABLE `produits` ADD COLUMN `categorie_generale_id` INT(11) NULL DEFAULT NULL
        COMMENT 'Rayon plateforme (categories_generales.id)' AFTER `categorie_id`
    ");
    echo "  + produits.categorie_generale_id\n";
}

if (col_exists($db, 'produits', 'categorie_generale_id')
    && col_exists($db, 'categories', 'categorie_generale_id')) {
    safe_exec($db, "
        UPDATE `produits` p
        INNER JOIN `categories` c ON c.`id` = p.`categorie_id`
        SET p.`categorie_generale_id` = c.`categorie_generale_id`
        WHERE (p.`categorie_generale_id` IS NULL OR p.`categorie_generale_id` = 0)
          AND c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0
    ");
    echo "  + rattrapage depuis categories.categorie_generale_id\n";
}

echo "Terminé.\n";
