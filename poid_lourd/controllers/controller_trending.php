<?php
/**
 * Contrôleur pour la gestion de la section trending
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_trending.php';
require_once __DIR__ . '/../includes/admin_param_boutique_scope.php';

/**
 * Traite le formulaire de modification de la section trending
 * @return array Résultat de l'opération ['success' => bool, 'message' => string]
 */
function process_update_trending() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }
    $boutique_scope = admin_param_boutique_scope_id();
    $scope_id = $boutique_scope !== null ? (int) $boutique_scope : null;
    
    // Validation des données
    $label = isset($_POST['label']) ? trim($_POST['label']) : '';
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '#';
    
    if (empty($label)) {
        return ['success' => false, 'message' => 'Le label est obligatoire'];
    }
    
    if (empty($titre)) {
        return ['success' => false, 'message' => 'Le titre est obligatoire'];
    }
    
    if (empty($bouton_texte)) {
        return ['success' => false, 'message' => 'Le texte du bouton est obligatoire'];
    }
    
    // Gestion de l'upload de l'image
    $image = null;
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_trending_image($_FILES['image']);
        
        if ($upload_result['success']) {
            $image = $upload_result['filename'];
            
            // Supprimer l'ancienne image si elle existe
            $current_config = get_trending_config();
            if ($current_config && !empty($current_config['image']) && $current_config['image'] !== $image && $current_config['image'] !== 'speaker.png') {
                delete_trending_image($current_config['image']);
            }
        } else {
            return ['success' => false, 'message' => $upload_result['message']];
        }
    } else {
        // Garder l'image existante si aucune nouvelle image n'est uploadée
        $current_config = get_trending_config($scope_id);
        if ($current_config && !empty($current_config['image'])) {
            $image = $current_config['image'];
        } else {
            $image = 'speaker.png'; // Image par défaut
        }
    }
    
    // Mettre à jour la configuration
    $data = [
        'label' => $label,
        'titre' => $titre,
        'bouton_texte' => $bouton_texte,
        'bouton_lien' => $bouton_lien,
        'image' => $image
    ];
    
    if (update_trending_config($data, $scope_id)) {
        return ['success' => true, 'message' => 'Configuration de la section trending mise à jour avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour de la configuration'];
    }
}

/**
 * Gère l'upload de l'image de la section trending
 * @param array $file Le fichier uploadé ($_FILES['image'])
 * @return array Résultat de l'upload ['success' => bool, 'filename' => string|null, 'message' => string]
 */
function upload_trending_image($file) {
    $upload_dir = __DIR__ . '/../upload/trending/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $result = upload_optimize_image_file($file, $upload_dir, 'trending', 'trending_');
    if (!empty($result['success']) && !empty($result['filename'])) {
        return ['success' => true, 'filename' => (string) $result['filename'], 'message' => 'Image optimisée et enregistrée'];
    }

    return [
        'success' => false,
        'filename' => null,
        'message' => (string) ($result['message'] ?? 'Erreur lors de l\'upload du fichier'),
    ];
}

?>

