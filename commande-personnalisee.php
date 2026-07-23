<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non)
 */

session_start_persistent();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
require_once __DIR__ . '/models/model_zones_livraison.php';
require_once __DIR__ . '/includes/asset_version.php';
$result = process_commande_personnalisee();
$zones_livraison = get_all_zones_livraison('actif');

if ($result['success']) {
    require_once __DIR__ . '/services/notifications_order_dispatch.php';

    if (!empty($result['notify_data'])) {
        notifications_dispatch_after_commande_personnalisee($result['notify_data']);
    }

    $_SESSION['commande_perso_success'] = $result['message'];
    header('Location: index.php?commande_perso=1');
    exit;
}

$prefill = [
    'nom' => $_SESSION['user_nom'] ?? '',
    'telephone' => $_SESSION['user_telephone'] ?? ''
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill = [
        'nom' => $_POST['nom'] ?? '',
        'telephone' => $_POST['telephone'] ?? ''
    ];
}

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Commande personnalisée gâteau - Sugar Paper';
$seo_description = 'Commande personnalisée de décoration pour gâteaux : anniversaire, mariage, cérémonies. Produits décoratifs comestibles et non comestibles à grande échelle.';
$seo_canonical = $base . '/commande-personnalisee.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'nav_bar.php'; ?>

    <div class="page-commande-perso">
        <header class="cp-hero">
            <div class="cp-hero-badge"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Création sur mesure</div>
            <h1><i class="fas fa-palette" aria-hidden="true"></i> Commande personnalisée</h1>
        </header>

        <?php if (!empty($result['message']) && !$result['success']): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo $result['message']; ?>
        </div>
        <?php endif; ?>

        <div class="cp-layout">
            <form method="POST" action="" class="form-commande-perso" id="form-commande-perso" enctype="multipart/form-data">
                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-user-circle" aria-hidden="true"></i> Vos coordonnées</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" required
                                value="<?php echo htmlspecialchars($prefill['nom']); ?>" placeholder="Votre nom">
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" required
                                value="<?php echo htmlspecialchars($prefill['telephone']); ?>" placeholder="+237 6XX XXX XXX">
                        </div>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-lightbulb" aria-hidden="true"></i> Votre projet</h2>
                    <div class="form-group">
                        <label for="description">Décrivez votre demande *</label>
                        <textarea id="description" name="description" required
                            placeholder="Type de décoration, thème, dimensions, couleurs, quantités, contraintes particulières..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_produit">Type de produit (optionnel)</label>
                            <select id="type_produit" name="type_produit">
                                <option value="">— Choisir un type —</option>
                                <option value="Cake Topper" <?php echo (($_POST['type_produit'] ?? '') === 'Cake Topper') ? ' selected' : ''; ?>>Cake Topper</option>
                                <option value="Papier sucre A4" <?php echo (($_POST['type_produit'] ?? '') === 'Papier sucre A4') ? ' selected' : ''; ?>>Papier sucre A4</option>
                                <option value="Papier sucre A3" <?php echo (($_POST['type_produit'] ?? '') === 'Papier sucre A3') ? ' selected' : ''; ?>>Papier sucre A3</option>
                                <option value="Papier Azym A4" <?php echo (($_POST['type_produit'] ?? '') === 'Papier Azym A4') ? ' selected' : ''; ?>>Papier Azym A4</option>
                                <option value="Papier choco transfert A4" <?php echo (($_POST['type_produit'] ?? '') === 'Papier choco transfert A4') ? ' selected' : ''; ?>>Papier choco transfert A4</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantite">Quantité souhaitée (optionnel)</label>
                            <input type="text" id="quantite" name="quantite"
                                value="<?php echo htmlspecialchars($_POST['quantite'] ?? ''); ?>"
                                placeholder="Ex : 5 pièces, 2 kg...">
                        </div>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-truck" aria-hidden="true"></i> Livraison</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_souhaitee">Date souhaitée (optionnel)</label>
                            <input type="date" id="date_souhaitee" name="date_souhaitee"
                                value="<?php echo htmlspecialchars($_POST['date_souhaitee'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="zone_livraison_id">Zone de livraison<?php echo !empty($zones_livraison) ? ' *' : ''; ?></label>
                            <select id="zone_livraison_id" name="zone_livraison_id" <?php echo !empty($zones_livraison) ? ' required' : ''; ?>>
                                <option value="">— Choisir une zone —</option>
                                <?php foreach ($zones_livraison as $z): ?>
                                <option value="<?php echo (int) $z['id']; ?>"
                                    data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                    <?php echo (isset($_POST['zone_livraison_id']) && (int) $_POST['zone_livraison_id'] === (int) $z['id']) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                    (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-image" aria-hidden="true"></i> Images d'inspiration (optionnel)</h2>
                    <div class="form-group">
                        <div class="upload-reference-box" id="upload-reference-box">
                            <input type="file" id="images_reference" name="images_reference[]" class="upload-reference-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                            <button type="button" class="upload-reference-trigger" id="upload-reference-trigger">
                                <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                <strong>Ajouter des photos d'inspiration</strong>
                                <span>Glissez-déposez ou cliquez pour sélectionner plusieurs images</span>
                            </button>
                            <p class="upload-help">
                                <strong>Formats acceptés :</strong> JPG, PNG, WEBP, GIF — 5 Mo max par image.
                            </p>
                            <p class="upload-counter" id="upload-counter">0 / 6 image(s) sélectionnée(s)</p>
                            <div class="preview-reference-grid" id="preview-reference-grid" aria-live="polite"></div>
                        </div>
                    </div>
                </section>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer ma demande
                </button>
            </form>
        </div>

        <a href="index.php" class="back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à l'accueil</a>
    </div>

    <div id="commande-loader-overlay" class="commande-loader-overlay" hidden aria-hidden="true" role="alertdialog" aria-modal="true" aria-labelledby="commande-loader-title" aria-describedby="commande-loader-text">
        <div class="commande-loader-card">
            <div class="commande-loader-spinner" aria-hidden="true">
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--outer"></span>
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--inner"></span>
                <span class="commande-loader-spinner__icon"><i class="fas fa-palette"></i></span>
            </div>
            <h2 class="commande-loader-title" id="commande-loader-title">Demande en cours</h2>
            <p class="commande-loader-text" id="commande-loader-text">Votre demande personnalisée est en train d'être enregistrée.<br>Merci de patienter quelques instants…</p>
            <div class="commande-loader-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>

    <script src="/js/commande-personnalisee.js<?php echo asset_version_query(); ?>"></script>
    <?php include __DIR__ . '/includes/floating_back_button.php'; ?>
</body>

</html>
