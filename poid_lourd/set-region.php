<?php
/**
 * Enregistre la région marketplace en session et redirige.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/marketplace_region_filter.php';

$redirect = isset($_POST['redirect']) ? (string) $_POST['redirect'] : (isset($_GET['redirect']) ? (string) $_GET['redirect'] : '/index.php');
$region = isset($_POST['region']) ? (string) $_POST['region'] : (isset($_GET['region']) ? (string) $_GET['region'] : '');

if ($redirect === '' || strpos($redirect, '/') !== 0 || strpos($redirect, '//') === 0) {
    $redirect = '/index.php';
}

if ($region === '' || $region === 'all') {
    marketplace_clear_selected_region();
} else {
    marketplace_set_selected_region($region);
}

header('Location: ' . $redirect, true, 303);
exit;
