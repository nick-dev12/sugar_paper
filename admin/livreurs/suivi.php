<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Suivi GPS en temps réel — carte plein écran (style app livreur)
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

$tables_ready = livreur_tracking_tables_ready();
$commande_id = (int) ($_GET['commande_id'] ?? 0);
$bl_id = (int) ($_GET['bl_id'] ?? 0);
$regarder_mode = ($bl_id > 0 || $commande_id > 0)
    && isset($_GET['regarder'])
    && (string) $_GET['regarder'] === '1';

if (!$regarder_mode && !admin_can_livreur_gps()) {
    header('Location: ../dashboard.php');
    exit;
}

$livraison = false;
$livraison_type = '';

if ($tables_ready && $bl_id > 0) {
    $livraison = livreur_get_facture_tracking($bl_id);
    $livraison_type = 'facture';
} elseif ($tables_ready && $commande_id > 0) {
    $livraison = livreur_get_commande_tracking($commande_id);
    $livraison_type = 'commande';
}

$client_nom = '';
$client_tel = '';
$statut_label = '';
$delivery_lat = null;
$delivery_lng = null;

if ($livraison) {
    if ($livraison_type === 'facture') {
        $client_nom = trim((string) ($livraison['client_nom'] ?? $livraison['raison_sociale'] ?? ''));
        $client_tel = trim((string) ($livraison['client_telephone'] ?? ''));
        $statut_label = livreur_facture_statut_livraison($livraison);
    } else {
        $client_nom = trim((string) ($livraison['client_prenom'] ?? '') . ' ' . (string) ($livraison['client_nom'] ?? ''));
        $client_tel = trim((string) ($livraison['client_telephone'] ?? ''));
        $statut_label = livreur_statut_label($livraison['statut'] ?? '');
    }
    $delivery_lat = livreur_parse_coord($livraison['delivery_latitude'] ?? null);
    $delivery_lng = livreur_parse_coord($livraison['delivery_longitude'] ?? null);
}

$tracking_cfg = tracking_load_config();
$socket_client_url = tracking_client_socket_url();
$socket_path = tracking_config_get('socket_path', '/socket.io');
$realtime_configured = tracking_realtime_available();

$watch_token_url = '';
if ($livraison_type === 'facture' && $bl_id > 0) {
    $watch_token_url = '/api/tracking/watch-token.php?bl_id=' . $bl_id;
} elseif ($livraison_type === 'commande' && $commande_id > 0) {
    $watch_token_url = '/api/tracking/watch-token.php?commande_id=' . $commande_id;
}

$can_manage_livraison = $livraison && livreur_web_can_manage_livraison((int) $_SESSION['admin_id'], $commande_id > 0 ? $commande_id : null, $bl_id > 0 ? $bl_id : null);
$can_start_livraison = $can_manage_livraison && !$regarder_mode;
$geo_ready = $delivery_lat !== null && $delivery_lng !== null;
$tracking_active_initial = $livraison ? (int) ($livraison['tracking_active'] ?? 0) : 0;
$autostart_tracking = isset($_GET['autostart']) && (string) $_GET['autostart'] === '1';
$watch_only = $regarder_mode || !$can_manage_livraison;
$index_back_url = $regarder_mode
    ? ($bl_id > 0
        ? '../invoice/bl_voir.php?id=' . (int) $bl_id
        : '../commandes/details.php?id=' . (int) $commande_id)
    : ('index.php' . ($livraison_type === 'facture' ? '?tab=facture' : ''));
