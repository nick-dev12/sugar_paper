<?php
/**
 * Corrige la FK livreur_positions → livreurs.
 *
 * Le suivi web admin stocke admin.id dans livreur_id (commandes + positions).
 * La contrainte fk_livreur_positions_livreur bloque l'INSERT si admin.id n'existe pas dans livreurs.
 *
 * Usage : php migrations/run_fix_livreur_positions_fk.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

echo "=== Fix FK livreur_positions (suivi web admin) ===\n";

$q = $db->prepare("
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'livreur_positions'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
");
$q->execute();
$constraints = $q->fetchAll(PDO::FETCH_COLUMN);

if ($constraints === []) {
    echo "  Aucune FK sur livreur_positions — rien à faire.\n";
} else {
    foreach ($constraints as $name) {
        try {
            $db->exec('ALTER TABLE `livreur_positions` DROP FOREIGN KEY `' . str_replace('`', '``', $name) . '`');
            echo "  OK : FK supprimée ($name)\n";
        } catch (PDOException $e) {
            echo "  ERREUR ($name) : " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

try {
    $db->exec("
        ALTER TABLE `livreur_positions`
        MODIFY COLUMN `livreur_id` INT NOT NULL
        COMMENT 'admin.id (web) ou livreurs.id (app mobile)'
    ");
    echo "  OK : commentaire livreur_id mis à jour\n";
} catch (PDOException $e) {
    echo "  Note commentaire : " . $e->getMessage() . "\n";
}

echo "=== Terminé ===\n";
