<?php
/**
 * Modèle pour la gestion des commandes (Admin)
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';

/**
 * Valeurs ENUM réelles de commandes.statut (cache).
 *
 * @return array<int, string>
 */
function commande_statut_enum_values() {
    static $values = null;
    if ($values !== null) {
        return $values;
    }
    global $db;
    $values = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes LIKE 'statut'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        $type = (string) ($row['Type'] ?? '');
        if (preg_match_all("/'([^']+)'/", $type, $m)) {
            $values = $m[1];
        }
    } catch (PDOException $e) {
        $values = [];
    }
    if (empty($values)) {
        $values = [
            'en_attente', 'confirmee', 'prise_en_charge', 'en_preparation',
            'livraison_en_cours', 'expediee', 'livree', 'paye', 'annulee',
        ];
    }
    return $values;
}

/**
 * Choisit une valeur ENUM compatible en base pour un statut logique.
 */
function commande_statut_resolve_for_db($statut_logique) {
    $statut_logique = (string) $statut_logique;
    $enum = commande_statut_enum_values();
    $candidates_map = [
        'livraison_en_cours' => ['livraison_en_cours', 'expediee', 'en_cours_expedition'],
        'expediee' => ['expediee', 'livraison_en_cours', 'en_cours_expedition'],
        'prise_en_charge' => ['prise_en_charge', 'en_preparation', 'confirmee'],
        'en_preparation' => ['en_preparation', 'prise_en_charge'],
        'livree' => ['livree', 'paye'],
        'paye' => ['paye', 'livree'],
    ];
    $candidates = $candidates_map[$statut_logique] ?? [$statut_logique];
    foreach ($candidates as $c) {
        if (in_array($c, $enum, true)) {
            return $c;
        }
    }
    if (in_array($statut_logique, $enum, true)) {
        return $statut_logique;
    }
    return null;
}

/**
 * Dernière erreur SQL lors d'un update_commande_statut (diagnostic admin).
 */
function commande_statut_update_last_error() {
    return (string) ($GLOBALS['_commande_statut_update_error'] ?? '');
}

function commande_statut_update_set_last_error($message) {
    $GLOBALS['_commande_statut_update_error'] = (string) $message;
}

/**
 * Normalise un statut lu en BDD pour l’interface admin.
 */
function commande_statut_ui_normalize($statut) {
    $s = (string) $statut;
    $map = [
        'en_cours_expedition' => 'livraison_en_cours',
        'expediee' => 'livraison_en_cours',
    ];
    return $map[$s] ?? $s;
}

/**
 * ID admin sûr pour admin_dernier_traitement_id (FK vers admin.id).
 */
function commande_admin_traitant_id_safe($admin_traitant_id) {
    if (!admin_activite_column_exists('commandes', 'admin_dernier_traitement_id')) {
        return null;
    }
    $admin_traitant_id = (int) $admin_traitant_id;
    if ($admin_traitant_id <= 0) {
        return null;
    }
    require_once __DIR__ . '/model_admin.php';
    $adm = get_admin_by_id($admin_traitant_id);
    return $adm ? $admin_traitant_id : null;
}

