/**
 * Recherche d'adresse / lieu avec suggestions dynamiques (Nominatim).
 * UI uniquement — met à jour GeoLocationCapture (carte, champs cachés, adresse).
 * Les suggestions sont filtrées par pays (countryCode = code ISO du marketplace).
 */
(function () {
    'use strict';

    var GeoLocationSearch = {
        opts: null,
        debounceTimer: null,
        lastQuery: '',

        init: function (options) {
            this.opts = options || {};
            this.opts.countryCode = (this.opts.countryCode || 'sn').toLowerCase();
            var input = document.getElementById(this.opts.searchInput);
            var list = document.getElementById(this.opts.suggestionsList);
            if (!input || !list) return;

            var self = this;

            input.addEventListener('input', function () {
                clearTimeout(self.debounceTimer);
                var q = input.value.trim();
                if (q.length < 2) {
                    self.hideSuggestions();
                    return;
                }
                self.debounceTimer = setTimeout(function () {
                    self.fetchSuggestions(q);
                }, 350);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var q = input.value.trim();
                    if (q.length >= 2) self.searchAndApply(q);
                }
            });

            input.addEventListener('blur', function () {
                setTimeout(function () {
                    if (!list.contains(document.activeElement)) {
                        self.hideSuggestions();
                    }
                }, 180);
            });

            list.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
        },

        hideSuggestions: function () {
            var list = document.getElementById(this.opts.suggestionsList);
            if (list) {
                list.innerHTML = '';
                list.style.display = 'none';
                list.setAttribute('aria-hidden', 'true');
            }
        },

        countryParam: function () {
            var cc = (this.opts.countryCode || 'sn').toLowerCase();
            return '&countrycodes=' + encodeURIComponent(cc);
        },

        /** Filtre de sécurité côté client (Nominatim renvoie parfois un voisin) */
        filterByCountry: function (items) {
            var cc = (this.opts.countryCode || 'sn').toLowerCase();
            if (!Array.isArray(items)) return [];
            return items.filter(function (item) {
                if (item.address && item.address.country_code) {
                    return String(item.address.country_code).toLowerCase() === cc;
                }
                return true;
            });
        },

        emptyMessage: function () {
            var label = this.opts.countryLabel || 'votre pays';
            return 'Aucun r\u00e9sultat au ' + label + '. Essayez un quartier, une rue ou une ville.';
        },

        showSuggestions: function (items) {
            var list = document.getElementById(this.opts.suggestionsList);
            if (!list) return;
            var self = this;
            list.innerHTML = '';

            if (!items.length) {
                list.innerHTML = '<div class="geo-search-empty">' + self.escapeHtml(self.emptyMessage()) + '</div>';
            } else {
                items.forEach(function (item) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'geo-search-item';
                    var label = self.conciseLabel(item) || item.display_name || '';
                    btn.innerHTML = '<i class="fas fa-location-dot"></i><span>' + self.escapeHtml(label) + '</span>';
                    btn.addEventListener('click', function () {
                        self.selectResult(item);
                    });
                    list.appendChild(btn);
                });
            }

            list.style.display = 'block';
            list.setAttribute('aria-hidden', 'false');
        },

        escapeHtml: function (s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        },

        conciseLabel: function (item) {
            if (!item) return '';
            if (window.GeoAddressFormat) {
                if (item.address && typeof window.GeoAddressFormat.fromParts === 'function') {
                    var fromParts = window.GeoAddressFormat.fromParts(item.address);
                    if (fromParts) return fromParts;
                }
                if (typeof window.GeoAddressFormat.fromDisplayName === 'function') {
                    return window.GeoAddressFormat.fromDisplayName(item.display_name || '');
                }
            }
            return item.display_name || '';
        },

        buildSearchUrl: function (query, limit) {
            return 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=' + (limit || 6)
                + '&q=' + encodeURIComponent(query)
                + '&accept-language=fr'
                + '&addressdetails=1'
                + this.countryParam();
        },

        fetchSuggestions: function (query) {
            var self = this;
            self.lastQuery = query;
            var url = self.buildSearchUrl(query, 8);

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (self.lastQuery !== query) return;
                    var filtered = self.filterByCountry(Array.isArray(data) ? data : []).slice(0, 6);
                    self.showSuggestions(filtered);
                })
                .catch(function () {
                    self.hideSuggestions();
                });
        },

        searchAndApply: function (query) {
            var self = this;
            var url = self.buildSearchUrl(query, 5);

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    var filtered = self.filterByCountry(Array.isArray(data) ? data : []);
                    if (filtered[0]) {
                        self.selectResult(filtered[0]);
                    } else if (window.GeoLocationCapture) {
                        var label = self.opts.countryLabel || 'votre pays';
                        window.GeoLocationCapture.setStatus('error',
                            'Adresse introuvable au ' + label + '. Pr\u00e9cisez le quartier ou la ville.');
                    }
                });
        },

        selectResult: function (item) {
            var lat = parseFloat(item.lat);
            var lng = parseFloat(item.lon);
            if (isNaN(lat) || isNaN(lng)) return;

            var cc = (this.opts.countryCode || 'sn').toLowerCase();
            if (item.address && item.address.country_code
                && String(item.address.country_code).toLowerCase() !== cc) {
                return;
            }

            var searchInput = document.getElementById(this.opts.searchInput);
            var shortLabel = this.conciseLabel(item) || item.display_name || '';
            if (searchInput) {
                searchInput.value = shortLabel || searchInput.value;
            }

            this.hideSuggestions();

            if (window.GeoLocationCapture) {
                window.GeoLocationCapture.applyPosition(lat, lng, null, 'adresse', {
                    addressText: shortLabel,
                    forceAddress: true
                });
                window.GeoLocationCapture.setStatus('ok',
                    'Adresse s\u00e9lectionn\u00e9e. V\u00e9rifiez le point sur la carte ou d\u00e9placez le marqueur.');
            }
        }
    };

    window.GeoLocationSearch = GeoLocationSearch;
})();
