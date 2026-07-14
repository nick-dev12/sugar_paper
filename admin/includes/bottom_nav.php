<?php

/**

 * Barre de navigation inférieure admin (mobile & tablette)

 * Programmation procédurale uniquement

 */



if (!function_exists('get_asset_version')) {

    require_once __DIR__ . '/../../includes/asset_version.php';

}



if (!function_exists('get_public_root_uri_path')) {

    require_once __DIR__ . '/../../includes/site_url.php';

}



if (!function_exists('admin_current_role')) {

    require_once __DIR__ . '/../../includes/admin_permissions.php';

}



if (!isset($admin_nav_base)) {

    $admin_nav_base = rtrim(get_public_root_uri_path(), '/') . '/admin/';

}



if (!isset($nav_href)) {

    $nav_href = function ($path) use ($admin_nav_base) {

        return $admin_nav_base . ltrim($path, '/');

    };

}



$current_page = basename($_SERVER['PHP_SELF'] ?? '');

$current_dir = dirname($_SERVER['PHP_SELF'] ?? '');



$admin_bottom_role = admin_current_role();

$is_contable_bottom = ($admin_bottom_role === 'contable');

$is_livreur_bottom = ($admin_bottom_role === 'livreur');

$is_utilisateur_bottom = ($admin_bottom_role === 'utilisateur');

$is_zones_livraison = strpos($current_dir, '/zones-livraison') !== false;

$is_livreurs = strpos($current_dir, '/livreurs') !== false;
$is_livreurs_carte = $is_livreurs && ($current_page === 'carte.php');

$is_produits = strpos($current_dir, '/produits') !== false;

$is_categories = strpos($current_dir, '/categories') !== false;

$is_stock = strpos($current_dir, '/stock') !== false;

$is_commandes = strpos($current_dir, '/commandes') !== false;

$is_commandes_perso = strpos($current_dir, '/commandes-personnalisees') !== false;

$is_commandes_std = $is_commandes && !$is_commandes_perso;

$is_devis = strpos($current_dir, '/devis') !== false;

$is_invoice = strpos($current_dir, '/invoice') !== false;

$is_comptes = strpos($current_dir, '/comptes') !== false;

$is_parametres = ($current_page === 'parametres.php' || strpos($current_dir, '/parametres') !== false);



$tab_param = isset($_GET['tab']) ? (string) $_GET['tab'] : '';



$admin_bottom_active = '';

if ($is_contable_bottom) {

    if ($is_comptes) {

        $admin_bottom_active = 'comptes';

    } elseif ($is_parametres) {

        $admin_bottom_active = 'parametres';

    } elseif ($current_page === 'profil.php') {

        $admin_bottom_active = 'profil';

    }

} elseif ($is_livreur_bottom) {

    if ($is_livreurs) {

        $admin_bottom_active = 'livreurs';

    } elseif ($is_zones_livraison) {

        $admin_bottom_active = 'zones';

    } elseif ($current_page === 'profil.php') {

        $admin_bottom_active = 'profil';

    }

} elseif ($is_utilisateur_bottom) {

    if ($is_livreurs_carte) {

        $admin_bottom_active = 'map';

    } elseif ($is_invoice) {

        $admin_bottom_active = 'invoice';

    } elseif ($is_produits || $is_categories || $is_stock) {

        $admin_bottom_active = 'produits';

    } elseif ($is_commandes_std || $is_commandes_perso) {

        $admin_bottom_active = 'commandes';

    }

} elseif ($is_livreurs_carte) {

    $admin_bottom_active = 'map';

} elseif ($is_produits || $is_categories || $is_stock) {

    $admin_bottom_active = 'produits';

} elseif ($is_commandes || $is_commandes_perso) {

    $admin_bottom_active = 'commandes';

} elseif ($is_invoice) {

    $admin_bottom_active = 'invoice';

} elseif ($is_comptes) {

    $admin_bottom_active = 'comptes';

}

$is_invoice_hub = !empty($admin_invoice_hub_bottom_nav) && $is_invoice && $current_page === 'index.php';
$invoice_hub_tab = isset($admin_invoice_hub_active_tab) ? (string) $admin_invoice_hub_active_tab : 'facture';

