<?php
/**
 * API version minimale apps mobiles (Android / iOS).
 * GET ?platform=android|ios&build=12
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/app_mobile_version.php';

$platform = app_mobile_version_normalize_platform((string) ($_GET['platform'] ?? ''));
$build = (int) ($_GET['build'] ?? 0);

if ($platform === '') {
    echo json_encode(app_mobile_version_public_payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(app_mobile_version_check($platform, $build), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
