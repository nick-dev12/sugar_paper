<?php
/**
 * Barre de navigation inférieure (mobile & tablette)
 * Programmation procédurale uniquement
 */

if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}

require_once __DIR__ . '/store_nav_account.php';
$store_nav_account = store_nav_account_info();

if (!isset($panier_count)) {
    $panier_count = 0;
    if (isset($_SESSION['user_id'])) {
        $model_path = __DIR__ . '/../models/model_panier.php';
        if (file_exists($model_path)) {
            require_once $model_path;
            $panier_count = count_panier_items($_SESSION['user_id']);
        }
    }
}

if (!isset($bottom_nav_context)) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $bottom_nav_context = (strpos($request_uri, '/user/') !== false) ? 'user' : 'store';
}

if (!isset($bottom_nav_active)) {
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, '/user/') !== false) {
        $bottom_nav_active = 'compte';
    } elseif ($script === 'index.php') {
        $bottom_nav_active = 'accueil';
    } elseif (in_array($script, ['produits.php', 'categorie.php', 'produit.php', 'nouveautes.php', 'promo.php'], true)) {
        $bottom_nav_active = 'produits';
    } elseif (in_array($script, ['panier.php', 'commande.php', 'commande-confirmee.php'], true)) {
        $bottom_nav_active = 'panier';
    } else {
        $bottom_nav_active = '';
    }
}

$panier_url = isset($_SESSION['user_id']) ? '/panier.php' : '/user/connexion.php?redirect=panier';

$compte_url = $store_nav_account['url'];
$compte_label = $store_nav_account['short_label'];

$bottom_nav_menu_target = $bottom_nav_context === 'user' ? 'user' : 'store';
?>
<link rel="stylesheet" href="/css/bottom-nav.css<?php echo asset_version_query(); ?>">
<?php if ($bottom_nav_context === 'user'): ?>
<nav class="bottom-nav bottom-nav--user" id="bottomNav" aria-label="Navigation espace client">
    <a href="/user/mon-compte.php"
        class="bottom-nav-item bottom-nav-item--dashboard<?php echo $bottom_nav_active === 'dashboard' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-home" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Tableau de bord</span>
    </a>
    <a href="/user/mes-commandes.php"
        class="bottom-nav-item bottom-nav-item--commandes<?php echo $bottom_nav_active === 'commandes' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-shopping-bag" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Mes commandes</span>
    </a>
    <a href="<?php echo htmlspecialchars($panier_url); ?>"
        class="bottom-nav-item bottom-nav-item--panier<?php echo $bottom_nav_active === 'panier' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <?php if (isset($_SESSION['user_id']) && $panier_count > 0): ?>
                <span class="bottom-nav-badge"><?php echo $panier_count > 99 ? '99+' : (int) $panier_count; ?></span>
            <?php endif; ?>
        </span>
        <span class="bottom-nav-label">Mon panier</span>
    </a>
    <a href="/user/profil.php"
        class="bottom-nav-item bottom-nav-item--profil<?php echo $bottom_nav_active === 'profil' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-user" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Mon profil</span>
    </a>
    <button type="button" class="bottom-nav-item bottom-nav-item--menu" id="bottomNavMenuBtn"
        data-menu-target="user" aria-label="Ouvrir le menu">
        <span class="bottom-nav-icon"><i class="fas fa-th" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Menu</span>
    </button>
