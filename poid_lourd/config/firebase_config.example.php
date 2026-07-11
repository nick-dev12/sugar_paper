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

    'auth' => [
        'webClientId' => 'VOTRE_WEB_CLIENT_ID.apps.googleusercontent.com',
        'iosClientId' => 'VOTRE_IOS_CLIENT_ID.apps.googleusercontent.com',
        'appleServicesId' => 'com.votre.app.web',
        'appleOAuthRedirectUri' => 'https://votre-projet.firebaseapp.com/__/auth/handler',
        'appleAndroidRedirectUri' => 'https://votre-domaine.com/auth/apple-callback',
        'appleTeamId' => 'VOTRE_TEAM_ID',
        'iosBundleId' => 'com.votre.app',
        'androidPackage' => 'com.votre.app',
    ],
];
