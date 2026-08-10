/**
 * Popup « Livreur en route » — affichage global client (site + app).
 */
(function (global) {
    'use strict';

    var API_URL = '/api/tracking/client-active-delivery.php';
    var POLL_MS = 22000;
    var STORAGE_PREFIX = 'sp_livraison_popup_hide_';

    var popup = null;
    var map = null;
    var driverMarker = null;
    var clientMarker = null;
    var pollTimer = null;
    var currentDelivery = null;
    var mapReady = false;

    function $(id) {
        return document.getElementById(id);
    }

    function isSuiviPage() {
        var path = (global.location.pathname || '').toLowerCase();
        return path.indexOf('suivi-livraison.php') !== -1
            || path.indexOf('/user/suivi-commande.php') !== -1;
    }

    function isDismissed(commandeId) {
        try {
            return global.sessionStorage.getItem(STORAGE_PREFIX + commandeId) === '1';
        } catch (e) {
            return false;
        }
    }

    function setDismissed(commandeId) {
        try {
            global.sessionStorage.setItem(STORAGE_PREFIX + commandeId, '1');
        } catch (e) { /* ignore */ }
    }

    function fetchActiveDelivery() {
        return fetch(API_URL, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .catch(function () { return null; });
    }

    function ensurePopupDom() {
        popup = $('client-livraison-popup');
        return !!popup;
    }

    function openPopup() {
        if (!popup) return;
        popup.removeAttribute('hidden');
        popup.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePopup(persistDismiss) {
        if (!popup) return;
        popup.setAttribute('hidden', '');
        popup.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (persistDismiss && currentDelivery && currentDelivery.commande_id) {
            setDismissed(currentDelivery.commande_id);
        }
    }

    function bindCloseHandlers() {
        if (!popup || popup.dataset.clpBound === '1') return;
        popup.dataset.clpBound = '1';

        popup.querySelectorAll('[data-clp-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closePopup(true);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popup && !popup.hasAttribute('hidden')) {
                closePopup(true);
            }
        });
    }

    function updatePopupContent(data) {
        var numeroEl = $('clp-numero');
        var livreurEl = $('clp-livreur');
        var followBtn = $('clp-follow-btn');
        var subtitle = $('clp-subtitle');

        if (numeroEl) {
            numeroEl.textContent = '#' + (data.numero_commande || data.commande_id || '');
        }
        if (livreurEl) {
            livreurEl.innerHTML = '<i class="fas fa-user"></i> ' + (data.livreur_nom || 'Votre livreur');
        }
        if (followBtn) {
            followBtn.href = data.suivi_url || ('/user/suivi-commande.php?commande_id=' + (data.commande_id || ''));
        }
        if (subtitle && data.adresse_livraison) {
            subtitle.textContent = 'Direction : ' + data.adresse_livraison;
        }
    }

    function initMiniMap(data) {
        var mapEl = $('clp-mini-map');
        var loader = $('clp-map-loader');
        if (!mapEl || typeof L === 'undefined') {
            if (loader) loader.classList.add('is-hidden');
            return;
        }

        var driverLat = parseFloat(data.driver_lat);
        var driverLng = parseFloat(data.driver_lng);
        var deliveryLat = parseFloat(data.delivery_lat);
        var deliveryLng = parseFloat(data.delivery_lng);

        var hasDriver = isFinite(driverLat) && isFinite(driverLng);
        var hasClient = isFinite(deliveryLat) && isFinite(deliveryLng);

        var centerLat = hasDriver ? driverLat : (hasClient ? deliveryLat : 14.6937);
        var centerLng = hasDriver ? driverLng : (hasClient ? deliveryLng : -17.4441);
        var zoom = hasDriver && hasClient ? 13 : 12;

        if (!map) {
            map = L.map(mapEl, {
                zoomControl: false,
                attributionControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false
            }).setView([centerLat, centerLng], zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            mapReady = true;
        }

        if (driverMarker) {
            map.removeLayer(driverMarker);
            driverMarker = null;
        }
        if (clientMarker) {
            map.removeLayer(clientMarker);
            clientMarker = null;
        }

        if (hasClient) {
            clientMarker = L.circleMarker([deliveryLat, deliveryLng], {
                radius: 7,
                color: '#fff',
                weight: 2,
                fillColor: '#F77F00',
                fillOpacity: 1
            }).addTo(map);
        }

        if (hasDriver) {
            driverMarker = L.circleMarker([driverLat, driverLng], {
                radius: 8,
                color: '#fff',
                weight: 2,
                fillColor: '#E5488A',
                fillOpacity: 1
            }).addTo(map);
        }

        var bounds = [];
        if (hasDriver) bounds.push([driverLat, driverLng]);
        if (hasClient) bounds.push([deliveryLat, deliveryLng]);
        if (bounds.length >= 2) {
            map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        }

        setTimeout(function () {
            if (map) map.invalidateSize();
            if (loader) loader.classList.add('is-hidden');
        }, 120);
    }

    function updateMapPosition(data) {
        if (!map || !mapReady) return;

        var driverLat = parseFloat(data.driver_lat);
        var driverLng = parseFloat(data.driver_lng);
        if (!isFinite(driverLat) || !isFinite(driverLng)) return;

        if (driverMarker) {
            driverMarker.setLatLng([driverLat, driverLng]);
        } else {
            driverMarker = L.circleMarker([driverLat, driverLng], {
                radius: 8,
                color: '#fff',
                weight: 2,
                fillColor: '#E5488A',
                fillOpacity: 1
            }).addTo(map);
        }

        var deliveryLat = parseFloat(data.delivery_lat);
        var deliveryLng = parseFloat(data.delivery_lng);
        if (clientMarker && isFinite(deliveryLat) && isFinite(deliveryLng)) {
            map.fitBounds([[driverLat, driverLng], [deliveryLat, deliveryLng]], {
                padding: [28, 28],
                maxZoom: 15
            });
        }
    }

    function showForDelivery(data, forceShow) {
        if (!data || !data.commande_id) return;
        if (isSuiviPage()) return;
        if (!forceShow && isDismissed(data.commande_id)) return;

        var isNew = !currentDelivery || currentDelivery.commande_id !== data.commande_id;
        currentDelivery = data;

        if (!ensurePopupDom()) return;
        bindCloseHandlers();
        updatePopupContent(data);

        if (isNew || !mapReady) {
            var loader = $('clp-map-loader');
            if (loader) loader.classList.remove('is-hidden');
            openPopup();
            setTimeout(function () {
                initMiniMap(data);
            }, 80);
        } else {
            updateMapPosition(data);
            if (popup.hasAttribute('hidden')) {
                openPopup();
            }
        }
    }

    function checkAndMaybeShow(forceShow) {
        if (isSuiviPage()) return;

        fetchActiveDelivery().then(function (payload) {
            if (!payload || !payload.success || !payload.active || !payload.delivery) {
                if (currentDelivery && popup && !popup.hasAttribute('hidden')) {
                    closePopup(false);
                }
                currentDelivery = null;
                return;
            }
            showForDelivery(payload.delivery, !!forceShow);
        });
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(function () {
            checkAndMaybeShow(false);
        }, POLL_MS);
    }

    function onPushPayload(payload) {
        if (!payload || !payload.data) return;
        var statut = (payload.data.statut || '').toLowerCase();
        var tag = (payload.data.tag || '').toLowerCase();
        if (statut === 'livraison_en_cours' || tag.indexOf('suivi-gps') !== -1 || tag.indexOf('livreur') !== -1) {
            checkAndMaybeShow(true);
        }
    }

    function hookFirebaseForeground() {
        if (global.__clpFirebaseHooked) return;
        global.__clpFirebaseHooked = true;

        var tries = 0;
        var iv = setInterval(function () {
            tries++;
            if (typeof firebase !== 'undefined' && firebase.messaging) {
                clearInterval(iv);
                try {
                    firebase.messaging().onMessage(function (payload) {
                        onPushPayload(payload);
                    });
                } catch (e) { /* ignore */ }
            }
            if (tries > 40) clearInterval(iv);
        }, 500);
    }

    function boot() {
        if (global.FIREBASE_NOTIFY_TYPE && global.FIREBASE_NOTIFY_TYPE !== 'user') {
            return;
        }
        if (!global.FCM_ACCOUNT_ID) {
            return;
        }
        if (!ensurePopupDom()) {
            return;
        }

        bindCloseHandlers();
        hookFirebaseForeground();

        setTimeout(function () {
            checkAndMaybeShow(false);
            startPolling();
        }, 600);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                checkAndMaybeShow(false);
            }
        });
    }

    global.ClientLivraisonPopup = {
        check: checkAndMaybeShow,
        show: showForDelivery,
        onPush: onPushPayload
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
