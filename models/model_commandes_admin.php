<?php
/**
 * Modèle pour la gestion des commandes (Admin)
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère toutes les commandes
 * @param string $statut Filtrer par statut (optionnel)
 * @return array|false Tableau des commandes ou False en cas d'erreur
 */
function get_all_commandes($statut = null) {
    global $db;
    
    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT c.*, u.nom as user_nom, u.prenom as user_prenom, u.email as user_email
                FROM commandes c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.statut = :statut
                ORDER BY c.date_commande DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT c.*, u.nom as user_nom, u.prenom as user_prenom, u.email as user_email
                FROM commandes c
                INNER JOIN users u ON c.user_id = u.id
                ORDER BY c.date_commande DESC
            ");
            $stmt->execute();
        }
        
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $commandes ? $commandes : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une commande par son ID
 * @param int $commande_id L'ID de la commande
 * @return array|false Les données de la commande ou False si non trouvé
 */
function get_commande_by_id($commande_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT c.*, u.nom as user_nom, u.prenom as user_prenom, u.email as user_email, u.telephone as user_telephone
            FROM commandes c
            INNER JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $commande ? $commande : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'une commande
 * @param int $commande_id L'ID de la commande
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_produits_by_commande($commande_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT cp.*, p.nom as produit_nom, p.image_principale, c.nom as categorie_nom
            FROM commande_produits cp
            INNER JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE cp.commande_id = :commande_id
            ORDER BY cp.id
        ");
        
        $stmt->execute(['commande_id' => $commande_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'une commande
 * @param int $commande_id L'ID de la commande
 * @param string $statut Le nouveau statut
 * @return bool True en cas de succès, False sinon
 */
function update_commande_statut($commande_id, $statut) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE commandes 
            SET statut = :statut,
                date_livraison = CASE WHEN :statut = 'livree' THEN NOW() ELSE date_livraison END
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $commande_id,
            'statut' => $statut
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte les commandes par statut
 * @param string $statut Le statut à compter
 * @return int Le nombre de commandes
 */
function count_commandes_by_statut($statut = null) {
    global $db;
    
    try {
        if ($statut) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE statut = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes");
            $stmt->execute();
        }
        
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

?>

