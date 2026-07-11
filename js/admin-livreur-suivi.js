/**
 * Carte suivi livraison — itinéraire sans péage + suivi GPS (Leaflet)
 */
(function () {
    'use strict';

    var cfg = window.LIVREUR_TRACKING_CONFIG;
    if (!cfg || (!cfg.commandeId && !cfg.blId)) {
        return;
    }

    var titleEl = document.getElementById('livreur-suivi-status-title');
    var statusEl = document.getElementById('livreur-suivi-status-sub');
    var mapEl = document.getElementById('livreur-tracking-map');
    var startBtn = document.getElementById('livreur-suivi-start-tracking');
    var stopBtn = document.getElementById('livreur-suivi-stop-tracking');
    var etaBlock = document.getElementById('livreur-suivi-eta');
    var etaRangeEl = document.getElementById('livreur-suivi-eta-range');
    var alertEl = document.getElementById('livreur-suivi-alert');
    var alertTitleEl = document.getElementById('livreur-suivi-alert-title');
    var alertMessageEl = document.getElementById('livreur-suivi-alert-message');
    var alertDetailsEl = document.getElementById('livreur-suivi-alert-details');
    var alertOkBtn = document.getElementById('livreur-suivi-alert-ok');
    var alertCloseBtn = document.getElementById('livreur-suivi-alert-close');
    var lastSocketError = '';
    if (!mapEl) {
        return;
    }

    function trackingError(title, message, details) {
        var err = new Error(message || title);
        err.trackingTitle = title || 'Erreur';
        err.trackingDetails = Array.isArray(details) ? details : (details ? [details] : []);
        return err;
    }

    function hideTrackingAlert() {
        if (!alertEl) return;
        alertEl.hidden = true;
    }

    function showTrackingAlert(payload) {
        if (!alertEl || !alertTitleEl || !alertMessageEl) return;
        var title = (payload && payload.title) ? payload.title : 'Erreur';
        var message = (payload && payload.message) ? payload.message : 'Une erreur est survenue.';
        var details = (payload && payload.details) ? payload.details : [];

        alertTitleEl.textContent = title;
        alertMessageEl.textContent = message;

        if (alertDetailsEl) {
            alertDetailsEl.innerHTML = '';
            if (details.length > 0) {
                details.forEach(function (line) {
                    if (!line) return;
                    var li = document.createElement('li');
                    li.textContent = line;
                    alertDetailsEl.appendChild(li);
                });
                alertDetailsEl.hidden = false;
            } else {
                alertDetailsEl.hidden = true;
            }
        }

        alertEl.hidden = false;
    }

    function showTrackingAlertFromError(err) {
        showTrackingAlert({
            title: (err && err.trackingTitle) ? err.trackingTitle : 'Démarrage impossible',
            message: (err && err.message) ? err.message : 'Une erreur inconnue est survenue.',
            details: (err && err.trackingDetails) ? err.trackingDetails : [],
        });
    }

    function getRealtimeFailureAlert() {
        if (!cfg.realtimeConfigured) {
            return {
                title: 'Temps réel non configuré',
                message: 'Le suivi en temps réel n\'est pas activé sur ce serveur.',
                details: [
                    'Copiez config/tracking.example.php vers config/tracking.php',
                    'Renseignez internal_secret et node_port',
                    'Démarrez le serveur Node.js (dossier tracking-server/)',
                ],
            };
        }
        if (typeof io === 'undefined') {
            return {
                title: 'Socket.io indisponible',
                message: 'La bibliothèque Socket.io n\'est pas chargée.',
                details: [
                    'Vérifiez config/tracking.php (realtimeConfigured)',
                    'Rechargez la page après configuration',
                ],
            };
        }
        var socketUrl = cfg.socketUrl || window.location.origin;
        var details = [
            'Vérifiez que le serveur Node.js est démarré',
            'URL testée : ' + socketUrl,
            'Chemin Socket.io : ' + (cfg.socketPath || '/socket.io'),
        ];
        if (lastSocketError.indexOf('unauthorized') !== -1 || lastSocketError.indexOf('watch_') !== -1) {
            details.unshift('Token de suivi refusé — rechargez la page et réessayez');
        } else if (lastSocketError) {
            details.unshift('Détail : ' + lastSocketError);
        }
        return {
            title: 'Connexion temps réel échouée',
            message: 'Impossible de se connecter au serveur Socket.io.',
            details: details,
        };
    }

    function bindTrackingAlert() {
        if (!alertEl) return;
        if (alertOkBtn) {
            alertOkBtn.addEventListener('click', hideTrackingAlert);
        }
        if (alertCloseBtn) {
            alertCloseBtn.addEventListener('click', hideTrackingAlert);
        }
        alertEl.querySelectorAll('[data-livreur-alert-close]').forEach(function (node) {
            node.addEventListener('click', hideTrackingAlert);
        });
    }

    var STATUS_TITLES = {
        pending: 'En attente',
        live: 'Suivi actif',
        off: 'Suivi inactif',
        error: 'Erreur',
        route: 'Itinéraire prêt'
    };

    var map = L.map(mapEl, {
        zoomControl: false,
        attributionControl: true,
        rotate: true,
        touchRotate: false,
        shiftKeyRotate: false,
        bearing: 0,
        maxZoom: 19
    }).setView(cfg.defaultCenter, cfg.defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    var driverMarker = null;
    var clientMarker = null;
    var routeLayer = null;
    var watchId = null;
    var gpsStreaming = false;
    var deliveryActive = false;
    var realtimeConnected = false;
    var socketClient = null;
    var lastPostAt = 0;
    var lastRouteRecalcAt = 0;
    var driverHeadingDeg = 0;
    var mapBearingDeg = 0;
    var compassHeadingDeg = null;
    var gpsCourseHeadingDeg = null;
    var lastGpsSpeed = null;
    var lastDriverLat = null;
    var lastDriverLng = null;
    var deviceOrientationBound = false;
    var autoRecenterTimer = null;
    var suppressMapInteractionEvents = false;
    var navigationMode = false;
    var bearingAnimFrame = null;
    var NAV_ZOOM = cfg.navZoom || 19;
    var AUTO_RECENTER_MS = cfg.navRecenterDelayMs || 6000;
    var MIN_HEADING_DELTA_NAV = 4;
    var MIN_MOVE_FOR_ANIMATED_FOLLOW = 0.00005;
    var MAP_FOLLOW_MIN_INTERVAL_MS = 900;
    var positionPollTimer = null;
    var observerFollowActive = false;
    var observerStopped = false;
    var lastMapFollowAt = 0;
    var bearingTargetDeg = 0;

    function isObserverMode() {
        return !!(cfg.watchOnly || cfg.publicMode);
    }

    function driverMarkerLabel() {
        return isObserverMode() ? 'Livreur' : 'Vous';
    }

    function stopPositionPolling() {
        if (positionPollTimer) {
            clearInterval(positionPollTimer);
            positionPollTimer = null;
        }
    }

    function handleTrackingEnded() {
        if (!cfg.trackingActive && !observerFollowActive) {
            return;
        }
        cfg.trackingActive = false;
        observerFollowActive = false;
        observerStopped = true;
        stopPositionPolling();
        disconnectRealtime();
        clearAutoRecenterTimer();
        setNavigationMode(false);
        if (titleEl) {
            titleEl.textContent = 'Livraison terminée';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--off';
        }
        setStatus('Suivi en temps réel arrêté', 'off');
    }

    function shouldUseCompassHeading() {
        if (isObserverMode() || !cfg.canManage || !deliveryActive || !navigationMode) {
            return false;
        }
        var moving = lastGpsSpeed != null && isFinite(lastGpsSpeed) && lastGpsSpeed >= 0.8;
        return !moving;
    }

    function applyRemoteDriverPosition(pos) {
        if (isObserverMode() && observerStopped) {
            return;
        }
        if (!pos || pos.latitude == null || pos.longitude == null) {
            return;
        }
        var lat = parseFloat(pos.latitude);
        var lng = parseFloat(pos.longitude);
        if (!isFinite(lat) || !isFinite(lng)) {
            return;
        }
        if (isObserverMode()) {
            cfg.trackingActive = true;
        }
        var coords = {
            heading: pos.heading != null ? parseFloat(pos.heading) : null,
            speed: pos.speed != null ? parseFloat(pos.speed) : null
        };
        var wasFirst = !driverMarker;
        updateDriverMarker(lat, lng, coords);
        if (!isObserverMode()) {
            return;
        }
        observerFollowActive = true;
        if (!navigationMode) {
            enableNavigationMode();
        }
        var client = getClientCoords();
        if (client.lat === null || client.lng === null) {
            return;
        }
        var now = Date.now();
        if (wasFirst || now - lastRouteRecalcAt > 45000) {
            lastRouteRecalcAt = now;
            drawRoute(lat, lng, client.lat, client.lng, true);
        }
    }
    var NAV_CHEVRON_SVG = '<svg viewBox="0 0 56 56" aria-hidden="true" focusable="false">' +
        '<defs>' +
        '<linearGradient id="livreurNavGrad" x1="0.35" y1="0" x2="0.65" y2="1">' +
        '<stop offset="0%" stop-color="#FFE082"/>' +
        '<stop offset="42%" stop-color="#FBBC04"/>' +
        '<stop offset="100%" stop-color="#F29900"/>' +
        '</linearGradient>' +
        '</defs>' +
        '<path d="M28 5 C28 5 48 43 45.5 45.5 C43 48 28 39 28 39 C28 39 13 48 10.5 45.5 C8 43 28 5 28 5 Z" fill="url(#livreurNavGrad)" stroke="#E37400" stroke-width="1.4" stroke-linejoin="round"/>' +
        '<ellipse cx="28" cy="41" rx="7" ry="2.8" fill="rgba(0,0,0,0.14)"/>' +
        '</svg>';
    var DRIVER_ARROW_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
        '<path d="M12 2.5c.45 0 .86.26 1.05.67l7.2 15.6a1.1 1.1 0 0 1-1 1.58h-4.05l-1.2 3.35a1.1 1.1 0 0 1-2.05 0l-1.2-3.35H5.75a1.1 1.1 0 0 1-1-1.58l7.2-15.6c.19-.41.6-.67 1.05-.67z" fill="currentColor"/>' +
        '</svg>';

    function durationToRangeMinutes(durationSeconds) {
        var baseMin = Math.max(1, durationSeconds / 60);
        var minMin = Math.max(5, Math.floor((baseMin * 0.82) / 5) * 5);
        var maxMin = Math.max(minMin + 10, Math.ceil((baseMin * 1.28) / 5) * 5);
        if (maxMin - minMin < 10) {
            maxMin = minMin + 10;
        }
        if (maxMin - minMin > 25) {
            maxMin = minMin + 25;
        }
        return { min: minMin, max: maxMin };
    }

    function formatEtaRange(minMin, maxMin) {
        if (minMin === maxMin) {
            return 'environ ' + minMin + ' min';
        }
        return 'de ' + minMin + ' à ' + maxMin + ' min';
    }

    function setEtaFromDuration(durationSeconds) {
        if (cfg.regarderMode || cfg.publicMode) {
            return;
        }
        if (!etaBlock || !etaRangeEl || !durationSeconds || durationSeconds <= 0) {
            return;
        }
        var range = durationToRangeMinutes(durationSeconds);
        etaRangeEl.textContent = formatEtaRange(range.min, range.max);
        etaBlock.hidden = false;
    }

    function hideEta() {
        if (etaBlock) etaBlock.hidden = true;
        if (etaRangeEl) etaRangeEl.textContent = '—';
    }

    function estimateStraightDurationSeconds(fromLat, fromLng, toLat, toLng) {
        var R = 6371000;
        var dLat = (toLat - fromLat) * Math.PI / 180;
        var dLng = (toLng - fromLng) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(fromLat * Math.PI / 180) * Math.cos(toLat * Math.PI / 180)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var distM = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        var speedMs = 25 * 1000 / 3600;
        return Math.max(180, distM / speedMs * 1.35);
    }

    function parseCoord(v) {
        if (v === null || v === undefined || v === '') return null;
        var n = parseFloat(v);
        return isFinite(n) ? n : null;
    }

    function setStatus(text, kind) {
        kind = kind || 'pending';
        if (titleEl) {
            titleEl.textContent = STATUS_TITLES[kind] || 'Suivi';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--' + kind;
        }
        if (statusEl && !cfg.regarderMode && !cfg.publicMode) {
            statusEl.textContent = text;
            statusEl.className = 'livreur-suivi-status livreur-suivi-status--' + kind;
        }
    }

    function normalizeHeading(deg) {
        return ((deg % 360) + 360) % 360;
    }

    function shortestAngleDiff(from, to) {
        return ((to - from + 540) % 360) - 180;
    }

    function mapHasRotation() {
        return typeof map.setBearing === 'function';
    }

    function setNavigationMode(active) {
        navigationMode = !!active;
        if (mapEl) {
            mapEl.classList.toggle('livreur-tracking-map--nav-mode', navigationMode);
        }
        if (!navigationMode && mapHasRotation()) {
            cancelBearingAnimation();
            mapBearingDeg = 0;
            map.setBearing(0);
            resetDriverArrowRotation();
        }
    }

    function cancelBearingAnimation() {
        if (bearingAnimFrame) {
            cancelAnimationFrame(bearingAnimFrame);
            bearingAnimFrame = null;
        }
    }

    function resetDriverArrowRotation() {
        var arrow = getDriverArrowElement();
        if (arrow) {
            arrow.style.transform = 'rotate(0deg)';
        }
    }

    function smoothSetMapBearing(targetHeading) {
        if (!mapHasRotation() || !navigationMode) {
            return;
        }
        bearingTargetDeg = normalizeHeading(targetHeading);
        driverHeadingDeg = bearingTargetDeg;
        if (bearingAnimFrame) {
            return;
        }

        function step() {
            var diff = shortestAngleDiff(mapBearingDeg, bearingTargetDeg);
            if (Math.abs(diff) < 0.6) {
                mapBearingDeg = bearingTargetDeg;
                map.setBearing(mapBearingDeg);
                bearingAnimFrame = null;
                return;
            }
            mapBearingDeg = normalizeHeading(mapBearingDeg + diff * 0.11);
            map.setBearing(mapBearingDeg);
            bearingAnimFrame = requestAnimationFrame(step);
        }

        bearingAnimFrame = requestAnimationFrame(step);
    }

    function getNavigationPadding() {
        var h = mapEl ? mapEl.clientHeight : 480;
        var offsetY = Math.max(80, Math.round(h * 0.22));
        return {
            topLeft: L.point(0, 0),
            bottomRight: L.point(0, offsetY * 2)
        };
    }

    function followDriverNavigation(animate, movedDistHint) {
        if (!driverMarker || !navigationMode) {
            return;
        }
        var driverLatLng = driverMarker.getLatLng();
        var now = Date.now();
        var movedDist = typeof movedDistHint === 'number' ? movedDistHint : 0;
        if (!movedDist && lastDriverLat != null && lastDriverLng != null) {
            movedDist = Math.hypot(driverLatLng.lat - lastDriverLat, driverLatLng.lng - lastDriverLng);
        }
        var significantMove = movedDist >= MIN_MOVE_FOR_ANIMATED_FOLLOW;
        if (!significantMove && now - lastMapFollowAt < MAP_FOLLOW_MIN_INTERVAL_MS) {
            return;
        }
        lastMapFollowAt = now;
        var shouldAnimate = animate === true && significantMove;
        var zoom = map.getZoom();
        if (zoom < NAV_ZOOM - 2) {
            zoom = NAV_ZOOM;
        }
        suppressMapInteractionEvents = true;
        var pad = getNavigationPadding();
        map.setView(driverLatLng, zoom, {
            animate: shouldAnimate,
            paddingTopLeft: pad.topLeft,
            paddingBottomRight: pad.bottomRight
        });
        setTimeout(function () { suppressMapInteractionEvents = false; }, shouldAnimate ? 400 : 50);
    }

    function makeDriverArrowIcon() {
        if (navigationMode) {
            return L.divIcon({
                className: 'livreur-marker-wrap livreur-marker-wrap--driver livreur-marker-wrap--nav',
                html: '<div class="livreur-marker-icon livreur-marker-icon--driver">' +
                    '<div class="livreur-nav-arrow">' + NAV_CHEVRON_SVG + '</div></div>',
                iconSize: [52, 52],
                iconAnchor: [26, 30],
            });
        }
        return L.divIcon({
            className: 'livreur-marker-wrap livreur-marker-wrap--driver',
            html: '<div class="livreur-marker-icon livreur-marker-icon--driver">' +
                '<div class="livreur-marker-arrow" style="transform:rotate(' + driverHeadingDeg + 'deg)">' +
                DRIVER_ARROW_SVG +
                '</div></div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
        });
    }

    function makeClientMarkerIcon() {
        return L.divIcon({
            className: 'livreur-marker-wrap livreur-marker-wrap--client',
            html: '<div class="livreur-marker-icon livreur-marker-icon--client"><i class="fas fa-location-dot"></i></div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
        });
    }

    function getDriverArrowElement() {
        if (!driverMarker) return null;
        var el = driverMarker.getElement();
        return el ? el.querySelector('.livreur-marker-arrow') : null;
    }

    function applyDriverHeading(deg) {
        if (deg === null || deg === undefined || !isFinite(deg)) return;
        var normalized = normalizeHeading(deg);
        var delta = Math.abs(shortestAngleDiff(driverHeadingDeg, normalized));
        if (delta < MIN_HEADING_DELTA_NAV && navigationMode) return;
        driverHeadingDeg = normalized;

        if (navigationMode && mapHasRotation()) {
            resetDriverArrowRotation();
            smoothSetMapBearing(normalized);
            return;
        }

        var arrow = getDriverArrowElement();
        if (arrow) {
            arrow.style.transform = 'rotate(' + normalized + 'deg)';
        }
    }

    function bearingFromMovement(lat, lng) {
        if (lastDriverLat == null || lastDriverLng == null) return null;
        var dLat = lat - lastDriverLat;
        var dLng = lng - lastDriverLng;
        if ((dLat * dLat + dLng * dLng) < 0.000000008) return null;
        var dLon = dLng * Math.PI / 180;
        var lat1 = lastDriverLat * Math.PI / 180;
        var lat2 = lat * Math.PI / 180;
        var y = Math.sin(dLon) * Math.cos(lat2);
        var x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
        return normalizeHeading(Math.atan2(y, x) * 180 / Math.PI);
    }

    function resolveHeadingFromPosition(coords, lat, lng) {
        if (coords && coords.speed != null && isFinite(coords.speed)) {
            lastGpsSpeed = coords.speed;
        }
        var moving = lastGpsSpeed != null && isFinite(lastGpsSpeed) && lastGpsSpeed >= 0.8;
        var heading = coords && coords.heading != null ? coords.heading : null;

        if (isObserverMode()) {
            if (heading != null && isFinite(heading) && heading >= 0) {
                applyDriverHeading(heading);
                return;
            }
            if (lat != null && lng != null) {
                var remoteMoveHeading = bearingFromMovement(lat, lng);
                if (remoteMoveHeading != null) {
                    applyDriverHeading(remoteMoveHeading);
                }
            }
            return;
        }

        if (moving && heading != null && isFinite(heading) && heading >= 0) {
            gpsCourseHeadingDeg = heading;
            applyDriverHeading(heading);
            return;
        }
        if (!moving) {
            gpsCourseHeadingDeg = null;
        }
        if (lat != null && lng != null && moving) {
            var moveHeading = bearingFromMovement(lat, lng);
            if (moveHeading != null) {
                applyDriverHeading(moveHeading);
                return;
            }
        }
        if (shouldUseCompassHeading() && compassHeadingDeg != null) {
            applyDriverHeading(compassHeadingDeg);
        } else if (heading != null && isFinite(heading) && heading >= 0) {
            applyDriverHeading(heading);
        }
    }

    function onDeviceOrientation(event) {
        if (!shouldUseCompassHeading()) {
            return;
        }
        var heading = null;
        if (event.webkitCompassHeading != null && isFinite(event.webkitCompassHeading)) {
            heading = event.webkitCompassHeading;
        } else if (event.alpha != null && isFinite(event.alpha)) {
            heading = (360 - event.alpha) % 360;
        }
        if (heading == null) return;
        compassHeadingDeg = heading;
        applyDriverHeading(heading);
    }

    function bindDeviceOrientation() {
        if (deviceOrientationBound) return;
        window.addEventListener('deviceorientationabsolute', onDeviceOrientation, true);
        window.addEventListener('deviceorientation', onDeviceOrientation, true);
        deviceOrientationBound = true;
    }

    function unbindDeviceOrientation() {
        if (!deviceOrientationBound) return;
        window.removeEventListener('deviceorientationabsolute', onDeviceOrientation, true);
        window.removeEventListener('deviceorientation', onDeviceOrientation, true);
        deviceOrientationBound = false;
    }

    function requestDeviceOrientationAccess() {
        if (typeof DeviceOrientationEvent === 'undefined') {
            return Promise.resolve(false);
        }
        bindDeviceOrientation();
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            return DeviceOrientationEvent.requestPermission()
                .then(function (state) { return state === 'granted'; })
                .catch(function () { return false; });
        }
        return Promise.resolve(true);
    }

    function focusMapOnDriver(animate) {
        if (!driverMarker) return;
        if (navigationMode) {
            followDriverNavigation(animate);
            return;
        }
        suppressMapInteractionEvents = true;
        var driverLatLng = driverMarker.getLatLng();
        if (clientMarker) {
            var group = L.featureGroup([driverMarker, clientMarker]);
            map.fitBounds(group.getBounds().pad(0.18), {
                animate: animate !== false,
                maxZoom: 16,
                padding: [40, 40]
            });
        } else {
            map.setView(driverLatLng, 16, { animate: animate !== false });
        }
        setTimeout(function () { suppressMapInteractionEvents = false; }, 350);
    }

    function clearAutoRecenterTimer() {
        if (autoRecenterTimer) {
            clearTimeout(autoRecenterTimer);
            autoRecenterTimer = null;
        }
    }

    function scheduleAutoRecenter() {
        if (!driverMarker || !navigationMode) return;
        clearAutoRecenterTimer();
        autoRecenterTimer = setTimeout(function () {
            autoRecenterTimer = null;
            restoreMapToDriver(true);
        }, AUTO_RECENTER_MS);
    }

    function onUserMapInteraction() {
        if (suppressMapInteractionEvents || !navigationMode) return;
        scheduleAutoRecenter();
    }

    function refreshDriverMarkerIcon() {
        if (!driverMarker) return;
        var latlng = driverMarker.getLatLng();
        driverMarker.setIcon(makeDriverArrowIcon());
        driverMarker.setLatLng(latlng);
    }

    function enableNavigationMode() {
        if (navigationMode) {
            followDriverNavigation(true);
            return;
        }
        setNavigationMode(true);
        refreshDriverMarkerIcon();
        if (driverHeadingDeg > 0 && mapHasRotation()) {
            smoothSetMapBearing(driverHeadingDeg);
        }
        followDriverNavigation(false);
    }

    function updateDriverMarker(lat, lng, coords, skipFollow) {
        var prevLat = lastDriverLat;
        var prevLng = lastDriverLng;
        if (driverMarker) {
            driverMarker.setLatLng([lat, lng]);
        } else {
            driverMarker = L.marker([lat, lng], {
                icon: makeDriverArrowIcon(),
            }).addTo(map).bindPopup(driverMarkerLabel());
        }
        resolveHeadingFromPosition(coords || null, lat, lng);
        lastDriverLat = lat;
        lastDriverLng = lng;
        if (navigationMode && !skipFollow) {
            var movedDist = 0;
            if (prevLat != null && prevLng != null) {
                movedDist = Math.hypot(lat - prevLat, lng - prevLng);
            }
            followDriverNavigation(movedDist >= MIN_MOVE_FOR_ANIMATED_FOLLOW, movedDist);
        }
    }

    function setClientMarker(lat, lng) {
        if (clientMarker) {
            clientMarker.setLatLng([lat, lng]);
            return;
        }
        clientMarker = L.marker([lat, lng], {
            icon: makeClientMarkerIcon(),
        }).addTo(map).bindPopup('Client');
    }

    function ensureRouteLayer() {
        if (!routeLayer) {
            routeLayer = L.layerGroup().addTo(map);
        }
        return routeLayer;
    }

    function drawStraightRoute(from, to) {
        var layer = ensureRouteLayer();
        layer.clearLayers();
        L.polyline([from, to], {
            color: '#c26638',
            weight: 4,
            opacity: 0.65,
            dashArray: '8, 8'
        }).addTo(layer);
    }

    function fetchRouteData(driverLat, driverLng, clientLat, clientLng) {
        if (window.LivreurRouteApi && typeof window.LivreurRouteApi.fetchRoute === 'function') {
            return window.LivreurRouteApi.fetchRoute(driverLat, driverLng, clientLat, clientLng);
        }
        return Promise.reject(new Error('route_api_unavailable'));
    }

    function drawRoute(driverLat, driverLng, clientLat, clientLng, silent) {
        if (driverLat === null || driverLng === null || clientLat === null || clientLng === null) {
            return Promise.resolve(false);
        }

        var layer = ensureRouteLayer();
        layer.clearLayers();

        if (!silent) {
            setStatus('Calcul de l\'itinéraire (sans péage)…', 'pending');
        }

        return fetchRouteData(driverLat, driverLng, clientLat, clientLng)
            .then(function (data) {
                var coords = data.coords || [];
                if (coords.length < 2) {
                    throw new Error('route_empty');
                }
                L.polyline(coords, {
                    color: '#c26638',
                    weight: 5,
                    opacity: 0.88
                }).addTo(layer);
                var km = ((data.distance_m || 0) / 1000).toFixed(1);
                var durationSec = data.duration_s || 0;
                setEtaFromDuration(durationSec);
                var range = durationToRangeMinutes(durationSec);
                if (!silent) {
                    setStatus('Itinéraire sans péage — ' + km + ' km, ' + formatEtaRange(range.min, range.max), 'route');
                }
                return true;
            })
            .catch(function () {
                drawStraightRoute([driverLat, driverLng], [clientLat, clientLng]);
                var approxSec = estimateStraightDurationSeconds(driverLat, driverLng, clientLat, clientLng);
                setEtaFromDuration(approxSec);
                if (!silent) {
                    setStatus('Itinéraire approximatif (hors ligne)', 'route');
                }
                return false;
            });
    }

    function fitMapBounds() {
        if (driverMarker) {
            focusMapOnDriver(false);
        } else if (clientMarker) {
            suppressMapInteractionEvents = true;
            map.setView(clientMarker.getLatLng(), 15);
            setTimeout(function () { suppressMapInteractionEvents = false; }, 350);
        }
    }

    function getClientCoords() {
        return {
            lat: parseCoord(cfg.deliveryLat),
            lng: parseCoord(cfg.deliveryLng)
        };
    }

    function applyDeliveryFromPayload(payload) {
        var c = payload && payload.commande ? payload.commande : null;
        if (!c) return;
        if (c.tracking_active != null) {
            cfg.trackingActive = parseInt(c.tracking_active, 10) === 1 || c.tracking_active === true;
        }
        if (c.delivery_latitude != null && c.delivery_longitude != null) {
            cfg.deliveryLat = c.delivery_latitude;
            cfg.deliveryLng = c.delivery_longitude;
            setClientMarker(c.delivery_latitude, c.delivery_longitude);
        }
    }

    function refreshFromGeolocation() {
        return new Promise(function (resolve) {
            if (!navigator.geolocation) {
                resolve(null);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        heading: pos.coords.heading,
                        speed: pos.coords.speed
                    });
                },
                function () { resolve(null); },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }

    function setupItinerary(payload) {
        applyDeliveryFromPayload(payload);

        var client = getClientCoords();
        if (client.lat !== null && client.lng !== null) {
            setClientMarker(client.lat, client.lng);
        }

        var driverFromServer = payload && payload.last_position ? payload.last_position : null;
        var driverLat = driverFromServer ? parseCoord(driverFromServer.latitude) : null;
        var driverLng = driverFromServer ? parseCoord(driverFromServer.longitude) : null;

        if (driverLat !== null && driverLng !== null) {
            if (isObserverMode()) {
                applyRemoteDriverPosition(driverFromServer);
            } else {
                updateDriverMarker(driverLat, driverLng, driverFromServer ? {
                    heading: driverFromServer.heading != null ? parseFloat(driverFromServer.heading) : null,
                    speed: driverFromServer.speed != null ? parseFloat(driverFromServer.speed) : null
                } : null);
            }
        }

        var routePromise = Promise.resolve();
        if (client.lat !== null && client.lng !== null) {
            if (driverLat !== null && driverLng !== null && !isObserverMode()) {
                routePromise = drawRoute(driverLat, driverLng, client.lat, client.lng);
            } else if (driverLat !== null && driverLng !== null && isObserverMode()) {
                routePromise = Promise.resolve();
            } else if (isObserverMode()) {
                setStatus('En attente de la position du livreur…', 'pending');
            } else {
                routePromise = refreshFromGeolocation().then(function (pos) {
                    if (!pos) {
                        setStatus('Autorisez le GPS pour afficher l\'itinéraire', 'off');
                        fitMapBounds();
                        return false;
                    }
                    updateDriverMarker(pos.lat, pos.lng, {
                        heading: pos.heading,
                        speed: pos.speed
                    });
                    return drawRoute(pos.lat, pos.lng, client.lat, client.lng);
                });
            }
        } else {
            setStatus('Adresse client non géolocalisée', 'error');
        }

        return routePromise.then(function () {
            fitMapBounds();
        });
    }

    function webApiPayload(extra) {
        var body = { action: extra.action };
        if (cfg.commandeId) body.commande_id = cfg.commandeId;
        if (cfg.blId) body.bl_id = cfg.blId;
        if (extra.latitude != null) body.latitude = extra.latitude;
        if (extra.longitude != null) body.longitude = extra.longitude;
        if (extra.accuracy != null) body.accuracy = extra.accuracy;
        return body;
    }

    function callWebApi(extra) {
        return fetch(cfg.webApiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(webApiPayload(extra || {}))
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    var msg = data.message || 'Erreur serveur';
                    var details = [];
                    if (res.status === 401) {
                        details = ['Votre session a peut-être expiré', 'Reconnectez-vous à l\'administration'];
                    } else if (res.status === 403) {
                        details = ['Vous n\'avez pas les droits livreur GPS'];
                    }
                    throw trackingError('Erreur serveur', msg, details);
                }
                return data;
            });
        }).catch(function (err) {
            if (err && err.trackingTitle) {
                throw err;
            }
            throw trackingError(
                'Erreur réseau',
                'Impossible de contacter le serveur de suivi.',
                ['Vérifiez votre connexion', 'Réessayez dans quelques secondes']
            );
        });
    }

    function setDeliveryActive(active) {
        deliveryActive = !!active;
        gpsStreaming = deliveryActive;
        if (deliveryActive) {
            enableNavigationMode();
        } else {
            setNavigationMode(false);
        }
    }

    function updateTrackingButtons() {
        if (startBtn) startBtn.hidden = realtimeConnected;
        if (stopBtn) stopBtn.hidden = !realtimeConnected;
    }

    function setTrackingInactive(subMessage) {
        disconnectRealtime();
        stopWatch();
        clearAutoRecenterTimer();
        setNavigationMode(false);
        deliveryActive = false;
        gpsStreaming = false;
        updateTrackingButtons();
        if (titleEl) {
            titleEl.textContent = STATUS_TITLES.off;
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--off';
        }
        if (statusEl) {
            statusEl.textContent = subMessage || 'Suivi en temps réel inactif';
            statusEl.className = 'livreur-suivi-status livreur-suivi-status--off';
        }
    }

    function setDeliveryStatusRealtime(realtimeOn) {
        realtimeConnected = !!realtimeOn;
        updateTrackingButtons();
        if (realtimeOn) {
            setStatus('Suivi en temps réel actif', 'live');
            return;
        }
        if (!deliveryActive) {
            return;
        }
        if (titleEl) {
            titleEl.textContent = 'Livraison en cours';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--off';
        }
        if (statusEl) {
            if (!cfg.realtimeConfigured) {
                statusEl.textContent = 'Suivi en temps réel inactif — configurez Socket.io et le serveur Node.js';
            } else {
                statusEl.textContent = 'Suivi en temps réel inactif — serveur Socket.io injoignable';
            }
            statusEl.className = 'livreur-suivi-status livreur-suivi-status--off';
        }
    }

    function fetchWatchToken() {
        if (cfg.embeddedWatchToken) {
            return Promise.resolve(cfg.embeddedWatchToken);
        }
        if (!cfg.watchTokenUrl) {
            return Promise.reject(trackingError(
                'Token de suivi',
                'Configuration de suivi incomplète.',
                ['Rechargez la page']
            ));
        }
        return fetch(cfg.watchTokenUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                return res.json().then(function (payload) {
                    if (!res.ok || !payload.success || !payload.watch_token) {
                        throw trackingError(
                            'Token de suivi',
                            payload.message || 'Impossible de générer le token de suivi.',
                            ['Vérifiez que la commande ou la facture existe', 'Reconnectez-vous si la session a expiré']
                        );
                    }
                    return payload.watch_token;
                });
            })
            .catch(function (err) {
                if (err && err.trackingTitle) {
                    throw err;
                }
                throw trackingError(
                    'Token de suivi',
                    'Erreur réseau lors de la récupération du token.',
                    ['Vérifiez votre connexion internet', 'Réessayez dans quelques secondes']
                );
            });
    }

    function disconnectRealtime() {
        if (socketClient) {
            socketClient.disconnect();
            socketClient = null;
        }
        realtimeConnected = false;
    }

    function tryConnectRealtime(watchToken, timeoutMs) {
        if (!cfg.realtimeConfigured || typeof io === 'undefined') {
            return Promise.resolve(false);
        }

        return new Promise(function (resolve) {
            var settled = false;
            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                lastSocketError = 'Délai de connexion dépassé (8 s)';
                disconnectRealtime();
                resolve(false);
            }, timeoutMs || 15000);

            disconnectRealtime();
            var socketUrl = cfg.socketUrl || window.location.origin;
            socketClient = io(socketUrl, {
                path: cfg.socketPath || '/socket.io',
                /* Webuzo/Nginx : polling seul (websocket upgrade échoue souvent) */
                transports: ['polling'],
                upgrade: false,
                reconnection: true,
                timeout: 15000,
                auth: {
                    role: 'watch',
                    token: watchToken,
                    commande_id: cfg.commandeId || 0,
                    bl_id: cfg.blId || 0,
                },
            });

            socketClient.on('connect', function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                lastSocketError = '';
                resolve(true);
            });

            socketClient.on('connect_error', function (err) {
                /* Ne pas couper ici : Socket.io réessaie (polling si websocket échoue) */
                lastSocketError = (err && err.message) ? err.message : 'Connexion refusée';
            });

            socketClient.on('position:update', function (data) {
                if (!data || data.latitude == null || data.longitude == null) return;
                applyRemoteDriverPosition(data);
            });

            socketClient.on('watch:ready', function (data) {
                if (!data) return;
                if (data.tracking_active === false || data.tracking_active === 0) {
                    if (cfg.trackingActive) {
                        handleTrackingEnded();
                    }
                    return;
                }
                if (data.tracking_active) {
                    cfg.trackingActive = true;
                }
                if (data.last_position) {
                    applyRemoteDriverPosition(data.last_position);
                }
            });

            socketClient.on('disconnect', function () {
                if (deliveryActive && realtimeConnected) {
                    realtimeConnected = false;
                    setDeliveryStatusRealtime(false);
                }
            });
        });
    }

    function waitForFirstPosition(timeoutMs) {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(trackingError(
                    'GPS non supporté',
                    'Votre navigateur ne prend pas en charge la géolocalisation.',
                    ['Utilisez Chrome, Firefox ou Safari récent', 'Sur mobile, activez le GPS']
                ));
                return;
            }
            if (!window.isSecureContext) {
                reject(trackingError(
                    'Connexion non sécurisée',
                    'Le GPS nécessite une connexion HTTPS ou localhost.',
                    ['Ouvrez le site en https://', 'En local, utilisez http://localhost']
                ));
                return;
            }
            var settled = false;
            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                reject(trackingError(
                    'GPS trop lent',
                    'Délai dépassé en attente de votre position.',
                    ['Activez le GPS de l\'appareil', 'Autorisez la géolocalisation', 'Réessayez près d\'une fenêtre']
                ));
            }, timeoutMs || 15000);

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timer);
                    resolve(pos);
                },
                function (geoErr) {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timer);
                    var title = 'Position GPS';
                    var message = 'Impossible d\'obtenir votre position.';
                    var details = [];
                    if (geoErr && geoErr.code === 1) {
                        title = 'GPS refusé';
                        message = 'Vous avez refusé l\'accès à la géolocalisation.';
                        details = [
                            'Cliquez sur l\'icône cadenas ou GPS dans la barre d\'adresse',
                            'Autorisez la localisation pour ce site',
                            'Rechargez la page puis réessayez',
                        ];
                    } else if (geoErr && geoErr.code === 2) {
                        title = 'GPS indisponible';
                        message = 'La position n\'a pas pu être déterminée.';
                        details = ['Activez le GPS / localisation sur l\'appareil', 'Sortez en extérieur si possible'];
                    } else if (geoErr && geoErr.code === 3) {
                        title = 'GPS en timeout';
                        message = 'Le signal GPS met trop de temps à répondre.';
                        details = ['Patientez quelques secondes', 'Vérifiez que le GPS est activé', 'Réessayez'];
                    }
                    reject(trackingError(title, message, details));
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        });
    }

    function rollbackTrackingStart() {
        return callWebApi({ action: 'stop' }).catch(function () { /* silencieux */ });
    }

    function emitPositionToSocket(lat, lng, coords) {
        if (!socketClient || !socketClient.connected || !cfg.canManage) {
            return;
        }
        var payload = {
            latitude: lat,
            longitude: lng,
            accuracy: coords && coords.accuracy != null ? coords.accuracy : null,
            speed: coords && coords.speed != null ? coords.speed : null,
            heading: coords && coords.heading != null ? coords.heading : null
        };
        if (cfg.blId) {
            payload.bl_id = cfg.blId;
        } else if (cfg.commandeId) {
            payload.commande_id = cfg.commandeId;
        }
        socketClient.emit('livreur:position', payload);
    }

    function postPosition(lat, lng, accuracy, coords) {
        var now = Date.now();
        if (now - lastPostAt < 4000) {
            emitPositionToSocket(lat, lng, coords || { accuracy: accuracy });
            return;
        }
        lastPostAt = now;
        emitPositionToSocket(lat, lng, coords || { accuracy: accuracy });
        callWebApi({
            action: 'position',
            latitude: lat,
            longitude: lng,
            accuracy: accuracy
        }).catch(function () { /* silencieux */ });
    }

    function stopWatch() {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    function startWatchStream() {
        if (!navigator.geolocation) {
            return false;
        }
        stopWatch();
        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                updateDriverMarker(lat, lng, pos.coords);
                postPosition(lat, lng, pos.coords.accuracy, pos.coords);
                var now = Date.now();
                if (now - lastRouteRecalcAt > 45000) {
                    lastRouteRecalcAt = now;
                    var client = getClientCoords();
                    if (client.lat !== null && client.lng !== null) {
                        drawRoute(lat, lng, client.lat, client.lng, true);
                    }
                }
            },
            function () {
                rollbackTrackingStart().finally(function () {
                    setTrackingInactive('Suivi en temps réel inactif');
                    showTrackingAlert({
                        title: 'GPS interrompu',
                        message: 'Le flux GPS s\'est arrêté pendant la livraison.',
                        details: ['Vérifiez les autorisations GPS', 'Appuyez à nouveau sur Démarrer la livraison'],
                    });
                });
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
        return true;
    }

    function beginRealtimeConnection() {
        lastSocketError = '';
        return fetchWatchToken()
            .then(function (token) {
                setStatus('Connexion au serveur temps réel…', 'pending');
                return tryConnectRealtime(token, 15000);
            })
            .then(function (connected) {
                setDeliveryStatusRealtime(connected);
                if (!connected && !cfg.watchOnly && !cfg.publicMode) {
                    showTrackingAlert(getRealtimeFailureAlert());
                }
                return connected;
            })
            .catch(function (err) {
                setDeliveryStatusRealtime(false);
                if (!cfg.watchOnly && !cfg.publicMode) {
                    showTrackingAlertFromError(err);
                    throw err;
                }
                return false;
            });
    }

    function startTracking() {
        if (!cfg.canManage) {
            showTrackingAlert({
                title: 'Accès refusé',
                message: 'Vous ne pouvez pas démarrer cette livraison.',
                details: ['Seul le livreur assigné peut lancer le suivi', 'Reconnectez-vous avec le bon compte'],
            });
            return;
        }
        if (!cfg.geoReady) {
            showTrackingAlert({
                title: 'Adresse non localisée',
                message: 'L\'adresse client n\'est pas géolocalisée.',
                details: ['Retournez à la liste et relancez la livraison avec une adresse valide'],
            });
            return;
        }
        if (realtimeConnected) {
            return;
        }
        if (startBtn) startBtn.setAttribute('disabled', 'disabled');
        requestDeviceOrientationAccess();

        if (deliveryActive && watchId !== null) {
            beginRealtimeConnection().finally(function () {
                if (startBtn) startBtn.removeAttribute('disabled');
            });
            return;
        }

        setStatus('Activation du suivi GPS…', 'pending');

        callWebApi({ action: 'start' })
            .then(function () {
                return waitForFirstPosition(15000);
            })
            .then(function (pos) {
                updateDriverMarker(pos.coords.latitude, pos.coords.longitude, pos.coords);
                postPosition(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, pos.coords);
                if (!startWatchStream()) {
                    throw trackingError(
                        'Flux GPS',
                        'Impossible de démarrer le suivi continu de position.',
                        ['Vérifiez les autorisations GPS', 'Réessayez avec un autre navigateur']
                    );
                }
                setDeliveryActive(true);
                setDeliveryStatusRealtime(false);
                return beginRealtimeConnection().catch(function () {
                    /* Erreur temps réel déjà affichée — on garde le GPS actif */
                });
            })
            .catch(function (err) {
                showTrackingAlertFromError(err);
                return rollbackTrackingStart().finally(function () {
                    setTrackingInactive('Suivi en temps réel inactif');
                });
            })
            .finally(function () {
                if (startBtn) startBtn.removeAttribute('disabled');
            });
    }

    function stopTracking() {
        if (!cfg.canManage) return;
        setStatus('Arrêt du suivi…', 'pending');
        stopBtn && stopBtn.setAttribute('disabled', 'disabled');
        stopWatch();
        disconnectRealtime();

        callWebApi({ action: 'stop' })
            .then(function () {
                setDeliveryActive(false);
                setDeliveryStatusRealtime(false);
                setStatus('Suivi GPS terminé', 'off');
                if (cfg.indexUrl) {
                    window.location.href = cfg.indexUrl;
                }
            })
            .catch(function (err) {
                setStatus(err.message || 'Erreur à l\'arrêt', 'error');
            })
            .finally(function () {
                if (stopBtn) stopBtn.removeAttribute('disabled');
            });
    }

    function restoreMapToDriver(animate) {
        if (!driverMarker) {
            return;
        }
        suppressMapInteractionEvents = true;
        var driverLatLng = driverMarker.getLatLng();
        var animateOpt = animate !== false;

        if (navigationMode) {
            var pad = getNavigationPadding();
            map.setView(driverLatLng, NAV_ZOOM, {
                animate: animateOpt,
                paddingTopLeft: pad.topLeft,
                paddingBottomRight: pad.bottomRight
            });
            if (driverHeadingDeg > 0 && mapHasRotation()) {
                smoothSetMapBearing(driverHeadingDeg);
            }
        } else {
            map.setView(driverLatLng, NAV_ZOOM, { animate: animateOpt });
            if (mapHasRotation()) {
                cancelBearingAnimation();
                mapBearingDeg = 0;
                map.setBearing(0);
            }
        }

        setTimeout(function () { suppressMapInteractionEvents = false; }, animateOpt ? 450 : 50);
    }

    function recenterMapOnDriver() {
        clearAutoRecenterTimer();
        var fitBtn = document.getElementById('livreur-map-fit');
        if (fitBtn) {
            fitBtn.classList.add('is-loading');
            fitBtn.setAttribute('disabled', 'disabled');
        }

        function finishRecenter() {
            if (fitBtn) {
                fitBtn.classList.remove('is-loading');
                fitBtn.removeAttribute('disabled');
            }
        }

        if (isObserverMode()) {
            setStatus('Actualisation de la position du livreur…', 'pending');
            fetchLastPositionUpdate()
                .then(function () {
                    if (driverMarker && cfg.trackingActive) {
                        restoreMapToDriver(true);
                        if (!navigationMode) {
                            enableNavigationMode();
                        }
                        setStatus(
                            realtimeConnected ? 'Suivi en temps réel actif' : 'Position actualisée',
                            realtimeConnected ? 'live' : 'route'
                        );
                    } else if (driverMarker) {
                        restoreMapToDriver(true);
                        setStatus('Livraison terminée', 'off');
                    } else {
                        setStatus('Position livreur indisponible', 'pending');
                    }
                })
                .finally(finishRecenter);
            return;
        }

        setStatus('Actualisation de la position…', 'pending');

        refreshFromGeolocation().then(function (pos) {
            if (pos) {
                updateDriverMarker(pos.lat, pos.lng, {
                    heading: pos.heading,
                    speed: pos.speed,
                    accuracy: pos.accuracy
                }, true);
                if (gpsStreaming) {
                    postPosition(pos.lat, pos.lng, pos.accuracy, pos);
                }
                var client = getClientCoords();
                if (client.lat !== null && client.lng !== null) {
                    drawRoute(pos.lat, pos.lng, client.lat, client.lng, true);
                }
            } else if (!driverMarker) {
                setStatus('Position GPS indisponible', 'off');
                return;
            }

            restoreMapToDriver(true);

            if (deliveryActive || navigationMode) {
                setStatus(
                    realtimeConnected ? 'Suivi en temps réel actif' : 'Livraison en cours — suivi temps réel inactif',
                    realtimeConnected ? 'live' : 'off'
                );
            } else {
                setStatus('Position actualisée', 'route');
            }
        }).finally(finishRecenter);
    }

    function refreshLivePosition() {
        recenterMapOnDriver();
    }

    function bindMapControls() {
        var zoomIn = document.getElementById('livreur-map-zoom-in');
        var zoomOut = document.getElementById('livreur-map-zoom-out');
        var fitBtn = document.getElementById('livreur-map-fit');

        if (zoomIn) zoomIn.addEventListener('click', function () { map.zoomIn(); });
        if (zoomOut) zoomOut.addEventListener('click', function () { map.zoomOut(); });
        if (fitBtn) fitBtn.addEventListener('click', recenterMapOnDriver);

        map.on('dragend', onUserMapInteraction);
        map.on('zoomend', onUserMapInteraction);

        if (cfg.canManage) {
            bindDeviceOrientation();
        }

        window.addEventListener('resize', function () {
            setTimeout(function () { map.invalidateSize(); }, 120);
        });

        setTimeout(function () { map.invalidateSize(); }, 200);
    }

    function bindTrackingButtons() {
        if (startBtn) startBtn.addEventListener('click', startTracking);
        if (stopBtn) stopBtn.addEventListener('click', stopTracking);
        setDeliveryActive(false);
        realtimeConnected = false;
        updateTrackingButtons();
    }

    function resumeActiveTracking() {
        if (!cfg.trackingActive || !cfg.canManage) {
            return;
        }
        setDeliveryActive(true);
        updateTrackingButtons();
        requestDeviceOrientationAccess();
        if (!startWatchStream()) {
            setTrackingInactive('Suivi en temps réel inactif');
            showTrackingAlert({
                title: 'Reprise impossible',
                message: 'Le GPS n\'a pas pu reprendre le suivi en cours.',
                details: ['Autorisez la géolocalisation', 'Appuyez sur Démarrer la livraison'],
            });
            return;
        }
        setDeliveryStatusRealtime(false);
        beginRealtimeConnection().catch(function () {
            /* popup déjà affichée */
        });
    }

    function autoStartTrackingIfNeeded() {
        if (cfg.watchOnly) {
            startWatchObserverMode();
            return;
        }
        if (!cfg.canManage) {
            return;
        }
        if (cfg.trackingActive) {
            resumeActiveTracking();
            return;
        }
        if (cfg.autostart && cfg.geoReady) {
            startTracking();
        }
    }

    function buildLastPositionUrl() {
        var url = cfg.lastPositionUrl || '/api/tracking/last-position.php';
        var params = new URLSearchParams();
        if (cfg.blId) {
            params.set('bl_id', String(cfg.blId));
        } else if (cfg.commandeId) {
            params.set('commande_id', String(cfg.commandeId));
        }
        if (cfg.publicWatchToken) {
            params.set('token', cfg.publicWatchToken);
        }
        return url + '?' + params.toString();
    }

    function fetchLastPositionUpdate() {
        return fetch(buildLastPositionUrl(), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }
                if (!data.tracking_active) {
                    if (cfg.trackingActive) {
                        handleTrackingEnded();
                    }
                    return;
                }
                if (!cfg.trackingActive) {
                    cfg.trackingActive = true;
                }
                if (data.last_position) {
                    applyRemoteDriverPosition(data.last_position);
                } else if (cfg.trackingActive && isObserverMode()) {
                    setStatus('En attente de la position du livreur…', 'pending');
                }
            })
            .catch(function () { /* silencieux */ });
    }

    function startPositionPolling() {
        if (positionPollTimer) {
            clearInterval(positionPollTimer);
        }
        positionPollTimer = setInterval(fetchLastPositionUpdate, 4000);
        fetchLastPositionUpdate();
    }

    function startWatchObserverMode() {
        if (!cfg.geoReady) {
            setStatus('Adresse client non géolocalisée', 'error');
        } else {
            setStatus('Connexion au suivi en direct…', 'pending');
        }
        beginRealtimeConnection()
            .then(function (connected) {
                startPositionPolling();
                if (connected) {
                    setDeliveryStatusRealtime(true);
                    if (cfg.trackingActive) {
                        setStatus('Suivi en temps réel actif', 'live');
                    } else {
                        setStatus('En attente du démarrage livreur…', 'pending');
                    }
                } else if (cfg.trackingActive) {
                    setStatus('Position actualisée toutes les 4 s', 'ok');
                } else {
                    setStatus('En attente du démarrage livreur…', 'pending');
                }
                if (cfg.trackingActive && driverMarker && !navigationMode) {
                    enableNavigationMode();
                }
                return connected;
            })
            .catch(function () {
                startPositionPolling();
                if (cfg.trackingActive) {
                    setStatus('Actualisation périodique de la position', 'ok');
                    if (driverMarker && !navigationMode) {
                        enableNavigationMode();
                    }
                } else {
                    setStatus('En attente du démarrage livreur…', 'pending');
                }
            });
    }

    function bindShareDelivery() {
        var btn = document.getElementById('livreur-suivi-share-delivery');
        var topBtn = document.getElementById('livreur-suivi-share-topbar');
        if ((!btn && !topBtn) || !cfg.shareLinkUrl) {
            return;
        }

        function openShareModal() {
            var body = {};
            if (cfg.blId) {
                body.bl_id = cfg.blId;
            } else if (cfg.commandeId) {
                body.commande_id = cfg.commandeId;
            }
            var triggers = [btn, topBtn].filter(Boolean);
            triggers.forEach(function (el) { el.setAttribute('disabled', 'disabled'); });
            fetch(cfg.shareLinkUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            })
                .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
                .then(function (result) {
                    if (!result.res.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Impossible de générer le lien.');
                    }
                    window.openPlatformShareModal({
                        modalTitle: 'Partager le suivi',
                        title: result.data.title || 'Suivi livraison',
                        url: result.data.url,
                        message: result.data.message || '',
                        hint: result.data.hint || ''
                    });
                })
                .catch(function (err) {
                    showTrackingAlert({
                        title: 'Partage impossible',
                        message: err.message || 'Erreur lors de la génération du lien.',
                        details: []
                    });
                })
                .finally(function () {
                    triggers.forEach(function (el) { el.removeAttribute('disabled'); });
                });
        }

        function attachShareHandler() {
            if (typeof window.openPlatformShareModal !== 'function') {
                window.setTimeout(attachShareHandler, 50);
                return;
            }
            if (btn) {
                btn.addEventListener('click', openShareModal);
            }
            if (topBtn) {
                topBtn.addEventListener('click', openShareModal);
            }
        }

        attachShareHandler();
    }

    function loadTrackingBootstrap() {
        if (cfg.initialWatchPayload) {
            return Promise.resolve(cfg.initialWatchPayload);
        }
        if (!cfg.watchTokenUrl) {
            return Promise.reject(new Error('Configuration suivi incomplète'));
        }
        return fetch(cfg.watchTokenUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                return res.json().then(function (payload) {
                    if (!res.ok || !payload.success) {
                        throw new Error(payload.message || 'Données indisponibles');
                    }
                    return payload;
                });
            });
    }

    function bindSheetCollapse() {
        var appEl = document.getElementById('livreur-suivi-app');
        var sheet = document.getElementById('livreur-suivi-sheet');
        var toggle = document.getElementById('livreur-sheet-toggle');
        var compact = document.getElementById('livreur-sheet-compact');
        var label = document.getElementById('livreur-sheet-toggle-label');
        if (!sheet || !toggle) return;

        function setSheetCollapsed(collapsed, persist) {
            sheet.classList.toggle('is-collapsed', collapsed);
            if (appEl) {
                appEl.classList.toggle('is-sheet-collapsed', collapsed);
            }
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (compact) {
                compact.hidden = !collapsed;
            }
            if (label) {
                label.textContent = collapsed ? 'Agrandir le panneau' : 'Réduire le panneau';
            }
            if (persist) {
                try {
                    sessionStorage.setItem('livreurSheetCollapsed', collapsed ? '1' : '0');
                } catch (e) { /* ignore */ }
            }
            setTimeout(function () {
                map.invalidateSize();
            }, 280);
        }

        toggle.addEventListener('click', function () {
            setSheetCollapsed(!sheet.classList.contains('is-collapsed'), true);
        });

        try {
            if (sessionStorage.getItem('livreurSheetCollapsed') === '1') {
                setSheetCollapsed(true, false);
            }
        } catch (e) { /* ignore */ }
    }

    function deliveryItemKey(item) {
        return (item.type || 'commande') + '-' + (item.id || 0);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    var myDeliveriesRefreshTimer = null;
    var switchModalRenderFn = null;

    function updateMyDeliveriesUi() {
        var deliveries = Array.isArray(cfg.myDeliveries) ? cfg.myDeliveries : [];
        var count = deliveries.length;
        var countEl = document.querySelector('.livreur-suivi-sheet__switch-count');
        var toolbar = document.querySelector('.livreur-suivi-sheet__toolbar');
        var openBtn = document.getElementById('livreur-switch-open');
        if (countEl) {
            countEl.textContent = String(count);
        }
        if (toolbar) {
            toolbar.hidden = count < 1;
        }
        if (openBtn) {
            openBtn.disabled = count < 1;
        }
        if (typeof switchModalRenderFn === 'function') {
            switchModalRenderFn();
        }
    }

    function fetchMyDeliveries() {
        var url = cfg.myDeliveriesUrl || '/api/tracking/mes-livraisons.php';
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }
                cfg.myDeliveries = Array.isArray(data.deliveries) ? data.deliveries : [];
                updateMyDeliveriesUi();
            })
            .catch(function () { /* silencieux */ });
    }

    function startMyDeliveriesPolling() {
        fetchMyDeliveries();
        if (myDeliveriesRefreshTimer) {
            clearInterval(myDeliveriesRefreshTimer);
        }
        myDeliveriesRefreshTimer = setInterval(fetchMyDeliveries, 25000);
    }

    function bindSwitchDeliveryModal() {
        var openBtn = document.getElementById('livreur-switch-open');
        var modal = document.getElementById('livreur-switch-modal');
        var listEl = document.getElementById('livreur-switch-list');
        var closeBtn = document.getElementById('livreur-switch-close');
        if (!openBtn || !modal || !listEl) {
            return;
        }

        function renderSwitchList() {
            var deliveries = Array.isArray(cfg.myDeliveries) ? cfg.myDeliveries : [];
            listEl.innerHTML = '';
            if (deliveries.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'livreur-switch-modal__empty';
                empty.textContent = 'Aucune livraison en cours assignée.';
                listEl.appendChild(empty);
                return;
            }

            deliveries.forEach(function (item) {
                var key = deliveryItemKey(item);
                var isCurrent = key === cfg.currentDeliveryKey;
                var li = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'livreur-switch-item' + (isCurrent ? ' is-current' : '');
                btn.setAttribute('role', 'option');
                btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');

                var typeLabel = item.type === 'facture' ? 'Facture' : 'Commande';
                var numero = item.numero || ('#' + item.id);
                var client = item.client_nom || 'Client';
                var tel = item.client_tel || '';
                var adresse = item.adresse || '';
                var statutLabel = item.statut_label || '';
                var badges = '<span class="livreur-switch-item__badge livreur-switch-item__badge--type">' + escapeHtml(typeLabel) + '</span>';
                if (item.tracking_active) {
                    badges += '<span class="livreur-switch-item__badge livreur-switch-item__badge--live">GPS actif</span>';
                } else if (statutLabel) {
                    badges += '<span class="livreur-switch-item__badge livreur-switch-item__badge--status">' + escapeHtml(statutLabel) + '</span>';
                }
                if (isCurrent) {
                    badges += '<span class="livreur-switch-item__badge livreur-switch-item__badge--current">Actuelle</span>';
                }

                btn.innerHTML =
                    '<div class="livreur-switch-item__top">' +
                    '<span class="livreur-switch-item__ref">' + escapeHtml(numero) + '</span>' +
                    '<span class="livreur-switch-item__badges">' + badges + '</span>' +
                    '</div>' +
                    '<p class="livreur-switch-item__client">' + escapeHtml(client) + '</p>' +
                    (tel ? '<p class="livreur-switch-item__meta"><i class="fas fa-phone" aria-hidden="true"></i> ' + escapeHtml(tel) + '</p>' : '') +
                    (adresse ? '<p class="livreur-switch-item__meta"><i class="fas fa-location-dot" aria-hidden="true"></i> ' + escapeHtml(adresse) + '</p>' : '');

                if (isCurrent) {
                    btn.disabled = true;
                } else {
                    btn.addEventListener('click', function () {
                        if (item.suivi_url) {
                            window.location.href = item.suivi_url;
                        }
                    });
                }

                li.appendChild(btn);
                listEl.appendChild(li);
            });
        }

        switchModalRenderFn = renderSwitchList;

        function openModal() {
            fetchMyDeliveries().finally(function () {
                renderSwitchList();
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
            });
        }

        function closeModal() {
            modal.hidden = true;
            document.body.style.overflow = '';
        }

        openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        modal.querySelectorAll('[data-livreur-switch-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        updateMyDeliveriesUi();
    }

    bindTrackingAlert();
    bindSheetCollapse();
    bindSwitchDeliveryModal();
    startMyDeliveriesPolling();
    bindMapControls();
    bindTrackingButtons();
    bindShareDelivery();
    setStatus('Chargement de l\'itinéraire…', 'pending');

    loadTrackingBootstrap()
        .then(function (payload) {
            return setupItinerary(payload).then(function () {
                autoStartTrackingIfNeeded();
            });
        })
        .catch(function (err) {
            if (cfg.watchOnly || cfg.publicMode) {
                var client = getClientCoords();
                if (client.lat !== null && client.lng !== null) {
                    setClientMarker(client.lat, client.lng);
                    setStatus('Suivi chargé — position livreur en attente', 'pending');
                    autoStartTrackingIfNeeded();
                    return;
                }
                setStatus('Impossible de charger le suivi : ' + err.message, 'error');
                return;
            }
            var client = getClientCoords();
            if (client.lat !== null && client.lng !== null) {
                setClientMarker(client.lat, client.lng);
                return refreshFromGeolocation().then(function (pos) {
                    if (pos) {
                        updateDriverMarker(pos.lat, pos.lng, {
                        heading: pos.heading,
                        speed: pos.speed
                    });
                        return drawRoute(pos.lat, pos.lng, client.lat, client.lng);
                    }
                    setStatus('Itinéraire partiel — ' + err.message, 'off');
                }).then(fitMapBounds);
            }
            setStatus('Impossible de charger l\'itinéraire : ' + err.message, 'error');
        });
})();
