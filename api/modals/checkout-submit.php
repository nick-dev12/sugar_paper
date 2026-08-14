<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/checkout_modals_data.php';
require_once __DIR__ . '/../../controllers/controller_commandes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    modal_json_response(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

$_POST['action'] = 'create_commande';
$result = process_create_commande();

if (!empty($result['success'])) {
    require_once __DIR__ . '/../../services/notifications_order_dispatch.php';
    notifications_dispatch_after_commande($result);
    $success = checkout_modals_render_success($result['numero_commande'] ?? '');
    modal_json_response([
        'ok' => true,
        'html' => $success['html'],
        'numero' => $success['numero'],
        'count' => 0,
    ]);
}

$rendered = checkout_modals_render_checkout($result['message'] ?? 'Impossible de créer la commande.', 'error');
modal_json_response([
    'ok' => false,
    'html' => $rendered['html'] ?? '',
    'count' => $rendered['count'] ?? 0,
    'panier_total' => $rendered['panier_total'] ?? 0,
    'message' => $result['message'] ?? '',
]);
