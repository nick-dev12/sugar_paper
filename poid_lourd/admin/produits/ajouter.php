<?php
/**
 * Page d'ajout de produ — formulaire (peut aussi être ouvert depuis la liste en modal).
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_add_produit();

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    $categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
    if ($categorie_id > 0) {
        header('Location: ../categories/produits.php?id=' . $categorie_id);
    } else {
        header('Location: ../stock/index.php');
    }
    exit;
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_genres.php';
$categories = admin_categories_list_for_session();
$__role_add = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__role_add === 'vendeur');
$vendeur_genre_ids_prefill = [];
$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;
$categorie_id_prefill = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
if ($fap_use_category_hierarchy && $categorie_id_prefill > 0) {
    $cp = get_categorie_by_id($categorie_id_prefill);
    if ($cp && function_exists('categorie_est_utilisable_par_vendeur')
        && categorie_est_utilisable_par_vendeur((int) $cp['id'], (int) $_SESSION['admin_id'])) {
        $vcat_prefill_sub = (int) $cp['id'];
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($cp['categorie_generale_id'] ?? 0);
        }
    }
}
$add_produit_modal = false;
$add_produit_form_action = '';
$add_produit_error_message = '';
if (isset($result['message']) && !empty($result['message']) && empty($result['success'])) {
    $add_produit_error_message = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header content-header-form">
        <h1><i class="fas fa-plus"></i> Ajouter un produit</h1>
        <div class="header-actions">
            <?php if ($categorie_id_prefill > 0): ?>
            <a href="../categories/produits.php?id=<?php echo $categorie_id_prefill; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour aux produits
            </a>
            <?php else: ?>
            <a href="../stock/index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour au stock
            </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="form-add-section">
        <div class="form-add-container">
            <?php
            require __DIR__ . '/inc_form_ajouter_produit.php';
            ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
