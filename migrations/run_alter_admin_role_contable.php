<?php
/**
 * Migration : ajout du rôle contable sur admin.role (élargissement ENUM uniquement).
 * Ne supprime aucune valeur existante (utilisateur, livreur, etc.).
 *
 * Usage : php migrations/run_alter_admin_role_contable.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

echo "=== Migration rôle contable ===\n\n";

$roles = [
    'admin',
    'utilisateur',
    'livreur',
    'gestion_stock',
    'commercial',
    'commercial_general',
    'informaticien',
    'developpeur',
    'comptabilite',
    'contable',
    'rh',
    'caissier',
];

try {
    mig_expand_enum_column($db, 'admin', 'role', $roles, 'admin');
    echo "\nMigration contable OK.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
