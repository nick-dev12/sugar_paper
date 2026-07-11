<?php
if (empty($vbl_admin) || !is_array($vbl_admin)) {
    return;
}
if (!isset($vbl_country_val)) {
    require_once __DIR__ . '/../vendeur_boutique_localisation_ui_vars.php';
    extract(vendeur_boutique_localisation_prepare_ui_vars($vbl_admin, [
        'admin_id' => (int) ($vbl_admin_id ?? ($vbl_admin['id'] ?? 0)),
        'allow_reverse_geocode_upgrade' => false,
    ]));
}
?>
<div class="abl-block" role="region" aria-labelledby="abl-localisation-title">
    <div class="prm-section-head" style="margin:0;">
        <h2 class="prm-section-head__title" id="abl-localisation-title">Localisation de la boutique</h2>
    </div>

    <form method="POST" action="parametres.php" class="abl-card">
        <input type="hidden" name="csrf_token" value="<?php echo $vbl_csrf; ?>">
        <input type="hidden" name="boutique_localisation_save" value="1">
        <input type="hidden" name="localisation_part" value="region">
        <div class="abl-card__head">
            <div class="abl-card__head-icon abl-card__head-icon--map"><i class="fas fa-map-location-dot"></i></div>
            <div class="abl-card__head-text">
                <h3>Pays et région de la boutique</h3>
                <p>Zone géographique de votre vitrine</p>
            </div>
        </div>
        <div class="abl-card__body">
            <script type="application/json" id="geoRegionsData"><?php echo geo_regions_json_for_js(); ?></script>
            <div class="abl-form-group">
                <label class="abl-form-label" for="boutique_country">Pays *</label>
                <select id="boutique_country" class="abl-select" name="boutique_country" required>
                    <?php echo marketplace_countries_options_html($vbl_country_val, false); ?>
                </select>
            </div>
            <div class="abl-form-group">
                <label class="abl-form-label" for="boutique_region">Région *</label>
                <select id="boutique_region" class="abl-select" name="boutique_region" required
                    data-selected="<?php echo $vbl_region_val; ?>"
                    data-empty-label="Sélectionnez une région">
                    <?php echo geo_regions_options_html($vbl_country_val, $vbl_region_raw, true, 'Sélectionnez une région'); ?>
                </select>
            </div>
            <button type="submit" class="abl-save abl-save--gold">
                <i class="fas fa-floppy-disk"></i> Enregistrer le pays et la région
            </button>
        </div>
    </form>

    <section class="abl-card" aria-labelledby="abl-geo-title">
        <div class="abl-card__head">
            <div class="abl-card__head-icon abl-card__head-icon--geo"><i class="fas fa-location-crosshairs"></i></div>
            <div class="abl-card__head-text">
                <h3 id="abl-geo-title">Position GPS de la boutique</h3>
                <p>Lieu en tête puis quartier, arrondissement et ville — sans virgules. Le GPS exact reste enregistré.</p>
            </div>
        </div>
        <div class="abl-card__body">
            <?php if ($vbl_geo_set): ?>
            <div class="abl-position-saved">
                <p class="abl-position-saved__label">Position enregistrée</p>
                <?php if ($vbl_position_text !== ''): ?>
                <p class="abl-position-saved__text">
                    <i class="fas fa-location-dot" aria-hidden="true"></i>
                    <?php echo $vbl_position_text_html; ?>
                </p>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(geo_gmaps_link($vbl_geo_lat, $vbl_geo_lng), ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank" rel="noopener noreferrer" class="abl-gmaps-btn">
                    <i class="fab fa-google"></i> Ouvrir avec Google Maps
                </a>
                <p class="abl-position-saved__coords">
                    <i class="fas fa-crosshairs" aria-hidden="true"></i>
                    <?php echo htmlspecialchars(number_format($vbl_geo_lat, 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>,
                    <?php echo htmlspecialchars(number_format($vbl_geo_lng, 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
            <?php else: ?>
            <p class="abl-hint"><i class="fas fa-info-circle"></i> Aucune position enregistrée.</p>
            <?php endif; ?>

            <div class="abl-actions">
                <form method="POST" action="parametres.php" id="abl-geo-capture-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $vbl_csrf; ?>">
                    <input type="hidden" name="geo_boutique_action" value="capture">
                    <input type="hidden" name="geo_lat" id="abl_geo_lat" value="">
                    <input type="hidden" name="geo_lng" id="abl_geo_lng" value="">
                    <button type="button" id="abl-btn-geo-capture" class="abl-save abl-save--gold">
                        <i class="fas fa-location-crosshairs"></i> Utiliser ma position actuelle
                    </button>
                </form>
                <button type="button" id="abl-btn-geo-custom-toggle" class="abl-save abl-save--blue" aria-expanded="false" aria-controls="abl-geo-custom-panel">
                    <i class="fas fa-pen-to-square"></i> Personnaliser ma position
                </button>
            </div>

            <div id="abl-geo-custom-panel" class="abl-custom-panel" hidden>
                <form method="POST" action="parametres.php" class="abl-custom-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $vbl_csrf; ?>">
                    <input type="hidden" name="geo_boutique_action" value="manual">
                    <p class="abl-custom-form__intro">Lieu puis quartier, arrondissement et ville — ex. : « Plan Jaxaay 01 Parcelles Jaxaay Dakar »</p>
                    <div class="abl-form-group">
                        <label class="abl-form-label" for="boutique_position_text">Adresse de retrait *</label>
                        <textarea id="boutique_position_text" class="abl-textarea" name="boutique_position_text" rows="2" required
                            placeholder="Ex. : Plan Jaxaay 01 Parcelles Jaxaay Dakar"><?php echo $vbl_position_text_html; ?></textarea>
                    </div>
                    <div class="abl-custom-form__actions">
                        <button type="submit" class="abl-save abl-save--blue">
                            <i class="fas fa-floppy-disk"></i> Enregistrer
                        </button>
                        <button type="button" id="abl-btn-geo-custom-cancel" class="abl-save abl-save--danger">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
            <div id="abl-geo-status" class="abl-status"></div>
        </div>
    </section>
</div>

<script src="/js/geo-native-bridge.js<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>"></script>
<script src="/js/geo-address-format.js<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>" defer></script>
<script src="/js/geo-country-region.js<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>" defer></script>
<script>
(function () {
    var btnCapture = document.getElementById('abl-btn-geo-capture');
    var formCapture = document.getElementById('abl-geo-capture-form');
    var latInput = document.getElementById('abl_geo_lat');
    var lngInput = document.getElementById('abl_geo_lng');
    var statusEl = document.getElementById('abl-geo-status');
    var btnCustom = document.getElementById('abl-btn-geo-custom-toggle');
    var btnCancel = document.getElementById('abl-btn-geo-custom-cancel');
    var customPanel = document.getElementById('abl-geo-custom-panel');

    function setStatus(msg) {
        if (!statusEl) return;
        statusEl.style.display = 'block';
        statusEl.innerHTML = msg;
    }

    function closeCustomPanel() {
        if (!customPanel || !btnCustom) return;
        customPanel.setAttribute('hidden', 'hidden');
        btnCustom.setAttribute('aria-expanded', 'false');
    }

    if (btnCustom && customPanel) {
        btnCustom.addEventListener('click', function () {
            if (customPanel.hasAttribute('hidden')) {
                customPanel.removeAttribute('hidden');
                btnCustom.setAttribute('aria-expanded', 'true');
            } else {
                closeCustomPanel();
            }
        });
    }
    if (btnCancel) btnCancel.addEventListener('click', closeCustomPanel);

    if (btnCapture && formCapture && latInput && lngInput) {
        btnCapture.addEventListener('click', function () {
            setStatus('<i class="fas fa-circle-notch fa-spin"></i> Recherche…');
            function submitCoords(pos) {
                latInput.value = pos.coords.latitude.toFixed(8);
                lngInput.value = pos.coords.longitude.toFixed(8);
                formCapture.submit();
            }
            if (window.GeoNativeBridge && typeof window.GeoNativeBridge.getCurrentPosition === 'function') {
                window.GeoNativeBridge.getCurrentPosition({ maximumAge: 0 })
                    .then(submitCoords)
                    .catch(function () {
                        setStatus('<i class="fas fa-triangle-exclamation"></i> Autorisez la localisation ou personnalisez la position.');
                    });
                return;
            }
            if (!('geolocation' in navigator)) {
                setStatus('<i class="fas fa-triangle-exclamation"></i> Géolocalisation non disponible.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                submitCoords,
                function () {
                    setStatus('<i class="fas fa-triangle-exclamation"></i> Autorisez la localisation ou personnalisez la position.');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        });
    }
})();
</script>
