<?php
/**
 * Migration: image de personnalisation sur lignes commande_produits
 * Exécuter: php migrations/run_add_commande_produits_image_personnalisation.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $stmt = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'image_personnalisation'");
    if ($stmt && $stmt->rowCount() > 0) {
        echo "La colonne image_personnalisation existe déjà sur commande_produits.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE commande_produits
        ADD COLUMN image_personnalisation VARCHAR(512) NULL DEFAULT NULL
        AFTER taille
    ");
    echo "Colonne image_personnalisation ajoutée à commande_produits.\n";
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
