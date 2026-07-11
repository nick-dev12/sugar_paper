<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Boutiques et produits proches de la position du visiteur.
 * La position est capturée par le navigateur (consentement) et envoyée
 * via un formulaire POST classique à set-location.php (session).
 */

session_start_persistent();

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/includes/geo_location_service.php';
require_once __DIR__ . '/includes/marketplace_country_filter.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$base = get_site_base_url();
$seo_title = 'Boutiques proches — ' . SITE_BRAND_NAME . ' (Colobane) | Marketplace Dakar';
$seo_description = 'Trouvez les boutiques et produits proches de vous sur ' . SITE_BRAND_NAME . ' (Colobane, Dakar). Marketplace Sénégal, achat en ligne, vendeurs locaux autour de vous.';
$seo_keywords = site_brand_seo_keywords_default() . ', boutiques proches, produits proches, géolocalisation Dakar, Colobane proche';
$seo_canonical = $base . '/boutiques-proches.php';

$geo_loc = geo_session_get_location();
$geo_error = !empty($_GET['geo_error']);

/* Rayon choisi (km) — 0 = tout le pays */
$rayons_autorises = [5, 15, 50, 0];
$rayon = isset($_GET['rayon']) ? (int) $_GET['rayon'] : 15;
if (!in_array($rayon, $rayons_autorises, true)) {
    $rayon = 15;
}

/* Restreindre au pays marketplace sélectionné (filtre existant conservé) */
$country = marketplace_get_selected_country_code();

