<?php
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
$asset_version = isset($asset_version) ? $asset_version : get_asset_version();
// Compter les articles du panier si l'utilisateur est connecté
$panier_count = 0;
if (isset($_SESSION['user_id'])) {
    $conn_path = file_exists(__DIR__ . '/conn/conn.php') ? __DIR__ . '/conn/conn.php' : dirname(__DIR__) . '/conn/conn.php';
    if (file_exists($conn_path)) {
        require_once $conn_path;
    }
    $model_path = file_exists(__DIR__ . '/models/model_panier.php')
        ? __DIR__ . '/models/model_panier.php'
        : dirname(__DIR__) . '/models/model_panier.php';

    if (file_exists($model_path)) {
        require_once $model_path;
        $panier_count = count_panier_items($_SESSION['user_id']);
    }
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
    /* Nav style Planète Gâteau - fond dégradé, barre recherche, Mon compte, panier */
    .nav-planete-gateau {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 12px 30px;
        background: #ffffff;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        overflow: visible;
    }

    .section1 {
        z-index: 100;
    }

    .nav-planete-gateau .logo {
        flex-shrink: 0;
    }

    .nav-planete-gateau .logo img {
        height: 70px;
        width: auto;
        max-width: 160px;
        object-fit: contain;
    }

    .nav-top-row {
        display: contents;
    }

    .nav-top-row .logo {
        order: 1;
    }

    .nav-search-wrapper {
        order: 2;
    }

    .nav-top-row .nav-panier-link {
        order: 3;
    }

    .nav-top-row .nav-compte-btn {
        order: 4;
    }

    /* Barre de recherche */
    .nav-search-wrapper {
        display: flex;
        flex: 1;
        max-width: 500px;
        margin: 0 20px;
        position: relative;
        z-index: 9999;
        align-items: center;
        overflow: visible;
    }

    .nav-search-form {
        display: flex;
        align-items: stretch;
        flex: 1;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(229, 72, 138, 0.15);
    }

    /* GTranslate — sélecteur de langue (https://gtranslate.io) */
    .nav-gtranslate-wrapper {
        margin-left: 8px;
        flex-shrink: 0;
        position: relative;
        z-index: 10000;
        display: flex;
        align-items: center;
        align-self: center;
        height: auto;
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
        border-radius: 12px !important;
        border: 2px solid rgba(229, 72, 138, 0.3) !important;
        box-shadow: 0 2px 12px rgba(229, 72, 138, 0.12) !important;
        font-size: 14px !important;
        line-height: 1.3 !important;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        display: block !important;
        width: max-content;
        max-width: 100%;
    }

    .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang {
        padding: 8px 12px !important;
    }

    .nav-gtranslate-wrapper .gt_float_switcher img {
        width: 28px !important;
        max-height: 22px !important;
        object-fit: contain;
    }

    /* Menu langues : hors flux pour ne pas étirer le header (flex stretch) */
    .nav-gtranslate-wrapper .gt_float_switcher .gt_options {
        position: absolute !important;
        left: 0 !important;
        top: calc(100% + 4px) !important;
        right: auto !important;
        z-index: 10002 !important;
        min-width: 200px;
        max-height: min(70vh, 320px) !important;
        overflow-y: auto !important;
        background: #fff !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.18) !important;
        border: 1px solid rgba(229, 72, 138, 0.25) !important;
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

    .nav-search-btn {
        padding: 12px 20px;
        background: var(--couleur-dominante);
        border: none;
        color: #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s;
    }

    .nav-search-btn:hover {
        background: rgba(229, 72, 138, 0.9);
    }

    .nav-search-btn i {
        font-size: 18px;
    }

    .nav-search-input {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid rgba(229, 72, 138, 0.25);
        border-left: none;
        background: #ffffff;
        font-size: 15px;
        outline: none;
        border-radius: 0 25px 25px 0;
    }

    .nav-search-input::placeholder {
        color: #999;
    }

    .nav-search-input:focus {
        border-color: var(--couleur-dominante);
    }

    /* Bouton Mon compte */
    .nav-compte-btn {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 20px;
        background: var(--couleur-dominante);
        color: #ffffff;
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s;
        position: relative;
        min-width: 140px;
    }

    .nav-compte-btn:hover {
        background: rgba(229, 72, 138, 0.9);
        color: #ffffff;
        transform: translateY(-1px);
    }

    .nav-compte-title {
        font-size: 14px;
        font-weight: 700;
        display: block;
        line-height: 1.2;
    }

    .nav-compte-subtitle {
        font-size: 12px;
        opacity: 0.95;
        font-weight: 400;
    }

    .nav-compte-chevron {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        opacity: 0.9;
    }

    /* Panier */
    .nav-panier-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        color: var(--texte-fonce);
        text-decoration: none;
        transition: color 0.3s;
    }

    .nav-panier-link:hover {
        color: var(--couleur-dominante);
    }

    .nav-panier-link i {
        font-size: 26px;
    }

    .nav-panier-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: #20C5C7;
        color: #ffffff;
        border-radius: 50%;
        min-width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        border: 2px solid #ffffff;
        padding: 0 4px;
        box-shadow: 0 2px 6px rgba(32, 197, 199, 0.4);
    }

    @media (max-width: 992px) {
        .nav-planete-gateau {
            padding: 10px 20px;
            gap: 12px;
        }

        .nav-planete-gateau .logo img {
            height: 55px;
        }

        .nav-search-wrapper {
            max-width: 320px;
        }

        .nav-gtranslate-wrapper .gt_float_switcher .gt-selected .gt-current-lang {
            padding: 6px 10px !important;
        }

        .nav-search-input {
            font-size: 14px;
            padding: 10px 16px;
        }

        .nav-compte-btn {
            min-width: 130px;
            padding: 8px 14px;
        }

        .nav-compte-title {
            font-size: 12px;
        }

        .nav-compte-subtitle {
            font-size: 11px;
        }
    }

    @media (max-width: 992px) {
        .nav-top-row .nav-panier-link,
        .nav-top-row .nav-compte-btn {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .nav-planete-gateau {
            flex-wrap: wrap;
            padding: 10px 12px;
            gap: 10px;
        }

        .nav-top-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            order: 1;
            flex-shrink: 0;
        }

        .nav-planete-gateau .logo {
            flex-shrink: 0;
        }

        .nav-planete-gateau .logo img {
            height: 45px;
            max-width: 120px;
        }

        .nav-panier-link {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
        }

        .nav-panier-link i {
            font-size: 22px;
        }

        .nav-panier-badge {
            min-width: 18px;
            height: 18px;
            font-size: 10px;
        }

        .nav-compte-btn {
            min-width: auto;
            padding: 8px 12px;
            flex-direction: row;
            gap: 6px;
            flex-shrink: 0;
        }

        .nav-compte-title {
            display: none;
        }

        .nav-compte-subtitle {
            font-size: 12px;
            font-weight: 600;
        }

        .nav-compte-chevron {
            display: none;
        }

        .nav-search-wrapper {
            order: 2;
            width: 100%;
            max-width: 100%;
            margin: 0;
            flex-direction: row;
        }

        .nav-search-form {
            flex: 1;
        }

        .nav-search-btn {
            padding: 10px 14px;
        }

        .nav-search-input {
            padding: 10px 14px;
            font-size: 14px;
        }

        .nav-gtranslate-wrapper {
            margin-left: 6px;
        }
    }

    @media (max-width: 480px) {
        .nav-planete-gateau {
            padding: 8px 10px;
            gap: 8px;
        }

        .nav-planete-gateau .logo img {
            height: 40px;
            max-width: 100px;
        }

        .nav-compte-btn {
            padding: 6px 10px;
        }

        .nav-compte-subtitle {
            font-size: 11px;
        }

        .nav-panier-link {
            width: 38px;
            height: 38px;
        }

        .nav-panier-link i {
            font-size: 20px;
        }

        .nav-search-btn {
            padding: 8px 12px;
        }

        .nav-search-input {
            padding: 8px 12px;
            font-size: 13px;
        }
    }
