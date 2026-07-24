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
    var etaLabelEl = document.getElementById('livreur-suivi-eta-label');
    var alertEl = document.getElementById('livreur-suivi-alert');
    var alertTitleEl = document.getElementById('livreur-suivi-alert-title');
    var alertMessageEl = document.getElementById('livreur-suivi-alert-message');
    var alertDetailsEl = document.getElementById('livreur-suivi-alert-details');
    var alertOkBtn = document.getElementById('livreur-suivi-alert-ok');
    var alertCloseBtn = document.getElementById('livreur-suivi-alert-close');
    var loadingOverlayEl = document.getElementById('livreur-suivi-loading');
    var loadingMessageEl = document.getElementById('livreur-suivi-loading-message');
    var confirmStopEl = document.getElementById('livreur-suivi-confirm-stop');
    var confirmStopYesBtn = document.getElementById('livreur-suivi-confirm-stop-yes');
    var confirmStopNoBtn = document.getElementById('livreur-suivi-confirm-stop-no');
    var topbarEl = document.querySelector('.livreur-suivi-topbar');
    var topbarTitleEl = document.getElementById('livreur-topbar-title');
    var topbarCountdownEl = document.getElementById('livreur-topbar-countdown');
    var topbarCountdownLabelEl = document.getElementById('livreur-topbar-countdown-label');
    var topbarCountdownValueEl = document.getElementById('livreur-topbar-countdown-value');
    var lastSocketError = '';
    var autostartFailed = false;
    var autostartInProgress = false;
    var manualDeliveryConfirmed = false;
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

    function showLoadingOverlay(message) {
        if (!loadingOverlayEl) return;
        if (loadingMessageEl && message) {
            loadingMessageEl.textContent = message;
        }
        loadingOverlayEl.hidden = false;
        document.body.classList.add('livreur-suivi-loading-open');
    }

    function setLoadingOverlayMessage(message) {
        if (loadingMessageEl && message) {
            loadingMessageEl.textContent = message;
        }
    }

    function hideLoadingOverlay() {
        if (!loadingOverlayEl) return;
        loadingOverlayEl.hidden = true;
        document.body.classList.remove('livreur-suivi-loading-open');
    }

    function shouldAutoStartDriver() {
        return cfg.canManage
            && !cfg.watchOnly
            && !cfg.publicMode
            && cfg.geoReady
            && (cfg.autostart || cfg.trackingActive);
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
        maxZoom: 19,
        zoomSnap: 0.25,
        zoomDelta: 0.5
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
    var activeRouteCoords = null;
    var routeRecalcInFlight = false;
    var ROUTE_MATCH_TOLERANCE_M = 50;
    var ROUTE_OFF_PATH_THRESHOLD_M = 50;
    var ROUTE_RECALC_CONFIRM_MS = 4000;
    var ROUTE_RECALC_CONFIRM_SAMPLES = 3;
    var ROUTE_RECALC_MIN_INTERVAL_MS = 12000;
    var ROUTE_RECALC_PERIODIC_MS = 45000;
    var ROUTE_HEADING_PARALLEL_MAX_DEG = 35;
    var ROUTE_HEADING_DIVERGE_MIN_DEG = 70;
    var ROUTE_HEADING_PARALLEL_BUFFER_M = 15;
    var driverHeadingDeg = 0;
    var mapBearingDeg = 0;
    var lastGpsSpeed = null;
    var lastDriverLat = null;
    var lastDriverLng = null;
    var autoRecenterTimer = null;
    var suppressMapInteractionEvents = false;
    var navigationMode = false;
    var bearingAnimFrame = null;
    var NAV_START_ZOOM = typeof cfg.navStartZoom === 'number' ? cfg.navStartZoom : 17.5;
    var AUTO_RECENTER_MS = cfg.navRecenterDelayMs || 10000;
    /** Vitesses (km/h) et zoom cible — conduite moto / voiture */
    var ZOOM_SPEED_SLOW_MAX_KMH = 20;   /* 0–20 km/h → zoom 19→18 */
    var ZOOM_SPEED_CITY_MAX_KMH = 50;   /* 20–50 km/h → zoom 17→16 */
    var ZOOM_MIN = 14;                  /* > 50 km/h → zoom 15→14 */
    var ZOOM_MAX = 19;
    var ZOOM_LERP_SLOW = 0.22;          /* adoucissement zoom à basse vitesse */
    var ZOOM_LERP_FAST = 0.14;          /* adoucissement zoom à haute vitesse */
    var ZOOM_APPLY_THRESHOLD = 0.12;    /* delta minimal avant animation carte */
    var currentNavZoom = NAV_START_ZOOM;
    var targetNavZoom = NAV_START_ZOOM;
    var lastAppliedNavZoom = null;
    var ROUTE_HEADING_LOOKAHEAD_M = 55;
    var ROUTE_SNAP_MAX_M = ROUTE_MATCH_TOLERANCE_M;
    var MIN_MOVE_FOR_ANIMATED_FOLLOW = 0.00005;
    var MAP_FOLLOW_MIN_INTERVAL_MS = 900;
    var positionPollTimer = null;
    var observerFollowActive = false;
    var observerStopped = false;
    var countdownTickTimer = null;
    var countdownSync = {
        remainingSec: null,
        status: null,
        localAt: null
    };
    var lastMapFollowAt = 0;
    var bearingTargetDeg = 0;
    var lastRouteSegIdx = 0;
    var nativeDriverTracking = false;
    var observerSocketConnecting = false;
    var lastRemotePositionAt = 0;
    var mapUserInteracted = false;
    var mapFollowPaused = false;
    var offRouteState = {
        active: false,
        since: 0,
        samples: 0,
        timer: null,
        lastLat: null,
        lastLng: null
    };
    var ROUTE_COLOR = '#e5488a';
    var ROUTE_WEIGHT = 7;
    var ROUTE_WEIGHT_FALLBACK = 5;
    var POSITION_POLL_FAST_MS = 1500;
    var POSITION_POLL_NORMAL_MS = 4000;

    function resolveSocketUrl() {
        var configured = (cfg.socketUrl || '').replace(/\/+$/, '');
        if (!configured) {
            return window.location.origin;
        }
        try {
            var cfgUrl = new URL(configured);
            var current = window.location;
            var isLocalPage = current.hostname === 'localhost' || current.hostname === '127.0.0.1';
            var isLocalSocket = cfgUrl.hostname === 'localhost' || cfgUrl.hostname === '127.0.0.1';
            if (isLocalPage && isLocalSocket) {
                return cfgUrl.origin;
            }
            if (isLocalPage && !isLocalSocket) {
                return 'http://127.0.0.1:3001';
            }
        } catch (e) {
            return configured;
        }
        return configured;
    }

    function isNativeDriverTrackingAvailable() {
        return typeof window.LivreurNativeTracking !== 'undefined' &&
            window.LivreurNativeTracking.isAvailable();
    }

    function startNativeDriverTracking() {
        if (!isNativeDriverTrackingAvailable()) {
            return Promise.resolve(false);
        }
        return window.LivreurNativeTracking.start().then(function (result) {
            var ok = !!(result && result.success);
            nativeDriverTracking = ok;
            if (ok) {
                clearBackgroundTracking();
                /* Garder le flux WebView en secours (HTTP + socket) si le canal natif n'enregistre pas */
            }
            return ok;
        }).catch(function () {
            nativeDriverTracking = false;
            return false;
        });
    }

    function stopNativeDriverTracking() {
        nativeDriverTracking = false;
        if (typeof window.LivreurNativeTracking !== 'undefined') {
            return window.LivreurNativeTracking.stop().catch(function () {
                return { success: false };
            });
        }
        return Promise.resolve({ success: false });
    }

    function isObserverMode() {
        return !!(cfg.watchOnly || cfg.publicMode);
    }

    /** Mode navigation livreur : carte qui tourne (cap en haut). */
    function isDriverNavMode() {
        return navigationMode && !isObserverMode();
    }

    function shouldRotateMapWithHeading() {
        return isDriverNavMode() && mapHasRotation();
    }

    function ensureNorthUpMap() {
        if (!mapHasRotation()) {
            return;
        }
        cancelBearingAnimation();
        mapBearingDeg = 0;
        map.setBearing(0);
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
        hideCountdown();
        if (titleEl) {
            titleEl.textContent = 'Livraison terminée';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--off';
        }
        setStatus('Suivi en temps réel arrêté', 'off');
    }

    function handleTrackingPaused(countdown) {
        cfg.trackingActive = false;
        observerStopped = false;
        if (countdown) {
            applyCountdownFromServer(countdown);
        } else if (countdownSync.remainingSec !== null) {
            countdownSync.status = countdownSync.remainingSec <= 0 ? 'late_paused' : 'paused';
            renderCountdownDisplay(countdownSync.status, getLocalRemainingSec());
            startCountdownTicker();
        }
        if (isObserverMode() && titleEl) {
            titleEl.textContent = 'Livraison en pause';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--pending';
        }
        if (isObserverMode()) {
            setStatus('Le livreur est momentanément sur une autre course', 'pending');
        }
    }

    function restoreObserverLiveTitle() {
        if (!isObserverMode() || !titleEl) {
            return;
        }
        if (countdownSync.status === 'late' || countdownSync.status === 'late_paused') {
            titleEl.textContent = 'Livraison en cours';
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--live';
            return;
        }
        titleEl.textContent = 'Livraison en cours';
        titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--live';
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
        lastRemotePositionAt = Date.now();
        var coords = {
            heading: pos.heading != null ? parseFloat(pos.heading) : null,
            speed: pos.speed != null ? parseFloat(pos.speed) : null
        };
        updateDriverMarker(lat, lng, coords, isObserverMode());
        if (!isObserverMode()) {
            return;
        }
        observerFollowActive = true;
        ensureNorthUpMap();
        maybeRecalculateObserverRoute(lat, lng);
    }

    function escapeHtmlAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function hasDriverPhoto() {
        return !!(cfg.livreurPhotoUrl && String(cfg.livreurPhotoUrl).trim());
    }

    function driverMarkerInnerClassName() {
        return hasDriverPhoto()
            ? 'livreur-marker-moto livreur-marker-avatar'
            : 'livreur-marker-moto';
    }

    function driverMarkerIconModifierClass() {
        if (hasDriverPhoto() || cfg.livreurInitials) {
            return ' livreur-marker-icon--avatar';
        }
        return ' livreur-marker-icon--moto';
    }

    function makeDriverMarkerInnerHtml() {
        if (hasDriverPhoto()) {
            return '<div class="' + driverMarkerInnerClassName() + '">' +
                '<img src="' + escapeHtmlAttr(cfg.livreurPhotoUrl) + '" alt="" class="livreur-marker-avatar__img" decoding="async" loading="lazy">' +
                '</div>';
        }
        var initial = String(cfg.livreurInitials || 'L').charAt(0).toUpperCase();
        return '<div class="livreur-marker-moto livreur-marker-avatar livreur-marker-avatar--initial">' +
            '<span class="livreur-marker-avatar__initial" aria-hidden="true">' + escapeHtmlAttr(initial) + '</span>' +
            '</div>';
    }

    function makeDriverMotoHtml() {
        if (hasDriverPhoto() || cfg.livreurInitials) {
            return makeDriverMarkerInnerHtml();
        }
        return '<div class="livreur-marker-moto" style="transform:rotate(' + driverHeadingDeg + 'deg)">' +
            '<i class="fas fa-motorcycle" aria-hidden="true"></i></div>';
    }

    function bindDriverMarkerExpand() {
        if (!driverMarker) {
            return;
        }
        var markerEl = driverMarker.getElement();
        if (!markerEl || markerEl._livreurExpandBound) {
            return;
        }
        markerEl._livreurExpandBound = true;
        markerEl.setAttribute('role', 'button');
        markerEl.setAttribute('tabindex', '0');
        markerEl.setAttribute('aria-label', 'Photo du livreur — appuyer pour agrandir');
        markerEl.addEventListener('click', function (ev) {
            ev.stopPropagation();
            markerEl.classList.toggle('livreur-marker-wrap--expanded');
        });
        markerEl.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                markerEl.classList.toggle('livreur-marker-wrap--expanded');
            }
        });
    }

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

    function durationToCountdownSeconds(durationSeconds) {
        var range = durationToRangeMinutes(durationSeconds);
        return Math.max(60, range.max * 60);
    }

    function formatCountdownTime(totalSec) {
        totalSec = Math.max(0, Math.floor(totalSec));
        var minutes = Math.floor(totalSec / 60);
        var seconds = totalSec % 60;
        if (minutes >= 60) {
            var hours = Math.floor(minutes / 60);
            minutes = minutes % 60;
            return hours + ' h ' + String(minutes).padStart(2, '0') + ' min';
        }
        return minutes + ' min ' + String(seconds).padStart(2, '0') + ' s';
    }

    function formatLateMinutes(lateSec) {
        var lateSeconds = Math.abs(Math.floor(lateSec));
        var minutes = Math.max(1, Math.ceil(lateSeconds / 60));
        return minutes + ' min';
    }

    function setTopbarCountdownVisible(visible) {
        if (topbarEl) {
            topbarEl.classList.toggle('has-live-countdown', !!visible);
        }
        if (topbarCountdownEl) {
            topbarCountdownEl.hidden = !visible;
        }
        if (topbarTitleEl) {
            topbarTitleEl.hidden = !!visible;
        }
    }

    function applyTopbarCountdownVisualState(status) {
        if (!topbarCountdownEl) {
            return;
        }
        topbarCountdownEl.classList.remove(
            'livreur-suivi-topbar__countdown--running',
            'livreur-suivi-topbar__countdown--late',
            'livreur-suivi-topbar__countdown--paused'
        );
        if (status === 'running') {
            topbarCountdownEl.classList.add('livreur-suivi-topbar__countdown--running');
        } else if (status === 'late' || status === 'late_paused') {
            topbarCountdownEl.classList.add('livreur-suivi-topbar__countdown--late');
        } else if (status === 'paused') {
            topbarCountdownEl.classList.add('livreur-suivi-topbar__countdown--paused');
        }
    }

    function renderTopbarCountdown(status, remainingSec) {
        if (!topbarCountdownEl || !topbarCountdownValueEl) {
            return;
        }
        applyTopbarCountdownVisualState(status);
        if (status === 'running') {
            if (topbarCountdownLabelEl) {
                topbarCountdownLabelEl.textContent = 'Arrivée dans';
            }
            topbarCountdownValueEl.textContent = formatCountdownTime(remainingSec);
        } else if (status === 'late') {
            if (topbarCountdownLabelEl) {
                topbarCountdownLabelEl.textContent = 'Retard';
            }
            topbarCountdownValueEl.textContent = formatLateMinutes(remainingSec);
        } else if (status === 'paused') {
            if (topbarCountdownLabelEl) {
                topbarCountdownLabelEl.textContent = 'En pause';
            }
            topbarCountdownValueEl.textContent = formatCountdownTime(remainingSec);
        } else if (status === 'late_paused') {
            if (topbarCountdownLabelEl) {
                topbarCountdownLabelEl.textContent = 'Pause — retard';
            }
            topbarCountdownValueEl.textContent = formatLateMinutes(remainingSec);
        }
        setTopbarCountdownVisible(true);
    }

    function setCountdownAppVisible(visible) {
        var app = document.getElementById('livreur-suivi-app');
        if (!app) {
            return;
        }
        if (visible) {
            app.classList.add('has-countdown');
            if (cfg.publicMode) {
                app.classList.add('has-live-eta');
            }
        } else {
            app.classList.remove('has-countdown');
        }
    }

    function applyCountdownVisualState(status) {
        if (!etaBlock) {
            return;
        }
        etaBlock.classList.remove(
            'livreur-suivi-sheet__eta--running',
            'livreur-suivi-sheet__eta--late',
            'livreur-suivi-sheet__eta--paused'
        );
        if (status === 'running') {
            etaBlock.classList.add('livreur-suivi-sheet__eta--running');
        } else if (status === 'late' || status === 'late_paused') {
            etaBlock.classList.add('livreur-suivi-sheet__eta--late');
        } else if (status === 'paused') {
            etaBlock.classList.add('livreur-suivi-sheet__eta--paused');
        }
    }

    function renderCountdownDisplay(status, remainingSec) {
        if (topbarCountdownValueEl) {
            renderTopbarCountdown(status, remainingSec);
        }
        if (!etaBlock || !etaRangeEl) {
            setCountdownAppVisible(true);
            return;
        }
        applyCountdownVisualState(status);
        if (status === 'running') {
            if (etaLabelEl) {
                etaLabelEl.textContent = 'Arrivée estimée dans';
            }
            etaRangeEl.textContent = formatCountdownTime(remainingSec);
        } else if (status === 'late') {
            if (etaLabelEl) {
                etaLabelEl.textContent = 'Retard';
            }
            etaRangeEl.textContent = formatLateMinutes(remainingSec);
        } else if (status === 'paused') {
            if (etaLabelEl) {
                etaLabelEl.textContent = 'En pause';
            }
            etaRangeEl.textContent = formatCountdownTime(remainingSec) + ' restantes';
        } else if (status === 'late_paused') {
            if (etaLabelEl) {
                etaLabelEl.textContent = 'En pause — retard';
            }
            etaRangeEl.textContent = formatLateMinutes(remainingSec);
        }
        etaBlock.hidden = false;
        setCountdownAppVisible(true);
    }

    function getLocalRemainingSec() {
        if (countdownSync.remainingSec === null) {
            return null;
        }
        if (countdownSync.status === 'paused' || countdownSync.status === 'late_paused') {
            return countdownSync.remainingSec;
        }
        if (countdownSync.localAt === null) {
            return countdownSync.remainingSec;
        }
        var elapsed = (Date.now() - countdownSync.localAt) / 1000;
        return Math.floor(countdownSync.remainingSec - elapsed);
    }

    function tickCountdownDisplay() {
        if (countdownSync.remainingSec === null) {
            return;
        }
        var remaining = getLocalRemainingSec();
        if (remaining === null) {
            return;
        }
        var status = countdownSync.status;
        if (status === 'running' && remaining <= 0) {
            status = 'late';
            countdownSync.status = 'late';
        }
        renderCountdownDisplay(status, remaining);
    }

    function startCountdownTicker() {
        stopCountdownTicker();
        tickCountdownDisplay();
        countdownTickTimer = setInterval(tickCountdownDisplay, 1000);
    }

    function stopCountdownTicker() {
        if (countdownTickTimer) {
            clearInterval(countdownTickTimer);
            countdownTickTimer = null;
        }
    }

    function applyCountdownFromServer(state) {
        if (!state || state.remaining_sec === undefined || state.remaining_sec === null) {
            return false;
        }
        countdownSync.remainingSec = parseInt(state.remaining_sec, 10);
        countdownSync.status = state.status || (state.paused ? 'paused' : 'running');
        countdownSync.localAt = Date.now();
        renderCountdownDisplay(countdownSync.status, getLocalRemainingSec());
        startCountdownTicker();
        return true;
    }

    function pushCountdownToServer(durationSeconds) {
        if (!cfg.canManage || !cfg.webApiUrl || !durationSeconds || durationSeconds <= 0) {
            return Promise.resolve(false);
        }
        return callWebApi({
            action: 'set_countdown',
            duration_seconds: Math.round(durationSeconds)
        }).then(function (data) {
            if (data.countdown) {
                applyCountdownFromServer(data.countdown);
            }
            return true;
        }).catch(function () {
            return false;
        });
    }

    function hideCountdown() {
        stopCountdownTicker();
        countdownSync.remainingSec = null;
        countdownSync.status = null;
        countdownSync.localAt = null;
        setCountdownAppVisible(false);
        setTopbarCountdownVisible(false);
        hideEta();
    }

    function setEtaFromDuration(durationSeconds) {
        if (!durationSeconds || durationSeconds <= 0) {
            return;
        }
        if (cfg.canManage && !isObserverMode()) {
            pushCountdownToServer(durationSeconds);
            return;
        }
        if ((cfg.regarderMode || cfg.publicMode) && cfg.trackingActive && countdownSync.remainingSec === null) {
            applyCountdownFromServer({
                status: 'running',
                remaining_sec: durationToCountdownSeconds(durationSeconds),
                paused: false
            });
            return;
        }
        if (cfg.regarderMode || cfg.publicMode) {
            setWatchEtaFromDuration(durationSeconds);
        }
    }

    function hideEta() {
        if (countdownSync.remainingSec !== null) {
            return;
        }
        if (etaBlock) etaBlock.hidden = true;
        if (etaRangeEl) etaRangeEl.textContent = '—';
    }

    function distanceMeters(lat1, lng1, lat2, lng2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function estimateStraightDurationSeconds(fromLat, fromLng, toLat, toLng) {
        var distM = distanceMeters(fromLat, fromLng, toLat, toLng);
        var speedMs = 25 * 1000 / 3600;
        return Math.max(180, distM / speedMs * 1.35);
    }

    function toLocalMeters(lat, lng, refLat) {
        var cosLat = Math.cos(refLat * Math.PI / 180);
        return {
            x: lng * Math.PI / 180 * 6371000 * cosLat,
            y: lat * Math.PI / 180 * 6371000
        };
    }

    function distancePointToSegmentMeters(lat, lng, latA, lngA, latB, lngB) {
        var refLat = lat;
        var p = toLocalMeters(lat, lng, refLat);
        var a = toLocalMeters(latA, lngA, refLat);
        var b = toLocalMeters(latB, lngB, refLat);
        var abx = b.x - a.x;
        var aby = b.y - a.y;
        var apx = p.x - a.x;
        var apy = p.y - a.y;
        var abLenSq = abx * abx + aby * aby;
        if (abLenSq < 1e-6) {
            return Math.sqrt(apx * apx + apy * apy);
        }
        var t = Math.max(0, Math.min(1, (apx * abx + apy * aby) / abLenSq));
        var cx = a.x + t * abx;
        var cy = a.y + t * aby;
        var dx = p.x - cx;
        var dy = p.y - cy;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function distanceToRouteMeters(lat, lng, coords) {
        if (!coords || coords.length < 2) {
            return Infinity;
        }
        var min = Infinity;
        for (var i = 0; i < coords.length - 1; i++) {
            var seg = coords[i];
            var segNext = coords[i + 1];
            var d = distancePointToSegmentMeters(
                lat, lng,
                seg[0], seg[1],
                segNext[0], segNext[1]
            );
            if (d < min) {
                min = d;
            }
        }
        return min;
    }

    function bearingBetween(lat1, lng1, lat2, lng2) {
        var dLon = (lng2 - lng1) * Math.PI / 180;
        var rLat1 = lat1 * Math.PI / 180;
        var rLat2 = lat2 * Math.PI / 180;
        var y = Math.sin(dLon) * Math.cos(rLat2);
        var x = Math.cos(rLat1) * Math.sin(rLat2) - Math.sin(rLat1) * Math.cos(rLat2) * Math.cos(dLon);
        return normalizeHeading(Math.atan2(y, x) * 180 / Math.PI);
    }

    function shouldShowNavMarker() {
        if (isObserverMode()) {
            return false;
        }
        return navigationMode || deliveryActive || observerFollowActive || cfg.trackingActive;
    }

    function getDriverMotoElement() {
        if (!driverMarker) return null;
        var el = driverMarker.getElement();
        return el ? el.querySelector('.livreur-marker-moto') : null;
    }

    function setMotoScreenRotation(screenDeg) {
        if (hasDriverPhoto() || cfg.livreurInitials) {
            return;
        }
        var moto = getDriverMotoElement();
        if (moto) {
            moto.style.transform = 'rotate(' + screenDeg + 'deg)';
        }
    }

    function snapToRoute(lat, lng, coords) {
        if (!coords || coords.length < 2) {
            return null;
        }
        var best = { score: Infinity, segIdx: 0, t: 0, lat: lat, lng: lng, dist: Infinity };
        for (var i = 0; i < coords.length - 1; i++) {
            var latA = coords[i][0];
            var lngA = coords[i][1];
            var latB = coords[i + 1][0];
            var lngB = coords[i + 1][1];
            var refLat = lat;
            var p = toLocalMeters(lat, lng, refLat);
            var a = toLocalMeters(latA, lngA, refLat);
            var b = toLocalMeters(latB, lngB, refLat);
            var abx = b.x - a.x;
            var aby = b.y - a.y;
            var apx = p.x - a.x;
            var apy = p.y - a.y;
            var abLenSq = abx * abx + aby * aby;
            var t = abLenSq < 1e-6 ? 0 : Math.max(0, Math.min(1, (apx * abx + apy * aby) / abLenSq));
            var cx = a.x + t * abx;
            var cy = a.y + t * aby;
            var dx = p.x - cx;
            var dy = p.y - cy;
            var dist = Math.sqrt(dx * dx + dy * dy);
            var penalty = 0;
            if (i < lastRouteSegIdx - 1) {
                penalty = 100;
            } else if (i < lastRouteSegIdx) {
                penalty = 20;
            }
            var score = dist + penalty;
            if (score < best.score) {
                var cosLat = Math.cos(refLat * Math.PI / 180);
                best = {
                    score: score,
                    segIdx: i,
                    t: t,
                    lat: (cy / 6371000) * (180 / Math.PI),
                    lng: (cx / (6371000 * cosLat)) * (180 / Math.PI),
                    dist: dist
                };
            }
        }
        if (best.score < Infinity) {
            lastRouteSegIdx = best.segIdx;
        }
        return best;
    }

    function pointAlongRoute(coords, segIdx, t, extraMeters) {
        if (!coords || coords.length < 2 || segIdx < 0 || segIdx >= coords.length - 1) {
            return null;
        }
        var latA = coords[segIdx][0];
        var lngA = coords[segIdx][1];
        var latB = coords[segIdx + 1][0];
        var lngB = coords[segIdx + 1][1];
        var startLat = latA + (latB - latA) * t;
        var startLng = lngA + (lngB - lngA) * t;
        var remaining = Math.max(0, extraMeters);
        var segLen = distanceMeters(startLat, startLng, latB, lngB);
        if (segLen >= remaining) {
            var ratio = segLen > 0.5 ? remaining / segLen : 0;
            return {
                lat: startLat + (latB - startLat) * ratio,
                lng: startLng + (lngB - startLng) * ratio
            };
        }
        remaining -= segLen;
        var i = segIdx + 1;
        while (i < coords.length - 1) {
            latA = coords[i][0];
            lngA = coords[i][1];
            latB = coords[i + 1][0];
            lngB = coords[i + 1][1];
            segLen = distanceMeters(latA, lngA, latB, lngB);
            if (segLen >= remaining) {
                var r2 = segLen > 0.5 ? remaining / segLen : 0;
                return {
                    lat: latA + (latB - latA) * r2,
                    lng: lngA + (lngB - lngA) * r2
                };
            }
            remaining -= segLen;
            i++;
        }
        var last = coords[coords.length - 1];
        return { lat: last[0], lng: last[1] };
    }

    function bearingFromRoute(lat, lng, coords, lookAheadM) {
        if (!coords || coords.length < 2) {
            return null;
        }
        var snap = snapToRoute(lat, lng, coords);
        if (!snap || snap.dist > ROUTE_SNAP_MAX_M) {
            return null;
        }
        var lookM = lookAheadM || ROUTE_HEADING_LOOKAHEAD_M;
        var segLatA = coords[snap.segIdx][0];
        var segLngA = coords[snap.segIdx][1];
        var segLatB = coords[snap.segIdx + 1][0];
        var segLngB = coords[snap.segIdx + 1][1];
        var segBearing = bearingBetween(segLatA, segLngA, segLatB, segLngB);
        var ahead = pointAlongRoute(coords, snap.segIdx, snap.t, lookM);
        if (!ahead) {
            return segBearing;
        }
        var aheadDist = distanceMeters(snap.lat, snap.lng, ahead.lat, ahead.lng);
        if (aheadDist < 6) {
            return segBearing;
        }
        var lookBearing = bearingBetween(snap.lat, snap.lng, ahead.lat, ahead.lng);
        if (snap.segIdx + 1 < coords.length - 1) {
            var nextLatA = coords[snap.segIdx + 1][0];
            var nextLngA = coords[snap.segIdx + 1][1];
            var nextLatB = coords[snap.segIdx + 2][0];
            var nextLngB = coords[snap.segIdx + 2][1];
            var nextBearing = bearingBetween(nextLatA, nextLngA, nextLatB, nextLngB);
            var turnDelta = Math.abs(shortestAngleDiff(segBearing, nextBearing));
            if (turnDelta > 18) {
                var remainOnSeg = distanceMeters(snap.lat, snap.lng, segLatB, segLngB);
                var blend = remainOnSeg < 45 ? Math.max(0, 1 - remainOnSeg / 45) : 0;
                if (blend > 0) {
                    return normalizeHeading(
                        segBearing + shortestAngleDiff(segBearing, nextBearing) * blend
                    );
                }
            }
        }
        return lookBearing;
    }

    function refreshHeadingFromRoute(lat, lng) {
        if (!activeRouteCoords || activeRouteCoords.length < 2) {
            return false;
        }
        var routeHeading = bearingFromRoute(lat, lng, activeRouteCoords);
        if (routeHeading == null) {
            return false;
        }
        applyDriverHeading(routeHeading);
        return true;
    }

    function resetOffRouteState() {
        offRouteState.active = false;
        offRouteState.since = 0;
        offRouteState.samples = 0;
        offRouteState.lastLat = null;
        offRouteState.lastLng = null;
        if (offRouteState.timer) {
            clearTimeout(offRouteState.timer);
            offRouteState.timer = null;
        }
    }

    function getSnappedDriverPosition(rawLat, rawLng) {
        if (isObserverMode() || !activeRouteCoords || activeRouteCoords.length < 2) {
            return { lat: rawLat, lng: rawLng, snapped: false, dist: Infinity };
        }
        var snap = snapToRoute(rawLat, rawLng, activeRouteCoords);
        if (!snap || snap.dist > ROUTE_MATCH_TOLERANCE_M) {
            return { lat: rawLat, lng: rawLng, snapped: false, dist: snap ? snap.dist : Infinity };
        }
        return { lat: snap.lat, lng: snap.lng, snapped: true, dist: snap.dist };
    }

    function analyzeRouteDeviation(rawLat, rawLng, coords) {
        if (!activeRouteCoords || activeRouteCoords.length < 2) {
            return { offRoute: false, immediate: false, dist: 0 };
        }
        var dist = distanceToRouteMeters(rawLat, rawLng, activeRouteCoords);
        if (dist <= ROUTE_OFF_PATH_THRESHOLD_M) {
            return { offRoute: false, immediate: false, dist: dist };
        }

        var gpsHeading = coords && coords.heading != null ? parseFloat(coords.heading) : null;
        var routeHeading = bearingFromRoute(rawLat, rawLng, activeRouteCoords);

        if (gpsHeading != null && isFinite(gpsHeading) && gpsHeading >= 0 && routeHeading != null) {
            var headingDelta = Math.abs(shortestAngleDiff(gpsHeading, routeHeading));
            if (headingDelta <= ROUTE_HEADING_PARALLEL_MAX_DEG &&
                dist <= ROUTE_OFF_PATH_THRESHOLD_M + ROUTE_HEADING_PARALLEL_BUFFER_M) {
                return { offRoute: false, immediate: false, dist: dist, parallel: true };
            }
            if (headingDelta >= ROUTE_HEADING_DIVERGE_MIN_DEG &&
                dist > ROUTE_OFF_PATH_THRESHOLD_M * 0.6) {
                return { offRoute: true, immediate: true, dist: dist, headingDelta: headingDelta };
            }
        }

        return { offRoute: true, immediate: false, dist: dist };
    }

    function triggerRouteRecalc(driverLat, driverLng, silent, bypassDebounce) {
        var client = getClientCoords();
        if (client.lat === null || client.lng === null) {
            return;
        }
        var now = Date.now();
        if (routeRecalcInFlight) {
            return;
        }
        if (!bypassDebounce && now - lastRouteRecalcAt < ROUTE_RECALC_MIN_INTERVAL_MS) {
            return;
        }

        resetOffRouteState();
        lastRouteRecalcAt = now;
        routeRecalcInFlight = true;
        if (!silent && statusEl) {
            setStatus('Recalcul de l\'itinéraire…', 'pending');
        }
        drawRoute(driverLat, driverLng, client.lat, client.lng, true)
            .finally(function () {
                routeRecalcInFlight = false;
            });
    }

    function maybeRecalculateObserverRoute(driverLat, driverLng) {
        if (!isObserverMode()) {
            return;
        }
        var client = getClientCoords();
        if (client.lat === null || client.lng === null) {
            return;
        }
        var now = Date.now();
        if (routeRecalcInFlight) {
            return;
        }
        var needsRoute = !activeRouteCoords || activeRouteCoords.length < 3;
        var periodicDue = now - lastRouteRecalcAt > ROUTE_RECALC_PERIODIC_MS;
        if (!needsRoute && !periodicDue) {
            return;
        }
        triggerRouteRecalc(driverLat, driverLng, true);
    }

    function maybeRecalculateRoute(driverLat, driverLng, force, coords) {
        if (isObserverMode() || !cfg.canManage) {
            return;
        }
        var client = getClientCoords();
        if (client.lat === null || client.lng === null) {
            return;
        }
        if (!force && !deliveryActive && !gpsStreaming) {
            return;
        }

        var now = Date.now();
        var periodicDue = force || (now - lastRouteRecalcAt > ROUTE_RECALC_PERIODIC_MS);
        var deviation = analyzeRouteDeviation(driverLat, driverLng, coords || null);

        if (!deviation.offRoute) {
            resetOffRouteState();
            if (!periodicDue) {
                return;
            }
            triggerRouteRecalc(driverLat, driverLng, true);
            return;
        }

        offRouteState.lastLat = driverLat;
        offRouteState.lastLng = driverLng;

        if (deviation.immediate) {
            triggerRouteRecalc(driverLat, driverLng, false, true);
            return;
        }

        offRouteState.samples += 1;
        if (!offRouteState.active) {
            offRouteState.active = true;
            offRouteState.since = now;
            if (statusEl) {
                setStatus('Écart d\'itinéraire détecté…', 'pending');
            }
            offRouteState.timer = setTimeout(function () {
                offRouteState.timer = null;
                if (!offRouteState.active) {
                    return;
                }
                var lat = offRouteState.lastLat;
                var lng = offRouteState.lastLng;
                if (lat == null || lng == null) {
                    return;
                }
                var stillOff = analyzeRouteDeviation(lat, lng, null);
                if (stillOff.offRoute) {
                    triggerRouteRecalc(lat, lng, false);
                } else {
                    resetOffRouteState();
                }
            }, ROUTE_RECALC_CONFIRM_MS);
        }

        if (offRouteState.samples >= ROUTE_RECALC_CONFIRM_SAMPLES) {
            triggerRouteRecalc(driverLat, driverLng, false);
        }
    }

    function syncBackgroundTracking(active) {
        if (nativeDriverTracking || isNativeDriverTrackingAvailable()) {
            return;
        }
        if (!cfg.enableBackgroundTracking || !cfg.canManage || typeof window.LivreurBgTracker === 'undefined') {
            return;
        }
        if (active || deliveryActive || cfg.trackingActive) {
            window.LivreurBgTracker.saveSession(cfg);
        }
    }

    function clearBackgroundTracking() {
        if (typeof window.LivreurBgTracker !== 'undefined') {
            window.LivreurBgTracker.clearSession();
        }
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
            resetDriverMotoRotation();
        }
    }

    function cancelBearingAnimation() {
        if (bearingAnimFrame) {
            cancelAnimationFrame(bearingAnimFrame);
            bearingAnimFrame = null;
        }
    }

    function resetDriverMotoRotation() {
        setMotoScreenRotation(0);
    }

    function smoothSetMapBearing(targetHeading) {
        if (!shouldRotateMapWithHeading()) {
            return;
        }
        bearingTargetDeg = normalizeHeading(targetHeading);
        driverHeadingDeg = bearingTargetDeg;
        if (bearingAnimFrame) {
            return;
        }

        function step() {
            var diff = shortestAngleDiff(mapBearingDeg, bearingTargetDeg);
            if (Math.abs(diff) < 0.4) {
                mapBearingDeg = bearingTargetDeg;
                map.setBearing(mapBearingDeg);
                setMotoScreenRotation(0);
                bearingAnimFrame = null;
                return;
            }
            var absDiff = Math.abs(diff);
            var stepFactor = absDiff > 35 ? 0.42 : (absDiff > 15 ? 0.28 : 0.18);
            mapBearingDeg = normalizeHeading(mapBearingDeg + diff * stepFactor);
            map.setBearing(mapBearingDeg);
            setMotoScreenRotation(0);
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

    function speedMsToKmh(speedMs) {
        if (speedMs == null || !isFinite(speedMs)) {
            return 0;
        }
        return Math.max(0, speedMs * 3.6);
    }

    /** Zoom cible selon la vitesse GPS (km/h) — conduite moto / voiture.
     *  0–20 km/h : 19→18 | 20–50 km/h : 17→16 | >50 km/h : 15→14 */
    function getTargetZoomFromSpeedKmh(speedKmh) {
        var v = Math.max(0, speedKmh);
        if (v <= ZOOM_SPEED_SLOW_MAX_KMH) {
            return ZOOM_MAX - (v / ZOOM_SPEED_SLOW_MAX_KMH);
        }
        if (v <= ZOOM_SPEED_CITY_MAX_KMH) {
            return 17 - ((v - ZOOM_SPEED_SLOW_MAX_KMH) / (ZOOM_SPEED_CITY_MAX_KMH - ZOOM_SPEED_SLOW_MAX_KMH));
        }
        var fast = Math.min(v, 100);
        return 15 - ((fast - ZOOM_SPEED_CITY_MAX_KMH) / 50);
    }

    function resetNavZoomState() {
        currentNavZoom = NAV_START_ZOOM;
        targetNavZoom = NAV_START_ZOOM;
        lastAppliedNavZoom = null;
    }

    function refreshNavZoomFromSpeed(speedMs) {
        if (!isDriverNavMode() || mapFollowPaused) {
            return;
        }
        targetNavZoom = getTargetZoomFromSpeedKmh(speedMsToKmh(speedMs));
        var diff = targetNavZoom - currentNavZoom;
        if (Math.abs(diff) < 0.04) {
            currentNavZoom = targetNavZoom;
            return;
        }
        var speedKmh = speedMsToKmh(speedMs);
        var lerp = speedKmh > 35 ? ZOOM_LERP_FAST : ZOOM_LERP_SLOW;
        currentNavZoom += diff * lerp;
    }

    function getDriverNavZoom() {
        return Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, currentNavZoom));
    }

    function updateRecenterButtonState() {
        var fitBtn = document.getElementById('livreur-map-fit');
        var controls = document.querySelector('.livreur-suivi-map-controls');
        var paused = mapFollowPaused && !isObserverMode();
        if (fitBtn) {
            fitBtn.classList.toggle('is-recenter-prompt', paused);
            var icon = fitBtn.querySelector('i');
            if (icon) {
                icon.className = paused ? 'fas fa-crosshairs' : 'fas fa-location-arrow';
            }
            fitBtn.setAttribute(
                'aria-label',
                paused
                    ? 'Reprendre le suivi GPS'
                    : (isObserverMode() ? 'Recentrer sur le livreur' : 'Recentrer et actualiser ma position')
            );
        }
        if (controls) {
            controls.classList.toggle('is-follow-paused', paused);
        }
    }

    function followDriverNavigation(animate, movedDistHint) {
        if (!driverMarker || !isDriverNavMode() || mapFollowPaused) {
            return;
        }
        var driverLatLng = driverMarker.getLatLng();
        var now = Date.now();
        var movedDist = typeof movedDistHint === 'number' ? movedDistHint : 0;
        if (!movedDist && lastDriverLat != null && lastDriverLng != null) {
            movedDist = Math.hypot(driverLatLng.lat - lastDriverLat, driverLatLng.lng - lastDriverLng);
        }
        var significantMove = movedDist >= MIN_MOVE_FOR_ANIMATED_FOLLOW;
        refreshNavZoomFromSpeed(lastGpsSpeed);
        var zoom = getDriverNavZoom();
        var zoomChanged = lastAppliedNavZoom === null ||
            Math.abs(zoom - lastAppliedNavZoom) >= ZOOM_APPLY_THRESHOLD;
        if (!significantMove && !zoomChanged && now - lastMapFollowAt < MAP_FOLLOW_MIN_INTERVAL_MS) {
            return;
        }
        lastMapFollowAt = now;
        var shouldAnimate = (animate === true && significantMove) || zoomChanged;
        lastAppliedNavZoom = zoom;
        suppressMapInteractionEvents = true;
        var pad = getNavigationPadding();
        map.setView(driverLatLng, zoom, {
            animate: shouldAnimate,
            paddingTopLeft: pad.topLeft,
            paddingBottomRight: pad.bottomRight
        });
        setTimeout(function () { suppressMapInteractionEvents = false; }, shouldAnimate ? 400 : 50);
        scheduleDriverMapBearingAfterView(true);
    }

    function makeDriverArrowIcon() {
        var innerHtml = makeDriverMotoHtml();
        var modifierClass = driverMarkerIconModifierClass();
        if (shouldShowNavMarker()) {
            return L.divIcon({
                className: 'livreur-marker-wrap livreur-marker-wrap--driver livreur-marker-wrap--nav',
                html: '<div class="livreur-marker-icon livreur-marker-icon--driver' + modifierClass + '">' +
                    innerHtml + '</div>',
                iconSize: [48, 48],
                iconAnchor: [24, 24],
            });
        }
        return L.divIcon({
            className: 'livreur-marker-wrap livreur-marker-wrap--driver',
            html: '<div class="livreur-marker-icon livreur-marker-icon--driver' + modifierClass + '">' +
                innerHtml + '</div>',
            iconSize: [44, 44],
            iconAnchor: [22, 22],
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
        return getDriverMotoElement();
    }

    function applyDriverHeading(deg, force) {
        if (deg === null || deg === undefined || !isFinite(deg)) return;
        var normalized = normalizeHeading(deg);
        var delta = Math.abs(shortestAngleDiff(driverHeadingDeg, normalized));
        if (!force && delta < 0.4) return;
        driverHeadingDeg = normalized;

        if (shouldRotateMapWithHeading()) {
            setMotoScreenRotation(0);
            smoothSetMapBearing(normalized);
            return;
        }

        ensureNorthUpMap();
        setMotoScreenRotation(normalized);
    }

    function bearingFromMovement(lat, lng) {
        if (lastDriverLat == null || lastDriverLng == null) return null;
        var dLat = lat - lastDriverLat;
        var dLng = lng - lastDriverLng;
        if ((dLat * dLat + dLng * dLng) < 0.000000008) return null;
        return bearingBetween(lastDriverLat, lastDriverLng, lat, lng);
    }

    function resolveHeadingFromPosition(coords, lat, lng) {
        if (coords && coords.speed != null && isFinite(coords.speed)) {
            lastGpsSpeed = coords.speed;
        }

        if (lat != null && lng != null && refreshHeadingFromRoute(lat, lng)) {
            return;
        }

        var moving = lastGpsSpeed != null && isFinite(lastGpsSpeed) && lastGpsSpeed >= 0.5;
        if (lat != null && lng != null && moving) {
            var moveHeading = bearingFromMovement(lat, lng);
            if (moveHeading != null) {
                applyDriverHeading(moveHeading);
                return;
            }
        }

        var heading = coords && coords.heading != null ? coords.heading : null;
        if (heading != null && isFinite(heading) && heading >= 0) {
            applyDriverHeading(heading);
        }
    }

    function restoreMapOverview(animate) {
        if (!driverMarker) {
            return;
        }
        if (isDriverNavMode()) {
            restoreMapToDriver(animate);
            return;
        }
        suppressMapInteractionEvents = true;
        var animateOpt = animate !== false;

        if (clientMarker) {
            var group = L.featureGroup([driverMarker, clientMarker]);
            map.fitBounds(group.getBounds().pad(0.18), {
                animate: animateOpt,
                maxZoom: 16,
                padding: [40, 40]
            });
        } else {
            map.setView(driverMarker.getLatLng(), cfg.defaultZoom || 15, { animate: animateOpt });
        }

        if (mapHasRotation()) {
            cancelBearingAnimation();
            mapBearingDeg = 0;
            map.setBearing(0);
            if (!isObserverMode()) {
                resetDriverMotoRotation();
            }
        }

        setTimeout(function () { suppressMapInteractionEvents = false; }, animateOpt ? 450 : 50);
    }

    function focusMapOnDriver(animate) {
        if (!driverMarker) return;
        if (isDriverNavMode()) {
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
        if (!driverMarker || !mapUserInteracted) {
            return;
        }
        clearAutoRecenterTimer();
        autoRecenterTimer = setTimeout(function () {
            autoRecenterTimer = null;
            if (!mapUserInteracted) {
                return;
            }
            mapUserInteracted = false;
            mapFollowPaused = false;
            updateRecenterButtonState();
            if (isDriverNavMode()) {
                restoreMapToDriver(true);
            } else if (isObserverMode()) {
                restoreMapOverview(true);
            } else {
                restoreMapToDriver(true);
            }
        }, AUTO_RECENTER_MS);
    }

    function onUserMapInteractionStart() {
        if (suppressMapInteractionEvents) {
            return;
        }
        mapUserInteracted = true;
        mapFollowPaused = true;
        clearAutoRecenterTimer();
        updateRecenterButtonState();
    }

    function onUserMapInteraction() {
        if (suppressMapInteractionEvents) {
            return;
        }
        mapUserInteracted = true;
        mapFollowPaused = true;
        updateRecenterButtonState();
        scheduleAutoRecenter();
    }

    function pauseDriverMapFollow() {
        if (!isDriverNavMode()) {
            return;
        }
        onUserMapInteractionStart();
    }

    function resumeDriverMapFollowCountdown() {
        if (!isDriverNavMode()) {
            return;
        }
        onUserMapInteraction();
    }

    function refreshDriverMarkerIcon() {
        if (!driverMarker) return;
        var latlng = driverMarker.getLatLng();
        var heading = driverHeadingDeg;
        var wasExpanded = false;
        var markerEl = driverMarker.getElement();
        if (markerEl) {
            wasExpanded = markerEl.classList.contains('livreur-marker-wrap--expanded');
        }
        driverMarker.setIcon(makeDriverArrowIcon());
        driverMarker.setLatLng(latlng);
        bindDriverMarkerExpand();
        markerEl = driverMarker.getElement();
        if (markerEl && wasExpanded) {
            markerEl.classList.add('livreur-marker-wrap--expanded');
        }
        if (isFinite(heading)) {
            applyDriverHeading(heading, true);
        }
    }

    function applyDriverMapBearing(force) {
        if (!shouldRotateMapWithHeading()) {
            return false;
        }
        if (driverMarker) {
            var ll = driverMarker.getLatLng();
            if (refreshHeadingFromRoute(ll.lat, ll.lng)) {
                return true;
            }
        } else if (activeRouteCoords && activeRouteCoords.length >= 2) {
            var routeHeading = bearingBetween(
                activeRouteCoords[0][0], activeRouteCoords[0][1],
                activeRouteCoords[1][0], activeRouteCoords[1][1]
            );
            applyDriverHeading(routeHeading, !!force);
            return true;
        }
        if (isFinite(driverHeadingDeg)) {
            applyDriverHeading(driverHeadingDeg, !!force);
            return true;
        }
        return false;
    }

    function scheduleDriverMapBearingAfterView(force) {
        if (!shouldRotateMapWithHeading()) {
            return;
        }
        setTimeout(function () {
            applyDriverMapBearing(force);
        }, 60);
    }

    function enableNavigationMode() {
        if (isObserverMode()) {
            ensureNorthUpMap();
            return;
        }
        if (!cfg.canManage) {
            return;
        }
        if (navigationMode) {
            applyDriverMapBearing(true);
            followDriverNavigation(true);
            return;
        }
        setNavigationMode(true);
        resetNavZoomState();
        refreshDriverMarkerIcon();
        applyDriverMapBearing(true);
        followDriverNavigation(false);
        scheduleDriverMapBearingAfterView(true);
    }

    function updateDriverMarker(lat, lng, coords, skipFollow) {
        var rawLat = lat;
        var rawLng = lng;
        var display = getSnappedDriverPosition(rawLat, rawLng);
        lat = display.lat;
        lng = display.lng;
        var prevLat = lastDriverLat;
        var prevLng = lastDriverLng;
        var needNavIcon = shouldShowNavMarker();
        if (driverMarker) {
            driverMarker.setLatLng([lat, lng]);
            var markerEl = driverMarker.getElement();
            if (needNavIcon && markerEl && markerEl.classList &&
                !markerEl.classList.contains('livreur-marker-wrap--nav')) {
                refreshDriverMarkerIcon();
            }
        } else {
            driverMarker = L.marker([lat, lng], {
                icon: makeDriverArrowIcon(),
            }).addTo(map);
            if (!hasDriverPhoto() && !cfg.livreurInitials) {
                driverMarker.bindPopup(driverMarkerLabel());
            }
            bindDriverMarkerExpand();
        }
        resolveHeadingFromPosition(coords || null, lat, lng);
        lastDriverLat = lat;
        lastDriverLng = lng;
        if (!isObserverMode() && (navigationMode || needNavIcon) && !skipFollow) {
            if (!navigationMode && needNavIcon) {
                enableNavigationMode();
            }
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
        activeRouteCoords = [from.slice(), to.slice()];
        lastRouteSegIdx = 0;
        L.polyline([from, to], {
            color: ROUTE_COLOR,
            weight: ROUTE_WEIGHT_FALLBACK,
            opacity: 0.75,
            dashArray: '10, 8'
        }).addTo(layer);
    }

    function getRouteFetchOptions() {
        var opts = {};
        if (cfg.publicWatchToken) {
            opts.token = cfg.publicWatchToken;
        }
        if (cfg.blId) {
            opts.blId = cfg.blId;
        } else if (cfg.commandeId) {
            opts.commandeId = cfg.commandeId;
        }
        return opts;
    }

    function fetchRouteData(driverLat, driverLng, clientLat, clientLng) {
        if (window.LivreurRouteApi && typeof window.LivreurRouteApi.fetchRoute === 'function') {
            return window.LivreurRouteApi.fetchRoute(
                driverLat,
                driverLng,
                clientLat,
                clientLng,
                getRouteFetchOptions()
            );
        }
        return Promise.reject(new Error('route_api_unavailable'));
    }

    function setWatchEtaFromDuration(durationSeconds) {
        if (countdownSync.remainingSec !== null) {
            return;
        }
        if (!etaBlock || !etaRangeEl || !durationSeconds || durationSeconds <= 0) {
            return;
        }
        var range = durationToRangeMinutes(durationSeconds);
        if (etaLabelEl) {
            etaLabelEl.textContent = 'Temps de trajet estimé';
        }
        etaRangeEl.textContent = formatEtaRange(range.min, range.max);
        etaBlock.hidden = false;
        if (cfg.publicMode && document.getElementById('livreur-suivi-app')) {
            document.getElementById('livreur-suivi-app').classList.add('has-live-eta');
        }
    }

    function emitRouteToSocket(routeData) {
        if (!socketClient || !socketClient.connected || isObserverMode() || !cfg.canManage) {
            return;
        }
        if (!routeData || !routeData.coords || routeData.coords.length < 2) {
            return;
        }
        var payload = {
            coords: routeData.coords,
            distance_m: routeData.distance_m || 0,
            duration_s: routeData.duration_s || 0,
        };
        if (cfg.blId) {
            payload.bl_id = cfg.blId;
        } else if (cfg.commandeId) {
            payload.commande_id = cfg.commandeId;
        }
        socketClient.emit('livreur:route', payload);
    }

    function applyRouteFromSocket(data) {
        if (!data || !data.coords || data.coords.length < 2) {
            return;
        }
        var layer = ensureRouteLayer();
        layer.clearLayers();
        L.polyline(data.coords, {
            color: ROUTE_COLOR,
            weight: ROUTE_WEIGHT,
            opacity: 0.92,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(layer);
        activeRouteCoords = data.coords.map(function (pt) {
            return [pt[0], pt[1]];
        });
        lastRouteSegIdx = 0;
        if (isObserverMode() && data.duration_s) {
            setWatchEtaFromDuration(data.duration_s);
        }
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
                    color: ROUTE_COLOR,
                    weight: ROUTE_WEIGHT,
                    opacity: 0.92,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(layer);
                activeRouteCoords = coords.map(function (pt) {
                    return [pt[0], pt[1]];
                });
                lastRouteSegIdx = 0;
                if (driverMarker) {
                    var ll = driverMarker.getLatLng();
                    var routeHeading = bearingFromRoute(ll.lat, ll.lng, activeRouteCoords);
                    if (routeHeading != null) {
                        applyDriverHeading(routeHeading);
                    }
                }
                var km = ((data.distance_m || 0) / 1000).toFixed(1);
                var durationSec = data.duration_s || 0;
                setEtaFromDuration(durationSec);
                emitRouteToSocket(data);
                if (!isObserverMode() && cfg.canManage) {
                    enableNavigationMode();
                }
                var range = durationToRangeMinutes(durationSec);
                if (!silent) {
                    setStatus('Itinéraire sans péage — ' + km + ' km, ' + formatEtaRange(range.min, range.max), 'route');
                }
                return true;
            })
            .catch(function () {
                drawStraightRoute([driverLat, driverLng], [clientLat, clientLng]);
                if (driverMarker) {
                    var ll = driverMarker.getLatLng();
                    var routeHeading = bearingFromRoute(ll.lat, ll.lng, activeRouteCoords);
                    if (routeHeading != null) {
                        applyDriverHeading(routeHeading);
                    }
                }
                var approxSec = estimateStraightDurationSeconds(driverLat, driverLng, clientLat, clientLng);
                setEtaFromDuration(approxSec);
                if (!silent) {
                    setStatus('Itinéraire approximatif (hors ligne)', 'route');
                }
                return false;
            });
    }

    function fitMapBounds() {
        if (isDriverNavMode()) {
            restoreMapToDriver(false);
            return;
        }
        if (driverMarker) {
            restoreMapOverview(false);
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

        if (payload && payload.countdown) {
            applyCountdownFromServer(payload.countdown);
            if (!cfg.trackingActive && isObserverMode() && payload.countdown.paused) {
                handleTrackingPaused(payload.countdown);
            }
        } else if (cfg.initialCountdown) {
            applyCountdownFromServer(cfg.initialCountdown);
            if (!cfg.trackingActive && isObserverMode() && cfg.initialCountdown.paused) {
                handleTrackingPaused(cfg.initialCountdown);
            }
        }

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
                routePromise = drawRoute(driverLat, driverLng, client.lat, client.lng, true);
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
            if (driverMarker && !isObserverMode() && cfg.canManage) {
                enableNavigationMode();
            } else if (isObserverMode()) {
                ensureNorthUpMap();
            }
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

    function confirmDeliveryStarted() {
        setDeliveryActive(true);
        manualDeliveryConfirmed = true;
        updateTrackingButtons();
    }

    function handleAutostartRealtimeFailure() {
        autostartFailed = true;
        manualDeliveryConfirmed = false;
        stopWatch();
        disconnectRealtime();
        clearBackgroundTracking();
        stopNativeDriverTracking().catch(function () { return { success: false }; });
        setDeliveryActive(false);
        cfg.trackingActive = false;
        return rollbackTrackingStart().then(function () {
            setDeliveryStatusRealtime(false);
            setTrackingInactive('Suivi en temps réel inactif — appuyez sur Démarrer la livraison');
            updateTrackingButtons();
            return false;
        });
    }

    function connectRealtimeAfterGpsStart(isManualStart) {
        setDeliveryStatusRealtime(false);
        return beginRealtimeConnection()
            .then(function (connected) {
                if (connected) {
                    confirmDeliveryStarted();
                    return true;
                }
                if (cfg.autostart && !isManualStart) {
                    return handleAutostartRealtimeFailure();
                }
                confirmDeliveryStarted();
                return false;
            })
            .catch(function () {
                if (cfg.autostart && !isManualStart) {
                    return handleAutostartRealtimeFailure();
                }
                confirmDeliveryStarted();
                return false;
            });
    }

    function setDeliveryActive(active) {
        deliveryActive = !!active;
        gpsStreaming = deliveryActive;
        if (deliveryActive) {
            enableNavigationMode();
            requestWakeLock();
        } else {
            releaseWakeLock();
            if (cfg.canManage && !isObserverMode() && activeRouteCoords && activeRouteCoords.length >= 2) {
                enableNavigationMode();
            } else {
                setNavigationMode(false);
            }
        }
    }

    function updateTrackingButtons() {
        if (startBtn) {
            var hideStart = manualDeliveryConfirmed && (deliveryActive || watchId !== null || realtimeConnected);
            if (cfg.autostart && autostartInProgress) {
                hideStart = true;
            }
            startBtn.hidden = hideStart;
            startBtn.disabled = autostartInProgress;
        }
        if (stopBtn) {
            stopBtn.hidden = !manualDeliveryConfirmed || !(deliveryActive || realtimeConnected);
        }
    }

    function setTrackingInactive(subMessage) {
        disconnectRealtime();
        stopWatch();
        clearAutoRecenterTimer();
        releaseWakeLock();
        setNavigationMode(false);
        deliveryActive = false;
        gpsStreaming = false;
        resetOffRouteState();
        manualDeliveryConfirmed = false;
        autostartInProgress = false;
        updateTrackingButtons();
        if (titleEl) {
            titleEl.textContent = STATUS_TITLES.off;
            titleEl.className = 'livreur-suivi-sheet__status-title livreur-suivi-sheet__status-title--off';
        }
        if (statusEl) {
            statusEl.textContent = subMessage || 'Suivi en temps réel inactif';
            statusEl.className = 'livreur-suivi-status livreur-suivi-status--off';
        }
        if (cfg.canManage && !isObserverMode() && activeRouteCoords && activeRouteCoords.length >= 2) {
            setTimeout(enableNavigationMode, 120);
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
                lastSocketError = 'Délai de connexion dépassé';
                disconnectRealtime();
                resolve(false);
            }, timeoutMs || 8000);

            disconnectRealtime();
            var socketUrl = resolveSocketUrl();
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
                realtimeConnected = true;
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

            socketClient.on('route:update', function (data) {
                if (!isObserverMode()) {
                    return;
                }
                applyRouteFromSocket(data);
            });

            socketClient.on('watch:ready', function (data) {
                if (!data) return;
                if (data.tracking_active === true || data.tracking_active === 1) {
                    cfg.trackingActive = true;
                    observerStopped = false;
                    restoreObserverLiveTitle();
                } else if (isObserverMode()) {
                    if (data.countdown) {
                        handleTrackingPaused(data.countdown);
                    } else if (!cfg.trackingActive) {
                        setStatus('En attente du démarrage livreur…', 'pending');
                    }
                } else if ((data.tracking_active === false || data.tracking_active === 0) && cfg.trackingActive) {
                    if (data.countdown) {
                        handleTrackingPaused(data.countdown);
                    } else {
                        handleTrackingEnded();
                    }
                    return;
                }
                if (data.countdown) {
                    applyCountdownFromServer(data.countdown);
                }
                if (data.last_position) {
                    applyRemoteDriverPosition(data.last_position);
                }
            });

            socketClient.on('disconnect', function () {
                var wasRealtime = realtimeConnected;
                realtimeConnected = false;
                if (isObserverMode()) {
                    restartPositionPolling();
                    if (wasRealtime && cfg.trackingActive && cfg.realtimeConfigured) {
                        setStatus('Reconnexion au suivi en direct…', 'pending');
                        setTimeout(function () {
                            ensureObserverRealtimeConnection();
                        }, 1200);
                    }
                } else if (deliveryActive && wasRealtime) {
                    setDeliveryStatusRealtime(false);
                }
            });
        });
    }

    function ensureObserverRealtimeConnection() {
        if (!isObserverMode() || !cfg.realtimeConfigured || realtimeConnected || observerSocketConnecting) {
            return Promise.resolve(false);
        }
        if (typeof io === 'undefined') {
            return Promise.resolve(false);
        }
        observerSocketConnecting = true;
        return beginRealtimeConnection()
            .then(function (connected) {
                observerSocketConnecting = false;
                if (connected) {
                    setDeliveryStatusRealtime(true);
                    restartPositionPolling();
                    if (cfg.trackingActive) {
                        setStatus('Suivi en temps réel actif', 'live');
                    }
                }
                return connected;
            })
            .catch(function () {
                observerSocketConnecting = false;
                return false;
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
        if (!socketClient || !socketClient.connected) {
            return;
        }
        if (!deliveryActive && !gpsStreaming && !cfg.trackingActive) {
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
        }).catch(function () { /* silencieux — prochaine position réessaiera */ });
    }

    function stopWatch() {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    function startWatchStream() {
        if (nativeDriverTracking) {
            return true;
        }
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
                maybeRecalculateRoute(lat, lng, false, pos.coords);
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
                return tryConnectRealtime(token, 8000);
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

    function startTracking(options) {
        options = options || {};
        var isManualStart = options.manual === true;
        if (!cfg.canManage) {
            showTrackingAlert({
                title: 'Accès refusé',
                message: 'Vous ne pouvez pas démarrer cette livraison.',
                details: ['Seul le livreur assigné peut lancer le suivi', 'Reconnectez-vous avec le bon compte'],
            });
            return Promise.reject(trackingError('Accès refusé', 'Vous ne pouvez pas démarrer cette livraison.'));
        }
        if (!cfg.geoReady) {
            showTrackingAlert({
                title: 'Adresse non localisée',
                message: 'L\'adresse client n\'est pas géolocalisée.',
                details: ['Retournez à la liste et relancez la livraison avec une adresse valide'],
            });
            return Promise.reject(trackingError('Adresse non localisée', 'L\'adresse client n\'est pas géolocalisée.'));
        }
        if (deliveryActive && (realtimeConnected || watchId !== null) && manualDeliveryConfirmed) {
            return Promise.resolve(true);
        }
        if (startBtn) startBtn.setAttribute('disabled', 'disabled');

        if (deliveryActive && watchId !== null && manualDeliveryConfirmed) {
            return beginRealtimeConnection().finally(function () {
                if (startBtn) startBtn.removeAttribute('disabled');
            });
        }

        setStatus('Activation du suivi GPS…', 'pending');
        var deferConfirm = cfg.autostart && !isManualStart;

        return callWebApi({ action: 'start' })
            .then(function (data) {
                cfg.trackingActive = true;
                if (data && data.countdown) {
                    applyCountdownFromServer(data.countdown);
                }
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
                if (!deferConfirm) {
                    confirmDeliveryStarted();
                }
                return startNativeDriverTracking().then(function (nativeOk) {
                    if (!nativeOk) {
                        syncBackgroundTracking(true);
                        if (!startWatchStream()) {
                            throw trackingError(
                                'Flux GPS',
                                'Impossible de démarrer le suivi continu de position.',
                                ['Vérifiez les autorisations GPS', 'Réessayez avec un autre navigateur']
                            );
                        }
                    }
                    return connectRealtimeAfterGpsStart(isManualStart);
                });
            })
            .catch(function (err) {
                autostartFailed = true;
                showTrackingAlertFromError(err);
                return rollbackTrackingStart().finally(function () {
                    setTrackingInactive('Suivi en temps réel inactif');
                }).then(function () {
                    throw err;
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

        stopNativeDriverTracking()
            .catch(function () { return { success: false }; })
            .then(function () {
                return callWebApi({ action: 'terminer' });
            })
            .then(function () {
                clearBackgroundTracking();
                setDeliveryActive(false);
                manualDeliveryConfirmed = false;
                setDeliveryStatusRealtime(false);
                setStatus('Livraison terminée', 'off');
                if (cfg.indexUrl) {
                    var backUrl = cfg.indexUrl;
                    if (/livreurs\/index\.php|^index\.php$/i.test(backUrl.replace(/^\.\.\//, ''))) {
                        backUrl += (backUrl.indexOf('?') >= 0 ? '&' : '?') + 'terminee=1';
                    }
                    window.location.href = backUrl;
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

        if (isDriverNavMode()) {
            refreshNavZoomFromSpeed(lastGpsSpeed);
            var pad = getNavigationPadding();
            var zoom = getDriverNavZoom();
            map.setView(driverLatLng, zoom, {
                animate: animateOpt,
                paddingTopLeft: pad.topLeft,
                paddingBottomRight: pad.bottomRight
            });
            lastAppliedNavZoom = zoom;
            scheduleDriverMapBearingAfterView(true);
        } else {
            map.setView(driverLatLng, getDriverNavZoom(), { animate: animateOpt });
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
        mapUserInteracted = false;
        mapFollowPaused = false;
        updateRecenterButtonState();
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
                    ensureNorthUpMap();
                    if (driverMarker && cfg.trackingActive) {
                        restoreMapOverview(true);
                        setStatus(
                            realtimeConnected ? 'Suivi en temps réel actif' : 'Position actualisée',
                            realtimeConnected ? 'live' : 'route'
                        );
                    } else if (driverMarker) {
                        restoreMapOverview(true);
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

        if (zoomIn) {
            zoomIn.addEventListener('click', function () {
                pauseDriverMapFollow();
                map.zoomIn();
                resumeDriverMapFollowCountdown();
            });
        }
        if (zoomOut) {
            zoomOut.addEventListener('click', function () {
                pauseDriverMapFollow();
                map.zoomOut();
                resumeDriverMapFollowCountdown();
            });
        }
        if (fitBtn) fitBtn.addEventListener('click', recenterMapOnDriver);

        map.on('dragstart', onUserMapInteractionStart);
        map.on('zoomstart', onUserMapInteractionStart);
        map.on('touchstart', onUserMapInteractionStart);
        map.on('mousedown', onUserMapInteractionStart);
        map.on('dragend', onUserMapInteraction);
        map.on('zoomend', onUserMapInteraction);
        map.on('touchend', onUserMapInteraction);

        updateRecenterButtonState();

        window.addEventListener('resize', function () {
            setTimeout(function () { map.invalidateSize(); }, 120);
        });

        setTimeout(function () { map.invalidateSize(); }, 200);
    }

    function bindTrackingButtons() {
        if (startBtn) {
            startBtn.addEventListener('click', function () {
                showLoadingOverlay('Activation du suivi GPS…');
                startTracking({ manual: true })
                    .catch(function () { /* alerte déjà affichée */ })
                    .finally(hideLoadingOverlay);
            });
        }
        bindStopConfirmation();
        setDeliveryActive(false);
        realtimeConnected = false;
        updateTrackingButtons();
    }

    function bindStopConfirmation() {
        if (!stopBtn || !confirmStopEl) {
            return;
        }

        function closeConfirmStop() {
            confirmStopEl.hidden = true;
            document.body.classList.remove('livreur-suivi-confirm-open');
        }

        stopBtn.addEventListener('click', function () {
            confirmStopEl.hidden = false;
            document.body.classList.add('livreur-suivi-confirm-open');
        });

        if (confirmStopNoBtn) {
            confirmStopNoBtn.addEventListener('click', closeConfirmStop);
        }
        confirmStopEl.querySelectorAll('[data-livreur-confirm-close]').forEach(function (el) {
            el.addEventListener('click', closeConfirmStop);
        });
        if (confirmStopYesBtn) {
            confirmStopYesBtn.addEventListener('click', function () {
                closeConfirmStop();
                stopTracking();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !confirmStopEl.hidden) {
                closeConfirmStop();
            }
        });
    }

    function resumeActiveTracking(options) {
        options = options || {};
        var isManualStart = options.manual === true;
        if (!cfg.trackingActive || !cfg.canManage) {
            return Promise.resolve(false);
        }
        var deferConfirm = cfg.autostart && !isManualStart && !manualDeliveryConfirmed;
        if (!deferConfirm) {
            confirmDeliveryStarted();
        }
        return startNativeDriverTracking().then(function (nativeOk) {
            if (!nativeOk && !startWatchStream()) {
                autostartFailed = true;
                return handleAutostartRealtimeFailure().then(function () {
                    showTrackingAlert({
                        title: 'Reprise impossible',
                        message: 'Le GPS n\'a pas pu reprendre le suivi en cours.',
                        details: ['Autorisez la géolocalisation', 'Appuyez sur Démarrer la livraison pour réessayer'],
                    });
                    return Promise.reject(trackingError('Reprise impossible', 'Le GPS n\'a pas pu reprendre le suivi en cours.'));
                });
            }
            if (!nativeOk) {
                syncBackgroundTracking(true);
            }
            return connectRealtimeAfterGpsStart(isManualStart);
        });
    }

    function runDriverAutostart() {
        if (!shouldAutoStartDriver()) {
            return Promise.resolve();
        }
        autostartInProgress = true;
        autostartFailed = false;
        updateTrackingButtons();
        setLoadingOverlayMessage('Activation du suivi GPS…');

        var trackingPromise;
        if (deliveryActive && (watchId !== null || realtimeConnected)) {
            trackingPromise = beginRealtimeConnection();
        } else if (cfg.trackingActive) {
            trackingPromise = resumeActiveTracking();
        } else {
            trackingPromise = startTracking();
        }

        return trackingPromise
            .then(function (started) {
                autostartInProgress = false;
                if (started !== false) {
                    autostartFailed = false;
                }
                updateTrackingButtons();
            })
            .catch(function (err) {
                autostartInProgress = false;
                if (!manualDeliveryConfirmed) {
                    autostartFailed = true;
                }
                updateTrackingButtons();
                throw err;
            });
    }

    function autoStartTrackingIfNeeded() {
        if (cfg.watchOnly || cfg.publicMode) {
            startWatchObserverMode();
            return Promise.resolve();
        }
        if (!cfg.canManage) {
            return Promise.resolve();
        }
        return runDriverAutostart();
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
                    if (data.countdown) {
                        if (cfg.trackingActive) {
                            handleTrackingPaused(data.countdown);
                        } else {
                            applyCountdownFromServer(data.countdown);
                        }
                        return;
                    }
                    if (cfg.trackingActive) {
                        handleTrackingEnded();
                    }
                    return;
                }
                var trackingJustStarted = !cfg.trackingActive;
                cfg.trackingActive = true;
                observerStopped = false;
                if (trackingJustStarted && isObserverMode()) {
                    restoreObserverLiveTitle();
                    ensureObserverRealtimeConnection();
                }
                if (data.countdown) {
                    applyCountdownFromServer(data.countdown);
                }
                if (data.last_position) {
                    applyRemoteDriverPosition(data.last_position);
                } else if (isObserverMode()) {
                    setStatus('En attente de la position du livreur…', 'pending');
                }
            })
            .catch(function () { /* silencieux */ });
    }

    function getPositionPollIntervalMs() {
        if (!isObserverMode()) {
            return POSITION_POLL_NORMAL_MS;
        }
        if (!realtimeConnected) {
            return POSITION_POLL_FAST_MS;
        }
        if (!lastRemotePositionAt || (Date.now() - lastRemotePositionAt) > 8000) {
            return POSITION_POLL_FAST_MS;
        }
        return POSITION_POLL_NORMAL_MS;
    }

    function restartPositionPolling() {
        if (!isObserverMode()) {
            return;
        }
        if (positionPollTimer) {
            clearInterval(positionPollTimer);
            positionPollTimer = null;
        }
        var interval = getPositionPollIntervalMs();
        positionPollTimer = setInterval(function () {
            fetchLastPositionUpdate().finally(function () {
                if (!positionPollTimer) {
                    return;
                }
                var next = getPositionPollIntervalMs();
                if (next !== interval) {
                    restartPositionPolling();
                }
            });
        }, interval);
        fetchLastPositionUpdate();
    }

    function startPositionPolling() {
        restartPositionPolling();
    }

    function startWatchObserverMode() {
        if (!cfg.geoReady) {
            setStatus('Adresse client non géolocalisée', 'error');
        } else {
            setStatus('Connexion au suivi en direct…', 'pending');
        }
        ensureNorthUpMap();
        if (mapEl) {
            mapEl.classList.add('livreur-tracking-map--watch-mode');
        }
        restartPositionPolling();
        beginRealtimeConnection()
            .then(function (connected) {
                restartPositionPolling();
                if (connected) {
                    setDeliveryStatusRealtime(true);
                    if (cfg.trackingActive) {
                        setStatus('Suivi en temps réel actif', 'live');
                    } else {
                        setStatus('En attente du démarrage livreur…', 'pending');
                    }
                } else if (cfg.trackingActive) {
                    setStatus('Actualisation toutes les ' + (POSITION_POLL_FAST_MS / 1000) + ' s', 'ok');
                } else {
                    setStatus('En attente du démarrage livreur…', 'pending');
                }
                return connected;
            })
            .catch(function () {
                restartPositionPolling();
                if (cfg.trackingActive) {
                    setStatus('Actualisation périodique de la position', 'ok');
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
                compact.hidden = true;
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
    var wakeLockSentinel = null;

    function releaseWakeLock() {
        if (!wakeLockSentinel) {
            return;
        }
        wakeLockSentinel.release().catch(function () { /* silencieux */ });
        wakeLockSentinel = null;
    }

    function requestWakeLock() {
        if (!cfg.enableBackgroundTracking || !deliveryActive) {
            return;
        }
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') {
            return;
        }
        if (wakeLockSentinel) {
            return;
        }
        navigator.wakeLock.request('screen').then(function (sentinel) {
            wakeLockSentinel = sentinel;
            sentinel.addEventListener('release', function () {
                if (wakeLockSentinel === sentinel) {
                    wakeLockSentinel = null;
                }
            });
        }).catch(function () { /* silencieux */ });
    }

    function bindBackgroundTracking() {
        if (!cfg.enableBackgroundTracking) {
            return;
        }
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                if (deliveryActive && watchId === null) {
                    startWatchStream();
                }
                requestWakeLock();
                return;
            }
            /* En arrière-plan : on garde le flux GPS actif (watchId) */
        });
        window.addEventListener('pagehide', function () {
            releaseWakeLock();
            if (deliveryActive || cfg.trackingActive) {
                syncBackgroundTracking(true);
            }
        });
        window.addEventListener('beforeunload', function () {
            releaseWakeLock();
            if (deliveryActive || cfg.trackingActive) {
                syncBackgroundTracking(true);
            }
        });
    }

    function updateMyDeliveriesUi() {
        var deliveries = Array.isArray(cfg.myDeliveries) ? cfg.myDeliveries : [];
        var count = deliveries.length;
        var countEl = document.querySelector('.livreur-suivi-sheet__switch-count');
        var toolbar = document.querySelector('.livreur-suivi-sheet__toolbar');
        var openBtn = document.getElementById('livreur-switch-open');
        if (countEl) {
            countEl.textContent = String(count);
        }
        if (toolbar && cfg.canManage) {
            toolbar.hidden = false;
        } else if (toolbar) {
            toolbar.hidden = count < 1;
        }
        if (openBtn) {
            openBtn.disabled = false;
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
                empty.textContent = 'Aucune livraison en cours assignée à votre compte.';
                listEl.appendChild(empty);
                return;
            }

            deliveries.forEach(function (item) {
                var key = deliveryItemKey(item);
                var isCurrent = key === cfg.currentDeliveryKey;
                var li = document.createElement('li');
                li.className = 'livreur-switch-item-wrap' + (isCurrent ? ' is-current' : '');

                var card = document.createElement('div');
                card.className = 'livreur-switch-item';

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
                    badges += '<span class="livreur-switch-item__badge livreur-switch-item__badge--current">Affichée</span>';
                }

                card.innerHTML =
                    '<div class="livreur-switch-item__body">' +
                    '<div class="livreur-switch-item__top">' +
                    '<span class="livreur-switch-item__ref">' + escapeHtml(numero) + '</span>' +
                    '<span class="livreur-switch-item__badges">' + badges + '</span>' +
                    '</div>' +
                    '<p class="livreur-switch-item__client">' + escapeHtml(client) + '</p>' +
                    (tel ? '<p class="livreur-switch-item__meta"><i class="fas fa-phone" aria-hidden="true"></i> ' + escapeHtml(tel) + '</p>' : '') +
                    (adresse ? '<p class="livreur-switch-item__meta"><i class="fas fa-location-dot" aria-hidden="true"></i> ' + escapeHtml(adresse) + '</p>' : '') +
                    '</div>';

                if (isCurrent) {
                    var currentNote = document.createElement('p');
                    currentNote.className = 'livreur-switch-item__current-note';
                    currentNote.textContent = 'Livraison actuellement affichée';
                    card.appendChild(currentNote);
                    card.classList.add('is-current');
                } else {
                    var continueBtn = document.createElement('button');
                    continueBtn.type = 'button';
                    continueBtn.className = 'livreur-switch-item__continue';
                    continueBtn.textContent = 'Continuer la livraison';
                    continueBtn.addEventListener('click', function () {
                        if (item.suivi_url) {
                            window.location.href = item.suivi_url;
                        }
                    });
                    card.appendChild(continueBtn);
                }

                li.appendChild(card);
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
    if (cfg.canManage) {
        bindSwitchDeliveryModal();
        startMyDeliveriesPolling();
        bindBackgroundTracking();
        if (cfg.trackingActive) {
            if (isNativeDriverTrackingAvailable()) {
                startNativeDriverTracking();
            } else {
                syncBackgroundTracking(true);
            }
        }
    }
    bindMapControls();
    bindTrackingButtons();
    bindShareDelivery();
    if (cfg.initialCountdown) {
        applyCountdownFromServer(cfg.initialCountdown);
    }
    setStatus('Chargement de l\'itinéraire…', 'pending');
    showLoadingOverlay('Chargement de l\'itinéraire…');

    loadTrackingBootstrap()
        .then(function (payload) {
            setLoadingOverlayMessage('Calcul de l\'itinéraire…');
            return setupItinerary(payload);
        })
        .then(function () {
            if (cfg.watchOnly || cfg.publicMode) {
                return autoStartTrackingIfNeeded();
            }
            if (shouldAutoStartDriver()) {
                setLoadingOverlayMessage('Activation du suivi en temps réel…');
                return runDriverAutostart();
            }
            return Promise.resolve();
        })
        .catch(function (err) {
            if (cfg.watchOnly || cfg.publicMode) {
                var client = getClientCoords();
                if (client.lat !== null && client.lng !== null) {
                    setClientMarker(client.lat, client.lng);
                    setStatus('Suivi chargé — position livreur en attente', 'pending');
                    return autoStartTrackingIfNeeded();
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
                }).then(function () {
                    if (shouldAutoStartDriver()) {
                        setLoadingOverlayMessage('Activation du suivi en temps réel…');
                        return runDriverAutostart();
                    }
                    return fitMapBounds();
                });
            }
            setStatus('Impossible de charger l\'itinéraire : ' + err.message, 'error');
        })
        .finally(function () {
            hideLoadingOverlay();
        });
})();
