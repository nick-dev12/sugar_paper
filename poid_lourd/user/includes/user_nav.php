<?php
/**
 * Inclusion de la barre de navigation utilisateur
 * Programmation procédurale uniquement
 */

$current_page = basename($_SERVER['PHP_SELF']);

$user_nav_commandes_actives = 0;
$user_nav_annonces_non_lues = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../models/model_commandes.php';
    $user_nav_commandes_actives = count_commandes_actives_by_user((int) $_SESSION['user_id']);
    if (file_exists(__DIR__ . '/../../models/model_annonces.php')) {
        require_once __DIR__ . '/../../models/model_annonces.php';
        if (function_exists('annonces_table_exists') && annonces_table_exists()) {
            $user_nav_annonces_non_lues = annonce_count_unread_client((int) $_SESSION['user_id']);
        }
    }
}

$user_nav_items = [
    [
        'key' => 'dashboard',
        'href' => 'mon-compte.php',
        'match' => ['mon-compte.php'],
        'fa' => 'fa-home',
        'class_base' => 'menu-item menu-item--dashboard',
        'label' => 'Tableau de bord',
        'dock' => 'primary',
    ],
    [
        'key' => 'cart',
        'href' => '/panier.php',
        'match' => ['panier.php'],
        'fa' => 'fa-shopping-cart',
        'class_base' => 'menu-item menu-item--cart',
        'label' => 'Mon panier',
        'dock' => 'primary',
    ],
    [
        'key' => 'orders',
        'href' => 'mes-commandes.php',
        'match' => ['mes-commandes.php'],
        'fa' => 'fa-shopping-bag',
        'class_base' => 'menu-item menu-item--orders',
        'label' => 'Mes commandes',
        'dock' => 'primary',
    ],
    [
        'key' => 'subscriptions',
        'href' => 'mes-boutiques-abonnees.php',
        'match' => ['mes-boutiques-abonnees.php'],
        'fa' => 'fa-store',
        'class_base' => 'menu-item menu-item--subscriptions',
        'label' => 'Mes boutiques',
        'dock' => 'more',
    ],
    [
        'key' => 'cancelled',
        'href' => 'commandes-annulees.php',
        'match' => ['commandes-annulees.php'],
        'fa' => 'fa-ban',
        'class_base' => 'menu-item menu-item--cancelled',
        'label' => 'Commandes annulées',
        'dock' => 'more',
    ],
    [
        'key' => 'delivered',
        'href' => 'produits-livres.php',
        'match' => ['produits-livres.php'],
        'fa' => 'fa-check-circle',
        'class_base' => 'menu-item menu-item--delivered',
        'label' => 'Produits livrés',
        'dock' => 'more',
    ],
    [
        'key' => 'announcements',
        'href' => 'annonces.php',
        'match' => ['annonces.php'],
        'fa' => 'fa-bullhorn',
        'class_base' => 'menu-item menu-item--announcements',
        'label' => 'Annonces',
        'dock' => 'more',
    ],
    [
        'key' => 'profile',
        'href' => 'profil.php',
        'match' => ['profil.php'],
        'fa' => 'fa-user',
        'class_base' => 'menu-item menu-item--profile',
        'label' => 'Mon profil',
        'dock' => 'primary',
    ],
    [
        'key' => 'logout',
        'href' => 'deconnexion.php',
        'match' => [],
        'fa' => 'fa-sign-out-alt',
        'class_base' => 'menu-item menu-item--logout',
        'label' => 'Déconnexion',
        'dock' => 'more',
    ],
];

