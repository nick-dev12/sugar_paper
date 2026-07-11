(function () {
    'use strict';

    function getCallbackUrl() {
        var origin = window.location.origin || '';
        if (origin) {
            return origin + '/auth-firebase-callback.php';
        }
        return '/auth-firebase-callback.php';
    }
    var APPLE_PENDING_KEY = 'colobanes_apple_auth_pending';
    var APPLE_FLAG_KEY = 'colobanes_apple_redirect_in_progress';

    function isColobanesNativeApp() {
        if (window.__COLOBANES_NATIVE_APP) return true;
        if (window.flutter_inappwebview) return true;
        if (window.ColobanesNative && window.ColobanesNative.isNativeApp) return true;
        return /ColobanesApp/i.test(navigator.userAgent || '');
    }

    function hasNativeGoogleSignIn() {
        return !!(window.ColobanesNative && typeof window.ColobanesNative.signInWithGoogle === 'function')
            || !!window.flutter_inappwebview;
    }

    function hasNativeAppleSignIn() {
        return !!(window.ColobanesNative && typeof window.ColobanesNative.signInWithApple === 'function')
            || !!window.flutter_inappwebview;
    }

    function ensureSocialAuthMessage(wrap) {
        var msg = wrap.querySelector('.social-auth-message');
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
        var msg = wrap.querySelector('.social-auth-message');
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

    function disableSocialButtons(wrap, disabled) {
        if (!wrap) return;
        wrap.querySelectorAll('.google-auth-btn, .apple-auth-btn').forEach(function (btn) {
            btn.disabled = disabled;
        });
    }

    function parseFirebaseAuthResponse(response) {
        return response.text().then(function (text) {
            var trimmed = (text || '').trim();
            if (!trimmed) {
                throw new Error('Réponse serveur vide (code ' + response.status + ').');
            }
            try {
                return JSON.parse(trimmed);
            } catch (e) {
                if (trimmed.indexOf('<') === 0 || trimmed.indexOf('<!') === 0) {
                    throw new Error(
                        'Le serveur a renvoyé une page HTML au lieu de JSON (code '
                        + response.status
                        + '). Vérifiez la configuration Firebase sur le VPS.'
                    );
                }
                throw new Error('Réponse serveur invalide (code ' + response.status + ').');
            }
        });
    }

    function sendTokenToServer(button, provider, getTokenPromise) {
        if (typeof firebase === 'undefined' || !firebase.auth) {
            if ((provider === 'google' && hasNativeGoogleSignIn())
                || (provider === 'apple' && hasNativeAppleSignIn())) {
                // Firebase web optionnel si le token vient de l'app native
            } else {
                setMessage(button, 'Firebase Auth n’est pas chargé. Rechargez la page.', true);
                return;
            }
        }

        var accountType = button.getAttribute('data-social-auth-type') || button.getAttribute('data-google-auth-type') || 'auto';
        var redirect = button.getAttribute('data-social-auth-redirect') || button.getAttribute('data-google-auth-redirect') || '';
        var wrap = button.closest('.social-auth');
        var originalHtml = button.innerHTML;
        var loadingLabel = provider === 'apple' ? 'Connexion Apple...' : 'Connexion Google...';

        disableSocialButtons(wrap, true);
        button.innerHTML = button.innerHTML.replace(/Continuer avec (Google|Apple)/, loadingLabel);
        setMessage(button, '', false);

        getTokenPromise()
            .then(function (idToken) {
                return fetch(getCallbackUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify({
                        idToken: idToken,
                        accountType: accountType,
                        redirect: redirect,
                        provider: provider
                    })
                });
            })
            .then(function (response) {
                if (!response.ok) {
                    return parseFirebaseAuthResponse(response).then(function (data) {
                        throw new Error((data && data.message) ? data.message : ('Erreur serveur (' + response.status + ').'));
                    });
                }
                return parseFirebaseAuthResponse(response);
            })
            .then(function (data) {
                if (!data || !data.success || !data.redirect) {
                    throw new Error((data && data.message) ? data.message : 'Connexion refusée.');
                }
                window.location.href = data.redirect;
            })
            .catch(function (error) {
                setMessage(button, error && error.message ? error.message : 'Connexion annulée ou impossible.', true);
                disableSocialButtons(wrap, false);
                button.innerHTML = originalHtml;
            });
    }

    function getGoogleIdTokenNative() {
        if (window.ColobanesNative && typeof window.ColobanesNative.signInWithGoogle === 'function') {
            return window.ColobanesNative.signInWithGoogle().then(function (result) {
                if (!result || !result.idToken) {
                    throw new Error('Token Google introuvable depuis l’application.');
                }
                return result.idToken;
            });
        }
        if (window.flutter_inappwebview) {
            return window.flutter_inappwebview.callHandler('signInWithGoogle').then(function (result) {
                if (!result || !result.success || !result.idToken) {
                    throw new Error((result && result.error) ? result.error : 'Connexion Google impossible.');
                }
                return result.idToken;
            });
        }
        throw new Error('Connexion Google native indisponible.');
    }

    function getAppleIdTokenNative() {
        if (window.ColobanesNative && typeof window.ColobanesNative.signInWithApple === 'function') {
            return window.ColobanesNative.signInWithApple().then(function (result) {
                if (!result || !result.idToken) {
                    throw new Error('Token Apple introuvable depuis l’application.');
                }
                return result.idToken;
            });
        }
        if (window.flutter_inappwebview) {
            return window.flutter_inappwebview.callHandler('signInWithApple').then(function (result) {
                if (!result || !result.success || !result.idToken) {
                    throw new Error((result && result.error) ? result.error : 'Connexion Apple impossible.');
                }
                return result.idToken;
            });
        }
        throw new Error('Connexion Apple native indisponible.');
    }

    function signInWithGoogle(button) {
        if (isColobanesNativeApp()) {
            if (!hasNativeGoogleSignIn()) {
                setMessage(
                    button,
                    'Mettez à jour l’application COLObanes pour vous connecter avec Google.',
                    true
                );
                return;
            }

            sendTokenToServer(button, 'google', getGoogleIdTokenNative);
            return;
        }

        if (/ColobanesApp/i.test(navigator.userAgent || '') || window.flutter_inappwebview) {
            setMessage(button, 'Connexion Google indisponible dans l’application. Rechargez la page.', true);
            return;
        }

        var provider = new firebase.auth.GoogleAuthProvider();
        provider.setCustomParameters({ prompt: 'select_account' });

        sendTokenToServer(button, 'google', function () {
            return firebase.auth().signInWithPopup(provider).then(function (result) {
                if (!result.user) {
                    throw new Error('Compte Google introuvable.');
                }
                return result.user.getIdToken();
            });
        });
    }

    function isIosWebBrowser() {
        return /iPhone|iPad|iPod/i.test(navigator.userAgent || '') && !isColobanesNativeApp();
    }

    /**
     * Redirect Apple désactivé : sur iPhone Safari, le retour après Face ID envoie souvent
     * une requête que le serveur ne traite pas en JSON (« Réponse serveur invalide »).
     * Popup = même flux que Android / PC / Mac (validé en production).
     */
    function shouldUseAppleRedirect() {
        return false;
    }

    function urlHasFirebaseAuthReturn() {
        var href = (window.location.href || '').toLowerCase();
        var hash = (window.location.hash || '').toLowerCase();
        var search = (window.location.search || '').toLowerCase();
        return href.indexOf('/__/auth/handler') !== -1
            || hash.indexOf('access_token=') !== -1
            || hash.indexOf('id_token=') !== -1
            || search.indexOf('code=') !== -1
            || search.indexOf('state=') !== -1;
    }

    function isRecentApplePending(maxMs) {
        var pending = readAppleRedirectPending();
        if (!pending || !pending.ts) {
            return false;
        }
        return (Date.now() - (pending.ts || 0)) < (maxMs || 900000);
    }

    function appleRedirectWasStarted() {
        if (!isRecentApplePending() && !urlHasFirebaseAuthReturn()) {
            try {
                if (localStorage.getItem(APPLE_FLAG_KEY) === '1') {
                    clearAppleRedirectPending();
                }
            } catch (e) {
                // ignore
            }
            return false;
        }
        try {
            if (localStorage.getItem(APPLE_FLAG_KEY) === '1') {
                return true;
            }
        } catch (e) {
            // ignore
        }
        return !!readAppleRedirectPending();
    }

    function storeAppleRedirectPending(button) {
        var accountType = button.getAttribute('data-social-auth-type') || button.getAttribute('data-google-auth-type') || 'auto';
        var redirect = button.getAttribute('data-social-auth-redirect') || button.getAttribute('data-google-auth-redirect') || '';
        var payload = JSON.stringify({
            accountType: accountType,
            redirect: redirect,
            ts: Date.now()
        });
        try {
            sessionStorage.setItem(APPLE_PENDING_KEY, payload);
            localStorage.setItem(APPLE_PENDING_KEY, payload);
            localStorage.setItem(APPLE_FLAG_KEY, '1');
            return true;
        } catch (e) {
            return false;
        }
    }

    function readAppleRedirectPending() {
        try {
            var raw = sessionStorage.getItem(APPLE_PENDING_KEY) || localStorage.getItem(APPLE_PENDING_KEY);
            if (!raw) {
                return null;
            }
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function clearAppleRedirectPending() {
        try {
            sessionStorage.removeItem(APPLE_PENDING_KEY);
            localStorage.removeItem(APPLE_PENDING_KEY);
            localStorage.removeItem(APPLE_FLAG_KEY);
        } catch (e) {
            // ignore
        }
    }

    function userIsAppleProvider(user) {
        if (!user || !user.providerData || !user.providerData.length) {
            return false;
        }
        for (var i = 0; i < user.providerData.length; i++) {
            if (user.providerData[i].providerId === 'apple.com') {
                return true;
            }
        }
        return false;
    }

    function authReadyPromise(auth) {
        if (auth && typeof auth.authStateReady === 'function') {
            return auth.authStateReady();
        }
        return Promise.resolve();
    }

    function pickAppleUserFromRedirectResult(result) {
        if (!result || !result.user) {
            return null;
        }
        if (userIsAppleProvider(result.user)) {
            return result.user;
        }
        if (result.credential && result.credential.providerId === 'apple.com') {
            return result.user;
        }
        return result.user;
    }

    /**
     * Safari iOS : getRedirectResult() peut être vide alors que currentUser est déjà connecté (Face ID).
     */
    function resolveAppleFirebaseUser() {
        var auth = firebase.auth();
        return authReadyPromise(auth).then(function () {
            return auth.getRedirectResult();
        }).then(function (result) {
            var fromRedirect = pickAppleUserFromRedirectResult(result);
            if (fromRedirect) {
                return fromRedirect;
            }
            var current = auth.currentUser;
            if (current && (userIsAppleProvider(current) || urlHasFirebaseAuthReturn())) {
                return current;
            }
            return new Promise(function (resolve) {
                var settled = false;
                var unsub = auth.onAuthStateChanged(function (user) {
                    if (settled || !user) {
                        return;
                    }
                    if (userIsAppleProvider(user) || isRecentApplePending(300000)) {
                        settled = true;
                        unsub();
                        resolve(user);
                    }
                });
                setTimeout(function () {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    unsub();
                    var late = auth.currentUser;
                    resolve(late || null);
                }, isIosWebBrowser() ? 4000 : 2000);
            });
        });
    }

    function showAppleCompletionUi(isError, message) {
        var wrap = document.querySelector('.social-auth');
        if (!wrap) {
            return;
        }
        var btn = wrap.querySelector('.apple-auth-btn');
        if (!btn) {
            return;
        }
        if (isError) {
            setMessage(btn, message, true);
            disableSocialButtons(wrap, false);
        } else {
            disableSocialButtons(wrap, true);
            setMessage(btn, message || 'Finalisation de la connexion Apple…', false);
        }
    }

    function postAppleTokenToServer(pending, idToken) {
        return fetch(getCallbackUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                idToken: idToken,
                accountType: pending.accountType || 'auto',
                redirect: pending.redirect || '',
                provider: 'apple'
            })
        }).then(function (response) {
            if (!response.ok) {
                return parseFirebaseAuthResponse(response).then(function (data) {
                    throw new Error((data && data.message) ? data.message : ('Erreur serveur (' + response.status + ').'));
                });
            }
            return parseFirebaseAuthResponse(response);
        }).then(function (data) {
            if (!data || !data.success || !data.redirect) {
                throw new Error((data && data.message) ? data.message : 'Connexion refusée.');
            }
            window.location.href = data.redirect;
        });
    }

    /**
     * Après signInWithRedirect Apple, finalise la connexion au retour sur la page (Safari / Face ID).
     */
    var appleCompletionInFlight = false;

    function completeAppleRedirectIfNeeded() {
        if (!appleRedirectWasStarted() || appleCompletionInFlight) {
            return Promise.resolve();
        }
        if (typeof firebase === 'undefined' || !firebase.auth) {
            return Promise.resolve();
        }

        appleCompletionInFlight = true;
        showAppleCompletionUi(false, 'Finalisation de la connexion Apple…');

        var pending = readAppleRedirectPending() || { accountType: 'auto', redirect: '' };

        return resolveAppleFirebaseUser().then(function (user) {
            if (!user) {
                if (!isRecentApplePending(300000) && !urlHasFirebaseAuthReturn()) {
                    clearAppleRedirectPending();
                    return;
                }
                throw new Error(
                    'Connexion Apple non finalisée. Réessayez ou connectez-vous avec votre email et mot de passe.'
                );
            }
            return user.getIdToken(true).then(function (idToken) {
                clearAppleRedirectPending();
                return postAppleTokenToServer(pending, idToken);
            });
        }).catch(function (error) {
            clearAppleRedirectPending();
            showAppleCompletionUi(
                true,
                error && error.message ? error.message : 'Connexion Apple impossible.'
            );
        }).finally(function () {
            appleCompletionInFlight = false;
        });
    }

    function scheduleAppleRedirectCompletion(attempt) {
        if (!appleRedirectWasStarted()) {
            return;
        }
        attempt = attempt || 0;
        if (typeof firebase === 'undefined' || !firebase.auth) {
            if (attempt < 40) {
                setTimeout(function () {
                    scheduleAppleRedirectCompletion(attempt + 1);
                }, 100);
            }
            return;
        }
        completeAppleRedirectIfNeeded();
    }

    window.colobanesCompleteAppleRedirect = completeAppleRedirectIfNeeded;
    window.colobanesScheduleAppleRedirect = scheduleAppleRedirectCompletion;

    function signInWithApple(button) {
        if (isColobanesNativeApp()) {
            if (!hasNativeAppleSignIn()) {
                setMessage(
                    button,
                    'Mettez à jour l’application COLObanes pour vous connecter avec Apple.',
                    true
                );
                return;
            }

            sendTokenToServer(button, 'apple', getAppleIdTokenNative);
            return;
        }

        if (typeof firebase === 'undefined' || !firebase.auth) {
            setMessage(button, 'Firebase Auth n’est pas chargé. Rechargez la page.', true);
            return;
        }

        var provider = new firebase.auth.OAuthProvider('apple.com');
        provider.addScope('email');
        provider.addScope('name');

        // iPhone Safari : redirect ; desktop / localhost / Android : popup (comme Google).
        if (shouldUseAppleRedirect() && storeAppleRedirectPending(button)) {
            var wrap = button.closest('.social-auth');
            disableSocialButtons(wrap, true);
            setMessage(button, 'Redirection vers Apple…', false);
            firebase.auth().signInWithRedirect(provider);
            return;
        }

        sendTokenToServer(button, 'apple', function () {
            return firebase.auth().signInWithPopup(provider).then(function (result) {
                if (!result.user) {
                    throw new Error('Compte Apple introuvable.');
                }
                return result.user.getIdToken();
            });
        });
    }

    document.addEventListener('click', function (event) {
        var googleBtn = event.target.closest('.google-auth-btn');
        if (googleBtn) {
            signInWithGoogle(googleBtn);
            return;
        }

        var appleBtn = event.target.closest('.apple-auth-btn');
        if (appleBtn) {
            signInWithApple(appleBtn);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!appleRedirectWasStarted()) {
            return;
        }
        scheduleAppleRedirectCompletion(0);
    });
})();
