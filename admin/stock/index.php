<?php
/**
 * Page de gestion du stock (articles en stock)
 * Programmation procédurale uniquement
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Traiter les formulaires
require_once __DIR__ . '/../../controllers/controller_stock.php';
$result = process_add_stock_article();
$result_ajustement = process_ajustement_inventaire();

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}
if (isset($result_ajustement['success']) && $result_ajustement['success']) {
    $_SESSION['success_message'] = $result_ajustement['message'];
    header('Location: index.php');
    exit;
}

// Récupérer les articles en stock
require_once __DIR__ . '/../../models/model_stock.php';
require_once __DIR__ . '/../../models/model_categories.php';
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$articles = get_all_stock_articles($recherche, $categorie_id > 0 ? $categorie_id : null);
$categories = get_all_categories();

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Ouvrir le modal si erreur de soumission
$show_modal_on_load = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($result['success']) && !$result['success']);
$show_modal_ajustement = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustement']) && isset($result_ajustement['success']) && !$result_ajustement['success']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Stock - Administration</title>
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
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #d9d9d9;
            color: #6b2f20;
            background: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .stock-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .stock-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stock-card-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #f5f5f5;
        }

        .stock-card-body {
            padding: 16px;
        }

        .stock-card-nom {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin: 0 0 8px 0;
        }

        .stock-card-categorie {
            font-size: 13px;
            color: #666;
            margin: 0 0 8px 0;
        }

        .stock-card-quantite {
            font-size: 14px;
            color: #6b2f20;
            font-weight: 600;
            margin: 0 0 10px 0;
        }

        .btn-ajuster-stock {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: 13px;
            background: #918a44;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-ajuster-stock:hover {
            background: #7a7340;
            color: #fff;
        }
        .btn-voir-produits {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: 13px;
            background: var(--couleur-dominante, #E5488A);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-voir-produits:hover {
            opacity: 0.9;
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 16px;
        }

        .empty-state p {
            margin-bottom: 20px;
            color: #666;
            margin-bottom: 20px;
        }

        /* Modal plein écran */
        .modal-fullscreen {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-fullscreen.show {
            display: flex;
        }

        .modal-fullscreen-content {
            background: #fff;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-fullscreen-header {
            padding: 20px 24px;
            border-bottom: 1px solid #ececec;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-fullscreen-header h2 {
            margin: 0;
            font-size: 20px;
            color: #6b2f20;
        }

        .modal-close-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: #f5f5f5;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }

        .modal-close-btn:hover {
            background: #eee;
            color: #000;
        }

        .modal-fullscreen-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #6b2f20;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            font-size: 15px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #918a44;
        }

        .file-input-stock {
            width: 100%;
            padding: 12px;
            border: 2px dashed #d9d9d9;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .file-input-stock:hover {
            border-color: #918a44;
            background: #fafafa;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #ececec;
        }

        .modal-actions .btn-primary {
            flex: 1;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-boxes-stacked"></i> Gestion du Stock</h1>
        <div class="header-actions">
            <a href="../categories/index.php" class="btn-filter-reset" style="margin-right: 10px;">
                <i class="fas fa-folder"></i> Catégories
            </a>
            <a href="mouvements.php" class="btn-filter-reset" style="margin-right: 10px;">
                <i class="fas fa-history"></i> Historique des mouvements
            </a>
            <button type="button" class="btn-primary" id="btn-add-article">
                <i class="fas fa-plus"></i> Ajouter un article
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
            <h2><i class="fas fa-boxes-stacked"></i> Articles en stock (<?php echo count($articles); ?>)</h2>
        </div>

        <form method="GET" action="" class="admin-filters-bar">
            <div class="admin-filter-field">
                <label for="recherche">Recherche</label>
                <input type="text" id="recherche" name="recherche" placeholder="Nom de l'article..."
                    value="<?php echo htmlspecialchars($recherche); ?>">
            </div>
            <div class="admin-filter-field">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                <a href="index.php" class="btn-filter-reset"><i class="fas fa-rotate-left"></i> Réinitialiser</a>
            </div>
        </form>

        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <i class="fas fa-boxes-stacked"></i>
                <p>Aucun article en stock. Ajoutez des articles pour pouvoir les publier comme produits.</p>
                <button type="button" class="btn-primary" id="btn-add-article-empty">
                    <i class="fas fa-plus"></i> Ajouter un article
                </button>
            </div>
        <?php else: ?>
            <div class="stock-grid">
                <?php foreach ($articles as $art): ?>
                    <div class="stock-card">
                        <img src="/upload/<?php echo htmlspecialchars($art['image_principale'] ?? ''); ?>"
                            alt="<?php echo htmlspecialchars($art['nom']); ?>" class="stock-card-image"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="stock-card-body">
                            <h3 class="stock-card-nom"><?php echo htmlspecialchars($art['nom']); ?></h3>
                            <p class="stock-card-categorie"><?php echo htmlspecialchars($art['categorie_nom'] ?? ''); ?></p>
                            <p class="stock-card-quantite">Stock: <?php echo (int) $art['quantite']; ?></p>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="produits.php?id=<?php echo (int) $art['id']; ?>" class="btn-voir-produits" title="Voir les produits liés">
                                    <i class="fas fa-box-open"></i> Produits
                                </a>
                                <button type="button" class="btn-ajuster-stock" data-id="<?php echo (int) $art['id']; ?>"
                                    data-nom="<?php echo htmlspecialchars($art['nom']); ?>"
                                    data-quantite="<?php echo (int) $art['quantite']; ?>"
                                    data-image="<?php echo htmlspecialchars($art['image_principale'] ?? ''); ?>">
                                    <i class="fas fa-edit"></i> Ajuster
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Modal plein écran : Ajouter un article -->
    <div class="modal-fullscreen" id="modal-add-article">
        <div class="modal-fullscreen-content">
            <div class="modal-fullscreen-header">
                <h2><i class="fas fa-plus-circle"></i> Ajouter un article au stock</h2>
                <button type="button" class="modal-close-btn" id="modal-close-btn" aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-fullscreen-body">
                <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $result['message']; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="form-add-article">
                    <div class="form-group">
                        <label for="nom">Nom de l'article <span style="color:#c00;">*</span></label>
                        <input type="text" id="nom" name="nom" required placeholder="Ex: Miel naturel pur"
                            value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="image_principale">Image principale <span style="color:#c00;">*</span></label>
                        <input type="file" id="image_principale" name="image_principale" accept="image/*" required
                            class="file-input-stock">
                    </div>
                    <div class="form-group">
                        <label for="quantite">Quantité en stock <span style="color:#c00;">*</span></label>
                        <input type="number" id="quantite" name="quantite" min="0" required placeholder="0"
                            value="<?php echo isset($_POST['quantite']) ? (int) $_POST['quantite'] : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="categorie_id">Catégorie <span style="color:#c00;">*</span></label>
                        <select id="categorie_id" name="categorie_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo (isset($_POST['categorie_id']) && $_POST['categorie_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <button type="button" class="btn-cancel" id="modal-cancel-btn">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ajustement inventaire -->
    <div class="modal-fullscreen" id="modal-ajustement">
        <div class="modal-fullscreen-content" style="max-width: 560px;">
            <div class="modal-fullscreen-header">
                <h2><i class="fas fa-clipboard-list"></i> Modifier l'article</h2>
                <button type="button" class="modal-close-btn" id="modal-ajustement-close"
                    aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-fullscreen-body">
                <?php if (isset($result_ajustement['message']) && !empty($result_ajustement['message']) && !$result_ajustement['success']): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $result_ajustement['message']; ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST" action="" id="form-ajustement" enctype="multipart/form-data">
                    <input type="hidden" name="ajustement" value="1">
                    <input type="hidden" name="stock_article_id" id="ajustement_article_id" value="">
                    <input type="hidden" name="quantite_actuelle" id="quantite_actuelle_hidden" value="">

                    <div class="form-group">
                        <label for="ajustement_nom">Nom de l'article <span style="color:var(--accent-promo);">*</span></label>
                        <input type="text" id="ajustement_nom" name="ajustement_nom" required placeholder="Nom de l'article">
                    </div>

                    <div class="form-group">
                        <label>Image actuelle</label>
                        <div id="ajustement_image_preview_wrap" style="margin-bottom:10px; display:none;">
                            <img id="ajustement_image_preview" src="" alt="Aperçu"
                                style="width:100%; max-height:180px; object-fit:cover; border-radius:8px; border:1px solid #e0e0e0;">
                        </div>
                        <label for="ajustement_image" style="font-weight:500; color:#555; font-size:13px; margin-bottom:6px; display:block;">
                            Changer l'image <span style="font-weight:400; color:#888;">(optionnel — laissez vide pour conserver)</span>
                        </label>
                        <input type="file" id="ajustement_image" name="ajustement_image" accept="image/*"
                            class="file-input-stock">
                        <div id="ajustement_new_preview_wrap" style="margin-top:10px; display:none;">
                            <p style="font-size:12px; color:#555; margin-bottom:4px;">Nouvelle image :</p>
                            <img id="ajustement_new_preview" src="" alt="Nouvelle image"
                                style="width:100%; max-height:180px; object-fit:cover; border-radius:8px; border:2px solid var(--couleur-dominante,#E5488A);">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quantite_actuelle">Quantité actuelle</label>
                        <input type="number" id="quantite_actuelle" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label for="nouvelle_quantite">Nouvelle quantité <span style="color:var(--accent-promo);">*</span></label>
                        <input type="number" id="nouvelle_quantite" name="nouvelle_quantite" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="notes_ajustement">Notes (optionnel)</label>
                        <input type="text" id="notes_ajustement" name="notes" placeholder="Ex: Inventaire physique">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        <button type="button" class="btn-cancel" id="modal-ajustement-cancel">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('modal-add-article');
            var btnAdd = document.getElementById('btn-add-article');
            var btnAddEmpty = document.getElementById('btn-add-article-empty');
            var btnClose = document.getElementById('modal-close-btn');
            var btnCancel = document.getElementById('modal-cancel-btn');

            function openModal() {
                if (modal) modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            <?php if ($show_modal_on_load): ?>
                document.addEventListener('DOMContentLoaded', function () { openModal(); });
            <?php endif; ?>

            var modalAjustement = document.getElementById('modal-ajustement');
            var btnAjuster = document.querySelectorAll('.btn-ajuster-stock');
            var btnCloseAjust = document.getElementById('modal-ajustement-close');
            var btnCancelAjust = document.getElementById('modal-ajustement-cancel');
            function openModalAjustement(id, nom, quantite, image) {
                document.getElementById('ajustement_article_id').value = id;
                document.getElementById('ajustement_nom').value = nom;
                document.getElementById('quantite_actuelle').value = quantite;
                document.getElementById('quantite_actuelle_hidden').value = quantite;
                document.getElementById('nouvelle_quantite').value = quantite;
                document.getElementById('notes_ajustement').value = '';
                // Réinitialiser le champ image
                var fileInput = document.getElementById('ajustement_image');
                if (fileInput) fileInput.value = '';
                // Prévisualisation image actuelle
                var prevWrap = document.getElementById('ajustement_image_preview_wrap');
                var prevImg = document.getElementById('ajustement_image_preview');
                var newPrevWrap = document.getElementById('ajustement_new_preview_wrap');
                var newPrevImg = document.getElementById('ajustement_new_preview');
                if (newPrevWrap) newPrevWrap.style.display = 'none';
                if (newPrevImg) newPrevImg.src = '';
                if (image && prevImg) {
                    prevImg.src = '/upload/' + image;
                    prevWrap.style.display = 'block';
                    prevImg.onerror = function() { this.src = '/image/produit1.jpg'; };
                } else if (prevWrap) {
                    prevWrap.style.display = 'none';
                }
                if (modalAjustement) modalAjustement.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            function closeModalAjustement() {
                if (modalAjustement) modalAjustement.classList.remove('show');
                document.body.style.overflow = '';
            }
            btnAjuster.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModalAjustement(btn.dataset.id, btn.dataset.nom, btn.dataset.quantite, btn.dataset.image);
                });
            });
            // Prévisualisation de la nouvelle image sélectionnée
            var fileInput = document.getElementById('ajustement_image');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    var newPrevWrap = document.getElementById('ajustement_new_preview_wrap');
                    var newPrevImg = document.getElementById('ajustement_new_preview');
                    if (this.files && this.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            newPrevImg.src = e.target.result;
                            newPrevWrap.style.display = 'block';
                        };
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        newPrevWrap.style.display = 'none';
                        newPrevImg.src = '';
                    }
                });
            }
            if (btnCloseAjust) btnCloseAjust.addEventListener('click', closeModalAjustement);
            if (btnCancelAjust) btnCancelAjust.addEventListener('click', closeModalAjustement);
            if (modalAjustement) modalAjustement.addEventListener('click', function (e) { if (e.target === modalAjustement) closeModalAjustement(); });
            <?php if ($show_modal_ajustement): ?>
                document.addEventListener('DOMContentLoaded', function () {
                    var id = '<?php echo (int) ($_POST["stock_article_id"] ?? 0); ?>';
                    var nom = <?php echo json_encode($_POST["ajustement_nom"] ?? ""); ?>;
                    var qty = '<?php echo (int) ($_POST["quantite_actuelle"] ?? 0); ?>';
                    var img = <?php
                        $aj_id = (int)($_POST["stock_article_id"] ?? 0);
                        if ($aj_id > 0) {
                            $aj_art = get_stock_article_by_id($aj_id);
                            echo json_encode($aj_art ? ($aj_art['image_principale'] ?? '') : '');
                        } else { echo '""'; }
                    ?>;
                    if (id) openModalAjustement(id, nom, qty, img);
                });
            <?php endif; ?>
            function closeModal() {
                if (modal) modal.classList.remove('show');
                document.body.style.overflow = '';
            }

            if (btnAdd) btnAdd.addEventListener('click', openModal);
            if (btnAddEmpty) btnAddEmpty.addEventListener('click', openModal);
            if (btnClose) btnClose.addEventListener('click', closeModal);
            if (btnCancel) btnCancel.addEventListener('click', closeModal);

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('show')) closeModal();
            });
        })();
    </script>
    <?php include '../includes/footer.php'; ?>