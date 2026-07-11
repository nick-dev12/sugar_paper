<?php
/**
 * API pour récupérer les produits avec pagination et filtres
 * Utilisé pour le chargement progressif via JavaScript
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/boutique_slug_redirect.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';
require_once __DIR__ . '/../includes/catalogue_shuffle.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

$boutique_admin_id = null;
if (!empty($_GET['boutique'])) {
    $row = resolve_vendeur_by_boutique_slug(trim((string) $_GET['boutique']));
    if ($row && ($row['role'] ?? '') === 'vendeur' && ($row['statut'] ?? '') === 'actif') {
        $boutique_admin_id = (int) $row['id'];
    }
}

// Récupérer les paramètres
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$prix_min = isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float) $_GET['prix_min'] : null;
$prix_max = isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float) $_GET['prix_max'] : null;
$categorie_id = isset($_GET['categorie']) && $_GET['categorie'] !== '' ? (int) $_GET['categorie'] : null;
$tri = isset($_GET['tri']) && in_array($_GET['tri'], ['date', 'prix_asc', 'prix_desc', 'nom']) ? $_GET['tri'] : 'date';

// Valider les paramètres
if ($offset < 0) $offset = 0;
if ($limit < 1 || $limit > 50) $limit = 20;

$has_filters = !empty($recherche) || $prix_min !== null || $prix_max !== null || $categorie_id !== null || $tri !== 'date';

$shuffle_seed = null;
if (!$has_filters && $boutique_admin_id === null) {
    $seed_param = isset($_GET['seed']) ? (int) $_GET['seed'] : null;
    $shuffle_seed = catalogue_seed_pagination('produits', $seed_param, false);
}

// Récupérer les produits (avec ou sans filtres)
if ($has_filters) {
    $produits = search_produits_with_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri, $offset, $limit, $boutique_admin_id);
} else {
    $produits = get_all_produits_paginated($offset, $limit, $boutique_admin_id, $shuffle_seed);
}

if (!empty($produits) && file_exists(__DIR__ . '/../models/model_produits_avis.php')) {
    require_once __DIR__ . '/../models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

// Formater les produits pour le JSON
$produits_formatted = [];
foreach ($produits as $produit) {
    $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'] 
        ? $produit['prix_promotion'] 
        : $produit['prix'];
    $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
    $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;

    $boutique_nom = '';
    $boutique_slug = '';
    $boutique_href = '';
    if ($boutique_admin_id === null) {
        $boutique_nom = produit_public_boutique_label($produit);
        $boutique_slug = trim((string) ($produit['vendeur_boutique_slug'] ?? ''));
        if ($boutique_slug !== '') {
            $boutique_href = boutique_url('index.php', $boutique_slug);
        }
    }
    
    $produits_formatted[] = [
        'id' => $produit['id'],
        'nom' => $produit['nom'],
        'prix' => $produit['prix'],
        'prix_promotion' => $produit['prix_promotion'],
        'prix_affichage' => $prix_affichage,
        'has_promotion' => $has_promotion,
        'pourcentage_promo' => $pourcentage_promo,
        'stock' => $produit['stock'],
        'poids' => $produit['poids'] ?? '',
        'categorie_nom' => $produit['categorie_nom'] ?? '',
        'image_principale' => $produit['image_principale'] ?? 'produit1.jpg',
        'image_url' => upload_image_url($produit['image_principale'] ?? '', 'md'),
        'boutique_nom' => $boutique_nom,
        'boutique_slug' => $boutique_slug,
        'boutique_href' => $boutique_href,
        'avis_moyenne' => (float) ($produit['avis_moyenne'] ?? 0),
        'avis_count' => (int) ($produit['avis_count'] ?? 0),
    ];
}

// Retourner la réponse JSON
echo json_encode([
    'success' => true,
    'produits' => $produits_formatted,
    'count' => count($produits_formatted),
    'offset' => $offset,
    'limit' => $limit
]);

?>

