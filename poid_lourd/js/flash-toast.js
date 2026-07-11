(function () {
    'use strict';

    var ICONS = {
        success: 'fa-check',
        error:   'fa-xmark',
        info:    'fa-circle-info',
        warning: 'fa-triangle-exclamation'
    };

    var TITLES = {
        success: 'Succès',
        error:   'Erreur',
        info:    'Information',
        warning: 'Attention'
    };

    var QUERY_STRIP = [
        'added', 'recommande', 'receive_ok', 'commande_annulee',
        'livraison_confirmee', 'error', 'success', 'numeros', 'numero', 'count'
    ];

    var queue = [];
    var hostEl = null;

    function escHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    function getHost() {
        if (hostEl && document.body.contains(hostEl)) {
            return hostEl;
        }
        hostEl = document.createElement('div');
        hostEl.id = 'flashToastHost';
        hostEl.className = 'flash-toast-host';
        hostEl.setAttribute('aria-live', 'polite');
        hostEl.setAttribute('aria-atomic', 'true');
        document.body.appendChild(hostEl);
        return hostEl;
    }

    function stripFlashQueryParams() {
        try {
            var url = new URL(window.location.href);
            var changed = false;
            QUERY_STRIP.forEach(function (key) {
                if (url.searchParams.has(key)) {
                    url.searchParams.delete(key);
                    changed = true;
                }
            });
            if (changed && window.history && window.history.replaceState) {
                var qs = url.searchParams.toString();
                window.history.replaceState({}, '', url.pathname + (qs ? '?' + qs : '') + url.hash);
            }
        } catch (e) { /* ignore */ }
    }

    function dismissPopup(popup) {
        if (!popup || popup.classList.contains('is-leaving')) return;
        popup.classList.remove('is-visible');
        popup.classList.add('is-leaving');
        setTimeout(function () {
            if (popup.parentNode) popup.parentNode.removeChild(popup);
            processQueue();
        }, 320);
    }

    function processQueue() {
        var host = getHost();
        if (host.querySelector('.flash-popup:not(.is-leaving)')) return;
        if (queue.length === 0) return;
        show(queue.shift());
    }

    function show(item) {
        var type = (item && ICONS[item.type]) ? item.type : 'info';
        var msg  = item && item.message ? String(item.message) : '';
        if (!item) {
            processQueue();
            return;
        }

        var host = getHost();
        var popup = document.createElement('div');
        popup.className = 'flash-popup flash-popup--' + type;
        popup.setAttribute('role', type === 'error' ? 'alert' : 'status');
        if (msg) {
            popup.setAttribute('aria-label', TITLES[type] + '. ' + msg);
        } else {
            popup.setAttribute('aria-label', TITLES[type]);
        }
        popup.innerHTML =
            '<div class="flash-popup__icon" aria-hidden="true"><i class="fas ' + ICONS[type] + '"></i></div>' +
            '<h2 class="flash-popup__title">' + escHtml(TITLES[type]) + '</h2>' +
            '<button type="button" class="flash-popup__done">Terminé</button>';

        popup.querySelector('.flash-popup__done').addEventListener('click', function () {
            dismissPopup(popup);
        });

        host.appendChild(popup);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                popup.classList.add('is-visible');
            });
        });

        setTimeout(function () { dismissPopup(popup); }, 5200);
    }

    function enqueue(item) {
        queue.push(item);
        processQueue();
    }

    function boot() {
        var list = Array.isArray(window.__FLASH_TOASTS__) ? window.__FLASH_TOASTS__ : [];
        window.__FLASH_TOASTS__ = [];
        list.forEach(function (item) {
            if (item && (item.message || item.type)) {
                queue.push(item);
            }
        });
        if (list.length > 0) {
            stripFlashQueryParams();
        }
        processQueue();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.FlashToast = {
        show:    function (type, msg) { enqueue({ type: type, message: msg }); },
        success: function (msg)       { enqueue({ type: 'success', message: msg }); },
        error:   function (msg)       { enqueue({ type: 'error',   message: msg }); },
        info:    function (msg)       { enqueue({ type: 'info',    message: msg }); },
        warning: function (msg)       { enqueue({ type: 'warning', message: msg }); }
    };
})();
