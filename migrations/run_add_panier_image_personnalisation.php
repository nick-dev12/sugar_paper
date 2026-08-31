<?php
/**
 * Migration: image de personnalisation sur lignes panier
 * Exécuter: php migrations/run_add_panier_image_personnalisation.php
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $stmt = $db->query("SHOW COLUMNS FROM panier LIKE 'image_personnalisation'");
    if ($stmt && $stmt->rowCount() > 0) {
        echo "La colonne image_personnalisation existe déjà.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE panier
        ADD COLUMN image_personnalisation VARCHAR(512) NULL DEFAULT NULL
        AFTER prix_unitaire
    ");
    echo "Colonne image_personnalisation ajoutée à panier.\n";
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
