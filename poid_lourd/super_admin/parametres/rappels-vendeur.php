<?php
/**
 * Rappels vendeur — configuration popups dashboard.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_vendeur_rappels.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/services/send_vendeur_rappel_notification.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';
$action_types = vendeur_rappel_action_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!vendeur_rappels_table_exists()) {
        $flash_err = 'Table absente. Exécutez : php migrations/run_migrate_vendeur_rappels.php';
    } else {
        if (isset($_POST['create_vr'])) {
            $titre = trim((string) ($_POST['vr_titre'] ?? ''));
            $msg = trim((string) ($_POST['vr_message'] ?? ''));
            $atype = trim((string) ($_POST['vr_action_type'] ?? ''));
            $alabel = trim((string) ($_POST['vr_action_label'] ?? ''));
            $so = (int) ($_POST['vr_sort'] ?? 0);
            $actif = isset($_POST['vr_actif']) ? 1 : 0;
            if ($titre === '' || $msg === '' || $alabel === '') {
                $flash_err = 'Titre, message et libellé du bouton sont obligatoires.';
            } elseif (!vendeur_rappel_action_type_valid($atype)) {
                $flash_err = 'Type d\'action invalide.';
            } else {
                $id = vendeur_rappel_insert_row($titre, $msg, $atype, $alabel, $so, $actif);
                if ($id) {
                    super_admin_log_action($sa_id, 'vendeur_rappel_cree', 'vendeur_rappels', $id, $titre);
                    if ($actif === 1) {
                        $push = send_vendeur_rappel_push_notification($id, ['only_concerned' => true]);
                        if (!empty($push['message'])) {
                            $_SESSION['sa_vr_push_notice'] = (string) $push['message'];
                        }
                    }
                    http_redirect_safe('/super_admin/parametres/rappels-vendeur.php?ok=1');
                } else {
                    $flash_err = 'Impossible d\'ajouter ce rappel.';
                }
            }
        } elseif (isset($_POST['update_vr'])) {
            $id = (int) ($_POST['vr_id'] ?? 0);
            $titre = trim((string) ($_POST['vr_titre'] ?? ''));
            $msg = trim((string) ($_POST['vr_message'] ?? ''));
            $atype = trim((string) ($_POST['vr_action_type'] ?? ''));
            $alabel = trim((string) ($_POST['vr_action_label'] ?? ''));
            $so = (int) ($_POST['vr_sort'] ?? 0);
            $actif = isset($_POST['vr_actif']) ? 1 : 0;
            if (!$id || !get_vendeur_rappel_by_id($id)) {
                $flash_err = 'Rappel introuvable.';
            } elseif ($titre === '' || $msg === '' || $alabel === '') {
                $flash_err = 'Titre, message et libellé du bouton sont obligatoires.';
            } elseif (!vendeur_rappel_action_type_valid($atype)) {
                $flash_err = 'Type d\'action invalide.';
            } elseif (vendeur_rappel_update_row($id, $titre, $msg, $atype, $alabel, $so, $actif)) {
                super_admin_log_action($sa_id, 'vendeur_rappel_modifie', 'vendeur_rappels', $id, $titre);
                http_redirect_safe('/super_admin/parametres/rappels-vendeur.php?ok=1');
            } else {
                $flash_err = 'Modification impossible.';
            }
        } elseif (isset($_POST['republish_vr'])) {
            $id = (int) ($_POST['vr_id'] ?? 0);
            $rappel = $id > 0 ? get_vendeur_rappel_by_id($id) : false;
            if (!$rappel) {
                $flash_err = 'Rappel introuvable.';
            } elseif ((int) ($rappel['actif'] ?? 0) !== 1) {
                $flash_err = 'Impossible de republier un rappel inactif.';
            } else {
                $push = republish_vendeur_rappel_notification($id);
                super_admin_log_action($sa_id, 'vendeur_rappel_republique', 'vendeur_rappels', $id, (string) ($rappel['titre'] ?? ''));
                $_SESSION['sa_vr_push_notice'] = (string) ($push['message'] ?? 'Rappel republié.');
                http_redirect_safe('/super_admin/parametres/rappels-vendeur.php?ok=1');
            }
        } elseif (isset($_POST['delete_vr'])) {
            $id = (int) ($_POST['vr_id'] ?? 0);
            if (vendeur_rappel_delete_row($id)) {
                super_admin_log_action($sa_id, 'vendeur_rappel_supprime', 'vendeur_rappels', $id, '');
                http_redirect_safe('/super_admin/parametres/rappels-vendeur.php?ok=1');
            }
            $flash_err = 'Suppression impossible.';
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
    if (!empty($_SESSION['sa_vr_push_notice'])) {
        $flash_ok .= ' ' . (string) $_SESSION['sa_vr_push_notice'];
        unset($_SESSION['sa_vr_push_notice']);
    }
}

$rappels_list = vendeur_rappels_list_all(true);
$edit_id = isset($_GET['edit_vr']) ? (int) $_GET['edit_vr'] : 0;
$row_edit = $edit_id > 0 ? get_vendeur_rappel_by_id($edit_id) : false;
$table_ok = vendeur_rappels_table_exists();
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
    <title>Rappels vendeur — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-rappels-vendeur.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page sa-vr-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero" aria-labelledby="sa-vr-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d'Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li><a href="index.php">Paramètres</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">Rappels vendeur</li>
                        </ol>
                    </nav>
                    <p class="sa-param-hero__eyebrow">
                        <i class="fas fa-bell" aria-hidden="true"></i> Boutiques vendeurs
                    </p>
                    <h1 class="sa-param-hero__title" id="sa-vr-title">
                        Rappels vendeur
                        <span class="sa-param-hero__badge">Dashboard</span>
                    </h1>
                    <p class="sa-param-hero__lead">
                        Configurez les popups affichées sur le <strong>tableau de bord vendeur</strong>. Le déclenchement est automatique selon le type d'action choisi.
                    </p>
                </div>
                <div class="sa-param-hero__stamp" aria-hidden="true">
                    <div class="sa-param-hero__stamp-box">
                        <i class="fas fa-bell"></i>
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
                <strong>Migration requise.</strong> Exécutez <code>php migrations/run_migrate_vendeur_rappels.php</code>
            </div>
        <?php endif; ?>

        <section class="sa-cat-panel sa-vr-list-panel" aria-labelledby="sa-vr-list-title">
            <div class="sa-vr-list-head" id="sa-vr-list-title">
                <div class="sa-vr-list-head__text">
                    <h2>Liste des rappels</h2>
                    <p><?php echo count($rappels_list); ?> rappel<?php echo count($rappels_list) > 1 ? 's' : ''; ?> configuré<?php echo count($rappels_list) > 1 ? 's' : ''; ?> — affichés sur le dashboard vendeur selon leur profil boutique.</p>
                </div>
                <button type="button" class="sa-vr-create-btn" id="btnOpenVrForm" <?php echo !$table_ok ? 'disabled' : ''; ?>>
                    <i class="fas fa-plus" aria-hidden="true"></i> Créer un rappel
                </button>
            </div>
            <div class="sa-vr-list-body">
                <?php if (empty($rappels_list)): ?>
                    <div class="sa-vr-empty">
                        <i class="fas fa-bell-slash" aria-hidden="true"></i>
                        <p>Aucun rappel configuré pour le moment.</p>
                        <button type="button" class="sa-vr-create-btn" data-open-vr-form <?php echo !$table_ok ? 'disabled' : ''; ?>>
                            <i class="fas fa-plus"></i> Créer le premier rappel
                        </button>
                    </div>
                <?php else: ?>
                    <div class="sa-vr-cards">
                        <?php foreach ($rappels_list as $vr): ?>
                            <?php
                            $vid = (int) ($vr['id'] ?? 0);
                            $atype = (string) ($vr['action_type'] ?? '');
                            $meta = $action_types[$atype] ?? ['label' => $atype, 'hint' => ''];
                            $is_actif = (int) ($vr['actif'] ?? 0) === 1;
                            $concernes = $is_actif ? count(vendeur_rappel_list_concerned_admin_ids($vid)) : 0;
                            ?>
                            <article class="sa-vr-card">
                                <div class="sa-vr-card__order" title="Ordre d'affichage"><?php echo (int) ($vr['sort_ordre'] ?? 0); ?></div>
                                <div class="sa-vr-card__main">
                                    <div class="sa-vr-card__title-row">
                                        <h3 class="sa-vr-card__title"><?php echo htmlspecialchars((string) ($vr['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <span class="sa-vr-card__status <?php echo $is_actif ? 'sa-vr-card__status--on' : 'sa-vr-card__status--off'; ?>">
                                            <i class="fas fa-circle" style="font-size:0.45rem"></i>
                                            <?php echo $is_actif ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </div>
                                    <div class="sa-vr-card__meta">
                                        <span class="sa-vr-card__pill"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="sa-vr-card__pill sa-vr-card__pill--accent"><i class="fas fa-hand-pointer"></i> <?php echo htmlspecialchars((string) ($vr['action_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($is_actif): ?>
                                            <span class="sa-vr-card__pill"><i class="fas fa-users"></i> <?php echo (int) $concernes; ?> vendeur<?php echo $concernes > 1 ? 's' : ''; ?> concerné<?php echo $concernes > 1 ? 's' : ''; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="sa-vr-card__hint"><?php echo htmlspecialchars($meta['hint'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="sa-vr-card__actions">
                                    <a href="rappels-vendeur.php?edit_vr=<?php echo $vid; ?>" class="sa-cat-btn sa-cat-btn--ghost sa-cat-btn--sm">
                                        <i class="fas fa-pen"></i> Modifier
                                    </a>
                                    <?php if ($is_actif): ?>
                                        <form method="post" action="rappels-vendeur.php" class="sa-cat-inline-form" onsubmit="return confirm('Republier ce rappel ? Les vendeurs concernés recevront une notification et la popup réapparaîtra sur leur tableau de bord.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="vr_id" value="<?php echo $vid; ?>">
                                            <button type="submit" name="republish_vr" value="1" class="sa-cat-btn sa-cat-btn--sm sa-vr-btn-republish">
                                                <i class="fas fa-paper-plane"></i> Republier
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="rappels-vendeur.php" class="sa-cat-inline-form" onsubmit="return confirm('Supprimer ce rappel ?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="vr_id" value="<?php echo $vid; ?>">
                                        <button type="submit" name="delete_vr" value="1" class="sa-cat-btn sa-cat-btn--danger sa-cat-btn--sm">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="sa-vr-form-overlay" id="vrFormOverlay" role="dialog" aria-modal="true" aria-labelledby="sa-vr-form-title"
        <?php echo $show_form_modal ? '' : 'hidden'; ?> aria-hidden="<?php echo $show_form_modal ? 'false' : 'true'; ?>">
        <div class="sa-vr-form-modal">
            <button type="button" class="sa-vr-form-modal__close" id="btnCloseVrForm" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
            <section class="sa-cat-panel" aria-labelledby="sa-vr-form-title">
                <div class="sa-cat-panel__head" id="sa-vr-form-title">
                    <span class="sa-cat-panel__head-icon" aria-hidden="true"><i class="fas fa-<?php echo $form_mode_edit ? 'pen' : 'plus-circle'; ?>"></i></span>
                    <div class="sa-cat-panel__head-text">
                        <h2><?php echo $form_mode_edit ? 'Modifier un rappel' : 'Créer un rappel'; ?></h2>
                        <p>Titre, message, type d'action et libellé du bouton.</p>
                    </div>
                </div>
                <div class="sa-cat-panel__body">
                    <div class="sa-cat-form-block">
                        <div class="sa-cat-form-card">
                            <form class="sa-cat-form" id="vrForm" method="post" action="rappels-vendeur.php<?php echo $form_mode_edit ? '?edit_vr=' . (int) $edit_id : ''; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($form_mode_edit): ?>
                                    <input type="hidden" name="vr_id" value="<?php echo (int) $edit_id; ?>">
                                <?php endif; ?>
                                <div class="sa-cat-field">
                                    <label for="vr_titre">Titre du rappel *</label>
                                    <input type="text" id="vr_titre" name="vr_titre" required maxlength="255"
                                        value="<?php echo htmlspecialchars((string) ($row_edit['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="vr_message">Message (paragraphe) *</label>
                                    <textarea id="vr_message" name="vr_message" rows="3" required maxlength="2000" <?php echo !$table_ok ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string) ($row_edit['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="vr_action_type">Type d'action *</label>
                                    <select id="vr_action_type" name="vr_action_type" required <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                        <?php
                                        $sel_at = (string) ($row_edit['action_type'] ?? '');
                                        foreach ($action_types as $code => $meta):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $sel_at === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="sa-cat-form-hint" id="vr_action_hint">
                                        <?php
                                        $hint_at = $sel_at !== '' ? $sel_at : array_key_first($action_types);
                                        echo htmlspecialchars($action_types[$hint_at]['hint'] ?? '', ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </p>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="vr_action_label">Libellé du bouton d'action *</label>
                                    <input type="text" id="vr_action_label" name="vr_action_label" required maxlength="120"
                                        placeholder="Ex. : Choisir mon type de boutique"
                                        value="<?php echo htmlspecialchars((string) ($row_edit['action_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label for="vr_sort">Ordre d'affichage</label>
                                    <input type="number" id="vr_sort" name="vr_sort" step="1"
                                        value="<?php echo (int) ($row_edit['sort_ordre'] ?? 0); ?>"
                                        <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                </div>
                                <div class="sa-cat-field">
                                    <label class="sa-cat-check-label">
                                        <input type="checkbox" name="vr_actif" value="1"
                                            <?php echo !$row_edit || (int) ($row_edit['actif'] ?? 0) === 1 ? 'checked' : ''; ?>
                                            <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                        Actif
                                    </label>
                                </div>
                                <div class="sa-cat-actions">
                                    <?php if ($form_mode_edit): ?>
                                        <button type="submit" name="update_vr" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo !$table_ok ? 'disabled' : ''; ?>>Enregistrer</button>
                                        <a href="rappels-vendeur.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
                                    <?php else: ?>
                                        <button type="submit" name="create_vr" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo !$table_ok ? 'disabled' : ''; ?>>Ajouter</button>
                                        <button type="button" class="sa-cat-btn sa-cat-btn--ghost" id="btnCancelVrForm">Annuler</button>
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
        var hints = <?php echo json_encode(array_map(function ($m) { return $m['hint']; }, $action_types), JSON_UNESCAPED_UNICODE); ?>;
        var sel = document.getElementById('vr_action_type');
        var hintEl = document.getElementById('vr_action_hint');
        if (sel && hintEl) {
            sel.addEventListener('change', function () {
                hintEl.textContent = hints[sel.value] || '';
            });
        }

        var overlay = document.getElementById('vrFormOverlay');
        var btnOpen = document.getElementById('btnOpenVrForm');
        var btnClose = document.getElementById('btnCloseVrForm');
        var btnCancel = document.getElementById('btnCancelVrForm');
        var openBtns = document.querySelectorAll('[data-open-vr-form]');

        function openForm() {
            if (!overlay) return;
            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var first = document.getElementById('vr_titre');
            if (first) setTimeout(function () { first.focus(); }, 80);
        }

        function closeForm() {
            if (!overlay) return;
            if (window.location.search.indexOf('edit_vr=') !== -1) {
                window.location.href = 'rappels-vendeur.php';
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
