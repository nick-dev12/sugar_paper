<?php
/**
 * API arrêter le suivi GPS d'une livraison
 * POST JSON : token, commande_id (optionnel — utilise la commande active)
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
$commande_id = (int) ($input['commande_id'] ?? 0);

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token requis']);
    exit;
}

$session = livreur_get_session_by_token($token);
if (!$session) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalide']);
    exit;
}

if ($commande_id < 1 && !empty($session['commande_id'])) {
    $commande_id = (int) $session['commande_id'];
}

if ($commande_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'commande_id requis']);
    exit;
}

$commande = livreur_get_commande_tracking($commande_id);
if (!$commande) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
    exit;
}

if ((int) ($commande['livreur_id'] ?? 0) !== (int) $session['livreur_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

livreur_stop_tracking($commande_id);
livreur_update_session_commande($token, null);

echo json_encode([
    'success' => true,
    'message' => 'Suivi arrêté',
    'commande_id' => $commande_id,
]);
