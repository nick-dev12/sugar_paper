/**
 * Suivi GPS livreur en arrière-plan — session + iframe launcher (admin)
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'livreur_bg_tracking_v1';
    var IFRAME_ID = 'livreur-bg-tracker-frame';

    function readSession() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || (!data.blId && !data.commandeId)) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function writeSession(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) { /* ignore */ }
        scheduleLauncher();
    }

    function clearSession() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
        removeBackgroundIframe();
    }

    function sessionKey(data) {
        if (!data) {
            return '';
        }
        if (data.blId) {
            return 'bl-' + data.blId;
        }
        return 'cmd-' + data.commandeId;
    }

    function isSuiviPageForSession(data) {
        if (!data || !global.location || !global.location.pathname) {
            return false;
        }
        if (global.location.pathname.indexOf('/admin/livreurs/suivi.php') === -1) {
            return false;
        }
        var params = new URLSearchParams(global.location.search || '');
        if (data.blId && params.get('bl_id') === String(data.blId)) {
            return true;
        }
        if (data.commandeId && params.get('commande_id') === String(data.commandeId)) {
            return true;
        }
        return false;
    }

    function buildBackgroundUrl(data) {
        var base = '/admin/livreurs/tracking-background.php';
        var qs = data.blId ? ('bl_id=' + encodeURIComponent(String(data.blId))) : ('commande_id=' + encodeURIComponent(String(data.commandeId)));
        return base + '?' + qs;
    }

    function removeBackgroundIframe() {
        var frame = document.getElementById(IFRAME_ID);
        if (frame && frame.parentNode) {
            frame.parentNode.removeChild(frame);
        }
    }

    function ensureBackgroundIframe() {
        if (global.__livreurBgTrackerDisabled || global.self !== global.top) {
            return;
        }
        var session = readSession();
        if (!session) {
            removeBackgroundIframe();
            return;
        }
        if (global.location.pathname.indexOf('/admin/livreurs/suivi.php') !== -1) {
            removeBackgroundIframe();
            return;
        }

        var frame = document.getElementById(IFRAME_ID);
        var targetUrl = buildBackgroundUrl(session);
        if (frame) {
            if (frame.getAttribute('data-session-key') !== sessionKey(session)) {
                frame.src = targetUrl;
                frame.setAttribute('data-session-key', sessionKey(session));
            }
            return;
        }
        frame = document.createElement('iframe');
        frame.id = IFRAME_ID;
        frame.title = 'Suivi GPS livreur';
        frame.setAttribute('aria-hidden', 'true');
        frame.setAttribute('data-session-key', sessionKey(session));
        frame.src = targetUrl;
        frame.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;z-index:-1';
        (document.body || document.documentElement).appendChild(frame);
    }

    var launcherTimer = null;
    function scheduleLauncher() {
        if (launcherTimer) {
            clearTimeout(launcherTimer);
        }
        launcherTimer = setTimeout(function () {
            launcherTimer = null;
            ensureBackgroundIframe();
        }, 80);
    }

    function saveSession(cfg) {
        if (!cfg || (!cfg.blId && !cfg.commandeId)) {
            return;
        }
        writeSession({
            blId: cfg.blId || 0,
            commandeId: cfg.commandeId || 0,
            livraisonType: cfg.livraisonType || (cfg.blId ? 'facture' : 'commande'),
            webApiUrl: cfg.webApiUrl || '/api/tracking/livreur-web.php',
            watchTokenUrl: cfg.watchTokenUrl || '',
            socketUrl: cfg.socketUrl || '',
            socketPath: cfg.socketPath || '/socket.io',
            realtimeConfigured: !!cfg.realtimeConfigured,
            startedAt: Date.now()
        });
    }

    function syncFromConfig(cfg, trackingActive) {
        if (!cfg || !cfg.canManage) {
            return;
        }
        if (trackingActive) {
            saveSession(cfg);
        } else {
            var session = readSession();
            if (session && isSuiviPageForSession(session)) {
                clearSession();
            }
        }
    }

    global.LivreurBgTracker = {
        saveSession: saveSession,
        clearSession: clearSession,
        getSession: readSession,
        isSuiviPageForSession: isSuiviPageForSession,
        ensureBackgroundIframe: ensureBackgroundIframe,
        syncFromConfig: syncFromConfig
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleLauncher);
    } else {
        scheduleLauncher();
    }

    global.addEventListener('storage', function (e) {
        if (e.key === STORAGE_KEY) {
            scheduleLauncher();
        }
    });

    global.addEventListener('pageshow', scheduleLauncher);
})(window);
