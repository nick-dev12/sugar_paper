<?php
/**
 * Endpoint AJAX : connexion/inscription Google ou Apple via Firebase Auth.
 * Réponse toujours en JSON (aucun warning/HTML parasite).
 */
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

@ini_set('display_errors', '0');

$firebase_auth_prev_error_handler = null;
$firebase_auth_prev_error_handler = set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log('[auth-firebase-callback] PHP ' . $severity . ' ' . $message . ' @ ' . $file . ':' . $line);

    return true;
});

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

/**
 * @param array<string, mixed>|null $payload
 */
function firebase_auth_callback_send_json(bool $success, string $message, string $redirect = ''): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        firebase_auth_callback_send_json(false, 'Méthode non autorisée.');
    }

    require_once __DIR__ . '/includes/firebase_auth_flow.php';

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);

    if (!is_array($payload)) {
        firebase_auth_callback_send_json(false, 'Requête invalide.');
    }

    firebase_auth_process_callback($payload);
} catch (Throwable $e) {
    error_log('[auth-firebase-callback] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    firebase_auth_callback_send_json(false, firebase_auth_callback_exception_message($e));
} finally {
    if ($firebase_auth_prev_error_handler !== null) {
        restore_error_handler();
    }
}

/**
 * Message utilisateur à partir d'une exception (sans détail technique en production).
 */
function firebase_auth_callback_exception_message(Throwable $e): string
{
    $detail = $e->getMessage();
    $file = $e->getFile();

    if (stripos($detail, 'Class ') !== false && stripos($detail, 'not found') !== false) {
        return 'Dépendances serveur manquantes (dossier vendor / Composer). Contactez l’administrateur.';
    }
    if (stripos($detail, 'prepare() on null') !== false || stripos($detail, 'on null') !== false) {
        return 'Base de données indisponible. Réessayez plus tard.';
    }
    if (stripos($file, 'firebase_server') !== false || stripos($detail, 'firebase_server') !== false) {
        return 'Configuration Firebase serveur manquante (config/firebase_server.php).';
    }
    if (stripos($detail, 'firebase_google_public_keys') !== false || stripos($detail, 'FetchingGooglePublicKeysFailed') !== false) {
        return 'Vérification Google indisponible. Mettez à jour le cache des clés Firebase sur le serveur.';
    }

    $server_config = null;
    $cfg_path = __DIR__ . '/config/firebase_server.php';
    if (file_exists($cfg_path)) {
        $loaded = require $cfg_path;
        if (is_array($loaded)) {
            $server_config = $loaded;
        }
    }
    if (!empty($server_config['auth_debug'])) {
        return 'Erreur technique : ' . $detail;
    }

    return 'Erreur serveur lors de la connexion. Réessayez dans un instant.';
}
