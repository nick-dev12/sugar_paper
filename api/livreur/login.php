<?php
/**
 * API connexion livreur (app mobile)
 * POST JSON : email, password, device_info (optionnel)
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if (!livreur_tracking_tables_ready()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Module suivi livreur non installé']);
    exit;
}

$input = $_POST;
$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');
$device_info = trim((string) ($input['device_info'] ?? ''));

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
    exit;
}

$livreur = livreur_authenticate($email, $password);
if (!$livreur) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Identifiants invalides']);
    exit;
}

$session = livreur_create_session((int) $livreur['id'], null, $device_info ?: null);
if (!$session) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossible de créer la session']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Connexion réussie',
    'token' => $session['token'],
    'expires_at' => $session['expires_at'],
    'livreur' => [
        'id' => (int) $livreur['id'],
        'nom' => $livreur['nom'],
        'prenom' => $livreur['prenom'],
        'email' => $livreur['email'],
        'telephone' => $livreur['telephone'] ?? null,
    ],
    'socket_path' => tracking_config_get('socket_path', '/socket.io'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