$boutiques = [];
$produits = [];
if ($geo_loc !== null) {
    $boutiques = geo_boutiques_proches($geo_loc['lat'], $geo_loc['lng'], (float) $rayon, 30, $country);
    $produits = geo_produits_proches($geo_loc['lat'], $geo_loc['lng'], (float) $rayon, 24, $country);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
    .geo-page-container {
        max-width: 1200px;
        margin: 40px auto 100px;
        padding: 0 20px;
        min-height: calc(100vh - 300px);
    }

    .geo-page-title {
        font-size: 28px;
        color: var(--titres);
        margin-bottom: 8px;
        font-family: var(--font-titres);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .geo-page-title i {
        color: var(--couleur-dominante);
    }

    .geo-page-subtitle {
        color: var(--gris-moyen);
        margin-bottom: 28px;
        font-size: 14px;
    }

    .geo-cta-box {
        background: var(--bleu-pale);
        border: 1px solid var(--border-input);
        border-radius: 12px;
        padding: 28px;
        text-align: center;
        max-width: 560px;
        margin: 40px auto;
    }

    .geo-cta-box i.geo-cta-icon {
        font-size: 42px;
        color: var(--couleur-dominante);
        margin-bottom: 14px;
        display: block;
    }

    .geo-cta-box p {
        font-size: 14px;
        color: var(--texte-fonce);
        margin-bottom: 18px;
        line-height: 1.6;
    }

    .btn-geo-main {
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        border: none;
        border-radius: 8px;
        padding: 13px 26px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-geo-main:hover {
        background: var(--couleur-dominante-hover);
        transform: translateY(-2px);
        box-shadow: var(--ombre-promo);
    }

    .geo-status-msg {
        margin-top: 14px;
        font-size: 13px;
        padding: 10px 12px;
        border-radius: 6px;
        background: var(--blanc-neige);
        color: var(--gris-fonce);
    }

    .geo-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 28px;
    }

    .geo-toolbar .geo-position-chip {
        background: var(--success-bg);
        border: 1px solid var(--success-border);
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 13px;
        color: var(--texte-fonce);
    }

    .geo-rayon-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .geo-rayon-links a {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        text-decoration: none;
        border: 1px solid var(--border-input);
        color: var(--texte-fonce);
        background: var(--blanc);
        transition: all 0.2s;
    }

    .geo-rayon-links a.active,
    .geo-rayon-links a:hover {
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        border-color: var(--couleur-dominante);
    }

    .geo-refresh-form button,
    .geo-clear-form button {
        background: none;
        border: 1px solid var(--border-input);
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 13px;
        cursor: pointer;
        color: var(--gris-fonce);
        transition: all 0.2s;
    }

    .geo-refresh-form button:hover {
        border-color: var(--couleur-dominante);
        color: var(--couleur-dominante);
    }

    .geo-section-title {
        font-size: 20px;
        color: var(--titres);
        margin: 30px 0 16px;
        font-family: var(--font-titres);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .geo-boutiques-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 16px;
    }

    .geo-boutique-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 18px;
        box-shadow: var(--glass-shadow);
        display: flex;
        gap: 14px;
        align-items: center;
        text-decoration: none;
        transition: all 0.25s;
    }

    .geo-boutique-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--ombre-gourmande);
    }

    .geo-boutique-logo {
        width: 54px;
        height: 54px;
        border-radius: 12px;
        object-fit: cover;
        background: var(--blanc-neige);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--couleur-dominante);
        font-size: 22px;
    }

    .geo-boutique-info h3 {
        font-size: 15px;
        color: var(--titres);
        margin: 0 0 4px;
        font-weight: 600;
    }

    .geo-boutique-distance {
        font-size: 13px;
        color: var(--orange-fonce);
        font-weight: 600;
    }

    .geo-boutique-adresse {
        font-size: 12px;
        color: var(--gris-moyen);
        margin-top: 3px;
    }

    .geo-produits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .geo-produit-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--glass-shadow);
        text-decoration: none;
        transition: all 0.25s;
        display: block;
    }

    .geo-produit-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--ombre-gourmande);
    }

    .geo-produit-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
    }

    .geo-produit-body {
        padding: 12px 14px 14px;
    }

    .geo-produit-body .geo-produit-boutique {
        font-size: 11px;
        color: var(--orange-fonce);
        font-weight: 600;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .geo-produit-body h3 {
        font-size: 14px;
        color: var(--titres);
        margin: 0 0 6px;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .geo-produit-prix {
        font-size: 15px;
        font-weight: 700;
        color: var(--couleur-dominante);
    }

    .geo-produit-prix .promo-old {
        font-size: 12px;
        color: var(--gris-clair);
        text-decoration: line-through;
        margin-left: 6px;
        font-weight: 400;
    }

    .geo-empty {
        background: var(--blanc-neige);
        border-radius: 12px;
        padding: 32px;
        text-align: center;
        color: var(--gris-moyen);
        font-size: 14px;
    }

    .geo-empty i {
        font-size: 30px;
        display: block;
        margin-bottom: 10px;
        color: var(--gris-clair);
    }
    </style>
</head>

<body>
    <?php include('nav_bar.php'); ?>

    <div class="geo-page-container">
        <h1 class="geo-page-title">
            <i class="fas fa-location-crosshairs"></i> Boutiques proches de moi
        </h1>
        <p class="geo-page-subtitle">
            Découvrez en priorité les produits des boutiques situées autour de vous.
        </p>

        <?php if ($geo_loc === null): ?>
        <!-- Pas encore de position : demander le consentement -->
        <div class="geo-cta-box">
            <i class="fas fa-map-location-dot geo-cta-icon"></i>
            <p>
                Autorisez la localisation pour voir les boutiques et produits
                les plus proches de vous.<br>
                Votre position n'est utilisée que pour trier les résultats et
                n'est jamais partagée sans votre accord.
            </p>
            <form method="POST" action="/set-location.php" id="geo-locate-form">
                <input type="hidden" name="redirect" value="/boutiques-proches.php">
                <input type="hidden" name="geo_lat" id="geo_lat" value="">
                <input type="hidden" name="geo_lng" id="geo_lng" value="">
                <input type="hidden" name="geo_precision" id="geo_precision" value="">
                <input type="hidden" name="geo_source" id="geo_source" value="">
                <button type="button" class="btn-geo-main" id="btn-geo-locate">
                    <i class="fas fa-location-crosshairs"></i> Activer ma position
                </button>
            </form>
            <div id="geo-status" class="geo-status-msg" style="display:none;"></div>
            <?php if ($geo_error): ?>
            <div class="geo-status-msg">
                <i class="fas fa-triangle-exclamation"></i>
                Position non reconnue. Veuillez réessayer.
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Position connue : résultats -->
        <div class="geo-toolbar">
            <span class="geo-position-chip">
                <i class="fas fa-location-dot"></i> Position activée
                <?php if (!empty($geo_loc['precision'])): ?>
                (± <?php echo (int) $geo_loc['precision']; ?> m)
                <?php endif; ?>
            </span>

            <div class="geo-rayon-links">
                <?php foreach ($rayons_autorises as $r): ?>
                <a href="/boutiques-proches.php?rayon=<?php echo $r; ?>"
                    class="<?php echo $r === $rayon ? 'active' : ''; ?>">
                    <?php echo $r === 0 ? 'Tout le pays' : $r . ' km'; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="/set-location.php" id="geo-locate-form" class="geo-refresh-form">
                <input type="hidden" name="redirect" value="/boutiques-proches.php?rayon=<?php echo $rayon; ?>">
                <input type="hidden" name="geo_lat" id="geo_lat" value="">
                <input type="hidden" name="geo_lng" id="geo_lng" value="">
                <input type="hidden" name="geo_precision" id="geo_precision" value="">
                <input type="hidden" name="geo_source" id="geo_source" value="">
                <button type="button" id="btn-geo-locate">
                    <i class="fas fa-rotate"></i> Actualiser ma position
                </button>
            </form>

            <form method="POST" action="/set-location.php" class="geo-clear-form">
                <input type="hidden" name="redirect" value="/boutiques-proches.php">
                <input type="hidden" name="action" value="clear_location">
                <button type="submit">
                    <i class="fas fa-xmark"></i> Désactiver
                </button>
            </form>
        </div>
        <div id="geo-status" class="geo-status-msg" style="display:none;"></div>

        <h2 class="geo-section-title">
            <i class="fas fa-store"></i> Boutiques
            <?php echo $rayon > 0 ? 'à moins de ' . $rayon . ' km' : 'du pays'; ?>
            (<?php echo count($boutiques); ?>)
        </h2>

        <?php if (empty($boutiques)): ?>
        <div class="geo-empty">
            <i class="fas fa-store-slash"></i>
            Aucune boutique géolocalisée dans ce rayon pour le moment.<br>
            Essayez d'élargir le rayon de recherche.
        </div>
        <?php else: ?>
        <div class="geo-boutiques-grid">
            <?php foreach ($boutiques as $b): ?>
            <a href="<?php echo htmlspecialchars(boutique_url('index.php', (string) $b['boutique_slug']), ENT_QUOTES, 'UTF-8'); ?>"
                class="geo-boutique-card">
                <?php if (!empty($b['boutique_logo'])): ?>
                <img class="geo-boutique-logo"
                    src="/upload/<?php echo htmlspecialchars(str_replace('\\', '/', (string) $b['boutique_logo']), ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars((string) $b['boutique_nom'], ENT_QUOTES, 'UTF-8'); ?>"
                    onerror="this.outerHTML='<span class=\'geo-boutique-logo\'><i class=\'fas fa-store\'></i></span>'">
                <?php else: ?>
                <span class="geo-boutique-logo"><i class="fas fa-store"></i></span>
                <?php endif; ?>
                <div class="geo-boutique-info">
                    <h3><?php echo htmlspecialchars((string) ($b['boutique_nom'] ?: 'Boutique'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="geo-boutique-distance">
                        <i class="fas fa-route"></i> <?php echo geo_format_distance((float) $b['distance_km']); ?>
                    </div>
                    <?php if (!empty($b['boutique_adresse'])): ?>
                    <div class="geo-boutique-adresse">
                        <?php echo htmlspecialchars(mb_substr((string) $b['boutique_adresse'], 0, 60), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2 class="geo-section-title">
            <i class="fas fa-box-open"></i> Produits près de vous (<?php echo count($produits); ?>)
        </h2>

        <?php if (empty($produits)): ?>
        <div class="geo-empty">
            <i class="fas fa-box-open"></i>
            Aucun produit de boutiques géolocalisées dans ce rayon.
        </div>
        <?php else: ?>
        <div class="geo-produits-grid">
            <?php foreach ($produits as $p): ?>
            <?php
                $promo = !empty($p['prix_promotion']) && (float) $p['prix_promotion'] > 0 && (float) $p['prix_promotion'] < (float) $p['prix'];
                $prix_aff = $promo ? (float) $p['prix_promotion'] : (float) $p['prix'];
            ?>
            <a href="<?php echo htmlspecialchars(boutique_url('produit.php?id=' . (int) $p['id'], (string) $p['boutique_slug']), ENT_QUOTES, 'UTF-8'); ?>"
                class="geo-produit-card">
                <img src="<?php echo htmlspecialchars(upload_image_url((string) ($p['image_principale'] ?? ''), 'md'), ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars((string) $p['nom'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="geo-produit-body">
                    <div class="geo-produit-boutique">
                        <i class="fas fa-store"></i>
                        <?php echo htmlspecialchars((string) ($p['boutique_nom'] ?: 'Boutique'), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo geo_format_distance((float) $p['distance_km']); ?>
                    </div>
                    <h3><?php echo htmlspecialchars((string) $p['nom'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="geo-produit-prix">
                        <?php echo number_format($prix_aff, 0, ',', ' '); ?> FCFA
                        <?php if ($promo): ?>
                        <span class="promo-old"><?php echo number_format((float) $p['prix'], 0, ',', ' '); ?> FCFA</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script src="/js/geo-location.js<?php echo asset_version_query(); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('geo-locate-form');
        if (!form || !window.GeoLocationCapture) return;
        window.GeoLocationCapture.init({
            latInput: 'geo_lat',
            lngInput: 'geo_lng',
            precisionInput: 'geo_precision',
            sourceInput: 'geo_source',
            statusEl: 'geo-status',
            button: 'btn-geo-locate',
            auto: false,
            onSuccess: function () {
                form.submit();
            }
        });
    });
    </script>
</body>

</html>
