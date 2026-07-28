<?php
/**
 * Convertit les images existantes en WebP et met à jour leurs chemins en BDD.
 *
 * Usage :
 *   php scripts/optimize_existing_images.php
 *   php scripts/optimize_existing_images.php produits
 *   php scripts/optimize_existing_images.php slider
 *   php scripts/optimize_existing_images.php --dry-run
 *   php scripts/optimize_existing_images.php produits --dry-run
 *
 * Options :
 *   --dry-run   Simulation : ni conversion, ni écriture BDD, ni suppression.
 *   --force     Supprime l'original même si aucune ligne BDD n'a été mise à jour.
 *
 * Sans argument, les dossiers administrables du site sont traités :
 * produits, catégories, slider, bannière d'accueil et mise en avant.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/image_optimizer_db.php';
require_once __DIR__ . '/../conn/conn.php';

$upload_root = realpath(__DIR__ . '/../upload');
if ($upload_root === false || !is_dir($upload_root)) {
    fwrite(STDERR, "Dossier upload/ introuvable.\n");
    exit(1);
}

// Séparation des options (--dry-run, --force) et de la cible éventuelle.
$args = array_slice($argv, 1);
$dry_run = in_array('--dry-run', $args, true);
$force_delete = in_array('--force', $args, true);
$positional = array_values(array_filter($args, static function ($arg) {
    return strpos((string) $arg, '--') !== 0;
}));

$allowed_targets = ['produits', 'categories', 'slider', 'section4', 'trending', 'commandes-personnalisees'];
$requested_target = isset($positional[0]) ? trim((string) $positional[0], "/\\ \t\n\r\0\x0B") : '';
if ($requested_target !== '' && !in_array($requested_target, $allowed_targets, true)) {
    fwrite(STDERR, 'Cible invalide. Valeurs : ' . implode(', ', $allowed_targets) . ".\n");
    exit(1);
}
$targets = $requested_target !== '' ? [$requested_target] : $allowed_targets;

if ($dry_run) {
    echo "=== MODE SIMULATION (--dry-run) : aucune modification ne sera écrite ===\n";
}

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion PDO indisponible : conversion annulée pour protéger les chemins BDD.\n");
    exit(1);
}

$processed = 0;
$skipped = 0;
$failed = 0;
$database_updates = 0;
$saved_bytes = 0;
$mapping_log = __DIR__ . '/optimize_image_mapping.jsonl';

foreach ($targets as $target) {
    $scan_dir = $upload_root . DIRECTORY_SEPARATOR . $target;
    if (!is_dir($scan_dir)) {
        echo "Ignoré : upload/{$target}/ n'existe pas.\n";
        continue;
    }

    echo "\nTraitement de upload/{$target}/\n";
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scan_dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file_info) {
        if (!$file_info->isFile()) {
            continue;
        }

        $extension = strtolower($file_info->getExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }

        $absolute = $file_info->getPathname();
        $stem = pathinfo($absolute, PATHINFO_FILENAME);
        if (str_ends_with($stem, '_md') || str_ends_with($stem, '_sm')) {
            continue;
        }

        $old_relative = ltrim(str_replace('\\', '/', substr($absolute, strlen($upload_root))), '/');
        $relative_directory = dirname($old_relative);
        $relative_directory = $relative_directory === '.' ? '' : $relative_directory;
        $webp_absolute = dirname($absolute) . DIRECTORY_SEPARATOR . $stem . '.webp';

        if ($extension !== 'webp' && is_file($webp_absolute)) {
            $skipped++;
            echo "Déjà convertie : {$old_relative}\n";
            continue;
        }

        if ($extension === 'webp'
            && is_file(dirname($absolute) . DIRECTORY_SEPARATOR . $stem . '_md.webp')
            && is_file(dirname($absolute) . DIRECTORY_SEPARATOR . $stem . '_sm.webp')) {
            $skipped++;
            continue;
        }

        $bytes_before = (int) (@filesize($absolute) ?: 0);

        if ($dry_run) {
            $skipped++;
            echo "[simulation] convertirait {$old_relative} → {$stem}.webp\n";
            continue;
        }

        $result = image_optimizer_process_tmp(
            $absolute,
            dirname($absolute),
            $relative_directory,
            'img_',
            $stem
        );
        if (empty($result['success'])) {
            $failed++;
            fwrite(STDERR, "Échec [{$old_relative}] : " . ($result['message'] ?? 'erreur inconnue') . "\n");
            continue;
        }

        $new_relative = trim(str_replace('\\', '/', (string) $result['relative_path']), '/');
        $db_rows = 0;
        try {
            $db->beginTransaction();
            $db_rows = image_db_apply_path_mapping($db, $old_relative, $new_relative);
            $db->commit();
            if ($db_rows > 0) {
                $database_updates++;
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $failed++;
            fwrite(STDERR, "BDD non mise à jour [{$old_relative}] : {$e->getMessage()}\n");
            continue;
        }

        file_put_contents(
            $mapping_log,
            json_encode(
                ['old' => $old_relative, 'new' => $new_relative, 'db_updates' => $db_rows],
                JSON_UNESCAPED_UNICODE
            ) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        // Sécurité : ne supprimer l'original que s'il est référencé en BDD,
        // sauf --force. Un original non référencé reste sur le disque.
        if ($extension !== 'webp' && is_file($absolute)) {
            if ($db_rows > 0 || $force_delete) {
                @unlink($absolute);
            } else {
                echo "  original conservé (aucune référence BDD) : {$old_relative}\n";
            }
        }

        $processed++;
        $saved_bytes += max(0, $bytes_before - (int) ($result['bytes_after'] ?? $bytes_before));
        echo "OK {$old_relative} → {$new_relative} ({$db_rows} chemin(s) BDD)\n";
    }
}

echo "\nTerminé : {$processed} optimisée(s), {$skipped} ignorée(s), {$failed} échec(s).\n";
echo "{$database_updates} chemin(s) BDD mis à jour, " . round($saved_bytes / 1024, 1) . " Ko économisés.\n";
if ($processed > 0) {
    echo "Journal : {$mapping_log}\n";
}
exit($failed > 0 ? 2 : 0);