?>

<link rel="stylesheet" href="/css/bottom-nav.css<?php echo asset_version_query(); ?>">

<nav class="bottom-nav bottom-nav--admin<?php echo $is_contable_bottom ? ' bottom-nav--contable' : ''; ?><?php echo $is_livreur_bottom ? ' bottom-nav--livreur' : ''; ?><?php echo $is_utilisateur_bottom ? ' bottom-nav--utilisateur' : ''; ?>" id="adminBottomNav" aria-label="Navigation administration">

    <?php if ($is_livreur_bottom): ?>

    <a href="<?php echo htmlspecialchars($nav_href('livreurs/index.php')); ?>"
        class="bottom-nav-item bottom-nav-item--livreurs<?php echo $admin_bottom_active === 'livreurs' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Livreurs GPS</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('zones-livraison/index.php')); ?>"
        class="bottom-nav-item bottom-nav-item--zones<?php echo $admin_bottom_active === 'zones' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-truck" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Zones</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('profil.php')); ?>"
        class="bottom-nav-item bottom-nav-item--profil<?php echo $admin_bottom_active === 'profil' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-user" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Profil</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('logout.php')); ?>"
        class="bottom-nav-item bottom-nav-item--logout"
        aria-label="Déconnexion">
        <span class="bottom-nav-icon"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Déconnexion</span>
    </a>

    <?php elseif ($is_invoice_hub): ?>

    <?php if (admin_can_bl_retours_b2b()): ?>
    <button type="button"
        class="bottom-nav-item bottom-nav-item--facture<?php echo $invoice_hub_tab === 'facture' ? ' is-active' : ''; ?>"
        data-invoice-tab="facture"
        aria-label="Onglet Facture">
        <span class="bottom-nav-icon"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Facture</span>
    </button>
    <?php endif; ?>

    <?php if (admin_can_devis()): ?>
    <button type="button"
        class="bottom-nav-item bottom-nav-item--devis<?php echo $invoice_hub_tab === 'devis' ? ' is-active' : ''; ?>"
        data-invoice-tab="devis"
        aria-label="Onglet Devis">
        <span class="bottom-nav-icon"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Devis</span>
    </button>
    <?php endif; ?>

    <button type="button"
        class="bottom-nav-item bottom-nav-item--contacts<?php echo $invoice_hub_tab === 'contacts' ? ' is-active' : ''; ?>"
        data-invoice-tab="contacts"
        aria-label="Onglet Clients">
        <span class="bottom-nav-icon"><i class="fas fa-address-book" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Clients</span>
    </button>

    <button type="button"
        class="bottom-nav-item bottom-nav-item--rapports<?php echo $invoice_hub_tab === 'rapports' ? ' is-active' : ''; ?>"
        data-invoice-tab="rapports"
        aria-label="Onglet Rapports">
        <span class="bottom-nav-icon"><i class="fas fa-chart-bar" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Rapports</span>
    </button>

    <button type="button" class="bottom-nav-item bottom-nav-item--menu" id="adminBottomNavMenuBtn"
        aria-label="Ouvrir le menu admin">
        <span class="bottom-nav-icon"><i class="fas fa-th" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Menu</span>
    </button>

    <?php elseif ($is_utilisateur_bottom): ?>

    <a href="<?php echo htmlspecialchars($nav_href('invoice/index.php')); ?>"
        class="bottom-nav-item bottom-nav-item--invoice<?php echo $admin_bottom_active === 'invoice' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Invoice</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('produits/index.php')); ?>"
        class="bottom-nav-item bottom-nav-item--produits<?php echo $admin_bottom_active === 'produits' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-box" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Produits</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('commandes/index.php')); ?>"
        class="bottom-nav-item bottom-nav-item--commandes<?php echo $admin_bottom_active === 'commandes' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-shopping-cart" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Commandes</span>
    </a>

    <a href="<?php echo htmlspecialchars($nav_href('livreurs/carte.php')); ?>"
        class="bottom-nav-item bottom-nav-item--map<?php echo $admin_bottom_active === 'map' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-map-location-dot" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Map</span>
    </a>

    <button type="button" class="bottom-nav-item bottom-nav-item--menu" id="adminBottomNavMenuBtn"
        aria-label="Ouvrir le menu admin">
        <span class="bottom-nav-icon"><i class="fas fa-th" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Menu</span>
    </button>

    <?php elseif ($is_contable_bottom): ?>

    <a href="<?php echo htmlspecialchars($nav_href('comptes/index.php')); ?>"

        class="bottom-nav-item bottom-nav-item--comptes<?php echo $admin_bottom_active === 'comptes' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Comptes</span>

    </a>

    <a href="<?php echo htmlspecialchars($nav_href('parametres.php')); ?>"

        class="bottom-nav-item bottom-nav-item--parametres<?php echo $admin_bottom_active === 'parametres' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-cog" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Paramètres</span>

    </a>

    <a href="<?php echo htmlspecialchars($nav_href('profil.php')); ?>"

        class="bottom-nav-item bottom-nav-item--profil<?php echo $admin_bottom_active === 'profil' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-user" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Profil</span>

    </a>

    <?php else: ?>

    <a href="<?php echo htmlspecialchars($nav_href('invoice/index.php')); ?>"

        class="bottom-nav-item bottom-nav-item--invoice<?php echo $admin_bottom_active === 'invoice' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Invoice</span>

    </a>

    <a href="<?php echo htmlspecialchars($nav_href('produits/index.php')); ?>"

        class="bottom-nav-item bottom-nav-item--produits<?php echo $admin_bottom_active === 'produits' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-box" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Produits</span>

    </a>

    <a href="<?php echo htmlspecialchars($nav_href('commandes/index.php')); ?>"

        class="bottom-nav-item bottom-nav-item--commandes<?php echo $admin_bottom_active === 'commandes' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-shopping-cart" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Commandes</span>

    </a>

    <?php if (admin_can_manage_livreurs()): ?>
    <a href="<?php echo htmlspecialchars($nav_href('livreurs/carte.php')); ?>"

        class="bottom-nav-item bottom-nav-item--map<?php echo $admin_bottom_active === 'map' ? ' is-active' : ''; ?>">

        <span class="bottom-nav-icon"><i class="fas fa-map-location-dot" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Map</span>

    </a>
    <?php endif; ?>

    <button type="button" class="bottom-nav-item bottom-nav-item--menu" id="adminBottomNavMenuBtn"

        aria-label="Ouvrir le menu admin">

        <span class="bottom-nav-icon"><i class="fas fa-th" aria-hidden="true"></i></span>

        <span class="bottom-nav-label">Menu</span>

    </button>

    <?php endif; ?>

