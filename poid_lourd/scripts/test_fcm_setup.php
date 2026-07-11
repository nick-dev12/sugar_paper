<?php
/**
 * Test complet configuration FCM (CLI ou navigateur)
 * Usage : php scripts/test_fcm_setup.php
 */
require_once __DIR__ . '/../services/firebase_push.php';
require_once __DIR__ . '/../includes/fcm_vapid_validate.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$errors = [];
$warnings = [];
$ok = [];

function out($msg)
{
    echo $msg . PHP_EOL;
}

function check($label, $pass, $detail = '')
{
    global $errors, $ok;
    if ($pass) {
        $ok[] = $label;
        out('[OK]   ' . $label . ($detail !== '' ? ' — ' . $detail : ''));
    } else {
        $errors[] = $label;
        out('[FAIL] ' . $label . ($detail !== '' ? ' — ' . $detail : ''));
    }
}

function warn($label, $detail = '')
{
    global $warnings;
    $warnings[] = $label;
    out('[WARN] ' . $label . ($detail !== '' ? ' — ' . $detail : ''));
}

out('=================================================');
out('  TEST CONFIGURATION FIREBASE CLOUD MESSAGING');
out('  Projet unifié : gestion-scolaire-6945a');
out('=================================================');
out('');

/* --- Fichiers requis --- */
$frontendPath = __DIR__ . '/../config/firebase_config.php';
$serverPath = __DIR__ . '/../config/firebase_server.php';
$swPath = __DIR__ . '/../firebase-messaging-sw.js';

check('config/firebase_config.php existe', file_exists($frontendPath));
check('config/firebase_server.php existe', file_exists($serverPath));
check('firebase-messaging-sw.js existe', file_exists($swPath));

if (!file_exists($frontendPath) || !file_exists($serverPath)) {
    out('');
    out('Configuration incomplète — arrêt.');
    exit(1);
}

$frontend = require $frontendPath;
$server = require $serverPath;

$requiredFrontend = ['apiKey', 'authDomain', 'projectId', 'storageBucket', 'messagingSenderId', 'appId', 'vapidKey'];
foreach ($requiredFrontend as $key) {
    $val = $frontend[$key] ?? '';
    check('Clé frontend "' . $key . '"', $val !== '' && $val !== null, $key === 'vapidKey' ? substr((string) $val, 0, 20) . '…' : (string) $val);
}

$vapidCheck = fcm_validate_vapid_key($frontend['vapidKey'] ?? '');
check('Format clé VAPID (65 octets P-256)', $vapidCheck['valid'], $vapidCheck['message']);

$credentials = $server['credentials_path'] ?? '';
check('Chemin credentials serveur défini', $credentials !== '');
check('Fichier credentials serveur existe', file_exists($credentials), $credentials);

if (!file_exists($credentials)) {
    out('');
    out('Credentials introuvables — arrêt.');
    exit(1);
}

$json = json_decode(file_get_contents($credentials), true);
check('JSON credentials valide', is_array($json) && !empty($json['project_id']));
check('client_email présent', !empty($json['client_email']));
check('private_key présent', !empty($json['private_key']));

$projectId = $json['project_id'] ?? '';
$expectedProject = $frontend['projectId'] ?? '';
check('projectId serveur = ' . $expectedProject, $projectId === $expectedProject, $projectId);
check('projectId frontend = serveur', $expectedProject === $projectId);

/* --- Service Worker synchronisé --- */
$swContent = file_get_contents($swPath);
check('SW contient projectId ' . $expectedProject, strpos($swContent, $expectedProject) !== false);
check('SW contient apiKey frontend', strpos($swContent, $frontend['apiKey']) !== false);
check('SW contient messagingSenderId', strpos($swContent, $frontend['messagingSenderId']) !== false);

/* --- Dépendances PHP --- */
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    check('Composer vendor/autoload.php', true);
} else {
    warn('Composer non installé — fallback envoi natif PHP sera utilisé');
}

/* --- OAuth + API FCM --- */
out('');
out('--- Test connexion Google / FCM ---');

$accessToken = firebase_get_access_token($credentials);
check('Token OAuth Google obtenu', !empty($accessToken), $accessToken ? strlen($accessToken) . ' caractères' : 'échec');

if (!$accessToken) {
    out('');
    out('Impossible de contacter Google OAuth — vérifiez le JSON et la connexion internet.');
    exit(1);
}

$result = firebase_send_notification(
    ['token_factice_test_gestion_scolaire'],
    'Test COLObanes',
    'Vérification configuration FCM',
    ['link' => '/', 'tag' => 'test-setup']
);

check('Appel API FCM exécuté', isset($result['success'], $result['failed']));

$apiOk = false;
if (!empty($result['errors'])) {
    $err = strtolower(implode(' ', $result['errors']));
    out('Réponse FCM : ' . implode(' | ', $result['errors']));
    if (
        strpos($err, 'invalid') !== false || strpos($err, 'not found') !== false
        || strpos($err, 'unregistered') !== false || strpos($err, 'not a valid fcm') !== false
    ) {
        $apiOk = true;
        check('API FCM accessible (erreur attendue token factice)', true);
    }
} elseif ($result['success'] > 0) {
    $apiOk = true;
    check('API FCM — envoi réussi', true);
}

if (!$apiOk) {
    check('API FCM accessible', false, 'réponse inattendue');
}

/* --- Checklist manuelle Google Cloud --- */
out('');
out('--- Checklist manuelle (Console Google / Firebase) ---');
out('  [ ] Browser key = celle copiée depuis Google Cloud (Identifiants), pas seulement Firebase SDK');
out('  [ ] Référents HTTP : http://localhost:5000/* et http://127.0.0.1:5000/*');
out('  [ ] APIs activées dans Bibliothèque :');
out('      - Firebase Installations API');
out('      - FCM Registration API');
out('      - Firebase Cloud Messaging API');
out('  [ ] Si la clé est limitée à 25 APIs, ces 3 APIs doivent être dans la liste');
out('  [ ] VAPID Web Push = ' . substr($frontend['vapidKey'], 0, 24) . '…');
out('  [ ] Après changement : F12 > Service Workers > Unregister puis Ctrl+F5');
out('');

/* --- Résumé --- */
out('=================================================');
out('  RÉSUMÉ');
out('=================================================');
out('  OK    : ' . count($ok));
out('  WARN  : ' . count($warnings));
out('  FAIL  : ' . count($errors));
out('');

if (count($errors) > 0) {
    out('ÉCHEC — corrigez les points [FAIL] ci-dessus.');
    exit(1);
}

out('SUCCÈS — configuration serveur FCM opérationnelle.');
out('Testez côté navigateur : user/mon-compte.php > Activer les notifications');
exit(0);
