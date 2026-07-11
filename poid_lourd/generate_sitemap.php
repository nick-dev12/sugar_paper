<?php
/**
 * Génère le fichier statique sitemap.xml (repli si la réécriture Apache est désactivée).
 * CLI : php generate_sitemap.php
 *
 * À exécuter après déploiement ou mise à jour massive du catalogue pour synchroniser la copie locale.
 */
require_once __DIR__ . '/includes/sitemap_data.php';

$urls = build_sitemap_url_rows();
$base = get_site_base_url();

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($urls as $u) {
    $loc = rtrim((string) $base, '/') . $u['loc'];
    if (!empty($u['lastmod'])) {
        $ts = strtotime($u['lastmod']);
        $lastmod = $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    } else {
        $lastmod = date('Y-m-d');
    }
    $changefreq = htmlspecialchars($u['changefreq'] ?? 'weekly', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $priority = htmlspecialchars($u['priority'] ?? '0.5', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xml .= "    <url>\n";
    $xml .= '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    $xml .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
    $xml .= "        <changefreq>{$changefreq}</changefreq>\n";
    $xml .= "        <priority>{$priority}</priority>\n";
    if (!empty($u['image'])) {
        $xml .= "        <image:image>\n";
        $xml .= '            <image:loc>' . htmlspecialchars($u['image'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</image:loc>\n";
        if (!empty($u['image_title'])) {
            $xml .= '            <image:title>' . htmlspecialchars($u['image_title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</image:title>\n";
        }
        $xml .= "        </image:image>\n";
    }
    $xml .= "    </url>\n";
}

$xml .= '</urlset>';

$file = __DIR__ . '/sitemap.xml';
if (file_put_contents($file, $xml) !== false) {
    echo "sitemap.xml créé (" . count($urls) . " URLs) — base : {$base}\n";
} else {
    echo "Erreur : impossible d'écrire sitemap.xml\n";
}
