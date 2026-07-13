<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Prise en charge d'une commande ou facture (livreur admin connecté)
 * POST JSON : action = prendre_commande | prendre_facture, commande_id ou bl_id
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_livreur_gps()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé'], JSON_UNESCAPED_UNICODE);
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

$action = trim((string) ($input['action'] ?? ''));
$admin_id = (int) $_SESSION['admin_id'];
$admin_role = admin_current_role();
$require_today = ($admin_role === 'livreur');

if ($action === 'prendre_commande') {
    $commande_id = (int) ($input['commande_id'] ?? 0);
    if ($commande_id < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'commande_id requis'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = livreur_prendre_commande($commande_id, $admin_id, $require_today);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Commande prise en charge.',
        'commande_id' => (int) ($result['commande_id'] ?? $commande_id),
        'already' => !empty($result['already']),
        'suivi_url' => 'suivi.php?commande_id=' . (int) ($result['commande_id'] ?? $commande_id) . '&autostart=1',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'prendre_facture') {
    $bl_id = (int) ($input['bl_id'] ?? 0);
    if ($bl_id < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'bl_id requis'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = livreur_prendre_facture($bl_id, $admin_id, $require_today);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Facture prise en charge.',
        'bl_id' => (int) ($result['bl_id'] ?? $bl_id),
        'already' => !empty($result['already']),
        'suivi_url' => 'suivi.php?bl_id=' . (int) ($result['bl_id'] ?? $bl_id) . '&autostart=1',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'annuler_commande') {
    $commande_id = (int) ($input['commande_id'] ?? 0);
    if ($commande_id < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'commande_id requis'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = livreur_abandonner_prise_commande($commande_id, $admin_id);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Prise en charge annulée.',
        'commande_id' => (int) ($result['commande_id'] ?? $commande_id),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'annuler_facture') {
    $bl_id = (int) ($input['bl_id'] ?? 0);
    if ($bl_id < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'bl_id requis'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = livreur_abandonner_prise_facture($bl_id, $admin_id);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Prise en charge annulée.',
        'bl_id' => (int) ($result['bl_id'] ?? $bl_id),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action invalide'], JSON_UNESCAPED_UNICODE);
