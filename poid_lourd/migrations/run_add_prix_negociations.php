<?php
/**
 * Migration: table prix_negociations
 * Executer: php migrations/run_add_prix_negociations.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

$create_table = "CREATE TABLE IF NOT EXISTS prix_negociations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    admin_id INT(11) NOT NULL,
    produit_id INT(11) NOT NULL,
    variante_id INT(11) NULL DEFAULT NULL,
    options_json TEXT NULL,
    options_hash VARCHAR(64) NOT NULL DEFAULT '',
    prix_reference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_propose_client DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_contre_vendeur DECIMAL(10,2) NULL DEFAULT NULL,
    prix_convenu DECIMAL(10,2) NULL DEFAULT NULL,
    statut ENUM('en_attente','acceptee','contre_proposee','refusee_finale','commandee') NOT NULL DEFAULT 'en_attente',
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_statut (admin_id, statut, date_maj),
    KEY idx_user_statut (user_id, statut, date_maj),
    KEY idx_produit (produit_id),
    KEY idx_user_produit_hash (user_id, produit_id, options_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$fks = [
    "ALTER TABLE prix_negociations ADD CONSTRAINT fk_prix_neg_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE",
    "ALTER TABLE prix_negociations ADD CONSTRAINT fk_prix_neg_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE",
    "ALTER TABLE prix_negociations ADD CONSTRAINT fk_prix_neg_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE CASCADE",
];

try {
    $db->exec($create_table);
    echo "Table prix_negociations creee ou deja presente.\n";
} catch (PDOException $e) {
    echo "Erreur CREATE TABLE: " . $e->getMessage() . "\n";
    exit(1);
}

foreach ($fks as $fk_sql) {
    try {
        $db->exec($fk_sql);
        echo "FK ajoutee.\n";
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'already exists') !== false) {
            continue;
        }
        echo "Note FK: " . $e->getMessage() . "\n";
    }
}

$check = $db->query("SHOW TABLES LIKE 'prix_negociations'");
if ($check && $check->fetchColumn()) {
    echo "Verification OK: table prix_negociations disponible.\n";
} else {
    echo "Erreur: table prix_negociations introuvable apres migration.\n";
    exit(1);
}

echo "Migration prix_negociations terminee.\n";
