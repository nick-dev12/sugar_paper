<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/checkout_modals_data.php';
require_once __DIR__ . '/../../controllers/controller_panier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    modal_json_response(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$result = ['success' => false, 'message' => 'Action invalide.'];

if ($action === 'update') {
    $result = process_update_panier();
} elseif ($action === 'delete') {
    $result = process_delete_from_panier();
}

$rendered = checkout_modals_render_cart(
    $result['message'] ?? '',
    !empty($result['success']) ? 'success' : 'error'
);

modal_json_response([
    'ok' => !empty($result['success']),
    'html' => $rendered['html'],
    'count' => $rendered['count'],
    'empty' => !empty($rendered['empty']),
    'logged_in' => !empty($rendered['logged_in']),
    'message' => $result['message'] ?? '',
]);
