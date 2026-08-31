<?php
/**
 * Optimisation centralisée des images uploadées.
 *
 * Chaque image est validée par son contenu, redimensionnée, convertie en WebP
 * et déclinée en trois tailles : 1920 px, 800 px (_md) et 400 px (_sm).
 */

require_once __DIR__ . '/upload_image_limits.php';

if (!defined('IMAGE_OPTIMIZER_MAX_WIDTH')) {
    define('IMAGE_OPTIMIZER_MAX_WIDTH', 1920);
}
if (!defined('IMAGE_OPTIMIZER_MD_WIDTH')) {
    define('IMAGE_OPTIMIZER_MD_WIDTH', 800);
}
if (!defined('IMAGE_OPTIMIZER_SM_WIDTH')) {
    define('IMAGE_OPTIMIZER_SM_WIDTH', 400);
}
if (!defined('IMAGE_OPTIMIZER_WEBP_QUALITY')) {
    define('IMAGE_OPTIMIZER_WEBP_QUALITY', 82);
}

function image_optimizer_allowed_mimes() {
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function image_optimizer_gd_available() {
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

function image_optimizer_webp_available() {
    return image_optimizer_gd_available() && function_exists('imagewebp');
}

function image_optimizer_detect_mime($path) {
    if (!is_file($path)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime !== '') {
                return $mime;
            }
        }
    }

    $info = @getimagesize($path);
    return is_array($info) && !empty($info['mime']) ? (string) $info['mime'] : '';
}

function image_optimizer_load($path) {
    if (!image_optimizer_gd_available() || !is_file($path)) {
        return false;
    }

    $blob = @file_get_contents($path);
    if ($blob === false || $blob === '') {
        return false;
    }

    $image = @imagecreatefromstring($blob);
    if ($image !== false && function_exists('imageresolution')) {
        @imageresolution($image, 72, 72);
    }
    return $image;
}

function image_optimizer_resize($source, $max_width) {
    $source_width = imagesx($source);
    $source_height = imagesy($source);
    if ($source_width <= 0 || $source_height <= 0) {
        return false;
    }

    $max_width = max(1, (int) $max_width);
    $target_width = min($source_width, $max_width);
    $target_height = (int) round($source_height * ($target_width / $source_width));
    $target = imagecreatetruecolor($target_width, max(1, $target_height));
    if ($target === false) {
        return false;
    }

    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefill($target, 0, 0, $transparent);
    }
    imagecopyresampled(
        $target,
        $source,
        0,
        0,
        0,
        0,
        $target_width,
        max(1, $target_height),
        $source_width,
        $source_height
    );
    return $target;
}

function image_optimizer_save_webp($image, $destination, $quality = IMAGE_OPTIMIZER_WEBP_QUALITY) {
    if (!image_optimizer_webp_available()) {
        return false;
    }
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }
    return imagewebp($image, $destination, max(1, min(100, (int) $quality)));
}

/**
 * Convertit une image temporaire ou existante et génère ses variantes.
 */