</nav>

<script>

    (function () {

        document.documentElement.classList.add('has-bottom-nav', 'has-admin-bottom-nav');



        var menuBtn = document.getElementById('adminBottomNavMenuBtn');

        if (!menuBtn) {

            return;

        }



        var sidebar = document.getElementById('adminSidebar');

        var overlay = document.getElementById('sidebarOverlay');



        function setMenuOpen(isOpen) {

            menuBtn.classList.toggle('is-open', isOpen);

        }



        menuBtn.addEventListener('click', function () {

            if (typeof window.toggleSidebar === 'function') {

                window.toggleSidebar();

            } else if (sidebar && overlay) {

                sidebar.classList.toggle('show');

                overlay.classList.toggle('show');

                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';

                if (typeof window.setAdminSidebarOpen === 'function') {
                    window.setAdminSidebarOpen(sidebar.classList.contains('show'));
                }

            }

            setMenuOpen(sidebar && sidebar.classList.contains('show'));

        });



        if (overlay) {

            overlay.addEventListener('click', function () {

                setMenuOpen(false);
                if (typeof window.setAdminSidebarOpen === 'function') {
                    window.setAdminSidebarOpen(false);
                }

            });

        }



        window.addEventListener('resize', function () {

            if (window.innerWidth > 992) {

                setMenuOpen(false);

            }

        });

    })();

</script>

