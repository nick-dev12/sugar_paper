<?php
/**
 * Livraison GPS active pour le client connecté (popup site / app).
 */
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$data = livreur_client_active_tracking_popup_data((int) $_SESSION['user_id']);
if (!$data) {
    echo json_encode(['success' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'active' => true,
    'delivery' => $data,
], JSON_UNESCAPED_UNICODE);