</nav>
<?php else: ?>
<nav class="bottom-nav" id="bottomNav" aria-label="Navigation principale">
    <a href="/index.php"
        class="bottom-nav-item bottom-nav-item--accueil<?php echo $bottom_nav_active === 'accueil' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-home" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Accueil</span>
    </a>
    <a href="/produits.php"
        class="bottom-nav-item bottom-nav-item--produits<?php echo $bottom_nav_active === 'produits' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-shopping-bag" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Produits</span>
    </a>
    <a href="<?php echo htmlspecialchars($panier_url); ?>"
        class="bottom-nav-item bottom-nav-item--panier<?php echo $bottom_nav_active === 'panier' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <?php if (isset($_SESSION['user_id']) && $panier_count > 0): ?>
                <span class="bottom-nav-badge"><?php echo $panier_count > 99 ? '99+' : (int) $panier_count; ?></span>
            <?php endif; ?>
        </span>
        <span class="bottom-nav-label">Panier</span>
    </a>
    <a href="<?php echo htmlspecialchars($compte_url); ?>"
        class="bottom-nav-item bottom-nav-item--compte<?php echo $bottom_nav_active === 'compte' ? ' is-active' : ''; ?>">
        <span class="bottom-nav-icon"><i class="fas fa-user" aria-hidden="true"></i></span>
        <span class="bottom-nav-label"><?php echo htmlspecialchars($compte_label); ?></span>
    </a>
    <button type="button" class="bottom-nav-item bottom-nav-item--menu" id="bottomNavMenuBtn"
        data-menu-target="<?php echo htmlspecialchars($bottom_nav_menu_target); ?>" aria-label="Ouvrir le menu">
        <span class="bottom-nav-icon"><i class="fas fa-th" aria-hidden="true"></i></span>
        <span class="bottom-nav-label">Menu</span>
    </button>
</nav>
<?php endif; ?>
<script>
    (function () {
        document.documentElement.classList.add('has-bottom-nav');

        var menuBtn = document.getElementById('bottomNavMenuBtn');
        if (!menuBtn) {
            return;
        }

        var target = menuBtn.getAttribute('data-menu-target') || 'store';

        function setMenuOpen(isOpen) {
            menuBtn.classList.toggle('is-open', isOpen);
        }

        if (target === 'user') {
            var userSidebar = document.getElementById('userSidebar');
            var userOverlay = document.getElementById('sidebarOverlay');

            menuBtn.addEventListener('click', function () {
                if (typeof window.toggleSidebar === 'function') {
                    window.toggleSidebar();
                } else if (userSidebar && userOverlay) {
                    userSidebar.classList.toggle('show');
                    userOverlay.classList.toggle('show');
                    document.body.style.overflow = userSidebar.classList.contains('show') ? 'hidden' : '';
                }
                setMenuOpen(userSidebar && userSidebar.classList.contains('show'));
            });

            if (userOverlay) {
                userOverlay.addEventListener('click', function () {
                    setMenuOpen(false);
                });
            }

            window.addEventListener('resize', function () {
                if (window.innerWidth > 992) {
                    setMenuOpen(false);
                }
            });
            return;
        }

        var storeSidebar = document.getElementById('navSidebar');
        var storeOverlay = document.getElementById('navSidebarOverlay');
        var storeToggle = document.getElementById('navMenuToggle');

        function openStoreMenu() {
            if (storeSidebar) {
                storeSidebar.classList.add('open');
            }
            if (storeOverlay) {
                storeOverlay.classList.add('show');
            }
            document.body.style.overflow = 'hidden';
            if (storeToggle) {
                var icon = storeToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            }
            setMenuOpen(true);
        }

        function closeStoreMenu() {
            if (storeSidebar) {
                storeSidebar.classList.remove('open');
            }
            if (storeOverlay) {
                storeOverlay.classList.remove('show');
            }
            document.body.style.overflow = '';
            if (storeToggle) {
                var icon = storeToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
            setMenuOpen(false);
        }

        menuBtn.addEventListener('click', function () {
            if (storeSidebar && storeSidebar.classList.contains('open')) {
                closeStoreMenu();
            } else {
                openStoreMenu();
            }
        });

        if (storeOverlay) {
            storeOverlay.addEventListener('click', closeStoreMenu);
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 992 && storeSidebar && storeSidebar.classList.contains('open')) {
                closeStoreMenu();
            }
        });
    })();
</script>
<?php
$jf_skip_pages = ['tracking-background.php'];
if (!in_array(basename($_SERVER['PHP_SELF'] ?? ''), $jf_skip_pages, true)) {
    if (empty($skip_jotform_ai_assistant) && !defined('JOTFORM_AI_ASSISTANT_INCLUDED')) {
        include __DIR__ . '/jotform_ai_assistant.php';
    }
}
?>
