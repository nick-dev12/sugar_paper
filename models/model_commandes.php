<?php
/**
 * Modèle pour la gestion des commandes
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Génère un numéro de commande unique
 * @return string Le numéro de commande
 */
function generate_numero_commande() {
    return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Crée une nouvelle commande avec ses produits
 * @param int $user_id L'ID de l'utilisateur
 * @param array $panier_items Les articles du panier
 * @param string $adresse_livraison L'adresse de livraison (zone sélectionnée)
 * @param string $telephone_livraison Le téléphone de livraison
 * @param string $notes Les notes optionnelles
 * @param int|null $zone_livraison_id ID de la zone de livraison (optionnel)
 * @param float $frais_livraison Frais de livraison en FCFA (défaut 0)
 * @return array|false Tableau avec 'success' et 'commande_id' ou False en cas d'erreur
 */
function create_commande($user_id, $panier_items, $adresse_livraison, $telephone_livraison, $notes = null, $zone_livraison_id = null, $frais_livraison = 0) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $sous_total = 0;
        foreach ($panier_items as $item) {
            $prix_unitaire = !empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] 
                ? $item['prix_promotion'] 
                : $item['prix'];
            $sous_total += $prix_unitaire * $item['quantite'];
        }
        
        $frais_livraison = (float) $frais_livraison;
        $montant_total = $sous_total + $frais_livraison;
        
        $numero_commande = generate_numero_commande();
        $stmt = $db->prepare("SELECT id FROM commandes WHERE numero_commande = :numero");
        $stmt->execute(['numero' => $numero_commande]);
        if ($stmt->fetch()) {
            $numero_commande = generate_numero_commande() . '-' . rand(100, 999);
        }
        
        $stmt = $db->prepare("
            INSERT INTO commandes (
                user_id, numero_commande, montant_total, adresse_livraison, 
                zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes
            ) VALUES (
                :user_id, :numero_commande, :montant_total, :adresse_livraison,
                :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes
            )
        ");
        
        $stmt->execute([
            'user_id' => $user_id,
            'numero_commande' => $numero_commande,
            'montant_total' => $montant_total,
            'adresse_livraison' => $adresse_livraison,
            'zone_livraison_id' => $zone_livraison_id ?: null,
            'frais_livraison' => $frais_livraison,
            'telephone_livraison' => $telephone_livraison,
            'notes' => $notes
        ]);
        
        $commande_id = $db->lastInsertId();
        
        // Insérer les produits de la commande
        foreach ($panier_items as $item) {
            $prix_unitaire = !empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] 
                ? $item['prix_promotion'] 
                : $item['prix'];
            $prix_total = $prix_unitaire * $item['quantite'];
            
            $stmt = $db->prepare("
                INSERT INTO commande_produits (
                    commande_id, produit_id, quantite, prix_unitaire, prix_total
                ) VALUES (
                    :commande_id, :produit_id, :quantite, :prix_unitaire, :prix_total
                )
            ");
            
            $stmt->execute([
                'commande_id' => $commande_id,
                'produit_id' => $item['id'],
                'quantite' => $item['quantite'],
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ]);
        }
        
        // Valider la transaction
        $db->commit();
        
        return [
            'success' => true,
            'commande_id' => $commande_id,
            'numero_commande' => $numero_commande
        ];
        
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        return false;
    }
}

/**
 * Récupère toutes les commandes d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return array Tableau des commandes
 */
function get_commandes_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM commandes 
            WHERE user_id = :user_id 
            ORDER BY date_commande DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $commandes ? $commandes : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une commande par son ID
 * @param int $commande_id L'ID de la commande
 * @param int $user_id L'ID de l'utilisateur (pour vérification)
 * @return array|false Les données de la commande ou False
 */
