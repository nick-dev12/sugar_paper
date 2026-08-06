<?php
/**
 * Checkout invité — préparation (étape 1) ou authentification PIN (étape 2).
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../controllers/controller_users.php';
require_once __DIR__ . '/../includes/guest_checkout_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$checkout_action = isset($_POST['checkout_action']) ? trim((string) $_POST['checkout_action']) : 'go_commande';
$return_url = guest_checkout_safe_redirect($_POST['return_url'] ?? '/panier.php', '/panier.php');
$guest_step = isset($_POST['guest_step']) ? trim((string) $_POST['guest_step']) : 'auth';

if ($guest_step === 'prepare') {
    $result = process_guest_checkout_prepare();
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    if (!$result['success']) {
        header('Location: ' . $return_url . $sep . 'guest_error=' . urlencode($result['message']));
        exit;
    }
    header('Location: ' . $return_url . $sep . 'guest_checkout=pin');
    exit;
}

$result = process_guest_checkout_auth();
if (!$result['success']) {
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $sep . 'guest_checkout=pin&guest_error=' . urlencode($result['message']));
    exit;
}

if ($checkout_action === 'add_to_panier') {
    require_once __DIR__ . '/../controllers/controller_panier.php';
    $add_result = process_add_to_panier();
    if ($add_result['success']) {
        header('Location: /panier.php?added=1');
        exit;
    }
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $sep . 'guest_error=' . urlencode($add_result['message']));
    exit;
}

header('Location: /commande.php');
exit;
