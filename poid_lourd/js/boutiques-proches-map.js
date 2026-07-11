/**
 * Carte interactive — boutiques proches (Leaflet) + filtres type / distance.
 */
(function (win, doc) {
    'use strict';

    var map = null;
    var markersLayer = null;
    var userMarker = null;
    var markerById = {};
    var selectedId = null;
    var state = { user: null, allBoutiques: [], boutiques: [], mapMax: 25 };
    var sidePanel = null;
    var listPanel = null;
    var listToggle = null;
    var filterTypeEl = null;
    var filterDistEl = null;

    function readPayload() {
        var el = doc.getElementById('mpBtMapData');
        if (!el) {
            return;
        }
        try {
            var data = JSON.parse(el.textContent || '{}');
            state.user = data.user || null;
            state.allBoutiques = Array.isArray(data.boutiques) ? data.boutiques : [];
            state.mapMax = parseInt(data.map_max, 10);
            if (isNaN(state.mapMax) || state.mapMax <= 0) {
                state.mapMax = 25;
            }
            var filters = data.filters || {};
            if (filterTypeEl && filters.type_id != null) {
                filterTypeEl.value = String(filters.type_id || '');
            }
            if (filterDistEl && filters.dist_km != null) {
                filterDistEl.value = String(filters.dist_km || '0');
            }
            applyMapFilters(false);
        } catch (e) {
            state.allBoutiques = [];
            state.boutiques = [];
        }
    }

    function getFilterValues() {
        var typeId = filterTypeEl ? parseInt(filterTypeEl.value, 10) : 0;
        var maxDist = filterDistEl ? parseFloat(filterDistEl.value) : 0;
        if (isNaN(typeId) || typeId < 0) {
            typeId = 0;
        }
        if (isNaN(maxDist) || maxDist < 0) {
            maxDist = 0;
        }
        return { typeId: typeId, maxDist: maxDist };
    }

    function boutiquePassesDistanceFilter(item, maxDist) {
        if (maxDist <= 0 || maxDist === 999) {
            return true;
        }
        if (item.distance_km == null || item.distance_km === '' || isNaN(Number(item.distance_km))) {
            return false;
        }
        return Number(item.distance_km) <= maxDist;
    }

    function sortByDistance(items) {
        return items.slice().sort(function (a, b) {
            var da = a.distance_km != null && !isNaN(Number(a.distance_km)) ? Number(a.distance_km) : 9999;
            var db = b.distance_km != null && !isNaN(Number(b.distance_km)) ? Number(b.distance_km) : 9999;
            return da - db;
        });
    }

    function applyMapFilters(updateUrl) {
        if (updateUrl === undefined) {
            updateUrl = true;
        }
        var f = getFilterValues();
        state.boutiques = sortByDistance(state.allBoutiques.filter(function (item) {
            if (f.typeId > 0 && Number(item.type_id || 0) !== f.typeId) {
                return false;
            }
            return boutiquePassesDistanceFilter(item, f.maxDist);
        })).slice(0, state.mapMax);

        if (selectedId && !state.boutiques.some(function (b) { return String(b.id) === String(selectedId); })) {
            hideDetailPanel();
        }

        if (map && markersLayer) {
            rebuildMarkers();
        }
        buildList();

        if (updateUrl) {
            reloadWithMapFilters(f.typeId, f.maxDist);
            return;
        }
    }

    function reloadWithMapFilters(typeId, maxDist) {
        try {
            var params = new URLSearchParams(win.location.search || '');
            if (typeId > 0) {
                params.set('type', String(typeId));
            } else {
                params.delete('type');
            }
            if (maxDist > 0) {
                params.set('dist', String(maxDist));
            } else {
                params.delete('dist');
            }
            params.delete('page');
            params.set('open_map', '1');
            var qs = params.toString();
            win.location.href = win.location.pathname + (qs ? '?' + qs : '');
        } catch (e) { /* ignore */ }
    }

    function syncUrlParams(typeId, maxDist) {
        try {
            var params = new URLSearchParams(win.location.search || '');
            if (typeId > 0) {
                params.set('type', String(typeId));
            } else {
                params.delete('type');
            }
            if (maxDist > 0) {
                params.set('dist', String(maxDist));
            } else {
                params.delete('dist');
            }
            params.delete('page');
            params.delete('open_map');
            var qs = params.toString();
            win.history.replaceState({}, '', win.location.pathname + (qs ? '?' + qs : ''));
        } catch (e) { /* ignore */ }

        var catalogType = doc.getElementById('mpBtFilterType');
        if (catalogType) {
            catalogType.value = typeId > 0 ? String(typeId) : '';
        }
        if (filterDistEl) {
            var catalogDist = doc.getElementById('mpBtFilterDist');
            if (catalogDist) {
                catalogDist.value = maxDist > 0 ? String(maxDist) : '0';
            }
        }
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function formatDistance(km) {
        if (km == null || isNaN(km)) {
            return '';
        }
        var n = Number(km);
        if (n < 1) {
            return Math.round(n * 1000) + ' m';
        }
        return n.toFixed(n < 10 ? 1 : 0).replace('.', ',') + ' km';
    }

    function buildMarkerIcon(item) {
        var color = item.theme_main || '#3564a6';
        var logo = item.logo
            ? '<img src="' + esc(item.logo) + '" alt="" onerror="this.remove()">'
            : '<i class="fas fa-store"></i>';
        var label = esc(item.nom || 'Boutique');
        return L.divIcon({
            className: 'mp-bt-map-marker-wrap',
            html: ''
                + '<div class="mp-bt-map-marker" style="--mk-color:' + esc(color) + '">'
                + '<div class="mp-bt-map-marker__pin">' + logo + '</div>'
                + '<span class="mp-bt-map-marker__label">' + label + '</span>'
                + '</div>',
            iconSize: [120, 72],
            iconAnchor: [60, 42]
        });
    }

    function openModal() {
        var modal = doc.getElementById('mpBtMapModal');
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('mp-bt-map-open');
        win.setTimeout(initMap, 60);
    }

    function closeModal() {
        var modal = doc.getElementById('mpBtMapModal');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        doc.body.classList.remove('mp-bt-map-open');
        hideDetailPanel();
        closeListPanel();
    }

    function hideDetailPanel() {
        selectedId = null;
        if (!sidePanel) {
            return;
        }
        sidePanel.hidden = true;
        sidePanel.classList.remove('is-open');
        doc.querySelectorAll('.mp-bt-map-list__item.is-active').forEach(function (el) {
            el.classList.remove('is-active');
        });
    }

    function showDetailPanel() {
        if (!sidePanel) {
            return;
        }
        sidePanel.hidden = false;
        win.requestAnimationFrame(function () {
            sidePanel.classList.add('is-open');
        });
    }

    function openListPanel() {
        if (!listPanel || !listToggle) {
            return;
        }
        listPanel.classList.add('is-open');
        listToggle.setAttribute('aria-expanded', 'true');
    }

    function closeListPanel() {
        if (!listPanel || !listToggle) {
            return;
        }
        listPanel.classList.remove('is-open');
        listToggle.setAttribute('aria-expanded', 'false');
    }

    function toggleListPanel() {
        if (!listPanel) {
            return;
        }
        if (listPanel.classList.contains('is-open')) {
            closeListPanel();
        } else {
            openListPanel();
        }
    }

    function highlightListItem(id) {
        doc.querySelectorAll('.mp-bt-map-list__item').forEach(function (el) {
            el.classList.toggle('is-active', String(el.getAttribute('data-id')) === String(id));
        });
    }

    function selectBoutique(item, options) {
        options = options || {};
        if (!item) {
            return;
        }
        selectedId = item.id;
        highlightListItem(item.id);
        showDetailPanel();

        var logoWrap = doc.getElementById('mpBtMapSideLogo');
        if (logoWrap) {
            logoWrap.innerHTML = item.logo
                ? '<img src="' + esc(item.logo) + '" alt="">'
                : '<i class="fas fa-store"></i>';
        }

        var nameEl = doc.getElementById('mpBtMapSideName');
        if (nameEl) {
            nameEl.textContent = item.nom || 'Boutique';
        }

        var distEl = doc.getElementById('mpBtMapSideDist');
        if (distEl) {
            var distLabel = formatDistance(item.distance_km);
            distEl.textContent = distLabel;
            distEl.hidden = distLabel === '';
        }

        var addrEl = doc.getElementById('mpBtMapSideAddr');
        if (addrEl) {
            addrEl.textContent = item.adresse || item.region || '';
            addrEl.hidden = !(item.adresse || item.region);
        }

        var visit = doc.getElementById('mpBtMapSideVisit');
        if (visit) {
            visit.href = item.vitrine_href || '#';
        }
        var maps = doc.getElementById('mpBtMapSideMaps');
        if (maps) {
            maps.href = item.maps_url || '#';
            maps.hidden = !item.maps_url;
        }

        var shareShop = doc.getElementById('mpBtMapShareShop');
        if (shareShop) {
            shareShop.onclick = function () {
                if (win.openPlatformShareModal && item.share_url) {
                    win.openPlatformShareModal({
                        modalTitle: item.share_modal_title || 'Partager cette boutique',
                        title: item.share_title || item.nom,
                        url: item.share_url,
                        message: item.share_text || item.share_title,
                        hint: item.share_hint || 'Le lien ouvre la boutique publique sur COLObanes.'
                    });
                }
            };
        }

        var shareGeo = doc.getElementById('mpBtMapShareGeo');
        if (shareGeo) {
            shareGeo.onclick = function () {
                if (!win.openPlatformShareModal) {
                    return;
                }
                var geoUrl = item.geo_share_url || item.maps_url || '';
                if (!geoUrl) {
                    return;
                }
                win.openPlatformShareModal({
                    modalTitle: 'Partager la localisation',
                    title: item.geo_share_title || ('Point de retrait — ' + item.nom),
                    url: geoUrl,
                    message: item.geo_share_title || item.nom,
                    hint: 'Partagez le point de retrait de la boutique.'
                });
            };
            shareGeo.hidden = !(item.geo_share_url || item.maps_url);
        }

        if (options.pan !== false && map && item.lat && item.lng) {
            map.panTo([item.lat, item.lng], { animate: true });
        }
    }

    function buildList() {
        var listEl = doc.getElementById('mpBtMapListItems');
        var countEl = doc.getElementById('mpBtMapListCount');
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        var sorted = state.boutiques.slice().sort(function (a, b) {
            var da = a.distance_km != null ? Number(a.distance_km) : 9999;
            var db = b.distance_km != null ? Number(b.distance_km) : 9999;
            return da - db;
        });

        if (!sorted.length) {
            var emptyLi = doc.createElement('li');
            emptyLi.className = 'mp-bt-map-list__item mp-bt-map-list__item--empty';
            emptyLi.textContent = 'Aucune boutique pour ces filtres.';
            listEl.appendChild(emptyLi);
        }

        sorted.forEach(function (item) {
            var li = doc.createElement('li');
            li.className = 'mp-bt-map-list__item';
            li.setAttribute('data-id', String(item.id));
            li.setAttribute('role', 'listitem');

            var logoHtml = item.logo
                ? '<img src="' + esc(item.logo) + '" alt="" onerror="this.remove()">'
                : '<i class="fas fa-store"></i>';
            var dist = formatDistance(item.distance_km);

            li.innerHTML = ''
                + '<button type="button" class="mp-bt-map-list__btn">'
                + '<span class="mp-bt-map-list__logo">' + logoHtml + '</span>'
                + '<span class="mp-bt-map-list__meta">'
                + '<strong>' + esc(item.nom || 'Boutique') + '</strong>'
                + (dist ? '<span class="mp-bt-map-list__dist">' + esc(dist) + '</span>' : '')
                + '</span>'
                + '<i class="fas fa-chevron-right mp-bt-map-list__arrow" aria-hidden="true"></i>'
                + '</button>';

            li.querySelector('.mp-bt-map-list__btn').addEventListener('click', function () {
                selectBoutique(item);
                if (win.matchMedia('(max-width: 1023px)').matches) {
                    closeListPanel();
                }
            });

            listEl.appendChild(li);
        });

        if (countEl) {
            countEl.textContent = String(sorted.length);
        }
    }

    function fitMapToVisible() {
        if (!map) {
            return;
        }
        var bounds = [];
        if (state.user && state.user.lat && state.user.lng) {
            bounds.push([state.user.lat, state.user.lng]);
        }
        state.boutiques.forEach(function (item) {
            if (item.lat && item.lng) {
                bounds.push([item.lat, item.lng]);
            }
        });
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [48, 48], maxZoom: 15 });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        }
    }

    function rebuildMarkers() {
        if (!map || !markersLayer) {
            return;
        }
        markersLayer.clearLayers();
        markerById = {};

        state.boutiques.forEach(function (item) {
            if (!item.lat || !item.lng) {
                return;
            }
            var marker = L.marker([item.lat, item.lng], { icon: buildMarkerIcon(item) });
            marker.on('click', function (e) {
                if (e && e.originalEvent) {
                    e.originalEvent.stopPropagation();
                }
                selectBoutique(item);
            });
            marker.addTo(markersLayer);
            markerById[item.id] = marker;
        });

        fitMapToVisible();
    }

    function initMap() {
        if (!win.L) {
            return;
        }
        var canvas = doc.getElementById('mpBtMapCanvas');
        if (!canvas) {
            return;
        }

        if (map) {
            map.invalidateSize();
            rebuildMarkers();
            buildList();
            return;
        }

        if (!state.allBoutiques.length && !state.user) {
            return;
        }

        var centerLat = state.user ? state.user.lat : (state.boutiques[0] ? state.boutiques[0].lat : 14.6928);
        var centerLng = state.user ? state.user.lng : (state.boutiques[0] ? state.boutiques[0].lng : -17.4467);

        map = L.map(canvas, { zoomControl: true }).setView([centerLat, centerLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        markersLayer = L.layerGroup().addTo(map);

        if (state.user && state.user.lat && state.user.lng) {
            userMarker = L.circleMarker([state.user.lat, state.user.lng], {
                radius: 8,
                color: '#3564a6',
                fillColor: '#3564a6',
                fillOpacity: 0.85,
                weight: 2
            }).addTo(map);
            userMarker.bindTooltip('Vous', { permanent: false, direction: 'top' });
        }

        map.on('click', function () {
            hideDetailPanel();
        });

        rebuildMarkers();
        buildList();
        hideDetailPanel();

        if (win.matchMedia('(min-width: 1024px)').matches && listPanel) {
            listPanel.classList.add('is-open');
            if (listToggle) {
                listToggle.setAttribute('aria-expanded', 'true');
            }
        }

        win.setTimeout(function () {
            map.invalidateSize();
        }, 120);
    }

    function bindUi() {
        sidePanel = doc.getElementById('mpBtMapSide');
        listPanel = doc.getElementById('mpBtMapList');
        listToggle = doc.getElementById('mpBtMapListToggle');
        filterTypeEl = doc.getElementById('mpBtMapFilterType');
        filterDistEl = doc.getElementById('mpBtMapFilterDist');

        readPayload();

        if (filterTypeEl) {
            filterTypeEl.addEventListener('change', function () {
                applyMapFilters(true);
            });
        }
        if (filterDistEl) {
            filterDistEl.addEventListener('change', function () {
                applyMapFilters(true);
            });
        }

        var openBtn = doc.getElementById('mpBtOpenMap');
        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }

        if (listToggle) {
            listToggle.addEventListener('click', toggleListPanel);
        }

        var listClose = doc.getElementById('mpBtMapListClose');
        if (listClose) {
            listClose.addEventListener('click', closeListPanel);
        }

        if (listPanel) {
            listPanel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        if (sidePanel) {
            sidePanel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        doc.querySelectorAll('[data-map-detail-close]').forEach(function (el) {
            el.addEventListener('click', hideDetailPanel);
        });

        doc.querySelectorAll('[data-map-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (sidePanel && sidePanel.classList.contains('is-open')) {
                    hideDetailPanel();
                    return;
                }
                if (listPanel && listPanel.classList.contains('is-open')
                    && win.matchMedia('(max-width: 1023px)').matches) {
                    closeListPanel();
                    return;
                }
                closeModal();
            }
        });
    }

    win.openBoutiquesMapModal = openModal;

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', bindUi);
    } else {
        bindUi();
    }
})(window, document);
