<?php
/**
 * Migration: métadonnées personnalisation (format A4/A3, forme, dimensions cm)
 * Exécuter: php migrations/run_add_personnalisation_meta.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$tables = ['panier', 'commande_produits'];

try {
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE 'personnalisation_meta'");
        if ($stmt && $stmt->rowCount() > 0) {
            echo "Colonne personnalisation_meta déjà présente sur {$table}.\n";
            continue;
        }

        $after = $table === 'panier' ? 'image_personnalisation' : 'image_personnalisation';
        $db->exec("
            ALTER TABLE {$table}
            ADD COLUMN personnalisation_meta TEXT NULL DEFAULT NULL
            AFTER {$after}
        ");
        echo "Colonne personnalisation_meta ajoutée à {$table}.\n";
    }
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
