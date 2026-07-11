<?php
/**
 * Contrôleur pour la gestion des catégories
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_categories.php';
if (file_exists(__DIR__ . '/../includes/admin_route_access.php')) {
    require_once __DIR__ . '/../includes/admin_route_access.php';
}

/**
 * Upload une image de catégorie
 * @param array $file Le fichier $_FILES
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_categorie_image($file) {
    if (!isset($file['image']) || $file['image']['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/categories/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_info = $file['image'];
    $result = upload_optimize_image_file($file_info, $upload_dir, 'categories', 'categorie_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        return (string) $result['relative_path'];
    }

    return false;
}

/**
 * Upload une image de genre (super admin — dossier upload/genres/)
 * @param array $file Tableau $_FILES
 * @return string|false Chemin relatif sous upload/ ou false
 */
function upload_genre_image($file) {
    if (!isset($file['image']) || $file['image']['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $upload_dir = __DIR__ . '/../upload/genres/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = $file['image'];
    $result = upload_optimize_image_file($file_info, $upload_dir, 'genres', 'genre_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        return (string) $result['relative_path'];
    }

    return false;
}

/**
 * Traite l'ajout d'une nouvelle catégorie
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_categorie() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $is_vendeur = function_exists('admin_normalize_role_for_route')
        && admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin') === 'vendeur';
    $admin_id_sess = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;

    if ($is_vendeur) {
        return ['success' => false, 'message' => 'Les catégories sont définies par la plateforme. Utilisez les sous-catégories proposées lors de l’ajout d’un produit.'];
    }

    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom de la catégorie est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    // Vérifier si le nom existe déjà
    if (!empty($nom)) {
        $existing = get_categorie_by_nom($nom);
        if ($existing) {
            $errors[] = 'Une catégorie avec ce nom existe déjà.';
        }
    }
    
    // Upload de l'image (optionnel)
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = upload_categorie_image($_FILES);
        if (!$image) {
            $errors[] = 'Erreur lors de l\'upload de l\'image.';
        }
    }
    
    // Si aucune erreur, créer la catégorie (vendeur : pas de lien avec categories_generales)
    if (empty($errors)) {
        if ($is_vendeur && $admin_id_sess > 0) {
            $categorie_id = create_categorie($nom, $description, $image, $admin_id_sess, null);
        } else {
            $categorie_id = create_categorie($nom, $description, $image);
        }
        
        if ($categorie_id) {
            $success = true;
            $message = 'Catégorie ajoutée avec succès !';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout de la catégorie.';
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
 * Traite la modification d'une catégorie
 * @param int $categorie_id L'ID de la catégorie à modifier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_categorie($categorie_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Vérifier que la catégorie existe
    $categorie = get_categorie_by_id($categorie_id);
    if (!$categorie) {
        return ['success' => false, 'message' => 'Catégorie introuvable.'];
    }

    $role_upd = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
    $vid_upd = (int) ($_SESSION['admin_id'] ?? 0);
    if ($role_upd === 'vendeur' && $vid_upd > 0 && !categorie_est_modifiable_par_vendeur((int) $categorie_id, $vid_upd)) {
        return ['success' => false, 'message' => 'Vous ne pouvez pas modifier cette catégorie (définie par la plateforme).'];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom de la catégorie est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }
    
    // Vérifier si le nom existe déjà (sauf pour la catégorie actuelle)
    if (!empty($nom)) {
        $existing = get_categorie_by_nom($nom);
        if ($existing && $existing['id'] != $categorie_id) {
            $errors[] = 'Une catégorie avec ce nom existe déjà.';
        }
    }
    
    // Upload de l'image (optionnel)
    $image = $categorie['image']; // Garder l'ancienne par défaut
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $new_image = upload_categorie_image($_FILES);
        if ($new_image) {
            // Supprimer l'ancienne image si elle existe
            if ($image && file_exists(__DIR__ . '/../upload/' . $image)) {
                @unlink(__DIR__ . '/../upload/' . $image);
            }
            $image = $new_image;
        }
    }
    
    // Si aucune erreur, mettre à jour la catégorie
    if (empty($errors)) {
        if (update_categorie($categorie_id, $nom, $description, $image)) {
            $success = true;
            $message = 'Catégorie modifiée avec succès !';
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification de la catégorie.';
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
 * Traite la suppression d'une catégorie
 * @param int $categorie_id L'ID de la catégorie à supprimer
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_categorie($categorie_id) {
    // Vérifier que la catégorie existe
    $categorie = get_categorie_by_id($categorie_id);
    if (!$categorie) {
        return ['success' => false, 'message' => 'Catégorie introuvable.'];
    }

    $role_del = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
    $vid_del = (int) ($_SESSION['admin_id'] ?? 0);
    if ($role_del === 'vendeur' && $vid_del > 0) {
        if (!categorie_est_modifiable_par_vendeur((int) $categorie_id, $vid_del)) {
            return ['success' => false, 'message' => 'Vous ne pouvez pas supprimer cette catégorie (rayon plateforme ou accès refusé).'];
        }
    }
    
    // Vérifier si des produits utilisent cette catégorie
    if (categorie_has_produits($categorie_id)) {
        return ['success' => false, 'message' => 'Impossible de supprimer cette catégorie car elle contient des produits.'];
    }
    
    // Supprimer l'image si elle existe
    if ($categorie['image'] && file_exists(__DIR__ . '/../upload/' . $categorie['image'])) {
        @unlink(__DIR__ . '/../upload/' . $categorie['image']);
    }
    
    // Supprimer la catégorie
    if (delete_categorie($categorie_id)) {
        return ['success' => true, 'message' => 'Catégorie supprimée avec succès !'];
    } else {
        return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
    }
}

?>

