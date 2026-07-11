<?php
/**
 * Contrôleur pour la gestion du slider
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_slider.php';

/**
 * Upload une image de slider
 * @param string $file_input_name Le nom du champ input file
 * @param string $current_image Le nom de l'image actuelle (pour mise à jour)
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_slider_image($file_input_name, $current_image = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image; // Retourner l'image actuelle si pas de nouveau fichier
    }
    
    $file = $_FILES[$file_input_name];
    $upload_dir = __DIR__ . '/../upload/slider/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $result = upload_optimize_image_file($file, $upload_dir, 'slider', 'slider_');
    if (!empty($result['success']) && !empty($result['filename'])) {
        if ($current_image) {
            image_optimizer_delete_with_variants('slider/' . $current_image);
            if (file_exists($upload_dir . $current_image)) {
                @unlink($upload_dir . $current_image);
            }
        }
        return (string) $result['filename'];
    }

    if (empty($result['success'])) {
        $_SESSION['upload_error'] = (string) ($result['message'] ?? 'Échec de l\'upload de l\'image du slider.');
    }

    return false;
}

/**
 * Traite l'ajout d'un slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_slide() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération des données
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $paragraphe = isset($_POST['paragraphe']) ? trim($_POST['paragraphe']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '';
    $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation
    if (empty($titre)) {
        $errors[] = 'Le titre est obligatoire.';
    }
    
    if (empty($paragraphe)) {
        $errors[] = 'Le paragraphe est obligatoire.';
    }
    
    // Upload de l'image
    $image = upload_slider_image('image');
    if (!$image) {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'L\'image est obligatoire.';
        } else {
            // Vérifier s'il y a un message d'erreur spécifique
            if (isset($_SESSION['upload_error'])) {
                $errors[] = $_SESSION['upload_error'];
                unset($_SESSION['upload_error']);
            } else {
                $errors[] = 'Erreur lors de l\'upload de l\'image. Vérifiez que le fichier est au format JPEG, JPG, PNG, GIF, WEBP ou AVIF et ne dépasse pas 20 Mo.';
            }
        }
    }
    
    // Si aucune erreur, créer le slide (vendeur : rattaché à sa boutique pour la vitrine)
    if (empty($errors)) {
        $admin_id_slide = null;
        if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] ?? '') === 'vendeur' && !empty($_SESSION['admin_id'])) {
            $admin_id_slide = (int) $_SESSION['admin_id'];
        }
        $slide_id = add_slide($titre, $paragraphe, $image, $bouton_texte, $bouton_lien, $ordre, $statut, $admin_id_slide);
        
        if ($slide_id) {
            $success = true;
            $message = 'Slide ajouté avec succès.';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout du slide.';
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
 * Ajout d'une affiche publicitaire (image uniquement).
 *
 * @return array{success: bool, message: string}
 */
function process_add_slide_image_only()
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_slide_image'])) {
        return ['success' => false, 'message' => ''];
    }

    $image = upload_slider_image('image');
    if (!$image) {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Veuillez sélectionner une image pour votre affiche.';
        } elseif (isset($_SESSION['upload_error'])) {
            $errors[] = $_SESSION['upload_error'];
            unset($_SESSION['upload_error']);
        } else {
            $errors[] = 'Erreur lors de l\'upload. Formats acceptés : JPEG, PNG, GIF, WEBP, AVIF (max. 20 Mo).';
        }
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode('<br>', $errors),
        ];
    }

    $admin_id_slide = null;
    if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] ?? '') === 'vendeur' && !empty($_SESSION['admin_id'])) {
        $admin_id_slide = (int) $_SESSION['admin_id'];
    }

    $ordre = slider_get_next_ordre($admin_id_slide);
    $slide_id = add_slide('', '', $image, null, null, $ordre, 'actif', $admin_id_slide);

    if ($slide_id) {
        return ['success' => true, 'message' => 'Affiche ajoutée avec succès.'];
    }

    return ['success' => false, 'message' => 'Impossible d\'enregistrer l\'affiche. Réessayez.'];
}

function slider_admin_can_manage_slide($slide)
{
    if (!is_array($slide) || empty($slide['id'])) {
        return false;
    }

    $role = $_SESSION['admin_role'] ?? '';
    if ($role !== 'vendeur') {
        return true;
    }

    if (!slider_table_has_admin_id_column()) {
        return false;
    }

    return isset($slide['admin_id']) && (int) $slide['admin_id'] === (int) ($_SESSION['admin_id'] ?? 0);
}

