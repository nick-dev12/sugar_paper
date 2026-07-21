<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page de test d'envoi d'email (admin)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../services/notify_helpers.php';
require_once __DIR__ . '/../services/email_queue.php';

$result_message = '';
$result_type = '';
$queue_stats = null;

$default_to = notifications_get_commande_admin_email();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to_email'] ?? $default_to);
    $mode = $_POST['mode'] ?? 'direct';

    if ($mode === 'process_queue') {
        $queue_stats = email_queue_process(30);
        $result_message = 'File traitée : ' . (int) $queue_stats['processed'] . ' job(s), '
            . (int) $queue_stats['sent'] . ' envoyé(s), '
            . (int) $queue_stats['failed'] . ' échec(s).';
        $result_type = $queue_stats['failed'] > 0 ? 'error' : 'success';
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result_message = 'Adresse email invalide.';
        $result_type = 'error';
    } elseif ($mode === 'queue') {
        $send = notifications_mail_send(
            $to,
            '[Sugar Paper] Test email (file d\'attente)',
            '<div style="font-family:Arial,sans-serif;max-width:520px;"><h2 style="color:#918a44;">Test file d\'attente</h2><p>Ce message a transité par la file d\'attente email.</p><p><strong>Date :</strong> ' . date('d/m/Y H:i:s') . '</p></div>',
            true,
            ['type' => 'test_email']
        );
        if (!empty($send['success'])) {
            $result_message = 'Email mis en file et traité (job: ' . ($send['job_id'] ?? '—') . ').';
            $result_type = 'success';
        } else {
            $result_message = 'Échec : ' . ($send['error'] ?? 'erreur inconnue');
            $result_type = 'error';
        }
    } else {
        notifications_ensure_mail_loaded();
        $send = mail_send(
            $to,
            '[Sugar Paper] Test email direct',
            '<div style="font-family:Arial,sans-serif;max-width:520px;"><h2 style="color:#918a44;">Test SMTP direct</h2><p>Ce message a été envoyé directement via PHPMailer.</p><p><strong>Date :</strong> ' . date('d/m/Y H:i:s') . '</p></div>',
            true
        );
        if (!empty($send['success'])) {
            $result_message = 'Email envoyé avec succès à ' . $to . '. Vérifiez la boîte de réception (et les spams).';
            $result_type = 'success';
        } else {
            $result_message = 'Échec SMTP : ' . ($send['error'] ?? 'erreur inconnue');
            $result_type = 'error';
        }
    }
}

$pending_count = is_dir(EMAIL_QUEUE_PENDING_DIR) ? count(glob(EMAIL_QUEUE_PENDING_DIR . '/*.json') ?: []) : 0;
$failed_count = is_dir(EMAIL_QUEUE_FAILED_DIR) ? count(glob(EMAIL_QUEUE_FAILED_DIR . '/*.json') ?: []) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test email - Administration Sugar Paper</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-dashboard-admin">
    <?php include 'includes/nav.php'; ?>

    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-envelope"></i> Test envoi email</h1>
            <div class="header-actions">
                <?php include __DIR__ . '/includes/btn_retour_site.php'; ?>
                <a href="dashboard.php" class="btn-primary btn-secondary-style"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if ($result_message): ?>
            <div class="alert-box message-<?php echo $result_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom:20px;">
                <p><i class="fas fa-<?php echo $result_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($result_message); ?></p>
            </div>
        <?php endif; ?>

        <div class="alert-box" style="margin-bottom:20px;">
            <p><strong>File d'attente :</strong> <?php echo $pending_count; ?> en attente, <?php echo $failed_count; ?> en échec.</p>
            <p style="margin:8px 0 0;font-size:14px;color:#666;">SMTP : mail.sugar-paper.com — Alertes commandes : <?php echo htmlspecialchars($default_to); ?></p>
        </div>

        <form method="POST" class="dashboard-quick-links" style="display:block;max-width:640px;padding:24px;background:#fff;border-radius:12px;border:1px solid rgba(0,0,0,0.08);">
            <div style="margin-bottom:16px;">
                <label for="to_email" style="display:block;font-weight:600;margin-bottom:6px;">Destinataire</label>
                <input type="email" id="to_email" name="to_email" value="<?php echo htmlspecialchars($default_to); ?>" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
            </div>
            <div style="margin-bottom:20px;">
                <label for="mode" style="display:block;font-weight:600;margin-bottom:6px;">Mode d'envoi</label>
                <select id="mode" name="mode" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    <option value="direct">Envoi SMTP direct (recommandé pour test)</option>
                    <option value="queue">Via file d'attente (comme les commandes)</option>
                    <option value="process_queue">Traiter uniquement la file en attente</option>
                </select>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Lancer le test</button>
        </form>
    </div>
</body>
</html>
