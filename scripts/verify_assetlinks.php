<?php
/**
 * Vérifie la présence et le format de assetlinks.json (Android App Links).
 *
 * Usage : php scripts/verify_assetlinks.php [https://sugar-paper.com]
 */
declare(strict_types=1);

$siteUrl = rtrim($argv[1] ?? 'https://sugar-paper.com', '/');
$url = $siteUrl . '/.well-known/assetlinks.json';

echo "Vérification Android App Links\n";
echo "URL : {$url}\n\n";

$localConfig = dirname(__DIR__) . '/config/assetlinks.php';
if (!is_file($localConfig)) {
    echo "LOCAL : config/assetlinks.php absent — copiez config/assetlinks.example.php\n";
} else {
    $cfg = require $localConfig;
    $fps = $cfg['sha256_cert_fingerprints'] ?? [];
    $count = is_array($fps) ? count(array_filter($fps)) : 0;
    if ($count < 1) {
        echo "LOCAL : sha256_cert_fingerprints vide — ajoutez le SHA-256 Play Console\n";
    } else {
        echo "LOCAL : {$count} empreinte(s) SHA-256 configurée(s)\n";
    }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HEADER => true,
]);
$raw = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($raw === false) {
    echo "\nHTTP : échec curl\n";
    exit(1);
}

$headerSize = strpos($raw, "\r\n\r\n");
$body = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;

echo "\nHTTP : {$code}\n";
echo "Content-Type : {$ctype}\n";

if ($code !== 200) {
    echo "ERREUR : code HTTP attendu 200\n";
    exit(1);
}

if (stripos($ctype, 'application/json') === false) {
    echo "ERREUR : Content-Type doit contenir application/json\n";
    exit(1);
}

$data = json_decode($body, true);
if (!is_array($data) || empty($data)) {
    echo "ERREUR : JSON vide ou invalide — configurez config/assetlinks.php sur le serveur\n";
    exit(1);
}

$target = $data[0]['target'] ?? [];
$pkg = $target['package_name'] ?? '';
$fps = $target['sha256_cert_fingerprints'] ?? [];

echo "Package : {$pkg}\n";
echo "Empreintes : " . (is_array($fps) ? count($fps) : 0) . "\n";
echo "\nOK : assetlinks.json accessible et valide\n";
exit(0);
