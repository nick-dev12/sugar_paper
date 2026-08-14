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
$user_footer_page = basename($_SERVER['PHP_SELF'] ?? '');
$bottom_nav_context = 'user';
$bottom_nav_active = 'dashboard';
if (in_array($user_footer_page, ['mes-commandes.php', 'commande-categorie.php', 'commande-personnalisee-details.php', 'commandes-annulees.php', 'produits-livres.php'], true)) {
    $bottom_nav_active = 'commandes';
} elseif ($user_footer_page === 'profil.php') {
    $bottom_nav_active = 'profil';
} elseif ($user_footer_page === 'panier.php') {
    $bottom_nav_active = 'panier';
}
include __DIR__ . '/../../includes/bottom_nav.php';
?>
<?php
if (!defined('JOTFORM_AI_ASSISTANT_INCLUDED')) {
    include __DIR__ . '/../../includes/jotform_ai_assistant.php';
}
?>
<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
<?php
$enable_firebase_notifications = true;
$firebase_notify_type = 'user';
include __DIR__ . '/../../includes/firebase_notifications_scripts.php';
include __DIR__ . '/../../includes/checkout_modals_init.php';
?>
</body>
</html>

