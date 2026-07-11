<?php
/**
 * Migration : mode_livraison sur commandes (livraison | retrait).
 * Usage : php migrations/run_add_mode_livraison_commandes.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

$q = $db->prepare("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes' AND COLUMN_NAME = 'mode_livraison'
");
$q->execute();
if ((int) $q->fetchColumn() > 0) {
    echo "commandes.mode_livraison déjà présente.\n";
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/add_mode_livraison_commandes.sql');
if ($sql === false) {
    echo "Fichier SQL introuvable.\n";
    exit(1);
}

try {
    $db->exec($sql);
    echo "commandes.mode_livraison ajoutée.\n";
} catch (PDOException $e) {
    echo 'ERREUR : ' . $e->getMessage() . "\n";
    exit(1);
}