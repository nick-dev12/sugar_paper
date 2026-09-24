/**
 * Préremplit l'adresse de livraison depuis le profil enregistré (même téléphone).
 */
(function (window) {
    'use strict';

    function digitsOnly(s) {
        return String(s || '').replace(/\D/g, '');
    }

    function fetchProfil(telephone, ajaxUrl) {
        var tel = String(telephone || '').trim();
        if (digitsOnly(tel).length < 8) {
            return Promise.resolve(null);
        }
        var url = (ajaxUrl || 'ajax_client_livraison_profil.php') +
            '?telephone=' + encodeURIComponent(tel);
        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !data.found || !data.adresse) {
                    return null;
                }
                return data;
            })
            .catch(function () { return null; });
    }

    function applyToFields(profil, adresseEl, hiddenEl) {
        if (!profil || !adresseEl) {
            return;
        }
        var current = (adresseEl.value || '').trim();
        if (current !== '') {
            return;
        }
        adresseEl.value = profil.adresse;
        if (hiddenEl) {
            hiddenEl.value = profil.adresse;
        }
    }

    function bindTelephoneInput(telInput, adresseEl, hiddenEl, ajaxUrl) {
        if (!telInput || !adresseEl) {
            return;
        }
        var timer = null;
        function tryFill() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                fetchProfil(telInput.value, ajaxUrl).then(function (profil) {
                    applyToFields(profil, adresseEl, hiddenEl);
                });
            }, 400);
        }
        telInput.addEventListener('change', tryFill);
        telInput.addEventListener('blur', tryFill);
    }

    function fillAfterClientSelect(telephone, adresseEl, hiddenEl, ajaxUrl) {
        fetchProfil(telephone, ajaxUrl).then(function (profil) {
            applyToFields(profil, adresseEl, hiddenEl);
        });
    }

    window.AdminClientLivraisonProfil = {
        fetchProfil: fetchProfil,
        applyToFields: applyToFields,
        bindTelephoneInput: bindTelephoneInput,
        fillAfterClientSelect: fillAfterClientSelect
    };
}(window));
