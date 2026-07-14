<?php
/**
 * Positions des livreurs actuellement en livraison GPS (admin).
 * GET JSON — session admin requise.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if (!admin_can_manage_livreurs() && !admin_can_livreur_gps() && !admin_can_view_livreurs_map()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé'], JSON_UNESCAPED_UNICODE);
    exit;
}

$livreurs = livreur_get_actifs_sur_carte();

echo json_encode([
    'success' => true,
    'count' => count($livreurs),
    'livreurs' => $livreurs,
    'updated_at' => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
