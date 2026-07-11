<?php
/**
 * Table pivot produits ↔ sous-catégories (plusieurs sous-catégories par fiche).
 *
 * Usage : php migrations/run_migrate_produits_sous_categories.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists_pssc(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec_pssc(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration produits_sous_categories…\n";

if (!table_exists_pssc($db, 'produits')) {
    echo "Table produits absente.\n";
    exit(1);
}

if (!table_exists_pssc($db, 'produits_sous_categories')) {
    safe_exec_pssc($db, "
        CREATE TABLE `produits_sous_categories` (
            `produit_id` INT(11) NOT NULL,
            `categorie_id` INT(11) NOT NULL,
            PRIMARY KEY (`produit_id`, `categorie_id`),
            KEY `idx_psc_categorie` (`categorie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table produits_sous_categories\n";
} else {
    echo "  table produits_sous_categories déjà présente.\n";
}

// Rétro-remplissage : une ligne par produit ayant déjà une categorie_id (feuille)
if (table_exists_pssc($db, 'produits_sous_categories')) {
    try {
        $n = (int) $db->query("
            SELECT COUNT(*) FROM `produits_sous_categories` psc
        ")->fetchColumn();
        if ($n === 0) {
            $ins = $db->exec("
                INSERT INTO `produits_sous_categories` (`produit_id`, `categorie_id`)
                SELECT p.`id`, p.`categorie_id` FROM `produits` p
                WHERE p.`categorie_id` IS NOT NULL AND p.`categorie_id` > 0
            ");
            if ($ins !== false) {
                echo "  + rétro-remplissage depuis produits.categorie_id\n";
            }
        }
    } catch (PDOException $e) {
        echo "  (rétro) " . $e->getMessage() . "\n";
    }
}

echo "OK.\n";
