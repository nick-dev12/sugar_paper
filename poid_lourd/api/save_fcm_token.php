<?php
/**
 * API pour enregistrer le token FCM (notifications push)
 * POST: token, type (user|admin)
 * Accepte FormData ou JSON (application/json) pour l'app mobile
 */


require_once __DIR__ . '/../includes/session_user.php';
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Méthode non autorisée';
    echo json_encode($response);
    exit;
}

$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$input = $_POST;

if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$token = isset($input['token']) ? trim((string) $input['token']) : '';
$type = isset($input['type']) ? trim((string) $input['type']) : '';

if ($type === '' && isset($input['device_type'])) {
    $type = 'user';
}

if (empty($token) || !in_array($type, ['user', 'admin'], true)) {
    $response['message'] = 'Paramètres invalides';
    echo json_encode($response);
    exit;
}

if ($type === 'user') {
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $user_id = (int) $_SESSION['user_id'];
    $admin_id = null;
} else {
    if (!isset($_SESSION['admin_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $admin_id = (int) $_SESSION['admin_id'];
    $user_id = null;
}

require_once __DIR__ . '/../models/model_fcm.php';

if (save_fcm_token($token, $type, $user_id, $admin_id)) {
    $response['success'] = true;
    $response['message'] = 'Notifications activées';
} else {
    $response['message'] = 'Erreur lors de l\'enregistrement';
}

echo json_encode($response);