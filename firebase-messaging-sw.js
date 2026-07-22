/**
 * Service Worker Firebase Cloud Messaging
 * Généré depuis config/firebase_config.php — ne pas éditer à la main.
 * Regénérer : php scripts/sync_firebase_sw.php
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

importScripts('https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyAOGTcYf7i-Jj6jj5KuTOJboFVagkbdBW4',
    authDomain: 'sugar-paper.firebaseapp.com',
    projectId: 'sugar-paper',
    storageBucket: 'sugar-paper.firebasestorage.app',
    messagingSenderId: '409713248489',
    appId: '1:409713248489:web:6bff9f5584e52c05a04878'
});

var messaging = firebase.messaging();

self.addEventListener('message', function (event) {
    if (!event.data || event.data.type !== 'FCM_PING') {
        return;
    }
    var port = event.ports && event.ports[0];
    if (port) {
        port.postMessage({
            type: 'FCM_PONG',
            ready: typeof firebase !== 'undefined' && firebase.apps && firebase.apps.length > 0
        });
    }
});

function resolveNotificationUrl(path) {
    var value = path || '/';
    if (value.indexOf('http://') === 0 || value.indexOf('https://') === 0) {
        return value;
    }
    return self.location.origin + (value.charAt(0) === '/' ? value : '/' + value);
}

function notifyPageClients(message, payload) {
    return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        list.forEach(function (client) {
            try {
                client.postMessage({ type: 'FCM_SW_LOG', message: message, payload: payload || null });
            } catch (e) { /* ignore */ }
        });
    });
}

messaging.onBackgroundMessage(function (payload) {
    console.log('[FCM-SW] Message arrière-plan', payload);
    var title = (payload.notification && payload.notification.title)
        || (payload.data && payload.data.title)
        || 'Sugar Paper';
    var body = (payload.notification && payload.notification.body)
        || (payload.data && payload.data.body)
        || '';
    var link = (payload.data && payload.data.link) ? payload.data.link : '/user/mes-commandes.php';
    var tag = (payload.data && payload.data.tag) ? payload.data.tag : ('sugar-paper-' + Date.now());
    var icon = resolveNotificationUrl('/icons/icon-192.png');

    return notifyPageClients('Message arrière-plan reçu', { title: title, body: body, tag: tag })
        .then(function () {
            return self.registration.showNotification(title, {
                body: body,
                icon: icon,
                badge: icon,
                tag: tag,
                requireInteraction: false,
                silent: false,
                vibrate: [200, 100, 200],
                data: Object.assign({}, payload.data || {}, { link: link })
            });
        });
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var raw = (event.notification.data && event.notification.data.link)
        || (event.notification.data && event.notification.data.url)
        || '/user/mes-commandes.php';
    var url = resolveNotificationUrl(raw);
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                var client = list[i];
                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(url).then(function () { return client.focus(); });
                    }
                    client.focus();
                    return;
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