function image_optimizer_process_tmp($tmp_path, $dest_dir, $relative_subdir, $name_prefix, $fixed_stem = null) {
    if (!is_uploaded_file($tmp_path) && !is_file($tmp_path)) {
        return ['success' => false, 'message' => 'Fichier source introuvable.'];
    }

    $bytes_before = (int) (@filesize($tmp_path) ?: 0);
    if ($bytes_before <= 0) {
        return ['success' => false, 'message' => 'Le fichier image est vide.'];
    }
    if ($bytes_before > UPLOAD_MAX_IMAGE_BYTES) {
        $max_mo = (int) round(UPLOAD_MAX_IMAGE_BYTES / (1024 * 1024));
        return ['success' => false, 'message' => 'Image trop volumineuse (' . $max_mo . ' Mo maximum).'];
    }

    $mime = image_optimizer_detect_mime($tmp_path);
    if (!in_array($mime, image_optimizer_allowed_mimes(), true)) {
        return ['success' => false, 'message' => 'Format non supporté. Utilisez JPG, PNG, GIF ou WebP.'];
    }

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true) && !is_dir($dest_dir)) {
        return ['success' => false, 'message' => 'Impossible de créer le dossier d’upload.'];
    }

    if (is_string($fixed_stem) && $fixed_stem !== '') {
        $safe_stem = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fixed_stem);
        $base_name = $safe_stem !== '' ? $safe_stem : $name_prefix . bin2hex(random_bytes(8));
    } else {
        $base_name = $name_prefix . bin2hex(random_bytes(8));
    }

    if (!image_optimizer_webp_available()) {
        return image_optimizer_fallback_move(
            $tmp_path,
            $dest_dir,
            $relative_subdir,
            $mime,
            $name_prefix,
            $bytes_before
        );
    }

    $source = image_optimizer_load($tmp_path);
    if ($source === false) {
        return ['success' => false, 'message' => 'L’image est illisible ou corrompue.'];
    }

    $variants = [
        ['suffix' => '', 'width' => IMAGE_OPTIMIZER_MAX_WIDTH],
        ['suffix' => '_md', 'width' => IMAGE_OPTIMIZER_MD_WIDTH],
        ['suffix' => '_sm', 'width' => IMAGE_OPTIMIZER_SM_WIDTH],
    ];
    $saved_files = [];

    foreach ($variants as $variant) {
        $resized = image_optimizer_resize($source, $variant['width']);
        if ($resized === false) {
            continue;
        }
        $filename = $base_name . $variant['suffix'] . '.webp';
        $destination = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (image_optimizer_save_webp($resized, $destination)) {
            $saved_files[] = $filename;
        }
        imagedestroy($resized);
    }
    imagedestroy($source);

    if (empty($saved_files) || !in_array($base_name . '.webp', $saved_files, true)) {
        foreach ($saved_files as $saved_file) {
            @unlink(rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $saved_file);
        }
        return ['success' => false, 'message' => 'Échec de la conversion WebP.'];
    }

    $bytes_after = 0;
    foreach ($saved_files as $saved_file) {
        $bytes_after += (int) (@filesize(rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $saved_file) ?: 0);
    }

    $relative_subdir = trim(str_replace('\\', '/', $relative_subdir), '/');
    $filename = $base_name . '.webp';
    return [
        'success' => true,
        'relative_path' => ($relative_subdir !== '' ? $relative_subdir . '/' : '') . $filename,
        'filename' => $filename,
        'message' => '',
        'bytes_before' => $bytes_before,
        'bytes_after' => $bytes_after,
    ];
}

/**
 * Conserve l'original uniquement si GD/WebP n'est pas disponible.
 */
function image_optimizer_fallback_move($tmp_path, $dest_dir, $relative_subdir, $mime, $name_prefix, $bytes_before) {
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $filename = $name_prefix . bin2hex(random_bytes(8)) . '.' . ($extensions[$mime] ?? 'jpg');
    $destination = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $moved = is_uploaded_file($tmp_path)
        ? move_uploaded_file($tmp_path, $destination)
        : @copy($tmp_path, $destination);

    if (!$moved) {
        return ['success' => false, 'message' => 'Impossible d’enregistrer l’image.'];
    }

    $relative_subdir = trim(str_replace('\\', '/', $relative_subdir), '/');
    return [
        'success' => true,
        'relative_path' => ($relative_subdir !== '' ? $relative_subdir . '/' : '') . $filename,
        'filename' => $filename,
        'message' => 'WebP indisponible : fichier original conservé.',
        'bytes_before' => $bytes_before,
        'bytes_after' => (int) (@filesize($destination) ?: $bytes_before),
    ];
}

function upload_optimize_image_file(array $file_info, $dest_dir, $relative_subdir, $name_prefix) {
    if (!isset($file_info['error']) || (int) $file_info['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload invalide.'];
    }
    $tmp_path = (string) ($file_info['tmp_name'] ?? '');
    if ($tmp_path === '' || !is_uploaded_file($tmp_path)) {
        return ['success' => false, 'message' => 'Fichier temporaire manquant.'];
    }
    return image_optimizer_process_tmp($tmp_path, $dest_dir, $relative_subdir, $name_prefix);
}

/**
 * Résout un chemin BDD vers le fichier réel (ex. foo.png → foo.webp si converti).
 */
