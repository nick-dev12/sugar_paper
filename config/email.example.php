<?php
/**
 * Configuration email - PHPMailer / SMTP
 * Copiez ce fichier en config/email.php et modifiez les valeurs
 * NE JAMAIS committer config/email.php (ajoutez-le à .gitignore)
 */

return [
    // Méthode d'envoi : 'smtp', 'sendmail', ou 'mail' (fonction mail() PHP)
    'method' => 'smtp',

    // Configuration SMTP - sugar-paper.com (TLS/STARTTLS sur port 587)
    'smtp' => [
        'host' => 'mail.sugar-paper.com',
        'port' => 587,
        'encryption' => 'tls',  // Port 587 = TLS (STARTTLS)
        'username' => 'service@sugar-paper.com',
        'password' => 'Ludvanne12@gmail.com',  // Mot de passe du compte service@sugar-paper.com
        'timeout' => 30,
    ],

    // Expéditeur par défaut
    'from' => [
        'email' => 'service@sugar-paper.com',
        'name' => 'Sugar Paper',
    ],

    // Email de contact (destinataire des messages du formulaire contact)
    'contact_email' => 'service@sugar-paper.com',

    // Mode debug : true pour afficher les erreurs SMTP
    'debug' => false,

    // URL du site pour les liens dans les emails (optionnel)
    // Si défini ici, surcharge config/site.php. Ex: 'https://sugar-paper.com'
    // 'site_url' => 'https://sugar-paper.com',
];