function get_commande_by_id($commande_id, $user_id = null) {
    global $db;
    
    try {
        $sql = "SELECT * FROM commandes WHERE id = :commande_id";
        $params = ['commande_id' => $commande_id];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $user_id;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $commande ? $commande : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'une commande
 * @param int $commande_id L'ID de la commande
 * @return array Tableau des produits de la commande
 */
function get_commande_produits($commande_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT cp.*, p.id as produit_id, p.nom, p.image_principale, p.poids, p.unite,
                   c.nom as categorie_nom, c.id as categorie_id,
                   cmd.numero_commande, cmd.date_commande, cmd.statut as statut_commande
            FROM commande_produits cp
            INNER JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            INNER JOIN commandes cmd ON cp.commande_id = cmd.id
            WHERE cp.commande_id = :commande_id
            ORDER BY c.nom ASC, p.nom ASC
        ");
        $stmt->execute(['commande_id' => $commande_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les commandes d'un utilisateur groupées par catégorie
 * @param int $user_id L'ID de l'utilisateur
 * @param int $categorie_id L'ID de la catégorie (optionnel)
 * @return array Tableau des commandes groupées par catégorie
 */
function get_commandes_by_categorie($user_id, $categorie_id = null) {
    global $db;
    
    try {
        $sql = "
            SELECT 
                c.id as categorie_id,
                c.nom as categorie_nom,
                cmd.id as commande_id,
                cmd.numero_commande,
                cmd.date_commande,
                cmd.statut as statut_commande,
                cmd.montant_total,
                cp.produit_id,
                p.nom as produit_nom,
                p.image_principale,
                p.poids,
                p.unite,
                cp.quantite,
                cp.prix_unitaire,
                cp.prix_total
            FROM commandes cmd
            INNER JOIN commande_produits cp ON cmd.id = cp.commande_id
            INNER JOIN produits p ON cp.produit_id = p.id
            INNER JOIN categories c ON p.categorie_id = c.id
            WHERE cmd.user_id = :user_id
        ";
        
        $params = ['user_id' => $user_id];
        
        if ($categorie_id !== null) {
            $sql .= " AND c.id = :categorie_id";
            $params['categorie_id'] = $categorie_id;
        }
        
        $sql .= " ORDER BY c.nom ASC, cmd.date_commande DESC, p.nom ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grouper par catégorie
        $grouped = [];
        foreach ($result as $row) {
            $cat_id = $row['categorie_id'];
            if (!isset($grouped[$cat_id])) {
                $grouped[$cat_id] = [
                    'categorie_id' => $cat_id,
                    'categorie_nom' => $row['categorie_nom'],
                    'produits' => []
                ];
            }
            $grouped[$cat_id]['produits'][] = $row;
        }
        
        return array_values($grouped);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère tous les produits commandés par un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param string $statut_commande Filtrer par statut de commande (optionnel)
 * @return array|false Tableau des produits commandés ou False en cas d'erreur
 */
function get_produits_commandes_by_user($user_id, $statut_commande = null) {
    global $db;
    
    try {
        $sql = "
            SELECT DISTINCT
                p.id,
                p.nom,
                p.description,
                p.prix,
                p.prix_promotion,
                p.stock,
                p.image_principale,
                p.poids,
                p.unite,
                p.statut,
                c.nom as categorie_nom,
                cp.quantite,
                cp.prix_unitaire,
                cp.prix_total,
                cmd.numero_commande,
                cmd.date_commande,
                cmd.statut as statut_commande
            FROM commandes cmd
            INNER JOIN commande_produits cp ON cmd.id = cp.commande_id
            INNER JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE cmd.user_id = :user_id
        ";
        
        $params = ['user_id' => $user_id];
        
        if ($statut_commande !== null) {
            $sql .= " AND cmd.statut = :statut_commande";
            $params['statut_commande'] = $statut_commande;
        }
        
        $sql .= " ORDER BY cmd.date_commande DESC, p.nom ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère le nombre de commandes d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre de commandes
 */
function count_commandes_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère le nombre d'articles dans le panier d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre d'articles
 */
function count_panier_items_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT SUM(quantite) FROM panier WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $count = $stmt->fetchColumn();
        return $count ? (int) $count : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Met à jour le statut d'une commande (pour l'utilisateur)
 * @param int $commande_id L'ID de la commande
 * @param int $user_id L'ID de l'utilisateur (pour vérification)
 * @param string $statut Le nouveau statut
 * @return bool True en cas de succès, False sinon
 */
function update_commande_statut_user($commande_id, $user_id, $statut) {
    global $db;
    
    try {
        // Vérifier que la commande appartient à l'utilisateur
        $commande = get_commande_by_id($commande_id, $user_id);
        if (!$commande) {
            return false;
        }
        
        // Mettre à jour le statut
        $stmt = $db->prepare("
            UPDATE commandes 
            SET statut = :statut,
                date_livraison = CASE WHEN :statut = 'livree' THEN NOW() ELSE date_livraison END
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            'id' => $commande_id,
            'user_id' => $user_id,
            'statut' => $statut
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

?>
