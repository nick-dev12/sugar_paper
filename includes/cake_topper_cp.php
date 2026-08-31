<?php
/**
 * Commande personnalisée — cake toppers (catalogue boutique section_accueil)
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_cp_catalogue.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

/**
 * @return bool
 */
function cake_topper_cp_enabled()
{
    return true;
}

/**
 * @param array|string|null $produit
 * @return bool
 */
function produit_supports_cake_topper_commande_perso($produit)
{
    if (!cake_topper_cp_enabled() || !is_array($produit)) {
        return false;
    }

    return normalize_produit_section_accueil($produit['section_accueil'] ?? '') === 'cake_topper';
}

/**
 * Bouton « Personnaliser » (commande personnalisée) sur les cartes cake_topper
 * @param array|string|null $produit
 * @return bool
 */
function produit_listing_uses_cake_topper_cp($produit)
{
    return produit_supports_cake_topper_commande_perso($produit);
}

/**
 * Prix effectif d'un produit boutique
 * @param array $produit
 * @return float
 */
function cake_topper_cp_effective_price(array $produit)
{
    $prix = (float) ($produit['prix'] ?? 0);
    $promo = isset($produit['prix_promotion']) && $produit['prix_promotion'] !== null && $produit['prix_promotion'] !== ''
        ? (float) $produit['prix_promotion']
        : 0;

    if ($promo > 0 && $promo < $prix) {
        return $promo;
    }

    return $prix > 0 ? $prix : 0;
}

/**
 * Recherche un produit catalogue CP par nom (correspondance souple)
 * @param string $nom
 * @return array|false
 */
