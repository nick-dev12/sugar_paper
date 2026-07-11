/**
 * Page iframe — GPS livreur en arrière-plan (admin)
 */
(function () {
    'use strict';

    var cfg = window.LIVREUR_BG_PAGE_CONFIG;
    if (!cfg || (!cfg.commandeId && !cfg.blId)) {
        return;
    }

    var watchId = null;
    var socketClient = null;
    var lastPostAt = 0;
    var lastStatusCheckAt = 0;
    var wakeLockSentinel = null;

    function apiBody(extra) {
        var body = { action: extra.action };
        if (cfg.commandeId) {
            body.commande_id = cfg.commandeId;
        }
        if (cfg.blId) {
            body.bl_id = cfg.blId;
        }
        if (extra.latitude != null) {
            body.latitude = extra.latitude;
        }
        if (extra.longitude != null) {
            body.longitude = extra.longitude;
        }
        if (extra.accuracy != null) {
            body.accuracy = extra.accuracy;
        }
        return body;
    }

    function callWebApi(extra) {
        return fetch(cfg.webApiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(apiBody(extra)),
            cache: 'no-store'
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data || !data.success) {
                    throw new Error((data && data.message) ? data.message : 'api_error');
                }
                return data;
            });
        });
    }

    function emitSocket(lat, lng, coords) {
        if (!socketClient || !socketClient.connected) {
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
        emitSocket(lat, lng, coords || { accuracy: accuracy });
        if (now - lastPostAt < 4000) {
            return;
        }
        lastPostAt = now;
        callWebApi({
            action: 'position',
            latitude: lat,
            longitude: lng,
            accuracy: accuracy
        }).catch(function () { /* silencieux */ });
    }

    function stopAll() {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        if (socketClient) {
            socketClient.disconnect();
            socketClient = null;
        }
        if (wakeLockSentinel) {
            wakeLockSentinel.release().catch(function () { /* ignore */ });
            wakeLockSentinel = null;
        }
        if (window.LivreurBgTracker && typeof window.LivreurBgTracker.clearSession === 'function') {
            window.LivreurBgTracker.clearSession();
        }
    }

    function requestWakeLock() {
        if (!('wakeLock' in navigator)) {
            return;
        }
        if (wakeLockSentinel || document.visibilityState !== 'visible') {
            return;
        }
        navigator.wakeLock.request('screen').then(function (sentinel) {
            wakeLockSentinel = sentinel;
        }).catch(function () { /* ignore */ });
    }

    function fetchWatchToken() {
        if (!cfg.watchTokenUrl) {
            return Promise.reject(new Error('no_watch_token_url'));
        }
        return fetch(cfg.watchTokenUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success || !data.watch_token) {
                    throw new Error('watch_token_failed');
                }
                return data.watch_token;
            });
    }

    function connectSocket(token) {
        if (!cfg.realtimeConfigured || typeof io === 'undefined') {
            return Promise.resolve(false);
        }
        return new Promise(function (resolve) {
            var settled = false;
            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                resolve(false);
            }, 12000);

            socketClient = io(cfg.socketUrl || window.location.origin, {
                path: cfg.socketPath || '/socket.io',
                transports: ['polling'],
                upgrade: false,
                reconnection: true,
                timeout: 12000,
                auth: {
                    role: 'watch',
                    token: token,
                    commande_id: cfg.commandeId || 0,
                    bl_id: cfg.blId || 0
                }
            });

            socketClient.on('connect', function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                resolve(true);
            });

            socketClient.on('connect_error', function () {
                /* retry via socket.io */
            });
        });
    }

    function checkStillActive() {
        var now = Date.now();
        if (now - lastStatusCheckAt < 20000) {
            return Promise.resolve(true);
        }
        lastStatusCheckAt = now;
        var url = cfg.statusUrl;
        if (!url) {
            return Promise.resolve(true);
        }
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.success || !data.tracking_active) {
                    stopAll();
                    return false;
                }
                return true;
            })
            .catch(function () {
                return true;
            });
    }

    function startWatch() {
        if (!navigator.geolocation) {
            return;
        }
        if (watchId !== null) {
            return;
        }
        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                checkStillActive();
                postPosition(
                    pos.coords.latitude,
                    pos.coords.longitude,
                    pos.coords.accuracy,
                    pos.coords
                );
            },
            function () { /* GPS error — on réessaie au prochain cycle */ },
            { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 }
        );
        requestWakeLock();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            startWatch();
            requestWakeLock();
        }
    });

    fetchWatchToken()
        .then(function (token) {
            return connectSocket(token).then(function () {
                return token;
            });
        })
        .catch(function () { /* socket optionnel */ })
        .finally(function () {
            startWatch();
        });
})();
