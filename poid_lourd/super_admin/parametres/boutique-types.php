<?php
/**
 * Types de boutique — options du formulaire d'inscription vendeur.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_boutique_types.php';
require_once dirname(__DIR__, 2) . '/models/model_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!boutique_types_table_exists()) {
        $flash_err = 'Table boutique_types absente. Exécutez : php migrations/run_migrate_boutique_types.php';
    } else {
        if (isset($_POST['create_bt'])) {
            $nom = trim((string) ($_POST['bt_nom'] ?? ''));
            $d = trim((string) ($_POST['bt_description'] ?? ''));
            $so = (int) ($_POST['bt_sort'] ?? 0);
            $actif = isset($_POST['bt_actif']) ? 1 : 0;
            if ($nom === '') {
                $flash_err = 'Le nom du type est obligatoire.';
            } else {
                $id = boutique_type_insert_row($nom, $d !== '' ? $d : null, $so, $actif);
                if ($id) {
                    super_admin_log_action($sa_id, 'boutique_type_cree', 'boutique_types', $id, $nom);
                    http_redirect_safe('/super_admin/parametres/boutique-types.php?ok=1');
                } else {
                    $flash_err = 'Impossible d\'ajouter ce type (nom déjà utilisé ou erreur).';
                }
            }
        } elseif (isset($_POST['update_bt'])) {
            $id = (int) ($_POST['bt_id'] ?? 0);
            $nom = trim((string) ($_POST['bt_nom'] ?? ''));
            $d = trim((string) ($_POST['bt_description'] ?? ''));
            $so = (int) ($_POST['bt_sort'] ?? 0);
            $actif = isset($_POST['bt_actif']) ? 1 : 0;
            $row = $id > 0 ? get_boutique_type_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Type introuvable.';
            } elseif ($nom === '') {
                $flash_err = 'Le nom du type est obligatoire.';
            } elseif (boutique_type_update_row($id, $nom, $d !== '' ? $d : null, $so, $actif)) {
                super_admin_log_action($sa_id, 'boutique_type_modifie', 'boutique_types', $id, $nom);
                http_redirect_safe('/super_admin/parametres/boutique-types.php?ok=1');
            } else {
                $flash_err = 'Modification impossible (nom en doublon ou erreur).';
            }
        } elseif (isset($_POST['delete_bt'])) {
            $id = (int) ($_POST['bt_id'] ?? 0);
            if (boutique_type_delete_row($id)) {
                super_admin_log_action($sa_id, 'boutique_type_supprime', 'boutique_types', $id, '');
                http_redirect_safe('/super_admin/parametres/boutique-types.php?ok=1');
            } else {
                $flash_err = 'Suppression impossible : des boutiques utilisent encore ce type.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$types_list = boutique_types_list_all(true);
$edit_id = isset($_GET['edit_bt']) ? (int) $_GET['edit_bt'] : 0;
$row_edit = $edit_id > 0 ? get_boutique_type_by_id($edit_id) : false;
$table_ok = boutique_types_table_exists();
$admin_col_ok = admin_has_boutique_type_id_column();

$csrf = super_admin_csrf_token();
$show_form_modal = ($row_edit !== false) || (isset($_GET['open_form']) && $_GET['open_form'] === '1');
$form_mode_edit = $row_edit !== false;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Types de boutique — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-rappels-vendeur.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page sa-bt-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero" aria-labelledby="sa-bt-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d’Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li><a href="index.php">Paramètres</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">Types de boutique</li>
                        </ol>
                    </nav>
                    <p class="sa-param-hero__eyebrow">
                        <i class="fas fa-store" aria-hidden="true"></i> Inscription vendeur
                    </p>
                    <h1 class="sa-param-hero__title" id="sa-bt-title">
                        Types de boutique
                        <span class="sa-param-hero__badge">Vendeurs</span>
                    </h1>
                    <p class="sa-param-hero__lead">
                        Les options ajoutées ici apparaissent dans le champ <strong>Type de boutique</strong> lors de la création d’un compte vendeur.
                    </p>
                </div>
                <div class="sa-param-hero__stamp" aria-hidden="true">
                    <div class="sa-param-hero__stamp-box">
                        <i class="fas fa-layer-group"></i>
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

        <?php if (!$table_ok || !$admin_col_ok): ?>
            <div class="sa-cat-migrate-banner" role="note">
                <strong>Migration requise.</strong> Exécutez
                <code>php migrations/run_migrate_boutique_types.php</code>
                pour créer la table <code>boutique_types</code> et la colonne <code>admin.boutique_type_id</code>.
            </div>
        <?php endif; ?>

        <section class="sa-cat-panel sa-vr-list-panel" aria-labelledby="sa-bt-list-title">
            <div class="sa-vr-list-head" id="sa-bt-list-title">
                <div class="sa-vr-list-head__text">
                    <h2>Liste des types</h2>
                    <p><?php echo count($types_list); ?> type<?php echo count($types_list) > 1 ? 's' : ''; ?> configuré<?php echo count($types_list) > 1 ? 's' : ''; ?> — seuls les types actifs sont proposés à l'inscription vendeur.</p>
                </div>
                <button type="button" class="sa-vr-create-btn" id="btnOpenBtForm" <?php echo !$table_ok ? 'disabled' : ''; ?>>
                    <i class="fas fa-plus" aria-hidden="true"></i> Créer un type
                </button>
            </div>
            <div class="sa-cat-panel__body">
                <div class="sa-cat-table-wrap">
                    <table class="sa-cat-table">
                        <thead>
                            <tr>
                                <th>Ordre</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th>Boutiques</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types_list as $bt): ?>
                                <?php
                                $bid = (int) ($bt['id'] ?? 0);
                                $nb_v = count_vendeurs_par_boutique_type_id($bid);
                                $is_actif = (int) ($bt['actif'] ?? 0) === 1;
                                ?>
                                <tr>
                                    <td><span class="sa-cat-num"><?php echo (int) ($bt['sort_ordre'] ?? 0); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars((string) ($bt['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php
                                    $__d = (string) ($bt['description'] ?? '');
                                    $__short = function_exists('mb_substr') ? mb_substr($__d, 0, 80) : substr($__d, 0, 80);
                                    echo nl2br(htmlspecialchars($__short, ENT_QUOTES, 'UTF-8'));
                                    echo strlen($__d) > 80 ? '…' : '';
                                    ?></td>
                                    <td><?php echo $is_actif ? 'Actif' : 'Inactif'; ?></td>
                                    <td><span class="sa-cat-num"><?php echo (int) $nb_v; ?></span></td>
                                    <td class="sa-cat-actions">
                                        <a href="boutique-types.php?edit_bt=<?php echo $bid; ?>" class="sa-cat-btn sa-cat-btn--ghost sa-cat-btn--sm">Modifier</a>
                                        <form method="post" action="boutique-types.php" class="sa-cat-inline-form" onsubmit="return confirm('Supprimer ce type de boutique ?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="bt_id" value="<?php echo $bid; ?>">
                                            <button type="submit" name="delete_bt" value="1" class="sa-cat-btn sa-cat-btn--danger sa-cat-btn--sm"
                                                <?php echo $nb_v > 0 ? 'disabled title="Des boutiques utilisent ce type"' : ''; ?>>Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($types_list)): ?>
                                <tr class="sa-cat-empty-row">
                                    <td colspan="6">
                                        Aucun type configuré.
                                        <button type="button" class="sa-vr-create-btn" data-open-bt-form style="margin-top:0.75rem;" <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                            <i class="fas fa-plus"></i> Créer le premier type
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="sa-vr-form-overlay" id="btFormOverlay" role="dialog" aria-modal="true" aria-labelledby="sa-bt-form-title"
        <?php echo $show_form_modal ? '' : 'hidden'; ?> aria-hidden="<?php echo $show_form_modal ? 'false' : 'true'; ?>">
        <div class="sa-vr-form-modal">
            <button type="button" class="sa-vr-form-modal__close" id="btnCloseBtForm" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
            <section class="sa-cat-panel" aria-labelledby="sa-bt-form-title">
                <div class="sa-cat-panel__head" id="sa-bt-form-title">
                    <span class="sa-cat-panel__head-icon" aria-hidden="true"><i class="fas fa-<?php echo $form_mode_edit ? 'pen' : 'plus-circle'; ?>"></i></span>
                    <div class="sa-cat-panel__head-text">
                        <h2><?php echo $form_mode_edit ? 'Modifier un type' : 'Ajouter un type'; ?></h2>
                        <p>Nom affiché dans le select d'inscription. Seuls les types <strong>actifs</strong> sont proposés aux vendeurs.</p>
                    </div>
                </div>
                <div class="sa-cat-panel__body">
                    <div class="sa-cat-form-block">
                        <div class="sa-cat-form-card">
                            <form class="sa-cat-form" id="btForm" method="post" action="boutique-types.php<?php echo $form_mode_edit ? '?edit_bt=' . (int) $edit_id : ''; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($form_mode_edit): ?>
                                    <input type="hidden" name="bt_id" value="<?php echo (int) $edit_id; ?>">
                                    <p><strong>Modifier</strong> « <?php echo htmlspecialchars((string) ($row_edit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> »</p>
                                <?php else: ?>
                                    <p><strong>Nouveau</strong> type de boutique</p>
                                <?php endif; ?>
                                <div class="sa-cat-field">
                                    <label for="bt_nom">Nom *</label>
                                    <input type="text" id="bt_nom" name="bt_nom" required maxlength="255"
                                        value="<?php echo htmlspecialchars((string) ($row_edit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="bt_sort">Ordre d'affichage</label>
                                    <input type="number" id="bt_sort" name="bt_sort" step="1"
                                        value="<?php echo (int) ($row_edit['sort_ordre'] ?? 0); ?>"
                                        <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="bt_description">Description (facultatif)</label>
                                    <textarea id="bt_description" name="bt_description" rows="2" maxlength="1000" <?php echo !$table_ok ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string) ($row_edit['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div class="sa-cat-field">
                                    <label class="sa-cat-check-label">
                                        <input type="checkbox" name="bt_actif" value="1"
                                            <?php echo !$row_edit || (int) ($row_edit['actif'] ?? 0) === 1 ? 'checked' : ''; ?>
                                            <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                        Actif (visible à l'inscription)
                                    </label>
                                </div>
                                <div class="sa-cat-actions">
                                    <?php if ($form_mode_edit): ?>
                                        <button type="submit" name="update_bt" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                            Enregistrer
                                        </button>
                                        <a href="boutique-types.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
                                    <?php else: ?>
                                        <button type="submit" name="create_bt" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                            Ajouter
                                        </button>
                                        <button type="button" class="sa-cat-btn sa-cat-btn--ghost" id="btnCancelBtForm">Annuler</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    (function () {
        var overlay = document.getElementById('btFormOverlay');
        var btnOpen = document.getElementById('btnOpenBtForm');
        var btnClose = document.getElementById('btnCloseBtForm');
        var btnCancel = document.getElementById('btnCancelBtForm');
        var openBtns = document.querySelectorAll('[data-open-bt-form]');

        function openForm() {
            if (!overlay) return;
            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var first = document.getElementById('bt_nom');
            if (first) setTimeout(function () { first.focus(); }, 80);
        }

        function closeForm() {
            if (!overlay) return;
            if (window.location.search.indexOf('edit_bt=') !== -1) {
                window.location.href = 'boutique-types.php';
                return;
            }
            overlay.setAttribute('hidden', 'hidden');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (btnOpen) btnOpen.addEventListener('click', openForm);
        openBtns.forEach(function (b) { b.addEventListener('click', openForm); });
        if (btnClose) btnClose.addEventListener('click', closeForm);
        if (btnCancel) btnCancel.addEventListener('click', closeForm);

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeForm();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && !overlay.hasAttribute('hidden')) {
                closeForm();
            }
        });

        <?php if ($show_form_modal): ?>
        document.body.style.overflow = 'hidden';
        <?php endif; ?>
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>
