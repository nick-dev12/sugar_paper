/**
 * Capture GPS à l'inscription (client / vendeur) — UI uniquement.
 * App native : GeoNativeBridge (ColobanesNative + geolocator).
 */
(function () {
    'use strict';

    function qs(id) { return document.getElementById(id); }

    function reverseGeocodeConcise(lat, lng, cb) {
        if (window.GeoNativeBridge && typeof window.GeoNativeBridge.reverseGeocode === 'function') {
            window.GeoNativeBridge.reverseGeocode(lat, lng).then(function (label) {
                cb(label || '');
            }).catch(function () { cb(''); });
            return;
        }
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=17&lat='
            + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { cb(''); return; }
                if (window.GeoAddressFormat && typeof window.GeoAddressFormat.fromNominatim === 'function') {
                    cb(window.GeoAddressFormat.fromNominatim(data));
                    return;
                }
                cb(data.display_name || '');
            })
            .catch(function () { cb(''); });
    }

    function fillCoords(lat, lng, accuracy) {
        var latEl = qs('insc_geo_lat');
        var lngEl = qs('insc_geo_lng');
        var precEl = qs('insc_geo_precision');
        if (latEl) latEl.value = lat.toFixed(8);
        if (lngEl) lngEl.value = lng.toFixed(8);
        if (precEl && accuracy != null) precEl.value = Math.round(accuracy);
    }

    function capturePosition(onSuccess, onError, options) {
        options = options || {};
        if (window.GeoNativeBridge && typeof window.GeoNativeBridge.getCurrentPosition === 'function') {
            window.GeoNativeBridge.getCurrentPosition(options).then(onSuccess).catch(onError);
            return;
        }
        if (!('geolocation' in navigator)) {
            onError();
            return;
        }
        navigator.geolocation.getCurrentPosition(onSuccess, onError, {
            enableHighAccuracy: true,
            timeout: options.timeout || 12000,
            maximumAge: options.maximumAge != null ? options.maximumAge : 300000
        });
    }

    function bindSubmitCapture() {
        var forms = [
            qs('inscriptionVendeurForm'),
            qs('inscriptionForm'),
            qs('googleCompleteForm')
        ].filter(Boolean);

        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var latEl = qs('insc_geo_lat');
                if (!latEl || latEl.value) {
                    return;
                }
                e.preventDefault();
                var done = false;
                function finish() {
                    if (done) return;
                    done = true;
                    form.submit();
                }
                capturePosition(
                    function (pos) {
                        fillCoords(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                        var flag = qs('insc_geo_manual');
                        if (flag) flag.value = '1';
                        finish();
                    },
                    finish,
                    { maximumAge: 0, timeout: 8000 }
                );
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindSubmitCapture();
    });
})();
