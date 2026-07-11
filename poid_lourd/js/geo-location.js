/**
 * Capture de la position GPS du client (Geolocation API navigateur).
 * Rôle : UI uniquement — remplit des champs cachés d'un formulaire classique,
 * affiche une carte Leaflet en direct et pré-remplit l'adresse (géocodage inverse).
 * La soumission des données passe toujours par le POST du formulaire.
 *
 * Utilisation :
 *   GeoLocationCapture.init({
 *     latInput: 'geo_lat',             // id du champ caché latitude
 *     lngInput: 'geo_lng',             // id du champ caché longitude
 *     precisionInput: 'geo_precision', // id du champ caché précision (m)
 *     sourceInput: 'geo_source',       // id du champ caché source ('gps'|'map_pin')
 *     statusEl: 'geo-status',          // id de l'élément d'état affiché
 *     button: 'btn-geo-capture',       // id du bouton de capture (optionnel)
 *     mapContainer: 'geo-map',         // id du conteneur carte Leaflet (optionnel)
 *     addressInput: 'adresse_livraison', // id du champ adresse à pré-remplir (optionnel)
 *     initial: { lat: 14.69, lng: -17.44, precision: 10 }, // position déjà connue (optionnel)
 *     auto: true,                      // capturer automatiquement au chargement
 *     onSuccess: function(lat,lng,precision){},
 *     onError: function(err){}
 *   });
 */
