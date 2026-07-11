<?php
/**
 * Modèle pour la gestion du panier
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

function _panier_has_variante_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM panier LIKE 'variante_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) { $has = false; }
    }
    return $has;
}

function _panier_has_vendeur_column() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM panier LIKE 'vendeur_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) { $has = false; }
    }
    return $has;
}

/**
 * Ajoute un produit au panier ou met à jour la quantité
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @param int $quantite La quantité à ajouter
 * @param string|null $couleur Option couleur (optionnel)
 * @param string|null $poids Option poids (optionnel)
 * @param string|null $taille Option taille (optionnel)
 * @param int|null $variante_id ID variante (optionnel)
 * @param string|null $variante_nom Nom variante (optionnel)
 * @param string|null $variante_image Image variante (optionnel)
 * @param float $surcout_poids Surcoût poids (optionnel)
 * @param float $surcout_taille Surcoût taille (optionnel)
 * @param float|null $prix_unitaire Prix unitaire final (optionnel, pour variante + surcoûts)
 * @param int|null $vendeur_id ID vendeur (admin). Si null, dérivé du produit (admin_id).
 * @return bool True en cas de succès, False sinon
 */
function add_to_panier($user_id, $produit_id, $quantite = 1, $couleur = null, $poids = null, $taille = null, $variante_id = null, $variante_nom = null, $variante_image = null, $surcout_poids = 0, $surcout_taille = 0, $prix_unitaire = null, $vendeur_id = null)
{
    global $db;

    try {
        require_once __DIR__ . '/model_produits.php';
        if ($vendeur_id === null || $vendeur_id === '') {
            $pr = get_produit_by_id((int) $produit_id);
            $vendeur_id = ($pr && !empty($pr['admin_id'])) ? (int) $pr['admin_id'] : 0;
        } else {
            $vendeur_id = (int) $vendeur_id;
        }
        if ($vendeur_id <= 0 && _panier_has_vendeur_column()) {
            return false;
        }

        $has_cols = _panier_has_variante_columns();
        $has_vend = _panier_has_vendeur_column();
        $vid = $variante_id ? (int)$variante_id : 0;
        $match_sql = "user_id = :user_id AND produit_id = :produit_id AND COALESCE(couleur,'') = COALESCE(:couleur,'') AND COALESCE(poids,'') = COALESCE(:poids,'') AND COALESCE(taille,'') = COALESCE(:taille,'')";
        $params = ['user_id' => $user_id, 'produit_id' => $produit_id, 'couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
        if ($has_vend) {
            $match_sql .= ' AND vendeur_id = :vendeur_id';
            $params['vendeur_id'] = $vendeur_id;
        }
        if ($has_cols) {
            $match_sql = "user_id = :user_id AND produit_id = :produit_id AND COALESCE(variante_id, 0) = :vid AND COALESCE(couleur,'') = COALESCE(:couleur,'') AND COALESCE(poids,'') = COALESCE(:poids,'') AND COALESCE(taille,'') = COALESCE(:taille,'')";
            if ($has_vend) {
                $match_sql .= ' AND vendeur_id = :vendeur_id';
            }
            $params = ['user_id' => $user_id, 'produit_id' => $produit_id, 'couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
            $params['vid'] = $vid;
            if ($has_vend) {
                $params['vendeur_id'] = $vendeur_id;
            }
        }

        $stmt = $db->prepare("SELECT id, quantite FROM panier WHERE $match_sql");
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $cols = "quantite = :quantite, couleur = :couleur, poids = :poids, taille = :taille";
        $vals = ['quantite' => $existing ? $existing['quantite'] + $quantite : $quantite, 'couleur' => $couleur, 'poids' => $poids, 'taille' => $taille, 'id' => $existing ? $existing['id'] : null];

        if (_panier_has_variante_columns()) {
            $cols .= ", variante_id = :variante_id, variante_nom = :variante_nom, variante_image = :variante_image, surcout_poids = :surcout_poids, surcout_taille = :surcout_taille, prix_unitaire = :prix_unitaire";
            $vals['variante_id'] = $variante_id;
            $vals['variante_nom'] = $variante_nom;
            $vals['variante_image'] = $variante_image;
            $vals['surcout_poids'] = $surcout_poids;
            $vals['surcout_taille'] = $surcout_taille;
            $vals['prix_unitaire'] = $prix_unitaire;
        }

        if ($existing) {
            $stmt = $db->prepare("UPDATE panier SET $cols WHERE id = :id");
            return $stmt->execute($vals);
        } else {
            $ins_cols = "user_id, produit_id, quantite, couleur, poids, taille";
            $ins_vals = ":user_id, :produit_id, :quantite, :couleur, :poids, :taille";
            $ins_params = ['user_id' => $user_id, 'produit_id' => $produit_id, 'quantite' => $quantite, 'couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
            if (_panier_has_vendeur_column()) {
                $ins_cols = "user_id, vendeur_id, produit_id, quantite, couleur, poids, taille";
                $ins_vals = ":user_id, :vendeur_id, :produit_id, :quantite, :couleur, :poids, :taille";
                $ins_params = ['user_id' => $user_id, 'vendeur_id' => $vendeur_id, 'produit_id' => $produit_id, 'quantite' => $quantite, 'couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
            }
            if (_panier_has_variante_columns()) {
                $ins_cols .= ", variante_id, variante_nom, variante_image, surcout_poids, surcout_taille, prix_unitaire";
                $ins_vals .= ", :variante_id, :variante_nom, :variante_image, :surcout_poids, :surcout_taille, :prix_unitaire";
                $ins_params['variante_id'] = $variante_id;
                $ins_params['variante_nom'] = $variante_nom;
                $ins_params['variante_image'] = $variante_image;
                $ins_params['surcout_poids'] = $surcout_poids;
                $ins_params['surcout_taille'] = $surcout_taille;
                $ins_params['prix_unitaire'] = $prix_unitaire;
            }
            $stmt = $db->prepare("INSERT INTO panier ($ins_cols) VALUES ($ins_vals)");
            return $stmt->execute($ins_params);
        }
    } catch (PDOException $e) {
        // Si les colonnes n'existent pas encore, fallback sans options
        if (strpos($e->getMessage(), 'couleur') !== false || strpos($e->getMessage(), 'poids') !== false || strpos($e->getMessage(), 'taille') !== false) {
            try {
                $stmt = $db->prepare("SELECT id, quantite FROM panier WHERE user_id = :user_id AND produit_id = :produit_id");
                $stmt->execute(['user_id' => $user_id, 'produit_id' => $produit_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $new_quantite = $existing['quantite'] + $quantite;
                    $stmt = $db->prepare("UPDATE panier SET quantite = :quantite WHERE id = :id");
                    return $stmt->execute(['quantite' => $new_quantite, 'id' => $existing['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO panier (user_id, produit_id, quantite) VALUES (:user_id, :produit_id, :quantite)");
                    return $stmt->execute(['user_id' => $user_id, 'produit_id' => $produit_id, 'quantite' => $quantite]);
                }
            } catch (PDOException $e2) {
                return false;
            }
        }
        return false;
    }
}

/**
 * Met à jour la quantité d'un produit dans le panier
 * @param int $panier_id L'ID de l'élément du panier
 * @param int $quantite La nouvelle quantité
 * @return bool True en cas de succès, False sinon
 */
function update_panier_quantite($panier_id, $quantite)
{
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
function delete_from_panier($panier_id)
{
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
 * Stock géré uniquement par produits.stock (table stock_articles supprimée)
 * @param int $user_id L'ID de l'utilisateur
 * @return array Tableau des produits du panier avec leurs détails
 */
/**
 * Regroupe les lignes panier par vendeur_id (affichage marketplace).
 * @return array<int, array{items: array, label: string, slug: string}>
 */
/**
 * ID vendeur effectif pour une ligne panier (panier.vendeur_id ou produits.admin_id).
 */
function panier_item_effective_vendeur_id(array $item)
{
    $vid = isset($item['vendeur_id']) ? (int) $item['vendeur_id'] : 0;
    if ($vid <= 0 && !empty($item['admin_id'])) {
        $vid = (int) $item['admin_id'];
    }
    return $vid;
}

/**
 * Ne garde que les lignes dont le vendeur correspond (vitrine boutique).
 */
function filter_panier_items_by_vendeur(array $panier_items, $vendeur_id)
{
    $vid = (int) $vendeur_id;
    if ($vid <= 0) {
        return $panier_items;
    }
    $out = [];
    foreach ($panier_items as $item) {
        if (panier_item_effective_vendeur_id($item) === $vid) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Somme des quantités (lignes du tableau passé).
 */
function panier_items_count_quantites(array $items)
{
    $n = 0;
    foreach ($items as $item) {
        $n += (int) ($item['quantite'] ?? 0);
    }
    return $n;
}

/**
 * Sous-total FCFA pour un ensemble de lignes panier déjà enrichies (prix promo / panier_prix_unitaire).
 */
function panier_items_sous_total(array $items)
{
    $total = 0.0;
    foreach ($items as $item) {
        $pu = (!empty($item['panier_prix_unitaire']) && (float) $item['panier_prix_unitaire'] > 0)
            ? (float) $item['panier_prix_unitaire']
            : (!empty($item['prix_promotion']) && (float) $item['prix_promotion'] < (float) $item['prix']
                ? (float) $item['prix_promotion']
                : (float) $item['prix']);
        $total += $pu * (int) ($item['quantite'] ?? 0);
    }
    return $total;
}

/**
 * Supprime du panier toutes les lignes rattachées à un vendeur (admin id).
 */
function delete_panier_lines_for_vendeur($user_id, $vendeur_id)
{
    global $db;
    $uid = (int) $user_id;
    $vid = (int) $vendeur_id;
    if ($uid <= 0 || $vid <= 0 || !$db) {
        return false;
    }
    try {
        if (_panier_has_vendeur_column()) {
            $sql = "DELETE pan FROM panier pan
                INNER JOIN produits p ON pan.produit_id = p.id
                WHERE pan.user_id = :uid AND (
                    (pan.vendeur_id IS NOT NULL AND pan.vendeur_id > 0 AND pan.vendeur_id = :vid)
                    OR ((pan.vendeur_id IS NULL OR pan.vendeur_id = 0) AND p.admin_id = :vid2)
                )";
            $stmt = $db->prepare($sql);
            return $stmt->execute(['uid' => $uid, 'vid' => $vid, 'vid2' => $vid]);
        }
        $stmt = $db->prepare(
            "DELETE pan FROM panier pan
            INNER JOIN produits p ON pan.produit_id = p.id
            WHERE pan.user_id = :uid AND p.admin_id = :vid"
        );
        return $stmt->execute(['uid' => $uid, 'vid' => $vid]);
    } catch (PDOException $e) {
        return false;
    }
}

function group_panier_items_by_vendeur(array $panier_items) {
    $groups = [];
    foreach ($panier_items as $item) {
        $vid = isset($item['vendeur_id']) ? (int) $item['vendeur_id'] : 0;
        if ($vid <= 0 && !empty($item['admin_id'])) {
            $vid = (int) $item['admin_id'];
        }
        if (!isset($groups[$vid])) {
            $groups[$vid] = [
                'items' => [],
                'label' => isset($item['vendeur_boutique_nom']) ? (string) $item['vendeur_boutique_nom'] : 'Boutique',
                'slug' => isset($item['vendeur_boutique_slug']) ? (string) $item['vendeur_boutique_slug'] : '',
            ];
        }
        $groups[$vid]['items'][] = $item;
    }
    ksort($groups, SORT_NUMERIC);
    return $groups;
}

function get_panier_by_user($user_id)
{
    global $db;

    try {
        $cols = "p.*, pan.id as panier_id, pan.quantite, pan.date_ajout, pan.couleur as panier_couleur, pan.poids as panier_poids, pan.taille as panier_taille";
        if (_panier_has_variante_columns()) {
            $cols .= ", pan.variante_id as panier_variante_id, pan.variante_nom as panier_variante_nom, pan.variante_image as panier_variante_image, pan.surcout_poids as panier_surcout_poids, pan.surcout_taille as panier_surcout_taille, pan.prix_unitaire as panier_prix_unitaire";
        }
        $join_v = '';
        $sel_v = '';
        if (_panier_has_vendeur_column()) {
            $join_v = ' LEFT JOIN admin vend ON pan.vendeur_id = vend.id ';
            $sel_v = ', pan.vendeur_id, vend.boutique_nom as vendeur_boutique_nom, vend.boutique_slug as vendeur_boutique_slug';
        }
        $stmt = $db->prepare("
            SELECT $cols$sel_v, c.nom as categorie_nom
            FROM panier pan
            INNER JOIN produits p ON pan.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            $join_v
            WHERE pan.user_id = :user_id
            ORDER BY pan.vendeur_id ASC, pan.date_ajout DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$it) {
            if (!isset($it['panier_prix_unitaire'])) $it['panier_prix_unitaire'] = null;
            if (!isset($it['panier_variante_nom'])) $it['panier_variante_nom'] = null;
        }
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
function clear_panier($user_id)
{
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
function get_panier_total($user_id)
{
    global $db;

    try {
        $sql = _panier_has_variante_columns()
            ? "SELECT SUM(CASE WHEN pan.prix_unitaire IS NOT NULL AND pan.prix_unitaire > 0 THEN pan.prix_unitaire * pan.quantite WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion < p.prix THEN p.prix_promotion * pan.quantite ELSE p.prix * pan.quantite END) as total FROM panier pan INNER JOIN produits p ON pan.produit_id = p.id WHERE pan.user_id = :user_id"
            : "SELECT SUM(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion < p.prix THEN p.prix_promotion * pan.quantite ELSE p.prix * pan.quantite END) as total FROM panier pan INNER JOIN produits p ON pan.produit_id = p.id WHERE pan.user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['total'] ? (float) $result['total'] : 0.0;
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
function is_in_panier($user_id, $produit_id, $vendeur_id = null)
{
    global $db;

    try {
        $sql = "SELECT * FROM panier WHERE user_id = :user_id AND produit_id = :produit_id";
        $params = ['user_id' => $user_id, 'produit_id' => $produit_id];
        if (_panier_has_vendeur_column() && $vendeur_id !== null && $vendeur_id !== '') {
            $sql .= " AND vendeur_id = :vendeur_id";
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
function count_panier_items($user_id)
{
    global $db;
    if (!$db) {
        return 0;
    }
    try {
        $stmt = $db->prepare("SELECT SUM(quantite) as total FROM panier WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['total'] ? (int) $result['total'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Somme des quantités du panier pour un vendeur (vitrine boutique).
 */
function count_panier_items_for_vendeur($user_id, $vendeur_id)
{
    global $db;
    $uid = (int) $user_id;
    $vid = (int) $vendeur_id;
    if ($uid <= 0 || $vid <= 0 || !$db) {
        return 0;
    }
    try {
        if (_panier_has_vendeur_column()) {
            $stmt = $db->prepare(
                "SELECT SUM(pan.quantite) as total FROM panier pan
                INNER JOIN produits p ON pan.produit_id = p.id
                WHERE pan.user_id = :uid AND (
                    (pan.vendeur_id IS NOT NULL AND pan.vendeur_id > 0 AND pan.vendeur_id = :vid)
                    OR ((pan.vendeur_id IS NULL OR pan.vendeur_id = 0) AND p.admin_id = :vid2)
                )"
            );
            $stmt->execute(['uid' => $uid, 'vid' => $vid, 'vid2' => $vid]);
        } else {
            $stmt = $db->prepare(
                "SELECT SUM(pan.quantite) as total FROM panier pan
                INNER JOIN produits p ON pan.produit_id = p.id
                WHERE pan.user_id = :uid AND p.admin_id = :vid"
            );
            $stmt->execute(['uid' => $uid, 'vid' => $vid]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['total'] !== null ? (int) $result['total'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

?>