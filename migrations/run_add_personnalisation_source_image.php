<?php
/**
 * Image source importée (fichier client) distincte de l'aperçu composé.
 * Exécuter: php migrations/run_add_personnalisation_source_image.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$tables = ['panier', 'commande_produits'];

try {
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE 'image_personnalisation_source'");
        if ($stmt && $stmt->rowCount() > 0) {
            echo "Colonne image_personnalisation_source déjà présente sur {$table}.\n";
            continue;
        }

        $db->exec("
            ALTER TABLE {$table}
            ADD COLUMN image_personnalisation_source VARCHAR(512) NULL DEFAULT NULL
            AFTER image_personnalisation
        ");
        echo "Colonne image_personnalisation_source ajoutée à {$table}.\n";
    }
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
