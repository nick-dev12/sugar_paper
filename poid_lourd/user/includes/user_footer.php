    </main>
</div>

<?php include __DIR__ . '/user_rating_popup.php'; ?>

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
            if (window.innerWidth > 1024) {
                closeUserSidebar();
            }
        });
    })();
</script>
<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
<?php include __DIR__ . '/../../includes/firebase_notifications_scripts.php'; ?>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
</body>
</html>

