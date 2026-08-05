<?php
/**
 * Inclusion de la barre de navigation admin
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../../includes/site_url.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_admin.php';

$admin_nav_base = rtrim(get_public_root_uri_path(), '/') . '/admin/';

$current_dir = dirname($_SERVER['PHP_SELF']);
$is_produits = strpos($current_dir, '/produits') !== false;
$is_categories = strpos($current_dir, '/categories') !== false;
$is_stock = strpos($current_dir, '/stock') !== false;
$is_slider = strpos($current_dir, '/slider') !== false;
$is_parametres = strpos($current_dir, '/parametres') !== false;
$is_commandes = strpos($current_dir, '/commandes') !== false;
$is_commandes_perso = strpos($current_dir, '/commandes-personnalisees') !== false;
$is_commandes_std = $is_commandes && !$is_commandes_perso;
$is_devis = strpos($current_dir, '/devis') !== false;
$is_invoice = strpos($current_dir, '/invoice') !== false;
$is_users = strpos($current_dir, '/users') !== false;
$is_zones_livraison = strpos($current_dir, '/zones-livraison') !== false;
$is_livreurs = strpos($current_dir, '/livreurs') !== false;
$is_comptes = strpos($current_dir, '/comptes') !== false;
$is_employes_rh = $is_comptes && strpos($current_dir, '/employes') !== false;

$admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
$_SESSION['admin_role'] = $admin_role;
$can_manage_users = ($admin_role === 'admin');
$can_manage_comptes = ($admin_role === 'admin');
$can_manage_employes_rh = in_array($admin_role, ['admin', 'rh', 'informaticien', 'developpeur', 'contable'], true);
$is_contable_nav = ($admin_role === 'contable');
$is_livreur_nav = ($admin_role === 'livreur');
$is_utilisateur_nav = ($admin_role === 'utilisateur');

$current_page = basename($_SERVER['PHP_SELF']);
$is_livreurs_carte = $is_livreurs && ($current_page === 'carte.php');
$is_livreurs_notes = $is_livreurs && in_array($current_page, ['notes.php', 'notes-detail.php'], true);
$nav_href = function ($path) use ($admin_nav_base) {
    return $admin_nav_base . ltrim($path, '/');
};
?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function setAdminSidebarOpen(isOpen) {
            document.documentElement.classList.toggle('admin-sidebar-open', !!isOpen);
        }
        function toggleAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                setAdminSidebarOpen(sidebar.classList.contains('show'));
            }
        }
        window.toggleSidebar = toggleAdminSidebar;
        window.setAdminSidebarOpen = setAdminSidebarOpen;
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('menuToggle');
            var overlay = document.getElementById('sidebarOverlay');
            if (btn) btn.addEventListener('click', toggleAdminSidebar);
            if (overlay) overlay.addEventListener('click', toggleAdminSidebar);
        });
    })();
</script>

<div class="admin-container">
    <!-- Barre de navigation verticale -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <i class="fas fa-store logo-icon"></i>
            <h2>Sugar Paper</h2>
            <?php if ($is_livreur_nav): ?>
            <a href="<?php echo htmlspecialchars($nav_href('logout.php')); ?>"
                class="sidebar-header__logout"
                aria-label="Déconnexion"
                title="Déconnexion">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            </a>
            <?php endif; ?>
        </div>
        <nav class="sidebar-menu">
            <?php if ($is_contable_nav): ?>
            <a href="<?php echo $nav_href('comptes/employes/index.php'); ?>"
                class="menu-item <?php echo $is_employes_rh ? 'active' : ''; ?>">
                <i class="fas fa-id-card-clip"></i>
                <span>Employés</span>
            </a>
            <a href="<?php echo $nav_href('parametres.php'); ?>"
                class="menu-item <?php echo ($current_page === 'parametres.php' || strpos($current_dir, '/parametres') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <a href="<?php echo $nav_href('profil.php'); ?>"
                class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Mon profil</span>
            </a>
            <?php elseif ($is_livreur_nav): ?>
            <a href="<?php echo $nav_href('livreurs/index.php'); ?>"
                class="menu-item <?php echo ($is_livreurs && $current_page === 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-motorcycle"></i>
                <span>Livreurs GPS</span>
            </a>
            <a href="<?php echo $nav_href('zones-livraison/index.php'); ?>"
                class="menu-item <?php echo ($is_zones_livraison) ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                <span>Zones de livraison</span>
            </a>
            <a href="<?php echo $nav_href('profil.php'); ?>"
                class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Mon profil</span>
            </a>
            <?php elseif ($is_utilisateur_nav): ?>
            <a href="<?php echo $nav_href('dashboard.php'); ?>"
                class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="<?php echo $nav_href('produits/index.php'); ?>"
                class="menu-item <?php echo ($is_produits && $current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="<?php echo $nav_href('stock/index.php'); ?>"
                class="menu-item <?php echo ($is_stock) ? 'active' : ''; ?>">
                <i class="fas fa-boxes-stacked"></i>
                <span>Stock</span>
            </a>
            <a href="<?php echo $nav_href('commandes/index.php'); ?>"
                class="menu-item <?php echo ($is_commandes_std && in_array($current_page, ['index.php', 'livrees.php', 'annulees.php', 'details.php', 'historique-ventes.php', 'archives.php'], true)) ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="<?php echo $nav_href('commandes-personnalisees/index.php'); ?>"
                class="menu-item <?php echo ($is_commandes_perso && ($current_page == 'index.php' || $current_page == 'details.php')) ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Commandes personnalisées</span>
            </a>
            <a href="<?php echo $nav_href('invoice/index.php'); ?>"
                class="menu-item <?php echo ($is_invoice || ($is_devis && in_array($current_page, ['index.php', 'details.php'], true))) ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoice</span>
            </a>
            <a href="<?php echo $nav_href('zones-livraison/index.php'); ?>"
                class="menu-item <?php echo ($is_zones_livraison) ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                <span>Zones de livraison</span>
            </a>
            <?php if (admin_can_view_livreurs_map()): ?>
            <a href="<?php echo $nav_href('livreurs/carte.php'); ?>"
                class="menu-item <?php echo $is_livreurs_carte ? 'active' : ''; ?>">
                <i class="fas fa-map-location-dot"></i>
                <span>Map</span>
            </a>
            <?php endif; ?>
            <?php if (admin_can_view_livreur_notes()): ?>
            <a href="<?php echo $nav_href('livreurs/notes.php'); ?>"
                class="menu-item <?php echo $is_livreurs_notes ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Notes clients</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $nav_href('profil.php'); ?>"
                class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Mon profil</span>
            </a>
            <?php else: ?>
            <a href="<?php echo $nav_href('dashboard.php'); ?>"
                class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="<?php echo $nav_href('produits/index.php'); ?>"
                class="menu-item <?php echo ($is_produits && $current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="<?php echo $nav_href('stock/index.php'); ?>"
                class="menu-item <?php echo ($is_stock) ? 'active' : ''; ?>">
                <i class="fas fa-boxes-stacked"></i>
                <span>Stock</span>
            </a>

            <a href="<?php echo $nav_href('commandes/index.php'); ?>"
                class="menu-item <?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'archives.php')) ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="<?php echo $nav_href('commandes-personnalisees/index.php'); ?>"
                class="menu-item <?php echo ($is_commandes_perso && ($current_page == 'index.php' || $current_page == 'details.php')) ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Commandes personnalisées</span>
            </a>
            <a href="<?php echo $nav_href('invoice/index.php'); ?>"
                class="menu-item <?php echo ($is_invoice || ($is_devis && ($current_page == 'index.php' || $current_page == 'details.php'))) ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoice</span>
            </a>
            <?php if ($can_manage_users): ?>
                <a href="<?php echo $nav_href('users/index.php'); ?>"
                    class="menu-item <?php echo ($is_users && $current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            <?php endif; ?>
            <?php if ($can_manage_comptes): ?>
                <a href="<?php echo $nav_href('comptes/index.php'); ?>"
                    class="menu-item <?php echo ($is_comptes && !$is_employes_rh) ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    <span>Comptes</span>
                </a>
            <?php endif; ?>
            <?php if ($can_manage_employes_rh): ?>
                <a href="<?php echo $nav_href('comptes/employes/index.php'); ?>"
                    class="menu-item <?php echo $is_employes_rh ? 'active' : ''; ?>">
                    <i class="fas fa-id-card-clip"></i>
                    <span>Employés</span>
                </a>
            <?php endif; ?>
            <a href="<?php echo $nav_href('zones-livraison/index.php'); ?>"
                class="menu-item <?php echo ($is_zones_livraison) ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                <span>Zones de livraison</span>
            </a>
            <?php if (admin_can_livreur_gps()): ?>
            <a href="<?php echo $nav_href('livreurs/index.php'); ?>"
                class="menu-item <?php echo ($is_livreurs && $current_page === 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-motorcycle"></i>
                <span>Livreurs GPS</span>
            </a>
            <?php endif; ?>
            <?php if (admin_can_view_livreurs_map()): ?>
            <a href="<?php echo $nav_href('livreurs/carte.php'); ?>"
                class="menu-item <?php echo $is_livreurs_carte ? 'active' : ''; ?>">
                <i class="fas fa-map-location-dot"></i>
                <span>Map</span>
            </a>
            <?php endif; ?>
            <?php if (admin_can_view_livreur_notes()): ?>
            <a href="<?php echo $nav_href('livreurs/notes.php'); ?>"
                class="menu-item <?php echo $is_livreurs_notes ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Notes clients</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $nav_href('parametres.php'); ?>"
                class="menu-item <?php echo ($current_page == 'parametres.php' || strpos($current_dir, '/parametres') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <a href="<?php echo $nav_href('profil.php'); ?>"
                class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>Mon profil</span>
            </a>
            <?php endif; ?>
            <?php if (!$is_livreur_nav && !$is_contable_nav): ?>
            <button type="button" id="btn-enable-notifications" class="menu-item menu-item-notify"
                data-notify-type="admin"
                title="Recevoir les alertes de nouvelles commandes sur cet appareil (même site fermé)">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </button>
            <?php endif; ?>
            <?php if ($admin_role === 'admin'): ?>
            <a href="<?php echo $nav_href('fcm-diagnostic.php'); ?>"
                class="menu-item <?php echo $current_page === 'fcm-diagnostic.php' ? 'active' : ''; ?>"
                title="Diagnostic push FCM et files d'attente">
                <i class="fas fa-satellite-dish"></i>
                <span>Diag. FCM</span>
            </a>
            <a href="<?php echo $nav_href('test-email.php'); ?>"
                class="menu-item <?php echo $current_page === 'test-email.php' ? 'active' : ''; ?>"
                title="Tester l'envoi SMTP et la file d'attente">
                <i class="fas fa-envelope-open-text"></i>
                <span>Test email</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $nav_href('logout.php'); ?>" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="admin-content" id="adminContent">
