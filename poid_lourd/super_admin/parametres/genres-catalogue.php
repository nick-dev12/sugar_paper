<?php
/**
 * Genres produits — indépendants des rayons ; les vendeurs les cochent à la publication.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_genres.php';
require_once dirname(__DIR__, 2) . '/models/model_categories.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_categories.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';
$upload_root = dirname(__DIR__, 2) . '/upload/';

/**
 * @return int[]
 */
function sa_genre_collect_post_cg_ids() {
    $raw = $_POST['g_categorie_generale_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $x) {
        $i = (int) $x;
        if ($i > 0) {
            $out[$i] = true;
        }
    }
    return array_keys($out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!genres_table_exists()) {
        $flash_err = 'Table genres absente. Exécutez la migration : php migrations/run_migrate_genres.php';
    } else {
        if (isset($_POST['create_genre'])) {
            $nom = trim((string) ($_POST['g_nom'] ?? ''));
            $d = trim((string) ($_POST['g_description'] ?? ''));
            $so = (int) ($_POST['g_sort'] ?? 0);
            $cg_ids = sa_genre_collect_post_cg_ids();
            $img = null;
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                $up = upload_genre_image($_FILES);
                if ($up) {
                    $img = $up;
                } else {
                    $flash_err = 'Image non valide (format JPEG, PNG, GIF, WebP — max. 20 Mo).';
                }
            }
            if ($flash_err === '') {
                if ($nom === '') {
                    $flash_err = 'Le nom du genre est obligatoire.';
                } elseif (!genres_cg_links_table_exists()) {
                    $flash_err = 'Migration requise : php migrations/run_migrate_genres_categories_generales.php';
                } elseif (empty($cg_ids)) {
                    $flash_err = 'Cochez au moins une catégorie principale (rayon) pour ce genre.';
                } else {
                    foreach ($cg_ids as $cid) {
                        if (!get_categorie_generale_by_id($cid)) {
                            $flash_err = 'Une ou plusieurs catégories principales sont invalides.';
                            break;
                        }
                    }
                }
                if ($flash_err === '') {
                    $id = genre_insert_row($nom, $d !== '' ? $d : null, $img, $so);
                    if ($id) {
                        if (!save_genre_categorie_generale_links($id, $cg_ids)) {
                            genre_delete_row($id);
                            $flash_err = 'Erreur lors de l’enregistrement des catégories liées.';
                        } else {
                            super_admin_log_action($sa_id, 'genre_cree', 'genres', $id, $nom);
                            http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
                        }
                    } else {
                        $flash_err = 'Impossible d’ajouter le genre (nom déjà utilisé ou erreur).';
                    }
                }
            }
        } elseif (isset($_POST['update_genre'])) {
            $id = (int) ($_POST['g_id'] ?? 0);
            $nom = trim((string) ($_POST['g_nom'] ?? ''));
            $d = trim((string) ($_POST['g_description'] ?? ''));
            $so = (int) ($_POST['g_sort'] ?? 0);
            $cg_ids = sa_genre_collect_post_cg_ids();
            $row = $id > 0 ? get_genre_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Genre introuvable.';
            } else {
                $img = $row['image'] ?? null;
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_genre_image($_FILES);
                    if ($up) {
                        if (!empty($row['image']) && is_file($upload_root . str_replace('\\', '/', (string) $row['image']))) {
                            @unlink($upload_root . str_replace('\\', '/', (string) $row['image']));
                        }
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide (format JPEG, PNG, GIF, WebP — max. 20 Mo).';
                    }
                }
                if ($flash_err === '') {
                    if ($nom === '') {
                        $flash_err = 'Le nom du genre est obligatoire.';
                    } elseif (!genres_cg_links_table_exists()) {
                        $flash_err = 'Migration requise : php migrations/run_migrate_genres_categories_generales.php';
                    } elseif (empty($cg_ids)) {
                        $flash_err = 'Cochez au moins une catégorie principale (rayon) pour ce genre.';
                    } else {
                        foreach ($cg_ids as $cid) {
                            if (!get_categorie_generale_by_id($cid)) {
                                $flash_err = 'Une ou plusieurs catégories principales sont invalides.';
                                break;
                            }
                        }
                    }
                }
                if ($flash_err === '' && genre_update_row($id, $nom, $d !== '' ? $d : null, $img, $so)) {
                    if (!save_genre_categorie_generale_links($id, $cg_ids)) {
                        $flash_err = 'Le genre a été mis à jour mais l’enregistrement des catégories liées a échoué.';
                    } else {
                        super_admin_log_action($sa_id, 'genre_modifie', 'genres', $id, $nom);
                        http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
                    }
                } elseif ($flash_err === '') {
                    $flash_err = 'Modification impossible (nom en doublon ou erreur).';
                }
            }
        } elseif (isset($_POST['delete_genre'])) {
            $id = (int) ($_POST['g_id'] ?? 0);
            $row = $id > 0 ? get_genre_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Genre introuvable.';
            } elseif (genre_delete_row($id)) {
                if (!empty($row['image']) && is_file($upload_root . str_replace('\\', '/', (string) $row['image']))) {
                    @unlink($upload_root . str_replace('\\', '/', (string) $row['image']));
                }
                super_admin_log_action($sa_id, 'genre_supprime', 'genres', $id, (string) ($row['nom'] ?? ''));
                http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
            } else {
                $flash_err = 'Suppression impossible : des produits sont encore associés à ce genre.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$genres_list = genres_list_all();
$edit_g = isset($_GET['edit_genre']) ? (int) $_GET['edit_genre'] : 0;
$row_edit_g = $edit_g > 0 ? get_genre_by_id($edit_g) : false;
$cg_list_all = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
    ? get_general_categories_ordered() : [];
$links_ok = genres_cg_links_table_exists();
$prefill_cg_ids = ($row_edit_g && $links_ok) ? get_categorie_generale_ids_for_genre((int) $row_edit_g['id']) : [];
$cg_nom_by_id = [];
foreach ($cg_list_all as $__cg) {
    $cg_nom_by_id[(int) ($__cg['id'] ?? 0)] = (string) ($__cg['nom'] ?? '');
}

$csrf = super_admin_csrf_token();
$table_ok = genres_table_exists();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genres catalogue — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page<?php echo $row_edit_g ? ' sa-cat-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero" aria-labelledby="sa-genres-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d’Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li><a href="index.php">Paramètres</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">Genres</li>
                        </ol>
                    </nav>
                    <p class="sa-param-hero__eyebrow">
                        <i class="fas fa-tags" aria-hidden="true"></i> Catalogue marketplace
                    </p>
                    <h1 class="sa-param-hero__title" id="sa-genres-title">
                        Genres produits
                        <span class="sa-param-hero__badge">Indépendants des rayons</span>
                    </h1>
                    <p class="sa-param-hero__lead">
                        Les <strong>genres</strong> se rattachent à une ou plusieurs <a href="categories-catalogue.php">catégories principales</a> : les vendeurs les cochent lorsque le rayon choisi prévoit des genres.
                    </p>
                </div>
                <div class="sa-param-hero__stamp" aria-hidden="true">
                    <div class="sa-param-hero__stamp-box">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$table_ok): ?>
            <div class="sa-cat-migrate-banner" role="note">
                <strong>Migration requise.</strong> Exécutez <code>php migrations/run_migrate_genres.php</code> pour créer les tables <code>genres</code> et <code>produits_genres</code>, puis rechargez cette page.
            </div>
        <?php endif; ?>
        <?php if ($table_ok && !$links_ok): ?>
            <div class="sa-cat-migrate-banner" role="note">
                <strong>Liaison genres ↔ rayons.</strong> Exécutez <code>php migrations/run_migrate_genres_categories_generales.php</code> pour activer la sélection des catégories principales par genre.
            </div>
        <?php endif; ?>

        <section class="sa-cat-panel" aria-labelledby="sa-genres-panel-title">
            <div class="sa-cat-panel__head" id="sa-genres-panel-title">
                <span class="sa-cat-panel__head-icon" aria-hidden="true"><i class="fas fa-list-check"></i></span>
                <div class="sa-cat-panel__head-text">
                    <h2>Liste des genres</h2>
                    <p>Ordre d’affichage, rayons associés, libellé et visuel. Les vendeurs voient les genres uniquement pour les rayons auxquels vous les liez.</p>
                </div>
            </div>
            <div class="sa-cat-panel__body">
                <div class="sa-cat-panel__toolbar">
                    <button type="button" class="sa-cat-btn sa-cat-btn--primary" id="btnOpenModalGenre" data-open-modal="saModalGenre" <?php echo !$table_ok ? 'disabled title="Migration requise"' : ''; ?> <?php echo $row_edit_g ? 'disabled title="Terminez la modification en cours" aria-disabled="true"' : ''; ?>>
                        <i class="fas fa-plus-circle" aria-hidden="true"></i>
                        Ajouter un genre
                    </button>
                </div>
                <div class="sa-cat-table-wrap">
                    <table class="sa-cat-table">
                        <thead>
                            <tr>
                                <th>Visuel</th>
                                <th>Ordre</th>
                                <th>Nom</th>
                                <th>Rayons</th>
                                <th>Description</th>
                                <th>Produits</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($genres_list as $gr): ?>
                                <?php
                                $gid = (int) ($gr['id'] ?? 0);
                                $nbp = count_produits_par_genre_id($gid);
                                $ids_rayons = $links_ok ? get_categorie_generale_ids_for_genre($gid) : [];
                                $labels_rayons = [];
                                foreach ($ids_rayons as $irc) {
                                    if (!empty($cg_nom_by_id[$irc])) {
                                        $labels_rayons[] = $cg_nom_by_id[$irc];
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="sa-cat-table__visuel">
                                        <?php if (!empty($gr['image'])): ?>
                                            <span class="sa-cat-table__thumb-wrap">
                                                <img src="/upload/<?php echo htmlspecialchars((string) $gr['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="sa-cat-table__thumb" width="44" height="44" loading="lazy" decoding="async">
                                            </span>
                                        <?php else: ?>
                                            <span class="sa-cat-table__thumb-placeholder" aria-hidden="true"><i class="fas fa-image"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sa-cat-num"><?php echo (int) ($gr['sort_ordre'] ?? 0); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars((string) ($gr['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo $links_ok
                                        ? htmlspecialchars(implode(', ', $labels_rayons), ENT_QUOTES, 'UTF-8')
                                        : '—'; ?></td>
                                    <td><?php
                                    $__d = (string) ($gr['description'] ?? '');
                                    $__short = function_exists('mb_substr') ? mb_substr($__d, 0, 100) : substr($__d, 0, 100);
                                    echo nl2br(htmlspecialchars($__short, ENT_QUOTES, 'UTF-8'));
                                    echo strlen($__d) > 100 ? '…' : '';
                                    ?></td>
                                    <td><span class="sa-cat-num"><?php echo (int) $nbp; ?></span></td>
                                    <td class="sa-cat-actions">
                                        <a href="genres-catalogue.php?edit_genre=<?php echo $gid; ?>" class="sa-cat-btn sa-cat-btn--ghost sa-cat-btn--sm">Modifier</a>
                                        <form method="post" action="genres-catalogue.php" class="sa-cat-inline-form" onsubmit="return confirm('Supprimer ce genre ?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="g_id" value="<?php echo $gid; ?>">
                                            <button type="submit" name="delete_genre" value="1" class="sa-cat-btn sa-cat-btn--danger sa-cat-btn--sm" <?php echo $nbp > 0 ? 'disabled title="Retirez ce genre des produits d’abord"' : ''; ?>>Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($genres_list)): ?>
                                <tr class="sa-cat-empty-row"><td colspan="7">Aucun genre. Utilisez « Ajouter un genre ».</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="sa-cat-modal" id="saModalGenre" role="dialog" aria-modal="true" aria-labelledby="saModalGenreTitle" <?php echo $row_edit_g ? '' : 'hidden'; ?><?php echo $row_edit_g ? ' data-sa-cat-close-reset-href="genres-catalogue.php"' : ''; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-cat-close-modal tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel">
            <div class="sa-cat-modal__header">
                <h2 id="saModalGenreTitle"><?php echo $row_edit_g ? 'Modifier un genre' : 'Nouveau genre'; ?></h2>
                <button type="button" class="sa-cat-modal__close" data-sa-cat-close-modal aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="sa-cat-modal__body">
                <?php if ($row_edit_g): ?>
                    <div class="sa-cat-form-block">
                        <form class="sa-cat-form" method="post" action="genres-catalogue.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="g_id" value="<?php echo (int) $row_edit_g['id']; ?>">
                            <p><strong>Modifier</strong> « <?php echo htmlspecialchars((string) $row_edit_g['nom'], ENT_QUOTES, 'UTF-8'); ?> »</p>
                            <div class="sa-cat-field">
                                <label for="g_nom">Nom</label>
                                <input type="text" id="g_nom" name="g_nom" required maxlength="255" value="<?php echo htmlspecialchars((string) $row_edit_g['nom'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="sa-cat-field">
                                <label for="g_description">Description</label>
                                <textarea id="g_description" name="g_description"><?php echo htmlspecialchars((string) ($row_edit_g['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="sa-cat-field">
                                <label for="g_sort">Ordre d’affichage</label>
                                <input type="number" id="g_sort" name="g_sort" value="<?php echo (int) ($row_edit_g['sort_ordre'] ?? 0); ?>">
                            </div>
                            <?php if (!empty($cg_list_all)): ?>
                            <fieldset class="sa-cat-field sa-cat-field--rayons">
                                <legend class="sa-cat-field__legend">Catégories principales <span class="required">*</span></legend>
                                <p class="sa-cat-form-hint">Le genre sera proposé aux vendeurs lorsqu’ils choisissent l’un de ces rayons.</p>
                                <div class="sa-cat-rayons-grid" role="group" aria-label="Rayons">
                                    <?php foreach ($cg_list_all as $__cg): ?>
                                        <?php
                                        $__cid = (int) ($__cg['id'] ?? 0);
                                        if ($__cid <= 0) {
                                            continue;
                                        }
                                        $__chk = in_array($__cid, $prefill_cg_ids, true);
                                        ?>
                                        <label class="sa-cat-rayon-label">
                                            <input type="checkbox" name="g_categorie_generale_ids[]" value="<?php echo $__cid; ?>" <?php echo $__chk ? 'checked' : ''; ?> <?php echo $links_ok ? '' : 'disabled'; ?>>
                                            <span><?php echo htmlspecialchars((string) ($__cg['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <?php endif; ?>
                            <div class="sa-cat-field">
                                <label for="g_image">Image (optionnel, remplace l’actuelle)</label>
                                <input type="file" id="g_image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                <?php if (!empty($row_edit_g['image'])): ?>
                                    <p class="sa-cat-form-hint">Fichier actuel : <?php echo htmlspecialchars((string) $row_edit_g['image'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="sa-cat-actions">
                                <button type="submit" name="update_genre" value="1" class="sa-cat-btn sa-cat-btn--primary">Enregistrer</button>
                                <a href="genres-catalogue.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="sa-cat-form-block">
                        <div class="sa-cat-form-card">
                            <form class="sa-cat-form" method="post" action="genres-catalogue.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <p><strong>Nouveau</strong> genre</p>
                                <div class="sa-cat-field">
                                    <label for="g_nom_new">Nom</label>
                                    <input type="text" id="g_nom_new" name="g_nom" required maxlength="255" placeholder="Ex. Huile moteur" <?php echo $table_ok ? '' : 'disabled'; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="g_description_new">Description</label>
                                    <textarea id="g_description_new" name="g_description" <?php echo $table_ok ? '' : 'disabled'; ?>></textarea>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="g_sort_new">Ordre</label>
                                    <input type="number" id="g_sort_new" name="g_sort" value="0" <?php echo $table_ok ? '' : 'disabled'; ?>>
                                </div>
                                <?php if (!empty($cg_list_all)): ?>
                                <fieldset class="sa-cat-field sa-cat-field--rayons">
                                    <legend class="sa-cat-field__legend">Catégories principales <span class="required">*</span></legend>
                                    <p class="sa-cat-form-hint">Cochez les rayons pour lesquels ce genre apparaît dans le formulaire vendeur.</p>
                                    <div class="sa-cat-rayons-grid" role="group" aria-label="Rayons">
                                        <?php foreach ($cg_list_all as $__cg): ?>
                                            <?php
                                            $__cid = (int) ($__cg['id'] ?? 0);
                                            if ($__cid <= 0) {
                                                continue;
                                            }
                                            ?>
                                            <label class="sa-cat-rayon-label">
                                                <input type="checkbox" name="g_categorie_generale_ids[]" value="<?php echo $__cid; ?>" <?php echo $table_ok && $links_ok ? '' : 'disabled'; ?>>
                                                <span><?php echo htmlspecialchars((string) ($__cg['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                                <?php endif; ?>
                                <div class="sa-cat-field">
                                    <label for="g_image_new">Image (optionnel)</label>
                                    <input type="file" id="g_image_new" name="image" accept="image/jpeg,image/png,image/gif,image/webp" <?php echo $table_ok ? '' : 'disabled'; ?>>
                                </div>
                                <button type="submit" name="create_genre" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo $table_ok ? '' : 'disabled'; ?>>Ajouter le genre</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var OPEN = 'sa-cat-modal-open';
        function openModal(el) {
            if (!el) return;
            el.removeAttribute('hidden');
            document.body.classList.add(OPEN);
        }
        function closeModal(el) {
            if (!el) return;
            var reset = el.getAttribute('data-sa-cat-close-reset-href');
            if (reset) {
                window.location.href = reset;
                return;
            }
            el.setAttribute('hidden', 'hidden');
            document.body.classList.remove(OPEN);
        }
        document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-open-modal');
                openModal(document.getElementById(id));
            });
        });
        document.querySelectorAll('[data-sa-cat-close-modal]').forEach(function (node) {
            node.addEventListener('click', function () {
                var m = node.closest('.sa-cat-modal');
                if (m) closeModal(m);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var vis = document.querySelector('.sa-cat-modal:not([hidden])');
            if (vis) closeModal(vis);
        });
    })();
    </script>
</body>

</html>
