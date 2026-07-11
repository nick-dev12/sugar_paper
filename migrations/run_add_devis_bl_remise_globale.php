<?php
/**
 * Colonnes remise_globale_pct sur devis et bons_livraison.
 * php migrations/run_add_devis_bl_remise_globale.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$cols = [
    'devis' => "ALTER TABLE `devis` ADD COLUMN `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total' AFTER `montant_total`",
    'bons_livraison' => "ALTER TABLE `bons_livraison` ADD COLUMN `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Réduction en % sur le total HT' AFTER `total_ht`",
];

try {
    $db->exec('SET NAMES utf8mb4');
    foreach ($cols as $table => $sql) {
        try {
            $st = $db->query("SHOW COLUMNS FROM `$table` LIKE 'remise_globale_pct'");
            if ($st && $st->fetch()) {
                echo "— $table.remise_globale_pct existe déjà\n";
                continue;
            }
            $db->exec($sql);
            echo "+ $table.remise_globale_pct\n";
        } catch (PDOException $e) {
            $m = strtolower($e->getMessage());
            if (strpos($m, 'duplicate') !== false || strpos($m, 'déjà') !== false) {
                echo "— $table.remise_globale_pct (déjà présent)\n";
            } else {
                throw $e;
            }
        }
    }
    echo "Migration remise globale terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
