<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non) — compte auto comme le checkout invité
 */

session_start_persistent();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
require_once __DIR__ . '/models/model_zones_livraison.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/guest_client.php';

$result = process_commande_personnalisee();
$zones_livraison = get_all_zones_livraison('actif');

if ($result['success']) {
    require_once __DIR__ . '/services/notifications_order_dispatch.php';

    if (!empty($result['notify_data'])) {
        notifications_dispatch_after_commande_personnalisee($result['notify_data']);
    }

    $_SESSION['commande_perso_success'] = $result['message'];
    header('Location: /user/mes-commandes.php?onglet=en_cours&commande_perso=1');
    exit;
}

if (empty($_SESSION['cp_form_csrf'])) {
    $_SESSION['cp_form_csrf'] = bin2hex(random_bytes(32));
}
$cp_csrf = (string) $_SESSION['cp_form_csrf'];
$user_logged_in = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;

$prefill = [
    'nom' => $_SESSION['user_nom'] ?? '',
    'telephone' => $_SESSION['user_telephone'] ?? '',
    'description' => '',
    'type_produit' => '',
    'quantite' => '',
    'date_souhaitee' => '',
    'zone_livraison_id' => '',
];

if (!$user_logged_in) {
    $gc = guest_client_get();
    if ($gc) {
        if ($prefill['nom'] === '') {
            $prefill['nom'] = $gc['nom'] ?? '';
        }
        if ($prefill['telephone'] === '') {
            $prefill['telephone'] = $gc['telephone'] ?? '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill['nom'] = $_POST['nom'] ?? $prefill['nom'];
    $prefill['telephone'] = $_POST['telephone'] ?? $prefill['telephone'];
    $prefill['description'] = $_POST['description'] ?? '';
    $prefill['type_produit'] = $_POST['type_produit'] ?? '';
    $prefill['quantite'] = $_POST['quantite'] ?? '';
    $prefill['date_souhaitee'] = $_POST['date_souhaitee'] ?? '';
    $prefill['zone_livraison_id'] = $_POST['zone_livraison_id'] ?? '';
}

$types_produit = [
    'Cake Topper' => ['icon' => 'fa-cake-candles', 'hint' => 'Prénom, âge, 3D'],
    'Papier sucre A4' => ['icon' => 'fa-image', 'hint' => 'Photo comestible A4'],
    'Papier sucre A3' => ['icon' => 'fa-expand', 'hint' => 'Grand format A3'],
    'Papier Azym A4' => ['icon' => 'fa-scroll', 'hint' => 'Azyme fin A4'],
    'Papier choco transfert A4' => ['icon' => 'fa-cookie', 'hint' => 'Transfert chocolat'],
];

$date_min = date('Y-m-d');

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-cp">
    <?php include 'nav_bar.php'; ?>

    <div class="page-commande-perso">
        <header class="cp-hero">
            <div class="cp-hero__glow" aria-hidden="true"></div>
            <div class="cp-hero-badge"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Création sur mesure</div>
            <h1>Votre gâteau,<br><span>unique.</span></h1>
            <p class="intro">Cake toppers, papier sucre, azyme ou transfert chocolat&nbsp;: décrivez votre idée, envoyez des photos d’inspiration, on s’occupe du reste.</p>
            <ol class="cp-steps" aria-label="Comment ça marche">
                <li><em>1</em><span>Décrivez</span></li>
                <li><em>2</em><span>Inspirez</span></li>
                <li><em>3</em><span>On crée</span></li>
            </ol>
        </header>

        <?php if (!empty($result['message']) && !$result['success']): ?>
        <div class="error-message" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo $result['message']; ?></span>
        </div>
        <?php endif; ?>

        <div class="cp-layout">
            <aside class="cp-aside">
                <div class="cp-aside__card">
                    <h2>Pourquoi Sugar Paper&nbsp;?</h2>
                    <ul class="cp-benefits">
                        <li><i class="fas fa-bolt" aria-hidden="true"></i> Réponse rapide sur votre demande</li>
                        <li><i class="fas fa-palette" aria-hidden="true"></i> Décoration 100&nbsp;% personnalisée</li>
                        <li><i class="fas fa-mobile-screen" aria-hidden="true"></i> Suivi dans l’application</li>
                        <li><i class="fas fa-location-dot" aria-hidden="true"></i> Livraison à Dakar et alentours</li>
                    </ul>
                </div>
                <?php if (!$user_logged_in): ?>
                <div class="cp-aside__card cp-aside__card--guest">
                    <h2><i class="fas fa-shield-heart" aria-hidden="true"></i> Sans inscription</h2>
                    <p>Comme pour une commande boutique&nbsp;: votre <strong>numéro de téléphone</strong> suffit. Un compte est créé automatiquement, vous pourrez suivre la demande dans Mes commandes.</p>
                </div>
                <?php else: ?>
                <div class="cp-aside__card cp-aside__card--guest">
                    <h2><i class="fas fa-circle-check" aria-hidden="true"></i> Compte connecté</h2>
                    <p>Cette demande sera enregistrée dans votre espace, au même endroit que vos commandes.</p>
                </div>
                <?php endif; ?>
            </aside>

            <form method="POST" action="" class="form-commande-perso" id="form-commande-perso" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($cp_csrf); ?>">

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-user-circle" aria-hidden="true"></i> Vos coordonnées</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" required autocomplete="name"
                                value="<?php echo htmlspecialchars($prefill['nom']); ?>" placeholder="Votre nom">
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <div class="input-wrapper input-wrapper--intl-tel">
                                <input type="tel" id="telephone" name="telephone" required autocomplete="tel"
                                    value="<?php echo htmlspecialchars($prefill['telephone']); ?>"
                                    placeholder="77 123 45 67">
                            </div>
                            <p class="cp-field-hint">Indicatif automatique — Sénégal par défaut, changeable.</p>
                        </div>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-lightbulb" aria-hidden="true"></i> Votre projet</h2>
                    <div class="form-group">
                        <label for="description">Décrivez votre demande *</label>
                        <textarea id="description" name="description" required minlength="10"
                            placeholder="Thème, prénom, âge, couleurs, dimensions, quantité, date de l’événement…"><?php echo htmlspecialchars($prefill['description']); ?></textarea>
                    </div>

                    <p class="cp-type-label">Type de produit <span>(optionnel)</span></p>
                    <div class="cp-type-grid" role="radiogroup" aria-label="Type de produit">
                        <?php foreach ($types_produit as $type_nom => $type_meta): ?>
                        <label class="cp-type-card">
                            <input type="radio" name="type_produit" value="<?php echo htmlspecialchars($type_nom); ?>"
                                <?php echo ($prefill['type_produit'] === $type_nom) ? ' checked' : ''; ?>>
                            <span class="cp-type-card__icon"><i class="fas <?php echo htmlspecialchars($type_meta['icon']); ?>" aria-hidden="true"></i></span>
                            <strong><?php echo htmlspecialchars($type_nom); ?></strong>
                            <em><?php echo htmlspecialchars($type_meta['hint']); ?></em>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label for="quantite">Quantité souhaitée <span class="cp-optional">(optionnel)</span></label>
                        <input type="text" id="quantite" name="quantite"
                            value="<?php echo htmlspecialchars($prefill['quantite']); ?>"
                            placeholder="Ex. : 5 pièces, 2 feuilles A4…">
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-truck" aria-hidden="true"></i> Livraison</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_souhaitee">Date souhaitée <span class="cp-optional">(optionnel)</span></label>
                            <input type="date" id="date_souhaitee" name="date_souhaitee"
                                min="<?php echo htmlspecialchars($date_min); ?>"
                                value="<?php echo htmlspecialchars($prefill['date_souhaitee']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="zone_livraison_id">Zone de livraison<?php echo !empty($zones_livraison) ? ' *' : ''; ?></label>
                            <select id="zone_livraison_id" name="zone_livraison_id" <?php echo !empty($zones_livraison) ? ' required' : ''; ?>>
                                <option value="">— Choisir une zone —</option>
                                <?php foreach ($zones_livraison as $z): ?>
                                <option value="<?php echo (int) $z['id']; ?>"
                                    data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                    <?php echo ((int) $prefill['zone_livraison_id'] === (int) $z['id']) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                    (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-image" aria-hidden="true"></i> Images d’inspiration <span class="cp-optional">(optionnel)</span></h2>
                    <div class="form-group">
                        <div class="upload-reference-box" id="upload-reference-box">
                            <input type="file" id="images_reference" name="images_reference[]" class="upload-reference-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                            <button type="button" class="upload-reference-trigger" id="upload-reference-trigger">
                                <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                <strong>Ajouter des photos d’inspiration</strong>
                                <span>Glissez-déposez ou cliquez — jusqu’à 6 images</span>
                            </button>
                            <p class="upload-help">
                                <strong>Formats :</strong> JPG, PNG, WEBP, GIF — 5&nbsp;Mo max par image.
                            </p>
                            <p class="upload-counter" id="upload-counter">0 / 6 image(s) sélectionnée(s)</p>
                            <div class="preview-reference-grid" id="preview-reference-grid" aria-live="polite"></div>
                        </div>
                    </div>
                </section>

                <p class="cp-legal">
                    En envoyant, vous acceptez les
                    <a href="/conditions-utilisation.php" target="_blank" rel="noopener">conditions d’utilisation</a>.
                    <?php if (!$user_logged_in): ?>
                    Un compte sera créé avec votre numéro pour suivre la demande.
                    <?php endif; ?>
                </p>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer ma demande
                </button>
            </form>
        </div>
    </div>

    <div id="commande-loader-overlay" class="commande-loader-overlay" hidden aria-hidden="true" role="alertdialog" aria-modal="true" aria-labelledby="commande-loader-title" aria-describedby="commande-loader-text">
        <div class="commande-loader-card">
            <div class="commande-loader-spinner" aria-hidden="true">
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--outer"></span>
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--inner"></span>
                <span class="commande-loader-spinner__icon"><i class="fas fa-palette"></i></span>
            </div>
            <h2 class="commande-loader-title" id="commande-loader-title">Demande en cours</h2>
            <p class="commande-loader-text" id="commande-loader-text">Votre demande personnalisée est en train d’être enregistrée.<br>Merci de patienter quelques instants…</p>
            <div class="commande-loader-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <script src="/js/commande-personnalisee.js<?php echo asset_version_query(); ?>"></script>
    <?php include('footer.php'); ?>
    <?php include __DIR__ . '/includes/floating_back_button.php'; ?>
</body>

</html>
