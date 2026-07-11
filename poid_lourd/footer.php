<?php
if (!defined('SITE_BRAND_NAME')) {
    require_once __DIR__ . '/includes/site_brand.php';
}
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
if (!function_exists('boutique_url')) {
    require_once __DIR__ . '/includes/marketplace_helpers.php';
}
if (!function_exists('boutique_adresse_publique')) {
    require_once __DIR__ . '/includes/boutique_vendeur_display.php';
}
$__footer_vd = isset($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']) && is_array($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'])
    ? $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']
    : null;
$__footer_slug = defined('BOUTIQUE_SLUG') ? (string) BOUTIQUE_SLUG : '';
$__footer_is_boutique = ($__footer_slug !== '' && $__footer_vd !== null);
$__footer_nom = $__footer_is_boutique
    ? (trim((string) ($__footer_vd['boutique_nom'] ?? '')) !== '' ? trim((string) $__footer_vd['boutique_nom']) : (defined('BOUTIQUE_NOM') ? BOUTIQUE_NOM : 'Boutique'))
    : '';
$__footer_logo_src = '/image/logo_market.png';
$__footer_logo_alt = SITE_BRAND_NAME;
if ($__footer_is_boutique && !empty($__footer_vd['boutique_logo'])) {
    $__footer_logo_src = '/upload/' . str_replace('\\', '/', $__footer_vd['boutique_logo']);
    $__footer_logo_alt = $__footer_nom;
}
$__footer_home = $__footer_is_boutique ? boutique_url('index.php', $__footer_slug) : '/index.php';
$__footer_panier = $__footer_is_boutique ? boutique_url('panier.php', $__footer_slug) : '/panier.php';
$__footer_produits = $__footer_is_boutique ? boutique_url('produits.php', $__footer_slug) : '/produits.php';
$__footer_contact = $__footer_is_boutique ? boutique_url('contact.php', $__footer_slug) : '/contact.php';
$__footer_mail = '';
$__footer_addr = '';
if ($__footer_is_boutique) {
    $__footer_mail = trim((string) ($__footer_vd['email'] ?? ''));
    $__footer_addr = boutique_adresse_publique($__footer_vd);
    if ($__footer_mail === '') {
        $__footer_mail = 'contact@colobanes.com';
    }
    if ($__footer_addr === '') {
        $__footer_addr = 'Adresse non renseignée — renseignez-la dans Paramètres (admin).';
    }
} else {
    $__footer_mail = 'contact@colobanes.com';
    $__footer_addr = 'Rond Point Colobane, Dakar, Sénégal';
}