</style>

<div class="info">

</div>
<nav class="nav-planete-gateau">
    <div class="nav-top-row">
        <a class="logo" href="/index.php">
            <img src="/image/sugar_paper.jpg" alt="Sugar Paper">
        </a>
        <a href="<?php echo isset($_SESSION['user_id']) ? '/panier.php' : '/user/connexion.php?redirect=panier'; ?>"
            class="nav-panier-link"
            title="<?php echo isset($_SESSION['user_id']) ? 'Voir mon panier (' . $panier_count . ' article' . ($panier_count > 1 ? 's' : '') . ')' : 'Se connecter pour voir le panier'; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <?php if (isset($_SESSION['user_id']) && $panier_count > 0): ?>
                <span class="nav-panier-badge"><?php echo $panier_count > 99 ? '99+' : $panier_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php
        if (isset($_SESSION['commercant_id']))
            echo '/view/profil_commercent.php';
        elseif (isset($_SESSION['user_id']))
            echo '/user/mon-compte.php';
        else
            echo '/user/connexion.php';
        ?>" class="nav-compte-btn">
            <span class="nav-compte-title">Mon compte</span>
            <span class="nav-compte-subtitle"><?php
            if (isset($_SESSION['commercant_id']) && isset($commercant) && !empty($commercant['nom'])) {
                $explode_nom = explode(' ', $commercant['nom']);
                echo htmlspecialchars($explode_nom[0] ?? $commercant['nom']);
            } elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_prenom'])) {
                echo htmlspecialchars($_SESSION['user_prenom']);
            } else {
                echo 'Identifiez-vous';
            }
            ?></span>
            <i class="fa-solid fa-chevron-down nav-compte-chevron"></i>
        </a>
    </div>

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
        <?php
        $gtranslate_path = __DIR__ . '/includes/gtranslate.php';
        if (is_file($gtranslate_path)) {
            include $gtranslate_path;
        }
        ?>
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