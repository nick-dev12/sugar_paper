/**
 * Carte admin — tous les livreurs en livraison GPS
 */
(function () {
    'use strict';

    var cfg = window.LIVREURS_CARTE_CONFIG;
    if (!cfg || !document.getElementById('livreurs-carte-map')) {
        return;
    }

    var map = L.map('livreurs-carte-map', {
        zoomControl: true,
        attributionControl: true
    }).setView(cfg.defaultCenter || [14.6937, -17.4441], cfg.defaultZoom || 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var markers = {};
    var destMarkers = {};
    var listEl = document.getElementById('livreurs-carte-list');
    var countEl = document.getElementById('livreurs-carte-count');
    var fitBtn = document.getElementById('livreurs-carte-fit');
    var COLORS = ['#c26638', '#918a44', '#6b2f20', '#2d6a4f', '#1d4e89', '#9b2226'];

    function colorForId(id) {
        return COLORS[Math.abs(parseInt(id, 10) || 0) % COLORS.length];
    }

    function makeIcon(color, label) {
        var initial = (label || 'L').charAt(0).toUpperCase();
        return L.divIcon({
            className: 'livreurs-carte-marker',
            html: '<div class="livreurs-carte-marker__pin" style="background:' + color + '">' +
                '<i class="fas fa-motorcycle"></i><span>' + initial + '</span></div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });
    }

    function makeDestIcon() {
        return L.divIcon({
            className: 'livreurs-carte-dest',
            html: '<div class="livreurs-carte-dest__dot"><i class="fas fa-location-dot"></i></div>',
            iconSize: [28, 28],
            iconAnchor: [14, 28]
        });
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function renderList(livreurs) {
        if (!listEl) return;
        if (!livreurs.length) {
            listEl.innerHTML = '<li class="livreurs-carte-list__empty" id="livreurs-carte-empty">Aucun livreur en suivi GPS pour le moment.</li>';
            return;
        }
        var html = '';
        livreurs.forEach(function (lv) {
            html += '<li class="livreurs-carte-list__item" data-livreur-id="' + lv.livreur_id + '">' +
                '<div class="livreurs-carte-list__head">' +
                '<strong>' + escapeHtml(lv.livreur_nom) + '</strong>' +
                '<span class="livreurs-carte-list__type">' + (lv.livraison_type === 'facture' ? 'Facture' : 'Commande') + '</span>' +
                '</div>' +
                '<p class="livreurs-carte-list__num">' + escapeHtml(lv.numero) + '</p>' +
                '<p class="livreurs-carte-list__addr">' + escapeHtml(lv.adresse_livraison) + '</p>' +
                '<a class="livreurs-carte-list__link" href="' + escapeHtml(lv.suivi_url) + '">' +
                '<i class="fas fa-eye" aria-hidden="true"></i> Suivre</a></li>';
        });
        listEl.innerHTML = html;
    }

    function updateCount(n) {
        if (countEl) {
            countEl.textContent = n + ' actif' + (n !== 1 ? 's' : '');
        }
    }

    function syncMarkers(livreurs) {
        var seen = {};
        var bounds = [];

        livreurs.forEach(function (lv) {
            var id = String(lv.livreur_id);
            seen[id] = true;
            var color = colorForId(lv.livreur_id);
            var hasPos = lv.latitude != null && lv.longitude != null;

            if (hasPos) {
                var latlng = [lv.latitude, lv.longitude];
                bounds.push(latlng);
                if (markers[id]) {
                    markers[id].setLatLng(latlng);
                } else {
                    markers[id] = L.marker(latlng, {
                        icon: makeIcon(color, lv.livreur_nom)
                    }).addTo(map).bindPopup(
                        '<strong>' + escapeHtml(lv.livreur_nom) + '</strong><br>' +
                        escapeHtml(lv.numero) + '<br>' +
                        '<a href="' + escapeHtml(lv.suivi_url) + '">Suivre</a>'
                    );
                }
            }

            if (lv.delivery_latitude != null && lv.delivery_longitude != null) {
                var dLatLng = [lv.delivery_latitude, lv.delivery_longitude];
                bounds.push(dLatLng);
                if (destMarkers[id]) {
                    destMarkers[id].setLatLng(dLatLng);
                } else {
                    destMarkers[id] = L.marker(dLatLng, {
                        icon: makeDestIcon()
                    }).addTo(map).bindPopup('Destination — ' + escapeHtml(lv.livreur_nom));
                }
            }
        });

        Object.keys(markers).forEach(function (id) {
            if (!seen[id]) {
                map.removeLayer(markers[id]);
                delete markers[id];
            }
        });
        Object.keys(destMarkers).forEach(function (id) {
            if (!seen[id]) {
                map.removeLayer(destMarkers[id]);
                delete destMarkers[id];
            }
        });

        return bounds;
    }

    function fitAll(bounds) {
        if (!bounds || !bounds.length) {
            map.setView(cfg.defaultCenter || [14.6937, -17.4441], cfg.defaultZoom || 12);
            return;
        }
        if (bounds.length === 1) {
            map.setView(bounds[0], 15);
            return;
        }
        map.fitBounds(L.latLngBounds(bounds).pad(0.2), { maxZoom: 15 });
    }

    var lastFitKey = '';
    function applyLivreurs(livreurs, doFit) {
        updateCount(livreurs.length);
        renderList(livreurs);
        var bounds = syncMarkers(livreurs);
        var key = livreurs.map(function (l) { return l.livreur_id; }).sort().join(',');
        if (doFit || key !== lastFitKey) {
            fitAll(bounds);
            lastFitKey = key;
        }
    }

    function poll() {
        fetch(cfg.pollUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.success || !Array.isArray(data.livreurs)) {
                    return;
                }
                applyLivreurs(data.livreurs, false);
            })
            .catch(function () { /* silencieux */ });
    }

    applyLivreurs(cfg.initialLivreurs || [], true);

    if (fitBtn) {
        fitBtn.addEventListener('click', function () {
            var bounds = [];
            Object.keys(markers).forEach(function (id) {
                bounds.push(markers[id].getLatLng());
            });
            Object.keys(destMarkers).forEach(function (id) {
                bounds.push(destMarkers[id].getLatLng());
            });
            fitAll(bounds);
        });
    }

    setInterval(poll, cfg.pollIntervalMs || 5000);
    setTimeout(function () { map.invalidateSize(); }, 200);
})();
