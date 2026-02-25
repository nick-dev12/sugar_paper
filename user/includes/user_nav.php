<?php
/**
 * Inclusion de la barre de navigation utilisateur
 * Programmation procédurale uniquement
 */

// Déterminer le chemin de base
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="user-container">
    <!-- Barre de navigation verticale -->
    <aside class="user-sidebar" id="userSidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-circle logo-icon"></i>
            <h2>Mon Compte</h2>
        </div>
        <nav class="sidebar-menu">
            <a href="mon-compte.php" class="menu-item <?php echo $current_page == 'mon-compte.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="/produits.php" class="menu-item">
                <i class="fas fa-box"></i>
                <span>Tous les produits</span>
            </a>
            <a href="/panier.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Mon panier</span>
            </a>
            <a href="mes-commandes.php" class="menu-item <?php echo $current_page == 'mes-commandes.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>Mes commandes</span>
            </a>
            <a href="commandes-annulees.php" class="menu-item <?php echo $current_page == 'commandes-annulees.php' ? 'active' : ''; ?>">
                <i class="fas fa-ban"></i>
                <span>Commandes annulées</span>
            </a>
            <a href="produits-livres.php" class="menu-item <?php echo $current_page == 'produits-livres.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Produits livrés</span>
            </a>
            <a href="produits-visites.php" class="menu-item">
                <i class="fas fa-eye"></i>
                <span>Produits visités</span>
            </a>
            <a href="favoris.php" class="menu-item">
                <i class="fas fa-heart"></i>
                <span>Mes favoris</span>
            </a>
            <a href="profil.php" class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Mon profil</span>
            </a>
            <a href="deconnexion.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="user-content" id="userContent">

