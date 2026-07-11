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
        'appleServicesId' => 'com.sugarpaper.app',
        'appleOAuthRedirectUri' => 'https://votre-projet.firebaseapp.com/__/auth/handler',
        'appleAndroidRedirectUri' => 'https://votre-domaine.com/auth/apple-callback',
        'appleTeamId' => 'VOTRE_TEAM_ID',
        'appleKeyId' => 'VOTRE_KEY_ID',
        'iosBundleId' => 'com.sugarpaper.app',
        'androidPackage' => 'com.sugarpaper.app',
    ],
];
