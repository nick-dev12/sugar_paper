<?php
/**
 * Modèle pour la gestion du panier
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Ajoute un produit au panier ou met à jour la quantité
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @param int $quantite La quantité à ajouter
 * @return bool True en cas de succès, False sinon
 */
function add_to_panier($user_id, $produit_id, $quantite = 1) {
    global $db;
    
    try {
        // Vérifier si le produit existe déjà dans le panier
        $stmt = $db->prepare("SELECT id, quantite FROM panier WHERE user_id = :user_id AND produit_id = :produit_id");
        $stmt->execute(['user_id' => $user_id, 'produit_id' => $produit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Mettre à jour la quantité
            $new_quantite = $existing['quantite'] + $quantite;
            $stmt = $db->prepare("UPDATE panier SET quantite = :quantite WHERE id = :id");
            return $stmt->execute(['quantite' => $new_quantite, 'id' => $existing['id']]);
        } else {
            // Ajouter un nouvel élément
            $stmt = $db->prepare("INSERT INTO panier (user_id, produit_id, quantite) VALUES (:user_id, :produit_id, :quantite)");
            return $stmt->execute(['user_id' => $user_id, 'produit_id' => $produit_id, 'quantite' => $quantite]);
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour la quantité d'un produit dans le panier
 * @param int $panier_id L'ID de l'élément du panier
 * @param int $quantite La nouvelle quantité
 * @return bool True en cas de succès, False sinon
 */
function update_panier_quantite($panier_id, $quantite) {
    global $db;
    
    try {
        if ($quantite <= 0) {
            return delete_from_panier($panier_id);
        }
        
        $stmt = $db->prepare("UPDATE panier SET quantite = :quantite WHERE id = :id");
        return $stmt->execute(['quantite' => $quantite, 'id' => $panier_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un produit du panier
 * @param int $panier_id L'ID de l'élément du panier
 * @return bool True en cas de succès, False sinon
 */
function delete_from_panier($panier_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM panier WHERE id = :id");
        return $stmt->execute(['id' => $panier_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les produits du panier d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return array Tableau des produits du panier avec leurs détails
 */
function get_panier_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT p.*, pan.id as panier_id, pan.quantite, pan.date_ajout,
                   c.nom as categorie_nom
            FROM panier pan
            INNER JOIN produits p ON pan.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE pan.user_id = :user_id
            ORDER BY pan.date_ajout DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $items ? $items : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Vide le panier d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return bool True en cas de succès, False sinon
 */
function clear_panier($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM panier WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Calcule le total du panier d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return float Le montant total
 */
function get_panier_total($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT SUM(
                CASE 
                    WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion < p.prix 
                    THEN p.prix_promotion * pan.quantite
                    ELSE p.prix * pan.quantite
                END
            ) as total
            FROM panier pan
            INNER JOIN produits p ON pan.produit_id = p.id
            WHERE pan.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['total'] ? (float)$result['total'] : 0.0;
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Vérifie si un produit est dans le panier
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return array|false Les données du panier ou False
 */
function is_in_panier($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM panier WHERE user_id = :user_id AND produit_id = :produit_id");
        $stmt->execute(['user_id' => $user_id, 'produit_id' => $produit_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $item ? $item : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte le nombre total d'articles dans le panier d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre total d'articles (somme des quantités)
 */
function count_panier_items($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT SUM(quantite) as total FROM panier WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['total'] ? (int)$result['total'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

?>

