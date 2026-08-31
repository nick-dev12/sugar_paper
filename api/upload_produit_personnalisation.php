<?php
/**
 * Upload d'une image de référence pour personnalisation produit (photo comestible)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../includes/produit_personnalisation.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucune image reçue.']);
    exit;
}

$upload_dir = produit_personnalisation_upload_dir();
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Dossier d’upload indisponible.']);
    exit;
}

$result = upload_optimize_image_file(
    $_FILES['image'],
    $upload_dir,
    produit_personnalisation_upload_subdir(),
    'perso_prod_'
);

if (empty($result['success'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Impossible d’enregistrer l’image.',
    ]);
    exit;
}

$relative = (string) ($result['relative_path'] ?? '');
echo json_encode([
    'success' => true,
    'path' => $relative,
    'url' => produit_personnalisation_public_url($relative),
    'message' => 'Image enregistrée.',
]);
