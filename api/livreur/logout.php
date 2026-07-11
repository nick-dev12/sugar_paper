<?php
/**
 * API déconnexion livreur
 * POST JSON : token
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$input = $_POST;
$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$token = trim((string) ($input['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token requis']);
    exit;
}

$ok = livreur_revoke_session($token);
echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Déconnexion effectuée' : 'Token introuvable',
]);
