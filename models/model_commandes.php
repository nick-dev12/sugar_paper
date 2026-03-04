<?php
/**
 * Modèle pour la gestion des commandes
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

function _commande_produits_has_option_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'couleur'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

function _commandes_has_zone_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'zone_livraison_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

function _commande_produits_has_variante_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'variante_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

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
 * @param array $choix Choix couleur/poids/taille par panier_id [panier_id => ['couleur'=>..., 'poids'=>..., 'taille'=>...]]
 * @return array|false Tableau avec 'success' et 'commande_id' ou False en cas d'erreur
 */
function create_commande($user_id, $panier_items, $adresse_livraison, $telephone_livraison, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $choix = []) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $sous_total = 0;
        foreach ($panier_items as $item) {
            $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                ? (float) $item['panier_prix_unitaire']
                : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
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
        
        $params_cmd = [
            'user_id' => $user_id,
            'numero_commande' => $numero_commande,
            'montant_total' => $montant_total,
            'adresse_livraison' => $adresse_livraison,
            'telephone_livraison' => $telephone_livraison,
            'notes' => $notes
        ];
        
        if (_commandes_has_zone_columns()) {
            $params_cmd['zone_livraison_id'] = $zone_livraison_id ?: null;
            $params_cmd['frais_livraison'] = $frais_livraison;
            $stmt = $db->prepare("
                INSERT INTO commandes (
                    user_id, numero_commande, montant_total, adresse_livraison, 
                    zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes
                ) VALUES (
                    :user_id, :numero_commande, :montant_total, :adresse_livraison,
                    :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes
                )
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO commandes (
                    user_id, numero_commande, montant_total, adresse_livraison, 
                    telephone_livraison, statut, date_commande, notes
                ) VALUES (
                    :user_id, :numero_commande, :montant_total, :adresse_livraison,
                    :telephone_livraison, 'en_attente', NOW(), :notes
                )
            ");
        }
        $stmt->execute($params_cmd);
        
        $commande_id = $db->lastInsertId();
        
        // Insérer les produits de la commande
        foreach ($panier_items as $item) {
            $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                ? (float) $item['panier_prix_unitaire']
                : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
            $prix_total = $prix_unitaire * $item['quantite'];
            
            $couleur = $item['panier_couleur'] ?? null;
            $poids_choix = $item['panier_poids'] ?? null;
            $taille_choix = $item['panier_taille'] ?? null;
            $panier_id = isset($item['panier_id']) ? (int) $item['panier_id'] : 0;
            if ($panier_id > 0 && isset($choix[$panier_id])) {
                $c = $choix[$panier_id];
                if (isset($c['couleur']) && trim($c['couleur']) !== '') $couleur = trim($c['couleur']);
                if (isset($c['poids']) && trim($c['poids']) !== '') $poids_choix = trim($c['poids']);
                if (isset($c['taille']) && trim($c['taille']) !== '') $taille_choix = trim($c['taille']);
            }
            
            $variante_id = isset($item['panier_variante_id']) && $item['panier_variante_id'] ? (int) $item['panier_variante_id'] : null;
            $variante_nom = isset($item['panier_variante_nom']) && trim($item['panier_variante_nom']) ? trim($item['panier_variante_nom']) : null;
            $surcout_poids = isset($item['panier_surcout_poids']) ? (float) $item['panier_surcout_poids'] : 0;
            $surcout_taille = isset($item['panier_surcout_taille']) ? (float) $item['panier_surcout_taille'] : 0;
            
            $params = [
                'commande_id' => $commande_id,
                'produit_id' => $item['id'],
                'quantite' => $item['quantite'],
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ];
            
            $has_options = _commande_produits_has_option_columns();
            $has_variantes = _commande_produits_has_variante_columns();
            
            if ($has_options) {
                $params['couleur'] = $couleur;
                $params['poids'] = $poids_choix;
                $params['taille'] = $taille_choix;
            }
            if ($has_variantes) {
                $params['variante_id'] = $variante_id;
                $params['variante_nom'] = $variante_nom;
                $params['surcout_poids'] = $surcout_poids;
                $params['surcout_taille'] = $surcout_taille;
            }
            
            $cols = 'commande_id, produit_id, quantite, prix_unitaire, prix_total';
            $vals = ':commande_id, :produit_id, :quantite, :prix_unitaire, :prix_total';
            if ($has_options) {
                $cols .= ', couleur, poids, taille';
                $vals .= ', :couleur, :poids, :taille';
            }
            if ($has_variantes) {
                $cols .= ', variante_id, variante_nom, surcout_poids, surcout_taille';
                $vals .= ', :variante_id, :variante_nom, :surcout_poids, :surcout_taille';
            }
            $stmt = $db->prepare("INSERT INTO commande_produits ($cols) VALUES ($vals)");
            $stmt->execute($params);
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
        error_log('[create_commande] ' . $e->getMessage());
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
        $join_variante = _commande_produits_has_variante_columns()
            ? "LEFT JOIN produits_variantes pv ON cp.variante_id = pv.id AND pv.produit_id = p.id"
            : "";
        $img = _commande_produits_has_variante_columns()
            ? "COALESCE(pv.image, p.image_principale) as image_afficher"
            : "p.image_principale as image_afficher";
        $stmt = $db->prepare("
            SELECT cp.*, p.id as produit_id, p.nom, p.image_principale, p.poids, p.unite,
                   c.nom as categorie_nom, c.id as categorie_id,
                   cmd.numero_commande, cmd.date_commande, cmd.statut as statut_commande,
                   $img
            FROM commande_produits cp
            INNER JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            $join_variante
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
        $has_opts = _commande_produits_has_option_columns();
        $has_var = _commande_produits_has_variante_columns();
        $img = $has_var ? "COALESCE(pv.image, p.image_principale) as image_principale" : "p.image_principale as image_principale";
        $cols = "c.id as categorie_id, c.nom as categorie_nom, cmd.id as commande_id, cmd.numero_commande, cmd.date_commande, cmd.statut as statut_commande, cmd.montant_total, cp.produit_id, p.nom as produit_nom, $img, p.poids, p.unite, cp.quantite, cp.prix_unitaire, cp.prix_total";
        if ($has_opts) $cols .= ", cp.couleur, cp.poids as choix_poids, cp.taille";
        if ($has_var) $cols .= ", cp.variante_nom, cp.surcout_poids, cp.surcout_taille";
        $join_pv = $has_var ? "LEFT JOIN produits_variantes pv ON cp.variante_id = pv.id AND pv.produit_id = p.id" : "";
        $sql = "
            SELECT $cols
            FROM commandes cmd
            INNER JOIN commande_produits cp ON cmd.id = cp.commande_id
            INNER JOIN produits p ON cp.produit_id = p.id
            INNER JOIN categories c ON p.categorie_id = c.id
            $join_pv
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
