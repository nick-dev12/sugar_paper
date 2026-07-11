<?php
/**
 * Initialise et affiche la popup de notation produits (espace client).
 * À inclure depuis user_footer.php — programmation procédurale uniquement.
 */
if (empty($_SESSION['user_id'])) {
    return;
}

if (!isset($pr_pending_items) || !isset($pr_show_popup)) {
    $pr_pending_items = [];
    $pr_show_popup = false;
    $pr_auto_open_commande = isset($pr_auto_open_commande) ? (int) $pr_auto_open_commande : 0;
    if (file_exists(__DIR__ . '/../../models/model_produits_avis.php')) {
        require_once __DIR__ . '/../../models/model_produits_avis.php';
        if (function_exists('produits_avis_get_pending_for_user')) {
            $pr_pending_items = produits_avis_get_pending_for_user((int) $_SESSION['user_id'], 12);
        }
        if ($pr_auto_open_commande <= 0 && isset($_GET['noter'])) {
            $pr_auto_open_commande = (int) $_GET['noter'];
        }
        if ($pr_auto_open_commande > 0 && function_exists('produits_avis_commande_a_noter')) {
            if (produits_avis_commande_a_noter((int) $_SESSION['user_id'], $pr_auto_open_commande)) {
                $pr_show_popup = true;
            }
        } elseif (function_exists('produits_avis_should_show_popup')) {
            $pr_show_popup = produits_avis_should_show_popup((int) $_SESSION['user_id']);
        }
    }
}

if (empty($pr_pending_items) || !is_array($pr_pending_items)) {
    return;
}

require_once __DIR__ . '/../../includes/asset_version.php';
require __DIR__ . '/../../includes/partials/product_rating_popup.php';
?>
<script src="/js/product-rating-popup.js<?php echo asset_version_query(); ?>" defer></script>
