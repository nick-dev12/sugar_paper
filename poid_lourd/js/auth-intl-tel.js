/**
 * Indicatif téléphonique international (intl-tel-input) — pages auth.
 * Pays initial détecté via IP (meta auth-geo-country) ; liste modifiable par l'utilisateur.
 */
(function () {
  function getGeoCountryCode() {
    var meta = document.querySelector('meta[name="auth-geo-country"]');
    var code = meta ? String(meta.getAttribute('content') || '').trim().toLowerCase() : '';
    return /^[a-z]{2}$/.test(code) ? code : 'sn';
  }

  function getE164(iti) {
    try {
      if (
        typeof intlTelInput !== 'undefined' &&
        intlTelInput.utils &&
        typeof intlTelInput.utils.numberFormat !== 'undefined'
      ) {
        return iti.getNumber(intlTelInput.utils.numberFormat.E164);
      }
    } catch (e) {}
    try {
      return iti.getNumber();
    } catch (e2) {
      return '';
    }
  }

  /**
   * @param {string} inputId
   */
  function initAuthIntlTel(inputId) {
    var input = document.getElementById(inputId);
    if (!input || typeof window.intlTelInput === 'undefined') {
      return null;
    }

    var geoCountry = getGeoCountryCode();
    var preferred = [geoCountry, 'sn', 'ga', 'ci', 'fr', 'ml', 'tg', 'bf', 'ne', 'cm', 'bj'];
    var seen = {};
    preferred = preferred.filter(function (c) {
      if (seen[c]) return false;
      seen[c] = true;
      return true;
    });

    var iti = window.intlTelInput(input, {
      initialCountry: geoCountry,
      preferredCountries: preferred,
      nationalMode: false,
      formatOnDisplay: true,
      strictMode: false
    });

    var form = input.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        var n = getE164(iti);
        if (n) {
          input.value = n;
        }
      });
    }

    return iti;
  }

  window.initAuthIntlTel = initAuthIntlTel;
})();
