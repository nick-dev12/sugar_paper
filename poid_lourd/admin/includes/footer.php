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
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 600) {
                closeAdminSidebar();
            }
        });
    })();
</script>
<?php
if (!empty($__vendeur_cert_niveau_hero) || !empty($__vendeur_cert_notif)) {
    require_once __DIR__ . '/../../includes/asset_version.php';
    if (!empty($__vendeur_cert_niveau_hero)) {
        echo '<link rel="stylesheet" href="/css/vendor-cert-ribbon.css' . asset_version_query() . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="/css/vendeur-cert-notif.css' . asset_version_query() . '">' . "\n";
}
if (!empty($__vendeur_cert_notif) && is_array($__vendeur_cert_notif)) {
    require __DIR__ . '/../../includes/partials/vendeur_certification_notif_modal.php';
}
?>
<?php include __DIR__ . '/../../includes/firebase_notifications_scripts.php'; ?>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
</body>
</html>

