<?php
/**
 * Importe uniquement les DONNÉES depuis une sauvegarde phpMyAdmin complète
 * (ignore CREATE TABLE, ALTER, INDEX — la structure doit déjà exister en prod).
 *
 * Usage :
 *   php scripts/import_donnees_sauvegarde.php "ariaqqrw_sugar (4).sql"
 *   php scripts/import_donnees_sauvegarde.php "ariaqqrw_sugar (4).sql" --vider-avant
 *   php scripts/import_donnees_sauvegarde.php "ariaqqrw_sugar (4).sql" --export-only
 */
require_once __DIR__ . '/../conn/conn.php';

$source = $argv[1] ?? (__DIR__ . '/../ariaqqrw_sugar (4).sql');
$exportOnly = in_array('--export-only', $argv, true);
$viderAvant = in_array('--vider-avant', $argv, true);

if (!is_file($source)) {
    fwrite(STDERR, "Fichier introuvable : $source\n");
    exit(1);
}

function import_is_duplicate_error(PDOException $e) {
    $m = strtolower($e->getMessage());
    return (string) $e->getCode() === '23000'
        || strpos($m, 'duplicate') !== false
        || strpos($m, 'duplicata') !== false
        || strpos($m, 'déjà') !== false
        || strpos($m, 'deja') !== false
        || strpos($m, '1062') !== false;
}

function import_truncate_tables(PDO $db, array $tables) {
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $reversed = array_reverse($tables);
    foreach ($reversed as $table) {
        try {
            $db->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
            echo "  vidé : $table\n";
        } catch (PDOException $e) {
            echo "  ! $table : " . $e->getMessage() . "\n";
        }
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

/** Ordre respectant les clés étrangères */
$import_order = [
    'zones_livraison',
    'categories',
    'users',
    'admin',
    'section4_config',
    'slider',
    'trending_config',
    'videos',
    'contacts',
    'produits',
    'produits_variantes',
    'commandes_personnalisees',
    'commandes',
    'commande_produits',
    'factures',
    'factures_personnalisees',
    'panier',
    'stock_mouvements',
    'produits_visites',
    'fcm_tokens',
    'user_password_reset',
    'admin_password_reset',
];

function extract_insert_statements($content) {
    $by_table = [];
    $len = strlen($content);
    $pos = 0;

    while (($start = stripos($content, 'INSERT INTO', $pos)) !== false) {
        if (!preg_match('/INSERT INTO `([^`]+)`/i', $content, $m, 0, $start)) {
            $pos = $start + 11;
            continue;
        }
        $table = $m[1];
        $in_string = false;
        $end = null;

        for ($i = $start; $i < $len; $i++) {
            $ch = $content[$i];
            if ($in_string && $ch === '\\' && ($i + 1) < $len) {
                $i++;
                continue;
            }
            if ($ch === "'") {
                if ($in_string && ($i + 1) < $len && $content[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                $in_string = !$in_string;
                continue;
            }
            if ($ch === ';' && !$in_string) {
                $end = $i;
                break;
            }
        }

        if ($end === null) {
            break;
        }

        $sql = trim(substr($content, $start, $end - $start + 1));
        if (!isset($by_table[$table])) {
            $by_table[$table] = [];
        }
        $by_table[$table][] = $sql;
        $pos = $end + 1;
    }

    return $by_table;
}

$content = file_get_contents($source);
if ($content === false) {
    fwrite(STDERR, "Lecture impossible : $source\n");
    exit(1);
}

$inserts = extract_insert_statements($content);

echo "=== Extraction données sauvegarde ===\n";
echo "Fichier : $source\n";
echo 'Tables avec données : ' . count($inserts) . "\n\n";

$header = <<<SQL
-- Données uniquement (généré automatiquement)
-- Ne pas importer si les tables n'existent pas encore.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

SQL;

$body = '';
$ordered_tables = [];
foreach ($import_order as $table) {
    if (!empty($inserts[$table])) {
        $ordered_tables[] = $table;
    }
}
foreach (array_keys($inserts) as $table) {
    if (!in_array($table, $ordered_tables, true)) {
        $ordered_tables[] = $table;
    }
}

foreach ($ordered_tables as $table) {
    foreach ($inserts[$table] as $sql) {
        $body .= "\n-- Table `$table`\n" . $sql . "\n";
    }
}

$footer = "\nSET FOREIGN_KEY_CHECKS=1;\n";
$output = $header . $body . $footer;

$export_path = preg_replace('/\.sql$/i', '', $source) . '_donnees_seules.sql';
file_put_contents($export_path, $output);
echo "Fichier généré : $export_path\n";
echo "Instructions INSERT : " . array_sum(array_map('count', $inserts)) . "\n\n";

if ($exportOnly) {
    echo "Mode --export-only : import BDD non exécuté.\n";
    echo "Importez $export_path dans phpMyAdmin (base déjà créée).\n";
    exit(0);
}

if (!$db instanceof PDO) {
    fwrite(STDERR, "Connexion BDD impossible. Utilisez --export-only puis importez le fichier SQL en production.\n");
    exit(1);
}

$dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
echo "=== Import en base : $dbName ===\n\n";

$db->exec('SET NAMES utf8mb4');

if ($viderAvant) {
    echo "→ Vidage des tables concernées (--vider-avant)…\n";
    import_truncate_tables($db, $ordered_tables);
    echo "\n";
} else {
    echo "Astuce : si des doublons apparaissent, relancez avec --vider-avant\n\n";
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');

$ok = 0;
$skipped = 0;
$errors = 0;

foreach ($ordered_tables as $table) {
    if (!empty($inserts[$table])) {
        echo ">> $table (" . count($inserts[$table]) . " requête(s))\n";
    }
    foreach ($inserts[$table] ?? [] as $sql) {
        try {
            $db->exec($sql);
            $ok++;
        } catch (PDOException $e) {
            if (import_is_duplicate_error($e)) {
                echo "  — ignoré (doublon — relancez avec --vider-avant)\n";
                $skipped++;
                continue;
            }
            echo '  ! Erreur : ' . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

$db->exec('SET FOREIGN_KEY_CHECKS=1');

// Réaligner AUTO_INCREMENT
foreach ($ordered_tables as $table) {
    try {
        $q = $db->query("SELECT MAX(`id`) FROM `$table`");
        $max = $q ? $q->fetchColumn() : null;
        if ($max !== null && $max !== false && (int) $max > 0) {
            $db->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` AUTO_INCREMENT = ' . ((int) $max + 1));
        }
    } catch (PDOException $e) {
        // table sans colonne id
    }
}

echo "\n=== Terminé ===\n";
echo "OK : $ok | Ignorés : $skipped | Erreurs : $errors\n";

if ($errors > 0) {
    exit(1);
}

if ($ok === 0 && $skipped > 0) {
    echo "\nAucune ligne importée : les données sont déjà présentes.\n";
    echo "Relancez avec --vider-avant pour remplacer par la sauvegarde :\n";
    echo "  php scripts/import_donnees_sauvegarde.php " . escapeshellarg(basename($source)) . " --vider-avant\n";
    exit(1);
}

echo "\nEn production : importez plutôt le fichier :\n  $export_path\n";
echo "(phpMyAdmin → votre base → Importer → cocher « Permettre l'interruption… » si gros fichier)\n";