function _admin_cp_has_option_columns() {
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

function _admin_cp_has_nom_produit() {
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

function _admin_cp_has_variante_columns() {
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
 * Récupère toutes les commandes
 * @param string $statut Filtrer par statut (optionnel)
 * @return array|false Tableau des commandes ou False en cas d'erreur
 */
/**
 * Commandes d’un client (acheteur) — toutes dates, plus récentes en premier.
 *
 * @param int $user_id ID table users
 * @return array<int, array<string, mixed>>
 */
function get_commandes_by_user_id($user_id) {
    global $db;
    $uid = (int) $user_id;
    if ($uid <= 0) {
        return [];
    }
    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.user_id = :uid
            ORDER BY c.date_commande DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['uid' => $uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_all_commandes($statut = null, $vendeur_id = null) {
    global $db;

    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) as user_telephone
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($statut) {
            $sql .= " AND c.statut = :statut";
            $params['statut'] = $statut;
        }
        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= " AND c.vendeur_id = :vendeur_id";
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $sql .= " ORDER BY c.date_commande DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $commandes ? $commandes : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une commande par son ID
 * @param int $commande_id L'ID de la commande
 * @param int|null $user_id L'ID de l'utilisateur (optionnel, pour vérifier l'appartenance côté client)
 * @return array|false Les données de la commande ou False si non trouvé
 */
function get_commande_by_id($commande_id, $user_id = null) {
    global $db;

    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) as user_telephone
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
        ";
        $params = ['id' => $commande_id];
        if ($user_id !== null) {
            $sql .= " AND c.user_id = :user_id";
            $params['user_id'] = (int) $user_id;
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
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_produits_by_commande($commande_id) {
    global $db;
    
    try {
        $has_opts = _admin_cp_has_option_columns();
        $has_var = _admin_cp_has_variante_columns();
        $has_nom_snap = _admin_cp_has_nom_produit();

        if ($has_nom_snap) {
            $produit_nom_col = "COALESCE(NULLIF(TRIM(cp.nom_produit), ''), p.nom, CONCAT('Produit #', cp.produit_id)) as produit_nom";
        } else {
            $produit_nom_col = "COALESCE(p.nom, CONCAT('Produit #', cp.produit_id)) as produit_nom";
        }
        $cols = "cp.*, $produit_nom_col, p.image_principale, cat.nom as categorie_nom";
        if ($has_opts) {
            $cols .= ", cp.couleur, cp.poids, cp.taille";
        }
        if ($has_var) {
            $cols .= ", cp.variante_id, COALESCE(NULLIF(TRIM(cp.variante_nom), ''), pv.nom) as variante_nom, cp.surcout_poids, cp.surcout_taille";
            $cols .= ", COALESCE(pv.image, p.image_principale) as image_afficher";
            $join_pv = "LEFT JOIN produits_variantes pv ON cp.variante_id = pv.id AND pv.produit_id = cp.produit_id";
        } else {
            $cols .= ", p.image_principale as image_afficher";
            $join_pv = "";
        }

        $sql = "
            SELECT $cols
            FROM commande_produits cp
            LEFT JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories cat ON p.categorie_id = cat.id
            $join_pv
            WHERE cp.commande_id = :commande_id
            ORDER BY cp.id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['commande_id' => $commande_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'une commande
 * Lorsque le statut passe à 'paye', le stock est décrémenté et l'historique des mouvements est enregistré
 * @param int $commande_id L'ID de la commande
 * @param string $statut Le nouveau statut
 * @param int|null $admin_traitant_id Admin ayant effectué le changement de statut (traçabilité)
 * @return bool True en cas de succès, False sinon
 */
function update_commande_statut($commande_id, $statut, $admin_traitant_id = null) {
    global $db;

    commande_statut_update_set_last_error('');

    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        commande_statut_update_set_last_error('Commande introuvable.');
        return false;
    }

    $statut = commande_statut_resolve_for_db($statut);
    if ($statut === null || $statut === '') {
        commande_statut_update_set_last_error(
            'Statut non supporté par la base (ENUM commandes.statut). Exécutez migrations/run_migrate_commande_statuts_enum.php sur le serveur.'
        );
        return false;
    }

    $ancien_statut = $commande['statut'] ?? '';
    $numero_commande = $commande['numero_commande'] ?? '';

    $set_traitement = '';
    $params_trait = [
        'id' => $commande_id,
        'statut_new' => $statut,
        'statut_liv' => $statut,
    ];
    $traitant_safe = commande_admin_traitant_id_safe($admin_traitant_id);
    if ($traitant_safe !== null) {
        $set_traitement = ', admin_dernier_traitement_id = :traitant';
        $params_trait['traitant'] = $traitant_safe;
    }

    if ($statut === 'paye' && $ancien_statut !== 'paye') {
        require_once __DIR__ . '/model_produits.php';
        require_once __DIR__ . '/model_mouvements_stock.php';
        require_once __DIR__ . '/model_commandes.php';

        $produits_commande = get_commande_produits($commande_id);
        if (empty($produits_commande)) {
            return false;
        }

        try {
            $db->beginTransaction();

            foreach ($produits_commande as $item) {
                $produit_id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
                $quantite = (int) ($item['quantite'] ?? 0);
                if ($produit_id <= 0 || $quantite <= 0) continue;

                $produit = get_produit_by_id($produit_id);
                if (!$produit) continue;

                $quantite_avant = (int) ($produit['stock'] ?? 0);
                decrement_produit_stock($produit_id, $quantite);
                $quantite_apres = max(0, $quantite_avant - $quantite);

                create_stock_mouvement([
                    'type' => 'sortie',
                    'stock_article_id' => null,
                    'produit_id' => $produit_id,
                    'quantite' => $quantite,
                    'quantite_avant' => $quantite_avant,
                    'quantite_apres' => $quantite_apres,
                    'reference_type' => 'commande',
                    'reference_id' => $commande_id,
                    'reference_numero' => $numero_commande,
                    'notes' => 'Vente commande ' . $numero_commande . ' (statut payé)'
                ]);
            }

            $stmt = $db->prepare("
                UPDATE commandes
                SET statut = :statut_new,
                    date_livraison = CASE WHEN :statut_liv IN ('livree', 'paye') THEN NOW() ELSE date_livraison END
                    $set_traitement
                WHERE id = :id
            ");
            $stmt->execute($params_trait);

            $db->commit();
            return true;
        } catch (PDOException $e) {
            $db->rollBack();
            commande_statut_update_set_last_error($e->getMessage());
            error_log('[update_commande_statut paye] ' . $e->getMessage());
            return false;
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE commandes
            SET statut = :statut_new,
                date_livraison = CASE WHEN :statut_liv IN ('livree', 'paye') THEN NOW() ELSE date_livraison END
                $set_traitement
            WHERE id = :id
        ");
        $ok = $stmt->execute($params_trait);
        if (!$ok) {
            commande_statut_update_set_last_error('Échec de la requête UPDATE.');
        }
        return $ok;
    } catch (PDOException $e) {
        commande_statut_update_set_last_error($e->getMessage());
        error_log('[update_commande_statut] commande #' . (int) $commande_id
            . ' -> ' . $statut . ' : ' . $e->getMessage());
        return false;
    }
}

/**
 * Compte les commandes par statut
 * @param string $statut Le statut à compter
 * @return int Le nombre de commandes
 */
function count_commandes_by_statut($statut = null, $vendeur_id = null) {
    global $db;
    
    try {
        $sql = 'SELECT COUNT(*) FROM commandes WHERE 1=1';
        $params = [];
        if ($statut) {
            $sql .= ' AND statut = :statut';
            $params['statut'] = $statut;
        }
        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= ' AND vendeur_id = :vendeur_id';
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Nombre de commandes actives pour une boutique (non livrées, non payées, non annulées).
 * Utilisé pour le badge « en cours de traitement » dans la navigation vendeur.
 *
 * @param int $vendeur_id ID admin (boutique)
 * @return int
 */
function count_commandes_en_traitement_vendeur($vendeur_id) {
    global $db;
    $vid = (int) $vendeur_id;
    if ($vid <= 0) {
        return 0;
    }
    if (!function_exists('_commandes_has_vendeur_id_column')) {
        require_once __DIR__ . '/model_commandes.php';
    }
    if (!_commandes_has_vendeur_id_column()) {
        return 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM commandes
            WHERE vendeur_id = :vid
            AND statut NOT IN ('livree', 'paye', 'annulee')
        ");
        $stmt->execute(['vid' => $vid]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Retourne le montant total des commandes (comptabilité)
 * @param string|null $statut Filtrer par statut (optionnel). Si null, toutes les commandes.
 * @return float Montant total en FCFA
 */
function get_montant_total_commandes($statut = null, $vendeur_id = null) {
    global $db;
    
    try {
        $sql = 'SELECT COALESCE(SUM(montant_total), 0) FROM commandes WHERE 1=1';
        $params = [];
        if ($statut) {
            $sql .= ' AND statut = :statut';
            $params['statut'] = $statut;
        }
        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= ' AND vendeur_id = :vendeur_id';
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Récupère les commandes par période (pour historique ventes / comptabilité)
 * @param string $periode 'jour'|'plage'|'mois'|'annee'
 * @param int|null $annee Année (optionnel, défaut: année courante)
 * @param int|null $mois Mois 1-12 (optionnel, pour jour / mois)
 * @param string|null $date_debut Date début Y-m-d (pour plage)
 * @param string|null $date_fin Date fin Y-m-d (pour plage)
 * @param int|null $jour Jour du mois 1-31 (optionnel, pour jour)
 * @param bool $filtrer_vendues_uniquement Si true : uniquement statuts livrée et payée (ventes finalisées)
 * @param int|null $vendeur_id Filtre boutique (marketplace)
 * @return array Tableau des commandes
 */
function get_commandes_by_periode($periode, $annee = null, $mois = null, $date_debut = null, $date_fin = null, $jour = null, $filtrer_vendues_uniquement = false, $vendeur_id = null) {
    global $db;
    $annee = $annee ?? (int) date('Y');
    $mois = $mois ?? (int) date('n');
    $jour = $jour ?? (int) date('j');
    
    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= ' AND c.vendeur_id = :vendeur_id';
            $params['vendeur_id'] = (int) $vendeur_id;
        }

        if ($filtrer_vendues_uniquement) {
            $sql .= " AND c.statut IN ('livree', 'paye')";
        }
        
        switch ($periode) {
            case 'jour':
                $sql .= " AND DATE(c.date_commande) = :date_jour";
                $params['date_jour'] = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
                break;
            case 'plage':
                if (!empty($date_debut) && !empty($date_fin)) {
                    $sql .= " AND DATE(c.date_commande) BETWEEN :date_debut AND :date_fin";
                    $params['date_debut'] = $date_debut;
                    $params['date_fin'] = $date_fin;
                } elseif (!empty($date_debut)) {
                    $sql .= " AND DATE(c.date_commande) >= :date_debut";
                    $params['date_debut'] = $date_debut;
                } elseif (!empty($date_fin)) {
                    $sql .= " AND DATE(c.date_commande) <= :date_fin";
                    $params['date_fin'] = $date_fin;
                } else {
                    $sql .= " AND DATE(c.date_commande) = CURDATE()";
                }
                break;
            case 'mois':
                $sql .= " AND YEAR(c.date_commande) = :annee_mois AND MONTH(c.date_commande) = :num_mois";
                $params['annee_mois'] = $annee;
                $params['num_mois'] = $mois;
                break;
            case 'annee':
                $sql .= " AND YEAR(c.date_commande) = :annee";
                $params['annee'] = $annee;
                break;
            default:
                $sql .= " AND DATE(c.date_commande) = CURDATE()";
        }
        
        $sql .= " ORDER BY c.date_commande DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Totaux globaux (toutes dates) des commandes vendues : statuts livrée et payée uniquement.
 * @param int|null $vendeur_id Filtre boutique (marketplace)
 * @return array{nb:int,ca_total:float,ca_livree:float,ca_paye:float}
 */
function get_stats_commandes_vendues_globales($vendeur_id = null) {
    global $db;
    try {
        $sql = "
            SELECT
                COUNT(*) AS nb,
                COALESCE(SUM(montant_total), 0) AS ca_total,
                COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_total ELSE 0 END), 0) AS ca_livree,
                COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant_total ELSE 0 END), 0) AS ca_paye
            FROM commandes
            WHERE statut IN ('livree', 'paye')
        ";
        $params = [];
        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= ' AND vendeur_id = :vendeur_id';
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $stmt = $params ? $db->prepare($sql) : $db->query($sql);
        if ($params) {
            $stmt->execute($params);
        }
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return ['nb' => 0, 'ca_total' => 0.0, 'ca_livree' => 0.0, 'ca_paye' => 0.0];
        }
        return [
            'nb' => (int) $row['nb'],
            'ca_total' => (float) $row['ca_total'],
            'ca_livree' => (float) $row['ca_livree'],
            'ca_paye' => (float) $row['ca_paye'],
        ];
    } catch (PDOException $e) {
        error_log('[get_stats_commandes_vendues_globales] ' . $e->getMessage());
        return ['nb' => 0, 'ca_total' => 0.0, 'ca_livree' => 0.0, 'ca_paye' => 0.0];
    }
}

/**
 * Liste de toutes les commandes vendues (livrée + payée), plus récentes en premier.
 * @param int|null $vendeur_id Filtre boutique (marketplace)
 * @return array
 */
function get_all_commandes_vendues($vendeur_id = null) {
    global $db;
    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.statut IN ('livree', 'paye')
        ";
        $params = [];
        if ($vendeur_id !== null && $vendeur_id !== '') {
            $sql .= ' AND c.vendeur_id = :vendeur_id';
            $params['vendeur_id'] = (int) $vendeur_id;
        }
        $sql .= ' ORDER BY c.date_commande DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        error_log('[get_all_commandes_vendues] ' . $e->getMessage());
        return [];
    }
}

/**
 * Statistiques pour des commandes déjà limitées aux statuts livrée / payée
 * @param array $commandes
 * @return array{nb:int,ca_total:float,ca_livree:float,ca_paye:float}
 */
function get_stats_ventes_commandes_vendues($commandes) {
    $nb = count($commandes);
    $ca_total = 0.0;
    $ca_livree = 0.0;
    $ca_paye = 0.0;
    foreach ($commandes as $c) {
        $mt = (float) ($c['montant_total'] ?? 0);
        $ca_total += $mt;
        $st = $c['statut'] ?? '';
        if ($st === 'livree') {
            $ca_livree += $mt;
        } elseif ($st === 'paye') {
            $ca_paye += $mt;
        }
    }
    return [
        'nb' => $nb,
        'ca_total' => $ca_total,
        'ca_livree' => $ca_livree,
        'ca_paye' => $ca_paye,
    ];
}

/**
 * Statistiques comptabilité par période
 * @param array $commandes Tableau des commandes (déjà filtrées)
 * @return array [montant_total, nb_commandes, montant_livrees, montant_non_traitees, nb_livrees, nb_non_traitees]
 */
function get_stats_comptabilite_periode($commandes) {
    $montant_total = 0;
    $montant_livrees = 0;
    $montant_non_traitees = 0;
    $nb_livrees = 0;
    $nb_non_traitees = 0;
    $nb_annulees = 0;

    foreach ($commandes as $c) {
        $mt = (float) ($c['montant_total'] ?? 0);
        $st = $c['statut'] ?? '';
        $montant_total += $mt;

        if (in_array($st, ['livree', 'paye'], true)) {
            $montant_livrees += $mt;
            $nb_livrees++;
        } elseif ($st === 'annulee') {
            $nb_annulees++;
        } else {
            $montant_non_traitees += $mt;
            $nb_non_traitees++;
        }
    }
    
    return [
        'montant_total' => $montant_total,
        'nb_commandes' => count($commandes),
        'montant_livrees' => $montant_livrees,
        'montant_non_traitees' => $montant_non_traitees,
        'nb_livrees' => $nb_livrees,
        'nb_non_traitees' => $nb_non_traitees,
        'nb_annulees' => $nb_annulees,
    ];
}

