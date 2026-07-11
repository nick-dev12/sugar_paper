<?php
/**
 * Sous-catégories plateforme — liées à un rayon (categories_generales), visibles des vendeurs.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_categories.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produits_sous_categories.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_categories.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';
$upload_root = dirname(__DIR__, 2) . '/upload/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()
        || !categories_has_categorie_generale_id_column()) {
        $flash_err = 'Structure catalogue incomplète. Exécutez les migrations catégories / rayons.';
    } else {
        if (isset($_POST['create_sc'])) {
            $nom = trim((string) ($_POST['sc_nom'] ?? ''));
            $d = trim((string) ($_POST['sc_description'] ?? ''));
            $gid = (int) ($_POST['sc_categorie_generale_id'] ?? 0);
            if ($nom === '') {
                $flash_err = 'Le nom est obligatoire.';
            } elseif ($gid <= 0 || !get_categorie_generale_by_id($gid)) {
                $flash_err = 'Sélectionnez un rayon valide.';
            } else {
                $img = null;
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_categorie_image($_FILES);
                    if ($up) {
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide (JPEG, PNG, GIF, WebP).';
                    }
                }
                if ($flash_err === '') {
                    $new_id = plateforme_create_sous_categorie($nom, $d !== '' ? $d : null, $img, $gid);
                    if ($new_id) {
                        if (function_exists('plateforme_ensure_liaison_rows_for_legacy_categories')) {
                            plateforme_ensure_liaison_rows_for_legacy_categories();
                        }
                        super_admin_log_action($sa_id, 'sous_categorie_creee', 'categories', (int) $new_id, $nom);
                        http_redirect_safe('/super_admin/parametres/sous-categories-catalogue.php?ok=1');
                    }
                    $flash_err = 'Impossible d’enregistrer (nom déjà utilisé dans ce rayon ou erreur).';
                }
            }
        } elseif (isset($_POST['update_sc'])) {
            $id = (int) ($_POST['sc_id'] ?? 0);
            $nom = trim((string) ($_POST['sc_nom'] ?? ''));
            $d = trim((string) ($_POST['sc_description'] ?? ''));
            $gid = (int) ($_POST['sc_categorie_generale_id'] ?? 0);
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Sous-catégorie introuvable.';
            } elseif (!categorie_est_sous_categorie_plateforme($row)) {
                $flash_err = 'Cette fiche n’est pas une sous-catégorie plateforme.';
            } elseif ($nom === '') {
                $flash_err = 'Le nom est obligatoire.';
            } elseif ($gid <= 0 || !get_categorie_generale_by_id($gid)) {
                $flash_err = 'Sélectionnez un rayon valide.';
            } else {
                $img = (string) ($row['image'] ?? '');
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_categorie_image($_FILES);
                    if ($up) {
                        if ($img !== '' && is_file($upload_root . str_replace('\\', '/', $img))) {
                            @unlink($upload_root . str_replace('\\', '/', $img));
                        }
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide.';
                    }
                }
                if ($flash_err === '' && plateforme_update_sous_categorie($id, $nom, $d !== '' ? $d : null, $img !== '' ? $img : null, $gid)) {
                    if (function_exists('plateforme_ensure_liaison_rows_for_legacy_categories')) {
                        plateforme_ensure_liaison_rows_for_legacy_categories();
                    }
                    super_admin_log_action($sa_id, 'sous_categorie_modifiee', 'categories', $id, $nom);
                    http_redirect_safe('/super_admin/parametres/sous-categories-catalogue.php?ok=1');
                } elseif ($flash_err === '') {
                    $flash_err = 'Modification impossible (nom déjà utilisé dans ce rayon ou erreur).';
                }
            }
        } elseif (isset($_POST['delete_sc'])) {
            $id = (int) ($_POST['sc_id'] ?? 0);
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Sous-catégorie introuvable.';
            } elseif (!categorie_est_sous_categorie_plateforme($row)) {
                $flash_err = 'Cette fiche n’est pas une sous-catégorie plateforme.';
            } elseif (function_exists('count_produits_referencing_sous_categorie') && count_produits_referencing_sous_categorie($id) > 0) {
                $flash_err = 'Impossible de supprimer : des produits utilisent encore cette sous-catégorie.';
            } else {
                if (produits_sous_categories_table_exists()) {
                    global $db;
                    try {
                        $db->prepare('DELETE FROM `produits_sous_categories` WHERE `categorie_id` = :c')->execute(['c' => $id]);
                    } catch (PDOException $e) {
                    }
                }
                if (delete_categorie($id)) {
                    $img = (string) ($row['image'] ?? '');
                    if ($img !== '' && is_file($upload_root . str_replace('\\', '/', $img))) {
                        @unlink($upload_root . str_replace('\\', '/', $img));
                    }
                    super_admin_log_action($sa_id, 'sous_categorie_supprimee', 'categories', $id, (string) ($row['nom'] ?? ''));
                    http_redirect_safe('/super_admin/parametres/sous-categories-catalogue.php?ok=1');
                }
                $flash_err = 'Suppression impossible.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$sc_list = get_plateforme_sous_categories_for_form();
$edit_sc = isset($_GET['edit_sc']) ? (int) $_GET['edit_sc'] : 0;
$row_edit = $edit_sc > 0 ? get_categorie_by_id($edit_sc) : false;
if ($row_edit && !categorie_est_sous_categorie_plateforme($row_edit)) {
    $row_edit = false;
    $edit_sc = 0;
}
$rayons = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
    ? get_general_categories_ordered() : [];
$edit_rayon = 0;
if ($row_edit) {
    $edit_rayon = (int) ($row_edit['categorie_generale_id'] ?? 0);
    if ($edit_rayon <= 0) {
        $rids = plateforme_get_rayons_ids_for_categorie((int) $row_edit['id']);
        $edit_rayon = (int) ($rids[0] ?? 0);
    }
}
$csrf = super_admin_csrf_token();
$table_ok = !empty($rayons) && categories_has_categorie_generale_id_column();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-catégories catalogue — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page<?php echo $row_edit ? ' sa-cat-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero" aria-labelledby="sa-sc-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d’Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li><a href="index.php">Paramètres</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">Sous-catégories</li>
                        </ol>
                    </nav>
                    <h1 class="sa-param-hero__title" id="sa-sc-title">Sous-catégories <span class="sa-param-hero__badge">Catalogue</span></h1>
                    <p class="sa-param-hero__lead">Rubriques rattachées à un <a href="categories-catalogue.php">rayon</a> : les vendeurs y associent leurs fiches (plusieurs choix possibles). Configurez ici le nom, l’image et le lien au rayon.</p>
                </div>
            </div>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!$table_ok): ?>
            <div class="sa-cat-migrate-banner" role="note"><strong>Configuration requise.</strong> Vérifiez les tables <code>categories_generales</code> et la colonne <code>categories.categorie_generale_id</code>.</div>
        <?php endif; ?>

        <section class="sa-cat-panel">
            <div class="sa-cat-panel__head">
                <span class="sa-cat-panel__head-icon" aria-hidden="true"><i class="fas fa-network-wired"></i></span>
                <div class="sa-cat-panel__head-text">
                    <h2>Sous-catégories plateforme</h2>
                    <p>Liste des rubriques proposées aux vendeurs (hors compte propriétaire boutique).</p>
                </div>
            </div>
            <div class="sa-cat-panel__body">
                <div class="sa-cat-panel__toolbar">
                    <button type="button" class="sa-cat-btn sa-cat-btn--primary" id="btnOpenModalSc" data-open-modal="saModalSc" <?php echo !$table_ok || $row_edit ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter une sous-catégorie
                    </button>
                </div>
                <p style="font-size:13px;color:#555;margin:0 0 12px;">Pour la liaison produits multi-choix, exécutez <code>php migrations/run_migrate_produits_sous_categories.php</code> si ce n’est pas déjà fait.</p>
                <div class="sa-cat-table-wrap">
                    <table class="sa-cat-table">
                        <thead>
                            <tr>
                                <th>Visuel</th>
                                <th>Nom</th>
                                <th>Rayon</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sc_list as $sc): ?>
                                <?php
                                $scid = (int) ($sc['id'] ?? 0);
                                if ($scid <= 0) {
                                    continue;
                                }
                                $__sc_img = function_exists('categorie_image_public_path') ? categorie_image_public_path($sc) : null;
                                ?>
                            <tr>
                                <td class="sa-cat-table__visuel" style="width:72px;">
                                    <?php if (is_string($__sc_img) && $__sc_img !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($__sc_img, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="max-width:56px;max-height:56px;object-fit:cover;border-radius:6px;" loading="lazy" decoding="async">
                                    <?php else: ?>
                                    <span class="sa-cat-table__fa-placeholder" aria-hidden="true"><i class="fas fa-image"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars((string) ($sc['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($sc['generale_nom'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="sa-cat-actions">
                                    <a class="sa-cat-btn sa-cat-btn--ghost sa-cat-btn--sm" href="sous-categories-catalogue.php?edit_sc=<?php echo $scid; ?>">Modifier</a>
                                    <form method="post" action="sous-categories-catalogue.php" class="sa-cat-inline-form" onsubmit="return confirm('Supprimer cette sous-catégorie ?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="sc_id" value="<?php echo $scid; ?>">
                                        <button type="submit" name="delete_sc" value="1" class="sa-cat-btn sa-cat-btn--danger sa-cat-btn--sm">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sc_list)): ?>
                            <tr class="sa-cat-empty-row"><td colspan="4">Aucune sous-catégorie. Ajoutez-en une ou exécutez la migration <code>plateforme_ensure</code> si des données existent déjà en base.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="sa-cat-modal" id="saModalSc" role="dialog" aria-modal="true" aria-labelledby="saModalScTitle" <?php echo $row_edit ? '' : 'hidden'; ?><?php echo $row_edit ? ' data-sa-cat-close-reset-href="sous-categories-catalogue.php"' : ''; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-cat-close-modal tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel">
            <div class="sa-cat-modal__header">
                <h2 id="saModalScTitle"><?php echo $row_edit ? 'Modifier la sous-catégorie' : 'Nouvelle sous-catégorie'; ?></h2>
                <button type="button" class="sa-cat-modal__close" data-sa-cat-close-modal aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="sa-cat-modal__body">
                <form class="sa-cat-form" method="post" enctype="multipart/form-data" action="sous-categories-catalogue.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($row_edit): ?>
                    <input type="hidden" name="sc_id" value="<?php echo (int) $row_edit['id']; ?>">
                    <?php endif; ?>
                    <div class="sa-cat-field">
                        <label for="sc_nom">Nom <span class="required">*</span></label>
                        <input type="text" id="sc_nom" name="sc_nom" required maxlength="255" value="<?php echo $row_edit ? htmlspecialchars((string) $row_edit['nom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                    <div class="sa-cat-field">
                        <label for="sc_description">Description</label>
                        <textarea id="sc_description" name="sc_description" rows="3"><?php echo $row_edit ? htmlspecialchars((string) ($row_edit['description'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    </div>
                    <div class="sa-cat-field">
                        <label for="sc_categorie_generale_id">Rayon (catégorie générale) <span class="required">*</span></label>
                        <select id="sc_categorie_generale_id" name="sc_categorie_generale_id" required>
                            <option value="">Choisir un rayon</option>
                            <?php foreach ($rayons as $r): ?>
                                <?php $rid = (int) ($r['id'] ?? 0);
                                if ($rid <= 0) {
                                    continue;
                                } ?>
                            <option value="<?php echo $rid; ?>" <?php echo $edit_rayon === $rid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($r['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sa-cat-field">
                        <label for="sc_image">Image (optionnel)</label>
                        <input type="file" id="sc_image" name="image" accept="image/*">
                    </div>
                    <div class="sa-cat-actions">
                        <button type="submit" name="<?php echo $row_edit ? 'update_sc' : 'create_sc'; ?>" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo $table_ok ? '' : 'disabled'; ?>>Enregistrer</button>
                        <a href="sous-categories-catalogue.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var openBtn = document.getElementById('btnOpenModalSc');
        var modal = document.getElementById('saModalSc');
        if (openBtn && modal) {
            openBtn.addEventListener('click', function () { modal.hidden = false; document.body.classList.add('sa-cat-modal-open'); });
        }
        document.querySelectorAll('[data-sa-cat-close-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                var m = el.closest ? el.closest('.sa-cat-modal') : null;
                if (m) {
                    m.hidden = true;
                    document.body.classList.remove('sa-cat-modal-open');
                    var h = m.getAttribute('data-sa-cat-close-reset-href');
                    if (h) window.location.href = h;
                }
            });
        });
    })();
    </script>
</body>
</html>
