<?php
/**
 * Exécute la migration: ajout du statut 'paye' à la table commandes
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

try {
    mig_expand_enum_column($db, 'commandes', 'statut', [
        'en_attente',
        'confirmee',
        'prise_en_charge',
        'en_preparation',
        'livraison_en_cours',
        'expediee',
        'livree',
        'paye',
        'annulee',
    ], 'en_attente');
    echo "Migration réussie: statut 'paye' disponible sur commandes.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
