<?php
/**
 * Migration : colonne users.inscription_checkout_invite
 * Comptes créés via le checkout invité (nom + téléphone).
 *
 * Usage : php migrations/run_add_users_inscription_checkout_invite.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

echo "=== Migration users.inscription_checkout_invite ===\n\n";

if (!mig_column_exists($db, 'users', 'inscription_checkout_invite')) {
    mig_safe_exec(
        $db,
        "ALTER TABLE users ADD COLUMN inscription_checkout_invite TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = compte créé via checkout invité' AFTER statut",
        'Colonne inscription_checkout_invite'
    );
} else {
    echo "  — Colonne inscription_checkout_invite (déjà présente)\n";
}

echo "\nMigration inscription_checkout_invite OK.\n";
