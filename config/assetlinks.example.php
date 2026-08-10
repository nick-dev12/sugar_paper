<?php
/**
 * Android App Links — empreintes SHA-256 pour .well-known/assetlinks.json
 *
 * Copiez ce fichier en config/assetlinks.php (non versionné) puis renseignez
 * les empreintes depuis Google Play Console :
 *   Release > Setup > App integrity > App signing
 *   → « Certificat de la clé de signature de l'application » → SHA-256
 *
 * Si vous signez aussi avec une clé d'upload locale, ajoutez son SHA-256 aussi.
 */
return [
    'package_name' => 'com.sugarpaper.app',
    'sha256_cert_fingerprints' => [
        '12:A3:64:3B:AA:B5:C7:67:17:AE:DC:B2:56:12:97:12:D5:06:BA:CD:E7:E9:AB:9C:90:06:C2:72:65:C1:96:4F',
    ],
];
