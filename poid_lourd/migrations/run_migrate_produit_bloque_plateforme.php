<?php
/**
 * Migration : statut produit « bloque » + métadonnées modération plateforme
 */
require_once dirname(__DIR__) . '/conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

echo "Migration blocage produits plateforme…\n";

function pb_col_exists(PDO $db, $table, $col) {
    $st = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
}

try {
    $st = $db->query("SHOW COLUMNS FROM produits LIKE 'statut'");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $type = (string) ($row['Type'] ?? '');
    if (stripos($type, 'bloque') === false) {
        $db->exec("
            ALTER TABLE `produits`
            MODIFY COLUMN `statut` ENUM('actif','inactif','rupture_stock','bloque') NOT NULL DEFAULT 'actif'
        ");
        echo "  + produits.statut (valeur bloque)\n";
    }

    if (!pb_col_exists($db, 'produits', 'bloque_motif')) {
        $db->exec("ALTER TABLE `produits` ADD COLUMN `bloque_motif` TEXT NULL DEFAULT NULL AFTER `statut`");
        echo "  + produits.bloque_motif\n";
    }
    if (!pb_col_exists($db, 'produits', 'bloque_champs')) {
        $db->exec("ALTER TABLE `produits` ADD COLUMN `bloque_champs` VARCHAR(32) NULL DEFAULT NULL AFTER `bloque_motif`");
        echo "  + produits.bloque_champs\n";
    }
    if (!pb_col_exists($db, 'produits', 'bloque_nom_ref')) {
        $db->exec("ALTER TABLE `produits` ADD COLUMN `bloque_nom_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `bloque_champs`");
        echo "  + produits.bloque_nom_ref\n";
    }
    if (!pb_col_exists($db, 'produits', 'bloque_image_ref')) {
        $db->exec("ALTER TABLE `produits` ADD COLUMN `bloque_image_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `bloque_nom_ref`");
        echo "  + produits.bloque_image_ref\n";
    }
    if (!pb_col_exists($db, 'produits', 'bloque_date')) {
        $db->exec("ALTER TABLE `produits` ADD COLUMN `bloque_date` DATETIME NULL DEFAULT NULL AFTER `bloque_image_ref`");
        echo "  + produits.bloque_date\n";
    }

    echo "Terminé.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
