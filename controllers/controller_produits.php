<?php
/**
 * Contrôleur pour la gestion des produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';

/**
 * Upload une image de produit
 * @param array $file Le fichier $_FILES
 * @param string $field_name Le nom du champ
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_produit_image($file, $field_name = 'image') {
    if (!isset($file[$field_name]) || $file[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/produits/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_info = $file[$field_name];
    
    // Vérifier le type
    if (!in_array($file_info['type'], $allowed_types)) {
        return false;
    }
    
    // Vérifier la taille
    if ($file_info['size'] > $max_size) {
        return false;
    }
    
    // Générer un nom unique
    $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
    $filename = uniqid('produit_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file_info['tmp_name'], $filepath)) {
        return 'produits/' . $filename;
    }
    
    return false;
}

/**
 * Traite l'ajout d'un nouveau produit
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_produit() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }
    
    if ($categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    
    // Vérifier que la catégorie existe
    if ($categorie_id > 0 && !get_categorie_by_id($categorie_id)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }
    
    // Upload de l'image principale
    $image_principale = null;
    if (isset($_FILES['image_principale']) && $_FILES['image_principale']['error'] === UPLOAD_ERR_OK) {
        $image_principale = upload_produit_image($_FILES, 'image_principale');
        if (!$image_principale) {
            $errors[] = 'Erreur lors de l\'upload de l\'image principale.';
        }
    } else {
        $errors[] = 'L\'image principale est obligatoire.';
    }
    
    // Si aucune erreur, créer le produit
    if (empty($errors)) {
        $data = [
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'prix_promotion' => $prix_promotion,
            'stock' => $stock,
            'categorie_id' => $categorie_id,
            'image_principale' => $image_principale,
            'poids' => null,
            'unite' => 'unité',
            'statut' => $stock > 0 ? $statut : 'rupture_stock'
        ];
        
        $produit_id = create_produit($data);
        
        if ($produit_id) {
            $success = true;
            $message = 'Produit ajouté avec succès !';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout du produit.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la modification d'un produit
 * @param int $produit_id L'ID du produit à modifier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_produit($produit_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $poids = isset($_POST['poids']) ? trim($_POST['poids']) : null;
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation (identique à l'ajout)
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }
    
    if ($categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    
    // Upload de l'image principale (optionnel lors de la modification)
    $image_principale = $produit['image_principale']; // Garder l'ancienne par défaut
    if (isset($_FILES['image_principale']) && $_FILES['image_principale']['error'] === UPLOAD_ERR_OK) {
        $new_image = upload_produit_image($_FILES, 'image_principale');
        if ($new_image) {
            // Supprimer l'ancienne image si elle existe
            if ($image_principale && file_exists(__DIR__ . '/../upload/' . $image_principale)) {
                @unlink(__DIR__ . '/../upload/' . $image_principale);
            }
            $image_principale = $new_image;
        }
    }
    
    // Si aucune erreur, mettre à jour le produit
    if (empty($errors)) {
        $data = [
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'prix_promotion' => $prix_promotion,
            'stock' => $stock,
            'categorie_id' => $categorie_id,
            'image_principale' => $image_principale,
            'poids' => $poids,
            'unite' => $unite,
            'statut' => $stock > 0 ? $statut : 'rupture_stock'
        ];
        
        if (update_produit($produit_id, $data)) {
            $success = true;
            $message = 'Produit modifié avec succès !';
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification du produit.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la suppression d'un produit
 * @param int $produit_id L'ID du produit à supprimer
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_produit($produit_id) {
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }
    
    // Supprimer l'image si elle existe
    if ($produit['image_principale'] && file_exists(__DIR__ . '/../upload/' . $produit['image_principale'])) {
        @unlink(__DIR__ . '/../upload/' . $produit['image_principale']);
    }
    
    // Supprimer le produit
    if (delete_produit($produit_id)) {
        return ['success' => true, 'message' => 'Produit supprimé avec succès !'];
    } else {
        return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
    }
}

?>

