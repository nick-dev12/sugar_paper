<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/firebase_auth_google.php';

$cfg = require __DIR__ . '/../config/firebase_server.php';
$cacert = firebase_auth_google_configure_ssl($cfg['cacert_path'] ?? __DIR__ . '/../config/cacert.pem');
if ($cacert === false) {
    echo "cacert missing\n";
    exit(1);
}

$client = new GuzzleHttp\Client([
    'http_errors' => false,
    'verify' => $cacert,
    'timeout' => 20,
]);
$response = $client->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
