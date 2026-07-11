/**
 * Partage unifié — produits + boutique vendeur.
 * Mobile / tablette / app : feuille de partage native du système.
 * Ordinateur : modal avec canaux (WhatsApp, Gmail, Facebook, Telegram).
 */

(function () {
    'use strict';

    var modal = null;
    var titleEl = null;
    var urlEl = null;
    var hintEl = null;
    var btnCopy = null;
    var lastFocus = null;
    var state = { url: '', title: '', message: '', hint: '' };

    function enc(v) {
        return encodeURIComponent(v == null ? '' : String(v));
    }

    function isInNativeApp() {
        return !!(window.__COLOBANES_NATIVE_APP || window.flutter_inappwebview
            || /ColobanesApp/i.test(navigator.userAgent || ''));
    }

    /**
     * Téléphone ou tablette (y compris iPadOS déguisé en Mac, tablettes Android).
     */
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

    function shouldUseNativeShare() {
        return isInNativeApp() || isMobileOrTablet();
    }

    /**
     * Texte de partage sans l'URL (évite la duplication quand url est aussi envoyée).
     */
    function buildShareText(opts) {
        var url = (opts.url || '').trim();
        var title = (opts.title || opts.modalTitle || 'Partager').trim();
        var text = (opts.message || title).trim();
        if (url && text.indexOf(url) !== -1) {
            text = text.replace(url, '').replace(/\s*:\s*$/, '').trim();
        }
        if (!text) {
            text = title;
        }
        return text;
    }

    /**
     * Construit la charge utile commune au partage natif.
     */
    function buildSharePayload(opts) {
        var url = (opts.url || '').trim();
        var title = (opts.title || opts.modalTitle || 'Partager').trim();
        var text = buildShareText(opts);
        /* Partage de lien : texte court sans URL (le champ url suffit — évite duplication Facebook/WhatsApp) */
        if (url) {
            if (text === url || text.indexOf(url) !== -1) {
                text = title;
            }
            return { title: title, text: text, url: url };
        }
        return { title: title, text: text || title, url: '' };
    }

    /**
     * Partage via le pont natif Flutter (retourne une Promise bool).
     */
    function nativeShareViaBridge(payload) {
        if (window.ColobanesNative && typeof window.ColobanesNative.shareContent === 'function') {
            return window.ColobanesNative.shareContent(payload || {})
                .then(function () { return true; })
                .catch(function () { return false; });
        }
        if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
            return window.flutter_inappwebview.callHandler('shareContent', payload || {})
                .then(function (result) { return !!(result && result.success); })
                .catch(function () { return false; });
        }
        return Promise.resolve(false);
    }

    /**
     * Partage via l'API Web standard (feuille native iOS / mobile web).
     */
    function nativeShareViaWebApi(payload) {
        if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
            try {
                var shareData = { title: payload.title || '' };
                if (payload.url) {
                    shareData.url = payload.url;
                    if (payload.text && payload.text.indexOf(payload.url) === -1) {
                        shareData.text = payload.text;
                    }
                } else if (payload.text) {
                    shareData.text = payload.text;
                }
                var p = navigator.share(shareData);
                if (p && typeof p.catch === 'function') {
                    p.catch(function () {});
                }
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Déclenche la feuille de partage native du système (app Flutter ou navigateur mobile).
     */
    function tryNativeShare(opts) {
        if (!opts || !opts.url) {
            return Promise.resolve(false);
        }
        var payload = buildSharePayload(opts);
        return nativeShareViaBridge(payload).then(function (bridgeOk) {
            if (bridgeOk) {
                return true;
            }
            return nativeShareViaWebApi(payload);
        });
    }

    function showMobileShareFallback(opts) {
        var url = (opts && opts.url) ? opts.url : '';
        if (!url) {
            return;
        }
        copyText(url).then(function () {
            if (window.FlashToast && typeof window.FlashToast.success === 'function') {
                window.FlashToast.success('Lien copié dans le presse-papiers.');
            }
        }).catch(function () {});
    }

    /**
     * Message canal desktop : inclut toujours l'URL si elle manque dans le texte.
     */
    function buildChannelMessage(url, title, message) {
        url = (url || '').trim();
        title = (title || '').trim();
        message = (message || '').trim();

        if (!url) {
            return message || title;
        }
        if (!message) {
            return title ? title + ' : ' + url : url;
        }
        if (message.indexOf(url) !== -1) {
            return message;
        }
        return message + '\n' + url;
    }

    /**
     * Lien HTTPS de partage par canal.
     * On n'utilise PLUS les schémas natifs (whatsapp://, tg://, fb://, intent://…)
     * car la WebView des apps n'est pas configurée pour les intercepter :
     * elle tente de les charger comme une page et affiche une page blanche.
     * Les liens HTTPS, eux, se chargent toujours (et redirigent vers l'app si installée).
     */
    function channelHttpsUrl(channel) {
        var url = state.url;
        var title = state.title;
        var message = buildChannelMessage(url, title, state.message || (title + ' : ' + url));

        switch (channel) {
            case 'wa':
                return 'https://wa.me/?text=' + enc(message);
            case 'gmail':
                return 'https://mail.google.com/mail/?view=cm&fs=1&su=' + enc(title) + '&body=' + enc(message);
            case 'fb':
                return 'https://www.facebook.com/sharer/sharer.php?u=' + enc(url);
            case 'tg':
                return 'https://t.me/share/url?url=' + enc(url) + '&text=' + enc(buildChannelMessage(url, title, state.message || title));
            default:
                return url;
        }
    }

    function openExternal(href) {
        if (!href) {
            return;
        }
        if (shouldUseNativeShare()) {
            return;
        }
        var w = window.open(href, '_blank', 'noopener,noreferrer');
        if (!w) {
            window.location.href = href;
        }
    }

    /** Partage par canal — utilisé uniquement sur ordinateur (modal desktop). */
    function shareViaChannel(channel) {
        openExternal(channelHttpsUrl(channel));
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(ta);
        });
    }

    function flashCopyButton() {
        if (!btnCopy) {
            return;
        }
        var icon = btnCopy.querySelector('i');
        var label = btnCopy.querySelector('.prm-share-modal__url-copy-label');
        var oldIconClass = icon ? icon.className : '';
        var oldLabel = label ? label.textContent : '';
        btnCopy.classList.add('is-copied');
        if (icon) {
            icon.className = 'fas fa-check';
        }
        if (label) {
            label.textContent = 'Copié';
        }
        window.setTimeout(function () {
            btnCopy.classList.remove('is-copied');
            if (icon) {
                icon.className = oldIconClass || 'fas fa-link';
            }
            if (label) {
                label.textContent = oldLabel || 'Copier';
            }
        }, 1800);
    }

    function copyShareUrl() {
        if (!state.url) {
            return;
        }
        copyText(state.url).then(flashCopyButton).catch(function () {
            if (urlEl) {
                var range = document.createRange();
                range.selectNodeContents(urlEl);
                var sel = window.getSelection();
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }
        });
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function openModal(opts) {
        opts = opts || {};
        // Mobile, tablette et app native : feuille de partage système uniquement (pas de modal custom).
        if (shouldUseNativeShare()) {
            tryNativeShare(opts).then(function (ok) {
                if (!ok) {
                    showMobileShareFallback(opts);
                }
            });
            return;
        }
        if (!modal) {
            return;
        }
        state.url = opts.url || '';
        state.title = opts.title || 'Partager';
        state.message = opts.message || (state.title + ' : ' + state.url);
        state.hint = opts.hint || '';

        if (titleEl) {
            titleEl.textContent = opts.modalTitle || state.title || 'Partager';
        }
        if (urlEl) {
            urlEl.textContent = state.url;
        }
        if (hintEl) {
            if (state.hint) {
                hintEl.textContent = state.hint;
                hintEl.hidden = false;
            } else {
                hintEl.textContent = '';
                hintEl.hidden = true;
            }
        }

        lastFocus = document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var closeBtn = modal.querySelector('.prm-share-modal__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function escAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    window.openPlatformShareModal = openModal;
    window.platformShareCopyUrl = copyShareUrl;

    window.buildProductShareHtml = function (opts) {
        opts = opts || {};
        var url = opts.url || '';
        var text = opts.text || '';
        var title = opts.title || '';
        if (!url) {
            return '';
        }
        return '<div class="pshare" data-pshare>'
            + '<button type="button" class="pshare__toggle" aria-label="Partager ' + escAttr(title) + '"'
            + ' data-share-url="' + escAttr(url) + '" data-share-text="' + escAttr(text) + '" data-share-title="' + escAttr(title) + '">'
            + '<i class="fa-solid fa-share-nodes" aria-hidden="true"></i></button></div>';
    };

    function readPlatformShareBtn(el) {
        return {
            modalTitle: el.getAttribute('data-share-modal-title') || '',
            title: el.getAttribute('data-share-title') || 'Partager',
            url: el.getAttribute('data-share-url') || '',
            message: el.getAttribute('data-share-text') || '',
            hint: el.getAttribute('data-share-hint') || ''
        };
    }

    function bindPshareToggleHandler() {
        /* Capture : avant stopPropagation sur cartes cliquables (ex. stock admin). */
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('.pshare__toggle');
            if (!toggle) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var url = toggle.getAttribute('data-share-url') || '';
            var text = toggle.getAttribute('data-share-text') || '';
            var title = toggle.getAttribute('data-share-title') || 'Produit';
            if (!url) {
                return;
            }
            openModal({
                modalTitle: 'Partager ce produit',
                title: title,
                url: url,
                message: text || title,
                hint: 'Partagez ce lien pour que vos contacts voient la fiche produit.'
            });
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-platform-share');
            if (!btn) {
                return;
            }
            e.preventDefault();
            var data = readPlatformShareBtn(btn);
            if (!data.url && !data.message) {
                return;
            }
            openModal({
                modalTitle: data.modalTitle || data.title || 'Partager',
                title: data.title,
                url: data.url,
                message: data.message || data.title,
                hint: data.hint
            });
        });
    }

    function init() {
        bindPshareToggleHandler();

        modal = document.getElementById('platformShareModal');
        if (!modal) {
            return;
        }
        titleEl = document.getElementById('platformShareModalTitle');
        urlEl = document.getElementById('platformShareModalUrl');
        hintEl = document.getElementById('platformShareModalHint');
        btnCopy = document.getElementById('platformShareModalUrlCopy');

        modal.querySelectorAll('[data-share-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        if (btnCopy) {
            btnCopy.addEventListener('click', function (e) {
                e.preventDefault();
                copyShareUrl();
            });
        }

        modal.querySelectorAll('[data-share-channel]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var ch = btn.getAttribute('data-share-channel') || '';
                shareViaChannel(ch);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
