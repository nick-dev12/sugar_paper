<?php
/**
 * Rayons (categories_generales) : champs produit activables (poids, taille, mesure, couleur).
 * Produits : colonne mesure (texte libre, ex. dimensions).
 *
 * Usage : php migrations/run_migrate_categories_generales_attributs_produit.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function column_exists(PDO $db, string $table, string $col): bool {
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
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration attributs rayons + produits.mesure…\n";

if (table_exists($db, 'categories_generales')) {
    if (!column_exists($db, 'categories_generales', 'attr_poids')) {
        safe_exec($db, "
            ALTER TABLE `categories_generales`
            ADD COLUMN `attr_poids` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `attr_taille` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `attr_mesure` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `attr_couleur` TINYINT(1) NOT NULL DEFAULT 1
        ");
        echo "  + colonnes attr_* sur categories_generales\n";
    } else {
        echo "  colonnes attr_* déjà présentes.\n";
    }
} else {
    echo "  (categories_generales absente — ignoré)\n";
}

if (table_exists($db, 'produits')) {
    if (!column_exists($db, 'produits', 'mesure')) {
        safe_exec($db, "ALTER TABLE `produits` ADD COLUMN `mesure` VARCHAR(255) NULL DEFAULT NULL AFTER `unite`");
        echo "  + produits.mesure\n";
    } else {
        echo "  produits.mesure déjà présente.\n";
    }
} else {
    echo "  (produits absente — ignoré)\n";
}

echo "Terminé.\n";
