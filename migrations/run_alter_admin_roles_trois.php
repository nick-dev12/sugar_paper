<?php
/**
 * Migration : trois rôles admin uniquement (admin, utilisateur, livreur).
 *
 * Usage : php migrations/run_alter_admin_roles_trois.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function roles_trois_safe_exec(PDO $db, string $sql, string $label): bool {
    try {
        $db->exec($sql);
        echo "  OK : $label\n";
        return true;
    } catch (PDOException $e) {
        echo "  ERREUR ($label) : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "=== Migration rôles admin (3 rôles) ===\n";

// Étape 1 : élargir l'ENUM pour accepter utilisateur + livreur
roles_trois_safe_exec($db, "
ALTER TABLE `admin` MODIFY COLUMN `role` ENUM(
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
    'caissier'
) NOT NULL DEFAULT 'utilisateur'
", 'élargissement ENUM');

// Étape 2 : convertir les anciens rôles
roles_trois_safe_exec($db, "
UPDATE `admin`
SET `role` = 'utilisateur'
WHERE `role` NOT IN ('admin', 'livreur', 'utilisateur')
", 'conversion anciens rôles → utilisateur');

roles_trois_safe_exec($db, "
UPDATE `admin`
SET `role` = 'utilisateur'
WHERE `role` = 'gestion_stock'
", 'conversion gestion_stock → utilisateur');

// Étape 3 : ENUM final à 3 valeurs
roles_trois_safe_exec($db, "
ALTER TABLE `admin` MODIFY COLUMN `role` ENUM(
    'admin',
    'utilisateur',
    'livreur'
) NOT NULL DEFAULT 'utilisateur'
", 'ENUM final admin / utilisateur / livreur');

echo "=== Migration terminée ===\n";
