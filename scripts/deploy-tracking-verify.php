<?php
/**
 * Vérifications post-déploiement — suivi GPS (PHP + Node + secrets + BDD).
 * Usage : php scripts/deploy-tracking-verify.php
 * Code sortie : 0 = OK, 1 = erreurs bloquantes
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

function deploy_verify_ok(string $msg): void
{
    echo "  OK   {$msg}\n";
}

function deploy_verify_warn(string $msg): void
{
    global $warnings;
    $warnings[] = $msg;
    echo "  WARN {$msg}\n";
}

function deploy_verify_fail(string $msg): void
{
    global $errors;
    $errors[] = $msg;
    echo "  FAIL {$msg}\n";
}

function deploy_verify_load_env(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $out[$key] = trim($val, " \t\"'");
    }
    return $out;
}

function deploy_verify_http_post(string $url, array $payload, string $hostHeader, string $secret): ?array
{
    $body = json_encode(array_merge($payload, ['internal_secret' => $secret]), JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'X-Tracking-Secret: ' . $secret,
                'Host: ' . $hostHeader,
            ]),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 12,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}

echo "=== Vérification suivi GPS ===\n\n";

/* --- Fichiers config --- */
$tracking_php = $root . '/config/tracking.php';
$tracking_env = $root . '/tracking-server/.env';
$conn_php = $root . '/conn/conn.php';

if (!is_file($tracking_php)) {
    deploy_verify_fail('config/tracking.php manquant (copiez config/tracking.example.php)');
} else {
    deploy_verify_ok('config/tracking.php présent');
}

if (!is_file($tracking_env)) {
    deploy_verify_fail('tracking-server/.env manquant (copiez .env.example)');
} else {
    deploy_verify_ok('tracking-server/.env présent');
}

if (!is_file($conn_php)) {
    deploy_verify_fail('conn/conn.php manquant');
} else {
    deploy_verify_ok('conn/conn.php présent');
}

$php_secret = '';
$php_port = 0;
$php_socket_path = '/socket.io';
$public_url = 'https://sugar-paper.com';

if (is_file($tracking_php)) {
    require_once $root . '/includes/tracking_config.php';
    $php_secret = tracking_internal_secret();
    $php_port = (int) tracking_config_get('node_port', 0);
    $php_socket_path = (string) tracking_config_get('socket_path', '/socket.io');
    $public_url = rtrim((string) tracking_config_get('public_site_url', $public_url), '/');

    if ($php_secret === '' || $php_secret === 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE') {
        deploy_verify_fail('internal_secret PHP invalide ou placeholder');
    } else {
        deploy_verify_ok('internal_secret PHP configuré (' . strlen($php_secret) . ' car.)');
    }

    if (tracking_realtime_available()) {
        deploy_verify_ok('tracking_realtime_available() = oui');
    } else {
        deploy_verify_fail('tracking_realtime_available() = non (secret ou node_port)');
    }
}

$env = deploy_verify_load_env($tracking_env);
$node_secret = trim((string) ($env['TRACKING_INTERNAL_SECRET'] ?? ''));
$node_port = (int) ($env['TRACKING_PORT'] ?? 0);
$php_base = rtrim((string) ($env['TRACKING_PHP_BASE'] ?? 'http://127.0.0.1:8081'), '/');
$php_host = trim((string) ($env['TRACKING_PHP_HOST'] ?? 'sugar-paper.com'));
$node_socket_path = (string) ($env['TRACKING_SOCKET_PATH'] ?? '/socket.io');

if ($node_secret === '' || stripos($node_secret, 'REMPLACEZ') !== false) {
    deploy_verify_fail('TRACKING_INTERNAL_SECRET Node invalide ou placeholder');
} else {
    deploy_verify_ok('TRACKING_INTERNAL_SECRET Node configuré');
}

if ($php_secret !== '' && $node_secret !== '') {
    if (hash_equals($php_secret, $node_secret)) {
        deploy_verify_ok('Secret PHP ↔ Node identique');
    } else {
        deploy_verify_fail('Secret PHP ≠ Node — alignez config/tracking.php et tracking-server/.env');
    }
}

