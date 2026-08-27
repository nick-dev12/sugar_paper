<?php
/**
 * Migration: catalogue commandes personnalisées (dossiers + produits)
 * Exécuter: php migrations/run_add_cp_catalogue.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible.\n";
        exit(1);
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS cp_catalogue_dossiers (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nom VARCHAR(255) NOT NULL,
            position INT(11) NOT NULL DEFAULT 0,
            statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cp_dossier_position (position),
            KEY idx_cp_dossier_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table cp_catalogue_dossiers OK.\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS cp_catalogue_produits (
            id INT(11) NOT NULL AUTO_INCREMENT,
            dossier_id INT(11) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            image VARCHAR(255) NOT NULL,
            prix_min DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            prix_max DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            position INT(11) NOT NULL DEFAULT 0,
            statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cp_produit_dossier (dossier_id),
            KEY idx_cp_produit_position (position),
            KEY idx_cp_produit_statut (statut),
            CONSTRAINT fk_cp_produit_dossier FOREIGN KEY (dossier_id)
                REFERENCES cp_catalogue_dossiers(id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table cp_catalogue_produits OK.\n";

    $table_cp = $db->query("SHOW TABLES LIKE 'commandes_personnalisees'");
    if ($table_cp && $table_cp->fetchColumn()) {
        $col = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'catalogue_produit_id'");
        if (!$col || !$col->fetchColumn()) {
            $db->exec("
                ALTER TABLE commandes_personnalisees
                ADD COLUMN catalogue_produit_id INT(11) NULL DEFAULT NULL AFTER type_produit,
                ADD KEY idx_cp_catalogue_produit (catalogue_produit_id)
            ");
            try {
                $db->exec("
                    ALTER TABLE commandes_personnalisees
                    ADD CONSTRAINT fk_cp_catalogue_produit
                    FOREIGN KEY (catalogue_produit_id) REFERENCES cp_catalogue_produits(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
                ");
            } catch (PDOException $e) {
                echo "Colonne catalogue_produit_id ajoutee (FK optionnelle: " . $e->getMessage() . ").\n";
            }
            echo "Colonne catalogue_produit_id ajoutee a commandes_personnalisees.\n";
        } else {
            echo "Colonne catalogue_produit_id existe deja.\n";
        }
    }

    echo "Migration catalogue CP terminee.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
