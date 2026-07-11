    </main>
</div>

<script>
    (function() {
        function closeSuperAdminSidebar() {
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
                closeSuperAdminSidebar();
            }
        });
    })();
</script>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
</body>
</html>
