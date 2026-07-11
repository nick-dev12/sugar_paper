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
    var suggestTimer = null;
    var suggestAbort = null;
    var activeSuggestIndex = -1;
    var lastSuggestItems = [];
    var isComposing = false;
    var suppressSuggest = false;
    var activeBtn = null;
    var activeRow = null;
    var sessionReserved = false;
    var formSubmitted = false;
    var currentLivraisonType = 'commande';

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
        geocodeBestMatch(address, true);
    }

    function setAddressSuggestExpanded(open) {
        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput) {
            adresseInput.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function geocodeBestMatch(address, silentList) {
        var q = (address || '').trim();
        if (q.length < 2) {
            setStatus('warn', 'Saisissez au moins 2 caractères pour rechercher un lieu.');
            return Promise.resolve(false);
        }

        clearTimeout(geocodeTimer);
        setStatus('pending', 'Recherche du lieu le plus proche…');

        return fetch('/api/geo-geocode.php?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.ok && data.lat !== null && data.lng !== null) {
                    suppressSuggest = true;
                    var adresseInput = qs('livreur-demarrage-adresse');
                    if (adresseInput && data.label) {
                        adresseInput.value = data.label;
                    }
                    hideAddressSuggestions();
                    updateClientOnMap(data.lat, data.lng);
                    setStatus('ok', 'Lieu trouvé et placé sur la carte.');
                    setTimeout(function () { suppressSuggest = false; }, 120);
                    return true;
                }
                if (!silentList) {
                    setStatus('warn', 'Aucun lieu trouvé. Précisez quartier et ville.');
                }
                return false;
            })
            .catch(function () {
                setStatus('error', 'Erreur lors de la recherche du lieu.');
                return false;
            });
    }

    function escapeHtml(text) {
        var el = document.createElement('span');
        el.textContent = text || '';
        return el.innerHTML;
    }

    function hideAddressSuggestions() {
        var list = qs('livreur-address-suggest');
        if (!list) return;
        list.hidden = true;
        list.innerHTML = '';
        activeSuggestIndex = -1;
        lastSuggestItems = [];
        setAddressSuggestExpanded(false);
    }

    function highlightSuggestItem(items, index) {
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('is-active', i === index);
        }
    }

    function selectAddressSuggestion(item) {
        if (!item || item.lat === null || item.lng === null) return;

        suppressSuggest = true;
        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput) {
            adresseInput.value = item.label || item.full || '';
        }
        hideAddressSuggestions();
        updateClientOnMap(item.lat, item.lng);
        setStatus('ok', 'Adresse sélectionnée sur la carte.');
        setTimeout(function () {
            suppressSuggest = false;
        }, 120);
    }

    function showAddressSuggestions(items) {
        var list = qs('livreur-address-suggest');
        if (!list) return;

        lastSuggestItems = Array.isArray(items) ? items.slice() : [];
        list.innerHTML = '';
        if (!lastSuggestItems.length) {
            var empty = document.createElement('li');
            empty.className = 'livreur-address-suggest__empty';
            empty.textContent = 'Aucun lieu trouvé. Appuyez sur Entrée pour relancer la recherche.';
            list.appendChild(empty);
            list.hidden = false;
            activeSuggestIndex = -1;
            setAddressSuggestExpanded(true);
            return;
        }

        lastSuggestItems.forEach(function (item, index) {
            var li = document.createElement('li');
            li.className = 'livreur-address-suggest__item';
            li.setAttribute('role', 'option');
            li.setAttribute('data-index', String(index));
            li.setAttribute('data-lat', String(item.lat));
            li.setAttribute('data-lng', String(item.lng));
            li.setAttribute('data-label', item.label || item.full || '');
            if (item.full && item.full !== item.label) {
                li.title = item.full;
            }
            li.innerHTML =
                '<i class="fas fa-location-dot" aria-hidden="true"></i>' +
                '<span>' + escapeHtml(item.label || item.full || '') + '</span>';
            list.appendChild(li);
        });

        list.hidden = false;
        activeSuggestIndex = -1;
        setAddressSuggestExpanded(true);
    }

    function resolveAddressFromInput() {
        var adresseInput = qs('livreur-demarrage-adresse');
        if (!adresseInput) return;

        var q = adresseInput.value.trim();
        if (q.length < 2) {
            setStatus('warn', 'Saisissez au moins 2 caractères.');
            return;
        }

        if (activeSuggestIndex >= 0 && lastSuggestItems[activeSuggestIndex]) {
            selectAddressSuggestion(lastSuggestItems[activeSuggestIndex]);
            return;
        }

        if (lastSuggestItems.length > 0) {
            selectAddressSuggestion(lastSuggestItems[0]);
            return;
        }

        geocodeBestMatch(q, false);
    }

    function fetchAddressSuggestions(query) {
        if (suggestAbort && typeof suggestAbort.abort === 'function') {
            suggestAbort.abort();
            suggestAbort = null;
        }

        var q = (query || '').trim();
        if (q.length < 2) {
            hideAddressSuggestions();
            return;
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        suggestAbort = controller;

        var url = '/api/geo-geocode-suggest.php?q=' + encodeURIComponent(q) + '&limit=6';
        fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) {
                    hideAddressSuggestions();
                    return;
                }
                showAddressSuggestions(data.suggestions || []);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                hideAddressSuggestions();
            })
            .finally(function () {
                if (suggestAbort === controller) {
                    suggestAbort = null;
                }
            });
    }

    function onAddressInput() {
        if (suppressSuggest || isComposing) return;

        var adresseInput = qs('livreur-demarrage-adresse');
        if (!adresseInput) return;

        clearTimeout(suggestTimer);
        clearTimeout(geocodeTimer);

        var value = adresseInput.value;
        suggestTimer = setTimeout(function () {
            fetchAddressSuggestions(value);
        }, 220);
    }

    function scheduleAddressSuggest() {
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(onAddressInput, 80);
    }

    function bindAddressAutocomplete() {
        var adresseInput = qs('livreur-demarrage-adresse');
        var suggestList = qs('livreur-address-suggest');
        if (!adresseInput) return;

        adresseInput.addEventListener('input', onAddressInput);
        adresseInput.addEventListener('compositionstart', function () {
            isComposing = true;
        });
        adresseInput.addEventListener('compositionend', function () {
            isComposing = false;
            scheduleAddressSuggest();
        });

        /* Clavier virtuel mobile / tablette : secours si input tardif */
        adresseInput.addEventListener('keyup', function (e) {
            if (isComposing) return;
            if (e.key === 'Enter') return;
            scheduleAddressSuggest();
        });

        adresseInput.addEventListener('keydown', function (e) {
            var list = qs('livreur-address-suggest');
            var items = list ? list.querySelectorAll('.livreur-address-suggest__item') : [];

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                if (isComposing) return;
                resolveAddressFromInput();
                return;
            }

            if (!list || list.hidden || !items.length) {
                if (e.key === 'Escape') {
                    hideAddressSuggestions();
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeSuggestIndex = Math.min(activeSuggestIndex + 1, items.length - 1);
                highlightSuggestItem(items, activeSuggestIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeSuggestIndex = Math.max(activeSuggestIndex - 1, 0);
                highlightSuggestItem(items, activeSuggestIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                hideAddressSuggestions();
            }
        });

        adresseInput.addEventListener('blur', function () {
            setTimeout(function () {
                hideAddressSuggestions();
            }, 220);
        });

        if (suggestList) {
            suggestList.addEventListener('mousedown', function (e) {
                var item = e.target.closest('.livreur-address-suggest__item');
                if (!item) return;
                e.preventDefault();
                selectAddressSuggestion({
                    lat: parseFloat(item.getAttribute('data-lat')),
                    lng: parseFloat(item.getAttribute('data-lng')),
                    label: item.getAttribute('data-label') || ''
                });
            });
        }
    }

    function openPanel(btn) {
        if (!panel || !form) return;

        activeBtn = btn;
        sessionReserved = false;
        formSubmitted = false;
        var livraisonType = btn.getAttribute('data-livraison-type') || 'commande';
        currentLivraisonType = livraisonType;
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
        hideAddressSuggestions();

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
        var shouldAbandon = sessionReserved && !formSubmitted;

        function finishClose() {
            stopWatch();
            hideAddressSuggestions();
            if (suggestAbort && typeof suggestAbort.abort === 'function') {
                suggestAbort.abort();
                suggestAbort = null;
            }
            if (panel) {
                panel.hidden = true;
                panel.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove('livreur-demarrage-open');
            if (activeBtn) {
                activeBtn.removeAttribute('disabled');
            }
            activeBtn = null;
            activeRow = null;
            sessionReserved = false;
            formSubmitted = false;
            currentLivraisonType = 'commande';
        }

        if (shouldAbandon) {
            revertRowIfNeeded();
            finishClose();
            abandonReservation().catch(function () {
                /* UI déjà réinitialisée — annulation BDD en arrière-plan */
            });
            return;
        }

        finishClose();
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
            return;
        }
        formSubmitted = true;
    }

    function abandonReservation() {
        var blInput = qs('livreur-demarrage-bl-id');
        var blId = blInput ? parseInt(blInput.value || '0', 10) : 0;
        var livraisonType = blId > 0 ? 'facture' : (currentLivraisonType || 'commande');
        var body = {
            action: livraisonType === 'facture' ? 'annuler_facture' : 'annuler_commande'
        };
        if (livraisonType === 'facture') {
            body.bl_id = blId;
        } else {
            body.commande_id = parseInt(qs('livreur-demarrage-commande-id') && qs('livreur-demarrage-commande-id').value || '0', 10);
        }

        return fetch('/api/tracking/prendre-livraison.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Annulation impossible.');
                }
                return data;
            });
        });
    }

    function revertRowIfNeeded() {
        if (!activeRow) return;

        var actions = activeRow.querySelector('.livreur-actions');
        if (actions && activeRow.dataset.livreurOriginalActions) {
            actions.innerHTML = activeRow.dataset.livreurOriginalActions;
            delete activeRow.dataset.livreurOriginalActions;
        }

        var statusCell = activeRow.querySelector('td[data-label="Statut"]');
        if (statusCell && activeRow.dataset.livreurOriginalStatut) {
            statusCell.innerHTML = activeRow.dataset.livreurOriginalStatut;
            delete activeRow.dataset.livreurOriginalStatut;
        }
    }

    function reserveLivraison(btn) {
        var livraisonType = btn.getAttribute('data-livraison-type') || 'commande';
        var body = {
            action: livraisonType === 'facture' ? 'prendre_facture' : 'prendre_commande'
        };
        if (livraisonType === 'facture') {
            body.bl_id = parseInt(btn.getAttribute('data-bl-id') || '0', 10);
        } else {
            body.commande_id = parseInt(btn.getAttribute('data-commande-id') || '0', 10);
        }

        btn.setAttribute('disabled', 'disabled');

        return fetch('/api/tracking/prendre-livraison.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Prise en charge impossible.');
                }
                var reservedNow = !data.already;
                if (panel && panel.hidden) {
                    if (reservedNow) {
                        sessionReserved = true;
                        abandonReservation().catch(function () { /* ignore */ });
                    }
                    return data;
                }
                if (reservedNow) {
                    sessionReserved = true;
                }
                btn.setAttribute('data-reserved', sessionReserved ? '1' : '0');
                markRowAsTaken(btn, data.suivi_url || '');
                return data;
            });
        }).finally(function () {
            if (btn) {
                btn.removeAttribute('disabled');
            }
        });
    }

    function markRowAsTaken(btn, suiviUrl) {
        if (panel && panel.hidden) {
            return;
        }
        var row = btn.closest('tr');
        if (!row || !suiviUrl || !sessionReserved) return;
        activeRow = row;
        var actions = row.querySelector('.livreur-actions');
        if (!actions) return;

        if (!row.dataset.livreurOriginalActions) {
            row.dataset.livreurOriginalActions = actions.innerHTML;
        }

        var statusCell = row.querySelector('td[data-label="Statut"]');
        if (statusCell && !row.dataset.livreurOriginalStatut) {
            row.dataset.livreurOriginalStatut = statusCell.innerHTML;
        }
        if (statusCell && statusCell.innerHTML.indexOf('livreur-cmd-mine') === -1) {
            statusCell.innerHTML =
                '<span class="livreur-badge livreur-badge--statut">En cours</span>' +
                '<br><small class="livreur-cmd-mine">Votre livraison</small>';
        }

        actions.innerHTML =
            '<a href="' + suiviUrl + '" class="btn-secondary btn-sm livreur-btn-suivi">' +
            '<i class="fas fa-map-location-dot" aria-hidden="true"></i> ' +
            '<span class="livreur-btn-text livreur-btn-text--full">Suivi GPS</span>' +
            '<span class="livreur-btn-text livreur-btn-text--short">GPS</span>' +
            '</a>';
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
                setStatus('pending', 'Prise en charge de la livraison…');
                reserveLivraison(btn).then(function () {
                    setStatus('pending', 'Capture de votre position en cours… Autorisez l\'accès GPS.');
                }).catch(function (err) {
                    setStatus('error', err.message || 'Impossible de prendre cette livraison.');
                });
                return;
            }
            if (e.target.closest('[data-livreur-demarrage-close]')) {
                e.preventDefault();
                closePanel();
            }
        });

        var adresseInput = qs('livreur-demarrage-adresse');
        bindAddressAutocomplete();

        form.addEventListener('submit', onSubmit);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !panel || panel.hidden) return;
            var list = qs('livreur-address-suggest');
            if (list && !list.hidden) {
                e.preventDefault();
                hideAddressSuggestions();
                return;
            }
            closePanel();
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
