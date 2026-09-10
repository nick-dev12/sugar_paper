<?php
/**
 * Contrôleur pour la gestion du panier
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_variantes.php';
require_once __DIR__ . '/../includes/panier_invite.php';
require_once __DIR__ . '/../includes/guest_client.php';
require_once __DIR__ . '/../includes/produit_personnalisation.php';

/**
 * Traite l'ajout d'un produit au panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_to_panier()
{
    if (!isset($_POST['produit_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $user_connecte = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    $user_id = $user_connecte ? (int) $_SESSION['user_id'] : 0;

    if (!$user_connecte) {
        return [
            'success' => false,
            'message' => 'Veuillez vous identifier avec votre nom et votre numéro de téléphone pour commander.',
        ];
    }

    $produit_id = (int) $_POST['produit_id'];
    $quantite = (int) $_POST['quantite'];
    $option_couleur = isset($_POST['option_couleur']) ? trim($_POST['option_couleur']) : '';
    $option_poids = isset($_POST['option_poids']) ? trim($_POST['option_poids']) : '';
    $option_taille = isset($_POST['option_taille']) ? trim($_POST['option_taille']) : '';
    $option_variante_id = isset($_POST['option_variante_id']) ? (int)$_POST['option_variante_id'] : null;
    $option_variante_nom = isset($_POST['option_variante_nom']) ? trim($_POST['option_variante_nom']) : null;
    $option_variante_image = isset($_POST['option_variante_image']) ? trim($_POST['option_variante_image']) : null;
    $option_prix_unitaire = isset($_POST['option_prix_unitaire']) && is_numeric($_POST['option_prix_unitaire']) ? (float)$_POST['option_prix_unitaire'] : null;
    $option_surcout_poids = isset($_POST['option_surcout_poids']) && is_numeric($_POST['option_surcout_poids']) ? (float)$_POST['option_surcout_poids'] : 0;
    $option_surcout_taille = isset($_POST['option_surcout_taille']) && is_numeric($_POST['option_surcout_taille']) ? (float)$_POST['option_surcout_taille'] : 0;
    $option_image_personnalisation = isset($_POST['option_image_personnalisation']) ? trim($_POST['option_image_personnalisation']) : null;
    if ($option_image_personnalisation === '' || $option_image_personnalisation === 'pending') {
        $option_image_personnalisation = null;
    }
    if ($option_image_personnalisation !== null && !produit_personnalisation_path_is_valid($option_image_personnalisation)) {
        return ['success' => false, 'message' => 'Image de personnalisation invalide.'];
    }

    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit || $produit['statut'] != 'actif') {
        return ['success' => false, 'message' => 'Ce produit n\'est pas disponible.'];
    }

    $option_perso_meta = isset($_POST['option_perso_meta']) ? trim((string) $_POST['option_perso_meta']) : null;
    if ($option_perso_meta === '') {
        $option_perso_meta = null;
    }
    if ($option_perso_meta !== null) {
        $decoded_meta = produit_personnalisation_meta_decode($option_perso_meta);
        $option_perso_meta = $decoded_meta ? produit_personnalisation_meta_encode($decoded_meta) : null;
    }

    if ($option_image_personnalisation === null && produit_supports_sheet_personnalisation($produit) && isset($_FILES['image_personnalisation']) && is_array($_FILES['image_personnalisation'])) {
        $upload_dir = produit_personnalisation_upload_dir();
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        require_once __DIR__ . '/../includes/image_optimizer.php';
        $upload_result = upload_optimize_image_file(
            $_FILES['image_personnalisation'],
            $upload_dir,
            produit_personnalisation_upload_subdir(),
            'perso_prod_'
        );
        if (!empty($upload_result['success'])) {
            $option_image_personnalisation = (string) ($upload_result['relative_path'] ?? '');
        } elseif ((int) ($_FILES['image_personnalisation']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => $upload_result['message'] ?? 'Impossible d’enregistrer l’image de personnalisation.'];
        }
    }

    $option_image_personnalisation_source = null;
    if (produit_supports_sheet_personnalisation($produit) && isset($_FILES['image_personnalisation_source']) && is_array($_FILES['image_personnalisation_source'])) {
        $upload_dir = produit_personnalisation_upload_dir();
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        require_once __DIR__ . '/../includes/image_optimizer.php';
        $src_result = upload_optimize_image_file(
            $_FILES['image_personnalisation_source'],
            $upload_dir,
            produit_personnalisation_upload_subdir(),
            'perso_src_'
        );
        if (!empty($src_result['success'])) {
            $option_image_personnalisation_source = (string) ($src_result['relative_path'] ?? '');
        } elseif ((int) ($_FILES['image_personnalisation_source']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => $src_result['message'] ?? 'Impossible d’enregistrer l’image importée.'];
        }
    }

    $couleurs_options = [];
    $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
    $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
    if (!empty($produit['couleurs'])) {
        $cr = trim($produit['couleurs']);
        $dec = json_decode($cr, true);
        if (is_array($dec)) {
            $couleurs_options = array_filter($dec, function ($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            });
        }
        if (empty($couleurs_options)) {
            $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
        }
    }

    $variante = ($option_variante_id && ($v = get_variante_by_id($option_variante_id)) && $v['produit_id'] == $produit_id) ? $v : null;
    $surcout_poids = get_surcharge_for_option($poids_options, $option_poids);
    $surcout_taille = get_surcharge_for_option($taille_options, $option_taille);

    $prix_final = null;
    if ($option_prix_unitaire !== null && $option_prix_unitaire > 0) {
        $prix_final = $option_prix_unitaire;
    }
    if ($prix_final === null) {
        $prix_base = $produit['prix'];
        if ($variante) {
            $prix_base = !empty($variante['prix_promotion']) && $variante['prix_promotion'] < $variante['prix']
                ? $variante['prix_promotion'] : $variante['prix'];
        }
        $prix_final = $prix_base + $surcout_poids + $surcout_taille;
    }

    $vid = ($option_variante_id && $option_variante_id > 0) ? $option_variante_id : null;
    $quantite_actuelle = 0;
    if ($user_connecte) {
        $item_panier = is_in_panier($user_id, $produit_id);
        $quantite_actuelle = $item_panier ? (int) $item_panier['quantite'] : 0;
    } else {
        $quantite_actuelle = panier_invite_get_line_quantity(
            $produit_id,
            $option_couleur ?: null,
            $option_poids ?: null,
            $option_taille ?: null,
            $vid
        );
    }
    $quantite_totale = $quantite_actuelle + $quantite;

    if ($quantite_totale > $produit['stock']) {
        return ['success' => false, 'message' => 'Stock insuffisant. Stock disponible : ' . $produit['stock']];
    }

    $vnom = $variante ? $variante['nom'] : $option_variante_nom;
    $vimg = $variante ? $variante['image'] : $option_variante_image;

    if ($option_image_personnalisation && $option_perso_meta === null && produit_supports_sheet_personnalisation($produit)) {
        if (produit_supports_cupcakes_personnalisation($produit)) {
            $option_perso_meta = produit_personnalisation_meta_encode([
                'type' => 'cupcakes',
                'format' => 'a4',
                'shape' => 'circle',
                'diameter_cm' => 5,
                'image_mode' => 'shared',
            ]);
        } elseif (produit_supports_disques_cocktail_personnalisation($produit)) {
            $option_perso_meta = produit_personnalisation_meta_encode([
                'type' => 'disques_cocktail',
                'format' => 'a4',
                'shape' => 'circle',
                'diameter_cm' => 8,
                'image_mode' => 'shared',
            ]);
        } elseif (produit_supports_contours_personnalisation($produit)) {
            $option_perso_meta = produit_personnalisation_meta_encode([
                'type' => 'contours_gateau',
                'format' => 'a4',
                'height_cm' => 5,
                'image_mode' => 'shared',
            ]);
        } else {
            $option_perso_meta = produit_personnalisation_meta_encode([
                'format' => 'a4',
                'shape' => 'circle',
                'width_cm' => 15,
                'height_cm' => 15,
            ]);
        }
    }

    if (add_to_panier($user_id, $produit_id, $quantite, $option_couleur ?: null, $option_poids ?: null, $option_taille ?: null,
        $vid, $vnom, $vimg, $surcout_poids, $surcout_taille, $prix_final, $option_image_personnalisation, $option_perso_meta, $option_image_personnalisation_source)) {
        return ['success' => true, 'message' => 'Produit ajouté au panier avec succès.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier.'];
}

/**
 * Traite la mise à jour de la quantité d'un produit dans le panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_panier()
{
    if (!isset($_POST['panier_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $panier_id = (int) $_POST['panier_id'];
    $quantite = (int) $_POST['quantite'];

    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }

    if (!isset($_SESSION['user_id'])) {
        $panier_items = panier_invite_get_items();
        $item = null;
        foreach ($panier_items as $panier_item) {
            if ($panier_item['panier_id'] == $panier_id) {
                $item = $panier_item;
                break;
            }
        }
        if (!$item) {
            return ['success' => false, 'message' => 'Élément du panier introuvable.'];
        }
        if ($quantite > $item['stock']) {
            return ['success' => false, 'message' => 'Stock insuffisant. Stock disponible : ' . $item['stock']];
        }
        if (panier_invite_update_quantite($panier_id, $quantite)) {
            return ['success' => true, 'message' => 'Quantité mise à jour.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }

    $panier_items = get_panier_by_user($_SESSION['user_id']);
    $item = null;
    foreach ($panier_items as $panier_item) {
        if ($panier_item['panier_id'] == $panier_id) {
            $item = $panier_item;
            break;
        }
    }

    if (!$item) {
        return ['success' => false, 'message' => 'Élément du panier introuvable.'];
    }

    if ($quantite > $item['stock']) {
        return ['success' => false, 'message' => 'Stock insuffisant. Stock disponible : ' . $item['stock']];
    }

    if (update_panier_quantite($panier_id, $quantite)) {
        return ['success' => true, 'message' => 'Quantité mise à jour.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
}

/**
 * Traite la suppression d'un produit du panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_from_panier()
{
    if (!isset($_POST['panier_id'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $panier_id = (int) $_POST['panier_id'];

    if (!isset($_SESSION['user_id'])) {
        if (panier_invite_delete_line($panier_id)) {
            return ['success' => true, 'message' => 'Produit retiré du panier.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }

    if (delete_from_panier($panier_id)) {
        return ['success' => true, 'message' => 'Produit retiré du panier.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
}
