<?php
/**
 * API profil livreur connecté
 * GET/POST : token (Authorization: Bearer … ou paramètre token)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if (!livreur_tracking_tables_ready()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Module suivi livreur non installé']);
    exit;
}

$token = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
    $token = $m[1];
}
if ($token === '') {
    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
}

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token requis']);
    exit;
}

$session = livreur_get_session_by_token($token);
if (!$session) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalide ou expirée']);
    exit;
}

$active_commande = null;
if (!empty($session['commande_id'])) {
    $active_commande = livreur_get_commande_tracking((int) $session['commande_id']);
}

$last_position = livreur_get_last_position((int) $session['livreur_id'], $session['commande_id'] ? (int) $session['commande_id'] : null);

echo json_encode([
    'success' => true,
    'livreur' => [
        'id' => (int) $session['livreur_id'],
        'nom' => $session['nom'],
        'prenom' => $session['prenom'],
        'email' => $session['email'],
    ],
    'active_commande' => $active_commande ? [
        'id' => (int) $active_commande['id'],
        'numero_commande' => $active_commande['numero_commande'],
        'statut' => $active_commande['statut'],
        'tracking_active' => (int) ($active_commande['tracking_active'] ?? 0),
        'adresse_livraison' => $active_commande['adresse_livraison'] ?? '',
        'delivery_latitude' => livreur_parse_coord($active_commande['delivery_latitude'] ?? null),
        'delivery_longitude' => livreur_parse_coord($active_commande['delivery_longitude'] ?? null),
    ] : null,
    'last_position' => $last_position,
    'socket_path' => tracking_config_get('socket_path', '/socket.io'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