(function () {
    'use strict';

    var GeoLocationCapture = {
        opts: null,
        map: null,
        marker: null,
        accuracyCircle: null,

        init: function (options) {
            this.opts = options || {};
            var self = this;

            var btn = this.opts.button ? document.getElementById(this.opts.button) : null;
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.capture();
                });
            }

            // Position déjà connue (session / BDD) : afficher carte + adresse sans re-capturer
            var ini = this.opts.initial;
            if (ini && typeof ini.lat === 'number' && typeof ini.lng === 'number') {
                this.applyPosition(ini.lat, ini.lng, ini.precision || null, 'gps', { quiet: true });
                this.setStatus('ok', 'Position enregistr\u00e9e charg\u00e9e. D\u00e9placez le marqueur sur la carte pour l\u2019ajuster.');
            }

            if (this.opts.auto) {
                this.capture(true);
            }
        },

        setStatus: function (state, message) {
            var el = this.opts.statusEl ? document.getElementById(this.opts.statusEl) : null;
            if (!el) return;
            el.style.display = '';
            el.setAttribute('data-geo-state', state);
            var icons = {
                pending: '<i class="fas fa-circle-notch fa-spin"></i>',
                ok: '<i class="fas fa-location-crosshairs"></i>',
                error: '<i class="fas fa-triangle-exclamation"></i>'
            };
            el.innerHTML = (icons[state] || '') + ' ' + message;
        },

        fill: function (id, value) {
            var input = id ? document.getElementById(id) : null;
            if (input) input.value = value;
        },

        /* ---- Carte Leaflet ---- */

        ensureMap: function (lat, lng) {
            if (!this.opts.mapContainer || typeof window.L === 'undefined') return null;
            var container = document.getElementById(this.opts.mapContainer);
            if (!container) return null;

            container.style.display = '';

            if (this.map) {
                return this.map;
            }

            var self = this;
            this.map = L.map(container.id).setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(this.map);

            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.bindPopup('Votre position \u2014 d\u00e9placez le marqueur pour ajuster').openPopup();

            // Ajustement manuel : le pin devient la position de référence
            this.marker.on('dragend', function () {
                var p = self.marker.getLatLng();
                self.applyPosition(p.lat, p.lng, null, 'map_pin', { keepView: true });
                self.setStatus('ok', 'Position ajust\u00e9e sur la carte. Elle sera transmise au vendeur.');
            });

            // La carte est parfois créée dans un bloc qui vient d'apparaître
            setTimeout(function () { self.map.invalidateSize(); }, 150);

            return this.map;
        },

        updateMap: function (lat, lng, precision, keepView) {
            var map = this.ensureMap(lat, lng);
            if (!map) return;
            this.marker.setLatLng([lat, lng]);
            if (this.accuracyCircle) {
                map.removeLayer(this.accuracyCircle);
                this.accuracyCircle = null;
            }
            if (precision && precision > 0 && precision < 5000) {
                this.accuracyCircle = L.circle([lat, lng], {
                    radius: precision,
                    color: '#3564a6',
                    fillColor: '#3564a6',
                    fillOpacity: 0.12,
                    weight: 1
                }).addTo(map);
            }
            if (!keepView) {
                map.setView([lat, lng], 16);
            }
        },

        /* ---- Adresse (géocodage inverse Nominatim, affichage concis) ---- */

        conciseAddress: function (data) {
            if (window.GeoAddressFormat && typeof window.GeoAddressFormat.fromNominatim === 'function') {
                return window.GeoAddressFormat.fromNominatim(data);
            }
            if (!data) return '';
            if (data.display_name && window.GeoAddressFormat && typeof window.GeoAddressFormat.fromDisplayName === 'function') {
                return window.GeoAddressFormat.fromDisplayName(data.display_name);
            }
            return '';
        },

        fillAddress: function (lat, lng, force) {
            var input = this.opts.addressInput ? document.getElementById(this.opts.addressInput) : null;
            if (!input) return;
            if (!force && input.value.trim() !== '' && input.dataset.geoAutofilled !== '1') return;

            var applyLabel = function (label) {
                if (label) {
                    input.value = label;
                    input.dataset.geoAutofilled = '1';
                }
            };

            if (window.GeoNativeBridge && typeof window.GeoNativeBridge.reverseGeocode === 'function') {
                window.GeoNativeBridge.reverseGeocode(lat, lng).then(applyLabel).catch(function () {});
                return;
            }
            if (window.GeoAddressFormat && typeof window.GeoAddressFormat.fromNominatim === 'function') {
                var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=17'
                    + '&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) { applyLabel(window.GeoAddressFormat.fromNominatim(data)); })
                    .catch(function () {});
            }
        },

        /** Définit l'adresse texte (recherche ou collage) sans écraser si force=false */
        setAddressValue: function (text, force) {
            var input = this.opts.addressInput ? document.getElementById(this.opts.addressInput) : null;
            if (!input || !text) return;
            if (force || input.value.trim() === '' || input.dataset.geoAutofilled === '1') {
                input.value = text;
                input.dataset.geoAutofilled = '1';
            }
        },

        /* ---- Application d'une position (champs + carte + adresse) ---- */

        applyPosition: function (lat, lng, precision, source, flags) {
            flags = flags || {};
            this.fill(this.opts.latInput, lat.toFixed(8));
            this.fill(this.opts.lngInput, lng.toFixed(8));
            this.fill(this.opts.precisionInput, precision !== null && precision !== undefined ? Math.round(precision) : '');
            this.fill(this.opts.sourceInput, source || 'gps');
            this.updateMap(lat, lng, precision, !!flags.keepView);
            if (flags.addressText) {
                this.setAddressValue(flags.addressText, true);
            } else {
                this.fillAddress(lat, lng, !!flags.forceAddress);
            }
            if (!flags.quiet && typeof this.opts.onSuccess === 'function') {
                this.opts.onSuccess(lat, lng, precision);
            }
        },

        /* ---- Capture GPS ---- */

        capture: function (silent, extra) {
            extra = extra || {};
            var self = this;
            var geoOptions = {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: extra.fresh ? 0 : 60000
            };

            function onSuccess(pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var precision = Math.round(pos.coords.accuracy || 0);
                self.applyPosition(lat, lng, precision, 'gps');
                self.setStatus('ok', 'Position captur\u00e9e (\u00b1 ' + precision + ' m). Ajustez le marqueur sur la carte si besoin.');
            }

            function onError(err) {
                var msg;
                if (err && err.code === 1) {
                    msg = 'Localisation refus\u00e9e. Vous pouvez quand m\u00eame commander : renseignez bien votre adresse.';
                } else if (err && err.code === 2) {
                    msg = 'Position indisponible pour le moment. Renseignez votre adresse de livraison.';
                } else {
                    msg = 'D\u00e9lai d\u00e9pass\u00e9 pour obtenir la position. Renseignez votre adresse de livraison.';
                }
                if (silent && self.opts.initial) {
                    self.setStatus('ok', 'Position enregistr\u00e9e conserv\u00e9e. Cliquez sur le bouton pour la mettre \u00e0 jour.');
                } else {
                    self.setStatus('error', msg);
                }
                if (typeof self.opts.onError === 'function') {
                    self.opts.onError(err);
                }
            }

            var runCapture = window.GeoNativeBridge && typeof window.GeoNativeBridge.getCurrentPosition === 'function'
                ? window.GeoNativeBridge.getCurrentPosition.bind(window.GeoNativeBridge)
                : null;

            if (!runCapture && !('geolocation' in navigator)) {
                if (!silent) self.setStatus('error', 'La g\u00e9olocalisation n\u2019est pas disponible sur cet appareil.');
                return;
            }

            self.setStatus('pending', 'Recherche de votre position\u2026');

            var promise = runCapture
                ? runCapture(geoOptions)
                : new Promise(function (resolve, reject) {
                    navigator.geolocation.getCurrentPosition(resolve, reject, geoOptions);
                });

            promise.then(onSuccess).catch(onError);
        }
    };

    window.GeoLocationCapture = GeoLocationCapture;
})();
