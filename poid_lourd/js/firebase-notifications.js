/**
 * Notifications push Firebase — activation client / admin
 */
(function () {
    'use strict';

    var LOG = '[FCM]';
    var FCM_SW_PATH = window.FCM_SW_PATH || '/firebase-messaging-sw.js';
    var FCM_ICON_PATH = '/image/logo_market.jpeg';
    var FCM_STORAGE_KEY = 'colobanes_fcm_enabled';
    var FCM_RESET_KEY = 'colobanes_fcm_force_reset';
    var PERMISSION_TIMEOUT_MS = 12000;
    var TOKEN_TIMEOUT_MS = 20000;
    var NATIVE_APP_MOBILE_MAX_WIDTH = 1024;
    var _activationInProgress = false;
    var _fcmRegistration = null;

    function isColobanesNativeApp() {
        if (window.__COLOBANES_NATIVE_APP === true) {
            return true;
        }
        if (window.ColobanesNative || window.flutter_inappwebview) {
            return true;
        }
        return /ColobanesApp/i.test(navigator.userAgent || '');
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: ' + NATIVE_APP_MOBILE_MAX_WIDTH + 'px)').matches;
    }

    function shouldHideWebPushButton() {
        return isColobanesNativeApp() && isMobileViewport();
    }

    function applyNativeAppNotificationUi() {
        var native = isColobanesNativeApp();
        document.documentElement.classList.toggle('is-colobanes-native-app', native);
        document.documentElement.classList.toggle('hide-web-push-notify-btn', shouldHideWebPushButton());
        return shouldHideWebPushButton();
    }

    function log() {
        if (typeof console !== 'undefined' && console.log) {
            console.log.apply(console, [LOG].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function warn() {
        if (typeof console !== 'undefined' && console.warn) {
            console.warn.apply(console, [LOG].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function getVapidKey() {
        var key = null;
        if (window.FIREBASE_VAPID_KEY) {
            key = window.FIREBASE_VAPID_KEY;
        } else if (window.FIREBASE_CONFIG && window.FIREBASE_CONFIG.vapidKey) {
            key = window.FIREBASE_CONFIG.vapidKey;
        }
        return key ? String(key).trim() : null;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function validateVapidKey(key) {
        if (!key) {
            return { valid: false, message: 'Clé VAPID absente dans la configuration.' };
        }
        key = String(key).trim();
        if (!/^[A-Za-z0-9_-]+$/.test(key)) {
            return { valid: false, message: 'Clé VAPID : caractères invalides.' };
        }
        try {
            var bytes = urlBase64ToUint8Array(key);
            if (bytes.length !== 65) {
                return {
                    valid: false,
                    message: 'Clé VAPID invalide (' + key.length + ' caractères, ' + bytes.length + ' octets — attendu 87 car. / 65 octets).\n\n'
                        + 'Firebase Console → Cloud Messaging → Web Push certificates :\n'
                        + '1. Cliquez sur le bouton Copier (icône) sur la ligne Key pair\n'
                        + '2. Ou générez une « Generate key pair » et collez la nouvelle clé dans config/firebase_config.php'
                };
            }
            if (bytes[0] !== 4) {
                return { valid: false, message: 'Clé VAPID : format EC invalide.' };
            }
            return { valid: true, message: 'OK' };
        } catch (e) {
            return { valid: false, message: 'Clé VAPID : décodage base64 impossible.' };
        }
    }

    function markEnabled() {
        try {
            localStorage.setItem(FCM_STORAGE_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function isMarkedEnabled() {
        try {
            return localStorage.getItem(FCM_STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function clearEnabledMark() {
        try {
            localStorage.removeItem(FCM_STORAGE_KEY);
        } catch (e) { /* ignore */ }
    }

    function shouldForceFcmReset() {
        try {
            return localStorage.getItem(FCM_RESET_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function clearForceFcmReset() {
        try {
            localStorage.removeItem(FCM_RESET_KEY);
        } catch (e) { /* ignore */ }
    }

    function requestForceFcmReset() {
        try {
            localStorage.setItem(FCM_RESET_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function resolveNotificationLink(path) {
        var value = path || '/';
        if (value.indexOf('http://') === 0 || value.indexOf('https://') === 0) {
            return value;
        }
        return window.location.origin + (value.charAt(0) === '/' ? value : '/' + value);
    }

    /**
     * Affiche une notification système.
     * Sur Windows/Chrome, new Notification() en premier plan n'affiche souvent PAS la bulle.
     * registration.showNotification() (Service Worker) est plus fiable.
     */
    function showBrowserNotification(title, body, tag, link) {
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
            warn('Permission notifications non accordée');
            return Promise.resolve(false);
        }

        var icon = resolveNotificationLink(FCM_ICON_PATH);
        var options = {
            body: body,
            icon: icon,
            badge: icon,
            tag: tag || ('colobanes-' + Date.now()),
            renotify: true,
            requireInteraction: false,
            silent: false,
            data: { link: link || '' }
        };

        function showViaNotificationApi() {
            try {
                var n = new Notification(title, options);
                n.onclick = function () {
                    window.focus();
                    if (link) {
                        window.location.href = link;
                    }
                    n.close();
                };
                log('Notification affichée via Notification API');
                return true;
            } catch (err) {
                warn('Notification API échouée', err);
                return false;
            }
        }

        if ('serviceWorker' in navigator) {
            return navigator.serviceWorker.ready
                .then(function (registration) {
                    log('Affichage notification via Service Worker…');
                    return registration.showNotification(title, options);
                })
                .then(function () {
                    log('Bulle système affichée via Service Worker ✓');
                    return true;
                })
                .catch(function (err) {
                    warn('Service Worker showNotification échoué, repli API', err);
                    return showViaNotificationApi();
                });
        }

        return Promise.resolve(showViaNotificationApi());
    }

    function getNotifyType(btn) {
        if (btn && btn.getAttribute('data-notify-type')) {
            return btn.getAttribute('data-notify-type');
        }
        return window.FIREBASE_NOTIFY_TYPE || 'user';
    }

    function updateButtonState(btn, state) {
        if (!btn) return;
        btn.disabled = state === 'loading';
        if (state === 'enabled') {
            btn.innerHTML = '<i class="fas fa-bell"></i> Notifications activées';
            btn.classList.add('notifications-enabled');
            btn.classList.remove('notifications-denied');
        } else if (state === 'denied') {
            btn.innerHTML = '<i class="fas fa-bell-slash"></i> Notifications bloquées';
            btn.classList.add('notifications-denied');
            btn.classList.remove('notifications-enabled');
        } else if (state === 'loading') {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Activation...';
        } else {
            btn.innerHTML = '<i class="fas fa-bell-slash"></i> Activer les notifications';
            btn.classList.remove('notifications-enabled', 'notifications-denied');
        }
    }

    function releaseActivationLock() {
        _activationInProgress = false;
    }

    function showNotifyHelp() {
        var panel = document.getElementById('notify-help-panel');
        if (panel) {
            panel.removeAttribute('hidden');
        }
        log('Panneau d\'aide affiché');
    }

    function hideNotifyHelp() {
        var panel = document.getElementById('notify-help-panel');
        if (panel) {
            panel.setAttribute('hidden', '');
        }
    }

    function checkBasics() {
        if (!window.isSecureContext) {
            alert('Les notifications nécessitent HTTPS ou localhost.');
            return false;
        }
        if (!('Notification' in window)) {
            alert('Votre navigateur ne supporte pas les notifications.');
            return false;
        }
        if (!('serviceWorker' in navigator)) {
            alert('Service Worker non supporté par ce navigateur.');
            return false;
        }
        if (typeof firebase === 'undefined') {
            alert('Firebase non chargé. Rechargez la page (Ctrl+F5).');
            return false;
        }
        if (!window.FIREBASE_CONFIG) {
            alert('Configuration Firebase absente sur cette page.');
            return false;
        }
        if (!getVapidKey()) {
            alert('Clé VAPID absente. Vérifiez config/firebase_config.php');
            return false;
        }
        var vapidValidation = validateVapidKey(getVapidKey());
        if (!vapidValidation.valid) {
            alert(vapidValidation.message);
            return false;
        }
        return true;
    }

    function getWorkerScriptUrl(reg) {
        var worker = (reg && (reg.active || reg.installing || reg.waiting)) || null;
        return worker && worker.scriptURL ? worker.scriptURL : '';
    }

    function isFcmRegistration(reg) {
        var url = getWorkerScriptUrl(reg);
        return url.indexOf('firebase-messaging-sw.js') !== -1;
    }

    function isStaleFcmRegistration(reg) {
        var url = getWorkerScriptUrl(reg);
        return url.indexOf('firebase-messaging-sw.php') !== -1;
    }

    function unregisterAllServiceWorkers() {
        return navigator.serviceWorker.getRegistrations().then(function (regs) {
            return Promise.all(regs.map(function (reg) {
                log('Suppression SW:', getWorkerScriptUrl(reg) || reg.scope);
                return reg.unregister();
            }));
        });
    }

    function registerFcmServiceWorker(forceFresh) {
        if (forceFresh) {
            _fcmRegistration = null;
            return unregisterAllServiceWorkers().then(function () {
                return registerFcmServiceWorker(false);
            });
        }

        if (_fcmRegistration && isFcmRegistration(_fcmRegistration) && !_fcmRegistration.waiting) {
            log('Réutilisation du Service Worker FCM');
            return _fcmRegistration.update().catch(function () {}).then(function () {
                return navigator.serviceWorker.ready;
            });
        }

        log('Enregistrement du Service Worker FCM…', FCM_SW_PATH);
        return navigator.serviceWorker.getRegistrations().then(function (regs) {
            var stale = regs.filter(function (reg) {
                return isStaleFcmRegistration(reg) || (!isFcmRegistration(reg) && getWorkerScriptUrl(reg));
            });
            return Promise.all(stale.map(function (reg) {
                log('Suppression SW obsolète:', getWorkerScriptUrl(reg));
                return reg.unregister();
            }));
        }).then(function () {
            return navigator.serviceWorker.register(FCM_SW_PATH, { scope: '/' });
        }).then(function (registration) {
            _fcmRegistration = registration;
            log('Service Worker FCM enregistré', registration.scope);
            return registration.update().catch(function () {});
        }).then(function () {
            return navigator.serviceWorker.ready;
        });
    }

    function withTimeout(promise, ms, message) {
        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () {
                reject(new Error(message || 'Délai dépassé'));
            }, ms);
            Promise.resolve(promise).then(function (v) {
                clearTimeout(timer);
                resolve(v);
            }).catch(function (e) {
                clearTimeout(timer);
                reject(e);
            });
        });
    }

    /**
     * Désabonne l’ancienne souscription push (ex. après changement de projet Firebase).
     * Limitée dans le temps : si le navigateur bloque, on continue quand même vers getToken().
     */
    function clearOldPushSubscription(registration) {
        if (!registration || !registration.pushManager) {
            return Promise.resolve();
        }
        return withTimeout(
            registration.pushManager.getSubscription().then(function (sub) {
                if (!sub) return;
                log('Suppression ancienne souscription push…');
                return sub.unsubscribe();
            }),
            8000,
            'UNSUBSCRIBE_TIMEOUT'
        ).catch(function (err) {
            if (err && err.message === 'UNSUBSCRIBE_TIMEOUT') {
                warn('Délai unsubscribe dépassé — poursuite vers getToken');
            }
            /* autres erreurs ignorées — getToken peut réparer */
        });
    }

    function saveToken(token, type) {
        log('Envoi du token au serveur…', type);
        var formData = new FormData();
        formData.append('token', token);
        formData.append('type', type);
        return fetch('/api/save_fcm_token.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (data) {
            if (data.success) {
                log('Token FCM enregistré avec succès ✓');
                markEnabled();
            } else {
                warn('Échec enregistrement token:', data.message || data);
                alert(data.message || 'Erreur lors de l\'enregistrement du token.');
            }
            return !!data.success;
        });
    }

    function waitForSwFirebaseReady(registration, timeoutMs) {
        timeoutMs = timeoutMs || 10000;
        return new Promise(function (resolve, reject) {
            if (!registration || !registration.active) {
                reject(new Error('Service Worker inactif'));
                return;
            }
            var done = false;
            var timer = setTimeout(function () {
                if (done) return;
                done = true;
                warn('SW Firebase ping timeout — poursuite quand même');
                resolve();
            }, timeoutMs);

            try {
                var channel = new MessageChannel();
                channel.port1.onmessage = function (event) {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    if (event.data && event.data.ready) {
                        log('Service Worker Firebase prêt ✓');
                        resolve();
                    } else {
                        warn('SW répond mais Firebase non initialisé dans le SW');
                        resolve();
                    }
                };
                registration.active.postMessage({ type: 'FCM_PING' }, [channel.port2]);
            } catch (err) {
                clearTimeout(timer);
                warn('Ping SW impossible:', err);
                resolve();
            }
        });
    }

    function tryGetToken(messaging, vapidKey, registration, label) {
        log('getToken —', label);
        var opts = { vapidKey: vapidKey };
        if (registration) {
            opts.serviceWorkerRegistration = registration;
        }
        return withTimeout(
            messaging.getToken(opts),
            TOKEN_TIMEOUT_MS,
            'Délai dépassé (' + label + ')'
        );
    }

    /**
     * Supprime les bases IndexedDB liées à Firebase pour forcer une réinitialisation propre.
     * Nécessaire quand on change de projet Firebase (nouveau FID, nouvelle VAPID).
     */
    function clearFirebaseIndexedDB() {
        var names = [
            'firebase-installations-database',
            'firebase-messaging-database',
            'firebase-heartbeat-database',
        ];
        return Promise.all(names.map(function (name) {
            return new Promise(function (resolve) {
                try {
                    var req = indexedDB.deleteDatabase(name);
                    req.onsuccess = function () { log('IDB nettoyée :', name); resolve(); };
                    req.onerror = resolve;
                    req.onblocked = resolve;
                } catch (e) { resolve(); }
            });
        }));
    }

    function getFcmToken(type, registration, retryFresh) {
        var vapidKey = getVapidKey();
        var messaging = firebase.messaging();
        var forceReset = shouldForceFcmReset() || !!retryFresh;

        log('Récupération du token FCM…', forceReset ? '(réinitialisation)' : '');

        var cleanStep = Promise.resolve();
        if (forceReset) {
            cleanStep = clearFirebaseIndexedDB().then(function () {
                return clearOldPushSubscription(registration);
            });
        }

        return cleanStep
            .then(function () { return waitForSwFirebaseReady(registration); })
            .then(function () {
                log('Étape getToken — SW actif:', !!(registration && registration.active));
                if (registration && registration.active && registration.active.scriptURL) {
                    log('Script SW:', registration.active.scriptURL);
                }
                return tryGetToken(messaging, vapidKey, registration, 'avec registration');
            })
            .catch(function (err) {
                if (retryFresh) {
                    throw err;
                }
                warn('getToken échoué, réenregistrement SW…', err && err.message ? err.message : err);
                return registerFcmServiceWorker(true).then(function (reg) {
                    return getFcmToken(type, reg, true);
                });
            })
            .then(function (token) {
                if (!token) {
                    alert('Impossible d\'obtenir le token FCM.');
                    return false;
                }
                log('Token FCM obtenu:', token.substring(0, 24) + '…');
                clearForceFcmReset();
                return saveToken(token, type);
            })
            .catch(function (err) {
                warn('Erreur getToken', err);
                var msg = err && err.message ? err.message : String(err);
                if (msg.indexOf('push service not available') !== -1) {
                    alert('Service push indisponible. Utilisez Chrome ou Edge (fenêtre normale, pas InPrivate).');
                } else if (msg.indexOf('permission') !== -1) {
                    alert('Permission notifications requise. Autorisez via le cadenas dans la barre d\'adresse.');
                } else if (msg.indexOf('API key') !== -1 || msg.indexOf('api-key') !== -1) {
                    alert('Clé API Firebase invalide ou mal restreinte.\n\nGoogle Cloud → Identifiants → Browser key :\n'
                        + '• Référents HTTP : http://localhost:5000/*\n'
                        + '• APIs autorisées : Firebase Installations, FCM Registration, Firebase Cloud Messaging\n'
                        + '• Ou désactivez les restrictions le temps du test');
                } else {
                    alert('Erreur FCM : ' + msg + '\n\nEssayez : F12 → Application → Service Workers → Unregister, puis Ctrl+F5.');
                }
                clearEnabledMark();
                return false;
            });
    }

    function completeActivation(type, btn, permission) {
        log('Réponse permission:', permission);

        if (permission === 'denied') {
            alert('Notifications refusées. Autorisez-les via l\'icône cadenas dans la barre d\'adresse.');
            updateButtonState(btn, 'denied');
            clearEnabledMark();
            return Promise.resolve(false);
        }
        if (permission !== 'granted') {
            showNotifyHelp();
            updateButtonState(btn, 'idle');
            return Promise.resolve(false);
        }

        window.FirebaseNotifications.setupForegroundHandler();

        return registerFcmServiceWorker(false)
            .then(function (registration) {
                return getFcmToken(type, registration, false);
            })
            .then(function (ok) {
                if (ok) {
                    updateButtonState(btn, 'enabled');
                    window.FirebaseNotifications.setupForegroundHandler();
                    hideNotifyHelp();
                    log('=== Notifications activées ✓ ===');
                } else {
                    updateButtonState(btn, 'idle');
                }
                return !!ok;
            });
    }

    window.FirebaseNotifications = {
        handleClick: function (btn) {
            if (_activationInProgress || !btn) {
                if (_activationInProgress) log('Activation déjà en cours…');
                return;
            }

            var earlyPermPromise = null;
            if (typeof Notification !== 'undefined' && window.isSecureContext && Notification.permission === 'default') {
                log('Demande permission (clic utilisateur)…');
                try {
                    earlyPermPromise = Notification.requestPermission();
                } catch (err) {
                    earlyPermPromise = Promise.reject(err);
                }
            } else if (typeof Notification === 'undefined') {
                alert('Votre navigateur ne supporte pas les notifications.');
                return;
            } else if (!window.isSecureContext) {
                alert('Les notifications nécessitent HTTPS ou localhost.');
                return;
            }

            if (!checkBasics()) return;

            var type = getNotifyType(btn);
            log('=== Activation notifications (' + type + ') ===');

            if (Notification.permission === 'denied') {
                alert('Notifications bloquées. Cliquez sur le cadenas → Notifications → Autoriser.');
                updateButtonState(btn, 'denied');
                return;
            }

            _activationInProgress = true;
            updateButtonState(btn, 'loading');

            var permPromise;
            if (earlyPermPromise) {
                permPromise = withTimeout(
                    Promise.resolve(earlyPermPromise),
                    PERMISSION_TIMEOUT_MS,
                    'PERMISSION_TIMEOUT'
                );
            } else if (Notification.permission === 'granted') {
                permPromise = Promise.resolve('granted');
            } else {
                permPromise = Promise.resolve(Notification.permission);
            }

            permPromise
                .then(function (permission) {
                    return completeActivation(type, btn, permission);
                })
                .catch(function (err) {
                    warn('Activation échouée', err.message || err);
                    if (err && err.message === 'PERMISSION_TIMEOUT') {
                        showNotifyHelp();
                    } else {
                        alert('Erreur : ' + (err.message || err));
                    }
                    updateButtonState(btn, 'idle');
                })
                .finally(function () {
                    releaseActivationLock();
                });
        },

        enable: function (type, buttonEl) {
            window.FirebaseNotifications.handleClick(buttonEl || document.getElementById('btn-enable-notifications'));
        },

        continueAfterManualGrant: function (buttonEl) {
            var btn = buttonEl || document.getElementById('btn-enable-notifications');
            if (!btn || _activationInProgress) return;

            if (Notification.permission === 'denied') {
                updateButtonState(btn, 'denied');
                return;
            }
            if (Notification.permission !== 'granted') {
                showNotifyHelp();
                return;
            }

            hideNotifyHelp();
            _activationInProgress = true;
            updateButtonState(btn, 'loading');

            completeActivation(getNotifyType(btn), btn, 'granted')
                .catch(function (err) {
                    warn('Finalisation échouée', err);
                    updateButtonState(btn, 'idle');
                })
                .finally(function () {
                    releaseActivationLock();
                });
        },

        syncButton: function (buttonEl) {
            var btn = buttonEl || document.getElementById('btn-enable-notifications');
            if (!btn) return;

            if (Notification.permission === 'denied') {
                updateButtonState(btn, 'denied');
                clearEnabledMark();
                return;
            }
            if (Notification.permission !== 'granted') {
                updateButtonState(btn, 'idle');
                return;
            }

            if (isMarkedEnabled()) {
                updateButtonState(btn, 'enabled');
                window.FirebaseNotifications.setupForegroundHandler();
                return;
            }

            /* Pas d'auto-activation au chargement — évite boucles et timeouts */
            updateButtonState(btn, 'idle');
        },

        bindButton: function (buttonEl) {
            var btn = buttonEl || document.getElementById('btn-enable-notifications');
            if (!btn || btn.dataset.notifyBound === '1') return;
            btn.dataset.notifyBound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                window.FirebaseNotifications.handleClick(btn);
            }, { passive: false });

            log('Bouton notifications lié');
        },

        bindHelpPanel: function () {
            var continueBtn = document.getElementById('btn-notify-continue');
            if (continueBtn && continueBtn.dataset.notifyBound !== '1') {
                continueBtn.dataset.notifyBound = '1';
                continueBtn.addEventListener('click', function () {
                    window.FirebaseNotifications.continueAfterManualGrant(
                        document.getElementById('btn-enable-notifications')
                    );
                });
            }
        },

        _foregroundHandlerSetup: false,
        setupForegroundHandler: function () {
            if (window.FirebaseNotifications._foregroundHandlerSetup) return;
            if (typeof firebase === 'undefined' || !firebase.messaging) return;
            window.FirebaseNotifications._foregroundHandlerSetup = true;
            firebase.messaging().onMessage(function (payload) {
                log('Notification reçue (premier plan)', payload);
                var title = (payload.notification && payload.notification.title)
                    || (payload.data && payload.data.title) || 'COLObanes';
                var body = (payload.notification && payload.notification.body)
                    || (payload.data && payload.data.body) || '';
                var tag = (payload.data && payload.data.tag) ? payload.data.tag : ('colobanes-' + Date.now());
                var link = (payload.data && payload.data.link) ? resolveNotificationLink(payload.data.link) : '';
                showBrowserNotification(title, body, tag, link).then(function (ok) {
                    if (!ok) {
                        warn('Impossible d\'afficher la bulle — vérifiez Paramètres Windows → Notifications → Chrome');
                    }
                });
            });
        },

        isColobanesNativeApp: isColobanesNativeApp,
        shouldHideWebPushButton: shouldHideWebPushButton,
        applyNativeAppNotificationUi: applyNativeAppNotificationUi,

        boot: function () {
            log('Initialisation module notifications');
            log('Projet:', window.FIREBASE_CONFIG && window.FIREBASE_CONFIG.projectId, '| SW:', FCM_SW_PATH);

            applyNativeAppNotificationUi();

            if (shouldHideWebPushButton()) {
                log('App COLObanes (mobile) — activation web push masquée (FCM natif)');
                return;
            }

            window.FirebaseNotifications.bindButton(document.getElementById('btn-enable-notifications'));
            window.FirebaseNotifications.bindHelpPanel();
            window.FirebaseNotifications.syncButton(document.getElementById('btn-enable-notifications'));

            if (typeof Notification !== 'undefined'
                && Notification.permission === 'granted'
                && isMarkedEnabled()) {
                window.FirebaseNotifications.setupForegroundHandler();
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function (event) {
                    if (event.data && event.data.type === 'FCM_SW_LOG') {
                        log('SW:', event.data.message, event.data.payload || '');
                    }
                });
            }
        }
    };

    function scheduleNativeAppUiRecheck() {
        var delays = [300, 1200];
        delays.forEach(function (delayMs) {
            setTimeout(function () {
                var wasHidden = document.documentElement.classList.contains('hide-web-push-notify-btn');
                var hideNow = applyNativeAppNotificationUi();
                if (!wasHidden && hideNow && window.FirebaseNotifications) {
                    log('App COLObanes détectée — masquage du bouton web push');
                }
            }, delayMs);
        });
    }

    function initFirebaseNotifications() {
        window.FirebaseNotifications.boot();
        scheduleNativeAppUiRecheck();

        var mq = window.matchMedia('(max-width: ' + NATIVE_APP_MOBILE_MAX_WIDTH + 'px)');
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', applyNativeAppNotificationUi);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(applyNativeAppNotificationUi);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFirebaseNotifications);
    } else {
        initFirebaseNotifications();
    }
})();
