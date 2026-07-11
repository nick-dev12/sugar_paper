<?php
/**
 * Recompression batch des images déjà présentes dans upload/ (produits, catégories…).
 * Conserve le nom de base (foo.png → foo.webp) et met à jour la BDD.
 *
 * Usage CLI :
 *   php scripts/optimize_existing_images.php produits
 *   php scripts/optimize_existing_images.php categories
 *   php scripts/optimize_existing_images.php marketplace_hero
 *
 * Dossier « categories » : upload/categories/
 *   - rayons catalogue → table categories_generales.image
 *   - sous-catégories plateforme → table categories.image
 *
 * Dossier « marketplace_hero » : upload/marketplace_hero/
 *   - bannières hero accueil → table marketplace_hero_affiches.image (nom de fichier seul)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/image_optimizer.php';

$target_subdir = isset($argv[1]) ? trim((string) $argv[1], '/\\') : '';
$upload_root = realpath(__DIR__ . '/../upload');
if ($upload_root === false || !is_dir($upload_root)) {
    fwrite(STDERR, "Dossier upload/ introuvable.\n");
    exit(1);
}

$db = null;
$conn = __DIR__ . '/../conn/conn.php';
if (is_file($conn)) {
    require_once $conn;
    if (isset($db) && $db instanceof PDO) {
        require_once __DIR__ . '/../includes/image_optimizer_db.php';
    }
}

$mapping_log = __DIR__ . '/optimize_image_mapping.jsonl';

$scan_dir = $target_subdir !== '' ? $upload_root . DIRECTORY_SEPARATOR . $target_subdir : $upload_root;
if (!is_dir($scan_dir)) {
    fwrite(STDERR, "Dossier cible introuvable : {$scan_dir}\n");
    exit(1);
}

if ($target_subdir === 'categories') {
    echo "Cible : upload/categories/ → BDD categories.image + categories_generales.image\n";
}
if ($target_subdir === 'marketplace_hero') {
    echo "Cible : upload/marketplace_hero/ → BDD marketplace_hero_affiches.image\n";
}

$skip_suffixes = ['_md', '_sm'];
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
$processed = 0;
$skipped = 0;
$failed = 0;
$saved_bytes = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scan_dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file_info) {
    if (!$file_info->isFile()) {
        continue;
    }
    $abs = $file_info->getPathname();
    $ext = strtolower($file_info->getExtension());
    if (!in_array($ext, $allowed_ext, true)) {
        continue;
    }

    $base = pathinfo($abs, PATHINFO_FILENAME);
    foreach ($skip_suffixes as $suffix) {
        if (str_ends_with($base, $suffix)) {
            continue 2;
        }
    }

    $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($upload_root))), '/');
    $dir_abs = dirname($abs);
    $rel_dir = dirname($rel);
    $rel_subdir = ($rel_dir === '.' ? '' : $rel_dir);

    $webp_abs = $dir_abs . DIRECTORY_SEPARATOR . $base . '.webp';
    if (is_file($webp_abs)) {
        $skipped++;
        continue;
    }

    $bytes_before = (int) filesize($abs);
    $result = image_optimizer_process_tmp($abs, $dir_abs, $rel_subdir, 'img_', $base);
    if (empty($result['success'])) {
        $failed++;
        fwrite(STDERR, "Échec [{$rel}] : " . ($result['message'] ?? 'erreur') . "\n");
        continue;
    }

    $new_rel = (string) ($result['relative_path'] ?? '');
    $expected_rel = image_db_webp_equivalent_path($rel);
    $db_rel = ($expected_rel !== '') ? $expected_rel : $new_rel;

    if ($rel !== '' && $db_rel !== '' && $rel !== $db_rel) {
        $log_line = json_encode(['old' => $rel, 'new' => $db_rel], JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($mapping_log, $log_line, FILE_APPEND | LOCK_EX);
        if ($db instanceof PDO && function_exists('image_db_apply_path_mapping')) {
            image_db_apply_path_mapping($db, $rel, $db_rel);
        }
    }

    if ($abs !== $upload_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $new_rel)) {
        @unlink($abs);
    }

    $processed++;
    $saved_bytes += max(0, $bytes_before - (int) ($result['bytes_after'] ?? $bytes_before));
    echo "OK {$rel} → {$db_rel}\n";
}

echo "\nTerminé : {$processed} optimisée(s), {$skipped} ignorée(s), {$failed} échec(s), " . round($saved_bytes / 1024, 1) . " Ko économisés.\n";

if ($target_subdir === 'categories' && $db instanceof PDO && function_exists('image_db_sync_table_column')) {
    $sync_details = [];
    $n_sc = image_db_sync_table_column($db, 'categories', 'image', $sync_details);
    $n_cg = image_db_sync_table_column($db, 'categories_generales', 'image', $sync_details);
    if ($n_sc > 0 || $n_cg > 0) {
        echo "Sync chemins BDD : {$n_sc} sous-catégorie(s), {$n_cg} rayon(s).\n";
    }
}

if ($target_subdir === 'marketplace_hero' && $db instanceof PDO && function_exists('image_db_sync_marketplace_hero_images')) {
    $sync_details = [];
    $n_hero = image_db_sync_marketplace_hero_images($db, $sync_details);
    if ($n_hero > 0) {
        echo "Sync chemins BDD : {$n_hero} bannière(s) hero.\n";
    }
}

if ($processed > 0) {
    echo "Journal : {$mapping_log}\n";
    echo "Si besoin : php scripts/sync_image_paths_database.php\n";
}
