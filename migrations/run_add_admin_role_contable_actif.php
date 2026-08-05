<?php
/**
 * Migration : réactive le rôle contable (Comptable) dans admin.role ENUM.
 * Conserve admin / utilisateur / livreur.
 *
 * Usage : php migrations/run_add_admin_role_contable_actif.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

echo "=== Migration rôle contable (actif) ===\n\n";

$roles = [
    'admin',
    'utilisateur',
    'livreur',
    'contable',
];

try {
    mig_expand_enum_column($db, 'admin', 'role', $roles, 'utilisateur');
    echo "\nMigration contable actif OK.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
