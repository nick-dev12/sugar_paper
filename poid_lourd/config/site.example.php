<?php
/**
 * Configuration de l'URL du site (pour les liens dans les emails, notifications, etc.)
 * Copiez ce fichier en config/site.php et modifiez site_url pour la production
 * 
 * En production : https://www.colobanes.sn (ou votre domaine COLObanes)
 * En développement : http://localhost:5000 ou laisser vide pour utiliser $_SERVER['HTTP_HOST']
 */

return [
    // URL de base du site (sans slash final)
    // Si vide, l'URL est déduite automatiquement de la requête HTTP
    'site_url' => 'https://colobanes.com',
];