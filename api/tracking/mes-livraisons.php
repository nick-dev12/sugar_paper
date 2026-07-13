<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Liste des livraisons en cours assignées au livreur connecté (non terminées)
 * GET JSON
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

if (!livreur_tracking_tables_ready()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Module non installé'], JSON_UNESCAPED_UNICODE);
    exit;
}

$only_today = !isset($_GET['all']) || $_GET['all'] !== '1';
$started_only = isset($_GET['started']) && $_GET['started'] === '1';
if ($started_only) {
    $admin_role = admin_current_role();
    if ($admin_role !== 'livreur') {
        $only_today = false;
    }
}
$deliveries = livreur_get_mes_livraisons_for_admin((int) $_SESSION['admin_id'], $only_today, $started_only);

echo json_encode([
    'success' => true,
    'count' => count($deliveries),
    'deliveries' => $deliveries,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
