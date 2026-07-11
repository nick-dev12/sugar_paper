    </main>
</div>

<script>
    (function() {
        function closeAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
            if (typeof window.setAdminSidebarOpen === 'function') {
                window.setAdminSidebarOpen(false);
            } else {
                document.documentElement.classList.remove('admin-sidebar-open');
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeAdminSidebar();
            }
        });
    })();
</script>
<?php if (empty($skip_admin_bottom_nav)): ?>
<?php include __DIR__ . '/bottom_nav.php'; ?>
<?php endif; ?>
<?php
$is_bg_tracker_frame = (basename($_SERVER['PHP_SELF'] ?? '') === 'tracking-background.php');
$is_livreur_suivi_page = (basename($_SERVER['PHP_SELF'] ?? '') === 'suivi.php'
    && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/livreurs/') !== false);
if (!$is_bg_tracker_frame && !$is_livreur_suivi_page && isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
    if (function_exists('admin_can_livreur_gps') && admin_can_livreur_gps()) {
        require_once __DIR__ . '/../../includes/asset_version.php';
        echo '<script src="/js/livreur-bg-tracker.js' . asset_version_query() . '"></script>';
    }
}
?>
<?php include __DIR__ . '/../../includes/firebase_notifications_scripts.php'; ?>
</body>
</html>

