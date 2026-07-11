<?php
/**
 * Modèle Super Administrateur (plateforme marketplace)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Indique si au moins un compte super_admin existe
 */
function super_admin_exists() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM super_admin");
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Email déjà utilisé
 */
function super_admin_email_exists($email) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM super_admin WHERE email = :e");
        $stmt->execute(['e' => (string) $email]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un super administrateur
 * @return int|false
 */
function create_super_admin($nom, $prenom, $email, $password_hash) {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO super_admin (nom, prenom, email, password, date_creation, statut)
            VALUES (:nom, :prenom, :email, :pw, NOW(), 'actif')
        ");
        $ok = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'pw' => $password_hash,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array|false
 */
function get_super_admin_by_email($email) {
    global $db;
    if ($email === '' || $email === null) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT * FROM super_admin WHERE email = :e LIMIT 1");
        $stmt->execute(['e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array|false
 */
function get_super_admin_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT * FROM super_admin WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Liste tous les comptes super administrateur
 * @return array
 */
function super_admin_list_all() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT id, nom, prenom, email, date_creation, derniere_connexion, statut
            FROM super_admin
            ORDER BY date_creation DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Nombre de comptes super admin actifs
 */
function super_admin_count_actifs() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM super_admin WHERE statut = 'actif'");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Met à jour nom, prénom et e-mail d'un super admin
 */
function super_admin_update_profile($id, $nom, $prenom, $email) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            UPDATE super_admin
            SET nom = :nom, prenom = :prenom, email = :email
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le mot de passe hashé
 */
function super_admin_update_password($id, $password_hash) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || $password_hash === '') {
        return false;
    }
    try {
        $stmt = $db->prepare("UPDATE super_admin SET password = :pw WHERE id = :id");
        return $stmt->execute(['id' => $id, 'pw' => $password_hash]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Active ou désactive un compte super admin
 */
function super_admin_set_statut($id, $statut) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !in_array($statut, ['actif', 'inactif'], true)) {
        return false;
    }
    try {
        $stmt = $db->prepare("UPDATE super_admin SET statut = :s WHERE id = :id");
        return $stmt->execute(['s' => $statut, 'id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour la dernière connexion
 */
function super_admin_update_last_login($id) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE super_admin SET derniere_connexion = NOW() WHERE id = :id");
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Liste des boutiques vendeurs avec compteurs produits
 * @return array
 */
function super_admin_list_boutiques_with_stats() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT
                a.id,
                a.nom,
                a.prenom,
                a.email,
                a.telephone,
                a.boutique_nom,
                a.boutique_slug,
                a.statut,
                a.date_creation,
                a.role,
                COALESCE(COUNT(p.id), 0) AS nb_produits_total,
                COALESCE(SUM(CASE WHEN p.statut IN ('actif', 'rupture_stock') THEN 1 ELSE 0 END), 0) AS nb_produits_catalogue,
                COALESCE(SUM(CASE WHEN p.statut = 'actif' THEN 1 ELSE 0 END), 0) AS nb_produits_actifs
            FROM admin a
            LEFT JOIN produits p ON p.admin_id = a.id
            WHERE a.role = 'vendeur'
            GROUP BY a.id
            ORDER BY a.date_creation DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Clause WHERE pour la liste des boutiques (alias a) + paramètres PDO.
 *
 * @param string $search Nom, slug, e-mail, téléphone, etc.
 * @param string $statut_filtre '' | 'actif' | 'inactif'
 * @param string $cert_filtre '' | 'certifie' | 'non_certifie'
 * @param string $country_filtre Code ISO pays (ex. SN) ou ''
 * @return array{sql:string,params:array}
 */
function _super_admin_boutiques_filter_sql($search, $statut_filtre, $cert_filtre = '', $country_filtre = '') {
    $search = trim((string) $search);
    $statut_filtre = (string) $statut_filtre;
    $cert_filtre = (string) $cert_filtre;
    $country_filtre = strtoupper(trim((string) $country_filtre));
    $parts = ["a.role = 'vendeur'"];
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $parts[] = '(a.boutique_nom LIKE :b1 OR a.boutique_slug LIKE :b2 OR a.nom LIKE :b3 OR a.prenom LIKE :b4 OR a.email LIKE :b5 OR COALESCE(a.telephone,\'\') LIKE :b6)';
        $params['b1'] = $like;
        $params['b2'] = $like;
        $params['b3'] = $like;
        $params['b4'] = $like;
        $params['b5'] = $like;
        $params['b6'] = $like;
    }
    if ($statut_filtre === 'actif' || $statut_filtre === 'inactif') {
        $parts[] = 'a.statut = :bstat';
        $params['bstat'] = $statut_filtre;
    }

    $cert_col_ok = false;
    if (file_exists(dirname(__DIR__) . '/models/model_vendeur_certification.php')) {
        require_once dirname(__DIR__) . '/models/model_vendeur_certification.php';
        $cert_col_ok = vendeur_certification_admin_column_exists();
    }
    if ($cert_col_ok && ($cert_filtre === 'certifie' || $cert_filtre === 'non_certifie')) {
        if ($cert_filtre === 'certifie') {
            $parts[] = "a.certification_niveau IS NOT NULL AND a.certification_niveau <> ''";
        } else {
            $parts[] = '(a.certification_niveau IS NULL OR a.certification_niveau = \'\')';
        }
    }

    if ($country_filtre !== '') {
        if (!function_exists('marketplace_country_is_valid')) {
            require_once dirname(__DIR__) . '/includes/marketplace_countries.php';
        }
        if (marketplace_country_is_valid($country_filtre)) {
            if (!function_exists('admin_has_boutique_country_column')) {
                require_once __DIR__ . '/model_admin.php';
            }
            if (admin_has_boutique_country_column()) {
                $parts[] = 'a.boutique_country = :bcountry';
                $params['bcountry'] = $country_filtre;
            } elseif ($country_filtre !== 'SN') {
                $parts[] = '1 = 0';
            }
        }
    }

    return [
        'sql' => implode(' AND ', $parts),
        'params' => $params,
    ];
}

/**
 * Nombre de boutiques (vendeurs) correspondant aux filtres.
 */
function count_boutiques_platform_filtered($search, $statut_filtre, $cert_filtre = '', $country_filtre = '') {
    global $db;

    $f = _super_admin_boutiques_filter_sql($search, $statut_filtre, $cert_filtre, $country_filtre);
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM admin a WHERE ' . $f['sql']);
        $stmt->execute($f['params']);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Boutiques vendeurs avec stats produits, filtrées et paginées.
 *
 * @return array
 */
function get_boutiques_platform_paginated($search, $statut_filtre, $page, $per_page, $cert_filtre = '', $country_filtre = '') {
    global $db;

    $page = max(1, (int) $page);
    $per_page = max(5, min(100, (int) $per_page));
    $offset = ($page - 1) * $per_page;

    $f = _super_admin_boutiques_filter_sql($search, $statut_filtre, $cert_filtre, $country_filtre);
    $whereSql = $f['sql'];

    $cert_select = '';
    if (file_exists(dirname(__DIR__) . '/models/model_vendeur_certification.php')) {
        require_once dirname(__DIR__) . '/models/model_vendeur_certification.php';
        if (vendeur_certification_admin_column_exists()) {
            $cert_select = ', a.certification_niveau, a.certification_date';
        }
    }

    $country_select = '';
    if (!function_exists('admin_has_boutique_country_column')) {
        require_once __DIR__ . '/model_admin.php';
    }
    if (admin_has_boutique_country_column()) {
        $country_select = ', a.boutique_country';
    }

    try {
        $sql = "
            SELECT
                a.id,
                a.nom,
                a.prenom,
                a.email,
                a.telephone,
                a.boutique_nom,
                a.boutique_slug,
                a.statut,
                a.date_creation,
                a.role
                $cert_select
                $country_select,
                COALESCE(COUNT(p.id), 0) AS nb_produits_total,
                COALESCE(SUM(CASE WHEN p.statut IN ('actif', 'rupture_stock') THEN 1 ELSE 0 END), 0) AS nb_produits_catalogue,
                COALESCE(SUM(CASE WHEN p.statut = 'actif' THEN 1 ELSE 0 END), 0) AS nb_produits_actifs
            FROM admin a
            LEFT JOIN produits p ON p.admin_id = a.id
            WHERE $whereSql
            GROUP BY a.id
            ORDER BY a.date_creation DESC
            LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset . '
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute($f['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une ligne boutique vendeur + compteurs (pour fiche détail)
 * @return array|false
 */
function super_admin_get_boutique_stats($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT
                a.*,
                COALESCE(COUNT(p.id), 0) AS nb_produits_total,
                COALESCE(SUM(CASE WHEN p.statut IN ('actif', 'rupture_stock') THEN 1 ELSE 0 END), 0) AS nb_produits_catalogue,
                COALESCE(SUM(CASE WHEN p.statut = 'actif' THEN 1 ELSE 0 END), 0) AS nb_produits_actifs,
                COALESCE(SUM(CASE WHEN p.statut = 'inactif' THEN 1 ELSE 0 END), 0) AS nb_produits_inactifs,
                COALESCE(SUM(CASE WHEN p.statut = 'rupture_stock' THEN 1 ELSE 0 END), 0) AS nb_produits_rupture
            FROM admin a
            LEFT JOIN produits p ON p.admin_id = a.id
            WHERE a.id = :id AND a.role = 'vendeur'
            GROUP BY a.id
        ");
        $stmt->execute(['id' => $admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Active / désactive l'accès d'une boutique (compte vendeur uniquement)
 */
function super_admin_set_vendeur_statut($vendeur_admin_id, $statut) {
    global $db;
    $vendeur_admin_id = (int) $vendeur_admin_id;
    if ($vendeur_admin_id <= 0 || !in_array($statut, ['actif', 'inactif'], true)) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            UPDATE admin SET statut = :s WHERE id = :id AND role = 'vendeur'
        ");
        return $stmt->execute(['s' => $statut, 'id' => $vendeur_admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * KPI tableau de bord plateforme
 * @return array
 */
function super_admin_dashboard_kpis() {
    global $db;
    $out = [
        'nb_boutiques' => 0,
        'nb_boutiques_actives' => 0,
        'nb_clients' => 0,
        'nb_clients_actifs' => 0,
        'nb_commandes' => 0,
        'ca_mois' => 0.0,
        'nb_produits_catalogue' => 0,
    ];
    try {
        $stmt = $db->query("
            SELECT
                (SELECT COUNT(*) FROM admin WHERE role = 'vendeur') AS nb_boutiques,
                (SELECT COUNT(*) FROM admin WHERE role = 'vendeur' AND statut = 'actif') AS nb_boutiques_actives,
                (SELECT COUNT(*) FROM users) AS nb_clients,
                (SELECT COUNT(*) FROM users WHERE statut = 'actif') AS nb_clients_actifs,
                (SELECT COUNT(*) FROM commandes WHERE statut <> 'annulee') AS nb_commandes,
                (SELECT COALESCE(SUM(montant_total), 0) FROM commandes
                 WHERE statut <> 'annulee' AND YEAR(date_commande) = YEAR(CURDATE()) AND MONTH(date_commande) = MONTH(CURDATE())) AS ca_mois,
                (SELECT COALESCE(COUNT(*), 0) FROM produits p
                 INNER JOIN admin a ON a.id = p.admin_id AND a.role = 'vendeur'
                 WHERE p.statut IN ('actif', 'rupture_stock')) AS nb_produits_catalogue
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['nb_boutiques'] = (int) $row['nb_boutiques'];
            $out['nb_boutiques_actives'] = (int) $row['nb_boutiques_actives'];
            $out['nb_clients'] = (int) $row['nb_clients'];
            $out['nb_clients_actifs'] = (int) $row['nb_clients_actifs'];
            $out['nb_commandes'] = (int) $row['nb_commandes'];
            $out['ca_mois'] = (float) $row['ca_mois'];
            $out['nb_produits_catalogue'] = (int) $row['nb_produits_catalogue'];
        }
    } catch (PDOException $e) {
        // garder zéros si tables manquantes
    }
    return $out;
}

/**
 * Journal d'audit
 */
function super_admin_log_action($super_admin_id, $action, $cible_type = null, $cible_id = null, $details = null) {
    global $db;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (strlen((string) $ip) > 45) {
            $ip = substr((string) $ip, 0, 45);
        }
        $stmt = $db->prepare("
            INSERT INTO super_admin_logs (super_admin_id, action, cible_type, cible_id, details, date_action, ip)
            VALUES (:sid, :act, :ctype, :cid, :det, NOW(), :ip)
        ");
        return $stmt->execute([
            'sid' => (int) $super_admin_id,
            'act' => $action,
            'ctype' => $cible_type,
            'cid' => $cible_id !== null ? (int) $cible_id : null,
            'det' => $details,
            'ip' => $ip,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Dernières entrées du journal
 * @return array
 */
function super_admin_logs_recent($limit = 100) {
    global $db;
    $limit = max(1, min(500, (int) $limit));
    try {
        $stmt = $db->prepare("
            SELECT l.*, s.email AS super_admin_email, s.nom AS super_admin_nom
            FROM super_admin_logs l
            INNER JOIN super_admin s ON s.id = l.super_admin_id
            ORDER BY l.date_action DESC
            LIMIT $limit
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}
