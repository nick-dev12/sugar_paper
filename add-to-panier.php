<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/guest_checkout_auth.php';
require_once __DIR__ . '/includes/modal_json_response.php';
require_once __DIR__ . '/includes/checkout_modals_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['produit_id'])) {
    header('Location: /index.php');
    exit;
}

$result = process_add_to_panier();
$user_logged_in = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$return_url = isset($_POST['return_url']) && $_POST['return_url'] !== '' ? $_POST['return_url'] : '/index.php';
$return_url = guest_checkout_safe_redirect($return_url, '/index.php');

if (modal_request_is_ajax()) {
    if ($result['success']) {
        $rendered = checkout_modals_render_cart($result['message'] ?? 'Produit ajouté au panier.', 'success');
        modal_json_response([
            'ok' => true,
            'open' => 'cart',
            'html' => $rendered['html'],
            'count' => $rendered['count'],
            'empty' => !empty($rendered['empty']),
            'message' => $result['message'] ?? 'Produit ajouté.',
        ]);
    }
    modal_json_response([
        'ok' => false,
        'need_guest' => !$user_logged_in,
        'message' => $result['message'] ?? 'Impossible d\'ajouter au panier.',
    ], 400);
}

if ($result['success']) {
    header('Location: ' . checkout_modals_append_query($return_url, 'open', 'panier'));
    exit;
}

if (!$user_logged_in) {
    header('Location: ' . checkout_modals_append_query($return_url, 'guest_checkout', 'open'));
    exit;
}

header('Location: ' . checkout_modals_append_query($return_url, 'error', $result['message']));
exit;