$__footer_social_cfg = file_exists(__DIR__ . '/config/social.php') ? require __DIR__ . '/config/social.php' : [];
$__footer_social_cfg = is_array($__footer_social_cfg) ? $__footer_social_cfg : [];
$__ig_t = trim((string) ($__footer_social_cfg['instagram'] ?? ''));
$__fb_t = trim((string) ($__footer_social_cfg['facebook'] ?? ''));
$__li_t = trim((string) ($__footer_social_cfg['linkedin'] ?? ''));
$__tt_t = trim((string) ($__footer_social_cfg['tiktok'] ?? ''));
$__footer_show_social = $__ig_t !== '' || $__fb_t !== '' || $__li_t !== '' || $__tt_t !== '';
?>
<?php if ($__footer_show_social): ?>
<link rel="stylesheet" href="/css/site-social-links.css<?php echo asset_version_query(); ?>">
<?php endif; ?>
<footer class="footer">
    <div class="container footer_container">
        <div class="footer_item">
            <a href="<?php echo htmlspecialchars($__footer_home, ENT_QUOTES, 'UTF-8'); ?>" class="footer_logo">
                <img src="<?php echo htmlspecialchars($__footer_logo_src, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($__footer_logo_alt, ENT_QUOTES, 'UTF-8'); ?>" class="footer_logo_img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                <span class="footer_logo_fallback"><i class="fas fa-store"></i> <?php echo htmlspecialchars($__footer_is_boutique ? $__footer_nom : SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <div class="footer_p">
                <?php echo $__footer_is_boutique
                    ? htmlspecialchars($__footer_nom . ' — boutique partenaire', ENT_QUOTES, 'UTF-8')
                    : (SITE_BRAND_NAME . ' — marketplace Sénégal'); ?>
            </div>
        </div>
        <div class="footer_item">
            <h3 class="footer_item_titl"><?php echo $__footer_is_boutique ? 'La boutique' : 'Contact'; ?></h3>
            <ul class="footer_list">
                <li class="li footer_list_item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo nl2br(htmlspecialchars($__footer_addr, ENT_QUOTES, 'UTF-8')); ?></span>
                </li>
                <li class="li footer_list_item">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars($__footer_mail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__footer_mail, ENT_QUOTES, 'UTF-8'); ?></a>
                </li>
            </ul>
        </div>
        <div class="footer_item">
            <h3 class="footer_item_titl">Liens rapides</h3>
            <ul class="footer_list">
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])): ?>
                    <li class="li footer_list_item">
                        <a href="/user/mon-compte.php">Mon compte</a>
                    </li>
                    <li class="li footer_list_item">
                        <a href="/user/deconnexion.php">Déconnexion</a>
                    </li>
                <?php else: ?>
                    <li class="li footer_list_item">
                        <a href="/choix-connexion.php">Connexion</a>
                    </li>
                    <li class="li footer_list_item">
                        <a href="/user/inscription.php">Inscription</a>
                    </li>
                <?php endif; ?>
                <li class="li footer_list_item">
                    <a href="<?php echo htmlspecialchars($__footer_panier, ENT_QUOTES, 'UTF-8'); ?>">Panier</a>
                </li>
                <li class="li footer_list_item">
                    <a href="<?php echo htmlspecialchars($__footer_produits, ENT_QUOTES, 'UTF-8'); ?>">Produits</a>
                </li>
                <li class="li footer_list_item">
                    <a href="<?php echo htmlspecialchars($__footer_contact, ENT_QUOTES, 'UTF-8'); ?>">Contact</a>
                </li>
            </ul>
        </div>
        <?php if (!$__footer_is_boutique): ?>
        <div class="footer_item">
            <h3 class="footer_item_titl">Informations légales</h3>
            <ul class="footer_list">
                <li class="li footer_list_item">
                    <a href="/politique-confidentialite.php">Politique de confidentialité</a>
                </li>
                <li class="li footer_list_item">
                    <a href="/politique-suppression-compte.php">Suppression de compte</a>
                </li>
                <li class="li footer_list_item">
                    <a href="/conditions-utilisation.php">Conditions d'utilisation</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($__footer_show_social && !$__footer_is_boutique): ?>
    <div class="footer_social_wrap">
        <div class="container footer_social_inner">
            <p class="footer_social_title">Suivez-nous</p>
            <?php
            $social_config = $__footer_social_cfg;
            include __DIR__ . '/includes/social_links_block.php';
            ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="footer_bottom">
        <div class="container footer_bottom_container">
            <p class="footer_copy">
                <?php echo $__footer_is_boutique
                    ? '2026 ' . htmlspecialchars($__footer_nom, ENT_QUOTES, 'UTF-8') . ' | Tous droits réservés'
                    : '2026 COLObanes | Tous droits réservés'; ?>
            </p>
        </div>
    </div>
</footer>
<?php include __DIR__ . '/includes/social_floating.php'; ?>
<?php
$__footer_script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$__footer_load_share = $__footer_script !== ''
    && (
        in_array(basename($__footer_script), ['index.php', 'produits.php', 'nouveautes.php', 'promo.php', 'categorie.php', 'produit.php'], true)
        || strpos($__footer_script, '/boutique/') !== false
    );
if ($__footer_load_share):
?>
<link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
<?php include __DIR__ . '/includes/partials/platform_share_modal.php'; ?>
<script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/perf_lazy_assets.php'; ?>
<?php require_once __DIR__ . '/includes/flash_toast.php'; flash_toast_render(); ?>
