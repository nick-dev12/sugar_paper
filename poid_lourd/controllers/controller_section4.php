<?php
/**
 * Contrôleur pour la gestion de la section4
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_section4.php';
require_once __DIR__ . '/../includes/admin_param_boutique_scope.php';

/**
 * Traite le formulaire de modification de la section4
 * @return array Résultat de l'opération ['success' => bool, 'message' => string]
 */
function process_update_section4() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }
    $boutique_scope = admin_param_boutique_scope_id();
    $scope_id = $boutique_scope !== null ? (int) $boutique_scope : null;
    
    // Données optionnelles (titre et texte peuvent être vides)
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $texte = isset($_POST['texte']) ? trim($_POST['texte']) : '';
    $statut = isset($_POST['statut']) && $_POST['statut'] === 'inactif' ? 'inactif' : 'actif';
    
    // Gestion de l'upload de l'image
    $image_fond = null;
    
    if (isset($_FILES['image_fond']) && $_FILES['image_fond']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_section4_image($_FILES['image_fond']);
        
        if ($upload_result['success']) {
            $image_fond = $upload_result['filename'];
            
            // Supprimer l'ancienne image si elle existe
            $current_config = get_section4_config($scope_id);
            if ($current_config && !empty($current_config['image_fond']) && $current_config['image_fond'] !== $image_fond) {
                delete_section4_image($current_config['image_fond']);
            }
        } else {
            return ['success' => false, 'message' => $upload_result['message']];
        }
    } else {
        // Garder l'image existante si aucune nouvelle image n'est uploadée
        $current_config = get_section4_config($scope_id);
        if ($current_config && !empty($current_config['image_fond'])) {
            $image_fond = $current_config['image_fond'];
        }
    }
    
    $data = [
        'titre' => $titre,
        'texte' => $texte,
        'image_fond' => $image_fond,
        'statut' => $statut
    ];
    
    $result = update_section4_config($data, $scope_id);
    if ($result['success']) {
        return ['success' => true, 'message' => 'Configuration de la section4 mise à jour avec succès'];
    }
    $msg = !empty($result['message']) ? $result['message'] : 'Erreur lors de la mise à jour de la configuration';
    return ['success' => false, 'message' => $msg];
}

/**
 * Gère l'upload de l'image de fond de la section4
 * @param array $file Le fichier uploadé ($_FILES['image_fond'])
 * @return array Résultat de l'upload ['success' => bool, 'filename' => string|null, 'message' => string]
 */
function upload_section4_image($file) {
    $upload_dir = __DIR__ . '/../upload/section4/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $result = upload_optimize_image_file($file, $upload_dir, 'section4', 'section4_');
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

