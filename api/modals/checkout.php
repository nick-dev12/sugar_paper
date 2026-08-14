<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/checkout_modals_data.php';

$rendered = checkout_modals_render_checkout();

if (!empty($rendered['need_guest'])) {
    modal_json_response([
        'ok' => false,
        'need_guest' => true,
        'html' => $rendered['html'],
        'count' => $rendered['count'] ?? 0,
    ]);
}

if (!empty($rendered['need_cart'])) {
    modal_json_response([
        'ok' => false,
        'need_cart' => true,
        'html' => $rendered['html'],
        'count' => $rendered['count'] ?? 0,
        'message' => 'Votre panier est vide.',
    ]);
}

modal_json_response([
    'ok' => true,
    'html' => $rendered['html'],
    'count' => $rendered['count'],
    'panier_total' => $rendered['panier_total'] ?? 0,
    'logged_in' => true,
]);
