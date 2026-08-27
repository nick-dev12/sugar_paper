<?php
/**
 * Migration: section d'accueil sur les produits
 * Exécuter: php migrations/run_add_produits_section_accueil.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible depuis le PHP CLI.\n";
        exit(1);
    }

    $table_exists_stmt = $db->query("SHOW TABLES LIKE 'produits'");
    if (!$table_exists_stmt || !$table_exists_stmt->fetchColumn()) {
        echo "La table produits n'existe pas.\n";
        exit;
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'section_accueil'");
    if ($column_stmt && $column_stmt->fetchColumn()) {
        echo "La colonne section_accueil existe deja.\n";
        exit;
    }

    $db->exec("
        ALTER TABLE produits
        ADD COLUMN section_accueil ENUM(
            'cake_topper',
            'photo_impression',
            'outils_patisserie'
        ) NULL DEFAULT NULL AFTER statut
    ");

    echo "Colonne section_accueil ajoutee a produits.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
