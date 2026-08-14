<?php
/**
 * Charge la chaîne de modales panier / commande / succès (une seule fois).
 */
if (defined('CHECKOUT_MODALS_INIT')) {
    return;
}
define('CHECKOUT_MODALS_INIT', true);

if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}

$user_logged_in_ckm = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
?>
<?php if (!$user_logged_in_ckm): ?>
    <?php include __DIR__ . '/auth_intl_tel_head.php'; ?>
<?php endif; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="/css/checkout-modals.css<?php echo asset_version_query(); ?>">

<div class="ckm-root" id="checkout-modals-root" hidden>
    <div class="ckm-backdrop"></div>
    <div class="ckm-stage" role="dialog" aria-modal="true" aria-labelledby="ckm-title">
        <header class="ckm-header">
            <button type="button" class="ckm-icon-btn" id="ckm-back" aria-label="Retour">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="ckm-header__title" id="ckm-title">Votre panier</h2>
            <button type="button" class="ckm-icon-btn" id="ckm-close" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </header>
        <div class="ckm-body">
            <div class="ckm-panel" data-panel="cart" id="ckm-cart-body"></div>
            <div class="ckm-panel" data-panel="checkout" id="ckm-checkout-body"></div>
            <div class="ckm-panel" data-panel="success" id="ckm-success-body"></div>
            <div class="ckm-loader" id="ckm-loader" hidden>
                <div class="ckm-loader__card">
                    <div class="ckm-spinner" aria-hidden="true"></div>
                    <p>Chargement…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$user_logged_in_ckm): ?>
    <?php
    if (!isset($guest_checkout_action)) {
        $guest_checkout_action = 'add_to_panier';
    }
    if (!isset($guest_checkout_return_url)) {
        $guest_checkout_return_url = $_SERVER['REQUEST_URI'] ?? '/index.php';
    }
    include __DIR__ . '/partials/guest_checkout_modal.php';
    include __DIR__ . '/auth_intl_tel_scripts.php';
    ?>
    <script src="/js/guest-checkout-modal.js<?php echo asset_version_query(); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.initGuestCheckoutModal === 'function') {
            var g = window.initGuestCheckoutModal({
                userLoggedIn: false,
                sourceFormId: 'add-to-panier-form'
            });
            window.SugarCheckoutModals = window.SugarCheckoutModals || {};
            window.SugarCheckoutModals._guest = g;
        }
    });
    </script>
<?php endif; ?>

<script>
window.CKM_USER_LOGGED = <?php echo $user_logged_in_ckm ? 'true' : 'false'; ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="/js/commande-geo.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/checkout-modals.js<?php echo asset_version_query(); ?>"></script>
