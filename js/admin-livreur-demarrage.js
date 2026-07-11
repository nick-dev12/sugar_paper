/**
 * Démarrage livraison — capture GPS livreur, adresse client, carte + itinéraire.
 * Inspiré de poid_lourd/js/geo-location.js (Leaflet + Geolocation API).
 */
(function () {
    'use strict';

    var panel = null;
    var form = null;
    var map = null;
    var driverMarker = null;
    var clientMarker = null;
    var routeLayer = null;
    var watchId = null;
    var geocodeTimer = null;
    var activeBtn = null;

    var DEFAULT_CENTER = [14.6937, -17.4441];

    function qs(id) {
        return document.getElementById(id);
    }

    function parseCoord(v) {
        if (v === null || v === undefined || v === '') return null;
        var n = parseFloat(v);
        return isFinite(n) ? n : null;
    }

    function setStatus(state, message) {
        var el = qs('livreur-demarrage-status');
        if (!el) return;
        el.setAttribute('data-state', state);
        var icons = {
            pending: '<i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>',
            ok: '<i class="fas fa-location-crosshairs" aria-hidden="true"></i>',
            error: '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>',
            warn: '<i class="fas fa-info-circle" aria-hidden="true"></i>'
        };
        el.innerHTML = (icons[state] || '') + ' <span>' + message + '</span>';
    }

    function fillCoord(id, value) {
        var input = qs(id);
        if (input) input.value = value !== null && value !== undefined ? String(value) : '';
    }

    function readCoords() {
        return {
            driverLat: parseCoord(qs('livreur-driver-lat') && qs('livreur-driver-lat').value),
            driverLng: parseCoord(qs('livreur-driver-lng') && qs('livreur-driver-lng').value),
            clientLat: parseCoord(qs('livreur-delivery-lat') && qs('livreur-delivery-lat').value),
            clientLng: parseCoord(qs('livreur-delivery-lng') && qs('livreur-delivery-lng').value)
        };
    }

    function driverIcon() {
        return L.divIcon({
            className: 'livreur-map-pin livreur-map-pin--driver',
            html: '<i class="fas fa-motorcycle" aria-hidden="true"></i>',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
    }

    function clientIcon() {
        return L.divIcon({
            className: 'livreur-map-pin livreur-map-pin--client',
            html: '<i class="fas fa-house" aria-hidden="true"></i>',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
    }

    function ensureMap() {
        var container = qs('livreur-demarrage-map');
        if (!container || typeof window.L === 'undefined') return null;

        if (map) {
            setTimeout(function () { map.invalidateSize(); }, 120);
            return map;
        }

        map = L.map(container, { zoomControl: true }).setView(DEFAULT_CENTER, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        routeLayer = L.layerGroup().addTo(map);
        setTimeout(function () { map.invalidateSize(); }, 150);
        return map;
    }

    function updateDriverOnMap(lat, lng, accuracy) {
        var m = ensureMap();
        if (!m || lat === null || lng === null) return;

        if (!driverMarker) {
            driverMarker = L.marker([lat, lng], { icon: driverIcon() }).addTo(m);
            driverMarker.bindPopup('Départ — votre position');
        } else {
            driverMarker.setLatLng([lat, lng]);
        }

        fillCoord('livreur-driver-lat', lat.toFixed(8));
        fillCoord('livreur-driver-lng', lng.toFixed(8));
        if (accuracy !== null && accuracy !== undefined) {
            fillCoord('livreur-driver-precision', Math.round(accuracy));
        }

        var posEl = qs('livreur-driver-position');
        if (posEl) {
            posEl.value = lat.toFixed(6) + ', ' + lng.toFixed(6)
                + (accuracy ? ' (±' + Math.round(accuracy) + ' m)' : '');
        }

        refreshRoute();
    }

    function updateClientOnMap(lat, lng) {
        var m = ensureMap();
        if (!m || lat === null || lng === null) return;

        if (!clientMarker) {
            clientMarker = L.marker([lat, lng], { icon: clientIcon() }).addTo(m);
            clientMarker.bindPopup('Arrivée — client');
        } else {
            clientMarker.setLatLng([lat, lng]);
        }

        fillCoord('livreur-delivery-lat', lat.toFixed(8));
        fillCoord('livreur-delivery-lng', lng.toFixed(8));
        refreshRoute();
    }

    function fitMapToPoints() {
        if (!map) return;
        var c = readCoords();
        var bounds = [];
        if (c.driverLat !== null && c.driverLng !== null) bounds.push([c.driverLat, c.driverLng]);
        if (c.clientLat !== null && c.clientLng !== null) bounds.push([c.clientLat, c.clientLng]);
        if (bounds.length === 1) {
            map.setView(bounds[0], 15);
        } else if (bounds.length === 2) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 16 });
        }
    }

    function drawStraightRoute(from, to) {
        if (!routeLayer) return;
        routeLayer.clearLayers();
        L.polyline([from, to], {
            color: '#c26638',
            weight: 4,
            opacity: 0.65,
            dashArray: '8, 8'
        }).addTo(routeLayer);
    }

    function refreshRoute() {
        var c = readCoords();
        if (!routeLayer) return;
        routeLayer.clearLayers();

        if (c.driverLat === null || c.driverLng === null || c.clientLat === null || c.clientLng === null) {
            fitMapToPoints();
            return;
        }

        setStatus('pending', 'Calcul de l\'itinéraire (sans péage)…');

        var routePromise;
        if (window.LivreurRouteApi && typeof window.LivreurRouteApi.fetchRoute === 'function') {
            routePromise = window.LivreurRouteApi.fetchRoute(c.driverLat, c.driverLng, c.clientLat, c.clientLng);
        } else {
            routePromise = Promise.reject(new Error('route_api_unavailable'));
        }

        routePromise
            .then(function (data) {
                var coords = data.coords || [];
                if (coords.length < 2) {
                    throw new Error('route_empty');
                }
                L.polyline(coords, {
                    color: '#c26638',
                    weight: 5,
                    opacity: 0.85
                }).addTo(routeLayer);
                var km = ((data.distance_m || 0) / 1000).toFixed(1);
                var min = Math.round((data.duration_s || 0) / 60);
                setStatus('ok', 'Itinéraire sans péage — ' + km + ' km, ~' + min + ' min');
            })
            .catch(function () {
                drawStraightRoute([c.driverLat, c.driverLng], [c.clientLat, c.clientLng]);
                setStatus('warn', 'Itinéraire approximatif (ligne directe).');
            })
            .finally(function () {
                fitMapToPoints();
            });
    }

    function stopWatch() {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    function startWatch() {
        if (!navigator.geolocation) {
            setStatus('error', 'Géolocalisation non supportée par ce navigateur.');
            return;
        }

        stopWatch();
        setStatus('pending', 'Capture de votre position en cours… Autorisez l\'accès GPS.');

        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                updateDriverOnMap(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                if (!clientMarker) {
                    setStatus('ok', 'Position livreur capturée. Renseignez l\'adresse client.');
                }
            },
            function (err) {
                var msg = 'Impossible d\'obtenir votre position.';
                if (err.code === 1) msg = 'Accès à la géolocalisation refusé. Autorisez-le dans les paramètres.';
                else if (err.code === 2) msg = 'Position indisponible.';
                else if (err.code === 3) msg = 'Délai dépassé pour la géolocalisation.';
                setStatus('error', msg);
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    }

    function geocodeAddress(address) {
        var q = (address || '').trim();
        if (q.length < 4) return;

        clearTimeout(geocodeTimer);
        geocodeTimer = setTimeout(function () {
            setStatus('pending', 'Recherche de l\'adresse sur la carte…');
            var url = '/api/geo-geocode.php?q=' + encodeURIComponent(q);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (data && data.ok && data.lat !== null && data.lng !== null) {
                        updateClientOnMap(data.lat, data.lng);
                        setStatus('ok', 'Adresse localisée sur la carte.');
                    } else {
                        setStatus('warn', 'Adresse non trouvée. Précisez quartier et ville.');
                    }
                })
                .catch(function () {
                    setStatus('error', 'Erreur lors de la recherche d\'adresse.');
                });
        }, 600);
    }

    function openPanel(btn) {
        if (!panel || !form) return;

        activeBtn = btn;
        var livraisonType = btn.getAttribute('data-livraison-type') || 'commande';
        var commandeId = btn.getAttribute('data-commande-id') || '';
        var blId = btn.getAttribute('data-bl-id') || '';
        var numero = btn.getAttribute('data-numero') || '';
        var client = btn.getAttribute('data-client') || '';
        var adresse = btn.getAttribute('data-adresse') || '';
        var dLat = parseCoord(btn.getAttribute('data-delivery-lat'));
        var dLng = parseCoord(btn.getAttribute('data-delivery-lng'));

        var actionInput = qs('livreur-demarrage-action');
        var labelEl = qs('livreur-demarrage-label');
        if (livraisonType === 'facture') {
            if (actionInput) actionInput.value = 'commencer_livraison_facture';
            if (labelEl) labelEl.textContent = 'Facture';
            qs('livreur-demarrage-commande-id').value = '';
            qs('livreur-demarrage-bl-id').value = blId;
            qs('livreur-demarrage-numero').textContent = client || 'Client B2B';
        } else {
            if (actionInput) actionInput.value = 'commencer_livraison';
            if (labelEl) labelEl.textContent = 'Commande';
            qs('livreur-demarrage-commande-id').value = commandeId;
            if (qs('livreur-demarrage-bl-id')) qs('livreur-demarrage-bl-id').value = '';
            qs('livreur-demarrage-numero').textContent = numero;
        }
        qs('livreur-demarrage-adresse').value = adresse;

        fillCoord('livreur-driver-lat', '');
        fillCoord('livreur-driver-lng', '');
        fillCoord('livreur-driver-precision', '');
        fillCoord('livreur-delivery-lat', '');
        fillCoord('livreur-delivery-lng', '');
        if (qs('livreur-driver-position')) qs('livreur-driver-position').value = '';

        if (map) {
            map.remove();
            map = null;
            driverMarker = null;
            clientMarker = null;
            routeLayer = null;
        }

        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
        panel.classList.remove('livreur-demarrage-panel--anchored');
        panel.style.top = '';
        panel.style.left = '';
        document.body.classList.add('livreur-demarrage-open');

        ensureMap();

        if (dLat !== null && dLng !== null) {
            updateClientOnMap(dLat, dLng);
        } else if (adresse) {
            geocodeAddress(adresse);
        }

        startWatch();
    }

    function closePanel() {
        stopWatch();
        if (panel) {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('livreur-demarrage-open');
        activeBtn = null;
    }

    function onSubmit(e) {
        var c = readCoords();
        if (c.driverLat === null || c.driverLng === null) {
            e.preventDefault();
            setStatus('error', 'Attendez la capture GPS ou autorisez la géolocalisation.');
            return;
        }
        if (c.clientLat === null || c.clientLng === null) {
            e.preventDefault();
            setStatus('error', 'Localisez l\'adresse client sur la carte.');
            return;
        }
        var adresse = qs('livreur-demarrage-adresse');
        if (!adresse || adresse.value.trim() === '') {
            e.preventDefault();
            setStatus('error', 'L\'adresse de livraison est obligatoire.');
        }
    }

    function init() {
        panel = qs('livreur-demarrage-panel');
        form = qs('livreur-demarrage-form');
        if (!panel || !form) return;

        /* Déplacer le panneau sous <body> pour éviter les conflits de stacking/overflow */
        if (panel.parentElement && panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }

        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('livreur-demarrage-open');

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.livreur-btn-prendre');
            if (btn) {
                e.preventDefault();
                openPanel(btn);
                return;
            }
            if (e.target.closest('[data-livreur-demarrage-close]')) {
                e.preventDefault();
                closePanel();
            }
        });

        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput) {
            adresseInput.addEventListener('input', function () {
                geocodeAddress(adresseInput.value);
            });
        }

        form.addEventListener('submit', onSubmit);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel && !panel.hidden) closePanel();
        });

        window.addEventListener('resize', function () {
            if (map && panel && !panel.hidden) {
                setTimeout(function () { map.invalidateSize(); }, 120);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
