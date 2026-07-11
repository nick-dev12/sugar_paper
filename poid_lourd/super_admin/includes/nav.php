<?php
/**
 * Navigation latérale super administrateur
 */

$current_dir = dirname($_SERVER['PHP_SELF'] ?? '');
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$is_boutiques = strpos($current_dir, '/boutiques') !== false;
$is_utilisateurs = strpos($current_dir, '/utilisateurs') !== false;
$is_logs = strpos($current_dir, '/logs') !== false;
$is_parametres = strpos($current_dir, '/parametres') !== false;
$is_certifications = strpos($current_dir, '/certifications') !== false;
$is_comptes_sa = strpos($current_dir, '/comptes') !== false;
$is_annonces_sa = strpos($current_dir, '/annonces') !== false;

$base_path = ($is_boutiques || $is_utilisateurs || $is_logs || $is_parametres || $is_certifications || $is_comptes_sa || $is_annonces_sa) ? '../' : '';

$sa_cert_pending_count = 0;
if (file_exists(dirname(__DIR__, 2) . '/models/model_vendeur_certification.php')) {
    require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
    $sa_cert_pending_count = vendeur_certification_count_en_attente();
}

?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

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
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <i class="fas fa-globe logo-icon" aria-hidden="true"></i>
            <h2>Super Admin</h2>
        </div>
        <nav class="sidebar-menu" aria-label="Navigation super administrateur">
            <a href="<?php echo htmlspecialchars($base_path . 'dashboard.php'); ?>"
                class="menu-item <?php echo $current_page === 'dashboard.php' && !$is_boutiques && !$is_utilisateurs && !$is_logs && !$is_parametres && !$is_certifications && !$is_comptes_sa && !$is_annonces_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                <span class="menu-item__text">Tableau de bord</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'boutiques/index.php'); ?>"
                class="menu-item <?php echo $is_boutiques ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item__text">Boutiques</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'utilisateurs/index.php'); ?>"
                class="menu-item <?php echo $is_utilisateurs ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span class="menu-item__text">Clients plateforme</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'certifications/index.php'); ?>"
                class="menu-item <?php echo $is_certifications ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-certificate"></i></span>
                <span class="menu-item__text">Certifications</span>
                <?php if ($sa_cert_pending_count > 0): ?>
                    <span class="menu-item__badge-cert" title="Demandes en attente"><?php echo (int) $sa_cert_pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'logs/index.php'); ?>"
                class="menu-item <?php echo $is_logs ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                <span class="menu-item__text">Journal d'audit</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'parametres/index.php'); ?>"
                class="menu-item <?php echo $is_parametres ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-cog"></i></span>
                <span class="menu-item__text">Paramètres</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'annonces/index.php'); ?>"
                class="menu-item <?php echo $is_annonces_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
                <span class="menu-item__text">Annonces</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'comptes/index.php'); ?>"
                class="menu-item <?php echo $is_comptes_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item__text">Comptes super admin</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'compte.php'); ?>"
                class="menu-item <?php echo $current_page === 'compte.php' ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                <span class="menu-item__text">Mon compte</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'logout.php'); ?>" class="menu-item menu-item--logout">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-sign-out-alt"></i></span>
                <span class="menu-item__text">Déconnexion</span>
            </a>
        </nav>
    </aside>

    <main class="admin-content" id="adminContent">
