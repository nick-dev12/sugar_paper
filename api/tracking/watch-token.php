<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Génère un token watch pour l'admin (session requise)
 * GET/POST : commande_id ou bl_id
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$commande_id = (int) ($_GET['commande_id'] ?? $_POST['commande_id'] ?? 0);
$bl_id = (int) ($_GET['bl_id'] ?? $_POST['bl_id'] ?? 0);

if ($bl_id > 0) {
    $facture = livreur_get_facture_tracking($bl_id);
    if (!$facture) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Facture introuvable']);
        exit;
    }

    $watch = livreur_create_watch_token(null, 'admin', (int) $_SESSION['admin_id'], null, $bl_id);
    if (!$watch) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de générer le token']);
        exit;
    }

    $last = null;
    if (!empty($facture['livreur_id'])) {
        $last = livreur_get_last_position((int) $facture['livreur_id'], null, $bl_id);
    }

    echo json_encode([
        'success' => true,
        'watch_token' => $watch['token'],
        'expires_at' => $watch['expires_at'],
        'livraison_type' => 'facture',
        'facture' => [
            'id' => $bl_id,
            'numero_bl' => $facture['numero_bl'] ?? '',
            'tracking_active' => (int) ($facture['tracking_active'] ?? 0),
            'adresse_livraison' => $facture['adresse_livraison'] ?? '',
            'delivery_latitude' => livreur_parse_coord($facture['delivery_latitude'] ?? null),
            'delivery_longitude' => livreur_parse_coord($facture['delivery_longitude'] ?? null),
            'livreur_nom' => trim(($facture['livreur_prenom'] ?? '') . ' ' . ($facture['livreur_nom'] ?? '')),
        ],
        'commande' => [
            'id' => $bl_id,
            'numero_commande' => $facture['numero_bl'] ?? '',
            'tracking_active' => (int) ($facture['tracking_active'] ?? 0),
            'adresse_livraison' => $facture['adresse_livraison'] ?? '',
            'delivery_latitude' => livreur_parse_coord($facture['delivery_latitude'] ?? null),
            'delivery_longitude' => livreur_parse_coord($facture['delivery_longitude'] ?? null),
            'livreur_nom' => trim(($facture['livreur_prenom'] ?? '') . ' ' . ($facture['livreur_nom'] ?? '')),
        ],
        'last_position' => $last,
        'socket_path' => tracking_config_get('socket_path', '/socket.io'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($commande_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'commande_id ou bl_id requis']);
    exit;
}

$commande = livreur_get_commande_tracking($commande_id);
if (!$commande) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
    exit;
}

$watch = livreur_create_watch_token($commande_id, 'admin', (int) $_SESSION['admin_id'], null);
if (!$watch) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossible de générer le token']);
    exit;
}

$last = null;
if (!empty($commande['livreur_id'])) {
    $last = livreur_get_last_position((int) $commande['livreur_id'], $commande_id);
}

echo json_encode([
    'success' => true,
    'watch_token' => $watch['token'],
    'expires_at' => $watch['expires_at'],
    'livraison_type' => 'commande',
    'commande' => [
        'id' => $commande_id,
        'numero_commande' => $commande['numero_commande'],
        'tracking_active' => (int) ($commande['tracking_active'] ?? 0),
        'adresse_livraison' => $commande['adresse_livraison'] ?? '',
        'delivery_latitude' => livreur_parse_coord($commande['delivery_latitude'] ?? null),
        'delivery_longitude' => livreur_parse_coord($commande['delivery_longitude'] ?? null),
        'livreur_nom' => trim(($commande['livreur_prenom'] ?? '') . ' ' . ($commande['livreur_nom'] ?? '')),
    ],
    'last_position' => $last,
    'socket_path' => tracking_config_get('socket_path', '/socket.io'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
