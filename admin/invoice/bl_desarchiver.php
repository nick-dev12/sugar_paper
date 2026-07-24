<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_is_full_admin()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: archives.php?tab=facture');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée.';
    header('Location: archives.php?tab=facture');
    exit;
}

$bl_id = (int) ($_POST['bl_id'] ?? 0);
require_once __DIR__ . '/../../models/model_bl.php';

$result = unarchive_bl($bl_id);
if (!empty($result['ok'])) {
    $_SESSION['success_message'] = 'Facture désarchivée.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$_SESSION['bl_erreur'] = $result['error'] ?? 'Désarchivage impossible.';
header('Location: archives.php?tab=facture');
exit;
