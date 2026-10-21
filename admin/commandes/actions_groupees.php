<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Archivage / suppression / désarchivage de plusieurs commandes.
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';

$redirect_tab = isset($_POST['redirect_tab']) ? (string) $_POST['redirect_tab'] : 'a_traiter';
if (!in_array($redirect_tab, ['a_traiter', 'livrees', 'annulees'], true)) {
    $redirect_tab = 'a_traiter';
}
$from_archives = !empty($_POST['from_archives']);
$redirect = $from_archives ? 'archives.php?tab=' . urlencode($redirect_tab) : 'index.php?tab=' . urlencode($redirect_tab);
if ($redirect_tab === 'livrees' && !empty($_POST['jours_precedents'])) {
    $redirect .= '&jours_precedents=1';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($from_archives ? 'archives.php' : 'index.php'));
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['error_message'] = 'Session expirée. Réessayez.';
    header('Location: ' . $redirect);
    exit;
}

$action = (string) ($_POST['action_groupee'] ?? '');
if (!in_array($action, ['archiver', 'supprimer', 'desarchiver'], true)) {
    $_SESSION['error_message'] = 'Action inconnue.';
    header('Location: ' . $redirect);
    exit;
}

$ids = isset($_POST['commande_ids']) && is_array($_POST['commande_ids']) ? $_POST['commande_ids'] : [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
    return $id > 0;
})));
if (empty($ids)) {
    $_SESSION['error_message'] = 'Sélectionnez au moins une commande.';
    header('Location: ' . $redirect);
    exit;
}

if (in_array($action, ['supprimer', 'desarchiver'], true) && !admin_is_full_admin()) {
    $_SESSION['error_message'] = 'Seul un compte administrateur peut effectuer cette action.';
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_admin.php';

$ok = 0;
$ignorees = 0;
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

foreach ($ids as $commande_id) {
    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        $ignorees++;
        continue;
    }
    $est_archivee = commande_est_archivee($commande);
    $archive_sql_ok = $from_archives ? $est_archivee : !$est_archivee;

    if ($action === 'archiver') {
        if ($from_archives || $est_archivee) {
            $ignorees++;
            continue;
        }
        $r = archive_commande($commande_id, $admin_id);
        if (!empty($r['success'])) {
            $ok++;
        } else {
            $ignorees++;
        }
    } elseif ($action === 'desarchiver') {
        if (!$from_archives || !$est_archivee) {
            $ignorees++;
            continue;
        }
        $r = unarchive_commande($commande_id);
        if (!empty($r['success'])) {
            $ok++;
        } else {
            $ignorees++;
        }
    } else {
        if ($from_archives && !$est_archivee) {
            $ignorees++;
            continue;
        }
        $r = delete_commande($commande_id);
        if (!empty($r['success'])) {
            $ok++;
        } else {
            $ignorees++;
        }
    }
}

$labels = [
    'archiver' => 'archivée(s)',
    'desarchiver' => 'désarchivée(s)',
    'supprimer' => 'supprimée(s)',
];
$verbe = $labels[$action] ?? 'traitée(s)';

if ($ok > 0) {
    $msg = $ok . ' commande(s) ' . $verbe . '.';
    if ($ignorees > 0) {
        $msg .= ' ' . $ignorees . ' ignorée(s).';
    }
    $_SESSION['success_message'] = $msg;
} else {
    $_SESSION['error_message'] = 'Aucune commande n\'a pu être traitée.';
}

header('Location: ' . $redirect);
exit;
