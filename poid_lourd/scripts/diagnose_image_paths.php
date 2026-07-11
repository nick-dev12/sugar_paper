<?php
/**
 * Diagnostic des chemins d'images en BDD vs fichiers sur disque.
 *
 * Usage : php scripts/diagnose_image_paths.php
 *         php scripts/diagnose_image_paths.php --missing
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$conn = __DIR__ . '/../conn/conn.php';
if (!is_file($conn)) {
    fwrite(STDERR, "conn/conn.php introuvable.\n");
    exit(1);
}
require $conn;
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion PDO indisponible — vérifiez conn/conn.php (base jomas_colobane1 en production).\n");
    exit(1);
}

$db_name = function_exists('image_db_current_database') ? image_db_current_database($db) : '';
echo 'Base connectée : ' . ($db_name !== '' ? $db_name : '(inconnue)') . "\n";
echo 'Dossier upload : ' . realpath(dirname(__DIR__) . '/upload') . "\n\n";

$root = dirname(__DIR__) . '/upload/';

/**
 * @return array{ok:int, missing:int, webp_db:int}
 */
function diagnose_image_column($db, $root, $table, $column, $where_extra = '') {
    if (!function_exists('image_db_table_has_column') || !image_db_table_has_column($db, $table, $column)) {
        echo "{$table}.{$column} : (colonne absente)\n";
        return ['ok' => 0, 'missing' => 0, 'webp_db' => 0];
    }

    $sql = "SELECT id, `{$column}` AS img FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''";
    if ($where_extra !== '') {
        $sql .= ' ' . $where_extra;
    }
    $stmt = $db->query($sql);
    $ok = $missing = $webp_db = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $p = trim(str_replace('\\', '/', (string) ($r['img'] ?? '')));
        if (str_ends_with(strtolower($p), '.webp')) {
            $webp_db++;
        }
        $resolved = function_exists('image_optimizer_resolve_relative_path')
            ? image_optimizer_resolve_relative_path($p)
            : $p;
        if (is_file($root . str_replace('/', DIRECTORY_SEPARATOR, $resolved))) {
            $ok++;
        } else {
            $missing++;
        }
    }
    echo "{$table}.{$column} : ok={$ok} missing={$missing} webp_en_bdd={$webp_db}\n";
    return ['ok' => $ok, 'missing' => $missing, 'webp_db' => $webp_db];
}

diagnose_image_column($db, $root, 'produits', 'image_principale');
diagnose_image_column($db, $root, 'categories', 'image');
diagnose_image_column($db, $root, 'categories_generales', 'image');

if (in_array('--missing', $argv, true)) {
    $tables = [
        ['produits', 'image_principale', ''],
        ['categories', 'image', ''],
        ['categories_generales', 'image', ''],
    ];
    foreach ($tables as $t) {
        [$table, $column, $where] = $t;
        if (!function_exists('image_db_table_has_column') || !image_db_table_has_column($db, $table, $column)) {
            continue;
        }
        echo "\n--- {$table}.{$column} manquants ---\n";
        $sql = "SELECT id, `{$column}` AS img FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''";
        if ($where !== '') {
            $sql .= ' ' . $where;
        }
        $stmt = $db->query($sql);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $p = trim(str_replace('\\', '/', (string) ($r['img'] ?? '')));
            $resolved = function_exists('image_optimizer_resolve_relative_path')
                ? image_optimizer_resolve_relative_path($p)
                : $p;
            if (!is_file($root . str_replace('/', DIRECTORY_SEPARATOR, $resolved))) {
                echo $r['id'] . ' ' . $p . "\n";
            }
        }
    }
}
