<?php
/**
 * Marque et textes SEO par défaut — COLObanes (marketplace Sénégal, Colobane Dakar)
 * Inclure avant seo_meta.php ou pour les expéditeurs email / factures.
 */
if (!defined('SITE_BRAND_NAME')) {
    define('SITE_BRAND_NAME', 'COLObanes');
}
if (!defined('SITE_BRAND_TAGLINE')) {
    define('SITE_BRAND_TAGLINE', 'Marketplace Sénégal — Colobane, Dakar — toutes les boutiques, tous les produits');
}
if (!defined('SITE_BRAND_CONTACT_EMAIL')) {
    define('SITE_BRAND_CONTACT_EMAIL', 'contact@colobanes.com');
}

if (!function_exists('site_brand_seo_title_default')) {
    function site_brand_seo_title_default() {
        return SITE_BRAND_NAME . ' (Colobane) — Marketplace Sénégal | Marché en ligne Dakar, boutiques & produits';
    }
}
if (!function_exists('site_brand_seo_description_default')) {
    function site_brand_seo_description_default() {
        return SITE_BRAND_NAME . ' — aussi Colobane, Colobanes — est le marketplace du Sénégal qui rassemble les boutiques en ligne au même endroit. '
            . 'Marché de Colobane, Dakar : mode, alimentaire, high-tech, artisanat, beauté, téléphones, électroménager et plus. '
            . 'Achat en ligne, vendeurs locaux, livraison et shopping facile partout au Sénégal.';
    }
}
if (!function_exists('site_brand_seo_keywords_default')) {
    function site_brand_seo_keywords_default() {
        return implode(', ', [
            'COLObanes', 'Colobane', 'Colobanes', 'COLOBANE', 'COLOBANES', 'colobane', 'colobanes',
            'colobanes.com', 'Colobanes.com', 'Colobane marketplace', 'Colobane marché en ligne',
            'Colobane Dakar', 'Marché Colobane', 'Marché de Colobane', 'Colobane Sénégal',
            'Colobane marché en ligne Sénégal', 'Colobane shopping', 'Colobane e-commerce',
            'marketplace Sénégal', 'marketplace Dakar', 'marché en ligne Sénégal', 'marché en ligne Dakar',
            'achat en ligne Sénégal', 'achat en ligne Dakar', 'shopping en ligne Sénégal',
            'e-commerce Sénégal', 'e-commerce Dakar', 'boutique en ligne Sénégal', 'boutiques en ligne Dakar',
            'boutique en ligne Colobane', 'vente en ligne Sénégal', 'commerce en ligne Sénégal',
            'plateforme marketplace', 'multi-vendeurs', 'marketplace Afrique de l\'Ouest',
            'marketplace Afrique', 'shopping Colobane', 'produits Sénégal', 'livraison Dakar',
            'high-tech Sénégal', 'mode Sénégal', 'alimentaire Sénégal', 'artisanat Sénégal',
            'téléphones Dakar', 'électroménager Sénégal', 'vendeurs locaux Sénégal',
            'application Colobanes', 'app Colobane', 'Colobane app', 'Colobanes app',
        ]);
    }
}
