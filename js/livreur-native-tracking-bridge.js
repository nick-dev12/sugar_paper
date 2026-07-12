/**
 * Pont suivi livraison — app Flutter Sugar Paper (GPS natif arrière-plan)
 */
(function (global) {
    'use strict';

    function getCfg() {
        return global.LIVREUR_TRACKING_CONFIG || null;
    }

    function isNativeApp() {
        return !!(global.SugarPaperNative &&
            typeof global.SugarPaperNative.isNativeApp === 'function' &&
            global.SugarPaperNative.isNativeApp());
    }

    function buildStatusUrl(cfg) {
        var base = cfg.lastPositionUrl || '/api/tracking/last-position.php';
        var params = new URLSearchParams();
        if (cfg.blId) {
            params.set('bl_id', String(cfg.blId));
        } else if (cfg.commandeId) {
            params.set('commande_id', String(cfg.commandeId));
        }
        return base + '?' + params.toString();
    }

    function buildNativeConfig(cfg) {
        return {
            blId: cfg.blId || 0,
            commandeId: cfg.commandeId || 0,
            siteOrigin: global.location.origin,
            webApiUrl: cfg.webApiUrl || '/api/tracking/livreur-web.php',
            watchTokenUrl: cfg.watchTokenUrl || '',
            statusUrl: buildStatusUrl(cfg),
            socketUrl: cfg.socketUrl || global.location.origin,
            socketPath: cfg.socketPath || '/socket.io',
            realtimeConfigured: !!cfg.realtimeConfigured
        };
    }

    function callNative(method, payload) {
        if (!global.SugarPaperNative || typeof global.SugarPaperNative[method] !== 'function') {
            return Promise.resolve({ success: false, error: 'native_unavailable' });
        }
        return global.SugarPaperNative[method](payload).catch(function (err) {
            return { success: false, error: (err && err.message) ? err.message : 'native_error' };
        });
    }

    global.LivreurNativeTracking = {
        isAvailable: function () {
            var cfg = getCfg();
            return !!(cfg && cfg.canManage && isNativeApp());
        },
        start: function () {
            var cfg = getCfg();
            if (!cfg || !this.isAvailable()) {
                return Promise.resolve({ success: false, error: 'unavailable' });
            }
            return callNative('startDeliveryTracking', buildNativeConfig(cfg));
        },
        stop: function () {
            if (!isNativeApp()) {
                return Promise.resolve({ success: false, error: 'unavailable' });
            }
            return callNative('stopDeliveryTracking');
        },
        status: function () {
            if (!isNativeApp()) {
                return Promise.resolve({ success: false, active: false });
            }
            return callNative('getDeliveryTrackingStatus');
        }
    };
})(window);