$show_share_delivery = $livraison && !empty($livraison['livreur_id']);
$embedded_watch_token = '';
$initial_watch_payload = null;
if ($regarder_mode && $livraison && !empty($livraison['livreur_id'])) {
    $watch_row = null;
    if ($bl_id > 0) {
        $watch_row = livreur_create_watch_token(null, 'admin', (int) $_SESSION['admin_id'], null, $bl_id);
    } elseif ($commande_id > 0) {
        $watch_row = livreur_create_watch_token($commande_id, 'admin', (int) $_SESSION['admin_id'], null);
    }
    if ($watch_row) {
        $embedded_watch_token = $watch_row['token'];
        $last_pos = livreur_get_last_position(
            (int) $livraison['livreur_id'],
            $commande_id > 0 ? $commande_id : null,
            $bl_id > 0 ? $bl_id : null
        );
        $initial_watch_payload = [
            'success' => true,
            'watch_token' => $embedded_watch_token,
            'livraison_type' => $livraison_type,
            'commande' => [
                'id' => $livraison_type === 'facture' ? $bl_id : $commande_id,
                'numero_commande' => $livraison_type === 'facture'
                    ? ($livraison['numero_bl'] ?? '')
                    : ($livraison['numero_commande'] ?? ''),
                'tracking_active' => $tracking_active_initial,
                'adresse_livraison' => $livraison['adresse_livraison'] ?? '',
                'delivery_latitude' => $delivery_lat,
                'delivery_longitude' => $delivery_lng,
                'livreur_nom' => trim(($livraison['livreur_prenom'] ?? '') . ' ' . ($livraison['livreur_nom'] ?? '')),
            ],
            'last_position' => $last_pos ?: null,
            'socket_path' => $socket_path,
        ];
    }
}
$mes_livraisons = [];
if ($tables_ready) {
    $admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
    $mes_livraisons_only_today = ($admin_role === 'livreur');
    $mes_livraisons = livreur_get_mes_livraisons_for_admin((int) $_SESSION['admin_id'], $mes_livraisons_only_today, true);
}
$mes_livraisons_count = count($mes_livraisons);
$client_tel_href = $client_tel !== '' ? preg_replace('/\s+/', '', $client_tel) : '';

