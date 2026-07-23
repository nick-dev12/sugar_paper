/**
 * Partage localisation — feuille native ou modal platformShareModal.
 */
(function () {
    'use strict';

    function enc(v) {
        return encodeURIComponent(v == null ? '' : String(v));
    }

    function parseCoord(v) {
        var n = parseFloat(v);
        return isNaN(n) ? null : n;
    }

    function mapsPointUrl(lat, lng) {
        return 'https://maps.google.com/?q=' + lat + ',' + lng;
    }

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
        if (typeof window.openPlatformShareModal === 'function') {
            window.openPlatformShareModal({
                modalTitle: opts.modalTitle || 'Partager la localisation',
                title: title,
                url: url,
                message: message,
                hint: opts.hint || 'Partagez ce lien pour indiquer l\'emplacement sur la carte.'
            });
        } else {
            window.open('https://wa.me/?text=' + enc(title + ' : ' + url), '_blank', 'noopener,noreferrer');
        }
    };

    function readGeoBtn(el) {
        return {
            lat: el.getAttribute('data-lat'),
            lng: el.getAttribute('data-lng'),
            label: el.getAttribute('data-label') || el.getAttribute('data-share-title') || '',
            url: el.getAttribute('data-share-url') || '',
            title: el.getAttribute('data-share-title') || el.getAttribute('data-label') || 'Localisation',
            message: el.getAttribute('data-share-text') || '',
            modalTitle: el.getAttribute('data-share-modal-title') || '',
            hint: el.getAttribute('data-share-hint') || ''
        };
    }

    document.addEventListener('click', function (e) {
        var shareBtn = e.target.closest('.js-geo-share-location');
        if (!shareBtn) {
            return;
        }
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
    });
})();
