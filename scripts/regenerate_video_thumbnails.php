<?php
/**
 * Régénère les miniatures manquantes pour toutes les vidéos.
 * Usage : php scripts/regenerate_video_thumbnails.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_videos.php';

echo "=== Régénération miniatures vidéos ===\n\n";

$videos = get_all_videos(null);
if (!$videos) {
    echo "Aucune vidéo en base.\n";
    exit(0);
}

$ok = 0;
$skip = 0;
$fail = 0;

foreach ($videos as $video) {
    $id = (int) ($video['id'] ?? 0);
    $label = trim((string) ($video['titre'] ?? '')) ?: ('#' . $id);

    if (!empty($video['image_preview'])) {
        $existing = __DIR__ . '/../upload/videos/thumbnails/' . $video['image_preview'];
        if (is_file($existing) && filesize($existing) > 0) {
            echo "  SKIP {$label} — miniature existante\n";
            $skip++;
            continue;
        }
    }

    $thumb = video_ensure_preview_image($video);
    if ($thumb) {
        echo "  OK   {$label} → {$thumb}\n";
        $ok++;
    } else {
        echo "  FAIL {$label} — FFmpeg requis ou vidéo introuvable\n";
        $fail++;
    }
}

echo "\nTerminé : {$ok} générée(s), {$skip} ignorée(s), {$fail} échec(s).\n";
if ($fail > 0) {
    echo "Installez FFmpeg et relancez ce script, ou les miniatures seront capturées côté navigateur.\n";
}
