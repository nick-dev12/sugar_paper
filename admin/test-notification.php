<?php
/**
 * Page de test des notifications push (Admin)
 * Envoie une notification de test à l'admin connecté
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/../services/firebase_push.php';

$message = '';
$tokens = get_fcm_tokens_by_admin($_SESSION['admin_id']);

if (empty($tokens)) {
    $message = 'Aucun token enregistré. Activez d\'abord les notifications depuis le tableau de bord.';
} else {
    $result = firebase_send_notification(
        $tokens,
        'Test Sugar Paper',
        'Ceci est une notification de test. Les notifications fonctionnent correctement !',
        ['link' => '/admin/dashboard.php', 'tag' => 'test']
    );
    $message = $result['success'] > 0
        ? "Notification envoyée avec succès ({$result['success']} appareil(s))."
        : "Échec de l'envoi. " . implode(' ', $result['errors']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test notification - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-bell"></i> Test des notifications</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <div class="message <?php echo strpos($message, 'succès') !== false ? 'success' : 'error'; ?>">
            <i class="fas fa-<?php echo strpos($message, 'succès') !== false ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
</body>
</html>
