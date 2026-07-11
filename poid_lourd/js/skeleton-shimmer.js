(function () {
    'use strict';

    /* Ne rien faire sur super_admin */
    if (/\/super_admin(?:\/|$)/i.test(window.location.pathname || '')) {
        return;
    }

    /* ——— Configuration ——— */

    var MIN_SHIMMER_MS = 120; /* délai minimal uniquement pour images encore en chargement */
    var TIMEOUT_MS     = 6000;

    /* Sélecteurs des conteneurs d'images connus */
    var IMG_CONT_SEL = [
        '.image-wrapper',
        '.mp-card-img',
        '.mp-new-card-img',
        '.mp-strip-card-img',
        '.mp-trend-img-link',
        '.mp-sp-tile',
        '.mp-sp-visual',
        '.slider-item',
        '.boutique-top-cat-card__media',
        '.mp-pop-cat-icon',
        /* pages produit / panier / commande / user */
        '.produit-gallery-main',
        '.panier-item-img',
        '.produit-card-img',
        '.produit-cmd-img',
        /* admin */
        '.stock-cat-card__media'
    ].join(',');

    /* Sélecteurs des cartes parentes (pour révéler le texte) */
    var CARD_SEL = [
        '.mp-card',
        '.mp-new-card',
        '.mp-strip-card',
        '.mp-trend-card',
        '.boutique-top-cat-card',
        '.carousel'
    ].join(',');

    /* Sélecteurs du texte à cacher/révéler dans les cartes */
    var CARD_BODY_SEL = [
        '.mp-card-body',
        '.produit-content',
        '.mp-strip-body',
        '.mp-new-title',
        '.mp-new-promo',
        '.mp-new-price',
        '.mp-trend-label',
        '.mp-trend-sub',
        '.boutique-top-cat-card__name',
        '.boutique-top-cat-card__cta'
    ].join(',');

    /* ——— État de session ——— */

    var sessionId  = null;
    var observer   = null;
    var obsTimer   = null;
    var pageStart  = 0;

    /* ——— Utilitaires ——— */

    function now() {
        return typeof performance !== 'undefined' ? performance.now() : Date.now();
    }

    function afterMin(fn) {
        var elapsed = now() - pageStart;
        var wait    = Math.max(0, MIN_SHIMMER_MS - elapsed);
        setTimeout(fn, wait);
    }

    /* ——— Marquage de la page ——— */

    function markPending() {
        document.documentElement.classList.remove('sk-shimmer-done');
        document.documentElement.classList.add('sk-shimmer-pending');
    }

    /* ——— Injection des lignes squelette dans les cartes ——— */

    function injectLines(card) {
        if (!card || card.dataset.skLines === sessionId) return;
        card.dataset.skLines = sessionId;

        var old = card.querySelector('.sk-card-lines');
        if (old) old.remove();

        /* Vérifier qu'il y a bien une image dans la carte */
        if (!card.querySelector('img')) return;

        var lines = document.createElement('div');
        lines.className = 'sk-card-lines';
        lines.setAttribute('aria-hidden', 'true');
        lines.innerHTML =
            '<span class="sk-line sk-line--lg sk-shimmer-bg"></span>' +
            '<span class="sk-line sk-line--md sk-shimmer-bg"></span>' +
            '<span class="sk-line sk-line--sm sk-shimmer-bg"></span>';

        /* Insérer juste avant le texte, ou après le conteneur image */
        var body = card.querySelector(CARD_BODY_SEL);
        if (body && body.parentNode) {
            body.parentNode.insertBefore(lines, body);
        } else {
            var imgCont = card.querySelector(IMG_CONT_SEL);
            if (imgCont && imgCont.parentNode) {
                imgCont.parentNode.insertBefore(lines, imgCont.nextSibling);
            } else {
                card.appendChild(lines);
            }
        }
    }

    /* ——— Révélation d'un conteneur ——— */

    function reveal(container, card, immediate) {
        var apply = function () {
            if (container && container.isConnected) {
                container.classList.add('sk-loaded');
            }
            if (card && card.isConnected) {
                card.classList.add('sk-loaded');
            }
        };
        if (immediate) {
            apply();
            return;
        }
        afterMin(apply);
    }

    /* ——— Surveillance d'un conteneur image ——— */

    function watchContainer(container) {
        if (!container || container.dataset.skWatch === sessionId) return;
        container.dataset.skWatch = sessionId;
        container.classList.remove('sk-loaded');

        /* Trouver la carte parente */
        var card = (container.parentElement || container).closest(CARD_SEL) || null;

        var img  = container.querySelector('img');
        if (!img) {
            reveal(container, card, true);
            return;
        }

        var done = false;
        function finish(immediate) {
            if (done) return;
            done = true;
            reveal(container, card, !!immediate);
        }

        if (img.complete && img.naturalWidth > 0) {
            finish(true);
        } else {
            img.addEventListener('load',  function () { finish(false); }, { once: true });
            img.addEventListener('error', function () { finish(false); }, { once: true });
            setTimeout(function () { finish(false); }, TIMEOUT_MS);
        }
    }

    /* ——— Scan d'une zone du DOM ——— */

    function scan(root) {
        var scope = root || document;

        /* Lignes squelette désactivées — texte visible immédiatement */
        scope.querySelectorAll(CARD_SEL).forEach(function (card) {
            var old = card.querySelector('.sk-card-lines');
            if (old) old.remove();
        });

        /* Surveiller tous les conteneurs d'images */
        scope.querySelectorAll(IMG_CONT_SEL).forEach(watchContainer);
    }

    /* ——— Observer les ajouts dynamiques (scroll infini, AJAX) ——— */

    function setupObserver() {
        if (typeof MutationObserver === 'undefined' || observer) return;

        observer = new MutationObserver(function (mutations) {
            clearTimeout(obsTimer);
            obsTimer = setTimeout(function () {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) scan(node);
                    });
                });
            }, 50);
        });

        var target = document.body || document.documentElement;
        observer.observe(target, { childList: true, subtree: true });
    }

    /* ——— Réinitialisation complète (nouveau chargement / bfcache) ——— */

    function resetAll() {
        if (observer) { observer.disconnect(); observer = null; }
        clearTimeout(obsTimer);

        /* Effacer l'état précédent */
        document.querySelectorAll('.sk-loaded').forEach(function (el) {
            el.classList.remove('sk-loaded');
        });
        document.querySelectorAll('[data-sk-watch]').forEach(function (el) {
            delete el.dataset.skWatch;
        });
        document.querySelectorAll('[data-sk-lines]').forEach(function (el) {
            delete el.dataset.skLines;
        });
        document.querySelectorAll('.sk-card-lines').forEach(function (el) {
            el.remove();
        });
    }

    /* ——— Démarrage ——— */

    function boot() {
        pageStart  = now();
        sessionId  = String(Date.now()) + '-' + Math.random().toString(36).slice(2, 7);

        resetAll();
        markPending();

        function run() {
            scan(document);
            setupObserver();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, { once: true });
        } else {
            run();
        }
    }

    /* ——— Lancement initial ——— */
    boot();

    /* ——— Retour arrière navigateur (bfcache) ——— */
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) boot();
    });

    /* ——— API publique ——— */
    window.ColobanesSkeleton = { refresh: scan, reboot: boot };
})();
