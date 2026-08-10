<?php
/**
 * Digital Asset Links (Android App Links) — sugar-paper.com
 * Servi en application/json via réécriture .htaccess → assetlinks.json
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');

$root = dirname(__DIR__);
$configPath = $root . '/config/assetlinks.php';
$package = 'com.sugarpaper.app';
$fingerprints = [];

if (is_file($configPath)) {
    $cfg = require $configPath;
    if (is_array($cfg)) {
        $package = trim((string) ($cfg['package_name'] ?? $package));
        $raw = $cfg['sha256_cert_fingerprints'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $fp) {
                $fp = strtoupper(trim((string) $fp));
                if ($fp !== '') {
                    $fingerprints[] = $fp;
                }
            }
        }
    }
}

$fingerprints = array_values(array_unique($fingerprints));

if ($package === '' || empty($fingerprints)) {
    http_response_code(503);
    echo "[]\n";
    exit;
}

$payload = [
    [
        'relation' => ['delegate_permission/common.handle_all_urls'],
        'target' => [
            'namespace' => 'android_app',
            'package_name' => $package,
            'sha256_cert_fingerprints' => $fingerprints,
        ],
    ],
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
