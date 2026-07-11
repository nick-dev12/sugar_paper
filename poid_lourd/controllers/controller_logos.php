<?php
/**
 * Contrôleur pour la gestion des logos partenaires
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_logos.php';
require_once __DIR__ . '/../includes/admin_param_boutique_scope.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';

/**
 * Vendeur : uniquement ses logos ; plateforme : logos sans admin_id.
 */
function admin_logo_row_allowed(array $row) {
    $scope = admin_param_boutique_scope_id();
    if ($scope !== null) {
        return isset($row['admin_id']) && (int) $row['admin_id'] === (int) $scope;
    }
    if (db_table_has_column('logos', 'admin_id')) {
        $aid = $row['admin_id'] ?? null;
        return $aid === null || $aid === '';
    }
    return true;
}

/**
 * Upload une image de logo
 * @param array $file $_FILES
 * @param string $field Nom du champ (défaut: image)
 * @return string|false Chemin relatif (logos/xxx.png) ou False
 */
function upload_logo_image($file, $field = 'image') {
    if (!isset($file[$field]) || $file[$field]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $upload_dir = __DIR__ . '/../upload/logos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $info = $file[$field];
    $result = upload_optimize_image_file($info, $upload_dir, 'logos', 'logo_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        return (string) $result['relative_path'];
    }
    return false;
}

/**
 * Traite l'ajout d'un logo
 * @return array ['success' => bool, 'message' => string]
 */
function process_add_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_logo'])) {
        return ['success' => false, 'message' => ''];
    }
    $image = upload_logo_image($_FILES, 'image');
    if (!$image) {
        return ['success' => false, 'message' => 'Veuillez sélectionner une image valide (JPG, PNG, GIF, WebP, max 20 Mo).'];
    }
    $ordre = isset($_POST['ordre']) ? (int) $_POST['ordre'] : 0;
    $scope = admin_param_boutique_scope_id();
    $owner = $scope !== null ? (int) $scope : null;
    $id = create_logo($image, $ordre, 'actif', $owner);
    if ($id) {
        return ['success' => true, 'message' => 'Logo ajouté avec succès.'];
    }
    @unlink(__DIR__ . '/../upload/' . $image);
    return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
}

/**
 * Traite la modification d'un logo
 * @return array
 */
function process_update_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['update_logo'])) {
        return ['success' => false, 'message' => ''];
    }
    $logo_id = isset($_POST['logo_id']) ? (int) $_POST['logo_id'] : 0;
    $logo = get_logo_by_id($logo_id);
    if (!$logo) {
        return ['success' => false, 'message' => 'Logo introuvable.'];
    }
    if (!admin_logo_row_allowed($logo)) {
        return ['success' => false, 'message' => 'Accès refusé.'];
    }
    $image = $logo['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $new_image = upload_logo_image($_FILES, 'image');
        if ($new_image) {
            $old_path = __DIR__ . '/../upload/' . $logo['image'];
            if (file_exists($old_path)) {
                @unlink($old_path);
            }
            $image = $new_image;
        }
    }
    $ordre = isset($_POST['ordre']) ? (int) $_POST['ordre'] : $logo['ordre'];
    if (update_logo($logo_id, $image, $ordre, null)) {
        return ['success' => true, 'message' => 'Logo modifié avec succès.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la modification.'];
}

/**
 * Traite la suppression d'un logo
 * @return array
 */
function process_delete_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'delete') {
        return ['success' => false, 'message' => ''];
    }
    $logo_id = isset($_POST['logo_id']) ? (int) $_POST['logo_id'] : 0;
    $logo = get_logo_by_id($logo_id);
    if (!$logo) {
        return ['success' => false, 'message' => 'Logo introuvable.'];
    }
    if (!admin_logo_row_allowed($logo)) {
        return ['success' => false, 'message' => 'Accès refusé.'];
    }
    if (delete_logo($logo_id)) {
        $path = __DIR__ . '/../upload/' . $logo['image'];
        if (file_exists($path)) {
            @unlink($path);
        }
        return ['success' => true, 'message' => 'Logo supprimé.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
}
