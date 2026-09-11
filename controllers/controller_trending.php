<?php
/**
 * Contrôleur pour la gestion de la section trending
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_trending.php';

/**
 * Traite le formulaire de modification de la section trending
 * @return array Résultat de l'opération ['success' => bool, 'message' => string, 'modal' => string]
 */
function process_update_trending() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }

    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

    if ($action === 'delete_image' || !empty($_POST['delete_spotlight_image_id'])) {
        $image_id = (int) ($_POST['delete_spotlight_image_id'] ?? $_POST['image_id'] ?? 0);
        if (delete_trending_spotlight_image_by_id($image_id)) {
            return ['success' => true, 'message' => 'Image supprimée du carrousel'];
        }
        return ['success' => false, 'message' => 'Impossible de supprimer cette image'];
    }

    if ($action === 'add_images') {
        return process_trending_add_images();
    }

    if ($action === 'replace_image') {
        return process_trending_replace_image();
    }

    if ($action === 'save_text') {
        return process_trending_save_text();
    }

    return ['success' => false, 'message' => 'Action non reconnue'];
}

/**
 * Enregistre le texte de la bannière (label, titre, description, bouton)
 * @return array
 */
function process_trending_save_text() {
    $label = isset($_POST['label']) ? trim($_POST['label']) : '';
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '#';

    if ($label === '') {
        return ['success' => false, 'message' => 'Le label est obligatoire', 'modal' => 'text'];
    }

    if ($titre === '') {
        return ['success' => false, 'message' => 'Le titre est obligatoire', 'modal' => 'text'];
    }

    if ($bouton_texte === '') {
        $bouton_texte = 'Découvrir';
    }

    $current_config = get_trending_config();
    $image = !empty($current_config['image']) ? $current_config['image'] : 'speaker.png';

    $data = [
        'label' => $label,
        'titre' => $titre,
        'description' => $description,
        'bouton_texte' => $bouton_texte,
        'bouton_lien' => $bouton_lien,
        'image' => $image,
    ];

    if (update_trending_config($data)) {
        return ['success' => true, 'message' => 'Texte de la bannière enregistré'];
    }

    return ['success' => false, 'message' => 'Erreur lors de l’enregistrement du texte', 'modal' => 'text'];
}

/**
 * Ajoute une ou plusieurs images au carrousel
 * @return array
 */
function process_trending_add_images() {
    $uploaded = trending_upload_posted_images('spotlight_images');
    if (empty($uploaded['filenames'])) {
        return [
            'success' => false,
            'message' => $uploaded['error'] !== '' ? $uploaded['error'] : 'Veuillez sélectionner au moins une image',
            'modal' => 'images',
        ];
    }

    $added = 0;
    foreach ($uploaded['filenames'] as $filename) {
        if (add_trending_spotlight_image($filename)) {
            $added++;
        }
    }

    if ($added > 0) {
        return ['success' => true, 'message' => $added . ' image(s) ajoutée(s) au carrousel'];
    }

    return ['success' => false, 'message' => 'Impossible d’enregistrer les images', 'modal' => 'images'];
}

/**
 * Remplace une image du carrousel
 * @return array
 */
function process_trending_replace_image() {
    $image_id = (int) ($_POST['image_id'] ?? 0);
    $existing = get_trending_spotlight_image_by_id($image_id);
    if (!$existing) {
        return ['success' => false, 'message' => 'Image introuvable', 'modal' => 'images', 'image_id' => $image_id];
    }

    $uploaded = trending_upload_posted_images('spotlight_image');
    if (empty($uploaded['filenames'])) {
        return [
            'success' => false,
            'message' => $uploaded['error'] !== '' ? $uploaded['error'] : 'Veuillez choisir une nouvelle image',
            'modal' => 'images',
            'image_id' => $image_id,
        ];
    }

    $filename = $uploaded['filenames'][0];
    if (update_trending_spotlight_image($image_id, $filename)) {
        return ['success' => true, 'message' => 'Image mise à jour'];
    }

    return ['success' => false, 'message' => 'Impossible de remplacer cette image', 'modal' => 'images', 'image_id' => $image_id];
}

/**
 * Upload les fichiers image postés (champ simple ou multiple)
 * @param string $field_name
 * @return array{filenames: array<int, string>, error: string}
 */
function trending_upload_posted_images($field_name) {
    $filenames = [];
    $error = '';

    if (!isset($_FILES[$field_name])) {
        return ['filenames' => [], 'error' => 'Aucun fichier reçu'];
    }

    $files = $_FILES[$field_name];
    $items = [];

    if (is_array($files['name'])) {
        $file_count = count($files['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $items[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
    } else {
        $items[] = $files;
    }

    foreach ($items as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $upload_result = upload_trending_image($file);
        if (!empty($upload_result['success']) && !empty($upload_result['filename'])) {
            $filenames[] = $upload_result['filename'];
        } elseif ($error === '' && !empty($upload_result['message'])) {
            $error = $upload_result['message'];
        }
    }

    if (empty($filenames) && $error === '') {
        $error = 'Aucun fichier image valide';
    }

    return ['filenames' => $filenames, 'error' => $error];
}

/**
 * Gère l'upload de l'image de la section trending
 * @param array $file Le fichier uploadé ($_FILES['image'])
 * @return array Résultat de l'upload ['success' => bool, 'filename' => string|null, 'message' => string]
 */
function upload_trending_image($file) {
    $upload_dir = __DIR__ . '/../upload/trending/';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $result = upload_optimize_image_file($file, $upload_dir, 'trending', 'trending_');
    if (!empty($result['success']) && !empty($result['filename'])) {
        return [
            'success' => true,
            'filename' => (string) $result['filename'],
            'message' => 'Image optimisée et enregistrée',
        ];
    }

    return [
        'success' => false,
        'filename' => null,
        'message' => (string) ($result['message'] ?? 'Erreur lors de l’upload du fichier'),
    ];
}
