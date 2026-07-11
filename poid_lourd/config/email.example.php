<?php
/**
 * Configuration email - PHPMailer / SMTP
 * Copiez ce fichier en config/email.php et modifiez les valeurs
 * NE JAMAIS committer config/email.php (ajoutez-le à .gitignore)
 */

return [
    // Méthode d'envoi : 'smtp', 'sendmail', ou 'mail' (fonction mail() PHP)
    'method' => 'smtp',

    // SMTP — Ex. mail.colobanes.com, port 465 (SSL)
    'smtp' => [
        'host' => 'mail.colobanes.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'no-replay@colobanes.com',
        'password' => 'VOTRE_MOT_DE_PASSE_SMTP',
        'timeout' => 30,
        'verify_ssl' => false,
    ],

    'from' => [
        'email' => 'no-replay@colobanes.com',
        'name' => 'Colobanes',
    ],

    // Email de contact (destinataire des messages du formulaire contact)
    'contact_email' => 'contact@colobanes.sn',

    // Mode debug : true pour afficher les erreurs SMTP
    'debug' => false,

    // URL du site pour les liens dans les emails (optionnel)
    // Si défini ici, surcharge config/site.php. Ex: 'https://www.colobanes.sn'
    // 'site_url' => 'https://www.colobanes.sn',
];