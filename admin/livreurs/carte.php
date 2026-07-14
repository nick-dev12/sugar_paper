<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Carte admin — tous les livreurs en livraison GPS en même temps
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../includes/tracking_config.php';

if (!admin_can_view_livreurs_map()) {
    header('Location: index.php');
    exit;
}

$tables_ready = livreur_tracking_tables_ready();
$livreurs = $tables_ready ? livreur_get_actifs_sur_carte() : [];
$poll_url = '/api/tracking/livreurs-actifs.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Map livreurs — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-livreurs-carte.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-livreurs-carte">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="livreurs-carte-app" id="livreurs-carte-app">
    <header class="livreurs-carte-topbar">
        <div class="livreurs-carte-topbar__text">
            <h1 class="livreurs-carte-topbar__title"><i class="fas fa-map-location-dot" aria-hidden="true"></i> Map livreurs</h1>
            <p class="livreurs-carte-topbar__sub">Tous les livreurs en livraison GPS en temps réel</p>
        </div>
        <div class="livreurs-carte-topbar__meta" aria-live="polite">
            <span class="livreurs-carte-badge" id="livreurs-carte-count"><?php echo (int) count($livreurs); ?> actif<?php echo count($livreurs) !== 1 ? 's' : ''; ?></span>
            <a href="index.php" class="livreurs-carte-link"><i class="fas fa-motorcycle" aria-hidden="true"></i> Livraisons</a>
        </div>
    </header>

    <?php if (!$tables_ready): ?>
    <div class="livreurs-carte-empty">
        <p class="message error"><i class="fas fa-database"></i> Module GPS non installé. Exécutez la migration SQL.</p>
    </div>
    <?php else: ?>
    <div class="livreurs-carte-layout">
        <div class="livreurs-carte-map-wrap">
            <div id="livreurs-carte-map" class="livreurs-carte-map"></div>
            <div class="livreurs-carte-map-controls">
                <button type="button" class="livreurs-carte-map-btn" id="livreurs-carte-fit" aria-label="Recentrer sur tous les livreurs">
                    <i class="fas fa-expand" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <aside class="livreurs-carte-panel" aria-label="Liste des livreurs actifs">
            <h2 class="livreurs-carte-panel__title">En livraison</h2>
            <ul class="livreurs-carte-list" id="livreurs-carte-list">
                <?php if (empty($livreurs)): ?>
                <li class="livreurs-carte-list__empty" id="livreurs-carte-empty">Aucun livreur en suivi GPS pour le moment.</li>
                <?php else: ?>
                <?php foreach ($livreurs as $lv): ?>
                <li class="livreurs-carte-list__item" data-livreur-id="<?php echo (int) $lv['livreur_id']; ?>">
                    <div class="livreurs-carte-list__head">
                        <strong><?php echo htmlspecialchars($lv['livreur_nom']); ?></strong>
                        <span class="livreurs-carte-list__type"><?php echo $lv['livraison_type'] === 'facture' ? 'Facture' : 'Commande'; ?></span>
                    </div>
                    <p class="livreurs-carte-list__num"><?php echo htmlspecialchars($lv['numero']); ?></p>
                    <p class="livreurs-carte-list__addr"><?php echo htmlspecialchars($lv['adresse_livraison']); ?></p>
                    <a class="livreurs-carte-list__link" href="<?php echo htmlspecialchars($lv['suivi_url']); ?>">
                        <i class="fas fa-eye" aria-hidden="true"></i> Suivre
                    </a>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
    <?php endif; ?>
</div>

<?php if ($tables_ready): ?>
<script>
window.LIVREURS_CARTE_CONFIG = {
    pollUrl: <?php echo json_encode($poll_url, JSON_UNESCAPED_SLASHES); ?>,
    pollIntervalMs: 5000,
    defaultCenter: [14.6937, -17.4441],
    defaultZoom: 12,
    initialLivreurs: <?php echo json_encode($livreurs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/js/admin-livreurs-carte.js?v=<?php echo (int) @filemtime(__DIR__ . '/../../js/admin-livreurs-carte.js'); ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
