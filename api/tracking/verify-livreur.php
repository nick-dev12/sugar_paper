<?php
/**
 * Vérification token livreur — appel interne Node.js uniquement
 * POST JSON : token, internal_secret
 */
require_once __DIR__ . '/../../includes/tracking_config.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tracking_json_response(['valid' => false, 'error' => 'method_not_allowed'], 405);
}

if (!tracking_verify_internal_request()) {
    tracking_json_response(['valid' => false, 'error' => 'unauthorized'], 403);
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

$token = trim((string) ($input['token'] ?? ''));
if ($token === '') {
    tracking_json_response(['valid' => false, 'error' => 'token_required'], 400);
}

$session = livreur_get_session_by_token($token);
if (!$session) {
    tracking_json_response(['valid' => false, 'error' => 'invalid_token'], 401);
}

$commande_id = $session['commande_id'] ? (int) $session['commande_id'] : null;
$tracking_active = false;
$commande = null;

if ($commande_id) {
    $commande = livreur_get_commande_tracking($commande_id);
    $tracking_active = $commande && (int) ($commande['tracking_active'] ?? 0) === 1;
}

tracking_json_response([
    'valid' => true,
    'livreur_id' => (int) $session['livreur_id'],
    'livreur_nom' => trim(($session['prenom'] ?? '') . ' ' . ($session['nom'] ?? '')),
    'commande_id' => $commande_id,
    'tracking_active' => $tracking_active,
    'numero_commande' => $commande['numero_commande'] ?? null,
]);
