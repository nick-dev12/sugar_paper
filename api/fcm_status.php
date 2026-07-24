<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Statut FCM du compte connecté (admin ou client)
 * GET — indique si CE compte a des tokens enregistrés
 */

session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../models/model_fcm.php';

$response = [
    'success' => true,
    'enabled' => false,
    'type' => '',
    'account_id' => 0,
    'token_count' => 0,
    'eligible' => false,
];

if (isset($_SESSION['admin_id'])) {
    $admin_id = (int) $_SESSION['admin_id'];
    $response['type'] = 'admin';
    $response['account_id'] = $admin_id;
    $response['eligible'] = fcm_admin_is_eligible_for_notify($admin_id);
    $tokens = get_fcm_tokens_by_admin($admin_id);
    $response['token_count'] = count($tokens);
    $response['enabled'] = $response['eligible'] && $response['token_count'] > 0;
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
    $response['type'] = 'user';
    $response['account_id'] = $user_id;
    $response['eligible'] = true;
    $tokens = get_fcm_tokens_by_user($user_id);
    $response['token_count'] = count($tokens);
    $response['enabled'] = $response['token_count'] > 0;
} else {
    $response['success'] = false;
    $response['message'] = 'Non connecté';
}

echo json_encode($response);
