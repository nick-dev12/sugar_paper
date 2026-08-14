<?php
/**
 * Suivi livraison public — lien partagé (token client).
 * Usage : /suivi-livraison.php?bl_id=7&token=...
 */
require_once __DIR__ . '/includes/tracking_config.php';
require_once __DIR__ . '/models/model_livreur_tracking.php';
require_once __DIR__ . '/models/model_livreur_notes.php';
require_once __DIR__ . '/includes/asset_version.php';

$commande_id = (int) ($_GET['commande_id'] ?? 0);
$bl_id = (int) ($_GET['bl_id'] ?? 0);
$cp_id = (int) ($_GET['cp_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || ($commande_id < 1 && $bl_id < 1 && $cp_id < 1)) {
    http_response_code(400);
    echo 'Lien de suivi invalide.';
    exit;
}

if (!livreur_tracking_tables_ready()) {
    http_response_code(503);
    echo 'Service de suivi indisponible.';
    exit;
}

$token_row = livreur_get_watch_token_row(
    $token,
    $commande_id > 0 ? $commande_id : null,
    $bl_id > 0 ? $bl_id : null,
    $cp_id > 0 ? $cp_id : null
);
if (!$token_row) {
    http_response_code(403);
    echo 'Lien expiré ou invalide.';
    exit;
}

$livraison = false;
$livraison_type = '';
if ($cp_id > 0) {
    $livraison = livreur_get_cp_tracking($cp_id);
    $livraison_type = 'personnalisee';
} elseif ($bl_id > 0) {
    $livraison = livreur_get_facture_tracking($bl_id);
    $livraison_type = 'facture';
} else {
    $livraison = livreur_get_commande_tracking($commande_id);
    $livraison_type = 'commande';
}

if (!$livraison) {
    http_response_code(404);
    echo 'Livraison introuvable.';
    exit;
}

$client_nom = '';
$client_tel = '';
$statut_label = '';
$delivery_lat = null;
$delivery_lng = null;

if ($livraison_type === 'facture') {
    $client_nom = trim((string) ($livraison['client_nom'] ?? $livraison['raison_sociale'] ?? ''));
    $client_tel = trim((string) ($livraison['client_telephone'] ?? ''));
    $statut_label = livreur_facture_statut_livraison($livraison);
} elseif ($livraison_type === 'personnalisee') {
    $client_nom = trim((string) ($livraison['client_prenom'] ?? '') . ' ' . (string) ($livraison['client_nom'] ?? ''));
    $client_tel = trim((string) ($livraison['client_telephone'] ?? ''));
    $statut_label = livreur_cp_statut_livraison($livraison);
} else {
    $client_nom = trim((string) ($livraison['client_prenom'] ?? '') . ' ' . (string) ($livraison['client_nom'] ?? ''));
    $client_tel = trim((string) ($livraison['client_telephone'] ?? ''));
    $statut_label = livreur_statut_label($livraison['statut'] ?? '');
}
$delivery_lat = livreur_parse_coord($livraison['delivery_latitude'] ?? null);
$delivery_lng = livreur_parse_coord($livraison['delivery_longitude'] ?? null);

$tracking_cfg = tracking_load_config();
$socket_client_url = tracking_client_socket_url();
$socket_path = tracking_config_get('socket_path', '/socket.io');
$realtime_configured = tracking_realtime_available();
$geo_ready = $delivery_lat !== null && $delivery_lng !== null;
$tracking_active_initial = (int) ($livraison['tracking_active'] ?? 0);
$initial_countdown = livreur_countdown_state_from_row($livraison);

$livreur_profile = livreur_photo_profile_for_livraison($livraison);
$livreur_photo_url = $livreur_profile['photo_url'];
$livreur_initials = $livreur_profile['initials'];
$livreur_nom_affichage = trim((string) ($livraison['livreur_prenom'] ?? '') . ' ' . (string) ($livraison['livreur_nom'] ?? ''));

$existing_rating = ($livraison_type !== 'personnalisee' && livreur_notes_tables_ready())
    ? livreur_note_get_for_livraison(
        $commande_id > 0 ? $commande_id : null,
        $bl_id > 0 ? $bl_id : null
    )
    : null;
$can_rate_livreur = $livraison_type !== 'personnalisee' && livreur_note_peut_noter($livraison, $livraison_type);
$rating_state = $existing_rating ? 'rated' : ($can_rate_livreur ? 'ready' : 'pending');
$existing_rating_value = $existing_rating ? (int) ($existing_rating['note'] ?? 0) : 0;
$livreur_id_rating = (int) ($livraison['livreur_id'] ?? 0);

$last = null;
if (!empty($livraison['livreur_id'])) {
    $last = livreur_get_last_position(
        (int) $livraison['livreur_id'],
        $commande_id > 0 ? $commande_id : null,
        $bl_id > 0 ? $bl_id : null,
        $cp_id > 0 ? $cp_id : null
    );
}

$initial_payload = [
    'success' => true,
    'watch_token' => $token,
    'livraison_type' => $livraison_type,
    'commande' => [
        'id' => $livraison_type === 'commande' ? $commande_id : ($livraison_type === 'personnalisee' ? $cp_id : $bl_id),
        'numero_commande' => $livraison_type === 'facture'
            ? ($livraison['numero_bl'] ?? '')
            : ($livraison['numero_commande'] ?? ''),
        'tracking_active' => $tracking_active_initial,
        'adresse_livraison' => $livraison['adresse_livraison'] ?? '',
        'delivery_latitude' => $delivery_lat,
        'delivery_longitude' => $delivery_lng,
        'livreur_nom' => trim(($livraison['livreur_prenom'] ?? '') . ' ' . ($livraison['livreur_nom'] ?? '')),
        'livreur_photo_url' => $livreur_photo_url,
        'livreur_initials' => $livreur_initials,
    ],
    'last_position' => $last,
    'countdown' => $initial_countdown,
    'socket_path' => $socket_path,
];

$client_tel_href = $client_tel !== '' ? preg_replace('/\s+/', '', $client_tel) : '';
$page_title = 'Suivi livraison' . ($client_nom !== '' ? ' — ' . $client_nom : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="/css/admin-livreur-suivi.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-livreur-suivi page-livreur-suivi--public">

<div class="livreur-suivi-app is-public-watch" id="livreur-suivi-app">
    <header class="livreur-suivi-topbar livreur-suivi-topbar--public">
        <span class="livreur-suivi-topbar__brand" aria-hidden="true"><i class="fas fa-truck-fast"></i></span>
        <div class="livreur-suivi-topbar__main">
            <h1 class="livreur-suivi-topbar__title" id="livreur-topbar-title"><?php echo htmlspecialchars($statut_label ?: 'Suivi livraison'); ?></h1>
            <div class="livreur-suivi-topbar__countdown" id="livreur-topbar-countdown" hidden aria-live="polite">
                <span class="livreur-suivi-topbar__countdown-label" id="livreur-topbar-countdown-label">Arrivée dans</span>
                <strong class="livreur-suivi-topbar__countdown-value" id="livreur-topbar-countdown-value">—</strong>
            </div>
        </div>
        <a href="/index.php" class="livreur-suivi-topbar__action livreur-suivi-topbar__action--brand" aria-label="Sugar Paper — Accueil">
            <img src="/image/sugar_paper.jpg" alt="Sugar Paper" class="livreur-suivi-topbar__logo">
        </a>
    </header>

    <div class="livreur-suivi-map-stage">
        <div id="livreur-tracking-map" class="livreur-tracking-map livreur-tracking-map--fullscreen"></div>
        <div class="livreur-suivi-map-controls" aria-label="Contrôles carte">
            <div class="livreur-suivi-map-controls__group">
                <button type="button" class="livreur-suivi-map-btn" id="livreur-map-zoom-in" aria-label="Zoom avant"><i class="fas fa-plus"></i></button>
                <button type="button" class="livreur-suivi-map-btn" id="livreur-map-zoom-out" aria-label="Zoom arrière"><i class="fas fa-minus"></i></button>
            </div>
            <button type="button" class="livreur-suivi-map-btn" id="livreur-map-fit" aria-label="Recentrer sur le livreur">
                <i class="fas fa-location-arrow" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <section class="livreur-suivi-sheet" id="livreur-suivi-sheet" aria-label="Informations livraison">
        <button type="button" class="livreur-suivi-sheet__toggle" id="livreur-sheet-toggle" aria-expanded="true" aria-controls="livreur-sheet-body">
            <span class="livreur-suivi-sheet__handle" aria-hidden="true"></span>
            <span class="livreur-suivi-sheet__toggle-label" id="livreur-sheet-toggle-label">Réduire le panneau</span>
            <i class="fas fa-chevron-down livreur-suivi-sheet__toggle-icon" aria-hidden="true"></i>
        </button>
        <div class="livreur-suivi-sheet__compact" id="livreur-sheet-compact" hidden>
            <?php if ($client_nom !== ''): ?><span class="livreur-suivi-sheet__compact-name"><?php echo htmlspecialchars($client_nom); ?></span><?php endif; ?>
        </div>
        <div class="livreur-suivi-sheet__body" id="livreur-sheet-body">
            <div class="livreur-suivi-sheet__head">
                <div class="livreur-suivi-sheet__status">
                    <h2 id="livreur-suivi-status-title" class="livreur-suivi-sheet__status-title">Connexion…</h2>
                    <p id="livreur-suivi-status-sub" class="livreur-suivi-status livreur-suivi-status--pending">Chargement du suivi</p>
                </div>
            </div>
            <div class="livreur-suivi-sheet__client">
                <?php if ($client_nom !== ''): ?>
                <p class="livreur-suivi-sheet__contact">
                    <span class="livreur-suivi-sheet__name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($client_nom); ?></span>
                </p>
                <?php endif; ?>
                <p class="livreur-suivi-sheet__adresse"><?php echo htmlspecialchars($livraison['adresse_livraison'] ?? ''); ?></p>
            </div>
            <div class="livreur-suivi-sheet__eta" id="livreur-suivi-eta" hidden aria-live="polite">
                <span class="livreur-suivi-sheet__eta-icon"><i class="fas fa-clock"></i></span>
                <div class="livreur-suivi-sheet__eta-body">
                    <span class="livreur-suivi-sheet__eta-label" id="livreur-suivi-eta-label">Arrivée estimée dans</span>
                    <strong class="livreur-suivi-sheet__eta-range" id="livreur-suivi-eta-range">—</strong>
                </div>
            </div>
            <section id="livreur-rating-section" class="livreur-rating" data-state="<?php echo htmlspecialchars($rating_state, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Notation du livreur">
                <h3 class="livreur-rating__title">Notez votre livreur</h3>
                <div class="livreur-rating__livreur">
                    <?php if ($livreur_photo_url !== ''): ?>
                    <img src="<?php echo htmlspecialchars($livreur_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="livreur-rating__avatar livreur-rating__avatar--photo" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <?php endif; ?>
                    <span class="livreur-rating__avatar livreur-rating__avatar--initials"<?php echo $livreur_photo_url !== '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($livreur_initials ?: 'L', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="livreur-rating__name"><?php echo htmlspecialchars($livreur_nom_affichage !== '' ? $livreur_nom_affichage : 'Votre livreur', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="livreur-rating__stars-wrap">
                    <div class="livreur-rating__stars" id="livreur-rating-stars" role="radiogroup" aria-label="Note sur 5 étoiles">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <button type="button" class="livreur-rating__star" data-star="<?php echo $s; ?>" aria-label="<?php echo $s; ?> étoile<?php echo $s > 1 ? 's' : ''; ?>"<?php echo ($rating_state !== 'ready') ? ' disabled' : ''; ?>>
                            <i class="fa-star<?php echo ($existing_rating_value >= $s) ? ' fas livreur-rating__star--filled' : ' far'; ?>" aria-hidden="true"></i>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <p class="livreur-rating__hint" id="livreur-rating-hint">
                        <?php if ($rating_state === 'rated'): ?>
                            Merci ! Votre note est enregistrée.
                        <?php elseif ($can_rate_livreur): ?>
                            Touchez une étoile pour noter la livraison.
                        <?php else: ?>
                            Disponible dès l'arrivée du livreur chez vous.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="livreur-rating__thanks" id="livreur-rating-thanks" hidden aria-live="polite">
                    <div class="livreur-rating__thanks-burst" aria-hidden="true">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="livreur-rating__thanks-title">Merci pour votre confiance !</p>
                    <p class="livreur-rating__thanks-text">Votre avis compte beaucoup pour nous.</p>
                </div>
                <div class="livreur-rating__frozen" id="livreur-rating-frozen"<?php echo $rating_state === 'rated' ? '' : ' hidden'; ?>>
                    <p class="livreur-rating__frozen-label">Votre note</p>
                    <div class="livreur-rating__frozen-stars" id="livreur-rating-frozen-stars">
                        <?php echo livreur_note_format_stars_html($existing_rating_value); ?>
                    </div>
                    <p class="livreur-rating__frozen-value"><strong id="livreur-rating-frozen-value"><?php echo $existing_rating_value > 0 ? (int) $existing_rating_value : '—'; ?></strong> / 5</p>
                </div>
            </section>
            <p class="livreur-suivi-sheet__watch-note"><i class="fas fa-satellite-dish"></i> Suivi en direct de la livraison</p>
        </div>
    </section>
</div>

<div id="livreur-suivi-alert" class="livreur-suivi-alert" hidden role="alertdialog" aria-modal="true">
    <div class="livreur-suivi-alert__backdrop" id="livreur-suivi-alert-backdrop"></div>
    <div class="livreur-suivi-alert__panel">
        <button type="button" class="livreur-suivi-alert__close" id="livreur-suivi-alert-close" aria-label="Fermer"><i class="fas fa-times"></i></button>
        <div class="livreur-suivi-alert__icon"><i class="fas fa-circle-exclamation"></i></div>
        <h3 class="livreur-suivi-alert__title" id="livreur-suivi-alert-title">Erreur</h3>
        <p class="livreur-suivi-alert__message" id="livreur-suivi-alert-message"></p>
        <ul class="livreur-suivi-alert__details" id="livreur-suivi-alert-details" hidden></ul>
        <button type="button" class="livreur-suivi-alert__ok" id="livreur-suivi-alert-ok">Compris</button>
    </div>
</div>

<script>
window.LIVREUR_TRACKING_CONFIG = {
    commandeId: <?php echo $livraison_type === 'commande' ? (int) $commande_id : 0; ?>,
    blId: <?php echo $livraison_type === 'facture' ? (int) $bl_id : 0; ?>,
    cpId: <?php echo $livraison_type === 'personnalisee' ? (int) $cp_id : 0; ?>,
    livraisonType: <?php echo json_encode($livraison_type, JSON_UNESCAPED_UNICODE); ?>,
    socketUrl: <?php echo json_encode($socket_client_url, JSON_UNESCAPED_SLASHES); ?>,
    socketPath: <?php echo json_encode($socket_path, JSON_UNESCAPED_SLASHES); ?>,
    watchTokenUrl: '',
    embeddedWatchToken: <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>,
    publicWatchToken: <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>,
    initialWatchPayload: <?php echo json_encode($initial_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    webApiUrl: '',
    indexUrl: '',
    canManage: false,
    watchOnly: true,
    publicMode: true,
    regarderMode: true,
    geoReady: <?php echo $geo_ready ? 'true' : 'false'; ?>,
    realtimeConfigured: <?php echo $realtime_configured ? 'true' : 'false'; ?>,
    trackingActive: <?php echo $tracking_active_initial ? 'true' : 'false'; ?>,
    initialCountdown: <?php echo $initial_countdown !== null
        ? json_encode($initial_countdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : 'null'; ?>,
    autostart: true,
    lastPositionUrl: '/api/tracking/last-position.php',
    deliveryLat: <?php echo $delivery_lat !== null ? json_encode($delivery_lat) : 'null'; ?>,
    deliveryLng: <?php echo $delivery_lng !== null ? json_encode($delivery_lng) : 'null'; ?>,
    defaultCenter: [14.6937, -17.4441],
    defaultZoom: 13,
    navStartZoom: 17.5,
    navRecenterDelayMs: 10000,
    myDeliveries: [],
    myDeliveriesUrl: '',
    currentDeliveryKey: <?php echo json_encode(
        $livraison_type === 'facture'
            ? 'facture-' . (int) $bl_id
            : ($livraison_type === 'personnalisee' ? 'personnalisee-' . (int) $cp_id : 'commande-' . (int) $commande_id),
        JSON_UNESCAPED_UNICODE
    ); ?>,
    livreurPhotoUrl: <?php echo json_encode($livreur_photo_url, JSON_UNESCAPED_SLASHES); ?>,
    livreurInitials: <?php echo json_encode($livreur_initials, JSON_UNESCAPED_UNICODE); ?>,
    ratingApiUrl: '/api/tracking/rate-livreur.php',
    ratingToken: <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>,
    ratingState: <?php echo json_encode($rating_state, JSON_UNESCAPED_UNICODE); ?>,
    existingRating: <?php echo (int) $existing_rating_value; ?>,
    canRateLivreur: <?php echo $can_rate_livreur ? 'true' : 'false'; ?>,
    livreurId: <?php echo (int) $livreur_id_rating; ?>,
    arriveeAt: <?php echo !empty($livraison['livraison_arrivee_at'])
        ? json_encode($livraison['livraison_arrivee_at'], JSON_UNESCAPED_UNICODE)
        : 'null'; ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-rotate@0.2.8/dist/leaflet-rotate.js" crossorigin="anonymous"></script>
<script src="/js/livreur-route-api.js<?php echo asset_version_query(); ?>"></script>
<?php if ($realtime_configured): ?>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<?php endif; ?>
<script src="/js/admin-livreur-suivi.js?v=<?php echo (int) @filemtime(__DIR__ . '/js/admin-livreur-suivi.js'); ?>"></script>
<script src="/js/livreur-rating.js<?php echo asset_version_query(); ?>"></script>
<?php include __DIR__ . '/includes/floating_back_button.php'; ?>
</body>
</html>
