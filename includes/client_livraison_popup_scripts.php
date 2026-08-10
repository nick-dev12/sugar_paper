<?php
/**
 * Popup « Livreur en route » — client connecté, toutes pages boutique + espace user.
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$user_id_popup = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($user_id_popup < 1) {
    return;
}

$request_path_popup = strtolower(str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '')));
if (strpos($request_path_popup, '/admin/') !== false) {
    return;
}

require_once __DIR__ . '/asset_version.php';
$clp_css_v = file_exists(__DIR__ . '/../css/client-livraison-popup.css')
    ? (string) filemtime(__DIR__ . '/../css/client-livraison-popup.css')
    : get_asset_version();
$clp_js_v = file_exists(__DIR__ . '/../js/client-livraison-popup.js')
    ? (string) filemtime(__DIR__ . '/../js/client-livraison-popup.js')
    : get_asset_version();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="/css/client-livraison-popup.css?v=<?php echo htmlspecialchars($clp_css_v, ENT_QUOTES, 'UTF-8'); ?>">
<div id="client-livraison-popup" class="clp" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="clp-title">
    <div class="clp__backdrop" data-clp-close tabindex="-1"></div>
    <div class="clp__panel">
        <button type="button" class="clp__close" data-clp-close aria-label="Fermer">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <div class="clp__hero">
            <span class="clp__pulse" aria-hidden="true"></span>
            <div class="clp__hero-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></div>
            <p class="clp__eyebrow">Livraison en direct</p>
            <h2 class="clp__title" id="clp-title">Votre livreur est en route</h2>
            <p class="clp__subtitle" id="clp-subtitle">Suivez sa position en temps réel sur la carte.</p>
        </div>
        <div class="clp__meta">
            <span class="clp__badge" id="clp-numero">Commande</span>
            <span class="clp__livreur" id="clp-livreur"><i class="fas fa-user"></i> Livreur</span>
        </div>
        <div class="clp__map-wrap">
            <div id="clp-mini-map" class="clp__map" aria-label="Mini carte livraison"></div>
            <div class="clp__map-loader" id="clp-map-loader" aria-hidden="true">
                <span class="clp__map-loader-dot"></span>
            </div>
        </div>
        <div class="clp__actions">
            <a href="#" id="clp-follow-btn" class="clp__btn clp__btn--primary">
                <i class="fas fa-location-dot" aria-hidden="true"></i>
                Suivre la livraison
            </a>
            <button type="button" class="clp__btn clp__btn--ghost" data-clp-close>Plus tard</button>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/js/client-livraison-popup.js?v=<?php echo htmlspecialchars($clp_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