if ($php_port > 0 && $node_port > 0) {
    if ($php_port === $node_port) {
        deploy_verify_ok("Port Node cohérent ({$php_port})");
    } else {
        deploy_verify_fail("Port incohérent : PHP node_port={$php_port}, .env TRACKING_PORT={$node_port}");
    }
}

if ($php_socket_path === $node_socket_path) {
    deploy_verify_ok("socket_path cohérent ({$php_socket_path})");
} else {
    deploy_verify_warn("socket_path PHP ({$php_socket_path}) ≠ Node ({$node_socket_path})");
}

/* --- BDD --- */
if (is_file($conn_php)) {
    require_once $conn_php;
    require_once $root . '/models/model_livreur_tracking.php';

    if (livreur_tracking_tables_ready()) {
        deploy_verify_ok('Tables tracking BDD présentes');
    } else {
        deploy_verify_fail('Tables tracking BDD manquantes — lancez migrations livreur');
    }

    if (function_exists('livreur_watch_token_has_bl_column') && livreur_watch_token_has_bl_column()) {
        deploy_verify_ok('Colonne tracking_watch_tokens.bl_id OK');
    } else {
        deploy_verify_warn('Colonne tracking_watch_tokens.bl_id absente (factures B2B)');
    }
}

/* --- Ping PHP (Node → PHP) --- */
if ($php_secret !== '' && is_file($tracking_php)) {
    $ping_url = $php_base . '/api/tracking/ping.php';
    $ping = deploy_verify_http_post($ping_url, [], $php_host, $php_secret);
    if (is_array($ping) && !empty($ping['ok'])) {
        deploy_verify_ok('API PHP ping (interne) OK — realtime=' . (!empty($ping['realtime']) ? '1' : '0'));
    } else {
        $detail = is_array($ping) ? json_encode($ping, JSON_UNESCAPED_UNICODE) : 'pas de réponse';
        deploy_verify_fail("API PHP ping échec ({$ping_url}) → {$detail}");
    }
}

/* --- Health Node local --- */
$health_port = $node_port > 0 ? $node_port : ($php_port > 0 ? $php_port : 3001);
$health_url = "http://127.0.0.1:{$health_port}/health";
$health_ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$health_raw = @file_get_contents($health_url, false, $health_ctx);
if ($health_raw !== false) {
    $health = json_decode($health_raw, true);
    if (is_array($health) && !empty($health['ok'])) {
        deploy_verify_ok("Serveur Node health OK ({$health_url})");
    } else {
        deploy_verify_fail("Serveur Node health réponse invalide : " . trim((string) $health_raw));
    }
} else {
    deploy_verify_fail("Serveur Node injoignable ({$health_url}) — pm2 status sugar-tracking");
}

/* --- Site public --- */
$site_ctx = stream_context_create([
    'http' => ['timeout' => 12, 'ignore_errors' => true],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$site_raw = @file_get_contents($public_url . '/', false, $site_ctx);
if ($site_raw !== false && strlen($site_raw) > 200) {
    deploy_verify_ok("Site public répond ({$public_url})");
} else {
    deploy_verify_warn("Site public {$public_url} — réponse faible ou timeout (vérifiez Apache/Nginx)");
}

/* --- Socket.io public (polling) --- */
$socket_probe = $public_url . $php_socket_path . '/?EIO=4&transport=polling';
$socket_raw = @file_get_contents($socket_probe, false, $site_ctx);
if ($socket_raw !== false && (strpos($socket_raw, 'sid') !== false || strpos($socket_raw, '0{') === 0)) {
    deploy_verify_ok('Socket.io public accessible (transport polling)');
} else {
    deploy_verify_warn("Socket.io public non confirmé ({$socket_probe}) — proxy Nginx /socket.io ?");
}

echo "\n=== Résumé ===\n";
echo '  Erreurs   : ' . count($errors) . "\n";
echo '  Avertiss. : ' . count($warnings) . "\n";

if ($errors) {
    echo "\nActions suggérées :\n";
    echo "  - Vérifiez config/tracking.php et tracking-server/.env (même internal_secret)\n";
    echo "  - pm2 restart sugar-tracking && pm2 save\n";
    echo "  - curl http://127.0.0.1:{$health_port}/health\n";
    echo "  - pm2 logs sugar-tracking --lines 30\n";
    exit(1);
}

echo "\nSuivi GPS : configuration OK.\n";
exit(0);
