<?php
/**
 * Images d'affichage du hero marketplace (page d'accueil)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/includes/upload_image_limits.php';
require_once dirname(__DIR__, 2) . '/includes/image_optimizer.php';
require_once dirname(__DIR__, 2) . '/models/model_marketplace_hero.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

$upload_base = dirname(__DIR__, 2) . '/upload/marketplace_hero';
if (!is_dir($upload_base)) {
    @mkdir($upload_base, 0755, true);
}

$flash_ok = '';
$flash_err = '';
$open_modal_on_load = false;

if (!empty($_SESSION['hero_affiches_flash']) && is_array($_SESSION['hero_affiches_flash'])) {
    $hf = $_SESSION['hero_affiches_flash'];
    unset($_SESSION['hero_affiches_flash']);
    if (($hf['type'] ?? '') === 'ok') {
        $flash_ok = (string) ($hf['msg'] ?? '');
    } elseif (($hf['type'] ?? '') === 'err') {
        $flash_err = (string) ($hf['msg'] ?? '');
    }
}
if (!empty($_SESSION['hero_affiches_open_modal'])) {
    $open_modal_on_load = true;
    unset($_SESSION['hero_affiches_open_modal']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg_ok = '';
    $msg_err = '';
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $msg_err = 'Jeton de sécurité invalide.';
    } elseif (isset($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        $row = marketplace_hero_get_by_id($did);
        if ($row && marketplace_hero_delete_by_id($did)) {
            image_optimizer_delete_with_variants('marketplace_hero/' . basename((string) $row['image']));
            $fn = $upload_base . DIRECTORY_SEPARATOR . basename((string) $row['image']);
            if (is_file($fn)) {
                @unlink($fn);
            }
            super_admin_log_action((int) $_SESSION['super_admin_id'], 'hero_affiche_supprimée', 'hero_affiche', $did, $row['image']);
            $msg_ok = 'Image supprimée.';
        } else {
            $msg_err = 'Suppression impossible.';
        }
    } elseif (isset($_POST['move_hero']) && isset($_POST['hero_id'])) {
        $hid = (int) $_POST['hero_id'];
        $dir = $_POST['move_hero'] === 'down' ? 'down' : 'up';
        if (marketplace_hero_move($hid, $dir)) {
            $msg_ok = 'Ordre mis à jour.';
        } else {
            $msg_err = 'Déplacement impossible.';
        }
    } elseif (!empty($_FILES['image']['name']) && isset($_FILES['image']['tmp_name'])) {
        $alt = isset($_POST['alt_text']) ? trim((string) $_POST['alt_text']) : '';
        if ($alt === '') {
            $alt = 'Bannière marketplace';
        }
        $result = upload_optimize_image_file($_FILES['image'], $upload_base, 'marketplace_hero', 'hero_');
        if (empty($result['success']) || empty($result['filename'])) {
            $msg_err = trim((string) ($result['message'] ?? ''));
            if ($msg_err === '') {
                $msg_err = 'Envoi du fichier invalide ou format non supporté (JPEG, PNG, WebP ou GIF — 20 Mo max).';
            }
        } else {
            $safe = (string) $result['filename'];
            $ord = marketplace_hero_next_ordre();
            $nid = marketplace_hero_insert($safe, $alt, $ord);
            if ($nid) {
                super_admin_log_action((int) $_SESSION['super_admin_id'], 'hero_affiche_ajoutée', 'hero_affiche', (int) $nid, $safe);
                $msg_ok = 'Image ajoutée et optimisée (WebP). Elle apparaît sur la page d’accueil.';
            } else {
                image_optimizer_delete_with_variants('marketplace_hero/' . $safe);
                $msg_err = 'Erreur base de données.';
            }
        }
    }

    if ($msg_ok !== '') {
        $_SESSION['hero_affiches_flash'] = ['type' => 'ok', 'msg' => $msg_ok];
    } elseif ($msg_err !== '') {
        $_SESSION['hero_affiches_flash'] = ['type' => 'err', 'msg' => $msg_err];
        if (isset($_FILES['image']['name']) && (string) $_FILES['image']['name'] !== ''
            && empty($_POST['delete_id']) && !isset($_POST['move_hero'])) {
            $_SESSION['hero_affiches_open_modal'] = true;
        }
    }

    header('Location: hero-affiches.php', true, 303);
    exit;
}

$list = marketplace_hero_list_all();
$csrf = super_admin_csrf_token();
$table_ok = marketplace_hero_table_exists();
$hero_count = count($list);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero accueil — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-hero-affiches.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-hero-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <p style="margin-bottom:0.75rem;"><a href="index.php" style="color:var(--couleur-dominante);font-weight:600;text-decoration:none;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a></p>
        <header class="sa-users-hero" style="margin-bottom:1rem;">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-panorama" aria-hidden="true"></i> Paramètres</p>
                    <h1 class="sa-users-hero__title">Hero de la page d’accueil</h1>
                    <p class="sa-users-hero__lead">
                        Gérez le carrousel affiché dans la section <strong>mp-hero</strong> de <strong>index.php</strong>.
                        Formats acceptés : JPEG, PNG, WebP, GIF — 20&nbsp;Mo max. Conversion WebP et compression automatiques à l’envoi.
                    </p>
                </div>
                <div class="sa-users-kpis" aria-label="Synthèse">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Bannières actives</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $hero_count; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="sa-hero-toolbar">
            <p class="sa-hero-toolbar__hint">
                <strong>Ordre d’affichage</strong> : la première image correspond à la première slide du carrousel sur le site.
                Utilisez les flèches sur chaque carte pour réorganiser.
            </p>
            <button type="button" class="sa-hero-btn-add" id="heroOpenModal" aria-haspopup="dialog" aria-controls="heroAddModal">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                Ajouter une image
            </button>
        </div>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-alert sa-alert--err"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!$table_ok): ?>
            <p class="sa-users-panel__meta" style="margin-bottom:16px;padding:16px;background:#fff3cd;border-radius:12px;">
                Table SQL absente. Exécutez : <code>php migrations/run_marketplace_hero_table.php</code>
            </p>
        <?php endif; ?>

        <section class="sa-hero-gallery" aria-labelledby="hero-gallery-title">
            <div class="sa-hero-gallery__head">
                <h2 id="hero-gallery-title" class="sa-hero-gallery__title">
                    <i class="fas fa-images" aria-hidden="true"></i>
                    Bannières en ligne
                </h2>
                <span class="sa-hero-gallery__badge"><?php echo (int) $hero_count; ?> image<?php echo $hero_count !== 1 ? 's' : ''; ?></span>
            </div>
            <div class="sa-hero-gallery__body">
                <?php if (empty($list)): ?>
                    <div class="sa-hero-empty">
                        <div class="sa-hero-empty__icon" aria-hidden="true"><i class="fas fa-image"></i></div>
                        <h3>Aucune bannière pour l’instant</h3>
                        <p>Le bandeau d’accueil affichera un fond neutre jusqu’à ce que vous ajoutiez au moins une image.</p>
                        <button type="button" class="sa-hero-btn-add" id="heroOpenModalEmpty" aria-haspopup="dialog" aria-controls="heroAddModal">
                            <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                            Ajouter la première image
                        </button>
                    </div>
                <?php else: ?>
                    <div class="sa-hero-grid">
                        <?php foreach ($list as $idx => $row): ?>
                            <?php
                            $rid = (int) $row['id'];
                            $img = upload_image_url('marketplace_hero/' . (string) $row['image'], 'md');
                            ?>
                            <article class="sa-hero-card">
                                <div class="sa-hero-card__thumb">
                                    <span class="sa-hero-card__order">#<?php echo (int) ($idx + 1); ?></span>
                                    <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $row['alt_text'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="225">
                                </div>
                                <div class="sa-hero-card__content">
                                    <div class="sa-hero-card__meta">ID <?php echo $rid; ?> · ordre <?php echo (int) $row['ordre']; ?></div>
                                    <div class="sa-hero-card__alt"><?php echo htmlspecialchars((string) $row['alt_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="sa-hero-card__actions">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="hero_id" value="<?php echo $rid; ?>">
                                            <input type="hidden" name="move_hero" value="up">
                                            <button type="submit" class="sa-hero-card__btn" <?php echo $idx === 0 ? 'disabled title="Déjà en tête"' : 'title="Monter"'; ?> aria-label="Monter"><i class="fas fa-arrow-up"></i></button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="hero_id" value="<?php echo $rid; ?>">
                                            <input type="hidden" name="move_hero" value="down">
                                            <button type="submit" class="sa-hero-card__btn" <?php echo $idx === count($list) - 1 ? 'disabled title="Déjà en bas"' : 'title="Descendre"'; ?> aria-label="Descendre"><i class="fas fa-arrow-down"></i></button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Supprimer cette image ?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo $rid; ?>">
                                            <button type="submit" class="sa-hero-card__btn sa-hero-card__btn--danger" title="Supprimer" aria-label="Supprimer"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <p class="sa-hero-back">
            <a href="../dashboard.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Tableau de bord</a>
        </p>
    </div>

    <!-- Modal plein écran : ajout d’image -->
    <div class="sa-hero-fs-modal" id="heroAddModal" role="presentation" aria-hidden="true" hidden data-open-on-load="<?php echo $open_modal_on_load ? '1' : '0'; ?>">
        <div class="sa-hero-fs-modal__backdrop" id="heroModalBackdrop" tabindex="-1" aria-hidden="true"></div>
        <div class="sa-hero-fs-modal__panel" role="dialog" aria-modal="true" aria-labelledby="heroModalTitle" tabindex="-1">
            <div class="sa-hero-fs-modal__header">
                <div class="sa-hero-fs-modal__titles">
                    <h2 id="heroModalTitle">Nouvelle bannière</h2>
                    <p>Ajoutez une image pour le carrousel du hero. Choisissez un fichier net et lisible ; le texte alternatif aide le référencement et l’accessibilité.</p>
                </div>
                <button type="button" class="sa-hero-fs-modal__close" id="heroModalClose" aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sa-hero-fs-modal__form-wrap">
                <form method="post" action="" enctype="multipart/form-data" class="sa-hero-fs-modal__form" id="heroAddForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="sa-hero-fs-modal__label" for="hero_alt_text">Texte alternatif</label>
                    <input type="text" class="sa-hero-fs-modal__input" name="alt_text" id="hero_alt_text" placeholder="Ex. : Promotion marketplace COLObanes" maxlength="250" autocomplete="off">

                    <div class="sa-hero-preview-zone">
                        <span class="sa-hero-fs-modal__label">Aperçu</span>
                        <div class="sa-hero-preview-box" id="heroPreviewBox">
                            <div class="sa-hero-preview-box__placeholder" id="heroPreviewPlaceholder">
                                <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                <span>Aucun fichier sélectionné</span>
                                <small>JPEG, PNG, WebP ou GIF — max. 20&nbsp;Mo</small>
                            </div>
                            <img id="heroPreviewImg" alt="" style="display:none;">
                        </div>
                        <div class="sa-hero-file-row">
                            <input type="file" class="sa-hero-file-input" name="image" id="hero_image_input" accept="image/jpeg,image/png,image/webp,image/gif" required aria-required="true">
                            <label for="hero_image_input" class="sa-hero-file-label">
                                <i class="fas fa-folder-open" aria-hidden="true"></i>
                                Parcourir…
                            </label>
                            <span class="sa-hero-file-name" id="heroFileName" aria-live="polite"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sa-hero-fs-modal__footer">
                <button type="button" class="sa-hero-fs-modal__btn-secondary" id="heroModalCancel">Annuler</button>
                <button type="submit" class="sa-hero-fs-modal__btn-primary" id="heroModalSubmit" form="heroAddForm">
                    <i class="fas fa-check" aria-hidden="true"></i>
                    Enregistrer la bannière
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        (function () {
            var modal = document.getElementById('heroAddModal');
            var openBtn = document.getElementById('heroOpenModal');
            var openEmpty = document.getElementById('heroOpenModalEmpty');
            var closeBtn = document.getElementById('heroModalClose');
            var cancelBtn = document.getElementById('heroModalCancel');
            var backdrop = document.getElementById('heroModalBackdrop');
            var form = document.getElementById('heroAddForm');
            var fileInput = document.getElementById('hero_image_input');
            var previewBox = document.getElementById('heroPreviewBox');
            var previewImg = document.getElementById('heroPreviewImg');
            var placeholder = document.getElementById('heroPreviewPlaceholder');
            var fileNameEl = document.getElementById('heroFileName');
            var objectUrl = null;
            var lastFocus = null;

            function revokePreview() {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
            }

            function resetFormUi() {
                if (form) {
                    form.reset();
                }
                revokePreview();
                if (previewImg) {
                    previewImg.removeAttribute('src');
                    previewImg.style.display = 'none';
                }
                if (placeholder) {
                    placeholder.style.display = 'block';
                }
                if (previewBox) {
                    previewBox.classList.remove('has-file');
                }
                if (fileNameEl) {
                    fileNameEl.textContent = '';
                }
            }

            function openModal() {
                if (!modal) return;
                lastFocus = document.activeElement;
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                var panel = modal.querySelector('.sa-hero-fs-modal__panel');
                if (panel) {
                    panel.focus();
                }
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modal.hidden = true;
                document.body.style.overflow = '';
                resetFormUi();
                if (lastFocus && typeof lastFocus.focus === 'function') {
                    lastFocus.focus();
                }
            }

            function onFileChange() {
                revokePreview();
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    if (previewImg) {
                        previewImg.removeAttribute('src');
                        previewImg.style.display = 'none';
                    }
                    if (placeholder) placeholder.style.display = 'block';
                    if (previewBox) previewBox.classList.remove('has-file');
                    if (fileNameEl) fileNameEl.textContent = '';
                    return;
                }
                var f = fileInput.files[0];
                if (fileNameEl) {
                    fileNameEl.textContent = f.name;
                }
                objectUrl = URL.createObjectURL(f);
                if (previewImg) {
                    previewImg.src = objectUrl;
                    previewImg.style.display = 'block';
                }
                if (placeholder) placeholder.style.display = 'none';
                if (previewBox) previewBox.classList.add('has-file');
            }

            if (openBtn) openBtn.addEventListener('click', openModal);
            if (openEmpty) openEmpty.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);
            if (fileInput) fileInput.addEventListener('change', onFileChange);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            if (modal && modal.getAttribute('data-open-on-load') === '1') {
                openModal();
            }
        })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