require_once __DIR__ . '/../../models/model_admin.php';
$livreur_photo_url = '';
$livreur_initials = 'L';
if ($livraison) {
    $livreur_photo_url = livreur_photo_url_from_row($livraison);
    $livreur_initials = livreur_initials_from_row($livraison);
}
if ($livreur_photo_url === '' && $can_start_livraison) {
    $current_admin = get_admin_by_id((int) $_SESSION['admin_id']);
    if (is_array($current_admin)) {
        $livreur_photo_url = admin_photo_profil_url((string) ($current_admin['photo_profil'] ?? ''));
        $livreur_initials = livreur_initials_from_row([
            'livreur_prenom' => $current_admin['prenom'] ?? '',
            'livreur_nom' => $current_admin['nom'] ?? '',
        ]);
    }
}
if ($initial_watch_payload !== null) {
    $initial_watch_payload['commande']['livreur_photo_url'] = $livreur_photo_url;
    $initial_watch_payload['commande']['livreur_initials'] = $livreur_initials;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Suivi livraison — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-livreur-suivi.css<?php echo asset_version_query(); ?>">
    <?php if ($show_share_delivery): ?>
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
</head>
<body class="page-livreur-suivi">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<?php if (!$tables_ready): ?>
<div class="livreur-suivi-fallback">
    <div class="message error">
        <i class="fas fa-database"></i>
        <span>Module non installé. Exécutez la migration SQL.</span>
    </div>
</div>
<?php elseif (!$livraison): ?>
<div class="livreur-suivi-fallback">
    <a href="index.php" class="livreur-suivi-topbar__back"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
    <p class="livreur-empty livreur-empty--suivi">Aucune livraison sélectionnée. <a href="index.php">Retour aux livraisons</a>.</p>
</div>
<?php else: ?>

<div class="livreur-suivi-app<?php echo $regarder_mode ? ' is-regarder-mode' : ''; ?>" id="livreur-suivi-app">
    <header class="livreur-suivi-topbar">
        <a href="<?php echo htmlspecialchars($index_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="livreur-suivi-topbar__back" aria-label="Retour">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="livreur-suivi-topbar__title"><?php echo htmlspecialchars($statut_label ?: 'Livraison'); ?></h1>
        <?php if ($regarder_mode && $show_share_delivery): ?>
        <button type="button"
            class="livreur-suivi-topbar__action livreur-suivi-topbar__action--share"
            id="livreur-suivi-share-topbar"
            aria-label="Partager le suivi">
            <i class="fas fa-share-nodes" aria-hidden="true"></i>
        </button>
        <?php elseif ($client_tel !== ''): ?>
        <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $client_tel)); ?>"
            class="livreur-suivi-topbar__action" aria-label="Appeler le client">
            <i class="fas fa-phone" aria-hidden="true"></i>
        </a>
        <?php else: ?>
        <span class="livreur-suivi-topbar__action livreur-suivi-topbar__action--placeholder" aria-hidden="true"></span>
        <?php endif; ?>
    </header>

    <div class="livreur-suivi-map-stage">
        <div id="livreur-tracking-map" class="livreur-tracking-map livreur-tracking-map--fullscreen"></div>

        <div class="livreur-suivi-map-controls" aria-label="Contrôles carte">
            <div class="livreur-suivi-map-controls__group">
                <button type="button" class="livreur-suivi-map-btn" id="livreur-map-zoom-in" aria-label="Zoom avant">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                </button>
                <button type="button" class="livreur-suivi-map-btn" id="livreur-map-zoom-out" aria-label="Zoom arrière">
                    <i class="fas fa-minus" aria-hidden="true"></i>
                </button>
            </div>
            <button type="button" class="livreur-suivi-map-btn" id="livreur-map-fit" aria-label="<?php echo $regarder_mode || $watch_only ? 'Recentrer sur le livreur' : 'Recentrer et actualiser ma position'; ?>">
                <i class="fas fa-location-arrow" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <section class="livreur-suivi-sheet" id="livreur-suivi-sheet" aria-label="Informations livraison">
        <button type="button"
            class="livreur-suivi-sheet__toggle"
            id="livreur-sheet-toggle"
            aria-expanded="true"
            aria-controls="livreur-sheet-body">
            <span class="livreur-suivi-sheet__handle" aria-hidden="true"></span>
            <span class="livreur-suivi-sheet__toggle-label" id="livreur-sheet-toggle-label">Réduire le panneau</span>
            <i class="fas fa-chevron-down livreur-suivi-sheet__toggle-icon" aria-hidden="true"></i>
        </button>

        <div class="livreur-suivi-sheet__compact" id="livreur-sheet-compact" hidden>
            <?php if ($client_nom !== ''): ?>
            <span class="livreur-suivi-sheet__compact-name"><i class="fas fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($client_nom); ?></span>
            <?php endif; ?>
            <?php if ($client_tel !== ''): ?>
            <a href="tel:<?php echo htmlspecialchars($client_tel_href); ?>" class="livreur-suivi-sheet__compact-tel"><?php echo htmlspecialchars($client_tel); ?></a>
            <?php endif; ?>
        </div>

        <div class="livreur-suivi-sheet__body" id="livreur-sheet-body">
            <?php if ($can_start_livraison): ?>
            <div class="livreur-suivi-sheet__controls">
                <div class="livreur-suivi-sheet__toolbar">
                    <button type="button" class="livreur-suivi-sheet__switch-btn" id="livreur-switch-open" aria-haspopup="dialog">
                        <span class="livreur-suivi-sheet__switch-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                        <span class="livreur-suivi-sheet__switch-text">
                            Mes livraisons
                            <strong class="livreur-suivi-sheet__switch-count"><?php echo (int) $mes_livraisons_count; ?></strong>
                        </span>
                        <i class="fas fa-chevron-right livreur-suivi-sheet__switch-arrow" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="livreur-suivi-sheet__actions">
                    <button type="button"
                        class="livreur-suivi-sheet__cta livreur-suivi-sheet__cta--start"
                        id="livreur-suivi-start-tracking"
                        <?php echo !$geo_ready ? 'disabled' : ''; ?>>
                        <span class="livreur-suivi-sheet__cta-main">Démarrer la livraison</span>
                        <span class="livreur-suivi-sheet__cta-sub"><?php echo $geo_ready ? 'Activez le GPS et partagez votre position' : 'Itinéraire non configuré'; ?></span>
                    </button>
                    <button type="button"
                        class="livreur-suivi-sheet__cta livreur-suivi-sheet__cta--stop"
                        id="livreur-suivi-stop-tracking"
                        hidden>
                        <span class="livreur-suivi-sheet__cta-main">Terminer</span>
                        <span class="livreur-suivi-sheet__cta-sub">Arrêter le suivi GPS</span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="livreur-suivi-sheet__head">
            <div class="livreur-suivi-sheet__status">
                <h2 id="livreur-suivi-status-title" class="livreur-suivi-sheet__status-title">Connexion…</h2>
                <p id="livreur-suivi-status-sub" class="livreur-suivi-status livreur-suivi-status--pending">Initialisation du suivi GPS</p>
            </div>
        </div>

        <div class="livreur-suivi-sheet__client">
            <?php if ($client_nom !== '' || $client_tel !== ''): ?>
            <p class="livreur-suivi-sheet__contact">
                <?php if ($client_nom !== ''): ?>
                <span class="livreur-suivi-sheet__name"><i class="fas fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($client_nom); ?></span>
                <?php endif; ?>
                <?php if ($client_tel !== ''): ?>
                <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $client_tel)); ?>" class="livreur-suivi-sheet__tel"><?php echo htmlspecialchars($client_tel); ?></a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <p class="livreur-suivi-sheet__adresse"><?php echo htmlspecialchars($livraison['adresse_livraison'] ?? ''); ?></p>
        </div>

        <div class="livreur-suivi-sheet__eta" id="livreur-suivi-eta" hidden aria-live="polite">
            <span class="livreur-suivi-sheet__eta-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
            <div class="livreur-suivi-sheet__eta-body">
                <span class="livreur-suivi-sheet__eta-label">Temps de trajet estimé</span>
                <strong class="livreur-suivi-sheet__eta-range" id="livreur-suivi-eta-range">—</strong>
            </div>
        </div>

        <?php if ($watch_only && $geo_ready): ?>
        <div class="livreur-suivi-sheet__actions livreur-suivi-sheet__actions--watch">
            <?php if ($show_share_delivery): ?>
            <button type="button"
                class="livreur-suivi-sheet__cta livreur-suivi-sheet__cta--share"
                id="livreur-suivi-share-delivery">
                <span class="livreur-suivi-sheet__cta-main"><i class="fas fa-share-nodes" aria-hidden="true"></i> Partager le suivi</span>
                <span class="livreur-suivi-sheet__cta-sub">Envoyer le lien de suivi en direct</span>
            </button>
            <?php endif; ?>
            <p class="livreur-suivi-sheet__watch-note">
                <i class="fas fa-eye" aria-hidden="true"></i>
                Mode observation — suivi du livreur en temps réel
            </p>
        </div>
        <?php elseif (!$geo_ready): ?>
        <button type="button" class="livreur-suivi-sheet__cta livreur-suivi-sheet__cta--disabled" disabled>
            <span class="livreur-suivi-sheet__cta-main">Suivi indisponible</span>
            <span class="livreur-suivi-sheet__cta-sub">Adresse client non géolocalisée</span>
        </button>
        <?php endif; ?>
        </div>
    </section>

    <div id="livreur-switch-modal" class="livreur-switch-modal" hidden role="dialog" aria-modal="true" aria-labelledby="livreur-switch-modal-title">
        <div class="livreur-switch-modal__backdrop" data-livreur-switch-close></div>
        <div class="livreur-switch-modal__panel">
            <header class="livreur-switch-modal__head">
                <div>
                    <h3 class="livreur-switch-modal__title" id="livreur-switch-modal-title">Mes livraisons</h3>
                    <p class="livreur-switch-modal__sub">Vos livraisons prises en charge, non terminées — le GPS continue en arrière-plan.</p>
                </div>
                <button type="button" class="livreur-switch-modal__close" id="livreur-switch-close" aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </header>
            <ul class="livreur-switch-modal__list" id="livreur-switch-list" role="listbox" aria-label="Livraisons en cours"></ul>
        </div>
    </div>

    <div id="livreur-suivi-alert" class="livreur-suivi-alert" hidden role="alertdialog" aria-modal="true" aria-labelledby="livreur-suivi-alert-title" aria-describedby="livreur-suivi-alert-message">
        <div class="livreur-suivi-alert__backdrop" data-livreur-alert-close></div>
        <div class="livreur-suivi-alert__panel">
            <button type="button" class="livreur-suivi-alert__close" id="livreur-suivi-alert-close" aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="livreur-suivi-alert__icon" aria-hidden="true">
                <i class="fas fa-circle-exclamation"></i>
            </div>
            <h3 class="livreur-suivi-alert__title" id="livreur-suivi-alert-title">Erreur</h3>
            <p class="livreur-suivi-alert__message" id="livreur-suivi-alert-message"></p>
            <ul class="livreur-suivi-alert__details" id="livreur-suivi-alert-details" hidden></ul>
            <button type="button" class="livreur-suivi-alert__ok" id="livreur-suivi-alert-ok">Compris</button>
        </div>
    </div>

    <div id="livreur-suivi-confirm-stop" class="livreur-suivi-alert livreur-suivi-confirm" hidden role="alertdialog" aria-modal="true" aria-labelledby="livreur-suivi-confirm-stop-title">
        <div class="livreur-suivi-alert__backdrop" data-livreur-confirm-close></div>
        <div class="livreur-suivi-alert__panel livreur-suivi-confirm__panel">
            <div class="livreur-suivi-alert__icon livreur-suivi-confirm__icon" aria-hidden="true">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <h3 class="livreur-suivi-alert__title" id="livreur-suivi-confirm-stop-title">Terminer la livraison ?</h3>
            <p class="livreur-suivi-alert__message">Confirmez-vous avoir livré la commande et terminé le suivi GPS ?</p>
            <div class="livreur-suivi-confirm__actions">
                <button type="button" class="btn-secondary" id="livreur-suivi-confirm-stop-no" data-livreur-confirm-close>Annuler</button>
                <button type="button" class="btn-primary livreur-suivi-confirm__yes" id="livreur-suivi-confirm-stop-yes">Oui, terminer</button>
            </div>
        </div>
    </div>

    <div id="livreur-suivi-loading" class="livreur-suivi-loading" hidden aria-live="polite" aria-busy="true">
        <div class="livreur-suivi-loading__backdrop"></div>
        <div class="livreur-suivi-loading__card">
            <div class="livreur-suivi-loading__spinner" aria-hidden="true"></div>
            <p class="livreur-suivi-loading__message" id="livreur-suivi-loading-message">Chargement…</p>
        </div>
    </div>
