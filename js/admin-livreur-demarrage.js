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
    var suggestLoading = false;
    var isComposing = false;
    var suppressSuggest = false;
    var activeBtn = null;
    var activeRow = null;
    var formSubmitted = false;
    var currentLivraisonType = 'commande';
    var orderOriginalLat = null;
    var orderOriginalLng = null;
    var originalClientAdresse = '';
    var hasOrderGps = false;

    var DEFAULT_CENTER = [14.6937, -17.4441];

    function qs(id) {
        return document.getElementById(id);
    }

    function parseCoord(v) {
        if (v === null || v === undefined || v === '') return null;
        var n = parseFloat(String(v).replace(',', '.'));
        return isFinite(n) ? n : null;
    }

    function dmsToDecimal(deg, min, sec, hemisphere) {
        var dec = Math.abs(parseFloat(String(deg).replace(',', '.')))
            + (Math.abs(parseFloat(String(min).replace(',', '.'))) / 60)
            + (Math.abs(parseFloat(String(sec).replace(',', '.'))) / 3600);
        var h = String(hemisphere || '').toUpperCase();
        if (h === 'S' || h === 'W') {
            dec = -dec;
        }
        return dec;
    }

    function assignDecimalPair(a, b) {
        a = parseCoord(a);
        b = parseCoord(b);
        if (a === null || b === null) return null;

        var absA = Math.abs(a);
        var absB = Math.abs(b);

        if (absA <= 90 && absB <= 180) {
            if (absA <= 17 && absB >= 10 && absB > absA) {
                return { lat: a, lng: b };
            }
            if (absB <= 17 && absA >= 10 && absA > absB) {
                return { lat: b, lng: a };
            }
            return { lat: a, lng: b };
        }
        return null;
    }

    function isLikelyMapsUrl(text) {
        var q = String(text || '').trim();
        if (!q) return false;
        return /^(?:https?:\/\/)?(?:maps\.(?:google|app\.goo\.gl)|www\.google\.(?:com|[a-z]{2}(?:\.[a-z]{2})?)\/maps|goo\.gl\/maps|geo:)/i.test(q)
            || /^https?:\/\/maps\.app\.goo\.gl\//i.test(q);
    }

    function isShortMapsUrl(text) {
        return /^https?:\/\/(maps\.app\.goo\.gl|goo\.gl\/)/i.test(String(text || '').trim());
    }

    function coordsFromPair(pair, fullText) {
        if (!pair) return null;
        return {
            lat: pair.lat,
            lng: pair.lng,
            label: pair.lat.toFixed(6) + ', ' + pair.lng.toFixed(6),
            full: fullText || ''
        };
    }

    function parseMapsUrlFromText(text) {
        var q = String(text || '').trim();
        if (!q || !isLikelyMapsUrl(q)) return null;

        var decoded = decodeURIComponent(q.replace(/\+/g, ' '));

        var pin = decoded.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
        if (pin) return coordsFromPair(assignDecimalPair(pin[1], pin[2]), q);

        var at = decoded.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (at) return coordsFromPair(assignDecimalPair(at[1], at[2]), q);

        var loc = decoded.match(/[?&](?:q|query)=loc:(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i);
        if (loc) return coordsFromPair(assignDecimalPair(loc[1], loc[2]), q);

        var qparam = decoded.match(/[?&](?:q|query)=(-?\d+(?:\.\d+)?)[,%20\s+]+(-?\d+(?:\.\d+)?)(?:&|$)/i);
        if (qparam) return coordsFromPair(assignDecimalPair(qparam[1], qparam[2]), q);

        var ll = decoded.match(/[?&](?:ll|center|destination|daddr)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i);
        if (ll) return coordsFromPair(assignDecimalPair(ll[1], ll[2]), q);

        var geo = q.match(/^geo:(?:-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?\?q=)?(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i);
        if (geo) return coordsFromPair(assignDecimalPair(geo[1], geo[2]), q);

        return null;
    }

    function parseLatLngFromText(text) {
        var q = String(text || '').trim().replace(/\s+/g, ' ');
        if (!q) return null;

        var fromMaps = parseMapsUrlFromText(q);
        if (fromMaps) return fromMaps;

        var dms = q.match(
            /(\d{1,2})\s*[°º˚]\s*(\d{1,2})\s*['′]?\s*(\d+(?:[.,]\d+)?)\s*["″]?\s*([NnSs]).*?(\d{1,3})\s*[°º˚]\s*(\d{1,2})\s*['′]?\s*(\d+(?:[.,]\d+)?)\s*["″]?\s*([EeWw])/
        );
        if (dms) {
            var lat = dmsToDecimal(dms[1], dms[2], dms[3], dms[4]);
            var lng = dmsToDecimal(dms[5], dms[6], dms[7], dms[8]);
            if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                return {
                    lat: lat,
                    lng: lng,
                    label: lat.toFixed(6) + ', ' + lng.toFixed(6),
                    full: q
                };
            }
        }

        var decToken = '(?:-?\\d+(?:[.,]\\d+)?)';
        var dec = q.match(new RegExp('^\\s*(' + decToken + ')\\s*[,;]\\s*(' + decToken + ')\\s*$'))
            || q.match(new RegExp('^\\s*(' + decToken + ')\\s+(' + decToken + ')\\s*$'));
        if (dec) {
            var pair = assignDecimalPair(dec[1], dec[2]);
            if (pair) {
                return {
                    lat: pair.lat,
                    lng: pair.lng,
                    label: pair.lat.toFixed(6) + ', ' + pair.lng.toFixed(6),
                    full: q
                };
            }
        }

        return null;
    }

    function applyParsedCoordinates(parsed, keepInput) {
        if (!parsed) return false;

        suppressSuggest = true;
        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput && !keepInput) {
            adresseInput.value = parsed.label || parsed.full || '';
        }
        hideAddressSuggestions();
        updateClientOnMap(parsed.lat, parsed.lng);
        setStatus('ok', 'Position placée sur la carte.');
        setTimeout(function () { suppressSuggest = false; }, 120);
        return true;
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
        syncGpsDisplay(lat, lng);
        refreshRoute();
    }

    function formatGpsLabel(lat, lng) {
        return lat.toFixed(6) + ', ' + lng.toFixed(6);
    }

    function syncGpsDisplay(lat, lng) {
        var gpsDisplay = qs('livreur-client-gps-display');
        if (gpsDisplay && lat !== null && lng !== null) {
            gpsDisplay.value = formatGpsLabel(lat, lng);
        }
        updateRestoreButtonVisibility();
    }

    function updateRestoreButtonVisibility() {
        var restoreBtn = qs('livreur-gps-restore');
        if (!restoreBtn) return;
        if (!hasOrderGps || orderOriginalLat === null || orderOriginalLng === null) {
            restoreBtn.hidden = true;
            return;
        }
        var c = readCoords();
        var same =
            c.clientLat !== null &&
            c.clientLng !== null &&
            Math.abs(c.clientLat - orderOriginalLat) < 0.000001 &&
            Math.abs(c.clientLng - orderOriginalLng) < 0.000001;
        restoreBtn.hidden = same;
    }

    function showOrderGpsPanel(show) {
        var gpsBlock = qs('livreur-demarrage-gps-exact');
        if (gpsBlock) {
            gpsBlock.hidden = !show;
        }
    }

    function applyOrderOriginalGps(options) {
        options = options || {};
        if (orderOriginalLat === null || orderOriginalLng === null) return;
        updateClientOnMap(orderOriginalLat, orderOriginalLng);
        if (options.resetAdresse !== false) {
            var adresseInput = qs('livreur-demarrage-adresse');
            if (adresseInput) {
                adresseInput.value = originalClientAdresse || '';
            }
        }
        setStatus('ok', 'Position GPS exacte du client restaurée.');
    }

    function resetAddressUi() {
        hasOrderGps = false;
        orderOriginalLat = null;
        orderOriginalLng = null;
        originalClientAdresse = '';
        showOrderGpsPanel(false);
        var gpsDisplay = qs('livreur-client-gps-display');
        if (gpsDisplay) gpsDisplay.value = '';
        var restoreBtn = qs('livreur-gps-restore');
        if (restoreBtn) restoreBtn.hidden = true;
        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput) {
            adresseInput.readOnly = false;
            adresseInput.value = '';
        }
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

        var parsedCoords = parseLatLngFromText(q);
        if (parsedCoords) {
            return Promise.resolve(applyParsedCoordinates(parsedCoords, false));
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
        suggestLoading = false;
        setAddressSuggestExpanded(false);
    }

    function showAddressSuggestionsLoading() {
        var list = qs('livreur-address-suggest');
        if (!list) return;
        suggestLoading = true;
        lastSuggestItems = [];
        list.innerHTML = '';
        var li = document.createElement('li');
        li.className = 'livreur-address-suggest__empty livreur-address-suggest__loading';
        li.textContent = 'Recherche en cours…';
        list.appendChild(li);
        list.hidden = false;
        activeSuggestIndex = -1;
        setAddressSuggestExpanded(true);
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

    function showAddressSuggestions(items, meta) {
        var list = qs('livreur-address-suggest');
        if (!list) return;

        suggestLoading = false;
        lastSuggestItems = Array.isArray(items) ? items.slice() : [];
        list.innerHTML = '';
        if (!lastSuggestItems.length) {
            var empty = document.createElement('li');
            empty.className = 'livreur-address-suggest__empty';
            if (meta && meta.hint === 'geo_unavailable') {
                empty.textContent = 'Service de recherche indisponible. Vérifiez la connexion ou réessayez.';
            } else {
                empty.textContent = 'Aucun lieu trouvé. Essayez une adresse, un lien Google Maps ou des coordonnées GPS.';
            }
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

        var parsedCoords = parseLatLngFromText(q);
        if (parsedCoords) {
            showAddressSuggestions([parsedCoords]);
            return;
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        suggestAbort = controller;

        showAddressSuggestionsLoading();

        var url = '/api/geo-geocode-suggest.php?q=' + encodeURIComponent(q) + '&limit=6';
        fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) {
                    showAddressSuggestions([], { hint: 'geo_unavailable' });
                    return;
                }
                showAddressSuggestions(data.suggestions || [], data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                showAddressSuggestions([], { hint: 'geo_unavailable' });
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
        }, 380);
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
        adresseInput.addEventListener('paste', function () {
            setTimeout(function () {
                if (suppressSuggest || isComposing) return;
                var value = adresseInput.value.trim();
                if (!value) return;
                clearTimeout(suggestTimer);
                if (isLikelyMapsUrl(value) || parseLatLngFromText(value)) {
                    fetchAddressSuggestions(value);
                }
            }, 0);
        });
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

    function reverseGeocodeLabel(lat, lng) {
        return fetch('/api/geo-reverse.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.ok && data.label) {
                    return data.label;
                }
                return lat.toFixed(6) + ', ' + lng.toFixed(6);
            })
            .catch(function () {
                return lat.toFixed(6) + ', ' + lng.toFixed(6);
            });
    }

    function openPanel(btn) {
        if (!panel || !form) return;

        activeBtn = btn;
        formSubmitted = false;
        var livraisonType = btn.getAttribute('data-livraison-type') || 'commande';
        currentLivraisonType = livraisonType;
        var commandeId = btn.getAttribute('data-commande-id') || '';
        var blId = btn.getAttribute('data-bl-id') || '';
        var cpId = btn.getAttribute('data-cp-id') || '';
        var numero = btn.getAttribute('data-numero') || '';
        var client = btn.getAttribute('data-client') || '';
        var adresse = btn.getAttribute('data-adresse') || '';
        var adresseHistorique = btn.getAttribute('data-adresse-historique') === '1';
        var dLat = parseCoord(btn.getAttribute('data-delivery-lat'));
        var dLng = parseCoord(btn.getAttribute('data-delivery-lng'));
        originalClientAdresse = adresse;
        orderOriginalLat = dLat;
        orderOriginalLng = dLng;
        hasOrderGps = (dLat !== null && dLng !== null);

        var actionInput = qs('livreur-demarrage-action');
        var labelEl = qs('livreur-demarrage-label');
        if (livraisonType === 'facture') {
            if (actionInput) actionInput.value = 'commencer_livraison_facture';
            if (labelEl) labelEl.textContent = 'Facture';
            qs('livreur-demarrage-commande-id').value = '';
            qs('livreur-demarrage-bl-id').value = blId;
            if (qs('livreur-demarrage-cp-id')) qs('livreur-demarrage-cp-id').value = '';
            qs('livreur-demarrage-numero').textContent = client || 'Client B2B';
        } else if (livraisonType === 'personnalisee') {
            if (actionInput) actionInput.value = 'commencer_livraison_cp';
            if (labelEl) labelEl.textContent = 'Personnalisée';
            qs('livreur-demarrage-commande-id').value = '';
            if (qs('livreur-demarrage-bl-id')) qs('livreur-demarrage-bl-id').value = '';
            if (qs('livreur-demarrage-cp-id')) qs('livreur-demarrage-cp-id').value = cpId;
            qs('livreur-demarrage-numero').textContent = numero;
        } else {
            if (actionInput) actionInput.value = 'commencer_livraison';
            if (labelEl) labelEl.textContent = 'Commande';
            qs('livreur-demarrage-commande-id').value = commandeId;
            if (qs('livreur-demarrage-bl-id')) qs('livreur-demarrage-bl-id').value = '';
            if (qs('livreur-demarrage-cp-id')) qs('livreur-demarrage-cp-id').value = '';
            qs('livreur-demarrage-numero').textContent = numero;
        }

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
        document.documentElement.classList.add('livreur-demarrage-open');

        ensureMap();

        var adresseInput = qs('livreur-demarrage-adresse');
        if (adresseInput) {
            adresseInput.readOnly = false;
            adresseInput.value = adresse;
        }

        if (hasOrderGps) {
            showOrderGpsPanel(true);
            updateClientOnMap(dLat, dLng);
            if (adresseHistorique) {
                setStatus('ok', 'Adresse reprise d\'une livraison précédente (même téléphone) — vous pouvez la modifier.');
            } else {
                setStatus('ok', 'Position GPS du client chargée — vous pouvez la modifier ou rechercher une adresse.');
            }
        } else {
            showOrderGpsPanel(false);
            var restoreBtn = qs('livreur-gps-restore');
            if (restoreBtn) restoreBtn.hidden = true;
            if (adresse) {
                if (adresseHistorique) {
                    setStatus('ok', 'Adresse reprise d\'une livraison précédente (même téléphone).');
                }
                geocodeAddress(adresse);
            } else {
                setStatus('pending', 'Saisissez l\'adresse du client.');
            }
        }

        startWatch();
    }

    function closePanel() {
        stopWatch();
        hideAddressSuggestions();
        resetAddressUi();
        if (suggestAbort && typeof suggestAbort.abort === 'function') {
            suggestAbort.abort();
            suggestAbort = null;
        }
        if (panel) {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('livreur-demarrage-open');
        document.documentElement.classList.remove('livreur-demarrage-open');
        if (activeBtn) {
            activeBtn.removeAttribute('disabled');
        }
        activeBtn = null;
        activeRow = null;
        formSubmitted = false;
        if (form) {
            delete form.dataset.nativeDeliveryPermOk;
        }
        currentLivraisonType = 'commande';
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

        /* App native : divulgation GPS livreur (Google Play) avant redirection suivi.php */
        if (window.SugarPaperNative &&
            typeof window.SugarPaperNative.isNativeApp === 'function' &&
            window.SugarPaperNative.isNativeApp() &&
            typeof window.SugarPaperNative.prepareDeliveryTrackingPermissions === 'function' &&
            form.dataset.nativeDeliveryPermOk !== '1') {
            e.preventDefault();
            setStatus('pending', 'Autorisation suivi livraison…');
            window.SugarPaperNative.prepareDeliveryTrackingPermissions()
                .then(function () {
                    form.dataset.nativeDeliveryPermOk = '1';
                    setStatus('ok', 'Autorisation accordée — démarrage…');
                    formSubmitted = true;
                    form.submit();
                })
                .catch(function () {
                    setStatus('error', 'Autorisez le suivi GPS livraison pour continuer (écran « J\'accepte »).');
                });
            return;
        }

        formSubmitted = true;
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
        document.documentElement.classList.remove('livreur-demarrage-open');

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.livreur-btn-prendre');
            if (btn) {
                e.preventDefault();
                openPanel(btn);
                if (hasOrderGps) {
                    setStatus('ok', 'Position GPS du client chargée — capture de votre position…');
                } else {
                    setStatus('pending', 'Capture de votre position en cours… Autorisez l\'accès GPS.');
                }
                return;
            }
            if (e.target.closest('[data-livreur-demarrage-close]')) {
                e.preventDefault();
                closePanel();
            }
        });

        bindAddressAutocomplete();

        var restoreBtn = qs('livreur-gps-restore');
        if (restoreBtn) {
            restoreBtn.addEventListener('click', function (e) {
                e.preventDefault();
                applyOrderOriginalGps({ resetAdresse: true });
            });
        }

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
