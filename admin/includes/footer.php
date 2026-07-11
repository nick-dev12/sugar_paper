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
            if (window.innerWidth > 992) {
                closeAdminSidebar();
            }
        });
    })();
</script>
<?php include __DIR__ . '/bottom_nav.php'; ?>
<?php include __DIR__ . '/../../includes/firebase_notifications_scripts.php'; ?>
</body>
</html>

