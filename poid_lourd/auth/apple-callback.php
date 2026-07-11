<?php
/**
 * Callback Apple Sign-In — app Android (flux form_post).
 *
 * Apple POSTe code + id_token vers cette URL (response_mode=form_post).
 * Le plugin sign_in_with_apple attend une redirection vers :
 *   intent://callback?…#Intent;package=com.colobanes.app;scheme=signinwithapple;end
 *
 * @see https://pub.dev/packages/sign_in_with_apple
 */
declare(strict_types=1);

header('Cache-Control: no-store');

$firebase_config_path = __DIR__ . '/../config/firebase_config.php';
$android_package = 'com.colobanes.app';
if (is_file($firebase_config_path)) {
    $cfg = require $firebase_config_path;
    if (!empty($cfg['auth']['androidPackage']) && is_string($cfg['auth']['androidPackage'])) {
        $android_package = $cfg['auth']['androidPackage'];
    }
}

/**
 * @param array<string, string> $params
 */
function colobanes_apple_android_intent_url(array $params, string $package): string
{
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return 'intent://callback?' . $query
        . '#Intent;package=' . $package
        . ';scheme=signinwithapple;end';
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $params = $_POST;
    if ($params === [] && !empty($_SERVER['CONTENT_TYPE'])
        && stripos((string) $_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') !== false
    ) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $params = $parsed;
            }
        }
    }

    if ($params !== []) {
        header('Location: ' . colobanes_apple_android_intent_url($params, $android_package), true, 302);
        exit;
    }

    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Réponse Apple vide.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion Apple — COLObanes</title>
    <style>
        body { font-family: system-ui, sans-serif; text-align: center; padding: 2rem; color: #3564a6; }
    </style>
</head>
<body>
    <p>Retour connexion Apple…</p>
    <p><small>Si l'application ne s'ouvre pas, fermez cet onglet et relancez la connexion depuis COLObanes.</small></p>
</body>
</html>
