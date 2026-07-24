<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * API pour enregistrer le token FCM (notifications push)
 * POST: token, type (user|admin)
 * Accepte FormData ou JSON (application/json)
 * Chaque token est lié individuellement au compte connecté (admin_id ou user_id).
 */

session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'type' => '', 'account_id' => 0];

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

$page_context = isset($input['page_context']) ? trim((string) $input['page_context']) : '';

// Priorité session : si type=user et session user active, ne pas basculer en admin
// (évite qu'un token client soit rattaché à un admin sur le même navigateur)
if ($type === 'user' && isset($_SESSION['user_id'])) {
    // garder type user
} elseif ($type === 'user' && isset($_SESSION['admin_id']) && $page_context !== '') {
    $ctx = strtolower($page_context);
    if (strpos($ctx, '/admin') !== false || strpos($ctx, 'admin/') === 0 || $page_context === 'admin') {
        $type = 'admin';
    }
}

// App mobile : type=user reçu alors qu'une session admin existe sans compte client
if ($type === 'user' && !isset($_SESSION['user_id']) && isset($_SESSION['admin_id'])) {
    $type = 'admin';
}

// Priorité session admin pour les activations explicitement admin
if ($type === 'admin' && !isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) {
    $response['message'] = 'Session administrateur requise pour activer les alertes admin.';
    echo json_encode($response);
    exit;
}

if (empty($token) || !in_array($type, ['user', 'admin'], true)) {
    $response['message'] = 'Paramètres invalides';
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/../models/model_fcm.php';

if ($type === 'user') {
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $user_id = (int) $_SESSION['user_id'];
    $admin_id = null;
    $account_id = $user_id;
} else {
    if (!isset($_SESSION['admin_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $admin_id = (int) $_SESSION['admin_id'];
    $user_id = null;
    $account_id = $admin_id;

    if (!fcm_admin_is_eligible_for_notify($admin_id)) {
        $response['message'] = 'Votre rôle ne permet pas de recevoir les alertes commandes.';
        echo json_encode($response);
        exit;
    }
}

if (save_fcm_token($token, $type, $user_id, $admin_id)) {
    // Relier aussi d'éventuels orphelins du même token
    if ($type === 'admin') {
        fcm_relink_orphan_admin_token($admin_id, $token);
        // Abonner ce token au topic alertes commandes (tous les admins/utilisateurs)
        require_once __DIR__ . '/../services/firebase_push.php';
        if (fcm_admin_is_eligible_for_notify((int) $admin_id)) {
            firebase_fcm_subscribe_admin_topic([$token]);
        } else {
            firebase_fcm_unsubscribe_admin_topic([$token]);
        }
    }

    $response['success'] = true;
    $response['message'] = 'Notifications activées pour votre compte';
    $response['type'] = $type;
    $response['account_id'] = $account_id;
    $response['token_count'] = $type === 'admin'
        ? count(get_fcm_tokens_by_admin($admin_id))
        : count(get_fcm_tokens_by_user($user_id));
} else {
    $response['message'] = 'Erreur lors de l\'enregistrement du token pour votre compte.';
}

echo json_encode($response);
