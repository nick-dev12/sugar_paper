<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=facture');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée.';
    header('Location: index.php?tab=facture');
    exit;
}

$bl_id = (int) ($_POST['bl_id'] ?? 0);
require_once __DIR__ . '/../../models/model_bl.php';

$result = archive_bl($bl_id, (int) ($_SESSION['admin_id'] ?? 0));
if (!empty($result['ok'])) {
    $_SESSION['success_message'] = 'Facture archivée. Le lien public reste disponible pour le client.';
} else {
    $_SESSION['bl_erreur'] = $result['error'] ?? 'Archivage impossible.';
}

header('Location: index.php?tab=facture');
exit;
