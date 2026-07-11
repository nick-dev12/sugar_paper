<?php
/**
 * Exemple — copiez en config/firebase_server.php
 */
return [
    'credentials_path' => __DIR__ . '/votre-projet-firebase-adminsdk-xxxxx.json',
    'cacert_path' => __DIR__ . '/cacert.pem',
    /** true = message d'erreur technique visible dans la réponse JSON (dépannage uniquement) */
    'auth_debug' => false,
    /** Clé optionnelle pour scripts/diagnose_firebase_auth.php?key=... */
    'auth_diagnose_key' => '',
];
