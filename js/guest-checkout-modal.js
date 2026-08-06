/**
 * UI modal checkout invité (2 étapes) — animations et copie champs panier uniquement.
 */
(function () {
    'use strict';

    function $(id) {
        return document.getElementById(id);
    }

    function openModal(modal) {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function updateStepDots(step) {
        var dots = document.querySelectorAll('[data-step-dot]');
        dots.forEach(function (dot) {
            var n = parseInt(dot.getAttribute('data-step-dot'), 10);
            dot.classList.toggle('is-active', n === step);
            dot.classList.toggle('is-done', n < step);
        });
    }

    function showStep(modal, step) {
        var prepare = $('guest-checkout-form-prepare');
        var auth = $('guest-checkout-form-auth');
        var title = $('guest-checkout-modal-title');
        var subtitle = $('guest-checkout-subtitle');
        if (!prepare || !auth) return;

        if (step === 2) {
            prepare.hidden = true;
            auth.hidden = false;
            if (title) title.textContent = 'Votre code PIN';
            if (subtitle) {
                var exists = modal.getAttribute('data-phone-exists') === '1';
                subtitle.textContent = exists
                    ? 'Entrez votre PIN de sécurité pour continuer.'
                    : 'Créez un PIN de sécurité.';
            }
            var pinInput = $('guest-checkout-pin');
            if (pinInput) pinInput.focus();
        } else {
            prepare.hidden = false;
            auth.hidden = true;
            if (title) title.textContent = 'Vos coordonnées';
            if (subtitle) subtitle.textContent = 'Indiquez votre nom et votre numéro pour continuer.';
        }
        modal.setAttribute('data-current-step', String(step));
        updateStepDots(step);
    }

    function setPhoneExistsMode(exists) {
        var hintExisting = $('guest-checkout-existing-hint');
        var pinInput = $('guest-checkout-pin');
        var pinLabel = $('guest-checkout-pin-label');
        var subtitle = $('guest-checkout-subtitle');
        if (hintExisting) hintExisting.hidden = !exists;
        if (pinLabel) {
            pinLabel.textContent = exists ? 'Code PIN (4 ou 6 chiffres) *' : 'Code PIN (4 chiffres) *';
        }
        if (subtitle && exists) {
            subtitle.textContent = 'Entrez votre PIN de sécurité pour continuer.';
        } else if (subtitle && !exists) {
            subtitle.textContent = 'Créez un PIN de sécurité.';
        }
        if (pinInput) {
            pinInput.maxLength = exists ? 6 : 4;
            pinInput.setAttribute('autocomplete', exists ? 'one-time-code' : 'off');
            pinInput.type = 'text';
            pinInput.inputMode = 'numeric';
            pinInput.placeholder = exists ? '••••••' : '••••';
        }
        var modal = $('guest-checkout-modal');
        if (modal) modal.setAttribute('data-phone-exists', exists ? '1' : '0');
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
            'option_prix_unitaire', 'option_surcout_poids', 'option_surcout_taille', 'action'
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
        var prepareForm = $('guest-checkout-form-prepare');
        var authForm = $('guest-checkout-form-auth');
        var inputNom = $('guest-checkout-nom');
        var inputTel = $('guest-checkout-telephone');
        var hiddenNom = $('guest-checkout-nom-hidden');
        var hiddenTel = $('guest-checkout-telephone-hidden');
        var panierFields = $('guest-checkout-panier-fields');
        var sourceForm = opts.sourceFormId ? $(opts.sourceFormId) : null;

        if (inputTel && typeof window.initAuthIntlTel === 'function') {
            window.guestCheckoutTelIti = window.initAuthIntlTel('guest-checkout-telephone');
        }

        var phoneExists = modal.getAttribute('data-phone-exists') === '1';
        setPhoneExistsMode(phoneExists);

        var pinField = $('guest-checkout-pin');
        if (pinField) {
            pinField.addEventListener('input', function () {
                var max = parseInt(pinField.getAttribute('maxlength'), 10) || 6;
                pinField.value = String(pinField.value || '').replace(/\D/g, '').slice(0, max);
            });
        }

        if (modal.getAttribute('data-open-pin') === '1') {
            openModal(modal);
            showStep(modal, 2);
        }

        function bindClose(el) {
            if (el) el.addEventListener('click', function () { closeModal(modal); });
        }
        bindClose($('guest-checkout-cancel'));
        bindClose($('guest-checkout-backdrop'));

        var backBtn = $('guest-checkout-back');
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                showStep(modal, 1);
            });
        }

        if (prepareForm) {
            prepareForm.addEventListener('submit', function () {
                var nom = inputNom ? inputNom.value.trim() : '';
                var tel = getTelValue(inputTel);
                if (inputNom) inputNom.value = nom;
                if (inputTel) inputTel.value = tel;
                if (hiddenNom) hiddenNom.value = nom;
                if (hiddenTel) hiddenTel.value = tel;
            });
        }

        if (authForm) {
            authForm.addEventListener('submit', function () {
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
            open: function (step) {
                if (userLoggedIn) return;
                openModal(modal);
                showStep(modal, step || 1);
            },
            close: function () { closeModal(modal); },
            isLoggedIn: function () { return userLoggedIn; }
        };
    }

    window.initGuestCheckoutModal = initGuestCheckoutModal;
})();
