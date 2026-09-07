<?php
/**
 * Migration: section accueil « cupcakes » sur produits
 * Exécuter: php migrations/run_add_produits_section_cupcakes.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible depuis le PHP CLI.\n";
        exit(1);
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'section_accueil'");
    if (!$column_stmt || !$column_stmt->fetchColumn()) {
        echo "La colonne section_accueil n'existe pas. Executez d'abord run_add_produits_section_accueil.php\n";
        exit(1);
    }

    $db->exec("
        ALTER TABLE produits
        MODIFY COLUMN section_accueil ENUM(
            'cake_topper',
            'photo_impression',
            'outils_patisserie',
            'decoration_gateau',
            'cupcakes'
        ) NULL DEFAULT NULL
    ");

    echo "Valeur cupcakes ajoutee a section_accueil.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
