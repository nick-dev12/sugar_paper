<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Archivage / suppression de plusieurs factures en une fois.
 * Archivage : factures payées uniquement. Suppression : factures impayées, compte admin uniquement.
 */
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

$redirect = 'index.php?tab=facture';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée.';
    header('Location: ' . $redirect);
    exit;
}

$action = (string) ($_POST['action_groupee'] ?? '');
if (!in_array($action, ['archiver', 'supprimer'], true)) {
    $_SESSION['bl_erreur'] = 'Action inconnue.';
    header('Location: ' . $redirect);
    exit;
}

$ids = isset($_POST['bl_ids']) && is_array($_POST['bl_ids']) ? $_POST['bl_ids'] : [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
    return $id > 0;
})));
if (empty($ids)) {
    $_SESSION['bl_erreur'] = 'Sélectionnez au moins une facture.';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'supprimer' && !admin_is_full_admin()) {
    $_SESSION['bl_erreur'] = 'Seul un compte administrateur peut supprimer des factures.';
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';

$ok = 0;
$ignorees = 0;
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

foreach ($ids as $bl_id) {
    $bl = get_bl_by_id($bl_id);
    if (!$bl) {
        $ignorees++;
        continue;
    }
    $payee = bl_est_facture_payee($bl);
    if ($action === 'archiver') {
        if (!$payee || bl_est_archive($bl)) {
            $ignorees++;
            continue;
        }
        $r = archive_bl($bl_id, $admin_id);
        if (!empty($r['ok'])) {
            $ok++;
        } else {
            $ignorees++;
        }
    } else {
        if ($payee) {
            $ignorees++;
            continue;
        }
        if (delete_bl($bl_id)) {
            $ok++;
        } else {
            $ignorees++;
        }
    }
}

$verbe = $action === 'archiver' ? 'archivée(s)' : 'supprimée(s)';
if ($ok > 0) {
    $msg = $ok . ' facture(s) ' . $verbe . '.';
    if ($ignorees > 0) {
        $msg .= ' ' . $ignorees . ' ignorée(s) : '
            . ($action === 'archiver' ? 'seules les factures payées peuvent être archivées.' : 'les factures payées ne peuvent pas être supprimées.');
    }
    $_SESSION['success_message'] = $msg;
} else {
    $_SESSION['bl_erreur'] = $action === 'archiver'
        ? 'Aucune facture archivée : seules les factures payées peuvent être archivées.'
        : 'Aucune facture supprimée : les factures payées ne peuvent pas être supprimées.';
}

header('Location: ' . $redirect);
exit;
