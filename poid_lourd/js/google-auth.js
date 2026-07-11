(function () {
    'use strict';

    function ensureSocialAuthMessage(wrap) {
        var msg = wrap.querySelector('.social-auth-message, .google-auth-message');
        if (msg) return msg;
        msg = document.createElement('p');
        msg.className = 'social-auth-message';
        msg.setAttribute('aria-live', 'polite');
        var buttons = wrap.querySelector('.social-auth__buttons');
        if (buttons) {
            buttons.insertAdjacentElement('afterend', msg);
        } else {
            wrap.appendChild(msg);
        }
        return msg;
    }

    function setMessage(button, message, isError) {
        var wrap = button.closest('.social-auth');
        if (!wrap) return;
        var msg = wrap.querySelector('.social-auth-message, .google-auth-message');
        if (!message) {
            if (msg) msg.remove();
            return;
        }
        if (!msg) {
            msg = ensureSocialAuthMessage(wrap);
        }
        msg.textContent = message;
        msg.classList.toggle('is-error', !!isError);
    }

    function signInWithGoogle(button) {
        if (typeof firebase === 'undefined' || !firebase.auth) {
            setMessage(button, 'Firebase Auth n’est pas chargé. Rechargez la page.', true);
            return;
        }

        var accountType = button.getAttribute('data-google-auth-type') || 'client';
        var redirect = button.getAttribute('data-google-auth-redirect') || '';
        var originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="google-auth-btn__icon" aria-hidden="true">G</span><span>Connexion...</span>';
        setMessage(button, '', false);

        var provider = new firebase.auth.GoogleAuthProvider();
        provider.setCustomParameters({ prompt: 'select_account' });

        firebase.auth().signInWithPopup(provider)
            .then(function (result) {
                if (!result.user) {
                    throw new Error('Compte Google introuvable.');
                }
                return result.user.getIdToken();
            })
            .then(function (idToken) {
                return fetch('/auth-google-callback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        idToken: idToken,
                        accountType: accountType,
                        redirect: redirect
                    })
                });
            })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('Réponse serveur invalide.');
                });
            })
            .then(function (data) {
                if (!data || !data.success || !data.redirect) {
                    throw new Error((data && data.message) ? data.message : 'Connexion Google refusée.');
                }
                window.location.href = data.redirect;
            })
            .catch(function (error) {
                setMessage(button, error && error.message ? error.message : 'Connexion Google annulée ou impossible.', true);
                button.disabled = false;
                button.innerHTML = originalText;
            });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.google-auth-btn');
        if (!button) return;
        signInWithGoogle(button);
    });
})();
