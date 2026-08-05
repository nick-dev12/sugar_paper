<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Enregistrement note client sur livreur (page suivi public tokenisée).
 * POST JSON : token, commande_id ou bl_id, note (1-5)
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../models/model_livreur_notes.php';

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
$bl_id = (int) ($input['bl_id'] ?? 0);
$note = (int) ($input['note'] ?? 0);

if ($token === '' || ($commande_id < 1 && $bl_id < 1)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($note < 1 || $note > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Choisissez une note entre 1 et 5 étoiles.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!livreur_tracking_tables_ready() || !livreur_notes_tables_ready()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Service indisponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token_row = livreur_get_watch_token_row(
    $token,
    $commande_id > 0 ? $commande_id : null,
    $bl_id > 0 ? $bl_id : null
);
if (!$token_row) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Lien expiré ou invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$livraison = false;
$livraison_type = '';
if ($bl_id > 0) {
    $livraison = livreur_get_facture_tracking($bl_id);
    $livraison_type = 'facture';
} else {
    $livraison = livreur_get_commande_tracking($commande_id);
    $livraison_type = 'commande';
}

if (!$livraison) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Livraison introuvable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!livreur_note_peut_noter($livraison, $livraison_type)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La notation sera disponible lorsque le livreur aura confirmé son arrivée.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$livreur_id = (int) ($livraison['livreur_id'] ?? 0);
if ($livreur_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun livreur associé à cette livraison.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$client_snapshot = livreur_note_build_client_snapshot($livraison, $livraison_type);
$result = livreur_note_enregistrer(
    $livreur_id,
    $note,
    $livraison_type,
    $commande_id > 0 ? $commande_id : null,
    $bl_id > 0 ? $bl_id : null,
    $client_snapshot
);

if (empty($result['ok'])) {
    $code = !empty($result['note']) ? 409 : 400;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Erreur',
        'note' => isset($result['note']) ? (int) $result['note'] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Merci pour votre note !',
    'note' => (int) $result['note'],
    'moyenne_livreur' => $result['moyenne_livreur'],
    'nb_notes_livreur' => (int) ($result['nb_notes_livreur'] ?? 0),
], JSON_UNESCAPED_UNICODE);
