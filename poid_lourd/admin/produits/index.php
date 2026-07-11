<?php
/**
 * Page de liste des produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
$__role_produits_nav = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
$categories = admin_categories_list_for_session();
$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__role_produits_nav === 'vendeur');
$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;

$add_produit_error_message = '';
$add_produit_post_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['admin_add_produit'])) {
    require_once __DIR__ . '/../../controllers/controller_produits.php';
    $add_result = process_add_produit();
    if (!empty($add_result['success'])) {
        $_SESSION['success_message'] = $add_result['message'];
        header('Location: index.php');
        exit;
    }
    $add_produit_error_message = $add_result['message'] ?? 'Erreur lors de l’ajout.';
    $add_produit_post_error = true;
}

$produits = get_all_produits(null, admin_vendeur_filter_id());
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$categorie_generale_id = isset($_GET['categorie_generale_id']) ? (int) $_GET['categorie_generale_id'] : 0;
$rayon_filtre_nom = '';
if ($categorie_generale_id > 0) {
    $cgf = get_categorie_generale_by_id($categorie_generale_id);
    $rayon_filtre_nom = $cgf && !empty($cgf['nom']) ? (string) $cgf['nom'] : '';
}

$ids_produits_rayon = null;
if ($categorie_generale_id > 0) {
    $prods_rayon = get_produits_by_categorie_generale($categorie_generale_id, admin_vendeur_filter_id());
    $ids_produits_rayon = [];
    foreach ($prods_rayon as $pr) {
        $ids_produits_rayon[(int) ($pr['id'] ?? 0)] = true;
    }
}
$open_add_modal = isset($_GET['open_add']) && $_GET['open_add'] === '1';
$categorie_id_prefill_modal = isset($_GET['prefill_categorie']) ? (int) $_GET['prefill_categorie'] : 0;

if ($fap_use_category_hierarchy && $categorie_id_prefill_modal > 0) {
    $cp = get_categorie_by_id($categorie_id_prefill_modal);
    if ($cp && function_exists('categorie_est_utilisable_par_vendeur')
        && categorie_est_utilisable_par_vendeur((int) $cp['id'], (int) $_SESSION['admin_id'])) {
        $vcat_prefill_sub = (int) $cp['id'];
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($cp['categorie_generale_id'] ?? 0);
        }
    }
}

if (!empty($produits)) {
    $expanded_filter_ids = ($categorie_id > 0 && function_exists('category_expanded_ids_for_products'))
        ? category_expanded_ids_for_products($categorie_id)
        : ($categorie_id > 0 ? [$categorie_id] : []);
    $produits = array_values(array_filter($produits, function ($produit) use ($recherche, $categorie_id, $expanded_filter_ids, $ids_produits_rayon) {
        if ($ids_produits_rayon !== null) {
            $pid = (int) ($produit['id'] ?? 0);
            if ($pid <= 0 || !isset($ids_produits_rayon[$pid])) {
                return false;
            }
        }

        if ($categorie_id > 0 && !in_array((int) ($produit['categorie_id'] ?? 0), $expanded_filter_ids, true)) {
            return false;
        }

        if ($recherche === '') {
            return true;
        }

        // Code interne (3 lettres + 6 chiffres)
        if (produit_identifiant_interne_is_valid_format(strtoupper($recherche))) {
            $code = strtoupper($recherche);
            $ident = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
            return $ident !== '' && $ident === $code;
        }

        // 5 derniers chiffres du numéro (saisie rapide, type caisse supermarché)
        if (preg_match('/^\d{5}$/', $recherche)) {
            $ident = $produit['identifiant_interne'] ?? '';

            return produit_identifiant_derniers_5_chiffres($ident) === $recherche;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($recherche) : strtolower($recherche);
        $haystacks = [
            $produit['nom'] ?? '',
            $produit['description'] ?? '',
            $produit['categorie_nom'] ?? '',
            $produit['statut'] ?? '',
            (string) ($produit['identifiant_interne'] ?? ''),
        ];

        foreach ($haystacks as $value) {
            $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Produits - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .admin-filters-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 12px;
        }

        .admin-filter-field {
            flex: 1 1 220px;
        }

        .admin-filter-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #6b2f20;
        }

        .admin-filter-field input,
        .admin-filter-field select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #d9d9d9;
            border-radius: 10px;
            background: #fff;
        }

        .admin-filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-filter-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #d9d9d9;
            color: #6b2f20;
            background: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .produit-card-linkable {
            cursor: pointer;
        }

        .produit-card-linkable:hover .produit-card-nom {
            color: #c26638;
        }

        /* Modal plein écran — ajout produit */
        .adm-modal-add-produit[hidden] {
            display: none !important;
        }
        .adm-modal-add-produit {
            position: fixed;
            inset: 0;
            z-index: 9990;
            display: flex;
            flex-direction: column;
            background: rgba(13, 13, 13, 0.52);
            backdrop-filter: blur(6px);
        }
        .adm-modal-add-produit-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            margin: 0;
            max-width: none;
            width: 100%;
            align-self: stretch;
            background: linear-gradient(165deg, var(--fond-secondaire, #fafafa) 0%, var(--blanc, #fff) 42%, rgba(53, 100, 166, 0.04) 100%);
            border-radius: 0;
            box-shadow: none;
            border: none;
            border-top: 3px solid var(--couleur-dominante, #3564a6);
            overflow: hidden;
        }
        .adm-modal-add-head {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 22px;
            background: var(--blanc, #fff);
            border-bottom: 1px solid var(--glass-border, rgba(0, 0, 0, 0.08));
        }
        .adm-modal-add-head h2 {
            margin: 0;
            font-size: 1.28rem;
            font-family: var(--font-titres, inherit);
            color: var(--titres, #0d0d0d);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .adm-modal-add-head h2 i {
            color: var(--couleur-dominante, #3564a6);
        }
        .adm-modal-add-head-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .adm-modal-add-close {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 12px;
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }
        .adm-modal-add-close:hover {
            background: var(--couleur-dominante, #3564a6);
            color: var(--texte-clair, #fff);
        }
        .adm-modal-add-body {
            flex: 1;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            padding: 22px 24px 40px;
        }
        .adm-modal-add-body .form-add-container {
            max-width: 1280px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-box"></i> Liste des Produits</h1>
        <div class="header-actions">
            <?php if ($__role_produits_nav === 'vendeur'): ?>
            <a href="../stock/index.php" class="btn-secondary-style" title="Gestion du stock">
                <i class="fas fa-boxes-stacked"></i> Stock
            </a>
            <?php endif; ?>
            <button type="button" class="btn-primary" id="btnOpenAddProduitModal">
                <i class="fas fa-upload"></i> Publier un produit
            </button>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Tous les Produits (<?php echo count($produits); ?>)
                <?php if ($categorie_generale_id > 0 && $rayon_filtre_nom !== ''): ?>
                    <span style="font-size:0.85em;font-weight:500;color:var(--gris-moyen,#737373);"> — Rayon&nbsp;: <?php echo htmlspecialchars($rayon_filtre_nom); ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <form method="GET" action="" class="admin-filters-bar">
            <?php if ($categorie_generale_id > 0): ?>
            <input type="hidden" name="categorie_generale_id" value="<?php echo (int) $categorie_generale_id; ?>">
            <?php endif; ?>
            <div class="admin-filter-field">
                <label for="recherche">Recherche</label>
                <input type="text" id="recherche" name="recherche" placeholder="Nom, FPL000151 ou 5 chiffres (ex. 00151)…"
                    value="<?php echo htmlspecialchars($recherche); ?>"
                    autocomplete="off"
                    inputmode="search">
            </div>
            <div class="admin-filter-field">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo (int) $categorie['id']; ?>"
                            <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <a href="index.php" class="btn-filter-reset">
                    <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                </a>
            </div>
        </form>

        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Aucun produit enregistré pour le moment.</p>
                <button type="button" class="btn-primary" id="btnOpenAddProduitModalEmpty">
                    <i class="fas fa-upload"></i> Publier le premier produit
                </button>
            </div>
        <?php else: ?>
            <div class="produits-grid">
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-card produit-card-linkable"
                        data-href="ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>">
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                        ?>
                        <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                        <div class="produit-card-img">
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-card-image"
                                onerror="this.src='/image/produit1.jpg'">
                        </div>
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                            <p class="produit-card-categorie">
                                <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?>
                            </p>
                            <p class="produit-card-prix">
                                <?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                <span class="prix-unite">FCFA</span>
                                <?php if ($produit['prix_promotion']): ?>
                                    <span class="prix-promo">
                                        (Promo: <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock">
                                Stock: <span class="stock-value"><?php echo $produit['stock']; ?></span>

                            </p>
                            <div class="produit-card-actions">
                                <a href="modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="supprimer.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-delete"
                                    onclick="return confirm('Supprimer ce produit ?\n\nCette action est définitive. Cliquez sur OK pour supprimer ou sur Annuler pour annuler.');">
                                    <i class="fas fa-trash"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
    $add_produit_modal = true;
    $add_produit_form_action = 'index.php';
    $categorie_id_prefill = $categorie_id_prefill_modal;
    $modal_should_show = $add_produit_post_error || $open_add_modal;
    ?>
    <div id="modalAddProduit" class="adm-modal-add-produit" <?php echo $modal_should_show ? '' : 'hidden'; ?> aria-hidden="<?php echo $modal_should_show ? 'false' : 'true'; ?>">
        <div class="adm-modal-add-produit-inner">
            <div class="adm-modal-add-head">
                <h2><i class="fas fa-plus-circle"></i> Publier un produit</h2>
                <div class="adm-modal-add-head-actions">
                    <button type="button" class="adm-modal-add-close" id="btnCloseAddProduitModal" title="Fermer" aria-label="Fermer">&times;</button>
                </div>
            </div>
            <div class="adm-modal-add-body">
                <div class="form-add-container">
                    <?php require __DIR__ . '/inc_form_ajouter_produit.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('modalAddProduit');
            var btnOpen = document.getElementById('btnOpenAddProduitModal');
            var btnOpenEmpty = document.getElementById('btnOpenAddProduitModalEmpty');
            var btnClose = document.getElementById('btnCloseAddProduitModal');
            var btnCancelModal = document.getElementById('btn-fap-cancel-modal');
            function openAddModal() {
                if (!modal) return;
                modal.removeAttribute('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            function closeAddModal() {
                if (!modal) return;
                modal.setAttribute('hidden', '');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            if (btnOpen) btnOpen.addEventListener('click', openAddModal);
            if (btnOpenEmpty) btnOpenEmpty.addEventListener('click', openAddModal);
            if (btnClose) btnClose.addEventListener('click', closeAddModal);
            if (btnCancelModal) btnCancelModal.addEventListener('click', closeAddModal);
            modal && modal.addEventListener('click', function (ev) {
                if (ev.target === modal) closeAddModal();
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
                    var vm = document.getElementById('fapVarianteModal');
                    if (vm && !vm.hidden) return;
                    closeAddModal();
                }
            });
            if (modal && !modal.hasAttribute('hidden')) {
                document.body.style.overflow = 'hidden';
            }

            try {
                var q = new URLSearchParams(window.location.search);
                if (q.get('open_add') === '1') {
                    openAddModal();
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        u.searchParams.delete('open_add');
                        u.searchParams.delete('prefill_categorie');
                        window.history.replaceState({}, '', u.pathname + u.search + u.hash);
                    }
                }
            } catch (e) {}

            document.querySelectorAll('.produit-card-linkable').forEach(function(card) {
                card.addEventListener('click', function(event) {
                    if (event.target.closest('a, button, input, select, textarea, form')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });
        });
    </script>