function image_optimizer_resolve_relative_path($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    if (str_starts_with($relative_path, 'upload/')) {
        $relative_path = substr($relative_path, 7);
    }

    $upload_root = dirname(__DIR__) . '/upload/';
    if (is_file($upload_root . $relative_path)) {
        return $relative_path;
    }

    $directory = dirname($relative_path);
    $stem = pathinfo($relative_path, PATHINFO_FILENAME);
    if ($stem === '') {
        return $relative_path;
    }
    $webp_rel = ($directory === '.' || $directory === '') ? $stem . '.webp' : $directory . '/' . $stem . '.webp';
    if (is_file($upload_root . $webp_rel)) {
        return $webp_rel;
    }
    return $relative_path;
}

/**
 * Normalise un chemin BDD vers .webp quand le fichier WebP existe.
 */
function image_optimizer_normalize_db_path($relative_path) {
    $resolved = image_optimizer_resolve_relative_path($relative_path);
    $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
    if ($ext === 'webp') {
        return $resolved;
    }
    $upload_root = dirname(__DIR__) . '/upload/';
    $directory = dirname($resolved);
    $stem = pathinfo($resolved, PATHINFO_FILENAME);
    if ($stem === '') {
        return $resolved;
    }
    $webp_rel = ($directory === '.' || $directory === '') ? $stem . '.webp' : $directory . '/' . $stem . '.webp';
    return is_file($upload_root . $webp_rel) ? $webp_rel : $resolved;
}

function image_optimizer_variant_relative_path($relative_path, $variant) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    $directory = dirname($relative_path);
    $filename = pathinfo($relative_path, PATHINFO_FILENAME) . '_' . $variant . '.webp';
    return ($directory === '.' || $directory === '') ? $filename : $directory . '/' . $filename;
}

/**
 * URL publique d'une image uploadée (original / md / sm).
 */
function upload_image_url($relative_path, $variant = 'md') {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '/image/produit1.jpg';
    }
    if (str_starts_with($relative_path, 'upload/')) {
        $relative_path = substr($relative_path, 7);
    }

    $relative_path = image_optimizer_resolve_relative_path($relative_path);
    $upload_root = dirname(__DIR__) . '/upload/';
    $variant = strtolower(trim((string) $variant));

    if ($variant === '' || $variant === 'original') {
        return '/upload/' . $relative_path;
    }
    if (!in_array($variant, ['md', 'sm'], true)) {
        $variant = 'md';
    }

    $variant_rel = image_optimizer_variant_relative_path($relative_path, $variant);
    if ($variant_rel !== '' && is_file($upload_root . $variant_rel)) {
        return '/upload/' . $variant_rel;
    }
    return '/upload/' . $relative_path;
}

/**
 * Image stockée comme nom de fichier seul dans un sous-dossier (slider, section4, trending).
 */
function upload_subdir_image_url($subdir, $filename, $variant = 'original') {
    $subdir = trim(str_replace('\\', '/', (string) $subdir), '/');
    $filename = basename(trim(str_replace('\\', '/', (string) $filename)));
    if ($subdir === '' || $filename === '') {
        return upload_image_url('', $variant);
    }
    return upload_image_url($subdir . '/' . $filename, $variant);
}

/**
 * Alias compatible poid_lourd — URL avec variante md/sm.
 */
function upload_image_url_from_src($src, $variant = 'md') {
    return upload_image_url($src, $variant);
}

/**
 * Optimise une miniature déjà générée sur disque (ex. FFmpeg → JPG).
 *
 * @return string|null Nom de fichier final (basename)
 */
function image_optimizer_process_disk_thumbnail($absolute_path, $dest_dir, $relative_subdir, $fixed_stem) {
    if (!is_file($absolute_path)) {
        return null;
    }
    $result = image_optimizer_process_tmp($absolute_path, $dest_dir, $relative_subdir, 'thumb_', $fixed_stem);
    if (empty($result['success'])) {
        return basename($absolute_path);
    }
    $ext = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
    if ($ext !== 'webp' && is_file($absolute_path)) {
        @unlink($absolute_path);
    }
    $filename = (string) ($result['filename'] ?? '');
    if ($filename !== '') {
        return $filename;
    }
    $relative = (string) ($result['relative_path'] ?? '');
    return $relative !== '' ? basename($relative) : null;
}

function image_optimizer_delete_with_variants($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return;
    }

    $paths = [$relative_path];
    foreach (['md', 'sm'] as $variant) {
        $paths[] = image_optimizer_variant_relative_path($relative_path, $variant);
    }
    foreach (array_unique($paths) as $path) {
        $absolute = dirname(__DIR__) . '/upload/' . $path;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