if (!function_exists('user_nav_link_classes')) {
    /**
     * Classes du lien sidebar / dock selon page courante
     *
     * @param array<string, mixed> $item
     */
    function user_nav_link_classes(array $item, string $current_page, string $suffix_class = '')
    {
        $parts = preg_split('/\s+/u', $item['class_base'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_values(array_unique($parts));
        if (!empty($item['match']) && is_array($item['match']) && in_array($current_page, $item['match'], true)) {
            $parts[] = 'active';
        }
        $extra = preg_split('/\s+/u', $suffix_class, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($extra as $c) {
            $parts[] = $c;
        }
        return trim(implode(' ', array_unique(array_filter($parts))));
    }
}

if (!function_exists('user_nav_more_has_active_child')) {
    /**
     * Page active parmi les liens du groupe « Menu » ?
     *
     * @param array<int, array<string, mixed>> $items
     */
    function user_nav_more_has_active_child(array $items, string $current_page)
    {
        foreach ($items as $it) {
            if (($it['dock'] ?? '') !== 'more') {
                continue;
            }
            if (
                !empty($it['match']) &&
                is_array($it['match']) &&
                in_array($current_page, $it['match'], true)
            ) {
                return true;
            }
        }
        return false;
    }
}

$dock_more_has_active_child = user_nav_more_has_active_child($user_nav_items, $current_page);
?>
<!-- Bouton menu mobile (réaffiché seulement en desktop élargi si utilisé hors zone dock) -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile (principalement désactivé en dock bas) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function toggleUserSidebar() {
            var sidebar = document.getElementById('userSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.documentElement.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        }
        window.toggleSidebar = toggleUserSidebar;
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('menuToggle');
            var overlay = document.getElementById('sidebarOverlay');
            if (btn) btn.addEventListener('click', toggleUserSidebar);
            if (overlay) overlay.addEventListener('click', toggleUserSidebar);
        });
    })();
</script>

<div class="user-container">
    <!-- Barre de navigation verticale (desktop) + dock compact (tablet/mobile) -->
    <aside class="user-sidebar user-sidebar--glass" id="userSidebar" aria-label="Navigation compte client">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon" aria-hidden="true">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="sidebar-brand-text">
                    <span class="sidebar-brand-eyebrow">Espace client</span>
                    <h2>Mon compte</h2>
                </div>
            </div>
        </div>
        <!-- Rail complet desktop -->
        <nav class="sidebar-menu sidebar-menu--rail" aria-label="Menu principal">
            <?php foreach ($user_nav_items as $nav_item): ?>
                <?php if (($nav_item['key'] ?? '') === 'logout'): ?>
            <div class="sidebar-menu-split" role="presentation" aria-hidden="true"></div>
                <?php endif; ?>
            <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                class="<?php echo htmlspecialchars(
                    user_nav_link_classes(
                        $nav_item,
                        $current_page,
                        (
                            (($nav_item['key'] ?? '') === 'orders' && $user_nav_commandes_actives > 0) ||
                            (($nav_item['key'] ?? '') === 'announcements' && $user_nav_annonces_non_lues > 0)
                        ) ? 'menu-item--has-badge' : ''
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars(
                    (string) $nav_item['fa'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"></i></span>
                <span class="menu-item-text"><?php echo htmlspecialchars(
                    (string) $nav_item['label'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?></span>
                <?php if (($nav_item['key'] ?? '') === 'orders' && $user_nav_commandes_actives > 0): ?>
                <span class="menu-item__badge" title="<?php echo (int) $user_nav_commandes_actives; ?> commande<?php echo $user_nav_commandes_actives > 1 ? 's' : ''; ?> en cours" aria-label="<?php echo (int) $user_nav_commandes_actives; ?> commande<?php echo $user_nav_commandes_actives > 1 ? 's' : ''; ?> en cours"><?php echo (int) $user_nav_commandes_actives; ?></span>
                <?php elseif (($nav_item['key'] ?? '') === 'announcements' && $user_nav_annonces_non_lues > 0): ?>
                <span class="menu-item__badge" title="<?php echo (int) $user_nav_annonces_non_lues; ?> annonce<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?> non lue<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?>" aria-label="<?php echo (int) $user_nav_annonces_non_lues; ?> annonce<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?> non lue<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?>"><?php echo (int) $user_nav_annonces_non_lues; ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Dock client : même structure DOM que boutique / vendeur (shop-bottom-dock → shop-dock-bar → shop-dock-primary) -->
    <div class="shop-bottom-dock" id="userBottomDock" aria-label="Navigation compte rapide">
        <div class="shop-dock-bar" id="userDockBar" aria-label="Navigation compte réduite" hidden>
            <nav class="shop-dock-primary shop-dock-primary--cols-5" aria-label="Raccourcis">
                <?php foreach ($user_nav_items as $nav_item): ?>
                    <?php if (($nav_item['dock'] ?? '') !== 'primary') {
                        continue;
                    } ?>
                <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo htmlspecialchars(
                        user_nav_link_classes(
                            $nav_item,
                            $current_page,
                            'menu-item--dock-mini' . (($nav_item['key'] ?? '') === 'orders' && $user_nav_commandes_actives > 0 ? ' menu-item--has-badge' : '')
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>">
                    <span class="menu-item__icon menu-item__icon--badge-host" aria-hidden="true"><i class="fas <?php echo htmlspecialchars(
                        (string) $nav_item['fa'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"></i>
                    <?php if (($nav_item['key'] ?? '') === 'orders' && $user_nav_commandes_actives > 0): ?>
                    <span class="menu-item__badge menu-item__badge--dock" aria-label="<?php echo (int) $user_nav_commandes_actives; ?> commande<?php echo $user_nav_commandes_actives > 1 ? 's' : ''; ?> en cours"><?php echo (int) $user_nav_commandes_actives; ?></span>
                    <?php endif; ?>
                    </span>
                    <span class="menu-item__text"><?php echo htmlspecialchars(
                        (string) $nav_item['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></span>
                </a>
                <?php endforeach; ?>
            <button type="button"
                id="userDockMenuBtn"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-controls="userDockMenuPanel"
                class="menu-item menu-item--dock-mini menu-item--dock-mini-btn<?php echo $dock_more_has_active_child ? ' menu-item--dock-mini-btn--hint' : ''; ?>">
                <span class="menu-item__icon menu-item__icon--hint-host" aria-hidden="true"><i class="fas fa-th"></i></span>
                <span class="menu-item__text">Menu</span>
            </button>
            </nav>
        </div>
    </div>

    <!-- Panneau « autres liens » — hors aside (backdrop-filter évite fullscreen fixed) -->
    <div class="user-dock-menu-layer" id="userDockMenuLayer" aria-hidden="true">
        <div class="user-dock-menu-backdrop" id="userDockMenuBackdrop" role="presentation"></div>
        <div class="user-dock-menu-panel" id="userDockMenuPanel" role="dialog" aria-modal="true"
            aria-labelledby="userDockMenuTitle">
            <header class="user-dock-menu-panel-hd">
                <strong id="userDockMenuTitle">Autres liens</strong>
                <button type="button" class="user-dock-menu-close" id="userDockMenuClose"
                    aria-label="Fermer le menu">
                    <i class="fas fa-times"></i>
                </button>
            </header>
            <nav class="user-dock-menu-panel-nav" aria-label="Menu étendu">
                <?php foreach ($user_nav_items as $nav_item): ?>
                    <?php if (($nav_item['dock'] ?? '') !== 'more') {
                        continue;
                    } ?>
                    <?php if (($nav_item['key'] ?? '') !== 'logout'): ?>
                <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo htmlspecialchars(
                        user_nav_link_classes($nav_item, $current_page, 'menu-item--dock-sheet-row'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>">
                    <span class="menu-item-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars(
                        (string) $nav_item['fa'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"></i></span>
                    <span class="menu-item-text"><?php echo htmlspecialchars(
                        (string) $nav_item['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></span>
                    <?php if (($nav_item['key'] ?? '') === 'announcements' && $user_nav_annonces_non_lues > 0): ?>
                    <span class="menu-item__badge" aria-label="<?php echo (int) $user_nav_annonces_non_lues; ?> annonce<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?> non lue<?php echo $user_nav_annonces_non_lues > 1 ? 's' : ''; ?>"><?php echo (int) $user_nav_annonces_non_lues; ?></span>
                    <?php endif; ?>
                </a>
                    <?php else: ?>
                <div class="user-dock-menu-divider" role="presentation"></div>
                <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo htmlspecialchars(
                        user_nav_link_classes(
                            $nav_item,
                            $current_page,
                            'menu-item--dock-sheet-row menu-item--dock-sheet-logout'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>">
                    <span class="menu-item-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars(
                        (string) $nav_item['fa'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"></i></span>
                    <span class="menu-item-text"><?php echo htmlspecialchars(
                        (string) $nav_item['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></span>
                </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <style>
    /* ================================================================
       USER CLIENT BOTTOM DOCK — REDESIGN v2
       Barre navigation responsive mobile/tablette — fond blanc nacré,
       icônes squircles colorées, état actif bleu site, panel sheet premium
       ================================================================ */

    /* Variables couleurs par onglet */
    #userBottomDock {
        --udock-c1: #3564a6;   /* Tableau de bord — bleu principal */
        --udock-c2: #FF6B35;   /* Panier — orange site */
        --udock-c3: #3564a6;   /* Commandes — bleu */
        --udock-c4: #6366f1;   /* Profil — indigo */
        --udock-c5: #3564a6;   /* Menu — bleu principal */
    }

    @media (max-width: 1024px) {

        /* ---- Dock bar — fond blanc nacré, liseré bleu du site ---- */
        #userBottomDock.shop-bottom-dock .shop-dock-bar {
            background: rgba(255,255,255,0.97) !important;
            backdrop-filter: blur(22px) saturate(1.5) !important;
            -webkit-backdrop-filter: blur(22px) saturate(1.5) !important;
            border-top: 2px solid rgba(53,100,166,0.14) !important;
            box-shadow: 0 -6px 28px rgba(53,100,166,0.12), 0 -1px 0 rgba(53,100,166,0.07) !important;
            padding: 8px 10px calc(env(safe-area-inset-bottom,0px) + 8px) !important;
            gap: 0 !important;
        }

        /* ---- Nav grid — fond transparent ---- */
        #userBottomDock.shop-bottom-dock .shop-dock-primary {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            padding: 0 !important;
            gap: 4px !important;
        }

        /* ---- Item de base ---- */
        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini {
            background: transparent !important;
            border: none !important;
            border-radius: 16px !important;
            padding: 9px 6px !important;
            min-height: auto !important;
            gap: 5px !important;
            color: var(--gris-moyen, #737373) !important;
            box-shadow: none !important;
            transition: background 0.2s, transform 0.15s !important;
        }

        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini:active {
            transform: scale(0.92) !important;
        }

        /* ---- Icône squircle ---- */
        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__icon {
            width: 42px !important;
            height: 42px !important;
            min-width: 42px !important;
            min-height: 42px !important;
            max-width: 42px !important;
            max-height: 42px !important;
            border-radius: 14px !important;
            font-size: 1.12rem !important;
            background: #f0f4fa !important;
            color: var(--gris-moyen, #737373) !important;
            border: none !important;
            transition: background 0.22s, color 0.22s, box-shadow 0.22s, transform 0.22s !important;
        }

        /* ---- Couleurs squircles par position ---- */
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(1) .menu-item__icon {
            background: rgba(53,100,166,0.1) !important;
            color: #3564a6 !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(2) .menu-item__icon {
            background: rgba(255,107,53,0.1) !important;
            color: #FF6B35 !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(3) .menu-item__icon {
            background: rgba(53,100,166,0.1) !important;
            color: #3564a6 !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(4) .menu-item__icon {
            background: rgba(99,102,241,0.1) !important;
            color: #6366f1 !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(5) .menu-item__icon {
            background: rgba(53,100,166,0.1) !important;
            color: #3564a6 !important;
        }

        /* ---- Texte label ---- */
        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__text {
            font-size: 0.62rem !important;
            font-weight: 600 !important;
            color: var(--gris-moyen, #737373) !important;
            margin-top: 0 !important;
            padding: 0 2px !important;
            letter-spacing: 0.01em !important;
            transition: color 0.2s !important;
        }

        /* ---- État ACTIF — tuile bleu pâle + icône pleine couleur ---- */
        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini.active {
            background: rgba(53,100,166,0.08) !important;
            border: none !important;
            box-shadow: none !important;
        }

        #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini.active .menu-item__text {
            color: var(--bleu-principal, #3564a6) !important;
            font-weight: 700 !important;
        }

        /* Squircle actif avec couleur pleine + glow */
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(1).active .menu-item__icon {
            background: var(--udock-c1) !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(53,100,166,0.4) !important;
            transform: scale(1.08) !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(2).active .menu-item__icon {
            background: var(--udock-c2) !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(255,107,53,0.4) !important;
            transform: scale(1.08) !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(3).active .menu-item__icon {
            background: var(--udock-c3) !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(53,100,166,0.4) !important;
            transform: scale(1.08) !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(4).active .menu-item__icon {
            background: var(--udock-c4) !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(99,102,241,0.4) !important;
            transform: scale(1.08) !important;
        }
        #userBottomDock.shop-bottom-dock .shop-dock-primary > .menu-item:nth-child(5).active .menu-item__icon {
            background: var(--udock-c5) !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(53,100,166,0.4) !important;
            transform: scale(1.08) !important;
        }

        /* ---- Badge commandes actives ---- */
        #userBottomDock.shop-bottom-dock .menu-item__badge--dock {
            background: var(--orange, #FF6B35) !important;
            border: 2px solid #fff !important;
            font-size: 0.6rem !important;
            font-weight: 800 !important;
            min-width: 1.1rem !important;
            height: 1.1rem !important;
            line-height: 1.1rem !important;
            top: 4px !important;
            right: 8px !important;
            padding: 0 4px !important;
        }

        /* ---- Point hint sur bouton Menu ---- */
        #userBottomDock.shop-bottom-dock
            button.menu-item.menu-item--dock-mini.menu-item--dock-mini-btn--hint
            .menu-item__icon--hint-host::after {
            background: var(--orange, #FF6B35) !important;
            border: 2px solid #fff !important;
            width: 8px !important;
            height: 8px !important;
            top: -3px !important;
            right: -3px !important;
        }

        /* ---- Mobile strict (< 600px) ---- */
        @media (max-width: 599px) {
            #userBottomDock.shop-bottom-dock .shop-dock-bar {
                padding: 6px 6px calc(env(safe-area-inset-bottom,0px) + 6px) !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini {
                padding: 7px 4px !important;
                border-radius: 13px !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__icon {
                width: 36px !important;
                height: 36px !important;
                min-width: 36px !important;
                min-height: 36px !important;
                max-width: 36px !important;
                max-height: 36px !important;
                border-radius: 11px !important;
                font-size: 1rem !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__text {
                font-size: 0.56rem !important;
            }
        }

        /* ---- Tablette (600–1024px) ---- */
        @media (min-width: 600px) and (max-width: 1024px) {
            #userBottomDock.shop-bottom-dock .shop-dock-bar {
                padding: 10px 20px calc(env(safe-area-inset-bottom,0px) + 10px) !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini {
                padding: 10px 10px !important;
                border-radius: 18px !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__icon {
                width: 48px !important;
                height: 48px !important;
                min-width: 48px !important;
                min-height: 48px !important;
                max-width: 48px !important;
                max-height: 48px !important;
                border-radius: 16px !important;
                font-size: 1.2rem !important;
            }
            #userBottomDock.shop-bottom-dock .menu-item.menu-item--dock-mini .menu-item__text {
                font-size: 0.7rem !important;
            }
        }

        /* ================================================================
           PANEL SHEET "Menu" client — redesign premium
           ================================================================ */

        .user-dock-menu-panel {
            background: #fff !important;
            border-radius: 28px 28px 0 0 !important;
            border: none !important;
            box-shadow: 0 -20px 60px rgba(10,15,40,0.22), 0 -1px 0 rgba(255,255,255,0.9) !important;
        }

        .user-dock-menu-panel-hd {
            background: linear-gradient(135deg, var(--bleu-fonce, #2d5690) 0%, var(--bleu-principal, #3564a6) 100%) !important;
            padding: 20px 20px 16px !important;
            border-radius: 28px 28px 0 0 !important;
        }

        .user-dock-menu-panel-hd strong {
            color: #fff !important;
            font-size: 1.02rem !important;
            font-weight: 800 !important;
            font-family: var(--font-titres, 'Poppins', sans-serif) !important;
        }

        .user-dock-menu-close {
            background: rgba(255,255,255,0.18) !important;
            color: #fff !important;
            border-radius: 12px !important;
            border: 1px solid rgba(255,255,255,0.22) !important;
            width: 38px !important;
            height: 38px !important;
        }

        .user-dock-menu-close:hover {
            background: rgba(255,255,255,0.3) !important;
        }

        /* Items du panel en grille 2 colonnes */
        .user-dock-menu-panel-nav {
            padding: 18px 16px !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 10px !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row {
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            gap: 9px !important;
            padding: 18px 12px !important;
            min-height: 92px !important;
            border-radius: 16px !important;
            border: 1.5px solid rgba(53,100,166,0.1) !important;
            background: #f8faff !important;
            text-decoration: none !important;
            transition: background 0.2s, border-color 0.2s, transform 0.15s !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:hover {
            background: rgba(53,100,166,0.07) !important;
            border-color: rgba(53,100,166,0.22) !important;
            transform: translateY(-2px) !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:active {
            transform: scale(0.96) !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row .menu-item-icon {
            width: 46px !important;
            height: 46px !important;
            border-radius: 14px !important;
            font-size: 1.1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            border: none !important;
            background: rgba(53,100,166,0.1) !important;
            color: #3564a6 !important;
            transition: background 0.2s, color 0.2s !important;
        }

        /* Couleurs icônes dans le panel selon position */
        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:nth-child(1) .menu-item-icon { background: rgba(53,100,166,0.1) !important; color: #3564a6 !important; }
        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:nth-child(2) .menu-item-icon { background: rgba(255,107,53,0.1) !important; color: #FF6B35 !important; }
        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:nth-child(3) .menu-item-icon { background: rgba(16,185,129,0.1) !important; color: #059669 !important; }
        .user-dock-menu-panel-nav .menu-item--dock-sheet-row:nth-child(4) .menu-item-icon { background: rgba(99,102,241,0.1) !important; color: #6366f1 !important; }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row .menu-item-text {
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            color: var(--titres, #0d0d0d) !important;
            white-space: nowrap !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-row.active {
            background: rgba(53,100,166,0.07) !important;
            border-color: rgba(53,100,166,0.25) !important;
        }

        .user-dock-menu-divider {
            grid-column: 1 / -1 !important;
            height: 1px !important;
            background: rgba(53,100,166,0.08) !important;
            margin: 2px 0 !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-logout .menu-item-icon {
            background: rgba(239,68,68,0.1) !important;
            color: #b91c1c !important;
        }

        .user-dock-menu-panel-nav .menu-item--dock-sheet-logout:hover {
            background: rgba(239,68,68,0.06) !important;
            border-color: rgba(239,68,68,0.18) !important;
        }

        /* Panel tablette : 3 colonnes */
        @media (min-width: 600px) and (max-width: 1024px) {
            .user-dock-menu-panel-nav {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

    } /* fin @media ≤ 1024px */

    </style>

    <script>
        (function () {
            function userDockQs() {
                return {
                    dockBar: document.getElementById('userDockBar'),
                    menuLayer: document.getElementById('userDockMenuLayer'),
                    backdrop: document.getElementById('userDockMenuBackdrop'),
                    btn: document.getElementById('userDockMenuBtn'),
                    closeBtn: document.getElementById('userDockMenuClose'),
                    panel: document.getElementById('userDockMenuPanel'),
                };
            }
            function isDockMq() {
                return window.matchMedia('(max-width: 1024px)').matches;
            }
            function openDockMenu() {
                var q = userDockQs();
                if (!q.menuLayer || !q.btn || !isDockMq()) return;
                q.menuLayer.classList.add('is-open');
                q.menuLayer.setAttribute('aria-hidden', 'false');
                q.btn.setAttribute('aria-expanded', 'true');
                document.documentElement.style.overflow = 'hidden';
            }
            function shutDockMenu() {
                var q = userDockQs();
                if (!q.menuLayer || !q.btn) return;
                q.menuLayer.classList.remove('is-open');
                q.menuLayer.setAttribute('aria-hidden', 'true');
                q.btn.setAttribute('aria-expanded', 'false');
                document.documentElement.style.overflow = '';
            }
            function toggleDockDockBarVisibility() {
                var q = userDockQs();
                if (!q.dockBar) return;
                if (isDockMq()) {
                    q.dockBar.removeAttribute('hidden');
                } else {
                    q.dockBar.setAttribute('hidden', '');
                    shutDockMenu();
                }
            }
            document.addEventListener('DOMContentLoaded', function () {
                toggleDockDockBarVisibility();
                var q = userDockQs();
                if (q.btn) q.btn.addEventListener('click', openDockMenu);
                if (q.backdrop) q.backdrop.addEventListener('click', shutDockMenu);
                if (q.closeBtn) q.closeBtn.addEventListener('click', shutDockMenu);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') shutDockMenu();
                });
                window.addEventListener('resize', function () {
                    toggleDockDockBarVisibility();
                });
            });
        })();
    </script>

    <!-- Contenu principal -->
    <main class="user-content" id="userContent">
