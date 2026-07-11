<?php
/**
 * Modération des images produits (vendeurs).
 * Copiez vers config/image_moderation.php et renseignez les clés API.
 *
 * Fournisseur recommandé : Sightengine (https://sightengine.com/)
 * Alternative : Google Cloud Vision (Safe Search Detection)
 */
return [
    /** Activer la modération automatique */
    'enabled' => true,

    /**
     * sightengine | google_vision | none
     * none = pas de scan auto ; les images vendeur passent en file d'attente manuelle.
     */
    'provider' => 'sightengine',

    /** Rôles soumis à la modération (admin plateforme exclu par défaut) */
    'roles' => ['vendeur'],

    /**
     * strict : rejeter si contenu sensible détecté
     * hold   : mettre en attente si doute ou pas d'API
     */
    'policy' => 'strict',

    /** Seuil 0–1 au-delà duquel l'image est rejetée (Sightengine) */
    'reject_score' => 0.55,

    /** false = publication normale ; modération Super Admin après coup (fiche boutique) */
    'hold_product_until_approved' => false,

    'sightengine' => [
        'api_user' => '',
        'api_secret' => '',
        'models' => 'nudity-2.0,offensive,gore-2.0',
    ],

    'google_vision' => [
        'api_key' => '',
    ],
];
