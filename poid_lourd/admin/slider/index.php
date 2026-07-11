<?php
/**
 * Gestion du slider — redesign v2
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';

require_once __DIR__ . '/../../includes/flash_toast.php';
require_once __DIR__ . '/../../includes/upload_image_limits.php';
require_once __DIR__ . '/../../controllers/controller_slider.php';
require_once __DIR__ . '/../../models/model_slider.php';

if (isset($_SESSION['success_message'])) {
    flash_toast_push('success', (string) $_SESSION['success_message']);
    unset($_SESSION['success_message']);
}

$show_add_modal = isset($_GET['ajouter']) && $_GET['ajouter'] === '1';
$show_edit_modal = false;
$edit_slide_id = isset($_GET['modifier']) ? (int) $_GET['modifier'] : 0;
$edit_slide = null;
$edit_slide_img = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slide_image'])) {
    $add_result = process_add_slide_image_only();
    if (!empty($add_result['success'])) {
        flash_toast_push('success', $add_result['message']);
        header('Location: index.php');
        exit;
    }
    if (!empty($add_result['message'])) {
        flash_toast_queue_page('error', str_replace('<br>', ' — ', $add_result['message']));
    }
    $show_add_modal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_slide_image'])) {
    $posted_slide_id = (int) ($_POST['slide_id'] ?? 0);
    $edit_result = process_update_slide_image_only($posted_slide_id);
    if (!empty($edit_result['success'])) {
        flash_toast_push('success', $edit_result['message']);
        header('Location: index.php');
        exit;
    }
    if (!empty($edit_result['message'])) {
        flash_toast_queue_page('error', str_replace('<br>', ' — ', $edit_result['message']));
    }
    $edit_slide_id = $posted_slide_id;
    $show_edit_modal = true;
}

if ($edit_slide_id > 0) {
    $edit_slide = get_slide_by_id($edit_slide_id);
    if (!$edit_slide || !slider_admin_can_manage_slide($edit_slide)) {
        $edit_slide = null;
        $edit_slide_id = 0;
        $show_edit_modal = false;
    } else {
        $edit_slide_img = '/upload/slider/' . (string) ($edit_slide['image'] ?? '');
        if (isset($_GET['modifier'])) {
            $show_edit_modal = true;
        }
    }
}

$slides = get_all_slides(null);
$is_vendeur = isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] ?? '') === 'vendeur' && !empty($_SESSION['admin_id']);
if ($is_vendeur) {
    $mid    = (int)$_SESSION['admin_id'];
    $slides = array_values(array_filter($slides, fn($s) => isset($s['admin_id']) && (int)$s['admin_id'] === $mid));
}

$nb_total  = count($slides);
$nb_actif  = count(array_filter($slides, fn($s) => ($s['statut'] ?? '') === 'actif'));
$nb_inactif = $nb_total - $nb_actif;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des affiches publicitaires &mdash; Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-slider-add-modal.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== SLIDER INDEX v2 ===== */

        .sl-page {
            max-width: 1080px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 90px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        /* ---- Header ---- */
        .sl-page-header {
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }

        .sl-page-header__left { display: flex; flex-direction: column; gap: 3px; }

        .sl-page-header__eyebrow {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; gap: 5px;
        }

        .sl-page-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        .sl-page-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons ---- */
        .sl-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; white-space: nowrap;
        }

        .sl-btn--primary { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .sl-btn--primary:hover { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .sl-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .sl-btn--outline:hover { background: rgba(53,100,166,0.05); }

        /* ---- Hero ---- */
        .sl-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 55%, #4f46e5 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative; overflow: hidden;
            box-shadow: 0 16px 44px rgba(67,56,202,0.3);
        }

        .sl-hero::before {
            content: ''; position: absolute; top: -60px; right: -40px;
            width: 230px; height: 230px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%; pointer-events: none;
        }

        .sl-hero::after {
            content: ''; position: absolute; bottom: -70px; right: 90px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%; pointer-events: none;
        }

        .sl-hero__inner {
            display: flex; align-items: flex-start;
            justify-content: space-between; flex-wrap: wrap; gap: 16px;
            position: relative;
        }

        .sl-hero__label {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: rgba(255,255,255,0.55); margin-bottom: 5px;
        }

        .sl-hero__count {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 900; color: #fff;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.0; letter-spacing: -0.03em;
        }

        .sl-hero__sub { font-size: 0.8rem; color: rgba(255,255,255,0.58); margin-top: 4px; }

        .sl-hero__pills { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }

        .sl-hero__pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 50px; padding: 6px 16px;
            display: flex; align-items: center; gap: 7px;
            color: #fff; font-size: 0.78rem; font-weight: 600;
        }

        .sl-hero__pill--on  { background: rgba(134,239,172,0.18); border-color: rgba(134,239,172,0.3); }
        .sl-hero__pill--off { background: rgba(255,255,255,0.08); }

        .sl-hero__cta {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 22px;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.22);
            border-radius: 12px; color: #fff;
            font-size: 0.83rem; font-weight: 700;
            text-decoration: none; transition: background 0.2s;
            white-space: nowrap; align-self: flex-start;
            cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .sl-hero__cta:hover { background: rgba(255,255,255,0.26); }

        /* ---- Stat cards ---- */
        .sl-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 13px;
        }

        .sl-stat {
            background: #fff; border-radius: 16px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            padding: 18px 16px;
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .sl-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(53,100,166,0.12); }

        .sl-stat__icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .sl-stat--total   .sl-stat__icon { background: rgba(67,56,202,0.1); color: #4338ca; }
        .sl-stat--actif   .sl-stat__icon { background: rgba(34,197,94,0.1); color: #15803d; }
        .sl-stat--inactif .sl-stat__icon { background: rgba(156,163,175,0.15); color: #6b7280; }

        .sl-stat__val { font-size: 1.6rem; font-weight: 900; color: var(--titres, #0d0d0d); line-height: 1.0; font-family: var(--font-titres, 'Poppins', sans-serif); }
        .sl-stat__lbl { font-size: 0.72rem; font-weight: 700; color: var(--gris-moyen, #737373); text-transform: uppercase; letter-spacing: 0.06em; }

        /* ---- Alert success ---- */
        .sl-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 0.84rem; font-weight: 500;
            background: rgba(34,197,94,0.09);
            border: 1px solid rgba(34,197,94,0.22); color: #15803d;
        }

        .sl-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        /* ---- Section head ---- */
        .sl-section-head {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }

        .sl-section-head__title {
            font-size: 1.05rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            display: flex; align-items: center; gap: 8px;
        }

        .sl-section-head__title::before {
            content: ''; display: inline-block;
            width: 4px; height: 18px; border-radius: 3px;
            background: #4338ca;
        }

        /* ---- Grille de slides ---- */
        .sl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 16px;
        }

        /* ---- Carte slide ---- */
        .sl-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex; flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .sl-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(53,100,166,0.13); }

        .sl-card--inactive { opacity: 0.72; }

        /* Image */
        .sl-card__img-wrap {
            position: relative;
            aspect-ratio: 16 / 7;
            overflow: hidden;
            background: #f1f5f9;
        }

        .sl-card__img {
            width: 100%; height: 100%; object-fit: contain;
            transition: transform 0.35s;
            background: #f1f5f9;
        }

        .sl-card:hover .sl-card__img { transform: none; }

        /* Overlay ordre */
        .sl-card__order-badge {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(6px);
            color: #fff; font-size: 0.72rem; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            display: flex; align-items: center; gap: 4px;
        }

        /* Badge statut */
        .sl-card__status-badge {
            position: absolute; top: 10px; right: 10px;
            font-size: 0.69rem; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 4px;
        }

        .sl-card__status-badge--actif {
            background: rgba(34,197,94,0.18);
            backdrop-filter: blur(6px);
            color: #fff;
            border: 1px solid rgba(134,239,172,0.3);
        }

        .sl-card__status-badge--inactif {
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(6px);
            color: rgba(255,255,255,0.8);
        }

        /* Body */
        .sl-card__body { padding: 16px 18px 14px; flex: 1; display: flex; flex-direction: column; gap: 7px; }

        .sl-card__title {
            font-size: 0.93rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.2;
            display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .sl-card__para {
            font-size: 0.78rem; color: var(--gris-moyen, #737373);
            line-height: 1.5;
            display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            flex: 1;
        }

        .sl-card__btn-info {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.73rem; color: #4338ca; font-weight: 600;
            background: rgba(67,56,202,0.08); border-radius: 7px;
            padding: 4px 10px; width: fit-content;
        }

        /* Footer actions */
        .sl-card__footer {
            display: flex; gap: 8px;
            padding: 12px 18px;
            border-top: 1px solid rgba(53,100,166,0.07);
            background: rgba(53,100,166,0.02);
        }

        .sl-card-btn {
            flex: 1; display: inline-flex; align-items: center;
            justify-content: center; gap: 6px;
            padding: 8px 12px; border-radius: 9px;
            font-size: 0.79rem; font-weight: 700;
            text-decoration: none;
            transition: all 0.18s; border: none; cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            background: transparent;
        }

        .sl-card-btn--edit   { background: rgba(67,56,202,0.1); color: #4338ca; }
        .sl-card-btn--edit:hover   { background: #4338ca; color: #fff; }
        .sl-card-btn--delete { background: rgba(239,68,68,0.08); color: #b91c1c; }
        .sl-card-btn--delete:hover { background: #ef4444; color: #fff; }

        /* ---- Empty state ---- */
        .sl-empty {
            background: #fff; border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            padding: 60px 24px; text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .sl-empty__icon {
            width: 70px; height: 70px; border-radius: 18px;
            background: rgba(67,56,202,0.08); color: #4338ca;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.9rem; margin: 0 auto 18px;
        }

        .sl-empty h3 { font-size: 1.05rem; font-weight: 700; color: var(--titres, #0d0d0d); margin-bottom: 7px; }
        .sl-empty p  { font-size: 0.86rem; max-width: 340px; margin: 0 auto 20px; }

        /* ---- Responsive ---- */
        @media (max-width: 600px) {
            .sl-stats { grid-template-columns: 1fr 1fr; }
            .sl-grid  { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="sl-page">

        <!-- ===== PAGE HEADER ===== -->
        <header class="sl-page-header">
            <div class="sl-page-header__left">
                <p class="sl-page-header__eyebrow">
                    <i class="fas fa-images"></i> Contenu publicitaire
                </p>
                <h1 class="sl-page-header__title">Gestion des affiches publicitaires</h1>
            </div>
            <div class="sl-page-header__actions">
                <a href="../parametres.php" class="sl-btn sl-btn--outline">
                    <i class="fas fa-arrow-left"></i> Param&egrave;tres
                </a>
            </div>
        </header>

        <!-- ===== HERO ===== -->
        <div class="sl-hero">
            <div class="sl-hero__inner">
                <div>
                    <p class="sl-hero__label">Carrousel principal</p>
                    <div class="sl-hero__count"><?php echo $nb_total; ?></div>
                </div>
                <button type="button" class="sl-hero__cta" data-sl-open-add>
                    <i class="fas fa-plus"></i> Ajouter une affiche publicitaire
                </button>
            </div>
        </div>

        <!-- ===== STAT CARDS ===== -->
        <div class="sl-stats">
            <div class="sl-stat sl-stat--total">
                <div class="sl-stat__icon"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="sl-stat__val"><?php echo $nb_total; ?></div>
                    <div class="sl-stat__lbl">Total</div>
                </div>
            </div>
            <div class="sl-stat sl-stat--actif">
                <div class="sl-stat__icon"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="sl-stat__val"><?php echo $nb_actif; ?></div>
                    <div class="sl-stat__lbl">Actifs</div>
                </div>
            </div>
            <div class="sl-stat sl-stat--inactif">
                <div class="sl-stat__icon"><i class="fas fa-eye-slash"></i></div>
                <div>
                    <div class="sl-stat__val"><?php echo $nb_inactif; ?></div>
                    <div class="sl-stat__lbl">Inactifs</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE AFFICHES ===== -->
        <?php if (empty($slides)): ?>

            <div class="sl-empty">
                <div class="sl-empty__icon"><i class="fas fa-images"></i></div>
                <h3>Aucune affiche publicitaire enregistr&eacute;e</h3>
                <p>Ajoutez votre premi&egrave;re affiche publicitaire pour l&rsquo;afficher sur la page d&rsquo;accueil.</p>
                <button type="button" class="sl-btn sl-btn--primary" data-sl-open-add>
                    <i class="fas fa-plus"></i> Ajouter la premi&egrave;re affiche publicitaire
                </button>
            </div>

        <?php else: ?>

            <div class="sl-section-head">
                <h2 class="sl-section-head__title">
                    Affiches publicitaires du carrousel (<?php echo $nb_total; ?>)
                </h2>
            </div>

            <div class="sl-grid">
                <?php foreach ($slides as $slide):
                    $statut      = $slide['statut'] ?? 'inactif';
                    $is_actif    = $statut === 'actif';
                    $titre_safe  = htmlspecialchars($slide['titre'] ?? '');
                    $para_safe   = htmlspecialchars($slide['paragraphe'] ?? '');
                    $img_path    = '/upload/slider/' . htmlspecialchars($slide['image'] ?? '');
                    $has_card_text = $titre_safe !== '' || $para_safe !== '' || !empty($slide['bouton_texte']);
                ?>
                    <article class="sl-card <?php echo !$is_actif ? 'sl-card--inactive' : ''; ?>">

                        <!-- Image -->
                        <div class="sl-card__img-wrap">
                            <img src="<?php echo $img_path; ?>"
                                alt="<?php echo $titre_safe; ?>"
                                class="sl-card__img"
                                onerror="this.src='/image/produit1.jpg'">

                            <span class="sl-card__order-badge">
                                <i class="fas fa-sort"></i> #<?php echo (int)$slide['ordre']; ?>
                            </span>

                            <span class="sl-card__status-badge sl-card__status-badge--<?php echo htmlspecialchars($statut); ?>">
                                <i class="fas <?php echo $is_actif ? 'fa-circle-check' : 'fa-circle-pause'; ?>"
                                    style="font-size:.65rem;"></i>
                                <?php echo $is_actif ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </div>

                        <!-- Body -->
                        <?php if ($has_card_text): ?>
                        <div class="sl-card__body">
                            <?php if ($titre_safe !== ''): ?>
                                <h3 class="sl-card__title"><?php echo $titre_safe; ?></h3>
                            <?php endif; ?>
                            <?php if ($para_safe !== ''): ?>
                                <p class="sl-card__para"><?php echo $para_safe; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($slide['bouton_texte'])): ?>
                                <span class="sl-card__btn-info">
                                    <i class="fas fa-arrow-pointer"></i>
                                    <?php echo htmlspecialchars($slide['bouton_texte']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Footer -->
                        <div class="sl-card__footer">
                            <button type="button"
                                class="sl-card-btn sl-card-btn--edit"
                                data-sl-open-edit
                                data-slide-id="<?php echo (int) $slide['id']; ?>"
                                data-slide-img="/upload/slider/<?php echo htmlspecialchars((string) ($slide['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-pen-to-square"></i> Modifier
                            </button>
                            <a href="supprimer.php?id=<?php echo (int)$slide['id']; ?>"
                                class="sl-card-btn sl-card-btn--delete"
                                onclick="return confirm('Supprimer cette affiche publicitaire définitivement ?');">
                                <i class="fas fa-trash"></i> Supprimer
                            </a>
                        </div>

                    </article>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div><!-- /.sl-page -->

    <div class="sl-add-overlay" id="slAddOverlay"
        <?php echo $show_add_modal ? '' : 'hidden'; ?>
        aria-hidden="<?php echo $show_add_modal ? 'false' : 'true'; ?>"
        data-open-on-load="<?php echo $show_add_modal ? '1' : '0'; ?>">
        <div class="sl-add-modal" role="dialog" aria-modal="true" aria-labelledby="slAddTitle">
            <header class="sl-add-modal__head">
                <div>
                    <p class="sl-add-modal__eyebrow"><i class="fas fa-image"></i> Nouvelle affiche publicitaire</p>
                    <h2 class="sl-add-modal__title" id="slAddTitle">Ajouter une affiche publicitaire</h2>
                    <p class="sl-add-modal__sub">Importez votre visuel — il sera affiché dans le carrousel de votre vitrine.</p>
                </div>
                <button type="button" class="sl-add-modal__close" data-sl-close-modal aria-label="Fermer">
                    <i class="fas fa-xmark"></i>
                </button>
            </header>
            <div class="sl-add-modal__body">
                <form method="POST" action="index.php" enctype="multipart/form-data" id="slAddForm">
                    <input type="hidden" name="add_slide_image" value="1">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) UPLOAD_MAX_IMAGE_BYTES; ?>">

                    <div class="sl-add-drop" id="slAddDrop">
                        <input type="file" class="sl-add-drop__input" id="slAddImage" name="image"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/avif" required
                            aria-label="Choisir une image d'affiche publicitaire">

                        <div class="sl-add-drop__placeholder">
                            <span class="sl-add-drop__icon"><i class="fas fa-cloud-arrow-up"></i></span>
                            <span class="sl-add-drop__label">Glissez votre image ici</span>
                            <span class="sl-add-drop__hint">ou cliquez pour parcourir — JPEG, PNG, GIF, WEBP, AVIF (max. 20 Mo)</span>
                        </div>

                        <div class="sl-add-preview" id="slAddPreview">
                            <img src="" alt="" class="sl-add-preview__img" id="slAddPreviewImg">
                            <span class="sl-add-preview__change"><i class="fas fa-pen"></i> Changer</span>
                        </div>
                    </div>

                    <div class="sl-add-modal__actions">
                        <button type="button" class="sl-add-modal__btn sl-add-modal__btn--ghost" data-sl-close-modal>
                            Annuler
                        </button>
                        <button type="submit" class="sl-add-modal__btn sl-add-modal__btn--primary" id="slAddSubmit" disabled>
                            <i class="fas fa-check"></i> Enregistrer l'affiche publicitaire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="sl-add-overlay" id="slEditOverlay"
        <?php echo $show_edit_modal ? '' : 'hidden'; ?>
        aria-hidden="<?php echo $show_edit_modal ? 'false' : 'true'; ?>"
        data-open-on-load="<?php echo $show_edit_modal ? '1' : '0'; ?>">
        <div class="sl-add-modal" role="dialog" aria-modal="true" aria-labelledby="slEditTitle">
            <header class="sl-add-modal__head">
                <div>
                    <p class="sl-add-modal__eyebrow"><i class="fas fa-pen-to-square"></i> Modification</p>
                    <h2 class="sl-add-modal__title" id="slEditTitle">Modifier l'affiche publicitaire</h2>
                    <p class="sl-add-modal__sub">Remplacez l'image actuelle par un nouveau visuel pour votre carrousel.</p>
                </div>
                <button type="button" class="sl-add-modal__close" data-sl-close-modal aria-label="Fermer">
                    <i class="fas fa-xmark"></i>
                </button>
            </header>
            <div class="sl-add-modal__body">
                <form method="POST" action="index.php" enctype="multipart/form-data" id="slEditForm">
                    <input type="hidden" name="edit_slide_image" value="1">
                    <input type="hidden" name="slide_id" id="slEditSlideId"
                        value="<?php echo $show_edit_modal ? (int) $edit_slide_id : 0; ?>"
                        <?php if ($show_edit_modal && $edit_slide_img !== ''): ?>
                        data-initial-img="<?php echo htmlspecialchars($edit_slide_img, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php endif; ?>>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) UPLOAD_MAX_IMAGE_BYTES; ?>">

                    <div class="sl-add-drop<?php echo ($show_edit_modal && $edit_slide_img !== '') ? ' is-has-preview' : ''; ?>" id="slEditDrop">
                        <input type="file" class="sl-add-drop__input" id="slEditImage" name="image"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/avif" required
                            aria-label="Choisir une nouvelle image d'affiche publicitaire">

                        <div class="sl-add-drop__placeholder">
                            <span class="sl-add-drop__icon"><i class="fas fa-cloud-arrow-up"></i></span>
                            <span class="sl-add-drop__label">Glissez la nouvelle image ici</span>
                            <span class="sl-add-drop__hint">ou cliquez pour parcourir — JPEG, PNG, GIF, WEBP, AVIF (max. 20 Mo)</span>
                        </div>

                        <div class="sl-add-preview" id="slEditPreview" style="<?php echo ($show_edit_modal && $edit_slide_img !== '') ? 'display:block;' : ''; ?>">
                            <img src="<?php echo $show_edit_modal && $edit_slide_img !== '' ? htmlspecialchars($edit_slide_img, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                alt="Image actuelle" class="sl-add-preview__img" id="slEditPreviewImg">
                            <span class="sl-add-preview__change"><i class="fas fa-pen"></i> Changer</span>
                        </div>
                    </div>

                    <div class="sl-add-modal__actions">
                        <button type="button" class="sl-add-modal__btn sl-add-modal__btn--ghost" data-sl-close-modal>
                            Annuler
                        </button>
                        <button type="submit" class="sl-add-modal__btn sl-add-modal__btn--primary" id="slEditSubmit" disabled>
                            <i class="fas fa-check"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="/js/admin-slider-modal.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
