/**
 * Navigation carte native + partage localisation (modal unifiée platformShareModal).
 */
(function () {
    'use strict';

    function enc(v) {
        return encodeURIComponent(v == null ? '' : String(v));
    }

    function isMobileOrTablet() {
        var ua = navigator.userAgent || '';
        if (/Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini|Silk|Kindle/i.test(ua)) {
            return true;
        }
        if (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) {
            return true;
        }
        if (/Android/i.test(ua) && !/Mobile/i.test(ua)) {
            return true;
        }
        return false;
    }

    function isIOS() {
        var ua = navigator.userAgent || '';
        return /iPad|iPhone|iPod/.test(ua)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function isInNativeApp() {
        return !!(window.__COLOBANES_NATIVE_APP || window.flutter_inappwebview
            || /ColobanesApp/i.test(navigator.userAgent || ''));
    }

    /**
     * Ouvre une URL hors WebView (app Flutter) pour éviter la page blanche sur geo: / maps.
     */
    function openExternalUrl(url) {
        if (!url) {
            return false;
        }
        if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
            try {
                window.flutter_inappwebview.callHandler('openExternalUrl', url);
                return true;
            } catch (e) {
                /* fallback ci-dessous */
            }
        }
        if (window.ColobanesNative && typeof window.ColobanesNative.openExternalUrl === 'function') {
            try {
                window.ColobanesNative.openExternalUrl(url);
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    function parseCoord(v) {
        var n = parseFloat(v);
        return isNaN(n) ? null : n;
    }

    function mapsPointUrl(lat, lng) {
        return 'https://maps.google.com/?q=' + lat + ',' + lng;
    }

    function mapsDirUrl(lat, lng) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng + '&travelmode=driving';
    }

    /**
     * Liens navigation (legacy — autres écrans).
     */
    window.geoBuildNavApps = function (lat, lng, label) {
        label = label || 'Position client';
        var gmaps = mapsDirUrl(lat, lng);
        var wa = 'https://wa.me/?text=' + enc(label + ' : ' + mapsPointUrl(lat, lng));
        return [
            { name: 'Google Maps', icon: 'fab fa-google', cls: 'gmaps', url: gmaps },
            { name: 'Partager la localisation', icon: 'fab fa-whatsapp', cls: 'whatsapp', url: wa }
        ];
    };

    /**
     * Ouvre la feuille native « Ouvrir avec… » (Maps, Waze, etc.) via geo: / maps:// / lien universel.
     */
    window.geoOpenNativeNavigation = function (lat, lng, label, fallbackUrl) {
        var latN = parseCoord(lat);
        var lngN = parseCoord(lng);
        label = (label || 'Destination').trim();

        if (latN === null || lngN === null) {
            if (fallbackUrl) {
                window.location.href = fallbackUrl;
            }
            return;
        }

        var geoHref = 'geo:' + latN + ',' + lngN + '?q=' + latN + ',' + lngN + '(' + enc(label) + ')';
        var gmapsDir = fallbackUrl || mapsDirUrl(latN, lngN);
        var appleMaps = 'maps://?daddr=' + latN + ',' + lngN;

        /* WebView Flutter : geo:/maps:// chargent une page blanche — ouvrir hors WebView */
        if (isInNativeApp()) {
            if (openExternalUrl(gmapsDir)) {
                return;
            }
            if (openExternalUrl(geoHref)) {
                return;
            }
            window.location.href = gmapsDir;
            return;
        }

        if (isIOS()) {
            if (isInNativeApp()) {
                openExternalUrl(appleMaps);
                return;
            }
            window.location.href = appleMaps;
            window.setTimeout(function () {
                window.location.href = gmapsDir;
            }, 400);
            return;
        }

        if (isMobileOrTablet()) {
            var link = document.createElement('a');
            link.href = geoHref;
            link.rel = 'noopener noreferrer';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            return;
        }

        window.open(gmapsDir, '_blank', 'noopener,noreferrer');
    };

    /**
     * Partage localisation — même approche que produits / boutique (platformShareModal).
     */
    window.geoOpenLocationShare = function (opts) {
        opts = opts || {};
        var latN = parseCoord(opts.lat);
        var lngN = parseCoord(opts.lng);
        var url = (opts.url || '').trim();
        if (!url && latN !== null && lngN !== null) {
            url = mapsPointUrl(latN, lngN);
        }
        if (!url) {
            return;
        }
        var title = (opts.title || opts.label || 'Localisation').trim();
        var message = (opts.message || title).trim();
        if (message.indexOf(url) !== -1) {
            message = message.replace(url, '').replace(/\s*:\s*$/, '').trim();
        }
        if (!message) {
            message = title;
        }
        var fullMessage = title + ' : ' + url;
        if (typeof window.openPlatformShareModal === 'function') {
            window.openPlatformShareModal({
                modalTitle: opts.modalTitle || 'Partager la localisation',
                title: title,
                url: url,
                message: message,
                hint: opts.hint || 'Partagez ce lien pour indiquer l\'emplacement sur la carte.'
            });
        } else {
            window.open('https://wa.me/?text=' + enc(fullMessage), '_blank', 'noopener,noreferrer');
        }
    };

    function readGeoBtn(el) {
        return {
            lat: el.getAttribute('data-lat'),
            lng: el.getAttribute('data-lng'),
            label: el.getAttribute('data-label') || el.getAttribute('data-share-title') || '',
            url: el.getAttribute('data-share-url') || el.getAttribute('data-maps-url') || '',
            title: el.getAttribute('data-share-title') || el.getAttribute('data-label') || 'Localisation',
            message: el.getAttribute('data-share-text') || '',
            modalTitle: el.getAttribute('data-share-modal-title') || '',
            hint: el.getAttribute('data-share-hint') || ''
        };
    }

    document.addEventListener('click', function (e) {
        var navBtn = e.target.closest('.js-geo-open-maps');
        if (navBtn) {
            e.preventDefault();
            var d = readGeoBtn(navBtn);
            window.geoOpenNativeNavigation(d.lat, d.lng, d.label, d.url);
            return;
        }
        var shareBtn = e.target.closest('.js-geo-share-location');
        if (shareBtn) {
            e.preventDefault();
            var s = readGeoBtn(shareBtn);
            window.geoOpenLocationShare({
                lat: s.lat,
                lng: s.lng,
                url: s.url,
                title: s.title,
                label: s.label,
                message: s.message,
                modalTitle: s.modalTitle,
                hint: s.hint
            });
        }
    });
})();
