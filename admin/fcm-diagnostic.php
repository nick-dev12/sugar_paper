<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Diagnostic FCM + files d'attente (admin)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin_permissions.php';
require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../services/notify_queue.php';
require_once __DIR__ . '/../services/email_queue.php';
require_once __DIR__ . '/../services/notify_queue_worker.php';
require_once __DIR__ . '/../includes/site_url.php';

$admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
if ($admin_role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$result_message = '';
$result_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'process_queues') {
        $stats = notify_queue_process_jobs(30, 30);
        $result_message = 'Files traitées — notify: ' . (int) $stats['notify']['processed']
            . ' job(s), email: ' . (int) ($stats['email']['sent'] ?? 0) . ' envoyé(s).';
        if (!empty($stats['notify']['errors'])) {
            $result_message .= ' Erreurs: ' . implode('; ', $stats['notify']['errors']);
            $result_type = 'error';
        } else {
            $result_type = 'success';
        }
    } elseif ($action === 'cleanup_orphans') {
        $n = fcm_cleanup_orphan_admin_tokens();
        $result_message = $n . ' token(s) admin orphelin(s) supprimé(s).';
        $result_type = 'success';
    }
}

$admins = $db->query('SELECT id, email, prenom, nom, role, statut FROM admin ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$token_rows = $db->query("
    SELECT ft.id, ft.token, ft.admin_id, ft.type, LEFT(ft.user_agent, 80) AS ua, ft.date_creation,
           a.email AS admin_email, a.role, a.statut
    FROM fcm_tokens ft
    LEFT JOIN admin a ON a.id = ft.admin_id
    WHERE ft.type = 'admin'
    ORDER BY ft.date_creation DESC
")->fetchAll(PDO::FETCH_ASSOC);

$eligible_count = count(get_all_fcm_tokens_admin());
$notify_pending = count(glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: []);
$email_pending = count(glob(EMAIL_QUEUE_PENDING_DIR . '/*.json') ?: []);
$email_failed = count(glob(EMAIL_QUEUE_FAILED_DIR . '/*.json') ?: []);
$worker_secret = queue_worker_get_secret();
$worker_url = rtrim(get_site_base_url(), '/') . get_public_root_uri_path() . '/api/process_queues.php?key=' . urlencode($worker_secret);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic FCM - Admin</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .fcm-diag-grid { display: grid; gap: 16px; margin-bottom: 24px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .fcm-diag-card { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 16px; }
        .fcm-diag-card strong { display: block; font-size: 1.4rem; color: #918a44; }
        .fcm-diag-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 12px; overflow: hidden; }
        .fcm-diag-table th, .fcm-diag-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
        .fcm-diag-table th { background: #faf8f3; }
        .fcm-diag-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .fcm-diag-url { word-break: break-all; font-size: 12px; background: #f5f5f5; padding: 10px; border-radius: 8px; }
        .badge-ok { color: #2e7d32; font-weight: 600; }
        .badge-ko { color: #c26638; font-weight: 600; }
    </style>
</head>
<body class="page-dashboard-admin">
<?php include 'includes/nav.php'; ?>
<div class="contents-container">
    <div class="content-header">
        <h1><i class="fas fa-bell"></i> Diagnostic notifications push</h1>
        <div class="header-actions">
            <a href="test-notification.php?mode=tous" class="btn-primary btn-secondary-style"><i class="fas fa-paper-plane"></i> Test push tous</a>
            <a href="dashboard.php" class="btn-primary btn-secondary-style"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <?php if ($result_message): ?>
        <div class="alert-box message-<?php echo $result_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom:16px;">
            <p><?php echo htmlspecialchars($result_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="fcm-diag-grid">
        <div class="fcm-diag-card"><span>Tokens admin éligibles</span><strong><?php echo (int) $eligible_count; ?></strong></div>
        <div class="fcm-diag-card"><span>Jobs notify en attente</span><strong><?php echo (int) $notify_pending; ?></strong></div>
        <div class="fcm-diag-card"><span>Emails en attente</span><strong><?php echo (int) $email_pending; ?></strong></div>
        <div class="fcm-diag-card"><span>Emails en échec</span><strong><?php echo (int) $email_failed; ?></strong></div>
    </div>

    <?php if ($notify_pending > 0): ?>
        <div class="alert-box" style="margin-bottom:16px;">
            <p><i class="fas fa-exclamation-triangle"></i>
                <strong><?php echo (int) $notify_pending; ?> notification(s) en attente</strong> —
                le worker CLI n'a probablement pas tourné sur le serveur. Cliquez « Traiter les files » ci-dessous
                ou configurez un cron HTTP.</p>
        </div>
    <?php endif; ?>

    <div class="fcm-diag-actions">
        <form method="POST"><input type="hidden" name="action" value="process_queues">
            <button type="submit" class="btn-primary"><i class="fas fa-play"></i> Traiter les files (notify + email)</button>
        </form>
        <form method="POST"><input type="hidden" name="action" value="cleanup_orphans">
            <button type="submit" class="btn-primary btn-secondary-style"><i class="fas fa-broom"></i> Nettoyer tokens orphelins</button>
        </form>
    </div>

    <h2 style="margin:0 0 12px;font-size:1.1rem;">Cron HTTP (production)</h2>
    <p style="font-size:14px;color:#666;margin:0 0 8px;">Toutes les 1–2 minutes sur le serveur :</p>
    <div class="fcm-diag-url"><?php echo htmlspecialchars($worker_url); ?></div>

    <h2 style="margin:24px 0 12px;font-size:1.1rem;">Comptes admin</h2>
    <table class="fcm-diag-table">
        <thead><tr><th>ID</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Éligible push</th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a):
            $role = normalize_admin_role($a['role'] ?? '');
            $ok = ($a['statut'] ?? '') === 'actif' && in_array($role, fcm_notify_admin_roles_eligible(), true);
        ?>
            <tr>
                <td><?php echo (int) $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars($role); ?></td>
                <td><?php echo htmlspecialchars($a['statut']); ?></td>
                <td class="<?php echo $ok ? 'badge-ok' : 'badge-ko'; ?>"><?php echo $ok ? 'Oui' : 'Non'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="margin:24px 0 12px;font-size:1.1rem;">Tokens FCM enregistrés (type admin)</h2>
    <table class="fcm-diag-table">
        <thead><tr><th>Admin</th><th>Token (début)</th><th>Lié admin_id</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($token_rows)): ?>
            <tr><td colspan="4">Aucun token — chaque admin doit autoriser les notifications dans le menu.</td></tr>
        <?php else: foreach ($token_rows as $t): ?>
            <tr>
                <td><?php echo htmlspecialchars($t['admin_email'] ?? '—'); ?><br><small><?php echo htmlspecialchars(normalize_admin_role($t['role'] ?? '')); ?></small></td>
                <td><code><?php echo htmlspecialchars(substr($t['token'], 0, 36)); ?>…</code></td>
                <td class="<?php echo !empty($t['admin_id']) ? 'badge-ok' : 'badge-ko'; ?>"><?php echo $t['admin_id'] ? (int) $t['admin_id'] : 'NULL'; ?></td>
                <td><?php echo htmlspecialchars($t['date_creation'] ?? ''); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
