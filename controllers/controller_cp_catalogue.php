<?php
/**
 * Contrôleur catalogue commandes personnalisées (admin)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_cp_catalogue.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

function cp_catalogue_admin_csrf_token() {
    if (empty($_SESSION['cp_catalogue_csrf'])) {
        $_SESSION['cp_catalogue_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['cp_catalogue_csrf'];
}

function cp_catalogue_verify_csrf() {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    return $token !== '' && !empty($_SESSION['cp_catalogue_csrf'])
        && hash_equals((string) $_SESSION['cp_catalogue_csrf'], $token);
}

function upload_cp_catalogue_image($file, $field_name = 'image') {
    if (!isset($file[$field_name]) || ($file[$field_name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }
    $upload_dir = __DIR__ . '/../upload/catalogue-personnalise/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $result = upload_optimize_image_file($file[$field_name], $upload_dir, 'catalogue-personnalise', 'cp_cat_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        return (string) $result['relative_path'];
    }
    return false;
}

function process_cp_catalogue_dossier_actions() {
    $result = ['success' => false, 'message' => ''];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cp_catalogue_verify_csrf()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result['message'] = 'Session expirée. Veuillez réessayer.';
        }
        return $result;
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'add_dossier') {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
        if ($nom === '') {
            $result['message'] = 'Le nom du dossier est obligatoire.';
            return $result;
        }
        if ($position < 0) {
            $position = 0;
        }
        $id = create_cp_dossier($nom, $position);
        if ($id) {
            $result['success'] = true;
            $result['message'] = 'Dossier créé avec succès.';
        } else {
            $result['message'] = 'Impossible de créer le dossier.';
        }
        return $result;
    }

    if ($action === 'update_dossier') {
        $id = isset($_POST['dossier_id']) ? (int) $_POST['dossier_id'] : 0;
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
        if ($id <= 0 || $nom === '') {
            $result['message'] = 'Données du dossier invalides.';
            return $result;
        }
        if (update_cp_dossier($id, $nom, max(0, $position))) {
            $result['success'] = true;
            $result['message'] = 'Dossier mis à jour.';
        } else {
            $result['message'] = 'Impossible de mettre à jour le dossier.';
        }
        return $result;
    }

    if ($action === 'delete_dossier') {
        $id = isset($_POST['dossier_id']) ? (int) $_POST['dossier_id'] : 0;
        if ($id <= 0) {
            $result['message'] = 'Dossier invalide.';
            return $result;
        }
        $del = delete_cp_dossier($id);
        $result['success'] = !empty($del['success']);
        $result['message'] = $del['message'] ?? '';
        return $result;
    }

    return $result;
}

function process_cp_catalogue_produit_actions($dossier_id) {
    $result = ['success' => false, 'message' => ''];
    $dossier_id = (int) $dossier_id;
    if ($dossier_id <= 0) {
        $result['message'] = 'Dossier invalide.';
        return $result;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cp_catalogue_verify_csrf()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result['message'] = 'Session expirée. Veuillez réessayer.';
        }
        return $result;
    }

    $dossier = get_cp_dossier_by_id($dossier_id);
    if (!$dossier) {
        $result['message'] = 'Dossier introuvable.';
        return $result;
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'add_produit') {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $prix_min = isset($_POST['prix_min']) ? trim($_POST['prix_min']) : '';
        $prix_max = isset($_POST['prix_max']) ? trim($_POST['prix_max']) : '';

        if ($nom === '') {
            $result['message'] = 'Le nom du produit est obligatoire.';
            return $result;
        }
        if (!is_numeric($prix_min) || !is_numeric($prix_max)) {
            $result['message'] = 'Les prix doivent être des nombres valides.';
            return $result;
        }
        $prix_min = (float) $prix_min;
        $prix_max = (float) $prix_max;
        if ($prix_min < 0 || $prix_max < 0) {
            $result['message'] = 'Les prix ne peuvent pas être négatifs.';
            return $result;
        }
        if ($prix_max < $prix_min) {
            $result['message'] = 'Le prix maximum doit être supérieur ou égal au prix minimum.';
            return $result;
        }

        $image = upload_cp_catalogue_image($_FILES, 'image');
        if (!$image) {
            $result['message'] = 'Une image valide est obligatoire (JPG, PNG, WEBP, GIF).';
            return $result;
        }

        $id = create_cp_produit([
            'dossier_id' => $dossier_id,
            'nom' => $nom,
            'image' => $image,
            'prix_min' => $prix_min,
            'prix_max' => $prix_max,
        ]);
        if ($id) {
            $result['success'] = true;
            $result['message'] = 'Produit ajouté avec succès.';
        } else {
            $result['message'] = 'Impossible d\'ajouter le produit.';
        }
        return $result;
    }

    if ($action === 'delete_produit') {
        $produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
        $produit = get_cp_produit_by_id($produit_id);
        if (!$produit || (int) $produit['dossier_id'] !== $dossier_id) {
            $result['message'] = 'Produit introuvable.';
            return $result;
        }
        if (delete_cp_produit($produit_id)) {
            $result['success'] = true;
            $result['message'] = 'Produit supprimé.';
        } else {
            $result['message'] = 'Impossible de supprimer le produit.';
        }
        return $result;
    }

    return $result;
}
