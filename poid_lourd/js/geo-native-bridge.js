/**
 * Pont géolocalisation WebView ↔ app native COLObanes (ColobanesNative / geolocator).
 * Priorité : GPS natif Flutter, repli navigator.geolocation.
 * Géocodage inverse via /api/geo-reverse.php (format serveur = geo_geocoder.php).
 */
(function (global) {
    'use strict';

    function isNativeApp() {
        return !!(global.__COLOBANES_NATIVE_APP && global.ColobanesNative
            && typeof global.ColobanesNative.requestLocation === 'function');
    }

    function waitForNativeBridge(maxMs) {
        maxMs = maxMs || 5000;
        if (isNativeApp()) {
            return Promise.resolve();
        }
        if (!global.__COLOBANES_NATIVE_APP) {
            return Promise.reject(new Error('not-native'));
        }
        return new Promise(function (resolve, reject) {
            var start = Date.now();
            var timer = setInterval(function () {
                if (isNativeApp()) {
                    clearInterval(timer);
                    resolve();
                    return;
                }
                if (Date.now() - start >= maxMs) {
                    clearInterval(timer);
                    reject(new Error('native-bridge-timeout'));
                }
            }, 50);
        });
    }

    function browserGetPosition(options) {
        options = options || {};
        return new Promise(function (resolve, reject) {
            if (!('geolocation' in navigator)) {
                reject(new Error('Géolocalisation indisponible'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve(pos); },
                function (err) { reject(err); },
                {
                    enableHighAccuracy: options.enableHighAccuracy !== false,
                    timeout: options.timeout || 12000,
                    maximumAge: options.maximumAge != null ? options.maximumAge : 60000
                }
            );
        });
    }

    function nativeGetPosition() {
        return global.ColobanesNative.requestLocation().then(function (result) {
            if (!result || result.success === false) {
                throw new Error((result && result.error) ? result.error : 'Position native indisponible');
            }
            var lat = parseFloat(result.latitude);
            var lng = parseFloat(result.longitude);
            if (isNaN(lat) || isNaN(lng)) {
                throw new Error('Coordonnées natives invalides');
            }
            return {
                coords: {
                    latitude: lat,
                    longitude: lng,
                    accuracy: result.accuracy != null ? parseFloat(result.accuracy) : 0
                }
            };
        });
    }

    function getCurrentPosition(options) {
        options = options || {};
        if (global.__COLOBANES_NATIVE_APP) {
            return waitForNativeBridge(options.nativeWaitMs).then(function () {
                return nativeGetPosition();
            }).catch(function () {
                return browserGetPosition(options);
            });
        }
        return browserGetPosition(options);
    }

    function reverseGeocode(lat, lng) {
        var url = '/api/geo-reverse.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.success && data.address) {
                    return data.address;
                }
                return nominatimFallback(lat, lng);
            })
            .catch(function () { return nominatimFallback(lat, lng); });
    }

    function nominatimFallback(lat, lng) {
        if (global.GeoAddressFormat && typeof global.GeoAddressFormat.fromNominatim === 'function') {
            var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=17&lat='
                + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
            return fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    return data ? global.GeoAddressFormat.fromNominatim(data) : '';
                })
                .catch(function () { return ''; });
        }
        return Promise.resolve('');
    }

    global.GeoNativeBridge = {
        isNativeApp: isNativeApp,
        getCurrentPosition: getCurrentPosition,
        reverseGeocode: reverseGeocode
    };
})(typeof window !== 'undefined' ? window : this);