/**
 * Modification d'une affiche (image uniquement).
 *
 * @return array{success: bool, message: string}
 */
function process_update_slide_image_only($slide_id)
{
    $slide_id = (int) $slide_id;
    if ($slide_id <= 0) {
        return ['success' => false, 'message' => 'Affiche introuvable.'];
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['edit_slide_image'])) {
        return ['success' => false, 'message' => ''];
    }

    $current = get_slide_by_id($slide_id);
    if (!$current || !slider_admin_can_manage_slide($current)) {
        return ['success' => false, 'message' => 'Affiche introuvable.'];
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Veuillez sélectionner une nouvelle image.'];
    }

    $image = upload_slider_image('image', $current['image']);
    if (!$image) {
        if (isset($_SESSION['upload_error'])) {
            $message = (string) $_SESSION['upload_error'];
            unset($_SESSION['upload_error']);
            return ['success' => false, 'message' => $message];
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'upload. Formats acceptés : JPEG, PNG, GIF, WEBP, AVIF (max. 20 Mo).'];
    }

    $ok = update_slide(
        $slide_id,
        (string) ($current['titre'] ?? ''),
        (string) ($current['paragraphe'] ?? ''),
        $image,
        $current['bouton_texte'] ?? null,
        $current['bouton_lien'] ?? null,
        (int) ($current['ordre'] ?? 0),
        (string) ($current['statut'] ?? 'actif')
    );

    if ($ok) {
        return ['success' => true, 'message' => 'Affiche modifiée avec succès.'];
    }

    return ['success' => false, 'message' => 'Impossible de modifier l\'affiche. Réessayez.'];
}

/**
 * Traite la modification d'un slide
 * @param int $slide_id L'ID du slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_slide($slide_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupérer le slide actuel
    $current_slide = get_slide_by_id($slide_id);
    if (!$current_slide) {
        return ['success' => false, 'message' => 'Slide non trouvé.'];
    }
    
    // Récupération des données
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $paragraphe = isset($_POST['paragraphe']) ? trim($_POST['paragraphe']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '';
    $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation
    if (empty($titre)) {
        $errors[] = 'Le titre est obligatoire.';
    }
    
    if (empty($paragraphe)) {
        $errors[] = 'Le paragraphe est obligatoire.';
    }
    
    // Upload de l'image (si nouvelle image fournie)
    $image = upload_slider_image('image', $current_slide['image']);
    if (!$image) {
        // Vérifier si une nouvelle image a été fournie mais qu'il y a eu une erreur
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Il y a eu une erreur lors de l'upload
            if (isset($_SESSION['upload_error'])) {
                $errors[] = $_SESSION['upload_error'];
                unset($_SESSION['upload_error']);
            } else {
                $errors[] = 'Erreur lors de l\'upload de l\'image. Vérifiez que le fichier est au format JPEG, JPG, PNG, GIF, WEBP ou AVIF et ne dépasse pas 20 Mo.';
            }
            // Garder l'ancienne image en cas d'erreur
            $image = $current_slide['image'];
        } else {
            // Pas de nouvelle image fournie, garder l'ancienne
            $image = $current_slide['image'];
        }
    }
    
    // Si aucune erreur, mettre à jour le slide
    if (empty($errors)) {
        if (update_slide($slide_id, $titre, $paragraphe, $image, $bouton_texte, $bouton_lien, $ordre, $statut)) {
            $success = true;
            $message = 'Slide modifié avec succès.';
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification du slide.';
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
 * Traite la suppression d'un slide
 * @param int $slide_id L'ID du slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_slide($slide_id) {
    // Récupérer le slide pour supprimer l'image
    $slide = get_slide_by_id($slide_id);
    
    if (!$slide) {
        return ['success' => false, 'message' => 'Slide non trouvé.'];
    }
    
    // Supprimer le slide
    if (delete_slide($slide_id)) {
        // Supprimer l'image
        $image_path = __DIR__ . '/../upload/slider/' . $slide['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
        
        return ['success' => true, 'message' => 'Slide supprimé avec succès.'];
    }
    
    return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
}

?>

