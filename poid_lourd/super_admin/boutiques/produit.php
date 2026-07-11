<?php
/**
 * Détail produit vendeur — Super Admin (modération, toutes les images).
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produits.php';
require_once dirname(__DIR__, 2) . '/includes/image_optimizer.php';
require_once dirname(__DIR__, 2) . '/includes/marketplace_helpers.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$vendeur_id = isset($_GET['vendeur_id']) ? (int) $_GET['vendeur_id'] : 0;

$produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;
if (!$produit || $vendeur_id <= 0 || (int) ($produit['admin_id'] ?? 0) !== $vendeur_id) {
    header('Location: index.php');
    exit;
}

$b = super_admin_get_boutique_stats($vendeur_id);
if (!$b) {
    header('Location: index.php');
    exit;
}

$moderation_ok = produit_moderation_plateforme_active();
$csrf = super_admin_csrf_token();
$gallery = produit_images_list_from_row($produit);
$titre_boutique = (string) ($b['boutique_nom'] ?: $b['nom']);
$vitrine = !empty($b['boutique_slug']) ? boutique_url('index.php', (string) $b['boutique_slug']) : '';
$pst = (string) ($produit['statut'] ?? '');
$is_bloque = ($pst === 'bloque');
$prix_affichage = !empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < (float) $produit['prix']
    ? (float) $produit['prix_promotion']
    : (float) ($produit['prix'] ?? 0);
$has_promotion = !empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < (float) $produit['prix'];
$public_url = '/produit.php?id=' . $produit_id;
$dc_mod = !empty($produit['date_modification']) ? date('d/m/Y à H:i', strtotime((string) $produit['date_modification'])) : '—';
$dc_crea = !empty($produit['date_creation']) ? date('d/m/Y à H:i', strtotime((string) $produit['date_creation'])) : '—';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string) ($produit['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8'); ?> — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-boutique-detail.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-boutique-detail sa-produit-detail">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero sa-prod-hero">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow">
                        <a href="detail.php?id=<?php echo $vendeur_id; ?>" class="sa-prod-back"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($titre_boutique, ENT_QUOTES, 'UTF-8'); ?></a>
                    </p>
                    <h1 class="sa-users-hero__title"><?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <div class="sa-prod-hero-meta">
                        <span class="sa-mp-card-statut sa-mp-card-statut--<?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(produit_statut_label($pst), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php if (!empty($produit['identifiant_interne'])): ?>
                        <span class="sa-prod-ref"><i class="fas fa-barcode"></i> <?php echo htmlspecialchars((string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sa-bd-hero-actions">
                        <a class="sa-bd-btn sa-bd-btn--accent" href="<?php echo htmlspecialchars($public_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-external-link-alt"></i> Voir sur le site
                        </a>
                        <?php if ($vitrine !== ''): ?>
                        <a class="sa-bd-btn sa-bd-btn--ghost" href="<?php echo htmlspecialchars($vitrine, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-store"></i> Vitrine boutique
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sa-prod-hero-price">
                    <?php if ($has_promotion): ?>
                    <span class="sa-prod-price-old"><?php echo number_format((float) $produit['prix'], 0, ',', ' '); ?> FCFA</span>
                    <?php endif; ?>
                    <span class="sa-prod-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> <small>FCFA</small></span>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok" role="status"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-alert sa-alert--err" role="alert"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>

        <?php if ($is_bloque): ?>
        <div class="sa-alert sa-alert--err" role="alert">
            <i class="fas fa-ban"></i>
            <span>
                <strong>Produit bloqué par la plateforme.</strong>
                <?php if (!empty($produit['bloque_motif'])): ?>
                    Motif : <?php echo htmlspecialchars((string) $produit['bloque_motif'], ENT_QUOTES, 'UTF-8'); ?>.
                <?php endif; ?>
                <?php
                $lbls = produit_bloque_champs_labels((string) ($produit['bloque_champs'] ?? ''));
                if (!empty($lbls)):
                    ?>
                    À corriger : <strong><?php echo htmlspecialchars(implode(', ', $lbls), ENT_QUOTES, 'UTF-8'); ?></strong>.
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <div class="sa-prod-layout">
            <section class="sa-prod-gallery-card" aria-labelledby="sa-prod-gallery-title">
                <h2 id="sa-prod-gallery-title"><i class="fas fa-images"></i> Images (<?php echo count($gallery); ?>)</h2>
                <?php if (!empty($gallery)): ?>
                <div class="sa-prod-gallery-main">
                    <img src="<?php echo htmlspecialchars(upload_image_url($gallery[0], 'original'), ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        id="saProdMainImg"
                        onerror="this.src='/image/produit1.jpg'">
                </div>
                <?php if (count($gallery) > 1): ?>
                <div class="sa-prod-gallery-thumbs">
                    <?php foreach ($gallery as $gidx => $gpath): ?>
                    <button type="button"
                        class="sa-prod-thumb<?php echo $gidx === 0 ? ' is-active' : ''; ?>"
                        data-src="<?php echo htmlspecialchars(upload_image_url($gpath, 'original'), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="Image <?php echo (int) $gidx + 1; ?>">
                        <img src="<?php echo htmlspecialchars(upload_image_url($gpath, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="" loading="lazy" onerror="this.src='/image/produit1.jpg'">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="sa-prod-gallery-all">
                    <?php foreach ($gallery as $gidx => $gpath): ?>
                    <a href="<?php echo htmlspecialchars(upload_image_url($gpath, 'original'), ENT_QUOTES, 'UTF-8'); ?>"
                        class="sa-prod-gallery-all-item" target="_blank" rel="noopener noreferrer"
                        title="Ouvrir image <?php echo (int) $gidx + 1; ?> en taille réelle">
                        <img src="<?php echo htmlspecialchars(upload_image_url($gpath, 'md'), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Image <?php echo (int) $gidx + 1; ?>" loading="lazy"
                            onerror="this.src='/image/produit1.jpg'">
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="sa-prod-no-img"><i class="fas fa-image"></i> Aucune image</p>
                <?php endif; ?>
            </section>

            <section class="sa-prod-info-card" aria-labelledby="sa-prod-info-title">
                <h2 id="sa-prod-info-title"><i class="fas fa-info-circle"></i> Informations</h2>
                <dl class="sa-prod-dl">
                    <div><dt>Catégorie</dt><dd><?php echo htmlspecialchars((string) ($produit['categorie_nom'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Stock</dt><dd><?php echo (int) ($produit['stock'] ?? 0); ?></dd></div>
                    <div><dt>Prix catalogue</dt><dd><?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?> FCFA</dd></div>
                    <?php if ($has_promotion): ?>
                    <div><dt>Prix promo</dt><dd><?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA</dd></div>
                    <?php endif; ?>
                    <div><dt>Unité</dt><dd><?php echo htmlspecialchars((string) ($produit['unite'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Poids</dt><dd><?php echo htmlspecialchars((string) ($produit['poids'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Créé le</dt><dd><?php echo htmlspecialchars($dc_crea, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Modifié le</dt><dd><?php echo htmlspecialchars($dc_mod, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                </dl>
                <?php if (!empty($produit['description'])): ?>
                <h3 class="sa-prod-desc-title">Description</h3>
                <div class="sa-prod-desc"><?php echo nl2br(htmlspecialchars((string) $produit['description'], ENT_QUOTES, 'UTF-8')); ?></div>
                <?php endif; ?>

                <?php if ($moderation_ok): ?>
                <h3 class="sa-prod-mod-title">Modération plateforme</h3>
                <?php
                $return_to = 'produit';
                require dirname(__DIR__, 2) . '/includes/partials/super_admin_produit_moderation.php';
                ?>
                <?php else: ?>
                <div class="sa-alert sa-alert--err" role="alert">
                    <i class="fas fa-database"></i>
                    <span>Migration blocage non exécutée.</span>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
    (function () {
        var main = document.getElementById('saProdMainImg');
        if (!main) return;
        document.querySelectorAll('.sa-prod-thumb').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.getAttribute('data-src');
                if (!src) return;
                main.src = src;
                document.querySelectorAll('.sa-prod-thumb').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
            });
        });
    })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
