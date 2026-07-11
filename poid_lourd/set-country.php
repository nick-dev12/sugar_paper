<?php
/**
 * Enregistre le pays marketplace en session et redirige.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/marketplace_country_filter.php';

$redirect = isset($_POST['redirect']) ? (string) $_POST['redirect'] : (isset($_GET['redirect']) ? (string) $_GET['redirect'] : '/index.php');
$country = isset($_POST['country']) ? (string) $_POST['country'] : (isset($_GET['country']) ? (string) $_GET['country'] : '');

if ($redirect === '' || strpos($redirect, '/') !== 0 || strpos($redirect, '//') === 0) {
    $redirect = '/index.php';
}

if ($country !== '' && marketplace_country_is_valid($country)) {
    marketplace_set_selected_country($country, true);
}

header('Location: ' . $redirect, true, 303);
exit;
