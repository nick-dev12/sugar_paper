<?php
/**
 * Exécute toutes les migrations en ordre (ajouts uniquement, sans suppression de données).
 * Termine par une réparation automatique du schéma + scan de vérification.
 *
 * Usage :
 *   php migrations/run_all_migrations.php
 *   php migrations/run_all_migrations.php --force
 *   php migrations/run_all_migrations.php --continue
 *   php migrations/run_all_migrations.php --only=livreur_tracking
 *   php migrations/run_all_migrations.php --from=invoice_bl
 *   php migrations/run_all_migrations.php --no-repair
 *
 * Scripts exclus volontairement (destructifs) :
 *   - run_remove_stock_articles.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$opts = mig_parse_cli_args($argv ?? []);
$no_repair = in_array('--no-repair', $argv ?? [], true);
$db = mig_connect();
$migrations_dir = __DIR__;
$manifest = require $migrations_dir . '/migrations_manifest.php';

mig_ensure_tracking_table($db);

echo "=== Migrations BDD — ajouts uniquement ===\n";
echo 'Base : ' . $db->query('SELECT DATABASE()')->fetchColumn() . "\n";
if ($opts['dry_run']) {
    echo "Mode : simulation (--dry-run)\n";
}
if ($opts['force']) {
    echo "Mode : forcer ré-exécution (--force)\n";
}
if ($opts['continue']) {
    echo "Mode : continuer malgré les erreurs (--continue)\n";
}
echo "\n";

$started = false;
$ran = 0;
$skipped = 0;
$errors = 0;
$failed_ids = [];

foreach ($manifest as $step) {
    $id = $step['id'];
    $label = $step['label'];
    $script = $step['script'];
    $path = $migrations_dir . '/' . $script;

    if ($opts['only'] !== '' && $opts['only'] !== $id) {
        continue;
    }
    if ($opts['from'] !== '' && !$started) {
        if ($id === $opts['from']) {
            $started = true;
        } else {
            continue;
        }
    }

    if (!is_file($path)) {
        echo "! [$id] Script absent : $script\n";
        $errors++;
        $failed_ids[] = $id;
        continue;
    }

    $already = mig_is_applied($db, $id);
    if ($already && !$opts['force'] && $opts['only'] === '' && $opts['from'] === '') {
        echo "— [$id] $label (déjà appliqué)\n";
        $skipped++;
        continue;
    }

    echo ">> [$id] $label\n";
    echo "   $script\n";

    if ($opts['dry_run']) {
        echo "   (simulation — non exécuté)\n\n";
        $ran++;
        continue;
    }

    $code = mig_run_php_script($path);
    if ($code !== 0) {
        echo "!! Échec [$id] code $code\n\n";
        $errors++;
        $failed_ids[] = $id;
        if (!$opts['continue'] && $opts['only'] === '') {
            echo "Arrêt sur erreur. Relancez avec --continue ou --from=$id\n";
            echo "Une réparation automatique sera tentée ci-dessous.\n\n";
            break;
        }
        continue;
    }

    mig_mark_applied($db, $id, $label);
    echo "OK [$id] enregistré\n\n";
    $ran++;
}

echo "=== Résumé migrations ===\n";
echo "Exécutées : $ran | Ignorées (déjà fait) : $skipped | Erreurs : $errors\n";
if (!empty($failed_ids)) {
    echo 'Échecs : ' . implode(', ', $failed_ids) . "\n";
}

if (!$opts['dry_run'] && !$no_repair) {
    echo "\n";
    $repair_code = mig_run_php_script($migrations_dir . '/run_schema_repair.php');
    if ($repair_code !== 0) {
        $errors++;
    }

    foreach ($manifest as $step) {
        if (in_array($step['id'], $failed_ids, true)) {
            $path = $migrations_dir . '/' . $step['script'];
            if (!is_file($path)) {
                continue;
            }
            echo "\n>> Nouvelle tentative [" . $step['id'] . "]\n";
            $code = mig_run_php_script($path);
            if ($code === 0) {
                mig_mark_applied($db, $step['id'], $step['label']);
                echo "OK [" . $step['id'] . "] enregistré après réparation\n";
            }
        }
    }
}

if (!$opts['dry_run']) {
    echo "\n";
    $scan_code = mig_run_php_script($migrations_dir . '/scan_schema.php');
    if ($scan_code !== 0) {
        echo "\nLe schéma n'est pas encore complet. Relancez : php migrations/run_all_migrations.php --force\n";
        exit(1);
    }
}

if ($errors > 0 && !$opts['continue']) {
    exit(1);
}

echo "\nToutes les migrations sont à jour.\n";
