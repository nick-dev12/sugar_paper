<?php
/**
 * Analyse le schéma BDD actuel vs les attentes post-migration.
 * N'applique aucune modification — diagnostic uniquement.
 *
 * Usage :
 *   php migrations/scan_schema.php
 *   php migrations/scan_schema.php --json
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$json_output = in_array('--json', $argv ?? [], true);
$db = mig_connect();
$expectations = require __DIR__ . '/schema_expectations.php';

$missing_tables = [];
$missing_columns = [];
$present_tables = [];
$present_columns = [];

foreach ($expectations['tables'] as $table) {
    if (mig_table_exists($db, $table)) {
        $present_tables[] = $table;
    } else {
        $missing_tables[] = $table;
    }
}

foreach ($expectations['columns'] as $item) {
    $table = $item[0];
    $column = $item[1];
    if (!mig_table_exists($db, $table)) {
        $missing_columns[] = ['table' => $table, 'column' => $column, 'reason' => 'table_absente'];
        continue;
    }
    if (mig_column_exists($db, $table, $column)) {
        $present_columns[] = "$table.$column";
    } else {
        $missing_columns[] = ['table' => $table, 'column' => $column, 'reason' => 'colonne_absente'];
    }
}

$applied = [];
if (mig_table_exists($db, '_schema_migrations')) {
    $rows = $db->query('SELECT migration_id, label, applied_at FROM `_schema_migrations` ORDER BY applied_at ASC')->fetchAll(PDO::FETCH_ASSOC);
    $applied = $rows ?: [];
}

$snapshot = [];
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $cols = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
    $snapshot[$table] = array_column($cols, 'Field');
}

$report = [
    'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
    'tables_count' => count($tables),
    'expected_tables_ok' => count($present_tables),
    'expected_tables_missing' => count($missing_tables),
    'expected_columns_ok' => count($present_columns),
    'expected_columns_missing' => count($missing_columns),
    'missing_tables' => $missing_tables,
    'missing_columns' => $missing_columns,
    'migrations_applied' => $applied,
    'generated_at' => date('c'),
];

$snapshot_path = __DIR__ . '/_schema_local_snapshot.json';
file_put_contents($snapshot_path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($json_output) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(count($missing_tables) + count($missing_columns) > 0 ? 1 : 0);
}

echo "=== Scan schéma BDD ===\n";
echo 'Base : ' . $report['database'] . "\n";
echo 'Tables présentes : ' . $report['tables_count'] . "\n";
echo 'Tables attendues OK : ' . $report['expected_tables_ok'] . ' / ' . count($expectations['tables']) . "\n";
echo 'Colonnes attendues OK : ' . $report['expected_columns_ok'] . ' / ' . count($expectations['columns']) . "\n\n";

if (!empty($missing_tables)) {
    echo "--- Tables manquantes ---\n";
    foreach ($missing_tables as $t) {
        echo "  ! $t\n";
    }
    echo "\n";
}

if (!empty($missing_columns)) {
    echo "--- Colonnes manquantes ---\n";
    foreach ($missing_columns as $item) {
        echo '  ! ' . $item['table'] . '.' . $item['column'];
        if ($item['reason'] === 'table_absente') {
            echo ' (table absente)';
        }
        echo "\n";
    }
    echo "\n";
}

if (empty($missing_tables) && empty($missing_columns)) {
    echo "OK : le schéma correspond aux attentes.\n";
} else {
    echo "Action : exécutez php migrations/run_all_migrations.php\n";
}

if (!empty($applied)) {
    echo "\n--- Migrations déjà enregistrées (" . count($applied) . ") ---\n";
    foreach ($applied as $row) {
        echo '  ' . $row['migration_id'] . ' — ' . $row['applied_at'] . "\n";
    }
}

echo "\nSnapshot exporté : migrations/_schema_local_snapshot.json\n";
exit(count($missing_tables) + count($missing_columns) > 0 ? 1 : 0);