</div>

<script>
window.LIVREUR_TRACKING_CONFIG = {
    commandeId: <?php echo $livraison_type === 'commande' ? (int) $commande_id : 0; ?>,
    blId: <?php echo $livraison_type === 'facture' ? (int) $bl_id : 0; ?>,
    livraisonType: <?php echo json_encode($livraison_type, JSON_UNESCAPED_UNICODE); ?>,
    socketUrl: <?php echo json_encode($socket_client_url, JSON_UNESCAPED_SLASHES); ?>,
    socketPath: <?php echo json_encode($socket_path, JSON_UNESCAPED_SLASHES); ?>,
    watchTokenUrl: <?php echo json_encode($watch_token_url, JSON_UNESCAPED_SLASHES); ?>,
    embeddedWatchToken: <?php echo json_encode($embedded_watch_token, JSON_UNESCAPED_UNICODE); ?>,
    initialWatchPayload: <?php echo $initial_watch_payload !== null
        ? json_encode($initial_watch_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : 'null'; ?>,
    webApiUrl: '/api/tracking/livreur-web.php',
    indexUrl: <?php echo json_encode($index_back_url, JSON_UNESCAPED_SLASHES); ?>,
    canManage: <?php echo $can_start_livraison ? 'true' : 'false'; ?>,
    geoReady: <?php echo $geo_ready ? 'true' : 'false'; ?>,
    realtimeConfigured: <?php echo $realtime_configured ? 'true' : 'false'; ?>,
    trackingActive: <?php echo $tracking_active_initial ? 'true' : 'false'; ?>,
    autostart: <?php echo ($autostart_tracking && $can_start_livraison) ? 'true' : 'false'; ?>,
    watchOnly: <?php echo $watch_only ? 'true' : 'false'; ?>,
    regarderMode: <?php echo $regarder_mode ? 'true' : 'false'; ?>,
    shareLinkUrl: '/api/tracking/share-link.php',
    lastPositionUrl: '/api/tracking/last-position.php',
    deliveryLat: <?php echo $delivery_lat !== null ? json_encode($delivery_lat) : 'null'; ?>,
    deliveryLng: <?php echo $delivery_lng !== null ? json_encode($delivery_lng) : 'null'; ?>,
    defaultCenter: [14.6937, -17.4441],
    defaultZoom: 13,
    navStartZoom: 17.5,
    navRecenterDelayMs: 10000,
    myDeliveries: <?php echo json_encode($mes_livraisons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    myDeliveriesUrl: '/api/tracking/mes-livraisons.php?started=1',
    enableBackgroundTracking: <?php echo $can_start_livraison ? 'true' : 'false'; ?>,
    currentDeliveryKey: <?php echo json_encode(
        $livraison_type === 'facture' ? 'facture-' . (int) $bl_id : 'commande-' . (int) $commande_id,
        JSON_UNESCAPED_UNICODE
    ); ?>,
    livreurPhotoUrl: <?php echo json_encode($livreur_photo_url, JSON_UNESCAPED_SLASHES); ?>,
    livreurInitials: <?php echo json_encode($livreur_initials, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-rotate@0.2.8/dist/leaflet-rotate.js" crossorigin="anonymous"></script>
<script src="/js/livreur-route-api.js<?php echo asset_version_query(); ?>"></script>
<?php if ($realtime_configured): ?>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<?php endif; ?>
<?php if ($show_share_delivery): ?>
<?php include __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
<script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
<?php endif; ?>
<script src="/js/livreur-native-tracking-bridge.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/livreur-bg-tracker.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/admin-livreur-suivi.js?v=<?php echo (int) @filemtime(__DIR__ . '/../../js/admin-livreur-suivi.js'); ?>"></script>

<?php endif; ?>

<?php $skip_admin_bottom_nav = true; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
