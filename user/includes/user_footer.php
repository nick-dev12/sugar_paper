    </main>
</div>

<script>
    (function() {
        function closeUserSidebar() {
            var sidebar = document.getElementById('userSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeUserSidebar();
            }
        });
    })();
</script>
<?php
$bottom_nav_context = 'user';
$bottom_nav_active = 'compte';
include __DIR__ . '/../../includes/bottom_nav.php';
?>
<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
<?php include __DIR__ . '/../../includes/firebase_notifications_scripts.php'; ?>
</body>
</html>

