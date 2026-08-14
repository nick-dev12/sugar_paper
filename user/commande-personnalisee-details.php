<?php
/**
 * Détails d'une commande personnalisée (côté client)
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

$cp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cp_id <= 0) {
    header('Location: mes-commandes.php');
    exit;
}

require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_livreur_tracking.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
$cp = get_commande_personnalisee_by_id($cp_id);

if (!$cp || $cp['user_id'] != $_SESSION['user_id']) {
    header('Location: mes-commandes.php');
    exit;
}

$statuts_labels = get_statuts_commande_personnalisee();
$cp_images = parse_commande_personnalisee_images($cp['image_reference'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Demande #<?php echo $cp['id']; ?> - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-palette"></i> Ma commande personnalisée</h1>
        <div class="header-actions">
            <?php if (livreur_client_peut_suivre_gps_cp($cp)): ?>
                <a href="suivi-commande-personnalisee.php?id=<?php echo (int) $cp['id']; ?>" class="btn-primary">
                    <i class="fas fa-location-dot"></i> Suivre la livraison
                </a>
            <?php endif; ?>
            <a href="mes-commandes.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>

    <?php if (isset($_GET['suivi']) && $_GET['suivi'] === 'indisponible'): ?>
    <div class="message error">
        <i class="fas fa-info-circle"></i>
        <span>Le suivi GPS n'est pas encore disponible. Il s'affichera dès que le livreur aura démarré la livraison.</span>
    </div>
    <?php endif; ?>

    <section class="content-section">
        <div class="commande-perso-detail-card">
            <div class="cp-detail-header">
                <h2>Demande #<?php echo $cp['id']; ?></h2>
                <span class="commande-statut statut-<?php echo $cp['statut']; ?>">
                    <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                </span>
            </div>
            <div class="cp-detail-body">
                <div class="detail-item">
                    <label>Description</label>
                    <div class="value"><?php echo nl2br(htmlspecialchars($cp['description'])); ?></div>
                </div>
                <?php if (!empty($cp_images)): ?>
                <div class="detail-item detail-item-images">
                    <label>Images d'inspiration (<?php echo count($cp_images); ?>)</label>
                    <div class="cp-user-images-grid">
                        <?php foreach ($cp_images as $img_index => $img_path): ?>
                        <button type="button" class="cp-user-image-trigger"
                            data-image-src="<?php echo htmlspecialchars(upload_image_url($img_path, 'original')); ?>"
                            aria-label="Agrandir l'image <?php echo (int) $img_index + 1; ?>">
                            <img src="<?php echo htmlspecialchars(upload_image_url($img_path, 'sm')); ?>"
                                alt="Image d'inspiration <?php echo (int) $img_index + 1; ?>"
                                loading="lazy"
                                onerror="this.src='/image/produit1.jpg'">
                            <span class="cp-user-image-zoom" aria-hidden="true"><i class="fas fa-search-plus"></i></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($cp['type_produit']): ?>
                <div class="detail-item">
                    <label>Type de produit</label>
                    <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($cp['quantite']): ?>
                <div class="detail-item">
                    <label>Quantité souhaitée</label>
                    <div class="value"><?php echo htmlspecialchars($cp['quantite']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($cp['date_souhaitee']): ?>
                <div class="detail-item">
                    <label>Date souhaitée</label>
                    <div class="value"><?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <label>Date de demande</label>
                    <div class="value"><?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($cp_images)): ?>
    <div class="cp-user-image-modal" id="cpUserImageModal" hidden aria-hidden="true">
        <div class="cp-user-image-modal-backdrop" data-close-user-image-modal="1"></div>
        <div class="cp-user-image-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpUserImageModalTitle">
            <button type="button" class="cp-user-image-modal-close" id="cpUserImageModalClose" aria-label="Fermer l'image">
                <i class="fas fa-times"></i>
            </button>
            <div class="cp-user-image-modal-header">
                <h3 id="cpUserImageModalTitle"><i class="fas fa-image"></i> Image d'inspiration</h3>
                <p>Demande #<?php echo (int) $cp['id']; ?></p>
            </div>
            <div class="cp-user-image-modal-body">
                <img id="cpUserImageModalPreview" src="<?php echo htmlspecialchars(upload_image_url($cp_images[0], 'original')); ?>"
                    alt="Image d'inspiration de la demande personnalisée"
                    onerror="this.src='/image/produit1.jpg'">
            </div>
        </div>
    </div>
    <script>
        (function () {
            var modal = document.getElementById('cpUserImageModal');
            var triggers = document.querySelectorAll('.cp-user-image-trigger[data-image-src]');
            var closeButton = document.getElementById('cpUserImageModalClose');
            var closeBackdrop = modal ? modal.querySelector('[data-close-user-image-modal="1"]') : null;
            var previewImage = document.getElementById('cpUserImageModalPreview');

            function openModal(src) {
                if (!modal || !previewImage || !src) {
                    return;
                }
                previewImage.src = src;
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                if (!modal) {
                    return;
                }
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    openModal(trigger.getAttribute('data-image-src'));
                });
            });

            if (closeButton) {
                closeButton.addEventListener('click', closeModal);
            }
            if (closeBackdrop) {
                closeBackdrop.addEventListener('click', closeModal);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal && !modal.hidden) {
                    closeModal();
                }
            });
        })();
    </script>
    <?php endif; ?>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
