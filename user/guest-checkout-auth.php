<?php
/**
 * Checkout invité — nom + téléphone → inscription/connexion automatique.
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../controllers/controller_users.php';
require_once __DIR__ . '/../includes/guest_checkout_auth.php';
require_once __DIR__ . '/../includes/modal_json_response.php';
require_once __DIR__ . '/../includes/checkout_modals_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$checkout_action = isset($_POST['checkout_action']) ? trim((string) $_POST['checkout_action']) : 'go_commande';
$return_url = guest_checkout_safe_redirect($_POST['return_url'] ?? '/index.php', '/index.php');
$is_ajax = modal_request_is_ajax();

$result = process_guest_checkout_auth();
if (!$result['success']) {
    if ($is_ajax) {
        modal_json_response([
            'ok' => false,
            'message' => $result['message'] ?? 'Impossible de continuer.',
        ], 400);
    }
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $sep . 'guest_checkout=open&guest_error=' . urlencode($result['message']));
    exit;
}

if ($checkout_action === 'add_to_panier') {
    require_once __DIR__ . '/../controllers/controller_panier.php';
    $add_result = process_add_to_panier();
    if ($add_result['success']) {
        if ($is_ajax) {
            $rendered = checkout_modals_render_cart($add_result['message'] ?? 'Produit ajouté au panier.', 'success');
            modal_json_response([
                'ok' => true,
                'open' => 'cart',
                'html' => $rendered['html'],
                'count' => $rendered['count'],
                'empty' => !empty($rendered['empty']),
                'message' => $add_result['message'] ?? 'Produit ajouté au panier.',
            ]);
        }
        header('Location: ' . checkout_modals_append_query($return_url, 'open', 'panier'));
        exit;
    }
    if ($is_ajax) {
        modal_json_response([
            'ok' => false,
            'message' => $add_result['message'] ?? 'Impossible d\'ajouter au panier.',
        ], 400);
    }
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $sep . 'guest_checkout=open&guest_error=' . urlencode($add_result['message']));
    exit;
}

if ($is_ajax) {
    modal_json_response([
        'ok' => true,
        'open' => 'checkout',
        'count' => modal_panier_count(),
    ]);
}

header('Location: ' . checkout_modals_append_query($return_url, 'open', 'commande'));
exit;
