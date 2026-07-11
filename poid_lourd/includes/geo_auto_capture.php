<?php
/**
 * Enregistrement automatique et silencieux de la position du client connecté.
 * Inclus en fin de nav_bar.php : ne s'affiche que si
 *  - un client est connecté (session user_id)
 *  - aucune position fraîche n'est déjà en session
 * Le navigateur capture la position (consentement via le navigateur) puis
 * soumet un formulaire POST classique vers /set-location.php (session + users).
 * Une seule tentative par session navigateur (garde sessionStorage).
 */

if (empty($_SESSION['user_id'])) {
    return;
}

require_once __DIR__ . '/geo_location_service.php';

if (geo_session_get_location() !== null) {
    return;
}

$geo_ac_redirect = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/index.php';
if ($geo_ac_redirect === '' || strpos($geo_ac_redirect, '/') !== 0 || strpos($geo_ac_redirect, '//') === 0) {
    $geo_ac_redirect = '/index.php';
}
?>
<?php require_once __DIR__ . '/geo_native_bridge_script.php'; ?>
<form method="POST" action="/set-location.php" id="geo-auto-capture-form" style="display:none;">
    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($geo_ac_redirect, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="geo_lat" id="geo_ac_lat" value="">
    <input type="hidden" name="geo_lng" id="geo_ac_lng" value="">
    <input type="hidden" name="geo_precision" id="geo_ac_precision" value="">
    <input type="hidden" name="geo_source" value="gps">
</form>
<script>
(function () {
    'use strict';
    if (document.getElementById('geo_lat')) return;

    try {
        if (sessionStorage.getItem('geoAutoCaptureDone') === '1') return;
    } catch (e) { return; }

    function submitPosition(pos) {
        var form = document.getElementById('geo-auto-capture-form');
        var lat = document.getElementById('geo_ac_lat');
        var lng = document.getElementById('geo_ac_lng');
        var prec = document.getElementById('geo_ac_precision');
        if (!form || !lat || !lng) return;
        lat.value = pos.coords.latitude.toFixed(8);
        lng.value = pos.coords.longitude.toFixed(8);
        if (prec) prec.value = Math.round(pos.coords.accuracy || 0);
        form.submit();
    }

    function attempt() {
        if (window.GeoNativeBridge && typeof window.GeoNativeBridge.getCurrentPosition === 'function') {
            window.GeoNativeBridge.getCurrentPosition({ maximumAge: 300000, nativeWaitMs: 6000 })
                .then(function (pos) {
                    try { sessionStorage.setItem('geoAutoCaptureDone', '1'); } catch (e) {}
                    submitPosition(pos);
                })
                .catch(function () {});
            return;
        }
        if (!('geolocation' in navigator)) return;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                try { sessionStorage.setItem('geoAutoCaptureDone', '1'); } catch (e) {}
                submitPosition(pos);
            },
            function () { /* silencieux */ },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
        );
    }

    if (window.GeoNativeBridge || ('geolocation' in navigator)) {
        attempt();
    }
})();
</script>
