<?php
/**
 * Page de vérification de la configuration Firebase
 */
require_once __DIR__ . '/includes/require_admin_session.php';

$config_path = __DIR__ . '/../config/firebase_config.php';
$config = file_exists($config_path) ? require $config_path : [];
$server_path = __DIR__ . '/../config/firebase_server.php';
$server = file_exists($server_path) ? require $server_path : [];
$credentials = $server['credentials_path'] ?? '';
$credentials_ok = $credentials !== '' && file_exists($credentials);
$project_id = $config['projectId'] ?? '';
$console_project = $project_id !== '' ? $project_id : 'colobanes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification config Firebase - Admin</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-key"></i> Configuration Firebase</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <div class="detail-box" style="max-width: 700px;">
            <h3>Configuration client (navigateur)</h3>
            <p style="margin-bottom: 15px; color: var(--texte-fonce);">Source : <code>config/firebase_config.php</code></p>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px; font-weight: 600;">projectId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['projectId'] ?? '—'); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">apiKey</td><td style="padding: 8px; word-break: break-all;"><?php echo htmlspecialchars($config['apiKey'] ?? '—'); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">messagingSenderId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['messagingSenderId'] ?? '—'); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">appId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['appId'] ?? '—'); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">vapidKey</td><td style="padding: 8px; word-break: break-all;"><?php echo htmlspecialchars(isset($config['vapidKey']) ? substr($config['vapidKey'], 0, 24) . '…' : '—'); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">Service Worker</td><td style="padding: 8px;"><code>/firebase-messaging-sw.js</code></td></tr>
            </table>
        </div>
        <div class="detail-box" style="max-width: 700px; margin-top: 20px;">
            <h3>Configuration serveur (envoi push)</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; font-weight: 600;">Compte de service</td>
                    <td style="padding: 8px; color: <?php echo $credentials_ok ? 'green' : 'red'; ?>;">
                        <?php echo $credentials_ok ? 'Fichier trouvé ✓' : 'Fichier introuvable ✗'; ?>
                    </td>
                </tr>
                <tr><td style="padding: 8px; font-weight: 600;">Chemin</td><td style="padding: 8px; word-break: break-all;"><code><?php echo htmlspecialchars($credentials); ?></code></td></tr>
            </table>
            <p style="margin-top: 12px;"><a href="../scripts/test_fcm_setup.php" target="_blank">Lancer le test serveur FCM (CLI)</a></p>
        </div>
        <div class="detail-box" style="max-width: 700px; margin-top: 20px;">
            <h3><i class="fas fa-wrench"></i> Google Cloud Console</h3>
            <ol style="line-height: 2; color: var(--texte-fonce);">
                <li>Ouvrez <a href="https://console.cloud.google.com/apis/credentials?project=<?php echo urlencode($console_project); ?>" target="_blank" rel="noopener">Identifiants API</a></li>
                <li>Clé navigateur : autorisez <code>http://localhost:5000/*</code> et votre domaine de production</li>
                <li>Activez <strong>Firebase Cloud Messaging API</strong></li>
                <li>Vérifiez la clé VAPID dans Firebase → Cloud Messaging → Web Push certificates</li>
            </ol>
        </div>
    </div>
</body>
</html>
