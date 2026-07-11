<?php
/**
 * Modèle pour la gestion des commandes
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_produits.php';
require_once __DIR__ . '/model_commandes_admin.php'; // fournit get_commande_by_id()
require_once __DIR__ . '/model_admin_activite.php'; // colonnes traçabilité admin (commandes)

// Charger config debug si présente (affichage erreur détaillée sur la page)
if (file_exists(__DIR__ . '/../config/config_debug.php')) {
    require_once __DIR__ . '/../config/config_debug.php';
}

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

function _commande_produits_has_nom_produit() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'nom_produit'");
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

function _commandes_has_vendeur_id_column() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'vendeur_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

function _commandes_has_mode_livraison_column() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'mode_livraison'");
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
 * @param int|null $vendeur_id Boutique vendeuse (marketplace). Si null, dérivé du premier article si colonne présente.
 * @param bool $manage_transaction Si false, ne pas begin/commit (transaction gérée par l'appelant)
 * @param string $mode_livraison 'livraison' ou 'retrait'
 * @return array|false Tableau avec 'success' et 'commande_id' ou False en cas d'erreur
 */
function create_commande($user_id, $panier_items, $adresse_livraison, $telephone_livraison, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $choix = [], $vendeur_id = null, $manage_transaction = true, $mode_livraison = 'livraison') {
    global $db;

    if (!function_exists('commande_mode_livraison_normalize')) {
        require_once __DIR__ . '/../includes/commande_mode_helpers.php';
    }
    $mode_livraison = commande_mode_livraison_normalize($mode_livraison);
    
    try {
        if ($manage_transaction) {
            $db->beginTransaction();
        }

        if (_commandes_has_vendeur_id_column()) {
            if ($vendeur_id === null || (int) $vendeur_id <= 0) {
                $first = reset($panier_items);
                $vendeur_id = isset($first['vendeur_id']) ? (int) $first['vendeur_id'] : (int) ($first['admin_id'] ?? 0);
            } else {
                $vendeur_id = (int) $vendeur_id;
            }
            if ($vendeur_id <= 0) {
                throw new PDOException('vendeur_id requis pour la commande');
            }
        } else {
            $vendeur_id = null;
        }

        if ($mode_livraison === 'retrait' && $vendeur_id !== null && (int) $vendeur_id > 0) {
            $adresse_livraison = commande_build_retrait_adresse((int) $vendeur_id);
        }
        
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
        if (_commandes_has_vendeur_id_column()) {
            $params_cmd['vendeur_id'] = $vendeur_id;
        }
        
        if (_commandes_has_zone_columns()) {
            $params_cmd['zone_livraison_id'] = $zone_livraison_id ?: null;
            $params_cmd['frais_livraison'] = $frais_livraison;
            if (_commandes_has_vendeur_id_column()) {
                $stmt = $db->prepare("
                INSERT INTO commandes (
                    user_id, vendeur_id, numero_commande, montant_total, adresse_livraison, 
                    zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes
                ) VALUES (
                    :user_id, :vendeur_id, :numero_commande, :montant_total, :adresse_livraison,
                    :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes
                )
            ");
            } else {
                $stmt = $db->prepare("
                INSERT INTO commandes (
                    user_id, numero_commande, montant_total, adresse_livraison, 
                    zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes
                ) VALUES (
                    :user_id, :numero_commande, :montant_total, :adresse_livraison,
                    :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes
                )
            ");
            }
        } else {
            if (_commandes_has_vendeur_id_column()) {
                $stmt = $db->prepare("
                INSERT INTO commandes (
                    user_id, vendeur_id, numero_commande, montant_total, adresse_livraison, 
                    telephone_livraison, statut, date_commande, notes
                ) VALUES (
                    :user_id, :vendeur_id, :numero_commande, :montant_total, :adresse_livraison,
                    :telephone_livraison, 'en_attente', NOW(), :notes
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
        }
        $stmt->execute($params_cmd);
        
        $commande_id = $db->lastInsertId();

        if (_commandes_has_mode_livraison_column() && $commande_id) {
            $stmt_mode = $db->prepare('UPDATE commandes SET mode_livraison = :mode WHERE id = :id');
            $stmt_mode->execute([
                'mode' => $mode_livraison,
                'id' => (int) $commande_id,
            ]);
        }
        
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

        // Le stock est décrémenté uniquement lorsque le statut de la commande passe à 'paye' (via update_commande_statut)

        // Valider la transaction
        if ($manage_transaction) {
            $db->commit();
        }
        
        return [
            'success' => true,
            'commande_id' => $commande_id,
            'numero_commande' => $numero_commande
        ];
        
    } catch (PDOException $e) {
        if ($manage_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[create_commande] ' . $e->getMessage());
        return false;
    }
}

/**
 * Crée plusieurs commandes (une par vendeur) à partir du panier marketplace.
 * Les frais de livraison globaux sont appliqués uniquement à la première commande du lot.
 *
 * @return array|false ['success'=>true,'commandes'=>[...],'numeros_commandes'=>[...]] ou false
 */
function create_marketplace_commandes_from_panier($user_id, $panier_items, $adresse_livraison, $telephone_livraison, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $choix = [], $mode_livraison = 'livraison') {
    global $db;

    if (!_commandes_has_vendeur_id_column()) {
        return create_commande($user_id, $panier_items, $adresse_livraison, $telephone_livraison, $notes, $zone_livraison_id, $frais_livraison, $choix, null, true, $mode_livraison);
    }

    $groups = [];
    foreach ($panier_items as $item) {
        $vid = isset($item['vendeur_id']) ? (int) $item['vendeur_id'] : 0;
        if ($vid <= 0 && !empty($item['admin_id'])) {
            $vid = (int) $item['admin_id'];
        }
        if ($vid <= 0) {
            error_log('[create_marketplace_commandes_from_panier] Ligne panier sans vendeur_id');
            return false;
        }
        if (!isset($groups[$vid])) {
            $groups[$vid] = [];
        }
        $groups[$vid][] = $item;
    }
    ksort($groups, SORT_NUMERIC);

    $created = [];
    $numeros = [];

    try {
        $db->beginTransaction();
        $i = 0;
        foreach ($groups as $vid => $subset) {
            $frais_ligne = ($i === 0) ? (float) $frais_livraison : 0.0;
            $i++;
            $res = create_commande($user_id, $subset, $adresse_livraison, $telephone_livraison, $notes, $zone_livraison_id, $frais_ligne, $choix, $vid, false, $mode_livraison);
            if ($res === false || empty($res['success'])) {
                $db->rollBack();
                return false;
            }
            $created[] = $res;
            $numeros[] = $res['numero_commande'];
        }
        $db->commit();

        return [
            'success' => true,
            'commandes' => $created,
            'numeros_commandes' => $numeros,
            'commande_id' => $created[0]['commande_id'] ?? null,
            'numero_commande' => $numeros[0] ?? '',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[create_marketplace_commandes_from_panier] ' . $e->getMessage());
        return false;
    }
}

/**
 * Crée une commande manuelle (admin, sans utilisateur connecté)
 * @param array $items [['produit_id'=>int, 'quantite'=>int, 'prix_unitaire'=>float, 'prix_promotion'=>float|null, 'nom_produit'=>string|null], ...]
 * @param string $client_nom Nom du client
 * @param string $client_prenom Prénom du client
 * @param string $client_telephone Téléphone du client
 * @param string $adresse_livraison Adresse de livraison
 * @param string|null $client_email Email (optionnel, si vide pas d'envoi email)
 * @param string|null $notes Notes optionnelles
 * @param int|null $zone_livraison_id ID zone de livraison (optionnel)
 * @param float $frais_livraison Frais de livraison en FCFA (défaut 0)
 * @param int|null $admin_createur_id Compte admin ayant créé la commande manuelle
 * @return array|false ['success'=>true, 'commande_id'=>int, 'numero_commande'=>string] ou false
 */
function create_commande_manuelle($items, $client_nom, $client_prenom, $client_telephone, $adresse_livraison, $client_email = null, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $admin_createur_id = null) {
    global $db;

    if (empty($items) || empty(trim($client_nom)) || empty(trim($client_prenom)) || empty(trim($client_telephone)) || empty(trim($adresse_livraison))) {
        return ['success' => false, 'error' => 'Données client ou produits manquants.'];
    }

    try {
        $db->beginTransaction();

        $montant_total = 0;
        $panier_items = [];
        foreach ($items as $it) {
            $produit_id = (int) ($it['produit_id'] ?? 0);
            $quantite = max(1, (int) ($it['quantite'] ?? 1));
            $prix_promo = isset($it['prix_promotion']) && $it['prix_promotion'] !== '' && (float) $it['prix_promotion'] > 0 ? (float) $it['prix_promotion'] : null;
            $prix_unitaire = $prix_promo !== null ? $prix_promo : (float) ($it['prix_unitaire'] ?? 0);
            if ($produit_id <= 0 || $prix_unitaire <= 0) continue;

            $produit = get_produit_by_id($produit_id);
            if (!$produit) continue;

            // Utiliser uniquement la colonne stock de la table produits (pas stock_articles)
            $stmt_stock = $db->prepare("SELECT stock FROM produits WHERE id = :id");
            $stmt_stock->execute(['id' => $produit_id]);
            $row_stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
            $stock_dispo = $row_stock ? (int) $row_stock['stock'] : 0;

            if ($stock_dispo < $quantite) {
                $db->rollBack();
                error_log('[create_commande_manuelle] Stock insuffisant: produit_id=' . $produit_id . ', stock=' . $stock_dispo . ', quantite_demandee=' . $quantite);
                return ['success' => false, 'error' => 'Stock insuffisant pour "' . ($produit['nom'] ?? 'produit #' . $produit_id) . '" (disponible: ' . $stock_dispo . ', demandé: ' . $quantite . ').'];
            }

            $nom_produit = isset($it['nom_produit']) && trim($it['nom_produit']) !== '' ? trim($it['nom_produit']) : null;
            $panier_items[] = [
                'id' => $produit_id,
                'quantite' => $quantite,
                'stock_article_id' => null,
                'prix' => $produit['prix'],
                'prix_promotion' => $prix_promo ?? ($produit['prix_promotion'] ?? null),
                'panier_prix_unitaire' => $prix_unitaire,
                'nom_produit' => $nom_produit
            ];
            $montant_total += $prix_unitaire * $quantite;
        }

        $frais_livraison = (float) ($frais_livraison ?? 0);
        $montant_total += $frais_livraison;

        if (empty($panier_items)) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Aucun produit valide à enregistrer. Vérifiez les IDs et les prix.'];
        }

        $numero_commande = generate_numero_commande();
        $stmt = $db->prepare("SELECT id FROM commandes WHERE numero_commande = :numero");
        $stmt->execute(['numero' => $numero_commande]);
        if ($stmt->fetch()) {
            $numero_commande = generate_numero_commande() . '-' . rand(100, 999);
        }

        $has_zone = _commandes_has_zone_columns();
        $has_admin_trace = admin_activite_column_exists('commandes', 'admin_createur_id')
            && admin_activite_column_exists('commandes', 'admin_dernier_traitement_id');
        $aid = $admin_createur_id !== null && (int) $admin_createur_id > 0 ? (int) $admin_createur_id : null;

        $params_exec = [
            'numero_commande' => $numero_commande,
            'montant_total' => $montant_total,
            'adresse_livraison' => trim($adresse_livraison),
            'telephone_livraison' => trim($client_telephone),
            'notes' => $notes ? trim($notes) : null,
            'client_nom' => trim($client_nom),
            'client_prenom' => trim($client_prenom),
            'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
            'client_telephone' => trim($client_telephone)
        ];
        if ($has_admin_trace) {
            $params_exec['admin_createur_id'] = $aid;
            $params_exec['admin_dernier_traitement_id'] = $aid;
        }

        if ($has_zone) {
            $params_exec['zone_livraison_id'] = $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null;
            $params_exec['frais_livraison'] = $frais_livraison;
            if ($has_admin_trace) {
                $stmt = $db->prepare("
                    INSERT INTO commandes (
                        user_id, numero_commande, montant_total, adresse_livraison,
                        zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes,
                        client_nom, client_prenom, client_email, client_telephone,
                        admin_createur_id, admin_dernier_traitement_id
                    ) VALUES (
                        NULL, :numero_commande, :montant_total, :adresse_livraison,
                        :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes,
                        :client_nom, :client_prenom, :client_email, :client_telephone,
                        :admin_createur_id, :admin_dernier_traitement_id
                    )
                ");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO commandes (
                        user_id, numero_commande, montant_total, adresse_livraison,
                        zone_livraison_id, frais_livraison, telephone_livraison, statut, date_commande, notes,
                        client_nom, client_prenom, client_email, client_telephone
                    ) VALUES (
                        NULL, :numero_commande, :montant_total, :adresse_livraison,
                        :zone_livraison_id, :frais_livraison, :telephone_livraison, 'en_attente', NOW(), :notes,
                        :client_nom, :client_prenom, :client_email, :client_telephone
                    )
                ");
            }
        } else {
            if ($has_admin_trace) {
                $stmt = $db->prepare("
                    INSERT INTO commandes (
                        user_id, numero_commande, montant_total, adresse_livraison,
                        telephone_livraison, statut, date_commande, notes,
                        client_nom, client_prenom, client_email, client_telephone,
                        admin_createur_id, admin_dernier_traitement_id
                    ) VALUES (
                        NULL, :numero_commande, :montant_total, :adresse_livraison,
                        :telephone_livraison, 'en_attente', NOW(), :notes,
                        :client_nom, :client_prenom, :client_email, :client_telephone,
                        :admin_createur_id, :admin_dernier_traitement_id
                    )
                ");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO commandes (
                        user_id, numero_commande, montant_total, adresse_livraison,
                        telephone_livraison, statut, date_commande, notes,
                        client_nom, client_prenom, client_email, client_telephone
                    ) VALUES (
                        NULL, :numero_commande, :montant_total, :adresse_livraison,
                        :telephone_livraison, 'en_attente', NOW(), :notes,
                        :client_nom, :client_prenom, :client_email, :client_telephone
                    )
                ");
            }
        }

        $stmt->execute($params_exec);

        $commande_id = $db->lastInsertId();

        $has_options = _commande_produits_has_option_columns();
        $has_variantes = _commande_produits_has_variante_columns();
        $has_nom_produit = _commande_produits_has_nom_produit();
        $cols = 'commande_id, produit_id, quantite, prix_unitaire, prix_total';
        $vals = ':commande_id, :produit_id, :quantite, :prix_unitaire, :prix_total';
        if ($has_nom_produit) { $cols .= ', nom_produit'; $vals .= ', :nom_produit'; }
        if ($has_options) { $cols .= ', couleur, poids, taille'; $vals .= ', NULL, NULL, NULL'; }
        if ($has_variantes) { $cols .= ', variante_id, variante_nom, surcout_poids, surcout_taille'; $vals .= ', NULL, NULL, 0, 0'; }

        foreach ($panier_items as $item) {
            $prix_unitaire = (float) $item['panier_prix_unitaire'];
            $prix_total = $prix_unitaire * $item['quantite'];
            $params = [
                'commande_id' => $commande_id,
                'produit_id' => $item['id'],
                'quantite' => $item['quantite'],
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ];
            if ($has_nom_produit) {
                $params['nom_produit'] = $item['nom_produit'] ?? null;
            }
            $stmt = $db->prepare("INSERT INTO commande_produits ($cols) VALUES ($vals)");
            $stmt->execute($params);
        }

        // Le stock est décrémenté uniquement lorsque le statut de la commande passe à 'paye' (via update_commande_statut)

        $db->commit();
        return ['success' => true, 'commande_id' => $commande_id, 'numero_commande' => $numero_commande];
    } catch (PDOException $e) {
        $db->rollBack();
        $msg = $e->getMessage();
        error_log('[create_commande_manuelle] ' . $msg);
        // Écrire dans un fichier log du projet (accessible via cPanel > Gestionnaire de fichiers)
        $log_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
        $log_file = $log_dir . '/commande_manuelle_errors.log';
        @file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND | LOCK_EX);
        // Si DEBUG_SHOW_ERREUR_COMMANDE activé : afficher l'erreur directement sur la page
        $msg_affichage = (defined('DEBUG_SHOW_ERREUR_COMMANDE') && DEBUG_SHOW_ERREUR_COMMANDE)
            ? 'Erreur base de données : ' . $msg
            : 'Erreur base de données. Vérifiez le fichier logs/commande_manuelle_errors.log ou les logs cPanel.';
        return ['success' => false, 'error' => $msg_affichage];
    }
}

/**
 * Récupère toutes les commandes d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param int|null $boutique_admin_id Si défini, uniquement les commandes avec au moins une ligne de produits
 *                                    dont produits.admin_id = cet ID. La colonne montant_lignes_boutique est ajoutée.
 * @return array Tableau des commandes
 */
function get_commandes_by_user($user_id, $boutique_admin_id = null) {
    global $db;

    $boutique_admin_id = $boutique_admin_id !== null ? (int) $boutique_admin_id : null;
    if ($boutique_admin_id !== null && $boutique_admin_id <= 0) {
        $boutique_admin_id = null;
    }

    try {
        if ($boutique_admin_id !== null) {
            require_once __DIR__ . '/model_produits.php';
            if (!produits_has_column('admin_id')) {
                return [];
            }
            $stmt = $db->prepare("
                SELECT c.*,
                    (SELECT COALESCE(SUM(cp.prix_total), 0)
                     FROM commande_produits cp
                     INNER JOIN produits p ON p.id = cp.produit_id AND p.admin_id = :vid_ca
                     WHERE cp.commande_id = c.id) AS montant_lignes_boutique
                FROM commandes c
                WHERE c.user_id = :user_id
                AND EXISTS (
                    SELECT 1 FROM commande_produits cp2
                    INNER JOIN produits p2 ON p2.id = cp2.produit_id AND p2.admin_id = :vid_ex
                    WHERE cp2.commande_id = c.id
                )
                ORDER BY c.date_commande DESC
            ");
            $stmt->execute([
                'user_id' => (int) $user_id,
                'vid_ca' => $boutique_admin_id,
                'vid_ex' => $boutique_admin_id,
            ]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM commandes
                WHERE user_id = :user_id
                ORDER BY date_commande DESC
            ");
            $stmt->execute(['user_id' => (int) $user_id]);
        }
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $commandes ? $commandes : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère la quantité totale vendue d'un produit (commandes non annulées)
 * @param int $produit_id ID du produit
 * @return int Quantité vendue
 */
function get_quantite_vendue_produit($produit_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(cp.quantite), 0) as total
            FROM commande_produits cp
            INNER JOIN commandes c ON cp.commande_id = c.id
            WHERE cp.produit_id = :produit_id AND c.statut != 'annulee'
        ");
        $stmt->execute(['produit_id' => (int) $produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['total'] : 0;
    } catch (PDOException $e) {
        return 0;
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
        $has_var = _commande_produits_has_variante_columns();
        $join_variante = $has_var
            ? "LEFT JOIN produits_variantes pv ON cp.variante_id = pv.id AND pv.produit_id = p.id"
            : "";
        $img = $has_var
            ? "COALESCE(pv.image, p.image_principale) as image_afficher"
            : "p.image_principale as image_afficher";
        $var_nom = $has_var
            ? ", COALESCE(NULLIF(TRIM(cp.variante_nom), ''), pv.nom) as variante_nom"
            : "";
        $nom_col = _commande_produits_has_nom_produit() ? "COALESCE(NULLIF(TRIM(cp.nom_produit), ''), p.nom) as nom" : "p.nom";
        $stmt = $db->prepare("
            SELECT cp.*, p.id as produit_id, $nom_col, p.image_principale, p.poids, p.unite,
                   c.nom as categorie_nom, c.id as categorie_id,
                   cmd.numero_commande, cmd.date_commande, cmd.statut as statut_commande,
                   $img $var_nom
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
 * Première image web (/upload/…) pour vignette liste commandes (vide si aucune image).
 */
function commande_resume_thumb_web_path($commande_id)
{
    $cid = (int) $commande_id;
    if ($cid <= 0) {
        return '';
    }
    foreach (get_commande_produits($cid) as $ln) {
        $img = '';
        if (!empty($ln['image_afficher'])) {
            $img = trim((string) $ln['image_afficher']);
        }
        if ($img === '' && !empty($ln['image_principale'])) {
            $img = trim((string) $ln['image_principale']);
        }
        if ($img !== '' && strpos($img, '..') === false && strpbrk($img, "\0<>") === false) {
            return '/upload/' . str_replace('\\', '/', $img);
        }
    }
    return '';
}

/**
 * Regroupement pour filtres « Mes commandes » (client).
 *
 * @return 'en_cours'|'livrees'|'annulees'
 */
function commande_bucket_public_ui($statut)
{
    $s = (string) $statut;
    if ($s === 'annulee') {
        return 'annulees';
    }
    if ($s === 'livree' || $s === 'paye') {
        return 'livrees';
    }
    return 'en_cours';
}

/**
 * Date affichée type « 15 mai 2026 » (timezone serveur).
 */
function format_date_fr_long($datetime_str)
{
    if ($datetime_str === null || trim((string) $datetime_str) === '') {
        return '';
    }
    $ts = strtotime((string) $datetime_str);
    if ($ts === false) {
        return '';
    }
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $m = (int) date('n', $ts);
    return (int) date('j', $ts) . ' ' . ($months[$m - 1] ?? '') . ' ' . date('Y', $ts);
}

/**
 * Libellés et mise en forme carte commande — espace client.
 *
 * @return array{status_label:string,status_mod:string,hint:string,cta_label:string,cta_icon:string,cta_track:bool,detail_href:string}
 */
function commande_present_ui_client(array $commande)
{
    $st = (string) ($commande['statut'] ?? '');
    $cid = (int) ($commande['id'] ?? 0);
    $detail_href = 'commande-categorie.php?commande_id=' . $cid;

    if ($st === 'annulee') {
        return [
            'status_label' => 'Annulée',
            'status_mod' => 'ocl-status--muted',
            'hint' => '',
            'cta_label' => 'Voir les détails',
            'cta_icon' => 'fa-eye',
            'cta_track' => false,
            'detail_href' => $detail_href,
        ];
    }
    if ($st === 'livree' || $st === 'paye') {
        $when = !empty($commande['date_livraison'])
            ? format_date_fr_long($commande['date_livraison'])
            : format_date_fr_long($commande['date_commande'] ?? '');
        return [
            'status_label' => 'Livré',
            'status_mod' => 'ocl-status--green',
            'hint' => $when !== '' ? ('Livré le ' . $when) : '',
            'cta_label' => 'Voir les détails',
            'cta_icon' => 'fa-eye',
            'cta_track' => false,
            'detail_href' => $detail_href,
        ];
    }
    if ($st === 'livraison_en_cours') {
        $hint = !empty($commande['date_livraison'])
            ? ('Livraison prévue : ' . format_date_fr_long($commande['date_livraison']))
            : 'Votre commande est en cours de livraison.';
        return [
            'status_label' => 'En cours de livraison',
            'status_mod' => 'ocl-status--blue',
            'hint' => $hint,
            'cta_label' => 'Suivre ma commande',
            'cta_icon' => 'fa-truck',
            'cta_track' => true,
            'detail_href' => $detail_href,
        ];
    }

    return [
        'status_label' => 'En préparation',
        'status_mod' => 'ocl-status--blue',
        'hint' => 'Nous préparons votre commande.',
        'cta_label' => 'Suivre ma commande',
        'cta_icon' => 'fa-truck',
        'cta_track' => true,
        'detail_href' => $detail_href,
    ];
}

/**
 * Libellés carte commande — admin / vendeur (lien vers details.php).
 *
 * @return array{status_label:string,status_mod:string,hint:string,cta_label:string,cta_icon:string,detail_href:string}
 */
function commande_present_ui_vendor(array $commande)
{
    $st = (string) ($commande['statut'] ?? '');
    $cid = (int) ($commande['id'] ?? 0);
    $detail_href = 'details.php?id=' . $cid;
    $client = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''));
    if ($client === '') {
        $client = 'Client';
    }

    if ($st === 'annulee') {
        return [
            'status_label' => 'Annulée',
            'status_mod' => 'ocl-status--muted',
            'hint' => $client,
            'cta_label' => 'Voir les détails',
            'cta_icon' => 'fa-eye',
            'detail_href' => $detail_href,
        ];
    }
    if ($st === 'livree' || $st === 'paye') {
        $when = !empty($commande['date_livraison'])
            ? format_date_fr_long($commande['date_livraison'])
            : format_date_fr_long($commande['date_commande'] ?? '');
        return [
            'status_label' => $st === 'paye' ? 'Payée' : 'Livré',
            'status_mod' => 'ocl-status--green',
            'hint' => ($when !== '' ? ('Livré le ' . $when . ' · ') : '') . $client,
            'cta_label' => 'Voir les détails',
            'cta_icon' => 'fa-eye',
            'detail_href' => $detail_href,
        ];
    }
    if ($st === 'livraison_en_cours') {
        $hint = !empty($commande['date_livraison'])
            ? ('Livraison prévue : ' . format_date_fr_long($commande['date_livraison']))
            : ('Client : ' . $client);
        return [
            'status_label' => 'En cours de livraison',
            'status_mod' => 'ocl-status--blue',
            'hint' => $hint,
            'cta_label' => 'Voir la commande',
            'cta_icon' => 'fa-truck',
            'detail_href' => $detail_href,
        ];
    }

    return [
        'status_label' => ucfirst(str_replace('_', ' ', $st)),
        'status_mod' => 'ocl-status--blue',
        'hint' => 'Client : ' . $client,
        'cta_label' => 'Voir les détails',
        'cta_icon' => 'fa-eye',
        'detail_href' => $detail_href,
    ];
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
        $produit_nom_col = _commande_produits_has_nom_produit() ? "COALESCE(NULLIF(TRIM(cp.nom_produit), ''), p.nom) as produit_nom" : "p.nom as produit_nom";
        $cols = "c.id as categorie_id, c.nom as categorie_nom, cmd.id as commande_id, cmd.numero_commande, cmd.date_commande, cmd.statut as statut_commande, cmd.montant_total, cp.produit_id, $produit_nom_col, $img, p.poids, p.unite, cp.quantite, cp.prix_unitaire, cp.prix_total";
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
 * Nombre de commandes en cours pour un client (hors livrées, payées et annulées).
 *
 * @param int $user_id
 * @return int
 */
function count_commandes_actives_by_user($user_id) {
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM commandes
            WHERE user_id = :user_id
              AND statut NOT IN ('livree', 'paye', 'annulee')
        ");
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
