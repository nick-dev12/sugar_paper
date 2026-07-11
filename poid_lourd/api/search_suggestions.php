<?php
/**
 * Suggestions de recherche catalogue (barre nav).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 8;
if ($limit < 1 || $limit > 12) {
    $limit = 8;
}



if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['success' => true, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$boutique_admin_id = null;
if (!empty($_GET['boutique'])) {
    require_once __DIR__ . '/../models/model_admin.php';
    require_once __DIR__ . '/../includes/boutique_slug_redirect.php';
    $row = resolve_vendeur_by_boutique_slug(trim((string) $_GET['boutique']));
    if ($row && ($row['role'] ?? '') === 'vendeur' && ($row['statut'] ?? '') === 'actif') {
        $boutique_admin_id = (int) $row['id'];
    }
}

$produits = search_produits_with_filters($q, null, null, null, 'date', 0, $limit, $boutique_admin_id);
$suggestions = [];

foreach ($produits as $produit) {
    $prix = !empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < (float) $produit['prix']
        ? (float) $produit['prix_promotion']
        : (float) $produit['prix'];
    $suggestions[] = [
        'id' => (int) ($produit['id'] ?? 0),
        'nom' => (string) ($produit['nom'] ?? ''),
        'prix' => $prix,
        'image_url' => upload_image_url($produit['image_principale'] ?? '', 'sm'),
        'url' => '/produit.php?id=' . (int) ($produit['id'] ?? 0),
    ];
}

echo json_encode([
    'success' => true,
    'suggestions' => $suggestions,
], JSON_UNESCAPED_UNICODE);