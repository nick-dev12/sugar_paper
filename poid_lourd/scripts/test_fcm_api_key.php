<?php
$cfg = require __DIR__ . '/../config/firebase_config.php';
$apiKey = $cfg['apiKey'];

$urls = [
    'Installations POST' => 'https://firebaseinstallations.googleapis.com/v1/projects/' . $cfg['projectId'] . '/installations',
    'Identity Toolkit' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getProjectConfig?key=' . urlencode($apiKey),
    'FCM v1 send (expect 401/403 not invalid key)' => 'https://fcm.googleapis.com/v1/projects/' . $cfg['projectId'] . '/messages:send',
];

foreach ($urls as $label => $url) {
    $method = strpos($label, 'POST') !== false || strpos($label, 'send') !== false ? 'POST' : 'GET';
    $headers = ['x-goog-api-key: ' . $apiKey, 'Referer: http://localhost:5000/'];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $method === 'POST' ? '{}' : '',
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    echo '=== ' . $label . ' ===' . PHP_EOL;
    echo 'Key: ' . $apiKey . PHP_EOL;
    echo ($http_response_header[0] ?? '') . PHP_EOL;
    echo substr($body ?: '', 0, 250) . PHP_EOL . PHP_EOL;
}
