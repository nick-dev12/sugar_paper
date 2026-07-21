<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page de test des notifications push (Admin)
 * - mode=moi : envoi à l'admin connecté uniquement
 * - mode=tous : envoi à tous les comptes admin + utilisateur (comme une vraie commande)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin_permissions.php';
require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/../services/firebase_push.php';

$admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
$mode = isset($_GET['mode']) ? trim((string) $_GET['mode']) : 'moi';

if ($mode === 'tous') {
    if (!in_array($admin_role, ['admin', 'utilisateur'], true)) {
        $_SESSION['notification_test_message'] = 'Action réservée aux rôles admin et utilisateur.';
        $_SESSION['notification_test_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }
    $tokens = get_all_fcm_tokens_admin();
    $scope_label = count($tokens) . ' appareil(s) enregistré(s) (admin + utilisateur)';
} else {
    if (!fcm_admin_is_eligible_for_notify((int) $_SESSION['admin_id'])) {
        $_SESSION['notification_test_message'] = 'Votre rôle ne peut pas recevoir les alertes commandes.';
        $_SESSION['notification_test_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }
    $tokens = get_fcm_tokens_by_admin((int) $_SESSION['admin_id']);
    $scope_label = 'votre compte';
}

if (empty($tokens)) {
    $_SESSION['notification_test_message'] = 'Aucun token enregistré pour ' . $scope_label . '. '
        . 'Chaque personne doit cliquer sur « Notifications » dans le menu admin sur chaque appareil (une seule fois).';
    $_SESSION['notification_test_type'] = 'error';
} else {
    $result = firebase_send_notification(
        $tokens,
        'Test Sugar Paper',
        'Notification de test — les alertes fonctionnent même si le site est fermé (Service Worker).',
        ['link' => '/admin/dashboard.php', 'tag' => 'test']
    );
    if ($result['success'] > 0) {
        $_SESSION['notification_test_message'] = 'Notification envoyée à ' . $scope_label
            . ' (' . (int) $result['success'] . ' succès'
            . (!empty($result['errors']) ? ', ' . count($result['errors']) . ' échec(s)' : '')
            . ').';
        $_SESSION['notification_test_type'] = 'success';
    } else {
        $_SESSION['notification_test_message'] = "Échec de l'envoi. " . implode(' ', $result['errors'] ?? []);
        $_SESSION['notification_test_type'] = 'error';
    }
}

header('Location: dashboard.php');
exit;
