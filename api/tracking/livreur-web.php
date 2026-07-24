<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Suivi GPS web (livreur connecté via session admin)
 * POST JSON : action = start | stop | terminer | position | set_countdown
 * - stop : pause le suivi GPS (sans clôturer la livraison)
 * - terminer : clôture définitive (bouton Terminer uniquement)
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_livreur_gps()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
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
$commande_id = (int) ($input['commande_id'] ?? 0);
$bl_id = (int) ($input['bl_id'] ?? 0);
$admin_id = (int) $_SESSION['admin_id'];

if ($commande_id < 1 && $bl_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'commande_id ou bl_id requis']);
    exit;
}

$cmd_param = $commande_id > 0 ? $commande_id : null;
$bl_param = $bl_id > 0 ? $bl_id : null;

if ($action === 'start') {
    $result = livreur_start_web_tracking($admin_id, $cmd_param, $bl_param);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'tracking_active' => true,
        'countdown' => $result['countdown'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_countdown') {
    $duration_seconds = (int) ($input['duration_seconds'] ?? 0);
    $result = livreur_countdown_init_from_duration($admin_id, $cmd_param, $bl_param, $duration_seconds);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'countdown' => $result['countdown'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'stop') {
    /* Pause uniquement du suivi GPS — ne marque PAS la livraison comme terminée */
    $result = livreur_stop_web_tracking($admin_id, $cmd_param, $bl_param);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur']);
        exit;
    }
    echo json_encode(['success' => true, 'tracking_active' => false, 'terminee' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'terminer') {
    /* Clôture définitive : uniquement via le bouton Terminer */
    $result = livreur_terminer_livraison($admin_id, $cmd_param, $bl_param);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur']);
        exit;
    }
    echo json_encode(['success' => true, 'tracking_active' => false, 'terminee' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'position') {
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $accuracy = $input['accuracy'] ?? null;
    if ($latitude === null || $longitude === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Coordonnées requises']);
        exit;
    }
    $result = livreur_save_web_position($admin_id, $latitude, $longitude, $cmd_param, $bl_param, $accuracy);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Erreur']);
        exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action invalide']);
