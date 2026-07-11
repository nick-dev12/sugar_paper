/**
 * Rappel vendeur — interactions modal dashboard
 */
(function () {
    'use strict';

    var overlay = document.getElementById('vrappelOverlay');
    var modal = document.getElementById('vrappelModal');
    if (!overlay || !modal) return;

    document.body.style.overflow = 'hidden';

    /* Pays / région */
    var countrySel = document.getElementById('vrappel_boutique_country');
    var regionSel = document.getElementById('vrappel_boutique_region');
    if (countrySel && regionSel && typeof window.initGeoCountryRegion === 'function') {
        window.initGeoCountryRegion(countrySel, regionSel, 'vrappelGeoRegionsData');
    }

    /* Capture GPS */
    var btnGeo = document.getElementById('vrappelBtnGeo');
    var geoForm = document.getElementById('vrappelGeoForm');
    var latInput = document.getElementById('vrappel_geo_lat');
    var lngInput = document.getElementById('vrappel_geo_lng');
    var geoStatus = document.getElementById('vrappelGeoStatus');

    function setGeoStatus(html) {
        if (!geoStatus) return;
        geoStatus.style.display = 'block';
        geoStatus.innerHTML = html;
    }

    if (btnGeo && geoForm && latInput && lngInput) {
        btnGeo.addEventListener('click', function () {
            setGeoStatus('<i class="fas fa-circle-notch fa-spin"></i> Recherche de votre position…');
            btnGeo.disabled = true;

            function submitCoords(pos) {
                latInput.value = pos.coords.latitude.toFixed(8);
                lngInput.value = pos.coords.longitude.toFixed(8);
                geoForm.submit();
            }

            function onFail() {
                btnGeo.disabled = false;
                setGeoStatus('<i class="fas fa-triangle-exclamation"></i> Autorisez la localisation dans votre navigateur puis réessayez.');
            }

            if (window.GeoNativeBridge && typeof window.GeoNativeBridge.getCurrentPosition === 'function') {
                window.GeoNativeBridge.getCurrentPosition({ maximumAge: 0 })
                    .then(submitCoords)
                    .catch(onFail);
                return;
            }
            if (!('geolocation' in navigator)) {
                onFail();
                return;
            }
            navigator.geolocation.getCurrentPosition(
                submitCoords,
                onFail,
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        });
    }

    /* Aperçu logo */
    var fileInput = document.getElementById('vrappel_logo_file');
    var logoImg = document.getElementById('vrappelLogoImg');
    var logoPlaceholder = document.getElementById('vrappelLogoPlaceholder');
    var objectUrl = null;

    function revokeObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    if (fileInput && logoImg) {
        fileInput.addEventListener('change', function () {
            revokeObjectUrl();
            var f = fileInput.files && fileInput.files[0];
            if (!f) return;
            objectUrl = URL.createObjectURL(f);
            logoImg.removeAttribute('hidden');
            logoImg.src = objectUrl;
            if (logoPlaceholder) logoPlaceholder.style.display = 'none';
        });
    }

    /* Couleurs plein écran */
    var btnOpenColors = document.getElementById('vrappelBtnOpenColors');
    var btnColorsBack = document.getElementById('vrappelColorsBack');
    var c1 = document.getElementById('vrappel_c1');
    var c2 = document.getElementById('vrappel_c2');
    var hexC1 = document.getElementById('vrappelHexC1');
    var hexC2 = document.getElementById('vrappelHexC2');
    var swatchMain = document.getElementById('vrappelSwatchMain');
    var swatchAccent = document.getElementById('vrappelSwatchAccent');
    var mockShop = document.getElementById('vrappelMockShop');
    var mockBadges = [
        document.getElementById('vrappelMockBadge1'),
        document.getElementById('vrappelMockBadge2')
    ];
    var mockPrices = [
        document.getElementById('vrappelMockPrice1'),
        document.getElementById('vrappelMockPrice2')
    ];

    function syncColors() {
        if (c1 && hexC1) hexC1.textContent = c1.value;
        if (c2 && hexC2) hexC2.textContent = c2.value;
        if (c1 && swatchMain) swatchMain.style.background = c1.value;
        if (c2 && swatchAccent) swatchAccent.style.background = c2.value;
        if (mockShop) {
            if (c1) mockShop.style.setProperty('--vr-mock-c1', c1.value);
            if (c2) mockShop.style.setProperty('--vr-mock-c2', c2.value);
        }
        mockBadges.forEach(function (el) {
            if (el && c2) el.style.background = c2.value;
        });
        mockPrices.forEach(function (el) {
            if (el && c2) el.style.color = c2.value;
        });
    }

    if (c1) c1.addEventListener('input', syncColors);
    if (c2) c2.addEventListener('input', syncColors);

    if (btnOpenColors) {
        btnOpenColors.addEventListener('click', function () {
            modal.classList.add('is-fullscreen');
            syncColors();
        });
    }
    if (btnColorsBack) {
        btnColorsBack.addEventListener('click', function () {
            modal.classList.remove('is-fullscreen');
        });
    }

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay && !modal.classList.contains('is-fullscreen')) {
            /* Pas de fermeture au clic extérieur — snooze explicite */
        }
    });
})();
