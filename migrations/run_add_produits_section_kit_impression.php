<?php
/**
 * Migration: section accueil « kit_impression » sur produits
 * Exécuter: php migrations/run_add_produits_section_kit_impression.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible.\n";
        exit(1);
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'section_accueil'");
    if (!$column_stmt || !$column_stmt->fetchColumn()) {
        echo "La colonne section_accueil n'existe pas.\n";
        exit(1);
    }

    $db->exec("
        ALTER TABLE produits
        MODIFY COLUMN section_accueil ENUM(
            'cake_topper',
            'photo_impression',
            'outils_patisserie',
            'decoration_gateau',
            'cupcakes',
            'contours_gateau',
            'disques_cocktail',
            'habillage_papier_azyme',
            'kit_impression'
        ) NULL DEFAULT NULL
    ");

    echo "Valeur kit_impression ajoutee a section_accueil.\n";
    exit(0);
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
