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
        if (lastSocketError) {
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
        attributionControl: true
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
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.className = 'livreur-suivi-status livreur-suivi-status--' + kind;
        }
    }

    function makeDivIcon(className, faClass) {
        return L.divIcon({
            className: '',
            html: '<div class="livreur-marker-icon ' + className + '"><i class="fas ' + faClass + '"></i></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 17],
        });
    }

    function updateDriverMarker(lat, lng) {
        if (driverMarker) {
            driverMarker.setLatLng([lat, lng]);
        } else {
            driverMarker = L.marker([lat, lng], {
                icon: makeDivIcon('livreur-marker-icon--driver', 'fa-motorcycle'),
            }).addTo(map).bindPopup('Vous');
        }
    }

    function setClientMarker(lat, lng) {
        if (clientMarker) {
            clientMarker.setLatLng([lat, lng]);
            return;
        }
        clientMarker = L.marker([lat, lng], {
            icon: makeDivIcon('livreur-marker-icon--client', 'fa-house'),
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
        var layers = [];
        if (driverMarker) layers.push(driverMarker);
        if (clientMarker) layers.push(clientMarker);
        if (layers.length === 1) {
            map.setView(layers[0].getLatLng(), 15);
        } else if (layers.length > 1) {
            var group = L.featureGroup(layers);
            map.fitBounds(group.getBounds().pad(0.22));
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
                        accuracy: pos.coords.accuracy
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
            updateDriverMarker(driverLat, driverLng);
        }

        var routePromise = Promise.resolve();
        if (client.lat !== null && client.lng !== null) {
            if (driverLat !== null && driverLng !== null) {
                routePromise = drawRoute(driverLat, driverLng, client.lat, client.lng);
            } else {
                routePromise = refreshFromGeolocation().then(function (pos) {
                    if (!pos) {
                        setStatus('Autorisez le GPS pour afficher l\'itinéraire', 'off');
                        fitMapBounds();
                        return false;
                    }
                    updateDriverMarker(pos.lat, pos.lng);
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
    }

    function updateTrackingButtons() {
        if (startBtn) startBtn.hidden = realtimeConnected;
        if (stopBtn) stopBtn.hidden = !realtimeConnected;
    }

    function setTrackingInactive(subMessage) {
        disconnectRealtime();
        stopWatch();
        setDeliveryActive(false);
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
            }, timeoutMs || 8000);

            disconnectRealtime();
            var socketUrl = cfg.socketUrl || window.location.origin;
            socketClient = io(socketUrl, {
                path: cfg.socketPath || '/socket.io',
                transports: ['websocket', 'polling'],
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
                resolve(true);
            });

            socketClient.on('connect_error', function (err) {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                lastSocketError = (err && err.message) ? err.message : 'Connexion refusée';
                disconnectRealtime();
                resolve(false);
            });

            socketClient.on('position:update', function (data) {
                if (!data || data.latitude == null || data.longitude == null) return;
                updateDriverMarker(parseFloat(data.latitude), parseFloat(data.longitude));
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

    function postPosition(lat, lng, accuracy) {
        var now = Date.now();
        if (now - lastPostAt < 4000) return;
        lastPostAt = now;
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
                updateDriverMarker(lat, lng);
                postPosition(lat, lng, pos.coords.accuracy);
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
                return tryConnectRealtime(token, 8000);
            })
            .then(function (connected) {
                setDeliveryStatusRealtime(connected);
                if (!connected) {
                    showTrackingAlert(getRealtimeFailureAlert());
                }
                return connected;
            })
            .catch(function (err) {
                setDeliveryStatusRealtime(false);
                showTrackingAlertFromError(err);
                throw err;
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
                updateDriverMarker(pos.coords.latitude, pos.coords.longitude);
                postPosition(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
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

    function refreshLivePosition() {
        setStatus('Actualisation…', 'pending');
        refreshFromGeolocation().then(function (pos) {
            if (pos) {
                updateDriverMarker(pos.lat, pos.lng);
                if (gpsStreaming) {
                    postPosition(pos.lat, pos.lng, pos.accuracy);
                }
                var client = getClientCoords();
                if (client.lat !== null && client.lng !== null) {
                    drawRoute(pos.lat, pos.lng, client.lat, client.lng).then(fitMapBounds);
                } else {
                    fitMapBounds();
                }
                setStatus(
                    realtimeConnected ? 'Suivi en temps réel actif' : 'Livraison en cours — suivi temps réel inactif',
                    realtimeConnected ? 'live' : 'off'
                );
            } else {
                setStatus('Position GPS indisponible', 'off');
            }
        });
    }

    function bindMapControls() {
        var zoomIn = document.getElementById('livreur-map-zoom-in');
        var zoomOut = document.getElementById('livreur-map-zoom-out');
        var fitBtn = document.getElementById('livreur-map-fit');

        if (zoomIn) zoomIn.addEventListener('click', function () { map.zoomIn(); });
        if (zoomOut) zoomOut.addEventListener('click', function () { map.zoomOut(); });
        if (fitBtn) fitBtn.addEventListener('click', refreshLivePosition);

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

    bindTrackingAlert();
    bindMapControls();
    bindTrackingButtons();
    setStatus('Chargement de l\'itinéraire…', 'pending');

    fetch(cfg.watchTokenUrl, { credentials: 'same-origin', cache: 'no-store' })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.message || 'Données indisponibles');
            }
            return setupItinerary(payload).then(function () {
                resumeActiveTracking();
            });
        })
        .catch(function (err) {
            var client = getClientCoords();
            if (client.lat !== null && client.lng !== null) {
                setClientMarker(client.lat, client.lng);
                return refreshFromGeolocation().then(function (pos) {
                    if (pos) {
                        updateDriverMarker(pos.lat, pos.lng);
                        return drawRoute(pos.lat, pos.lng, client.lat, client.lng);
                    }
                    setStatus('Itinéraire partiel — ' + err.message, 'off');
                }).then(fitMapBounds);
            }
            setStatus('Impossible de charger l\'itinéraire : ' + err.message, 'error');
        });
})();
