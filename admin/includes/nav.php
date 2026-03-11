<?php
/**
 * Inclusion de la barre de navigation admin
 * Programmation procédurale uniquement
 */

// Déterminer le chemin de base selon le dossier actuel
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_produits = strpos($current_dir, '/produits') !== false;
$is_categories = strpos($current_dir, '/categories') !== false;
$is_stock = strpos($current_dir, '/stock') !== false;
$is_slider = strpos($current_dir, '/slider') !== false;
$is_parametres = strpos($current_dir, '/parametres') !== false;
$is_commandes = strpos($current_dir, '/commandes') !== false;
$is_commandes_perso = strpos($current_dir, '/commandes-personnalisees') !== false;
$is_devis = strpos($current_dir, '/devis') !== false;
$is_users = strpos($current_dir, '/users') !== false;
$is_contacts = strpos($current_dir, '/contacts') !== false;
$is_zones_livraison = strpos($current_dir, '/zones-livraison') !== false;
$is_comptes = strpos($current_dir, '/comptes') !== false;

$admin_role = $_SESSION['admin_role'] ?? 'admin';
$can_manage_users = ($admin_role === 'admin');
$can_manage_comptes = ($admin_role === 'admin');

if ($is_produits || $is_categories || $is_stock || $is_slider || $is_parametres || $is_commandes || $is_commandes_perso || $is_devis || $is_users || $is_contacts || $is_zones_livraison || $is_comptes) {
    $base_path = '../';
} else {
    $base_path = '';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function toggleAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        }
        window.toggleSidebar = toggleAdminSidebar;
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
        </div>
        <nav class="sidebar-menu">
            <a href="<?php echo $base_path; ?>dashboard.php"
                class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="<?php echo $base_path; ?>produits/index.php"
                class="menu-item <?php echo ($is_produits && $current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <!-- <a href="<?php echo $base_path; ?>categories/index.php"
                class="menu-item <?php echo ($is_categories && $current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </a> -->
            <a href="<?php echo $base_path; ?>stock/index.php"
                class="menu-item <?php echo ($is_stock) ? 'active' : ''; ?>">
                <i class="fas fa-boxes-stacked"></i>
                <span>Stock</span>
            </a>

            <a href="<?php echo $base_path; ?>commandes/index.php"
                class="menu-item <?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php')) ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="<?php echo $base_path; ?>commandes-personnalisees/index.php"
                class="menu-item <?php echo ($is_commandes_perso && ($current_page == 'index.php' || $current_page == 'details.php')) ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Commandes personnalisées</span>
            </a>
            <a href="<?php echo $base_path; ?>devis/index.php"
                class="menu-item <?php echo ($is_devis && ($current_page == 'index.php' || $current_page == 'details.php')) ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i>
                <span>Devis</span>
            </a>
            <a href="<?php echo $base_path; ?>contacts/index.php"
                class="menu-item <?php echo $is_contacts ? 'active' : ''; ?>">
                <i class="fas fa-address-book"></i>
                <span>Contacts</span>
            </a>
            <?php if ($can_manage_users): ?>
                <a href="<?php echo $base_path; ?>users/index.php"
                    class="menu-item <?php echo ($is_users && $current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            <?php endif; ?>
            <?php if ($can_manage_comptes): ?>
                <a href="<?php echo $base_path; ?>comptes/index.php"
                    class="menu-item <?php echo $is_comptes ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    <span>Comptes</span>
                </a>
            <?php endif; ?>
            <a href="<?php echo $base_path; ?>zones-livraison/index.php"
                class="menu-item <?php echo ($is_zones_livraison) ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                <span>Zones de livraison</span>
            </a>
            <a href="<?php echo $base_path; ?>parametres.php"
                class="menu-item <?php echo ($current_page == 'parametres.php' || strpos($current_dir, '/parametres') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <!-- <a href="<?php echo $base_path; ?>test-email.php" class="menu-item <?php echo $current_page == 'test-email.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Test email</span>
            </a> -->
            <a href="<?php echo $base_path; ?>profil.php"
                class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>Mon profil</span>
            </a>
            <a href="<?php echo $base_path; ?>logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="admin-content" id="adminContent">