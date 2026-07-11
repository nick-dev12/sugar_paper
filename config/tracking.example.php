<?php
/**
 * Configuration suivi GPS livreurs (Socket.io + PHP).
 * Copiez ce fichier en config/tracking.php et modifiez les secrets en production.
 */
return [
    // Secret partagé entre PHP et le serveur Node (générez une chaîne longue aléatoire)
    'internal_secret' => 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE',

    // Serveur Node.js (écoute en local, Nginx fait le proxy WebSocket)
    'node_host' => '127.0.0.1',
    'node_port' => 3001,

    // Chemin Socket.io (identique côté Nginx et client)
    'socket_path' => '/socket.io',

    // URL publique du site (sans slash final) — utilisée par l'admin pour le client JS
    'public_site_url' => 'https://sugar-paper.com',

    // URL Socket.io côté client (optionnel). En local WAMP : http://127.0.0.1:3001
    // Si vide, le client utilise public_site_url (proxy Nginx /socket.io recommandé en prod).
    'socket_url' => '',

    // Durée de validité du token livreur (heures)
    'livreur_token_ttl_hours' => 720,

    // Durée du token « watch » admin/client pour rejoindre une room (minutes)
    'watch_token_ttl_minutes' => 480,

    // Origines autorisées pour Socket.io (CORS)
    'cors_origins' => [
        'https://sugar-paper.com',
        'https://www.sugar-paper.com',
    ],
];
