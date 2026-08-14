/**
 * Commande client — mode livraison / retrait + carte GPS temps réel.
 */
(function () {
    'use strict';

    var watchId = null;
    var map = null;
    var marker = null;
    var accuracyCircle = null;
    var reverseTimer = null;
    var DEFAULT_LAT = 14.6937;
    var DEFAULT_LNG = -17.4441;
    var DEFAULT_ZOOM = 13;

    function qs(id) {
        return document.getElementById(id);
    }

    function parseCoord(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var n = parseFloat(String(value).replace(',', '.'));
        return isFinite(n) ? n : null;
    }

    function coordsValid(lat, lng) {
        if (lat === null || lng === null) {
            return false;
        }
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return false;
        }
        if (Math.abs(lat) < 0.0001 && Math.abs(lng) < 0.0001) {
            return false;
        }
        return true;
    }

    function setStatus(state, message) {
        var el = qs('commande-geo-status');
        if (!el) {
            return;
        }
        el.style.display = '';
        el.setAttribute('data-geo-state', state);
        var icons = {
            pending: '<i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>',
            ok: '<i class="fas fa-location-crosshairs" aria-hidden="true"></i>',
            error: '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>'
        };
        el.innerHTML = (icons[state] || '') + ' ' + message;
    }

    function fillField(id, value) {
        var input = qs(id);
        if (input) {
            input.value = value;
        }
    }

    function reverseGeocode(lat, lng) {
        clearTimeout(reverseTimer);
        reverseTimer = setTimeout(function () {
            fetch('/api/geo-reverse.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (data && data.ok && data.label) {
                        fillField('geo_address', data.label);
                    }
                })
                .catch(function () {});
        }, 400);
    }

    function showMapContainer() {
        var container = qs('commande-geo-map');
        if (container) {
            container.style.display = 'block';
        }
        return container;
    }

    function ensureMap(lat, lng, zoom) {
        if (typeof window.L === 'undefined') {
            setStatus('error', 'Carte indisponible (Leaflet non chargé). Rechargez la page.');
            return null;
        }
        var container = showMapContainer();
        if (!container) {
            return null;
        }

        var viewLat = typeof lat === 'number' && isFinite(lat) ? lat : DEFAULT_LAT;
        var viewLng = typeof lng === 'number' && isFinite(lng) ? lng : DEFAULT_LNG;
        var viewZoom = typeof zoom === 'number' && isFinite(zoom) ? zoom : DEFAULT_ZOOM;

        if (!map) {
            map = L.map(container.id, { zoomControl: true }).setView([viewLat, viewLng], viewZoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            marker = L.marker([viewLat, viewLng], { draggable: true }).addTo(map);
            marker.bindPopup('Votre position — déplacez le marqueur si besoin');
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                applyPosition(p.lat, p.lng, null, 'map_pin', true);
                setStatus('ok', 'Position ajustée sur la carte.');
            });

            setTimeout(function () {
                if (map) {
                    map.invalidateSize();
                }
            }, 200);
            setTimeout(function () {
                if (map) {
                    map.invalidateSize();
                }
            }, 600);
        }

        return map;
    }

    function updateMap(lat, lng, precision, keepView) {
        var m = ensureMap(lat, lng, coordsValid(lat, lng) ? 16 : DEFAULT_ZOOM);
        if (!m || !marker) {
            return;
        }
        marker.setLatLng([lat, lng]);
        if (accuracyCircle) {
            m.removeLayer(accuracyCircle);
            accuracyCircle = null;
        }
        if (precision && precision > 0 && precision < 5000) {
            accuracyCircle = L.circle([lat, lng], {
                radius: precision,
                color: '#918a44',
                fillColor: '#c26638',
                fillOpacity: 0.12,
                weight: 2
            }).addTo(m);
        }
        if (!keepView) {
            m.setView([lat, lng], 16);
        }
    }

    function applyPosition(lat, lng, precision, source, keepView) {
        fillField('geo_lat', lat.toFixed(8));
        fillField('geo_lng', lng.toFixed(8));
        fillField('geo_precision', precision !== null && precision !== undefined ? String(Math.round(precision)) : '');
        fillField('geo_source', source || 'gps');
        updateMap(lat, lng, precision, !!keepView);
        reverseGeocode(lat, lng);
    }

    function stopWatch() {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    function startWatch() {
        stopWatch();
        if (!('geolocation' in navigator)) {
            setStatus('error', 'La géolocalisation n\'est pas disponible sur cet appareil.');
            return;
        }

        setStatus('pending', 'Capture de votre position en temps réel…');

        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var precision = Math.round(pos.coords.accuracy || 0);
                applyPosition(lat, lng, precision, 'gps', false);
                setStatus('ok', 'Position capturée (± ' + precision + ' m). Elle se met à jour automatiquement.');
            },
            function (err) {
                var msg = 'Impossible d\'obtenir votre position.';
                if (err && err.code === 1) {
                    msg = 'Géolocalisation refusée. Placez le marqueur sur la carte à votre adresse exacte.';
                } else if (err && err.code === 2) {
                    msg = 'Signal GPS indisponible. Déplacez le marqueur sur la carte à votre adresse.';
                } else {
                    msg = 'Position GPS indisponible. Déplacez le marqueur sur la carte à votre adresse.';
                }
                ensureMap(DEFAULT_LAT, DEFAULT_LNG, DEFAULT_ZOOM);
                setStatus('error', msg);
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            }
        );
    }

    function getMode() {
        var input = qs('mode_livraison');
        return input && input.value === 'retrait' ? 'retrait' : 'livraison';
    }

    function setMode(mode) {
        mode = mode === 'retrait' ? 'retrait' : 'livraison';
        var modeInput = qs('mode_livraison');
        var panelLivraison = qs('panel-livraison');
        var panelRetrait = qs('panel-retrait');
        var btnLivraison = qs('cmd-mode-btn-livraison');
        var btnRetrait = qs('cmd-mode-btn-retrait');
        var selectZone = qs('zone_livraison_id');
        var hiddenRetraitZone = qs('zone_retrait_id');
        var telLabel = qs('tel-label-text');

        if (modeInput) {
            modeInput.value = mode;
        }
        if (panelLivraison) {
            panelLivraison.classList.toggle('is-visible', mode === 'livraison');
        }
        if (panelRetrait) {
            panelRetrait.classList.toggle('is-visible', mode === 'retrait');
        }
        var geoBox = document.querySelector('.commande-geo-box');
        if (geoBox) {
            geoBox.style.display = mode === 'livraison' ? '' : 'none';
        }
        if (btnLivraison) {
            btnLivraison.classList.toggle('is-active', mode === 'livraison');
            btnLivraison.setAttribute('aria-selected', mode === 'livraison' ? 'true' : 'false');
        }
        if (btnRetrait) {
            btnRetrait.classList.toggle('is-active', mode === 'retrait');
            btnRetrait.setAttribute('aria-selected', mode === 'retrait' ? 'true' : 'false');
        }
        if (selectZone) {
            selectZone.disabled = mode === 'retrait';
            selectZone.required = mode === 'livraison';
        }
        if (hiddenRetraitZone) {
            hiddenRetraitZone.disabled = mode !== 'retrait';
        }
        if (telLabel) {
            telLabel.textContent = mode === 'retrait' ? 'Téléphone de contact' : 'Téléphone de livraison';
        }

        if (mode === 'livraison') {
            var savedLat = parseCoord(qs('geo_lat') && qs('geo_lat').value);
            var savedLng = parseCoord(qs('geo_lng') && qs('geo_lng').value);
            showMapContainer();
            if (coordsValid(savedLat, savedLng)) {
                ensureMap(savedLat, savedLng, 16);
                updateMap(savedLat, savedLng, parseCoord(qs('geo_precision') && qs('geo_precision').value), true);
            } else {
                ensureMap(DEFAULT_LAT, DEFAULT_LNG, DEFAULT_ZOOM);
                setStatus('pending', 'Autorisez la géolocalisation pour afficher votre position exacte…');
            }
            startWatch();
            if (window.CommandeTotaux && typeof window.CommandeTotaux.refresh === 'function') {
                window.CommandeTotaux.refresh();
            }
        } else {
            stopWatch();
            fillField('geo_lat', '');
            fillField('geo_lng', '');
            fillField('geo_precision', '');
            fillField('geo_source', '');
            fillField('geo_address', '');
            var mapContainer = qs('commande-geo-map');
            if (mapContainer) {
                mapContainer.style.display = 'none';
            }
            var geoBox = document.querySelector('.commande-geo-box');
            if (geoBox) {
                geoBox.style.display = 'none';
            }
            var statusEl = qs('commande-geo-status');
            if (statusEl) {
                statusEl.style.display = 'none';
                statusEl.innerHTML = '';
            }
            if (window.CommandeTotaux && typeof window.CommandeTotaux.refresh === 'function') {
                window.CommandeTotaux.refresh();
            }
        }
    }

    function validateBeforeSubmit() {
        if (getMode() !== 'livraison') {
            return true;
        }
        var lat = parseCoord(qs('geo_lat') && qs('geo_lat').value);
        var lng = parseCoord(qs('geo_lng') && qs('geo_lng').value);
        if (!coordsValid(lat, lng)) {
            setStatus('error', 'Votre position GPS est requise pour une livraison à domicile.');
            return false;
        }
        var selectZone = qs('zone_livraison_id');
        if (selectZone && !selectZone.disabled && !selectZone.value) {
            setStatus('error', 'Veuillez sélectionner votre zone de livraison.');
            return false;
        }
        return true;
    }

    function bindModeButtons() {
        var buttons = document.querySelectorAll('.cmd-mode-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setMode(btn.getAttribute('data-mode') || 'livraison');
            });
        });
    }

    function reset() {
        stopWatch();
        if (map) {
            try { map.remove(); } catch (e) {}
        }
        map = null;
        marker = null;
        accuracyCircle = null;
    }

    function init() {
        reset();
        bindModeButtons();
        setMode(getMode());

        var btnRefresh = qs('btn-commande-geo-refresh');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', function (e) {
                e.preventDefault();
                if (getMode() === 'livraison') {
                    startWatch();
                }
            });
        }

        var form = qs('form-commande');
        if (form) {
            var originalSubmitHandler = null;
            form.addEventListener('submit', function (e) {
                if (!validateBeforeSubmit()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            }, true);
        }
    }

    window.CommandeGeo = {
        init: init,
        reset: reset,
        validate: validateBeforeSubmit,
        getMode: getMode,
        stopWatch: stopWatch
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (qs('form-commande')) {
                init();
            }
        });
    } else if (qs('form-commande')) {
        init();
    }
})();
