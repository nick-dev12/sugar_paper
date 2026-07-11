/**
 * Service Worker PWA (installation, cache léger)
 * Les notifications FCM utilisent firebase-messaging-sw.js
 */
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});