function find_cp_catalogue_produit_by_name($nom)
{
    if (!cp_catalogue_tables_available()) {
        return false;
    }

    $nom = trim($nom);
    if ($nom === '') {
        return false;
    }

    global $db;
    try {
        $stmt = $db->prepare("
            SELECT p.* FROM cp_catalogue_produits p
            INNER JOIN cp_catalogue_dossiers d ON d.id = p.dossier_id
            WHERE p.statut = 'actif' AND d.statut = 'actif'
              AND LOWER(TRIM(p.nom)) = LOWER(:nom)
            LIMIT 1
        ");
        $stmt->execute(['nom' => $nom]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $stmt = $db->prepare("
            SELECT p.* FROM cp_catalogue_produits p
            INNER JOIN cp_catalogue_dossiers d ON d.id = p.dossier_id
            WHERE p.statut = 'actif' AND d.statut = 'actif'
              AND (LOWER(p.nom) LIKE LOWER(:like) OR LOWER(:nom2) LIKE CONCAT('%', LOWER(p.nom), '%'))
            ORDER BY CHAR_LENGTH(p.nom) DESC
            LIMIT 1
        ");
        $like = '%' . $nom . '%';
        $stmt->execute(['like' => $like, 'nom2' => $nom]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Contexte modal CP pour un produit boutique (cake topper)
 * @param array $produit
 * @return array{catalogue_produit_id:int,boutique_produit_id:int,nom:string,image:string,prix_min:float,prix_max:float}
 */
function resolve_cp_modal_context_for_produit(array $produit)
{
    $boutique_id = (int) ($produit['id'] ?? 0);
    $nom = trim((string) ($produit['nom'] ?? 'Produit'));
    $image = upload_image_url($produit['image_principale'] ?? '', 'md');
    $prix_effectif = cake_topper_cp_effective_price($produit);

    $cp_match = find_cp_catalogue_produit_by_name($nom);
    if ($cp_match) {
        return [
            'catalogue_produit_id' => (int) $cp_match['id'],
            'boutique_produit_id' => $boutique_id,
            'nom' => trim((string) $cp_match['nom']),
            'image' => upload_image_url($cp_match['image'] ?? '', 'md'),
            'prix_min' => (float) $cp_match['prix_min'],
            'prix_max' => (float) $cp_match['prix_max'],
        ];
    }

    $prix_min = 500.0;
    $prix_max = max(8000.0, $prix_effectif > 0 ? $prix_effectif : 8000.0);

    return [
        'catalogue_produit_id' => 0,
        'boutique_produit_id' => $boutique_id,
        'nom' => $nom,
        'image' => $image,
        'prix_min' => $prix_min,
        'prix_max' => $prix_max,
    ];
}

/**
 * Contexte serveur pour validation commande personnalisée (catalogue ou boutique)
 * @param int $catalogue_produit_id
 * @param int $boutique_produit_id
 * @return array{ok:bool,message:string,catalogue_produit_id:int,boutique_produit_id:int,type_produit:string,prix_min:float,prix_max:float,catalogue_row:array|null}
 */
function cake_topper_cp_resolve_submission_context($catalogue_produit_id, $boutique_produit_id)
{
    $catalogue_produit_id = (int) $catalogue_produit_id;
    $boutique_produit_id = (int) $boutique_produit_id;

    if ($catalogue_produit_id > 0) {
        $row = get_cp_produit_by_id($catalogue_produit_id, true);
        if (!$row) {
            return ['ok' => false, 'message' => 'Le produit sélectionné n\'est plus disponible.', 'catalogue_produit_id' => 0, 'boutique_produit_id' => 0, 'type_produit' => '', 'prix_min' => 0, 'prix_max' => 0, 'catalogue_row' => null];
        }
        return [
            'ok' => true,
            'message' => '',
            'catalogue_produit_id' => $catalogue_produit_id,
            'boutique_produit_id' => $boutique_produit_id > 0 ? $boutique_produit_id : 0,
            'type_produit' => trim((string) $row['nom']),
            'prix_min' => (float) $row['prix_min'],
            'prix_max' => (float) $row['prix_max'],
            'catalogue_row' => $row,
        ];
    }

    if ($boutique_produit_id <= 0) {
        return ['ok' => false, 'message' => 'Veuillez sélectionner un produit.', 'catalogue_produit_id' => 0, 'boutique_produit_id' => 0, 'type_produit' => '', 'prix_min' => 0, 'prix_max' => 0, 'catalogue_row' => null];
    }

    $produit = get_produit_by_id($boutique_produit_id);
    if (!$produit || !produit_supports_cake_topper_commande_perso($produit)) {
        return ['ok' => false, 'message' => 'Ce produit ne permet pas de commande personnalisée.', 'catalogue_produit_id' => 0, 'boutique_produit_id' => 0, 'type_produit' => '', 'prix_min' => 0, 'prix_max' => 0, 'catalogue_row' => null];
    }

    $ctx = resolve_cp_modal_context_for_produit($produit);

    return [
        'ok' => true,
        'message' => '',
        'catalogue_produit_id' => (int) $ctx['catalogue_produit_id'],
        'boutique_produit_id' => $boutique_produit_id,
        'type_produit' => $ctx['nom'],
        'prix_min' => (float) $ctx['prix_min'],
        'prix_max' => (float) $ctx['prix_max'],
        'catalogue_row' => null,
    ];
}

/**
 * Prépare CSRF + préremplissage formulaire CP
 * @return array
 */
function cp_form_modal_prepare_state()
{
    if (empty($_SESSION['cp_form_csrf'])) {
        $_SESSION['cp_form_csrf'] = bin2hex(random_bytes(32));
    }

    $user_logged_in = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    $prefill = [
        'nom' => $_SESSION['user_nom'] ?? '',
        'telephone' => $_SESSION['user_telephone'] ?? '',
        'prix_propose' => '',
        'type_produit' => '',
        'catalogue_produit_id' => '',
        'boutique_produit_id' => '',
        'description_creation' => '',
    ];

    if (!$user_logged_in) {
        require_once __DIR__ . '/guest_client.php';
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

    return [
        'csrf' => (string) $_SESSION['cp_form_csrf'],
        'user_logged_in' => $user_logged_in,
        'prefill' => $prefill,
        'form_action' => '/commande-personnalisee.php',
        'show_order_form' => false,
        'selected_catalogue_id' => 0,
        'selected_catalogue_nom' => '',
        'selected_catalogue_image' => '',
        'selected_prix_min' => 0,
        'selected_prix_max' => 0,
    ];
}

/**
 * Affiche modal + assets CP (une fois par page)
 * @return void
 */
function render_cp_form_modal_assets()
{
    static $done = false;
    if ($done) {
        return;
    }

    require_once __DIR__ . '/asset_version.php';
    $state = cp_form_modal_prepare_state();
    $cp_modal = $state;

    if (!defined('CP_FORM_MODAL_ASSETS')) {
        define('CP_FORM_MODAL_ASSETS', true);
        if (!$state['user_logged_in']) {
            include __DIR__ . '/auth_intl_tel_head.php';
        }
        ?>
        <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
        <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
        <link rel="stylesheet" href="/css/produit-personnalisation.css<?php echo asset_version_query(); ?>">
        <?php
    }

    include __DIR__ . '/partials/cp_commande_modal.php';
    $done = true;
    ?>
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
    <?php
    if (!$state['user_logged_in']) {
        include __DIR__ . '/auth_intl_tel_scripts.php';
    }
    ?>
    <script>
        window.cpShopConfig = window.cpShopConfig || {
            userLoggedIn: <?php echo $state['user_logged_in'] ? 'true' : 'false'; ?>
        };
    </script>
    <script src="/js/cp-form-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php
}

/**
 * Bouton « Personnaliser » sur la fiche produit (section cake_topper)
 * @param array $produit
 * @return void
 */
function render_produit_detail_cp_action(array $produit)
{
    if (!produit_supports_cake_topper_commande_perso($produit)) {
        return;
    }

    $ctx = resolve_cp_modal_context_for_produit($produit);
    ?>
    <div class="produit-cp-detail produit-section-bg">
        <p class="produit-cp-detail__hint">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            Décrivez votre création sur mesure : nous réalisons votre cake topper selon vos envies.
        </p>
        <div class="produit-actions-row produit-actions-row--single">
            <button type="button"
                class="btn-add-panier btn-personnaliser js-open-cp-modal"
                data-cp-catalogue-id="<?php echo (int) $ctx['catalogue_produit_id']; ?>"
                data-cp-boutique-id="<?php echo (int) $ctx['boutique_produit_id']; ?>"
                data-cp-name="<?php echo htmlspecialchars($ctx['nom'], ENT_QUOTES, 'UTF-8'); ?>"
                data-cp-image="<?php echo htmlspecialchars($ctx['image'], ENT_QUOTES, 'UTF-8'); ?>"
                data-cp-price-min="<?php echo (float) $ctx['prix_min']; ?>"
                data-cp-price-max="<?php echo (float) $ctx['prix_max']; ?>">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                Personnaliser
            </button>
        </div>
    </div>
    <?php
}
