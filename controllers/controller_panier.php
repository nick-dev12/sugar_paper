<?php
/**
 * Contrôleur pour la gestion du panier
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_produits.php';

/**
 * Traite l'ajout d'un produit au panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_to_panier() {
    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'Vous devez être connecté pour ajouter des produits au panier.'];
    }
    
    if (!isset($_POST['produit_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }
    
    $user_id = $_SESSION['user_id'];
    $produit_id = (int)$_POST['produit_id'];
    $quantite = (int)$_POST['quantite'];
    
    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }
    
    // Vérifier que le produit existe et est actif
    $produit = get_produit_by_id($produit_id);
    if (!$produit || $produit['statut'] != 'actif') {
        return ['success' => false, 'message' => 'Ce produit n\'est pas disponible.'];
    }
    
    // Vérifier le stock disponible
    $item_panier = is_in_panier($user_id, $produit_id);
    $quantite_actuelle = $item_panier ? $item_panier['quantite'] : 0;
    $quantite_totale = $quantite_actuelle + $quantite;
    
    if ($quantite_totale > $produit['stock']) {
        return ['success' => false, 'message' => 'Stock insuffisant. Stock disponible : ' . $produit['stock']];
    }
    
    // Ajouter au panier
    if (add_to_panier($user_id, $produit_id, $quantite)) {
        return ['success' => true, 'message' => 'Produit ajouté au panier avec succès.'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier.'];
    }
}

/**
 * Traite la mise à jour de la quantité d'un produit dans le panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_panier() {
    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'Vous devez être connecté.'];
    }
    
    if (!isset($_POST['panier_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }
    
    $panier_id = (int)$_POST['panier_id'];
    $quantite = (int)$_POST['quantite'];
    
    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }
    
    // Récupérer l'élément du panier pour vérifier le stock
    $panier_items = get_panier_by_user($_SESSION['user_id']);
    $item = null;
    foreach ($panier_items as $panier_item) {
        if ($panier_item['panier_id'] == $panier_id) {
            $item = $panier_item;
            break;
        }
    }
    
    if (!$item) {
        return ['success' => false, 'message' => 'Élément du panier introuvable.'];
    }
    
    // Vérifier le stock
    if ($quantite > $item['stock']) {
        return ['success' => false, 'message' => 'Stock insuffisant. Stock disponible : ' . $item['stock']];
    }
    
    // Mettre à jour
    if (update_panier_quantite($panier_id, $quantite)) {
        return ['success' => true, 'message' => 'Quantité mise à jour.'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }
}

/**
 * Traite la suppression d'un produit du panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_from_panier() {
    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'Vous devez être connecté.'];
    }
    
    if (!isset($_POST['panier_id'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }
    
    $panier_id = (int)$_POST['panier_id'];
    
    if (delete_from_panier($panier_id)) {
        return ['success' => true, 'message' => 'Produit retiré du panier.'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}

?>

