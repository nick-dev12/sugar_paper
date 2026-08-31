<?php
/**
 * API — produits d'une section accueil (pagination)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_share.php';
require_once __DIR__ . '/../includes/produit_prix_display.php';
require_once __DIR__ . '/../includes/cake_topper_cp.php';
require_once __DIR__ . '/../models/model_produits.php';

$section = isset($_GET['section']) ? normalize_produit_section_accueil($_GET['section']) : null;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

if (!$section) {
    echo json_encode(['success' => false, 'message' => 'Section invalide.', 'produits' => []]);
    exit;
}

if ($offset < 0) {
    $offset = 0;
}
if ($limit < 1 || $limit > 50) {
    $limit = 20;
}

$produits = get_produits_by_home_section($section, $offset, $limit);
$produits_formatted = [];

foreach ($produits as $produit) {
    $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
        ? $produit['prix_promotion']
        : $produit['prix'];
    $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
    $pourcentage_promo = $has_promotion && (float) $produit['prix'] > 0
        ? (int) round((((float) $produit['prix'] - (float) $produit['prix_promotion']) / (float) $produit['prix']) * 100)
        : 0;

    $share_data = produit_share_build_data($produit);

    $item = [
        'id' => $produit['id'],
        'nom' => $produit['nom'],
        'prix' => $produit['prix'],
        'prix_promotion' => $produit['prix_promotion'],
        'prix_affichage' => $prix_affichage,
        'has_promotion' => $has_promotion,
        'pourcentage_promo' => $pourcentage_promo,
        'show_price_from' => produit_uses_price_from_label($produit),
        'section_accueil' => $produit['section_accueil'] ?? null,
        'stock' => $produit['stock'],
        'poids' => $produit['poids'] ?? '',
        'categorie_nom' => $produit['categorie_nom'] ?? '',
        'image_principale' => $produit['image_principale'] ?? 'produit1.jpg',
        'image_url' => !empty($produit['image_principale'])
            ? upload_image_url($produit['image_principale'], 'md')
            : '/image/produit1.jpg',
        'share_url' => $share_data['share_url'] ?? '',
        'share_title' => $share_data['share_title'] ?? ($produit['nom'] ?? ''),
        'share_text' => $share_data['share_text'] ?? '',
        'share_image' => $share_data['share_image'] ?? '',
        'uses_cp_modal' => false,
    ];

    if (produit_listing_uses_cake_topper_cp($produit)) {
        $ctx = resolve_cp_modal_context_for_produit($produit);
        $item['uses_cp_modal'] = true;
        $item['cp'] = [
            'catalogue_produit_id' => (int) $ctx['catalogue_produit_id'],
            'boutique_produit_id' => (int) $ctx['boutique_produit_id'],
            'nom' => $ctx['nom'],
            'image' => $ctx['image'],
            'prix_min' => (float) $ctx['prix_min'],
            'prix_max' => (float) $ctx['prix_max'],
        ];
    }

    $produits_formatted[] = $item;
}

echo json_encode([
    'success' => true,
    'produits' => $produits_formatted,
    'count' => count($produits_formatted),
    'offset' => $offset,
    'limit' => $limit,
    'total' => count_produits_by_home_section($section),
    'section' => $section,
]);
