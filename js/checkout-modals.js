/**
 * Chaîne de modales : panier → commande → succès
 */
(function () {
    'use strict';

    var root;
    var bodyCart;
    var bodyCheckout;
    var bodySuccess;
    var titleEl;
    var backBtn;
    var current = null;
    var stack = [];
    var loading = false;

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function qsa(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    function setTitle(text) {
        if (titleEl) titleEl.textContent = text;
    }

    function updateBadges(count) {
        count = parseInt(count, 10) || 0;
        qsa('.nav-panier-badge, .bottom-nav-badge').forEach(function (el) {
            if (count > 0) {
                el.hidden = false;
                el.textContent = count > 99 ? '99+' : String(count);
            } else {
                el.textContent = '0';
                if (el.classList.contains('nav-panier-badge') || el.classList.contains('bottom-nav-badge')) {
                    el.style.display = count > 0 ? '' : 'none';
                }
            }
        });
        qsa('.nav-panier-link').forEach(function (link) {
            var badge = link.querySelector('.nav-panier-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'nav-panier-badge';
                    link.appendChild(badge);
                }
                badge.style.display = '';
                badge.textContent = count > 99 ? '99+' : String(count);
            } else if (badge) {
                badge.style.display = 'none';
            }
        });
    }

    function showLoader(on) {
        var loader = qs('#ckm-loader');
        if (!loader) return;
        if (on) {
            loader.removeAttribute('hidden');
        } else {
            loader.setAttribute('hidden', '');
        }
    }

    function parseJson(res) {
        return res.text().then(function (text) {
            if (!text) return null;
            try {
                return JSON.parse(text);
            } catch (e) {
                return null;
            }
        });
    }

    function fetchJson(url, opts) {
        opts = opts || {};
        opts.headers = opts.headers || {};
        opts.headers.Accept = 'application/json';
        opts.headers['X-Requested-With'] = 'XMLHttpRequest';
        opts.credentials = 'same-origin';
        return fetch(url, opts).then(function (res) {
            return parseJson(res).then(function (data) {
                return { okHttp: res.ok, data: data || {} };
            });
        });
    }

    function activatePanel(name, dir) {
        qsa('.ckm-panel', root).forEach(function (panel) {
            var active = panel.getAttribute('data-panel') === name;
            panel.classList.toggle('is-active', active);
            panel.classList.toggle('ckm-dir-back', active && dir === 'back');
        });
        current = name;
        if (backBtn) {
            backBtn.hidden = name === 'success';
        }
        if (name === 'cart') setTitle('Votre panier');
        if (name === 'checkout') setTitle('Passer la commande');
        if (name === 'success') setTitle('Commande confirmée');
    }

    function openRoot() {
        if (!root) return;
        if (root.parentElement !== document.body) {
            document.body.appendChild(root);
        }
        root.removeAttribute('hidden');
        document.body.classList.add('ckm-open');
        document.body.style.overflow = 'hidden';
    }

    function closeAll() {
        if (window.CommandeGeo && typeof window.CommandeGeo.stopWatch === 'function') {
            window.CommandeGeo.stopWatch();
        }
        if (window.CommandeGeo && typeof window.CommandeGeo.reset === 'function') {
            window.CommandeGeo.reset();
        }
        stack = [];
        current = null;
        if (root) root.setAttribute('hidden', '');
        document.body.classList.remove('ckm-open');
        document.body.style.overflow = '';
        showLoader(false);
    }

    function closeGuest() {
        var guest = document.getElementById('guest-checkout-modal');
        if (guest && window.initGuestCheckoutModal) {
            guest.setAttribute('hidden', '');
            guest.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('guest-checkout-open');
            document.documentElement.classList.remove('guest-checkout-open');
        }
    }

    function bindCartBody() {
        if (!bodyCart) return;
        qsa('.js-ckm-qty-plus', bodyCart).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                var input = form && form.querySelector('.ckm-qty-input');
                if (!input) return;
                var max = parseInt(input.getAttribute('max'), 10) || 99;
                var val = parseInt(input.value, 10) || 1;
                if (val < max) {
                    input.value = val + 1;
                    submitCartForm(form);
                }
            });
        });
        qsa('.js-ckm-qty-minus', bodyCart).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                var input = form && form.querySelector('.ckm-qty-input');
                if (!input) return;
                var val = parseInt(input.value, 10) || 1;
                if (val > 1) {
                    input.value = val - 1;
                    submitCartForm(form);
                }
            });
        });
        qsa('.js-ckm-qty-form', bodyCart).forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitCartForm(form);
            });
        });
        qsa('.js-ckm-delete-form', bodyCart).forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitCartForm(form);
            });
        });
        qsa('.js-ckm-close', bodyCart).forEach(function (btn) {
            btn.addEventListener('click', closeAll);
        });
        qsa('.js-ckm-go-checkout', bodyCart).forEach(function (btn) {
            btn.addEventListener('click', function () {
                openCheckout();
            });
        });
    }

    function bindCheckoutBody(panierTotal) {
        if (!bodyCheckout) return;
        window.CommandeTotaux = {
            refresh: function () {
                var selectZone = document.getElementById('zone_livraison_id');
                var spanLivraison = document.getElementById('summary-livraison');
                var spanTotal = document.getElementById('summary-total');
                var frais = 0;
                if (window.CommandeGeo && CommandeGeo.getMode() === 'retrait') {
                    frais = 0;
                } else if (selectZone && !selectZone.disabled && selectZone.selectedIndex >= 0) {
                    var opt = selectZone.options[selectZone.selectedIndex];
                    frais = opt && opt.dataset && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
                }
                if (isNaN(frais)) frais = 0;
                var total = (panierTotal || 0) + frais;
                function fmt(n) {
                    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
                }
                if (spanLivraison) spanLivraison.textContent = fmt(frais);
                if (spanTotal) spanTotal.textContent = fmt(total);
            }
        };
        var selectZone = document.getElementById('zone_livraison_id');
        if (selectZone) {
            selectZone.addEventListener('change', function () {
                window.CommandeTotaux.refresh();
            });
        }
        if (window.CommandeGeo && typeof window.CommandeGeo.reset === 'function') {
            window.CommandeGeo.reset();
        }
        if (window.CommandeGeo && typeof window.CommandeGeo.init === 'function') {
            window.CommandeGeo.init();
        }
        window.CommandeTotaux.refresh();
        setTimeout(function () {
            var mapEl = document.getElementById('commande-geo-map');
            if (mapEl && window.L && mapEl._leaflet_id) {
                try {
                    var mapInst = mapEl._leaflet;
                } catch (e) {}
            }
            window.dispatchEvent(new Event('resize'));
        }, 280);

        var form = document.getElementById('form-commande');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (window.CommandeGeo && typeof CommandeGeo.validate === 'function' && !CommandeGeo.validate()) {
                    return;
                }
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                submitCheckout(form);
            });
        }
    }

    function submitCartForm(form) {
        if (loading) return;
        loading = true;
        showLoader(true);
        var fd = new FormData(form);
        fd.append('ajax', '1');
        fetchJson('/api/modals/cart-action.php', { method: 'POST', body: fd })
            .then(function (res) {
                var data = res.data || {};
                if (data.html) bodyCart.innerHTML = data.html;
                if (typeof data.count !== 'undefined') updateBadges(data.count);
                bindCartBody();
            })
            .catch(function () {})
            .then(function () {
                loading = false;
                showLoader(false);
            });
    }

    function fallbackOpenWhatsApp(url) {
        var link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
            if (link.parentNode) {
                link.parentNode.removeChild(link);
            }
        }, 0);
    }

    function openWhatsAppConversation(url) {
        url = (url || '').trim();
        if (!url || url === '#') {
            return;
        }
        if (window.SugarPaperNative && typeof window.SugarPaperNative.openExternalUrl === 'function') {
            window.SugarPaperNative.openExternalUrl(url).catch(function () {
                fallbackOpenWhatsApp(url);
            });
            return;
        }
        if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
            window.flutter_inappwebview.callHandler('openExternalUrl', url).catch(function () {
                fallbackOpenWhatsApp(url);
            });
            return;
        }
        fallbackOpenWhatsApp(url);
    }

    function submitCheckout(form) {
        if (loading) return;
        loading = true;
        showLoader(true);
        if (window.CommandeGeo && typeof CommandeGeo.stopWatch === 'function') {
            CommandeGeo.stopWatch();
        }
        var fd = new FormData(form);
        fd.append('ajax', '1');
        fetchJson('/api/modals/checkout-submit.php', { method: 'POST', body: fd })
            .then(function (res) {
                var data = res.data || {};
                if (data.ok && data.html) {
                    bodySuccess.innerHTML = data.html;
                    updateBadges(0);
                    stack = ['cart', 'checkout'];
                    activatePanel('success', 'fwd');
                    if (data.whatsapp_url) {
                        setTimeout(function () {
                            openWhatsAppConversation(data.whatsapp_url);
                        }, 350);
                    }
                } else if (data.html) {
                    bodyCheckout.innerHTML = data.html;
                    bindCheckoutBody(data.panier_total || 0);
                }
            })
            .catch(function () {})
            .then(function () {
                loading = false;
                showLoader(false);
            });
    }

    function fallbackCartHtml(message) {
        return '<div class="ckm-empty"><div class="ckm-empty__icon"><i class="fas fa-shopping-bag"></i></div>'
            + '<p class="ckm-empty__title">' + (message || 'Impossible de charger le panier') + '</p>'
            + '<button type="button" class="ckm-btn ckm-btn--primary js-ckm-close">Fermer</button></div>';
    }

    function applyCartHtml(html, count) {
        if (!bodyCart) return;
        bodyCart.innerHTML = html || fallbackCartHtml();
        if (typeof count !== 'undefined') updateBadges(count);
        bindCartBody();
        openRoot();
        activatePanel('cart', 'fwd');
    }

    function loadCart(thenOpen) {
        if (loading) return Promise.resolve();
        loading = true;
        showLoader(true);
        openRoot();
        return fetchJson('/api/modals/cart.php')
            .then(function (res) {
                var data = res.data || {};
                var html = data.html || fallbackCartHtml(data.message);
                if (thenOpen !== false) {
                    applyCartHtml(html, data.count);
                } else if (bodyCart) {
                    bodyCart.innerHTML = html;
                    if (typeof data.count !== 'undefined') updateBadges(data.count);
                    bindCartBody();
                }
                return data;
            })
            .catch(function () {
                applyCartHtml(fallbackCartHtml(), 0);
                return {};
            })
            .then(function (data) {
                loading = false;
                showLoader(false);
                return data;
            });
    }

    function openCart() {
        closeGuest();
        return loadCart(true);
    }

    function openCheckout() {
        if (loading) return;
        loading = true;
        showLoader(true);
        openRoot();
        fetchJson('/api/modals/checkout.php')
            .then(function (res) {
                var data = res.data || {};
                if (data.need_guest) {
                    loading = false;
                    showLoader(false);
                    if (data.html) {
                        bodyCart.innerHTML = data.html;
                        bindCartBody();
                        activatePanel('cart', 'back');
                    }
                    openGuestThenCheckout();
                    return;
                }
                if (data.need_cart) {
                    if (data.html) bodyCart.innerHTML = data.html;
                    bindCartBody();
                    stack = [];
                    activatePanel('cart', 'back');
                    return;
                }
                if (data.html) {
                    bodyCheckout.innerHTML = data.html;
                    bindCheckoutBody(data.panier_total || 0);
                    stack = ['cart'];
                    activatePanel('checkout', 'fwd');
                }
            })
            .catch(function () {})
            .then(function () {
                loading = false;
                showLoader(false);
            });
    }

    function openGuestThenCheckout() {
        var guest = document.getElementById('guest-checkout-modal');
        var action = qs('#guest-checkout-form input[name="checkout_action"]');
        if (action) action.value = 'go_commande';
        if (window.SugarCheckoutModals && window.SugarCheckoutModals._guest) {
            window.SugarCheckoutModals._guest.open();
            return;
        }
        if (guest) {
            guest.removeAttribute('hidden');
            guest.setAttribute('aria-hidden', 'false');
            document.body.classList.add('guest-checkout-open');
        }
    }

    function goBack() {
        if (current === 'checkout') {
            if (window.CommandeGeo && typeof window.CommandeGeo.stopWatch === 'function') {
                window.CommandeGeo.stopWatch();
            }
            loadCart(false).then(function () {
                stack = [];
                activatePanel('cart', 'back');
            });
            return;
        }
        closeAll();
    }

    function interceptLinks() {
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a');
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (a.classList.contains('js-open-cart-modal') || /\/panier\.php(\?|$)/.test(href)) {
                e.preventDefault();
                openCart();
                return;
            }
            if (/\/commande\.php(\?|$)/.test(href)) {
                e.preventDefault();
                openCheckout();
            }
        });
    }

    function requestGuestNotifyPermission() {
        try {
            if (window.SugarPaperNative && typeof window.SugarPaperNative.registerFcmToken === 'function') {
                window.SugarPaperNative.registerFcmToken();
            } else if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
                window.flutter_inappwebview.callHandler('registerFcmToken');
            }
        } catch (e) { /* ignore */ }

        if (window.FirebaseNotifications && window.FirebaseNotifications.isSugarPaperNativeApp
            && window.FirebaseNotifications.isSugarPaperNativeApp()) {
            return Promise.resolve();
        }
        if (typeof Notification === 'undefined' || !window.isSecureContext) {
            return Promise.resolve();
        }
        if (Notification.permission === 'granted' || Notification.permission === 'denied') {
            return Promise.resolve();
        }
        try {
            return Promise.race([
                Notification.requestPermission().then(function () {}).catch(function () {}),
                new Promise(function (resolve) { setTimeout(resolve, 6000); })
            ]);
        } catch (err) {
            return Promise.resolve();
        }
    }

    function interceptGuestForm() {
        var form = document.getElementById('guest-checkout-form');
        if (!form || form.getAttribute('data-ckm-bound') === '1') return;
        form.setAttribute('data-ckm-bound', '1');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var inputNom = document.getElementById('guest-checkout-nom');
            var inputTel = document.getElementById('guest-checkout-telephone');
            var nom = inputNom ? inputNom.value.trim() : '';
            var tel = '';
            if (window.guestCheckoutTelIti && inputTel) {
                try {
                    if (typeof intlTelInput !== 'undefined' && intlTelInput.utils) {
                        tel = window.guestCheckoutTelIti.getNumber(intlTelInput.utils.numberFormat.E164) || inputTel.value.trim();
                    } else {
                        tel = window.guestCheckoutTelIti.getNumber() || inputTel.value.trim();
                    }
                } catch (err) {
                    tel = inputTel.value.trim();
                }
            } else if (inputTel) {
                tel = inputTel.value.trim();
            }
            if (inputNom) inputNom.value = nom;
            if (inputTel) inputTel.value = tel;
            var panierFields = document.getElementById('guest-checkout-panier-fields');
            var sourceForm = document.getElementById('add-to-panier-form');
            if (sourceForm && panierFields) {
                panierFields.innerHTML = '';
                ['produit_id', 'quantite', 'option_couleur', 'option_poids', 'option_taille',
                    'option_variante_id', 'option_variante_nom', 'option_variante_image',
                    'option_prix_unitaire', 'option_surcout_poids', 'option_surcout_taille', 'action'].forEach(function (name) {
                    var el = sourceForm.querySelector('[name="' + name + '"]');
                    if (!el || el.value === '') return;
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    hidden.value = el.value;
                    panierFields.appendChild(hidden);
                });
            }
            var fd = new FormData(form);
            fd.set('nom', nom);
            fd.set('telephone', tel);
            fd.set('accepte_conditions', '1');
            fd.append('ajax', '1');
            loading = true;
            showLoader(true);
            requestGuestNotifyPermission().then(function () {
                return fetchJson(form.getAttribute('action') || '/user/guest-checkout-auth.php', {
                    method: 'POST',
                    body: fd
                });
            }).then(function (res) {
                var data = res.data || {};
                if (!data.ok) {
                    loading = false;
                    showLoader(false);
                    var err = document.getElementById('guest-checkout-error');
                    if (err) {
                        err.hidden = false;
                        err.textContent = data.message || 'Impossible de continuer.';
                    }
                    return;
                }
                var redirect = data.redirect || '';
                if (data.reload && redirect) {
                    window.location.href = redirect;
                    return;
                }
                window.location.href = redirect || (window.location.pathname + '?open=panier');
            }).catch(function () {
                form.removeAttribute('data-ckm-bound');
                form.submit();
            });
        });
    }

    function copyAddFormToGuest(sourceForm) {
        var panierFields = document.getElementById('guest-checkout-panier-fields');
        var actionInput = document.querySelector('#guest-checkout-form input[name="checkout_action"]');
        if (actionInput) actionInput.value = 'add_to_panier';
        if (!sourceForm || !panierFields) return;
        panierFields.innerHTML = '';
        ['produit_id', 'quantite', 'option_couleur', 'option_poids', 'option_taille',
            'option_variante_id', 'option_variante_nom', 'option_variante_image',
            'option_prix_unitaire', 'option_surcout_poids', 'option_surcout_taille', 'action'].forEach(function (name) {
            var el = sourceForm.querySelector('[name="' + name + '"]');
            if (!el || el.value === '') return;
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = el.value;
            panierFields.appendChild(hidden);
        });
        if (!panierFields.querySelector('[name="action"]')) {
            var action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'add_to_panier';
            panierFields.appendChild(action);
        }
    }

    function interceptAddToCartForms() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.nodeName !== 'FORM') return;
            if (form.id === 'guest-checkout-form' || form.id === 'form-commande') return;
            if (form.classList.contains('js-ckm-qty-form') || form.classList.contains('js-ckm-delete-form')) return;
            var action = (form.getAttribute('action') || '').toLowerCase();
            var isAdd = form.id === 'add-to-panier-form'
                || form.classList.contains('add-to-cart-form')
                || action.indexOf('add-to-panier.php') !== -1;
            if (!isAdd) return;
            e.preventDefault();
            if (!window.CKM_USER_LOGGED) {
                copyAddFormToGuest(form);
                var guest = window.SugarCheckoutModals && window.SugarCheckoutModals._guest;
                if (guest && typeof guest.open === 'function') {
                    guest.open();
                } else {
                    var modal = document.getElementById('guest-checkout-modal');
                    if (modal) {
                        modal.removeAttribute('hidden');
                        modal.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('guest-checkout-open');
                    }
                }
                return;
            }
            var fd = new FormData(form);
            fd.append('ajax', '1');
            loading = true;
            showLoader(true);
            fetchJson('/add-to-panier.php', { method: 'POST', body: fd })
                .then(function (res) {
                    var data = res.data || {};
                    loading = false;
                    showLoader(false);
                    if (data.html) {
                        applyCartHtml(data.html, data.count);
                        return;
                    }
                    if (data.ok) {
                        openCart();
                        return;
                    }
                    openCart();
                })
                .catch(function () {
                    loading = false;
                    showLoader(false);
                    form.submit();
                });
        });
    }

    function bootFromQuery() {
        var params = new URLSearchParams(window.location.search);
        var open = params.get('open');
        if (open === 'panier' || open === 'cart') openCart();
        if (open === 'commande' || open === 'checkout') openCheckout();
    }

    function init() {
        root = document.getElementById('checkout-modals-root');
        if (!root) return;
        bodyCart = document.getElementById('ckm-cart-body');
        bodyCheckout = document.getElementById('ckm-checkout-body');
        bodySuccess = document.getElementById('ckm-success-body');
        titleEl = document.getElementById('ckm-title');
        backBtn = document.getElementById('ckm-back');

        var closeBtn = document.getElementById('ckm-close');
        var backdrop = qs('.ckm-backdrop', root);
        if (closeBtn) closeBtn.addEventListener('click', closeAll);
        if (backdrop) backdrop.addEventListener('click', closeAll);
        if (backBtn) backBtn.addEventListener('click', goBack);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root && !root.hasAttribute('hidden')) {
                goBack();
            }
        });

        interceptLinks();
        interceptGuestForm();
        interceptAddToCartForms();
        bootFromQuery();

        window.SugarCheckoutModals = {
            openCart: openCart,
            openCheckout: openCheckout,
            close: closeAll
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
