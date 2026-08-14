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
$guest_user_id = (int) ($_SESSION['user_id'] ?? ($result['user']['id'] ?? 0));
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
        $redirect = checkout_modals_after_login_url($return_url);
        if ($is_ajax) {
            modal_json_response([
                'ok' => true,
                'reload' => true,
                'redirect' => $redirect,
                'user_id' => $guest_user_id,
            ]);
        }
        header('Location: ' . $redirect);
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

$redirect = checkout_modals_after_login_url($return_url);
if ($is_ajax) {
    modal_json_response([
        'ok' => true,
        'reload' => true,
        'redirect' => $redirect,
        'user_id' => $guest_user_id,
    ]);
}

header('Location: ' . $redirect);
exit;
