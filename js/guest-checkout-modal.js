/**
 * UI modal checkout invité — saisie nom/téléphone puis soumission serveur.
 */
(function () {
    'use strict';

    function $(id) {
        return document.getElementById(id);
    }

    function openModal(modal) {
        if (!modal) return;
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('guest-checkout-open');
        document.documentElement.classList.add('guest-checkout-open');
        document.body.style.overflow = 'hidden';
        var nomInput = $('guest-checkout-nom');
        if (nomInput) nomInput.focus();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('guest-checkout-open');
        document.documentElement.classList.remove('guest-checkout-open');
        document.body.style.overflow = '';
    }

    function getTelValue(inputTel) {
        if (window.guestCheckoutTelIti && inputTel) {
            try {
                if (typeof intlTelInput !== 'undefined' && intlTelInput.utils) {
                    return window.guestCheckoutTelIti.getNumber(intlTelInput.utils.numberFormat.E164) || inputTel.value.trim();
                }
                return window.guestCheckoutTelIti.getNumber() || inputTel.value.trim();
            } catch (e) {
                return inputTel.value.trim();
            }
        }
        return inputTel ? inputTel.value.trim() : '';
    }

    function copyPanierFieldsFromForm(sourceForm, targetContainer) {
        if (!sourceForm || !targetContainer) return;
        targetContainer.innerHTML = '';
        var names = [
            'produit_id', 'quantite', 'option_couleur', 'option_poids', 'option_taille',
            'option_variante_id', 'option_variante_nom', 'option_variante_image',
            'option_prix_unitaire', 'option_surcout_poids', 'option_surcout_taille',
            'option_perso_meta', 'option_image_personnalisation', 'action'
        ];
        names.forEach(function (name) {
            var el = sourceForm.querySelector('[name="' + name + '"]');
            if (!el || el.value === '') return;
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = el.value;
            targetContainer.appendChild(hidden);
        });
        if (!targetContainer.querySelector('[name="action"]')) {
            var action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'add_to_panier';
            targetContainer.appendChild(action);
        }
    }

    function initGuestCheckoutModal(opts) {
        opts = opts || {};
        var modal = $('guest-checkout-modal');
        if (!modal) return;

        var userLoggedIn = !!opts.userLoggedIn;
        var checkoutForm = $('guest-checkout-form');
        var inputNom = $('guest-checkout-nom');
        var inputTel = $('guest-checkout-telephone');
        var panierFields = $('guest-checkout-panier-fields');
        var sourceForm = opts.sourceFormId ? $(opts.sourceFormId) : null;

        if (inputTel && typeof window.initAuthIntlTel === 'function') {
            window.guestCheckoutTelIti = window.initAuthIntlTel('guest-checkout-telephone');
        }

        if (modal.getAttribute('data-open') === '1') {
            openModal(modal);
        }

        function bindClose(el) {
            if (el) el.addEventListener('click', function () { closeModal(modal); });
        }
        bindClose($('guest-checkout-cancel'));
        bindClose($('guest-checkout-backdrop'));

        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function () {
                var nom = inputNom ? inputNom.value.trim() : '';
                var tel = getTelValue(inputTel);
                if (inputNom) inputNom.value = nom;
                if (inputTel) inputTel.value = tel;
                if (sourceForm && panierFields) {
                    copyPanierFieldsFromForm(sourceForm, panierFields);
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
                closeModal(modal);
            }
        });

        return {
            open: function () {
                if (userLoggedIn) return;
                openModal(modal);
            },
            close: function () { closeModal(modal); },
            isLoggedIn: function () { return userLoggedIn; }
        };
    }

    window.initGuestCheckoutModal = initGuestCheckoutModal;
})();
