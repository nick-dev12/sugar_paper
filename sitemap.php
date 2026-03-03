<?php
/**
 * Sitemap XML pour le référencement
 * Accessible via /sitemap.php ou /sitemap.xml (avec rewrite)
 */
header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_categories.php';

$base = get_site_base_url();

// Pages statiques
$static_pages = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/produits.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/nouveautes.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/promo.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/commande-personnalisee.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/politique-confidentialite.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/conditions-utilisation.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
];

// Catégories
$categories = get_all_categories();
$category_pages = [];
foreach ($categories as $cat) {
    if (!empty($cat['nom'])) {
        $category_pages[] = [
            'loc' => '/categorie.php?id=' . (int)$cat['id'],
            'priority' => '0.85',
            'changefreq' => 'weekly',
            'lastmod' => isset($cat['date_modification']) ? $cat['date_modification'] : null
        ];
    }
}

// Produits actifs
$produits = get_all_produits('actif');
$product_pages = [];
foreach ($produits as $p) {
    $product_pages[] = [
        'loc' => '/produit.php?id=' . (int)$p['id'],
        'priority' => '0.8',
        'changefreq' => 'weekly',
        'lastmod' => isset($p['date_modification']) ? $p['date_modification'] : ($p['date_creation'] ?? null)
    ];
}

$urls = array_merge($static_pages, $category_pages, $product_pages);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<?php foreach ($urls as $u):
    $loc = $base . $u['loc'];
    $lastmod = '';
    if (!empty($u['lastmod'])) {
        $lastmod = date('Y-m-d', strtotime($u['lastmod']));
    } else {
        $lastmod = date('Y-m-d');
    }
?>
    <url>
        <loc><?php echo htmlspecialchars($loc); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo htmlspecialchars($u['changefreq'] ?? 'weekly'); ?></changefreq>
        <priority><?php echo htmlspecialchars($u['priority'] ?? '0.5'); ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
