<?php
/**
 * Diagnostic connexion Google/Apple (serveur).
 * Accès : connecté en super admin OU ?key=VOTRE_CLE dans firebase_server.php (auth_diagnose_key).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$root = dirname(__DIR__);
$checks = [];
$ok = true;

function diag_add(array &$checks, bool &$ok, string $id, bool $pass, string $label, string $hint = ''): void
{
    $checks[] = [
        'id' => $id,
        'ok' => $pass,
        'label' => $label,
        'hint' => $hint,
    ];
    if (!$pass) {
        $ok = false;
    }
}

// Garde d'accès minimale
$allowed = false;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['super_admin_id'])) {
    $allowed = true;
}

$cfg_path = $root . '/config/firebase_server.php';
$cfg = is_file($cfg_path) ? require $cfg_path : [];
if (!$allowed && is_array($cfg) && !empty($cfg['auth_diagnose_key'])) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if ($key !== '' && hash_equals((string) $cfg['auth_diagnose_key'], $key)) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
    exit;
}

diag_add($checks, $ok, 'php', PHP_VERSION_ID >= 80000, 'PHP >= 8.0', 'Version : ' . PHP_VERSION);

$autoload = $root . '/vendor/autoload.php';
diag_add($checks, $ok, 'vendor', is_file($autoload), 'vendor/autoload.php présent', 'Exécutez : composer install');

if (is_file($autoload)) {
    require_once $autoload;
    diag_add($checks, $ok, 'jwt', class_exists(\Kreait\Firebase\JWT\IdTokenVerifier::class), 'Librairie Kreait JWT');
}

diag_add($checks, $ok, 'firebase_server', is_file($cfg_path), 'config/firebase_server.php présent', 'Copiez firebase_server.example.php');

$cred = is_array($cfg) ? ($cfg['credentials_path'] ?? '') : '';
if ($cred !== '' && $cred[0] !== '/' && $cred[1] !== ':') {
    $cred = $root . '/' . ltrim(str_replace('\\', '/', $cred), '/');
}
diag_add($checks, $ok, 'credentials', $cred !== '' && is_file($cred), 'Clé de service Firebase (JSON)', (string) $cred);

$cacert = is_array($cfg) ? ($cfg['cacert_path'] ?? $root . '/config/cacert.pem') : $root . '/config/cacert.pem';
if ($cacert !== '' && $cacert[0] !== '/' && $cacert[1] !== ':') {
    $cacert = $root . '/' . ltrim(str_replace('\\', '/', $cacert), '/');
}
diag_add($checks, $ok, 'cacert', is_file($cacert), 'config/cacert.pem présent');

$cache = $root . '/config/firebase_google_public_keys_cache.json';
diag_add($checks, $ok, 'keys_cache', is_file($cache), 'Cache clés Google (firebase_google_public_keys_cache.json)', 'php scripts/sync_firebase_google_keys_cache.php');

require_once $root . '/conn/conn.php';
$db_ok = isset($db) && $db instanceof PDO;
diag_add($checks, $ok, 'database', $db_ok, 'Connexion PDO MySQL');

if ($db_ok) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'firebase_uid'");
        $has_uid = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        diag_add($checks, $ok, 'users_firebase_uid', $has_uid, 'Colonne users.firebase_uid', 'Migration Firebase / Google identity');
    } catch (Throwable $e) {
        diag_add($checks, $ok, 'users_firebase_uid', false, 'Colonne users.firebase_uid', $e->getMessage());
    }
}

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Diagnostic OK — la connexion sociale devrait fonctionner.' : 'Des éléments manquent ou sont incorrects.',
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
