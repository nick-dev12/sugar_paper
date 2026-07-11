<?php
/**
 * Configuration itinéraires livreurs (évitement des autoroutes à péage).
 * Copiez en config/routing.php si vous personnalisez les services.
 */
return [
    // Serveur Valhalla public (sans clé) — use_tolls=0 évite les routes à péage
    'valhalla_url' => 'https://valhalla1.openstreetmap.de/route',

    // Clé OpenRouteService (optionnelle) — évitement strict des péages si renseignée
    'openrouteservice_api_key' => '',

    // Toujours tenter d'éviter les péages
    'avoid_tolls' => true,
];
