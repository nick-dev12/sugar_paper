<?php
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
require_once __DIR__ . '/includes/store_nav_account.php';
$store_nav_account = store_nav_account_info();
$asset_version = isset($asset_version) ? $asset_version : get_asset_version();
$panier_count = 0;
$panier_invite_path = file_exists(__DIR__ . '/includes/panier_invite.php')
    ? __DIR__ . '/includes/panier_invite.php'
    : dirname(__DIR__) . '/includes/panier_invite.php';
if (file_exists($panier_invite_path)) {
    require_once $panier_invite_path;
}
if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    $conn_path = file_exists(__DIR__ . '/conn/conn.php') ? __DIR__ . '/conn/conn.php' : dirname(__DIR__) . '/conn/conn.php';
    if (file_exists($conn_path)) {
        require_once $conn_path;
    }
    $model_path = file_exists(__DIR__ . '/models/model_panier.php')
        ? __DIR__ . '/models/model_panier.php'
        : dirname(__DIR__) . '/models/model_panier.php';

    if (file_exists($model_path)) {
        require_once $model_path;
        $panier_count = count_panier_items((int) $_SESSION['user_id']);
    }
} elseif (function_exists('panier_invite_count_items')) {
    $panier_count = panier_invite_count_items();
}
?>
<link rel="stylesheet" href="/css/variables.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="/css/nabare.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php include __DIR__ . '/includes/google_fonts.php'; ?>
<style>
    /* ── Navigation Sugar Paper ───────────────────────────────────── */
    nav.nav-planete-gateau {
        background: #ffffff;
        border-bottom: 1px solid rgba(229, 72, 138, 0.12);
        box-shadow: 0 2px 16px rgba(229, 72, 138, 0.06);
        overflow: visible !important;
        min-height: 0 !important;
        height: auto !important;
        padding: 8px clamp(12px, 2.5vw, 28px);
        box-sizing: border-box;
    }

    .section1 {
        z-index: 100;
    }

    /* Logo */
    .nav-planete-gateau .logo {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        flex-shrink: 0;
        text-decoration: none;
        line-height: 0;
        justify-self: start;
    }

    .nav-planete-gateau .logo img {
        display: block;
        height: auto;
        width: auto;
        max-height: 56px;
        max-width: clamp(90px, 14vw, 150px);
        object-fit: contain !important;
        object-position: left center;
    }

    /* Barre de recherche */
    .nav-search-wrapper {
        min-width: 0;
        position: relative;
        z-index: 9999;
    }

    .nav-search-form {
        display: flex;
        align-items: stretch;
        width: 100%;
        border-radius: 999px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(229, 72, 138, 0.12);
    }

    .nav-search-btn {
        flex-shrink: 0;
        padding: 0 16px;
        min-height: 40px;
        background: var(--couleur-dominante);
        border: none;
        color: #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.25s;
    }

    .nav-search-btn:hover {
        background: rgba(229, 72, 138, 0.9);
    }

    .nav-search-btn i {
        font-size: 16px;
    }

    .nav-search-input {
        flex: 1;
        min-width: 0;
        padding: 0 16px;
        min-height: 40px;
        border: 2px solid rgba(229, 72, 138, 0.22);
        border-left: none;
        background: #ffffff;
        font-size: 14px;
        outline: none;
        border-radius: 0 999px 999px 0;
    }

    .nav-search-input::placeholder {
        color: #999;
    }

    .nav-search-input:focus {
        border-color: var(--couleur-dominante);
    }

    /* Groupe actions : langue · panier · compte */
    .nav-actions {
        display: flex;
        align-items: center;
        gap: 2px;
        flex-shrink: 0;
    }

    .nav-gtranslate-wrapper {
        flex-shrink: 0;
        position: relative;
        z-index: 10000;
        display: flex;
        align-items: center;
        margin: 0;
    }

    .nav-gtranslate-wrapper #gt_float_wrapper {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
        z-index: 10000 !important;
        height: auto !important;
        display: block !important;
    }

    .nav-gtranslate-wrapper .gt_float_switcher {
        position: relative !important;
        border-radius: 10px !important;
        border: 1.5px solid rgba(229, 72, 138, 0.28) !important;
        box-shadow: 0 1px 8px rgba(229, 72, 138, 0.1) !important;
        font-size: 13px !important;
        line-height: 1.2 !important;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        display: block !important;
        width: max-content;
        max-width: 100%;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang {
        padding: 6px 10px !important;
    }

    .nav-gtranslate-wrapper .gt_float_switcher img {
        width: 24px !important;
        max-height: 18px !important;
        object-fit: contain;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt_options {
        position: absolute !important;
        left: 0 !important;
        top: calc(100% + 4px) !important;
        right: auto !important;
        z-index: 10002 !important;
        min-width: 180px;
        max-height: min(70vh, 300px) !important;
        overflow-y: auto !important;
        background: #fff !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
        border: 1px solid rgba(229, 72, 138, 0.2) !important;
        margin: 0 !important;
        transform: none !important;
        float: none !important;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt_options.gt-open {
        transform: none !important;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt_options a {
        color: #333 !important;
        white-space: nowrap;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt_options a:hover {
        color: #fff !important;
    }

    /* Panier */
    .nav-panier-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        color: var(--texte-fonce);
        text-decoration: none;
        transition: color 0.25s;
        flex-shrink: 0;
    }

    .nav-panier-link:hover {
        color: var(--couleur-dominante);
    }

    .nav-panier-link i {
        font-size: 22px;
    }

    .nav-panier-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #20C5C7;
        color: #ffffff;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 700;
        border: 2px solid #ffffff;
        padding: 0 3px;
        box-shadow: 0 1px 4px rgba(32, 197, 199, 0.35);
    }

    /* Mon compte */
    .nav-compte-btn {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        padding: 7px 32px 7px 14px;
        background: var(--couleur-dominante);
        color: #ffffff;
        text-decoration: none;
        border-radius: 999px;
        transition: background 0.25s, transform 0.2s;
        position: relative;
        min-width: 128px;
        max-width: 180px;
        flex-shrink: 0;
        line-height: 1.2;
    }

    .nav-compte-btn:hover {
        background: rgba(229, 72, 138, 0.92);
        color: #ffffff;
        transform: translateY(-1px);
    }

    .nav-compte-title {
        font-size: 13px;
        font-weight: 700;
        display: block;
    }

    .nav-compte-subtitle {
        font-size: 11px;
        opacity: 0.92;
        font-weight: 400;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }

    .nav-compte-chevron {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 10px;
        opacity: 0.85;
    }

    /* ── Desktop & tablette large (≥ 800 px) : une ligne ─────────── */
    @media (min-width: 800px) {
        nav.nav-planete-gateau {
            display: grid;
            grid-template-columns: auto minmax(160px, 1fr) auto;
            grid-template-rows: 1fr;
            align-items: center;
            column-gap: clamp(10px, 1.5vw, 20px);
            max-height: 80px;
        }

        .nav-planete-gateau .logo {
            grid-column: 1;
            grid-row: 1;
        }

        .nav-search-wrapper {
            grid-column: 2;
            grid-row: 1;
            width: 100%;
            max-width: 480px;
            justify-self: center;
        }

        .nav-actions {
            grid-column: 3;
            grid-row: 1;
            justify-self: end;
            gap: 4px;
        }
    }

    /* Panier + compte dans la barre du bas sur mobile/tablette */
    @media (max-width: 992px) {

        .nav-actions .nav-panier-link,
        .nav-actions .nav-compte-btn {
            display: none !important;
        }
    }

    @media (min-width: 993px) {
        .nav-actions .nav-panier-link {
            display: flex !important;
        }

        .nav-actions .nav-compte-btn {
            display: flex !important;
        }
    }

    @media (min-width: 800px) and (max-width: 992px) {
        nav.nav-planete-gateau {
            max-height: 76px;
        }

        .nav-planete-gateau .logo {
            justify-self: start;
        }

        .nav-planete-gateau .logo img {
            max-height: 54px;
            max-width: min(150px, 22vw);
        }

        .nav-search-wrapper {
            max-width: 380px;
        }

        .nav-search-btn,
        .nav-search-input {
            min-height: 38px;
        }
    }

    /* Langue compacte + logo ancré à gauche (tablette & mobile) */
    @media (max-width: 992px) {
        .nav-planete-gateau .logo {
            justify-self: start;
            justify-content: flex-start;
            margin-right: auto;
        }

        .nav-gtranslate-wrapper .gt_float_switcher {
            font-size: 11px !important;
            border-radius: 8px !important;
            border-width: 1px !important;
            box-shadow: 0 1px 4px rgba(229, 72, 138, 0.08) !important;
        }

        .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang {
            padding: 3px 6px !important;
            font-size: 11px !important;
            line-height: 1.1 !important;
            min-height: 0 !important;
            gap: 4px !important;
        }

        .nav-gtranslate-wrapper .gt_float_switcher img {
            width: 18px !important;
            max-height: 13px !important;
        }

        .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang span {
            font-size: 11px !important;
        }
    }

    /* ── Mobile & petite tablette (< 800 px) : 2 lignes, max 100 px ─ */
    @media (max-width: 799px) {
        nav.nav-planete-gateau {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            grid-template-rows: auto auto;
            align-items: center;
            gap: 5px 8px;
            padding: 6px 10px;
            max-height: 100px;
        }

        .nav-planete-gateau .logo {
            grid-column: 1;
            grid-row: 1;
            justify-self: start;
            align-self: center;
            min-width: 0;
            margin-right: 0;
        }

        .nav-planete-gateau .logo img {
            max-height: 52px;
            max-width: min(150px, 48vw);
            object-position: left center;
        }

        .nav-actions {
            grid-column: 2;
            grid-row: 1;
            justify-self: end;
            align-self: center;
        }

        .nav-search-wrapper {
            grid-column: 1 / -1;
            grid-row: 2;
            width: 100%;
            max-width: none;
        }

        .nav-search-btn {
            padding: 0 12px;
            min-height: 34px;
        }

        .nav-search-input {
            padding: 0 12px;
            min-height: 34px;
            font-size: 13px;
        }
    }

    @media (max-width: 480px) {
        nav.nav-planete-gateau {
            padding: 5px 8px;
            gap: 4px 6px;
        }

        .nav-planete-gateau .logo img {
            max-height: 48px;
            max-width: min(140px, 46vw);
        }

        .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang {
            padding: 2px 5px !important;
            font-size: 10px !important;
        }

        .nav-gtranslate-wrapper .gt_float_switcher img {
            width: 16px !important;
            max-height: 12px !important;
        }

        .nav-search-btn {
            padding: 0 10px;
            min-height: 32px;
        }

        .nav-search-input {
            padding: 0 10px;
            min-height: 32px;
            font-size: 12px;
        }
    }
</style>

<div class="info">

</div>
<nav class="nav-planete-gateau">
    <a class="logo" href="/index.php">
        <img src="/image/sugar_paper.jpg" alt="Sugar Paper">
    </a>

    <div class="nav-search-wrapper">
        <form class="nav-search-form" action="/produits.php" method="get" id="nav-search-form">
            <button type="submit" class="nav-search-btn" aria-label="Rechercher">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="recherche" id="nav-search" class="nav-search-input"
                placeholder="Que recherchez-vous ?"
                value="<?php echo !empty($_GET['recherche']) ? htmlspecialchars($_GET['recherche']) : ''; ?>">
            <input type="hidden" name="prix_min" id="nav-prix-min"
                value="<?php echo isset($_GET['prix_min']) ? htmlspecialchars($_GET['prix_min']) : ''; ?>">
            <input type="hidden" name="prix_max" id="nav-prix-max"
                value="<?php echo isset($_GET['prix_max']) ? htmlspecialchars($_GET['prix_max']) : ''; ?>">
            <input type="hidden" name="categorie" id="nav-categorie"
                value="<?php echo isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie']) : ''; ?>">
            <input type="hidden" name="tri" id="nav-tri"
                value="<?php echo isset($_GET['tri']) ? htmlspecialchars($_GET['tri']) : ''; ?>">
        </form>
    </div>

    <div class="nav-actions">
        <?php
        $gtranslate_path = __DIR__ . '/includes/gtranslate.php';
        if (is_file($gtranslate_path)) {
            include $gtranslate_path;
        }
        ?>
        <a href="/index.php?open=panier" class="nav-panier-link js-open-cart-modal"
            title="<?php echo 'Voir mon panier (' . $panier_count . ' article' . ($panier_count > 1 ? 's' : '') . ')'; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <?php if ($panier_count > 0): ?>
                <span class="nav-panier-badge"><?php echo $panier_count > 99 ? '99+' : $panier_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo htmlspecialchars($store_nav_account['url']); ?>"
            class="<?php echo htmlspecialchars($store_nav_account['btn_class']); ?>">
            <span class="nav-compte-title"><?php echo htmlspecialchars($store_nav_account['title']); ?></span>
            <span class="nav-compte-subtitle"><?php echo htmlspecialchars($store_nav_account['subtitle']); ?></span>
            <i class="fa-solid fa-chevron-down nav-compte-chevron"></i>
        </a>
    </div>
</nav>

<?php
$categories_menu = [];
if (file_exists(__DIR__ . '/models/model_categories.php')) {
    require_once __DIR__ . '/models/model_categories.php';
    $categories_menu = get_all_categories();
}
?>

<!-- Overlay et sidebar menu latéral (apparaît au clic sur MENU) -->
<div class="nav-sidebar-overlay" id="navSidebarOverlay"></div>
<aside class="nav-sidebar" id="navSidebar">
    <div class="nav-sidebar-header">
        <a href="/index.php" class="nav-sidebar-logo">
            <img src="/image/sugar_paper.jpg" alt="Sugar Paper">
        </a>
        <p class="nav-sidebar-slogan">SUGAR PAPER</p>
    </div>
    <div class="nav-sidebar-content">
        <a href="/nouveautes.php" class="nav-sidebar-item nav-sidebar-nouveautes">
            <i class="fa-solid fa-cake-candles"></i>
            <span>NOUVEAUTÉS</span>
        </a>
        <a href="/promo.php" class="nav-sidebar-item nav-sidebar-promo">
            <i class="fa-solid fa-percent"></i>
            <span>PROMO</span>
        </a>
        <div class="nav-sidebar-categories">
            <?php if (!empty($categories_menu)): ?>
                <?php foreach ($categories_menu as $categorie): ?>
                    <a href="categorie.php?id=<?php echo $categorie['id']; ?>" class="nav-sidebar-category">
                        <span><?php echo htmlspecialchars($categorie['nom']); ?></span>
                        <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="produits.php" class="nav-sidebar-category">
                    <span>Tous les produits</span>
                    <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="nav-sidebar-footer">
        <a href="/contact.php" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-phone"></i>
            <span>CONTACTEZ<br>NOUS</span>
        </a>
        <a href="/contact.php#livraison" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-truck"></i>
            <span>PORTS ET<br>EXPÉDITION</span>
        </a>
        <a href="<?php echo isset($_SESSION['user_id']) ? '/user/mon-compte.php' : '/user/connexion.php'; ?>"
            class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-briefcase"></i>
            <span>COMPTE<br>PRO</span>
        </a>
    </div>
</aside>

<section class="section1">
    <div class="section1-left">
        <button type="button" class="toggle-categories-btn" id="navMenuToggle" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars"></i>
            <span>MENU</span>
        </button>
    </div>
    <div class="section1-right">
        <a href="/nouveautes.php" class="nav-action-btn nav-btn-nouveautes">
            <i class="fa-solid fa-gift"></i>
            <span>NOUVEAUTÉS</span>
        </a>
        <a href="/promo.php" class="nav-action-btn nav-btn-promo">
            <i class="fa-solid fa-percent"></i>
            <span>PROMO</span>
        </a>
        <a href="/contact.php" class="nav-action-btn nav-btn-contact">
            <i class="fa-solid fa-phone"></i>
            <span>CONTACT</span>
        </a>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('navMenuToggle');
        var sidebar = document.getElementById('navSidebar');
        var overlay = document.getElementById('navSidebarOverlay');

        function openMenu() {
            if (sidebar) sidebar.classList.add('open');
            if (overlay) overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
            var icon = toggle ? toggle.querySelector('i') : null;
            if (icon) { icon.classList.remove('fa-bars'); icon.classList.add('fa-times'); }
        }
        function closeMenu() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            document.body.style.overflow = '';
            var icon = toggle ? toggle.querySelector('i') : null;
            if (icon) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); }
        }

        if (toggle) toggle.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('open')) closeMenu();
            else openMenu();
        });
        if (overlay) overlay.addEventListener('click', closeMenu);

        window.addEventListener('resize', function () {
            if (window.innerWidth > 992 && sidebar && sidebar.classList.contains('open')) closeMenu();
        });
    });
</script>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>