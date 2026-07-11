<?php
/**
 * Génère les icônes PWA depuis image/logo_market.jpeg (ou .png en secours).
 * Usage : php generate_pwa_icons.php
 */

$sources = [
    __DIR__ . '/image/logo_market.jpeg',
    __DIR__ . '/image/logo_market.jpg',
    __DIR__ . '/image/logo_market.png',
];

$source = null;
foreach ($sources as $candidate) {
    if (is_file($candidate)) {
        $source = $candidate;
        break;
    }
}

$iconsDir = __DIR__ . '/icons';

if ($source === null) {
    fwrite(STDERR, "Erreur : logo introuvable (logo_market.jpeg / .png).\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Erreur : l'extension GD de PHP est requise.\n");
    exit(1);
}

if (!is_dir($iconsDir) && !mkdir($iconsDir, 0755, true)) {
    fwrite(STDERR, "Erreur : impossible de créer le dossier icons/.\n");
    exit(1);
}

/**
 * @return resource|false
 */
function pwa_load_image($path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpeg' || $ext === 'jpg') {
        return @imagecreatefromjpeg($path);
    }
    if ($ext === 'png') {
        return @imagecreatefrompng($path);
    }
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }
    return false;
}

/**
 * @param resource $source
 */
function pwa_write_square_icon($source, $size, $dest, $paddingRatio = 0.1)
{
    $canvas = imagecreatetruecolor($size, $size);
    if (!$canvas) {
        return false;
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    imagealphablending($canvas, true);

    $srcWidth = imagesx($source);
    $srcHeight = imagesy($source);
    if ($srcWidth <= 0 || $srcHeight <= 0) {
        imagedestroy($canvas);
        return false;
    }

    $padding = (int) round($size * $paddingRatio);
    $maxDim = max(1, $size - ($padding * 2));
    $scale = min($maxDim / $srcWidth, $maxDim / $srcHeight);
    $targetW = max(1, (int) round($srcWidth * $scale));
    $targetH = max(1, (int) round($srcHeight * $scale));
    $dstX = (int) floor(($size - $targetW) / 2);
    $dstY = (int) floor(($size - $targetH) / 2);

    imagecopyresampled(
        $canvas,
        $source,
        $dstX,
        $dstY,
        0,
        0,
        $targetW,
        $targetH,
        $srcWidth,
        $srcHeight
    );

    $ok = imagepng($canvas, $dest, 9);
    imagedestroy($canvas);

    return $ok;
}

$image = pwa_load_image($source);
if (!$image) {
    fwrite(STDERR, "Erreur : impossible de charger l'image source.\n");
    exit(1);
}

$jobs = [
    ['file' => 'icon-192.png', 'size' => 192, 'padding' => 0.1],
    ['file' => 'icon-512.png', 'size' => 512, 'padding' => 0.1],
    ['file' => 'icon-192-maskable.png', 'size' => 192, 'padding' => 0.2],
    ['file' => 'icon-512-maskable.png', 'size' => 512, 'padding' => 0.2],
    ['file' => 'apple-touch-icon.png', 'size' => 180, 'padding' => 0.1],
    ['file' => 'favicon-16.png', 'size' => 16, 'padding' => 0.06],
    ['file' => 'favicon-32.png', 'size' => 32, 'padding' => 0.08],
    ['file' => 'favicon-48.png', 'size' => 48, 'padding' => 0.08],
];

foreach ($jobs as $job) {
    $dest = $iconsDir . '/' . $job['file'];
    if (!pwa_write_square_icon($image, (int) $job['size'], $dest, (float) $job['padding'])) {
        imagedestroy($image);
        fwrite(STDERR, "Erreur : impossible d'enregistrer {$dest}.\n");
        exit(1);
    }
    echo "Icône créée : {$job['file']} ({$job['size']}x{$job['size']})\n";
}

$faviconIco = __DIR__ . '/favicon.ico';
if (!copy($iconsDir . '/favicon-48.png', $faviconIco)) {
    fwrite(STDERR, "Avertissement : impossible de copier favicon.ico à la racine.\n");
} else {
    echo "favicon.ico créé à la racine (copie de favicon-48.png).\n";
}

imagedestroy($image);

echo "Source : " . basename($source) . "\n";
echo "Terminé. Les icônes PWA ont été générées dans /icons/.\n";
