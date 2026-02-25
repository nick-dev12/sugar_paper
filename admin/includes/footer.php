    </main>
</div>

<script>
    /**
     * Fonction pour afficher/masquer la barre latérale sur mobile
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.getElementById('adminContent');
        
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Empêcher le scroll du body quand le menu est ouvert
        if (sidebar.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
    }

    // Fermer le menu si on clique sur l'overlay
    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        toggleSidebar();
    });

    // Gérer le redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth > 600) {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
</script>
</body>
</html>

