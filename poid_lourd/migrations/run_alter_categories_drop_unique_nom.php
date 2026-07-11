<?php
/**
 * Supprime l'index UNIQUE global sur categories.nom pour permettre le même libellé
 * sous des catégories générales différentes (sous-catégories plateforme).
 * Usage : php migrations/run_alter_categories_drop_unique_nom.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

try {
    $st = $db->query("SHOW INDEX FROM `categories` WHERE Key_name = 'idx_nom'");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!empty($rows)) {
        $db->exec('ALTER TABLE `categories` DROP INDEX `idx_nom`');
        echo "Index idx_nom supprimé.\n";
    } else {
        echo "Index idx_nom absent (déjà migré).\n";
    }
} catch (PDOException $e) {
    echo 'Erreur : ' . $e->getMessage() . "\n";
    exit(1);
}

echo "Terminé.\n";
