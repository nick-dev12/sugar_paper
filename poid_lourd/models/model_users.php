<?php
/**
 * Modèle pour la gestion des utilisateurs
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie si un utilisateur existe déjà avec cet email
 * @param string $email L'email à vérifier
 * @return bool True si l'email existe, False sinon
 */
function user_email_exists($email) {
    global $db;

    $email = trim((string) $email);
    if ($email === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();

        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un utilisateur par son email
 * @param string $email L'email de l'utilisateur
 * @return array|false Les données de l'utilisateur ou False si non trouvé
 */
function get_user_by_email($email) {
    global $db;

    $email = trim((string) $email);
    if ($email === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Indique si une colonne existe dans la table users.
 */
function users_has_column($column) {
    static $cache = [];
    global $db;

    $column = trim((string) $column);
    if ($column === '') {
        return false;
    }
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    if (!isset($db) || !($db instanceof PDO)) {
        $cache[$column] = false;
        return false;
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE " . $db->quote($column));
        $cache[$column] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        return $cache[$column];
    } catch (PDOException $e) {
        $cache[$column] = false;
        return false;
    }
}

/**
 * Récupère un utilisateur lié à un UID Firebase.
 */
function get_user_by_firebase_uid($firebase_uid) {
    global $db;

    $firebase_uid = trim((string) $firebase_uid);
    if ($firebase_uid === '' || !users_has_column('firebase_uid')) {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE firebase_uid = :uid LIMIT 1");
        $stmt->execute(['uid' => $firebase_uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Lie un compte client existant à Firebase/Google.
 */
function update_user_google_identity($user_id, $firebase_uid, $auth_provider = 'google') {
    global $db;

    if (!users_has_column('firebase_uid')) {
        return true;
    }

    $auth_provider = trim((string) $auth_provider);
    if ($auth_provider === '') {
        $auth_provider = 'google';
    }

    $sets = ['firebase_uid = :firebase_uid'];
    $params = [
        'id' => (int) $user_id,
        'firebase_uid' => trim((string) $firebase_uid),
    ];
    if (users_has_column('auth_provider')) {
        $sets[] = 'auth_provider = :auth_provider';
        $params['auth_provider'] = $auth_provider;
    }

    try {
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Complète les champs manquants d'un compte client Google.
 */
function update_user_google_missing_info($user_id, $nom, $telephone) {
    global $db;

    $tel_digits = users_normalize_phone_digits($telephone);
    if ($tel_digits === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("
            UPDATE users SET
                nom = :nom,
                telephone = :telephone,
                accepte_conditions = 1
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => (int) $user_id,
            'nom' => trim((string) $nom),
            'telephone' => $tel_digits,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Normalise un numéro saisi : uniquement les chiffres (comparaisons, doublons, insertion).
 */
function users_normalize_phone_digits($telephone) {
    return preg_replace('/\D/', '', (string) $telephone);
}

/**
 * Valeur du doublon MySQL considérée comme « vide » (email absent).
 */
function users_mysql_duplicate_value_is_empty($dup_val) {
    if ($dup_val === null) {
        return true;
    }
    $s = trim((string) $dup_val);
    return $s === '' || strtoupper($s) === 'NULL';
}

/**
 * Extrait nom de contrainte / index et valeur du message d’erreur doublon MySQL (1062).
 *
 * @return array{key:string,value:?string}
 */
function users_parse_mysql_duplicate_message(PDOException $e) {
    $driver_msg = isset($e->errorInfo[2]) ? (string) $e->errorInfo[2] : '';
    $blob = $driver_msg !== '' ? $driver_msg : $e->getMessage();

    $key = '';
    if (preg_match('/for key `([^`]+)`/iu', $blob, $mk)) {
        $key = strtolower(trim($mk[1]));
    } elseif (preg_match("/for key '([^']+)'/iu", $blob, $mk)) {
        $key = strtolower(trim($mk[1]));
    } elseif (preg_match('/for key "([^"]+)"/iu', $blob, $mk)) {
        $key = strtolower(trim($mk[1]));
    }
    if ($key !== '' && strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $key = strtolower(trim(end($parts)));
    }

    $value = null;
    if (preg_match("/duplicate entry\\s+'([^']*)'/iu", $blob, $mv)) {
        $value = $mv[1];
    }

    return ['key' => $key, 'value' => $value];
}

/**
 * Message utilisateur lisible à partir d'une erreur PDO lors de la création de compte.
 */
function users_format_create_user_exception(PDOException $e) {
    $info = $e->errorInfo ?? [];
    $state = isset($info[0]) ? (string) $info[0] : '';
    $driver_code = isset($info[1]) ? (string) $info[1] : '';
    $driver_msg = isset($info[2]) ? (string) $info[2] : '';
    $blob_lc = strtolower($driver_msg . ' ' . $e->getMessage());

    // Doublon MySQL / SQLSTATE 23000 (libellés FR possible : « Doublon »)
    $is_dup = ($state === '23000' || $driver_code === '1062'
        || strpos($blob_lc, 'duplicate') !== false || strpos($blob_lc, 'doublon') !== false);

    if ($is_dup) {
        $parsed = users_parse_mysql_duplicate_message($e);
        $dup_key = $parsed['key'];
        $dup_val = $parsed['value'];

        $looks_like_email_idx = ($dup_key !== '' && (
            preg_match('/(^|_)email($|_)/', $dup_key)
            || strpos($dup_key, 'idx_email') !== false
        ));
        $looks_like_tel_idx = ($dup_key !== '' && preg_match('/tel|phone|mobile/', $dup_key));

        if ($looks_like_tel_idx && !$looks_like_email_idx) {
            return 'Ce numéro de téléphone est déjà enregistré.';
        }

        if ($looks_like_email_idx) {
            if (users_mysql_duplicate_value_is_empty($dup_val)) {
                return 'Inscription sans email impossible avec la configuration actuelle de la base : une contrainte unique sur l’email bloque plusieurs comptes sans email. Exécutez la migration migrations/fix_users_email_unique_optional.sql (email nullable + suppression des emails vides), ou renseignez une adresse email.';
            }
            return 'Cet email est déjà utilisé par un autre compte.';
        }

        // Repli si le nom de clé n’a pas été reconnu : éviter les faux positifs « email » (ex. sous-chaîne dans un autre texte)
        if (
            strpos($blob_lc, 'telephone') !== false || strpos($blob_lc, 'téléphone') !== false
            || strpos($blob_lc, 'tel') !== false || strpos($blob_lc, 'phone') !== false
        ) {
            return 'Ce numéro de téléphone est déjà enregistré.';
        }
        if (strpos($blob_lc, 'idx_email') !== false || preg_match('/for key [`\'"][^`\'"]*email[^`\'"]*[`\'"]/iu', $driver_msg . $e->getMessage())) {
            if (users_mysql_duplicate_value_is_empty($dup_val)) {
                return 'Inscription sans email impossible avec la configuration actuelle de la base : une contrainte unique sur l’email bloque plusieurs comptes sans email. Exécutez la migration migrations/fix_users_email_unique_optional.sql (email nullable + suppression des emails vides), ou renseignez une adresse email.';
            }
            return 'Cet email est déjà utilisé par un autre compte.';
        }

        return 'Certaines informations sont déjà utilisées par un autre compte.';
    }

    if ($state === 'HY000' && strpos($blob_lc, 'cannot be null') !== false) {
        return 'Une donnée obligatoire manque côté serveur (schéma base). Contactez le support.';
    }

    if (
        strpos($blob_lc, 'data too long') !== false || strpos($blob_lc, 'too long') !== false
        || $driver_code === '1406'
    ) {
        return 'Une valeur dépasse la taille maximale autorisée (nom, email ou téléphone trop long).';
    }

    if (strpos($blob_lc, 'unknown column') !== false) {
        return 'La base de données semble incomplète (mise à jour manquante). Contactez le support.';
    }

    return 'Erreur lors de l\'enregistrement'
        . ($state !== '' ? ' (' . $state . ').' : '.')
        . ' Réessayez ou contactez le support.';
}

/**
 * Récupère un utilisateur par téléphone (chiffres uniquement, format libre en saisie).
 * @param string $telephone
 * @return array|false
 */
function get_user_by_telephone($telephone) {
    global $db;

    $digits = users_normalize_phone_digits($telephone);
    if ($digits === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM users
            WHERE telephone IS NOT NULL AND TRIM(telephone) != ''
              AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', ''), '.', '') = :d
            LIMIT 1
        ");
        $stmt->execute(['d' => $digits]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un utilisateur par son ID
 * @param int $id L'ID de l'utilisateur
 * @return array|false Les données de l'utilisateur ou False si non trouvé
 */
function get_user_by_id($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un nouvel utilisateur
 * @param string $nom Le nom de l'utilisateur
 * @param string $prenom Le prénom de l'utilisateur
 * @param string $email L'email de l'utilisateur
 * @param string $telephone Le téléphone de l'utilisateur
 * @param string $password_hash Le mot de passe hashé
 * @param string|null $creation_error Message précis si échec (référence)
 * @return int|false L'ID de l'utilisateur créé ou False en cas d'erreur
 */
function create_user($nom, $prenom, $email, $telephone, $password_hash, &$creation_error = null) {
    global $db;

    $creation_error = null;

    if (!$db) {
        $creation_error = 'Connexion à la base de données impossible. Réessayez plus tard.';
        return false;
    }

    $email_bind = null;
    if ($email !== null && trim((string) $email) !== '') {
        $email_bind = trim((string) $email);
    }

    $tel_digits = users_normalize_phone_digits($telephone);
    if ($tel_digits === '') {
        $creation_error = 'Numéro de téléphone invalide après normalisation.';
        return false;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut)
            VALUES (:nom, :prenom, :email, :telephone, :password, NOW(), 'actif')
        ");

        $result = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email_bind,
            'telephone' => $tel_digits,
            'password' => $password_hash
        ]);
        
        if ($result) {
            return $db->lastInsertId();
        }

        $creation_error = 'L\'enregistrement du compte a été refusé sans détail technique.';
        return false;
    } catch (PDOException $e) {
        $creation_error = users_format_create_user_exception($e);
        return false;
    }
}

/**
 * Crée un compte client depuis Google, puis le lie à Firebase.
 */
function create_google_user($nom, $prenom, $email, $telephone, $firebase_uid, &$creation_error = null, $auth_provider = 'google') {
    $password_hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
    $user_id = create_user($nom, $prenom, $email, $telephone, $password_hash, $creation_error);
    if ($user_id) {
        update_user_google_identity($user_id, $firebase_uid, $auth_provider);
    }
    return $user_id;
}

/**
 * Met à jour les informations d'un utilisateur
 * @param int $id L'ID de l'utilisateur
 * @param array $data Les nouvelles données
 * @return bool True en cas de succès, False sinon
 */
function update_user($id, $data) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE users SET
                nom = :nom,
                prenom = :prenom,
                email = :email,
                telephone = :telephone
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => ($data['email'] === null || $data['email'] === '') ? null : $data['email'],
            'telephone' => $data['telephone']
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Indique si le client a au moins une commande contenant un produit publié par cette boutique (admin_id).
 */
function user_a_commande_chez_boutique($user_id, $boutique_admin_id) {
    global $db;
    $uid = (int) $user_id;
    $vid = (int) $boutique_admin_id;
    if ($uid <= 0 || $vid <= 0) {
        return false;
    }
    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id')) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT 1
            FROM commandes c
            INNER JOIN commande_produits cp ON cp.commande_id = c.id
            INNER JOIN produits p ON p.id = cp.produit_id AND p.admin_id = :vid
            WHERE c.user_id = :uid
            LIMIT 1
        ");
        $stmt->execute(['uid' => $uid, 'vid' => $vid]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les utilisateurs avec leurs statistiques de commandes.
 *
 * @param int|null $boutique_admin_id Si défini (ex. vendeur marketplace), uniquement les clients ayant
 *                                    commandé au moins un produit dont produits.admin_id = cet ID.
 *                                    Les compteurs et le CA ne portent que sur ces commandes / lignes.
 * @return array Tableau des utilisateurs avec leurs statistiques
 */
function get_all_users_with_stats($boutique_admin_id = null) {
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
                SELECT
                    u.id,
                    u.nom,
                    u.prenom,
                    u.email,
                    u.telephone,
                    u.date_creation,
                    u.statut,
                    COUNT(DISTINCT c.id) AS nb_commandes,
                    COUNT(DISTINCT CASE WHEN c.statut = 'livree' THEN c.id END) AS nb_commandes_livrees,
                    COALESCE(SUM(CASE WHEN c.statut <> 'annulee' THEN vend_scope.vendor_ca ELSE 0 END), 0) AS ca_total_ht
                FROM users u
                INNER JOIN commandes c ON c.user_id = u.id
                INNER JOIN (
                    SELECT cp.commande_id, SUM(cp.prix_total) AS vendor_ca
                    FROM commande_produits cp
                    INNER JOIN produits p ON p.id = cp.produit_id AND p.admin_id = :boutique_admin_id
                    GROUP BY cp.commande_id
                ) vend_scope ON vend_scope.commande_id = c.id
                GROUP BY u.id
                ORDER BY nb_commandes DESC, nb_commandes_livrees DESC, u.date_creation DESC
            ");
            $stmt->execute(['boutique_admin_id' => $boutique_admin_id]);
        } else {
            $stmt = $db->prepare("
                SELECT
                    u.id,
                    u.nom,
                    u.prenom,
                    u.email,
                    u.telephone,
                    u.date_creation,
                    u.statut,
                    COUNT(DISTINCT c.id) as nb_commandes,
                    COUNT(DISTINCT CASE WHEN c.statut = 'livree' THEN c.id END) as nb_commandes_livrees,
                    COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND c.statut <> 'annulee' THEN c.montant_total ELSE 0 END), 0) AS ca_total_ht
                FROM users u
                LEFT JOIN commandes c ON u.id = c.user_id
                GROUP BY u.id
                ORDER BY nb_commandes DESC, nb_commandes_livrees DESC, u.date_creation DESC
            ");
            $stmt->execute();
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $users ? $users : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Construit la clause WHERE (alias u) pour recherche + filtre statut (liste plateforme).
 *
 * @param string $search Terme recherché (nom, prénom, email, téléphone)
 * @param string $statut_filtre '' | 'actif' | 'inactif'
 * @return array{sql:string,params:array}
 */
function _users_platform_filter_sql($search, $statut_filtre) {
    $search = trim((string) $search);
    $statut_filtre = (string) $statut_filtre;
    $parts = ['1=1'];
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $parts[] = '(u.nom LIKE :ulk1 OR u.prenom LIKE :ulk2 OR u.email LIKE :ulk3 OR COALESCE(u.telephone,\'\') LIKE :ulk4)';
        $params['ulk1'] = $like;
        $params['ulk2'] = $like;
        $params['ulk3'] = $like;
        $params['ulk4'] = $like;
    }
    if ($statut_filtre === 'actif' || $statut_filtre === 'inactif') {
        $parts[] = 'u.statut = :ustat';
        $params['ustat'] = $statut_filtre;
    }

    return [
        'sql' => implode(' AND ', $parts),
        'params' => $params,
    ];
}

/**
 * Nombre d'utilisateurs correspondant aux filtres (vue super admin plateforme).
 */
function count_users_platform_filtered($search, $statut_filtre) {
    global $db;

    $f = _users_platform_filter_sql($search, $statut_filtre);
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users u WHERE ' . $f['sql']);
        $stmt->execute($f['params']);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Utilisateurs avec stats commandes, filtrés et paginés (scope plateforme globale).
 *
 * @param string $search
 * @param string $statut_filtre '' | 'actif' | 'inactif'
 * @param int $page Numéro de page (1-based)
 * @param int $per_page Entrées par page (5–100)
 * @return array Liste des lignes utilisateur + stats
 */
function get_users_platform_paginated($search, $statut_filtre, $page, $per_page) {
    global $db;

    $page = max(1, (int) $page);
    $per_page = max(5, min(100, (int) $per_page));
    $offset = ($page - 1) * $per_page;

    $f = _users_platform_filter_sql($search, $statut_filtre);
    $whereSql = $f['sql'];

    try {
        $sql = "
            SELECT
                u.id,
                u.nom,
                u.prenom,
                u.email,
                u.telephone,
                u.date_creation,
                u.statut,
                COUNT(DISTINCT c.id) AS nb_commandes,
                COUNT(DISTINCT CASE WHEN c.statut = 'livree' THEN c.id END) AS nb_commandes_livrees,
                COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND c.statut <> 'annulee' THEN c.montant_total ELSE 0 END), 0) AS ca_total_ht
            FROM users u
            LEFT JOIN commandes c ON u.id = c.user_id
            WHERE $whereSql
            GROUP BY u.id
            ORDER BY u.date_creation DESC
            LIMIT " . (int) $per_page . " OFFSET " . (int) $offset . "
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($f['params']);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $users ? $users : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Met à jour le statut d'un utilisateur (actif/inactif)
 * @param int $id L'ID de l'utilisateur
 * @param string $statut Le nouveau statut ('actif' ou 'inactif')
 * @return bool True en cas de succès, False sinon
 */
function update_user_statut($id, $statut) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET statut = :statut WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'statut' => $statut
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour l'acceptation des conditions d'utilisation par un utilisateur
 * @param int $id L'ID de l'utilisateur
 * @param bool $accepte True si accepté, False sinon
 * @return bool True en cas de succès, False sinon
 */
function update_user_accepte_conditions($id, $accepte = true) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET accepte_conditions = :accepte WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'accepte' => $accepte ? 1 : 0
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le mot de passe d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param string $password_hash Le nouveau mot de passe hashé
 * @return bool True en cas de succès, False sinon
 */
function update_user_password($user_id, $password_hash) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        return $stmt->execute(['id' => $user_id, 'password' => $password_hash]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un token de réinitialisation de mot de passe pour un client
 * @param string $email L'email du client
 * @param string $token Le token généré
 * @param string $expires_at Date d'expiration (format DATETIME)
 * @return bool True en cas de succès, False sinon
 */
function create_user_password_reset_token($email, $token, $expires_at) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM user_password_reset WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        $stmt = $db->prepare("
            INSERT INTO user_password_reset (email, token, expires_at) 
            VALUES (:email, :token, :expires_at)
        ");
        return $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un token de réinitialisation valide pour un client
 * @param string $token Le token à vérifier
 * @return array|false Les données du token ou False si invalide/expiré
 */
function get_valid_user_reset_token($token) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM user_password_reset 
            WHERE token = :token AND used = 0 AND expires_at > NOW()
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Marque un token client comme utilisé
 * @param string $token Le token à marquer
 * @return bool True en cas de succès, False sinon
 */
function mark_user_reset_token_used($token) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE user_password_reset SET used = 1 WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Totaux commandes boutique pour la fiche client (hors annulées pour le CA).
 *
 * @param int|null $boutique_admin_id Si défini, uniquement les commandes qui contiennent au moins une ligne
 *                                    de produits de cette boutique ; le CA est la somme des prix_total de ces lignes.
 * @return array{nb_commandes: int, ca_total_ht: float}
 */
function get_user_stats_commandes_boutique($user_id, $boutique_admin_id = null) {
    global $db;
    $uid = (int) $user_id;
    $boutique_admin_id = $boutique_admin_id !== null ? (int) $boutique_admin_id : null;
    if ($boutique_admin_id !== null && $boutique_admin_id <= 0) {
        $boutique_admin_id = null;
    }

    try {
        if ($boutique_admin_id !== null) {
            require_once __DIR__ . '/model_produits.php';
            if (!produits_has_column('admin_id')) {
                return ['nb_commandes' => 0, 'ca_total_ht' => 0.0];
            }
            $stmt = $db->prepare("
                SELECT
                    COUNT(DISTINCT c.id) AS nb_commandes,
                    COALESCE(SUM(CASE WHEN c.statut <> 'annulee' THEN vs.vendor_ca ELSE 0 END), 0) AS ca_total_ht
                FROM commandes c
                INNER JOIN (
                    SELECT cp.commande_id, SUM(cp.prix_total) AS vendor_ca
                    FROM commande_produits cp
                    INNER JOIN produits p ON p.id = cp.produit_id AND p.admin_id = :boutique_admin_id
                    GROUP BY cp.commande_id
                ) vs ON vs.commande_id = c.id
                WHERE c.user_id = :uid
            ");
            $stmt->execute(['uid' => $uid, 'boutique_admin_id' => $boutique_admin_id]);
        } else {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) AS nb_commandes,
                    COALESCE(SUM(CASE WHEN statut <> 'annulee' THEN montant_total ELSE 0 END), 0) AS ca_total_ht
                FROM commandes
                WHERE user_id = :uid
            ");
            $stmt->execute(['uid' => $uid]);
        }
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'nb_commandes' => (int) ($r['nb_commandes'] ?? 0),
            'ca_total_ht' => (float) ($r['ca_total_ht'] ?? 0),
        ];
    } catch (PDOException $e) {
        return ['nb_commandes' => 0, 'ca_total_ht' => 0.0];
    }
}

?>

