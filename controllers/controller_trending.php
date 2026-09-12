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

    if ($action === 'delete_slide') {
        $slide_id = (int) ($_POST['slide_id'] ?? 0);
        if (delete_trending_slide($slide_id)) {
            return ['success' => true, 'message' => 'Slide supprimé'];
        }
        return ['success' => false, 'message' => 'Impossible de supprimer ce slide'];
    }

    if ($action === 'delete_image' || !empty($_POST['delete_spotlight_image_id'])) {
        $slide_id = (int) ($_POST['delete_spotlight_image_id'] ?? $_POST['slide_id'] ?? 0);
        if (remove_trending_slide_image($slide_id)) {
            return ['success' => true, 'message' => 'Image supprimée du slide'];
        }
        return ['success' => false, 'message' => 'Impossible de supprimer cette image'];
    }

    if ($action === 'add_image') {
        return process_trending_add_slide_image();
    }

    if ($action === 'replace_image') {
        return process_trending_replace_slide_image();
    }

    if ($action === 'save_text') {
        return process_trending_save_text();
    }

    return ['success' => false, 'message' => 'Action non reconnue'];
}

/**
 * Enregistre ou crée le texte d'un slide
 * @return array
 */
function process_trending_save_text() {
    $slide_id = (int) ($_POST['slide_id'] ?? 0);
    $label = isset($_POST['label']) ? trim($_POST['label']) : '';
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $section_key = trending_normalize_section_key($_POST['section_key'] ?? 'kit_impression');

    if ($label === '') {
        return ['success' => false, 'message' => 'Le label est obligatoire', 'modal' => 'text', 'slide_id' => $slide_id];
    }

    if ($titre === '') {
        return ['success' => false, 'message' => 'Le titre est obligatoire', 'modal' => 'text', 'slide_id' => $slide_id];
    }

    if ($bouton_texte === '') {
        $bouton_texte = 'Découvrir';
    }

    $data = [
        'label' => $label,
        'titre' => $titre,
        'description' => $description,
        'bouton_texte' => $bouton_texte,
        'section_key' => $section_key,
    ];

    if ($slide_id > 0) {
        if (update_trending_slide_text($slide_id, $data)) {
            return ['success' => true, 'message' => 'Texte du slide enregistré'];
        }
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement du texte', 'modal' => 'text', 'slide_id' => $slide_id];
    }

    if (add_trending_slide($data)) {
        return ['success' => true, 'message' => 'Nouveau slide texte ajouté'];
    }

    return ['success' => false, 'message' => 'Erreur lors de la création du slide', 'modal' => 'text'];
}

/**
 * Ajoute une image liée à un slide texte
 * @return array
 */
function process_trending_add_slide_image() {
    $slide_id = (int) ($_POST['slide_id'] ?? 0);
    $slide = get_trending_slide_by_id($slide_id);
    if (!$slide) {
        return ['success' => false, 'message' => 'Slide texte introuvable', 'modal' => 'images'];
    }

    if (trim((string) ($slide['image'] ?? '')) !== '') {
        return ['success' => false, 'message' => 'Ce slide possède déjà une image. Modifiez-la ou choisissez un autre slide.', 'modal' => 'images', 'slide_id' => $slide_id];
    }

    $uploaded = trending_upload_posted_images('spotlight_image');
    if (empty($uploaded['filenames'])) {
        return [
            'success' => false,
            'message' => $uploaded['error'] !== '' ? $uploaded['error'] : 'Veuillez sélectionner une image',
            'modal' => 'images',
            'slide_id' => $slide_id,
        ];
    }

    $filename = $uploaded['filenames'][0];
    if (set_trending_slide_image($slide_id, $filename)) {
        return ['success' => true, 'message' => 'Image liée au slide'];
    }

    return ['success' => false, 'message' => 'Impossible d’enregistrer l’image', 'modal' => 'images', 'slide_id' => $slide_id];
}

/**
 * Remplace l'image d'un slide
 * @return array
 */
function process_trending_replace_slide_image() {
    $slide_id = (int) ($_POST['slide_id'] ?? 0);
    $slide = get_trending_slide_by_id($slide_id);
    if (!$slide) {
        return ['success' => false, 'message' => 'Slide introuvable', 'modal' => 'images', 'slide_id' => $slide_id];
    }

    $uploaded = trending_upload_posted_images('spotlight_image');
    if (empty($uploaded['filenames'])) {
        return [
            'success' => false,
            'message' => $uploaded['error'] !== '' ? $uploaded['error'] : 'Veuillez choisir une nouvelle image',
            'modal' => 'images',
            'slide_id' => $slide_id,
        ];
    }

    $filename = $uploaded['filenames'][0];
    if (set_trending_slide_image($slide_id, $filename)) {
        return ['success' => true, 'message' => 'Image mise à jour'];
    }

    return ['success' => false, 'message' => 'Impossible de remplacer cette image', 'modal' => 'images', 'slide_id' => $slide_id];
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
