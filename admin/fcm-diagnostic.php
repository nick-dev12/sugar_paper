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
require_once __DIR__ . '/../services/notify_helpers.php';

$admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
if ($admin_role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$result_message = '';
$result_type = '';

if (!empty($_SESSION['notification_test_message'])) {
    $result_message = (string) $_SESSION['notification_test_message'];
    $result_type = (string) ($_SESSION['notification_test_type'] ?? 'success');
    unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type'], $_SESSION['notification_test_details']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'process_queues') {
        @set_time_limit(180);
        @ignore_user_abort(true);
        $stats = notify_queue_process_jobs(30, 30);
        $result_message = 'Files traitées — notify: ' . (int) $stats['notify']['processed']
            . ' job(s), email: ' . (int) ($stats['email']['sent'] ?? 0) . ' envoyé(s)'
            . ', échec email: ' . (int) ($stats['email']['failed'] ?? 0) . '.';
        if (!empty($stats['notify']['errors'])) {
            $result_message .= "\nErreurs notify: " . implode('; ', $stats['notify']['errors']);
            $result_type = 'error';
        } elseif ((int) ($stats['email']['failed'] ?? 0) > 0) {
            $result_type = 'error';
        } else {
            $result_type = 'success';
        }
    } elseif ($action === 'cleanup_orphans') {
        $n = fcm_cleanup_orphan_admin_tokens();
        $result_message = $n . ' token(s) admin orphelin(s) supprimé(s).';
        $result_type = 'success';
    } elseif ($action === 'retry_failed_emails') {
        $n = email_queue_retry_failed();
        $stats = email_queue_process(40);
        $result_message = $n . ' email(s) remis en file. Traitement : '
            . (int) ($stats['sent'] ?? 0) . ' envoyé(s), '
            . (int) ($stats['failed'] ?? 0) . ' échec(s).';
        $result_type = ((int) ($stats['failed'] ?? 0) > 0 || (int) ($stats['sent'] ?? 0) === 0 && $n > 0)
            ? 'error'
            : 'success';
        if ((int) ($stats['failed'] ?? 0) > 0) {
            $result_message .= "\nCause probable : identifiants SMTP invalides dans config/email.php (mot de passe service@sugar-paper.com).";
            $result_message .= "\nCorrigez le mot de passe, puis réessayez. Testez aussi via admin/test-email.php.";
        }
    } elseif ($action === 'test_smtp') {
        notifications_ensure_mail_loaded();
        $to = notifications_get_commande_admin_email();
        $send = function_exists('mail_send')
            ? mail_send(
                $to,
                '[Sugar Paper] Test SMTP diagnostic',
                '<p>Test SMTP du ' . date('d/m/Y H:i:s') . '</p>',
                true
            )
            : ['success' => false, 'error' => 'Service mail indisponible'];
        if (!empty($send['success'])) {
            $result_message = 'SMTP OK — email de test envoyé à ' . $to;
            $result_type = 'success';
        } else {
            $result_message = 'Échec SMTP : ' . ($send['error'] ?? 'erreur inconnue')
                . "\nVérifiez username/password dans config/email.php (compte service@sugar-paper.com).";
            $result_type = 'error';
        }
    } elseif ($action === 'test_push_all') {
        require_once __DIR__ . '/../services/firebase_push.php';
        @set_time_limit(180);
        @ignore_user_abort(true);
        $groups = get_fcm_admin_token_groups();
        if (empty($groups)) {
            $result_message = 'Aucun token — chaque admin/utilisateur doit activer les notifications sur son appareil.';
            $result_type = 'error';
        } else {
            $push = firebase_send_notification_to_all_admins(
                'Test Sugar Paper (diagnostic)',
                'Envoi parallèle à tous les comptes admin/utilisateur avec token.',
                ['link' => '/admin/fcm-diagnostic.php', 'tag' => 'diag-tous-' . time()]
            );
            $parts = [
                (int) $push['admins_notified'] . '/' . (int) $push['admins_total'] . ' compte(s)',
                (int) $push['success'] . ' appareil(s) OK',
            ];
            if ((int) ($push['failed'] ?? 0) > 0) {
                $parts[] = (int) $push['failed'] . ' échec(s)';
            }
            $result_message = 'Test push : ' . implode(', ', $parts) . '.';
            if (!empty($push['details'])) {
                foreach ($push['details'] as $d) {
                    $result_message .= "\n• #" . (int) $d['admin_id'] . ' ' . ($d['email'] ?? '')
                        . ' → ' . (int) $d['success'] . '/' . (int) $d['tokens'];
                }
            }
            if (!empty($push['errors'])) {
                $result_message .= "\nErreurs: " . implode(' ; ', array_slice($push['errors'], 0, 5));
            }
            $result_type = ((int) ($push['success'] ?? 0) > 0) ? 'success' : 'error';
        }
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
$fcm_groups = get_fcm_admin_token_groups();
$admins_with_tokens = count($fcm_groups);
$notify_pending = count(glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: []);
$notify_failed = is_dir(NOTIFY_QUEUE_FAILED_DIR) ? count(glob(NOTIFY_QUEUE_FAILED_DIR . '/*.json') ?: []) : 0;
$email_pending = count(glob(EMAIL_QUEUE_PENDING_DIR . '/*.json') ?: []);
$email_failed = count(glob(EMAIL_QUEUE_FAILED_DIR . '/*.json') ?: []);
$email_failed_list = email_queue_list_failed(8);
$worker_secret = queue_worker_get_secret();
$worker_url = rtrim(get_site_base_url(), '/') . get_public_root_uri_path() . '/api/process_queues.php?key=' . urlencode($worker_secret);
$cron_php = '/usr/bin/php';
$cron_script = str_replace('\\', '/', dirname(__DIR__) . '/scripts/process_queues_cron.php');
$admin_alert_email = notifications_get_commande_admin_email();
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
        .fcm-diag-grid { display: grid; gap: 16px; margin-bottom: 24px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
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
            <a href="test-email.php" class="btn-primary btn-secondary-style"><i class="fas fa-envelope"></i> Test email</a>
            <a href="test-notification.php?mode=tous&from=diag" class="btn-primary btn-secondary-style"><i class="fas fa-paper-plane"></i> Test push tous</a>
            <a href="dashboard.php" class="btn-primary btn-secondary-style"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <?php if ($result_message): ?>
        <div class="alert-box message-<?php echo $result_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom:16px;">
            <p style="white-space:pre-wrap;margin:0;"><?php echo htmlspecialchars($result_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($email_failed > 0): ?>
        <div class="alert-box message-error" style="margin-bottom:16px;">
            <p style="margin:0 0 8px;"><i class="fas fa-exclamation-triangle"></i>
                <strong><?php echo (int) $email_failed; ?> email(s) en échec</strong> —
                cause fréquente : <em>échec d’authentification SMTP</em> (mot de passe
                <code>service@sugar-paper.com</code> dans <code>config/email.php</code>).
            </p>
            <p style="margin:0;font-size:13px;">Destinataire alertes commandes : <strong><?php echo htmlspecialchars($admin_alert_email); ?></strong></p>
        </div>
    <?php endif; ?>

    <div class="fcm-diag-grid">
        <div class="fcm-diag-card"><span>Tokens appareils</span><strong><?php echo (int) $eligible_count; ?></strong></div>
        <div class="fcm-diag-card"><span>Admins avec push actif</span><strong><?php echo (int) $admins_with_tokens; ?></strong></div>
        <div class="fcm-diag-card"><span>Jobs notify en attente</span><strong><?php echo (int) $notify_pending; ?></strong></div>
        <div class="fcm-diag-card"><span>Jobs notify en échec</span><strong class="<?php echo $notify_failed ? 'badge-ko' : ''; ?>"><?php echo (int) $notify_failed; ?></strong></div>
        <div class="fcm-diag-card"><span>Emails en attente</span><strong><?php echo (int) $email_pending; ?></strong></div>
        <div class="fcm-diag-card"><span>Emails en échec</span><strong class="<?php echo $email_failed ? 'badge-ko' : ''; ?>"><?php echo (int) $email_failed; ?></strong></div>
    </div>

    <?php if ($notify_pending > 0): ?>
        <div class="alert-box" style="margin-bottom:16px;">
            <p><i class="fas fa-exclamation-triangle"></i>
                <strong><?php echo (int) $notify_pending; ?> notification(s) en attente</strong> —
                cliquez « Traiter les files » ou vérifiez le cron ci-dessous.</p>
        </div>
    <?php endif; ?>

    <div class="fcm-diag-actions">
        <form method="POST"><input type="hidden" name="action" value="process_queues">
            <button type="submit" class="btn-primary"><i class="fas fa-play"></i> Traiter les files (notify + email)</button>
        </form>
        <form method="POST"><input type="hidden" name="action" value="test_push_all">
            <button type="submit" class="btn-primary btn-secondary-style"><i class="fas fa-bell"></i> Tester push (tous)</button>
        </form>
        <form method="POST"><input type="hidden" name="action" value="test_smtp">
            <button type="submit" class="btn-primary btn-secondary-style"><i class="fas fa-envelope-open-text"></i> Tester SMTP</button>
        </form>
        <?php if ($email_failed > 0): ?>
        <form method="POST"><input type="hidden" name="action" value="retry_failed_emails">
            <button type="submit" class="btn-primary btn-secondary-style"><i class="fas fa-redo"></i> Réessayer emails échoués</button>
        </form>
        <?php endif; ?>
        <form method="POST"><input type="hidden" name="action" value="cleanup_orphans">
            <button type="submit" class="btn-primary btn-secondary-style"><i class="fas fa-broom"></i> Nettoyer tokens orphelins</button>
        </form>
    </div>

    <?php if (!empty($email_failed_list)): ?>
    <h2 style="margin:0 0 12px;font-size:1.1rem;">Derniers échecs email</h2>
    <table class="fcm-diag-table" style="margin-bottom:20px;">
        <thead><tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>Erreur</th></tr></thead>
        <tbody>
        <?php foreach ($email_failed_list as $ef): ?>
            <tr>
                <td><?php echo htmlspecialchars($ef['failed_at']); ?></td>
                <td><?php echo htmlspecialchars($ef['to']); ?></td>
                <td><?php echo htmlspecialchars($ef['subject']); ?></td>
                <td class="badge-ko"><?php echo htmlspecialchars($ef['error']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2 style="margin:0 0 12px;font-size:1.1rem;">Cron files (production Webuzo)</h2>
    <p style="font-size:14px;color:#666;margin:0 0 8px;">
        Après une commande : push + emails sont mis en file, puis traités automatiquement
        (worker HTTP / shutdown PHP / cron). Le cron ci-dessous reste obligatoire en secours.
    </p>
    <p style="font-size:14px;color:#666;margin:0 0 8px;"><strong>Option A — CLI (recommandé) :</strong></p>
    <div class="fcm-diag-url"><?php echo htmlspecialchars($cron_php . ' ' . $cron_script); ?></div>
    <p style="font-size:13px;color:#888;margin:6px 0 12px;">
        Chemin détecté depuis ce serveur. Minute = <code>*</code> (chaque minute).
        Si l’ancien cron pointe encore vers <code>public_html</code>, mettez à jour vers ce chemin.
    </p>
    <p style="font-size:14px;color:#666;margin:0 0 8px;"><strong>Option B — HTTP :</strong></p>
    <div class="fcm-diag-url"><?php echo htmlspecialchars($worker_url); ?></div>
    <p style="font-size:13px;color:#888;margin:6px 0 20px;">
        Commande cron : <code>curl -fsS "<?php echo htmlspecialchars($worker_url); ?>" >/dev/null 2>&amp;1</code>
    </p>

    <h2 style="margin:24px 0 12px;font-size:1.1rem;">Comptes admin</h2>
    <table class="fcm-diag-table">
        <thead><tr><th>ID</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Éligible push</th><th>Tokens liés</th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a):
            $role = normalize_admin_role($a['role'] ?? '');
            $ok = ($a['statut'] ?? '') === 'actif' && in_array($role, fcm_notify_admin_roles_eligible(), true);
            $aid = (int) $a['id'];
            $tok_n = isset($fcm_groups[$aid]) ? count($fcm_groups[$aid]['tokens']) : 0;
        ?>
            <tr>
                <td><?php echo $aid; ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars($role); ?></td>
                <td><?php echo htmlspecialchars($a['statut']); ?></td>
                <td class="<?php echo $ok ? 'badge-ok' : 'badge-ko'; ?>"><?php echo $ok ? 'Oui' : 'Non'; ?></td>
                <td class="<?php echo $tok_n > 0 ? 'badge-ok' : 'badge-ko'; ?>"><?php echo (int) $tok_n; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="font-size:13px;color:#666;margin:8px 0 20px;">
        <i class="fas fa-info-circle"></i>
        Chaque compte admin/utilisateur doit activer les notifications <strong>sur son propre appareil / navigateur</strong>.
        Un même navigateur ne peut lier le token qu’à <strong>un seul</strong> compte à la fois.
    </p>

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
