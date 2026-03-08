<?php
/**
 * Contrôleur pour la gestion des articles en stock
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_stock.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_mouvements_stock.php';

/**
 * Upload une image pour un article en stock
 */
function upload_stock_image($file, $field_name = 'image_principale')
{
    if (!isset($file[$field_name]) || $file[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $upload_dir = __DIR__ . '/../upload/produits/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_info = $file[$field_name];
    if (!in_array($file_info['type'], $allowed_types)) {
        return false;
    }

    $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
    $filename = uniqid('stock_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file_info['tmp_name'], $filepath)) {
        return 'produits/' . $filename;
    }
    return false;
}

/**
 * Traite l'ajout d'un article en stock
 * @return array ['success' => bool, 'message' => string]
 */
function process_add_stock_article()
{
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $quantite = isset($_POST['quantite']) ? (int) $_POST['quantite'] : 0;
    $categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;

    if (empty($nom)) {
        $errors[] = 'Le nom de l\'article est obligatoire.';
    }
    if ($quantite < 0) {
        $errors[] = 'La quantité ne peut pas être négative.';
    }
    if ($categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    if ($categorie_id > 0 && !get_categorie_by_id($categorie_id)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }

    $image_principale = null;
    if (isset($_FILES['image_principale']) && $_FILES['image_principale']['error'] === UPLOAD_ERR_OK) {
        $image_principale = upload_stock_image($_FILES, 'image_principale');
    }
    if (!$image_principale) {
        $errors[] = 'L\'image principale est obligatoire.';
    }

    if (empty($errors)) {
        $data = [
            'nom' => $nom,
            'image_principale' => $image_principale,
            'quantite' => $quantite,
            'categorie_id' => $categorie_id
        ];
        $id = create_stock_article($data);
        if ($id) {
            create_stock_mouvement([
                'type' => 'entree',
                'stock_article_id' => $id,
                'produit_id' => null,
                'quantite' => $quantite,
                'quantite_avant' => 0,
                'quantite_apres' => $quantite,
                'reference_type' => 'ajout_article',
                'reference_id' => $id,
                'reference_numero' => null,
                'notes' => 'Création article en stock'
            ]);
            $success = true;
            $message = 'Article ajouté au stock avec succès !';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout.';
        }
    }

    if ($success) {
        return ['success' => true, 'message' => $message];
    }
    return ['success' => false, 'message' => !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.'];
}

/**
 * Traite un ajustement d'inventaire (quantité, nom, image)
 * @return array ['success' => bool, 'message' => string]
 */
function process_ajustement_inventaire()
{
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajustement'])) {
        return ['success' => false, 'message' => ''];
    }

    $stock_article_id = isset($_POST['stock_article_id']) ? (int) $_POST['stock_article_id'] : 0;
    $nouvelle_quantite = isset($_POST['nouvelle_quantite']) ? (int) $_POST['nouvelle_quantite'] : 0;
    $nouveau_nom = isset($_POST['ajustement_nom']) ? trim($_POST['ajustement_nom']) : '';

    if ($stock_article_id <= 0) {
        $errors[] = 'Article invalide.';
    }
    if ($nouvelle_quantite < 0) {
        $errors[] = 'La quantité ne peut pas être négative.';
    }
    if (empty($nouveau_nom)) {
        $errors[] = 'Le nom de l\'article est obligatoire.';
    }

    $article = $stock_article_id > 0 ? get_stock_article_by_id($stock_article_id) : null;
    if (!$article) {
        $errors[] = 'Article introuvable.';
    }

    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // Traitement de la nouvelle image (optionnel)
    $nouvelle_image = null;
    if (isset($_FILES['ajustement_image']) && $_FILES['ajustement_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../upload/produits/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = $_FILES['ajustement_image'];
        if (!in_array($file_info['type'], $allowed_types)) {
            $errors[] = 'Format d\'image non supporté (jpg, png, gif, webp uniquement).';
        } elseif ($file_info['size'] > 5 * 1024 * 1024) {
            $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
        } else {
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $filename = uniqid('stock_', true) . '.' . strtolower($extension);
            if (move_uploaded_file($file_info['tmp_name'], $upload_dir . $filename)) {
                $nouvelle_image = 'produits/' . $filename;
            } else {
                $errors[] = 'Erreur lors de l\'upload de l\'image.';
            }
        }
    }

    if (empty($errors)) {
        $quantite_avant = (int) $article['quantite'];
        $difference = $nouvelle_quantite - $quantite_avant;

        $data_update = [
            'nom' => $nouveau_nom,
            'quantite' => $nouvelle_quantite,
            'categorie_id' => (int) $article['categorie_id']
        ];
        if ($nouvelle_image !== null) {
            $data_update['image_principale'] = $nouvelle_image;
        }

        if (update_stock_article($stock_article_id, $data_update)) {
            create_stock_mouvement([
                'type' => 'inventaire',
                'stock_article_id' => $stock_article_id,
                'produit_id' => null,
                'quantite' => abs($difference),
                'quantite_avant' => $quantite_avant,
                'quantite_apres' => $nouvelle_quantite,
                'reference_type' => 'inventaire',
                'reference_id' => null,
                'reference_numero' => null,
                'notes' => $notes ?: 'Ajustement inventaire'
            ]);
            $success = true;
            $message = 'Article mis à jour avec succès.';
        } else {
            $errors[] = 'Erreur lors de la mise à jour.';
        }
    }

    if ($success) {
        return ['success' => true, 'message' => $message];
    }
    return ['success' => false, 'message' => !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.'];
}
