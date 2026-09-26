<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page de liste des produits
 * Programmation procédurale uniquement
 */

session_start_persistent();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer tous les produits (filtre temps réel côté JS)
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
require_once __DIR__ . '/../../includes/produit_share.php';
require_once __DIR__ . '/../../includes/produit_recherche_fuzzy.php';
$produits = get_all_produits() ?: [];
$categories = get_all_categories();
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$total_produits = count($produits);
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
    <link rel="stylesheet" href="/css/admin-produits-index.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../../includes/platform_share_head.php'; ?>
</head>

<body class="page-produits-index">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-box"></i> Liste des Produits</h1>
        <div class="header-actions">
            <?php include __DIR__ . '/../includes/btn_retour_site.php'; ?>
            <a href="ajouter.php" class="btn-primary">
                <i class="fas fa-upload"></i> Publier un produit
            </a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Tous les Produits (<span id="produits-count-visible"><?php echo $total_produits; ?></span><span id="produits-count-total-wrap"<?php echo $recherche !== '' || $categorie_id > 0 ? '' : ' hidden'; ?>> / <span id="produits-count-total"><?php echo $total_produits; ?></span></span>)</h2>
        </div>

        <form method="GET" action="" class="admin-filters-bar admin-filters-bar--produits" id="form-filtre-produits">
            <div class="admin-filters-fields-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label for="recherche">Recherche</label>
                    <input type="search" id="recherche" name="recherche" placeholder="Nom du produit…"
                        value="<?php echo htmlspecialchars($recherche); ?>" autocomplete="off" inputmode="search">
                </div>
                <div class="admin-filter-field admin-filter-field--categorie">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="0">Toutes</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo (int) $categorie['id']; ?>"
                                <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categorie['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <a href="index.php" class="btn-filter-reset" id="btn-reset-filtre-produits">
                    <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                </a>
            </div>
        </form>

        <?php if (empty($produits)): ?>
            <div class="empty-state" id="produits-empty-none">
                <i class="fas fa-box-open"></i>
                <p>Aucun produit enregistré pour le moment.</p>
                <a href="ajouter.php" class="btn-primary">
                    <i class="fas fa-upload"></i> Publier le premier produit
                </a>
            </div>
        <?php else: ?>
            <div class="empty-state" id="produits-empty-filter" hidden>
                <i class="fas fa-search"></i>
                <p>Aucun produit ne correspond à votre recherche.</p>
            </div>
            <div class="produits-grid" id="produits-grid">
                <?php foreach ($produits as $produit): ?>
                    <?php
                    $nom = (string) ($produit['nom'] ?? '');
                    $cat_nom = (string) ($produit['categorie_nom'] ?? 'Sans catégorie');
                    $search_norm = produit_recherche_normalize($nom . ' ' . $cat_nom);
                    $statut_class = 'statut-actif';
                    if (($produit['statut'] ?? '') == 'inactif') {
                        $statut_class = 'statut-inactif';
                    } elseif (($produit['statut'] ?? '') == 'rupture_stock') {
                        $statut_class = 'statut-rupture';
                    }
                    $statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
                    ?>
                    <div class="produit-card produit-card-linkable"
                        data-href="ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>"
                        data-nom="<?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?>"
                        data-categorie-id="<?php echo (int) ($produit['categorie_id'] ?? 0); ?>"
                        data-search="<?php echo htmlspecialchars($search_norm, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo produit_share_button_html($produit); ?>
                        <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                        <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                            alt="<?php echo htmlspecialchars($nom); ?>" class="produit-card-image"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars($nom); ?></h3>
                            <p class="produit-card-categorie">
                                <?php echo htmlspecialchars($cat_nom); ?>
                            </p>
                            <p class="produit-card-prix">
                                <?php echo number_format((float) $produit['prix'], 0, ',', ' '); ?>
                                <span class="prix-unite">FCFA</span>
                                <?php if (!empty($produit['prix_promotion'])): ?>
                                    <span class="prix-promo">
                                        (Promo: <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock">
                                Stock: <span class="stock-value"><?php echo (int) $produit['stock']; ?></span>
                            </p>
                            <div class="produit-card-actions">
                                <a href="modifier.php?id=<?php echo (int) $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="supprimer.php?id=<?php echo (int) $produit['id']; ?>" class="btn-card btn-delete"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                    <i class="fas fa-trash"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            var form = document.getElementById('form-filtre-produits');
            var input = document.getElementById('recherche');
            var select = document.getElementById('categorie_id');
            var grid = document.getElementById('produits-grid');
            var emptyFilter = document.getElementById('produits-empty-filter');
            var countVisible = document.getElementById('produits-count-visible');
            var countTotalWrap = document.getElementById('produits-count-total-wrap');
            var countTotal = document.getElementById('produits-count-total');
            if (!form || !input || !select || !grid) {
                return;
            }

            function normalize(text) {
                var s = String(text || '').toLowerCase().trim();
                try {
                    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                } catch (e) {}
                return s.replace(/[^a-z0-9\s\-]/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function scoreMatch(query, haystack) {
                var q = normalize(query);
                var h = normalize(haystack);
                if (!q) {
                    return 1;
                }
                if (!h) {
                    return 0;
                }
                if (h === q) {
                    return 1000;
                }
                if (h.indexOf(q) === 0) {
                    return 850;
                }
                if (h.indexOf(q) !== -1) {
                    return 700;
                }
                var words = q.split(' ').filter(function(w) {
                    return w.length >= 2 && !/^\d+$/.test(w);
                });
                if (!words.length) {
                    return 0;
                }
                var tokens = h.split(' ').filter(function(t) {
                    return t.length >= 2 && !/^\d+$/.test(t);
                });
                var score = 0;
                var matched = 0;
                for (var i = 0; i < words.length; i++) {
                    var w = words[i];
                    var best = 0;
                    if (h.indexOf(w) !== -1) {
                        best = 120;
                    } else {
                        for (var t = 0; t < tokens.length; t++) {
                            var tok = tokens[t];
                            if (tok === w) {
                                best = Math.max(best, 120);
                                continue;
                            }
                            if (w.length >= 3 && tok.indexOf(w) === 0) {
                                best = Math.max(best, 110);
                                continue;
                            }
                            if (w.length >= 4 && tok.length >= 4) {
                                if (tok.indexOf(w) !== -1 || w.indexOf(tok) !== -1) {
                                    best = Math.max(best, 100);
                                    continue;
                                }
                                var dist = levenshtein(w, tok);
                                var maxDist = tok.length <= 5 ? 1 : (tok.length <= 8 ? 2 : 3);
                                if (dist <= maxDist) {
                                    best = Math.max(best, 90);
                                }
                            }
                        }
                    }
                    if (best > 0) {
                        matched++;
                        score += best;
                    }
                }
                if (matched < words.length) {
                    return 0;
                }
                return score;
            }

            function levenshtein(a, b) {
                var m = a.length;
                var n = b.length;
                if (Math.abs(m - n) > 3) {
                    return 99;
                }
                var row = [];
                var i, j, prev, tmp;
                for (j = 0; j <= n; j++) {
                    row[j] = j;
                }
                for (i = 1; i <= m; i++) {
                    prev = i - 1;
                    row[0] = i;
                    for (j = 1; j <= n; j++) {
                        tmp = row[j];
                        row[j] = a.charAt(i - 1) === b.charAt(j - 1)
                            ? prev
                            : Math.min(prev + 1, row[j] + 1, row[j - 1] + 1);
                        prev = tmp;
                    }
                }
                return row[n];
            }

            function applyFilter() {
                var q = input.value.trim();
                var cat = parseInt(select.value, 10) || 0;
                var cards = grid.querySelectorAll('.produit-card');
                var visible = 0;
                var total = cards.length;
                var minScore = 90;

                cards.forEach(function(card) {
                    var catOk = !cat || parseInt(card.getAttribute('data-categorie-id') || '0', 10) === cat;
                    var score = 1;
                    if (q) {
                        score = scoreMatch(q, card.getAttribute('data-nom') || '');
                    }
                    var show = catOk && score >= minScore;
                    card.hidden = !show;
                    card.style.display = show ? '' : 'none';
                    if (show) {
                        visible++;
                    }
                });

                if (countVisible) {
                    countVisible.textContent = String(visible);
                }
                if (countTotal) {
                    countTotal.textContent = String(total);
                }
                if (countTotalWrap) {
                    countTotalWrap.hidden = !(q || cat);
                }
                if (emptyFilter) {
                    emptyFilter.hidden = visible > 0;
                }
                grid.hidden = visible === 0;
            }

            var timer = null;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(applyFilter, 120);
            });
            select.addEventListener('change', applyFilter);

            form.addEventListener('submit', function(ev) {
                ev.preventDefault();
                applyFilter();
                var params = new URLSearchParams();
                var q = input.value.trim();
                var cat = parseInt(select.value, 10) || 0;
                if (q) {
                    params.set('recherche', q);
                }
                if (cat > 0) {
                    params.set('categorie_id', String(cat));
                }
                var qs = params.toString();
                var url = window.location.pathname + (qs ? '?' + qs : '');
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', url);
                }
            });

            applyFilter();
        });
    </script>
    <?php include __DIR__ . '/../../includes/platform_share_footer.php'; ?>
