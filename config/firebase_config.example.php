<?php
/**
 * Exemple — copiez en config/firebase_config.php
 * Firebase Console > Paramètres > Vos applications > Config SDK
 * VAPID : Cloud Messaging > Web Push certificates
 */
return [
    'apiKey' => 'VOTRE_API_KEY',
    'authDomain' => 'votre-projet.firebaseapp.com',
    'projectId' => 'votre-projet',
    'storageBucket' => 'votre-projet.firebasestorage.app',
    'messagingSenderId' => '000000000000',
    'appId' => '1:000000000000:web:xxxxxxxx',
    'measurementId' => 'G-XXXXXXXX',
    'vapidKey' => 'VOTRE_CLE_VAPID_PUBLIQUE',

    /**
     * Auth sociale (Google + Apple) — aligné Firebase Console + Apple Developer.
     * Regénérer l'app Flutter : php scripts/sync_sugarpaper_auth_config.php
     */
    'auth' => [
        'webClientId' => 'VOTRE_WEB_CLIENT_ID.apps.googleusercontent.com',
        'iosClientId' => 'VOTRE_IOS_CLIENT_ID.apps.googleusercontent.com',
        // Services ID Apple (Sign In with Apple → Web / Android), PAS le Bundle ID app
        'appleServicesId' => 'com.goobridge.sugarpaper.signin',
        'appleOAuthRedirectUri' => 'https://votre-projet.firebaseapp.com/__/auth/handler',
        'appleAndroidRedirectUri' => 'https://votre-domaine.com/auth/apple-callback',
        'appleTeamId' => 'XA8994VJC6',
        'appleKeyId' => 'GDH9F8THP9',
        'applePrimaryAppId' => 'com.goobridge.sugarpaper',
        'iosBundleId' => 'com.goobridge.sugarpaper',
        'androidPackage' => 'com.sugarpaper.app',
    ],
];
