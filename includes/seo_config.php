<?php
/**
 * Configuration SEO centralisée — titres, descriptions, mots-clés et textes d'intro.
 */

if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}

/**
 * @return array<string, mixed>
 */
function get_seo_site_organization()
{
    $base = rtrim(get_site_base_url(), '/');

    return [
        'name' => 'Sugar Paper',
        'url' => $base,
        'logo' => $base . '/image/sugar_paper.jpg',
        'email' => 'sugarpaper26@gmail.com',
        'telephone' => '+221774161212',
        'streetAddress' => 'Hann Mariste 2 LOT R/01',
        'addressLocality' => 'Dakar',
        'addressRegion' => 'Dakar',
        'postalCode' => '',
        'addressCountry' => 'SN',
        'geo' => [
            'latitude' => 14.7392,
            'longitude' => -17.4254,
        ],
        'priceRange' => '$$',
        'areaServed' => 'Dakar, Sénégal',
    ];
}

/**
 * @return string
 */
function get_seo_default_keywords()
{
    return 'cake topper, topper gâteau, impression comestible, photo gâteau comestible, personnalisation gâteau, décoration gâteau, outils pâtisserie, papier sucre, Sugar Paper, Dakar, Sénégal';
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_home_meta()
{
    return [
        'title' => 'Sugar Paper — Cake topper, impression comestible & décoration gâteau à Dakar',
        'description' => 'Boutique à Dakar : cake toppers, impressions comestibles, photos sur gâteau et outils de pâtisserie. Personnalisation de gâteaux pour anniversaire, mariage et événements au Sénégal.',
        'keywords' => get_seo_default_keywords(),
    ];
}

/**
 * @return array<string, array{title: string, description: string, keywords: string, intro: string}>
 */
function get_seo_sections_meta()
{
    return [
        'cake_topper' => [
            'title' => 'Cake topper & topper gâteau — Dakar | Sugar Paper',
            'description' => 'Achetez vos cake toppers et décorations de gâteau à Dakar : anniversaire, mariage, baptême. Topper personnalisé ou prêt à poser. Livraison au Sénégal.',
            'keywords' => 'cake topper, topper gâteau, décoration gâteau anniversaire, topper mariage, topper baptême, décoration gâteau Dakar, Sugar Paper Sénégal',
            'intro' => 'Sugar Paper est votre boutique de référence pour les cake toppers et décorations de gâteau à Dakar. '
                . 'Nous proposons des toppers pour anniversaires, mariages, baptêmes et toutes vos célébrations : prénoms, âges, messages personnalisés et motifs festifs. '
                . 'Chaque topper est sélectionné pour s\'intégrer facilement sur vos gâteaux, que vous soyez pâtissier professionnel ou amateur passionné. '
                . 'Retrait possible à Hann Mariste 2 ou livraison selon votre zone au Sénégal. '
                . 'Parcourez notre catalogue, ajoutez au panier en ligne et personnalisez vos événements avec des décorations qui font la différence.',
        ],
        'photo_impression' => [
            'title' => 'Impression comestible & photo sur gâteau — Dakar | Sugar Paper',
            'description' => 'Impression comestible et photo sur gâteau à Dakar : décors alimentaires pour anniversaire, mariage et événements. Qualité professionnelle au Sénégal.',
            'keywords' => 'impression comestible, photo gâteau comestible, papier sucre, décor comestible, impression alimentaire gâteau, photo sur gâteau Dakar, Sugar Paper',
            'intro' => 'Transformez vos gâteaux en véritables œuvres avec nos impressions comestibles et photos sur gâteau. '
                . 'Sugar Paper propose des décors alimentaires de qualité pour sublimer vos créations : portraits, logos, messages et visuels personnalisés, imprimés sur support comestible. '
                . 'Idéal pour les anniversaires, mariages, événements d\'entreprise ou cadeaux sur mesure à Dakar et dans tout le Sénégal. '
                . 'Nos produits respectent les standards alimentaires et s\'appliquent facilement sur crème, pâte à sucre ou glaçage. '
                . 'Commandez en ligne et recevez vos impressions comestibles prêtes à décorer vos gâteaux.',
        ],
        'outils_patisserie' => [
            'title' => 'Outils de pâtisserie & matériel décor — Dakar | Sugar Paper',
            'description' => 'Outils de pâtisserie et matériel de décoration à Dakar : emporte-pièces, poches, spatules, accessoires pour gâteaux. Sugar Paper Sénégal.',
            'keywords' => 'outils pâtisserie, matériel décoration gâteau, emporte-pièce, poche à douille, accessoires pâtisserie Dakar, Sugar Paper',
            'intro' => 'Équipez-vous avec les outils de pâtisserie indispensables pour réussir vos décorations de gâteaux. '
                . 'Sugar Paper propose une sélection d\'accessoires professionnels et grand public : emporte-pièces, poches à douille, spatules, rouleaux, cutters et petit matériel de finition. '
                . 'Que vous prépariez un gâteau d\'anniversaire à la maison ou que vous gériez une activité de pâtisserie à Dakar, trouvez le matériel adapté à vos besoins. '
                . 'Nos outils sont choisis pour leur durabilité et leur facilité d\'utilisation au quotidien. '
                . 'Commandez en ligne et complétez votre arsenal de décoration avec des produits disponibles au Sénégal.',
        ],
        'decoration_gateau' => [
            'title' => 'Décoration de gâteau — Dakar | Sugar Paper',
            'description' => 'Décoration de gâteau à Dakar : paillettes, sprays, rubans, bougies et accessoires festifs pour sublimer vos créations. Sugar Paper Sénégal.',
            'keywords' => 'décoration gâteau, paillettes comestibles, spray alimentaire, ruban gâteau, bougie anniversaire, décoration gâteau Dakar, Sugar Paper',
            'intro' => 'Parcourez notre sélection de décorations de gâteau pour donner le dernier coup de polish à vos créations. '
                . 'Sugar Paper propose paillettes comestibles, sprays alimentaires, rubans, bougies, figurines et accessoires festifs pour anniversaires, mariages et événements. '
                . 'Complétez vos gâteaux avec des finitions élégantes et des touches de couleur, disponibles à Dakar avec livraison au Sénégal. '
                . 'Commandez en ligne et trouvez la décoration adaptée à chaque occasion.',
        ],
    ];
}

/**
 * @param string $section_key
 * @return array{title: string, description: string, keywords: string, intro: string}|null
 */
function get_seo_section_meta($section_key)
{
    $sections = get_seo_sections_meta();
    return $sections[$section_key] ?? null;
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_produits_meta()
{
    return [
        'title' => 'Catalogue décoration gâteau — Cake topper & impression comestible | Sugar Paper Dakar',
        'description' => 'Catalogue complet : cake toppers, impressions comestibles, outils pâtisserie et décoration de gâteau. Commandez en ligne à Dakar, livraison au Sénégal.',
        'keywords' => 'catalogue décoration gâteau, cake topper Dakar, impression comestible Sénégal, produits pâtisserie, Sugar Paper',
    ];
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_commande_perso_meta()
{
    return [
        'title' => 'Personnalisation gâteau sur mesure — Commande | Sugar Paper Dakar',
        'description' => 'Commande personnalisée de décoration de gâteau à Dakar : topper sur mesure, impression comestible, création unique pour anniversaire, mariage ou événement au Sénégal.',
        'keywords' => 'personnalisation gâteau, commande gâteau personnalisé, topper sur mesure, impression comestible personnalisée, décoration gâteau Dakar, Sugar Paper',
    ];
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_contact_meta()
{
    return [
        'title' => 'Contact Sugar Paper — Boutique décoration gâteau à Dakar',
        'description' => 'Contactez Sugar Paper à Hann Mariste 2, Dakar : cake topper, impression comestible, personnalisation gâteau. Téléphone, email et adresse au Sénégal.',
        'keywords' => 'contact Sugar Paper, boutique gâteau Dakar, Hann Mariste, décoration gâteau Sénégal',
    ];
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_nouveautes_meta()
{
    return [
        'title' => 'Nouveautés décoration gâteau — Cake topper & comestible | Sugar Paper',
        'description' => 'Dernières nouveautés Sugar Paper : cake toppers, impressions comestibles et outils pâtisserie. Découvrez les nouveaux produits à Dakar.',
        'keywords' => 'nouveautés décoration gâteau, nouveaux cake topper, impression comestible Dakar, Sugar Paper',
    ];
}

/**
 * @return array{title: string, description: string, keywords: string}
 */
function get_seo_promo_meta()
{
    return [
        'title' => 'Promotions décoration gâteau — Offres Sugar Paper Dakar',
        'description' => 'Promotions sur cake toppers, impressions comestibles et outils pâtisserie. Offres limitées sur la décoration de gâteau à Dakar, Sénégal.',
        'keywords' => 'promotion décoration gâteau, promo cake topper, offre impression comestible, Sugar Paper Dakar',
    ];
}

/**
 * @param string $section_key
 * @return string
 */
function render_section_seo_intro_html($section_key)
{
    $meta = get_seo_section_meta($section_key);
    if (!$meta || empty($meta['intro'])) {
        return '';
    }

    return '<div class="seo-content-intro"><p>' . htmlspecialchars($meta['intro']) . '</p></div>';
}
