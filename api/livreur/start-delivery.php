<?php
/**
 * API démarrer une livraison (suivi GPS actif)
 * POST JSON : token, commande_id
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

if ($token === '' || $commande_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token et commande_id requis']);
    exit;
}

$session = livreur_get_session_by_token($token);
if (!$session) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalide']);
    exit;
}

$commande = livreur_get_commande_tracking($commande_id);
if (!$commande) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
    exit;
}

if (in_array($commande['statut'] ?? '', ['livree', 'paye', 'annulee'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Commande non éligible au suivi']);
    exit;
}

$livreur_id = (int) $session['livreur_id'];
if (!empty($commande['livreur_id']) && (int) $commande['livreur_id'] !== $livreur_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Cette commande est assignée à un autre livreur']);
    exit;
}

if (!livreur_start_tracking($commande_id, $livreur_id)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossible de démarrer le suivi']);
    exit;
}

livreur_update_session_commande($token, $commande_id);

echo json_encode([
    'success' => true,
    'message' => 'Suivi démarré',
    'commande' => [
        'id' => $commande_id,
        'numero_commande' => $commande['numero_commande'],
        'adresse_livraison' => $commande['adresse_livraison'] ?? '',
        'delivery_latitude' => livreur_parse_coord($commande['delivery_latitude'] ?? null),
        'delivery_longitude' => livreur_parse_coord($commande['delivery_longitude'] ?? null),
    ],
    'room' => 'commande_' . $commande_id,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
