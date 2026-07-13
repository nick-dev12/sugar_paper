<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Iframe suivi GPS en arrière-plan (session admin livreur)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_livreur_gps()) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../includes/asset_version.php';

$commande_id = (int) ($_GET['commande_id'] ?? 0);
$bl_id = (int) ($_GET['bl_id'] ?? 0);
$admin_id = (int) $_SESSION['admin_id'];

$livraison = null;
$livraison_type = '';
if ($bl_id > 0 && livreur_tracking_tables_ready()) {
    $livraison = livreur_get_facture_tracking($bl_id);
    $livraison_type = 'facture';
} elseif ($commande_id > 0 && livreur_tracking_tables_ready()) {
    $livraison = livreur_get_commande_tracking($commande_id);
    $livraison_type = 'commande';
}

if (!$livraison || !livreur_web_can_manage_livraison($admin_id, $commande_id > 0 ? $commande_id : null, $bl_id > 0 ? $bl_id : null)) {
    http_response_code(403);
    exit;
}

if ((int) ($livraison['tracking_active'] ?? 0) !== 1) {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../includes/tracking_config.php';

$socket_client_url = tracking_client_socket_url();
$socket_path = tracking_config_get('socket_path', '/socket.io');
$realtime_configured = tracking_realtime_available();

$watch_token_url = '';
if ($livraison_type === 'facture' && $bl_id > 0) {
    $watch_token_url = '/api/tracking/watch-token.php?bl_id=' . $bl_id;
} elseif ($livraison_type === 'commande' && $commande_id > 0) {
    $watch_token_url = '/api/tracking/watch-token.php?commande_id=' . $commande_id;
}

$status_url = '/api/tracking/last-position.php?';
if ($bl_id > 0) {
    $status_url .= 'bl_id=' . $bl_id;
} else {
    $status_url .= 'commande_id=' . $commande_id;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi GPS — arrière-plan</title>
</head>
<body>
<script>window.__livreurBgTrackerDisabled = true;</script>
<script>
window.LIVREUR_BG_PAGE_CONFIG = {
    commandeId: <?php echo $livraison_type === 'commande' ? (int) $commande_id : 0; ?>,
    blId: <?php echo $livraison_type === 'facture' ? (int) $bl_id : 0; ?>,
    webApiUrl: '/api/tracking/livreur-web.php',
    watchTokenUrl: <?php echo json_encode($watch_token_url, JSON_UNESCAPED_SLASHES); ?>,
    statusUrl: <?php echo json_encode($status_url, JSON_UNESCAPED_SLASHES); ?>,
    socketUrl: <?php echo json_encode($socket_client_url, JSON_UNESCAPED_SLASHES); ?>,
    socketPath: <?php echo json_encode($socket_path, JSON_UNESCAPED_SLASHES); ?>,
    realtimeConfigured: <?php echo $realtime_configured ? 'true' : 'false'; ?>
};
</script>
<script src="/js/livreur-bg-tracker.js<?php echo asset_version_query(); ?>"></script>
<?php if ($realtime_configured): ?>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<?php endif; ?>
<script src="/js/livreur-bg-tracker-page.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
