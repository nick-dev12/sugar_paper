<?php
/**
 * Migration: colonne produits.prix_negociable
 * Executer: php migrations/run_add_prix_negociable.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/add_prix_negociable.sql');
if ($sql === false || trim($sql) === '') {
    echo "Erreur: fichier add_prix_negociable.sql introuvable.\n";
    exit(1);
}

try {
    $db->exec($sql);
    echo "Colonne prix_negociable ajoutee ou deja presente.\n";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Colonne prix_negociable deja presente.\n";
    } else {
        echo "Erreur migration: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$check = $db->query("SHOW COLUMNS FROM produits LIKE 'prix_negociable'");
if ($check && $check->fetch(PDO::FETCH_ASSOC)) {
    echo "Verification OK: produits.prix_negociable disponible.\n";
} else {
    echo "Erreur: colonne prix_negociable introuvable apres migration.\n";
    exit(1);
}

echo "Migration prix_negociable terminee.\n";
