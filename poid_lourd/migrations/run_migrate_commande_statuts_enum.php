<?php
/**
 * Aligne l'ENUM commandes.statut (livraison_en_cours, paye, prise_en_charge, etc.)
 *
 * CLI : php migrations/run_migrate_commande_statuts_enum.php
 * Web (admin connecté) : https://votre-site/migrations/run_migrate_commande_statuts_enum.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    require_once $root . '/includes/session_admin.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Accès refusé — connectez-vous en tant qu'administrateur.\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function cse_out(string $line): void
{
    echo $line . "\n";
}

if (!$db) {
    cse_out('Erreur : connexion BDD indisponible.');
    exit(1);
}

cse_out('Migration ENUM commandes.statut…');

$target_enum = [
    'en_attente',
    'confirmee',
    'prise_en_charge',
    'en_preparation',
    'livraison_en_cours',
    'expediee',
    'livree',
    'paye',
    'annulee',
];

$current = [];
try {
    $stmt = $db->query("SHOW COLUMNS FROM `commandes` LIKE 'statut'");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $type = (string) ($row['Type'] ?? '');
    if (preg_match_all("/'([^']+)'/", $type, $m)) {
        $current = $m[1];
    }
} catch (PDOException $e) {
    cse_out('Erreur lecture ENUM : ' . $e->getMessage());
    exit(1);
}

$missing = array_diff($target_enum, $current);
if ($missing === [] && $current !== []) {
    cse_out('  Déjà à jour — aucune modification nécessaire.');
    cse_out('Valeurs : ' . implode(', ', $current));
    exit(0);
}

cse_out('  ENUM actuel : ' . ($current ? implode(', ', $current) : '(vide)'));
if ($missing !== []) {
    cse_out('  Valeurs manquantes : ' . implode(', ', $missing));
}

$sql = "
ALTER TABLE `commandes`
MODIFY COLUMN `statut` ENUM(
    'en_attente',
    'confirmee',
    'prise_en_charge',
    'en_preparation',
    'livraison_en_cours',
    'expediee',
    'livree',
    'paye',
    'annulee'
) NOT NULL DEFAULT 'en_attente'
";

try {
    if (in_array('en_cours_expedition', $current, true)) {
        $db->exec("UPDATE commandes SET statut = 'livraison_en_cours' WHERE statut = 'en_cours_expedition'");
        cse_out('  OK — en_cours_expedition → livraison_en_cours');
    }
    $db->exec($sql);
    cse_out('  OK — ENUM commandes.statut mis à jour.');
    cse_out('Valeurs : ' . implode(', ', $target_enum));
} catch (PDOException $e) {
    cse_out('Erreur : ' . $e->getMessage());
    exit(1);
}

cse_out('Terminé. Vous pouvez réessayer le changement de statut dans l\'admin.');
