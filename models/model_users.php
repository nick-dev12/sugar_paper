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
 * Normalise un numéro saisi : uniquement les chiffres.
 */
function users_normalize_phone_digits($telephone) {
    return preg_replace('/\D/', '', (string) $telephone);
}

/**
 * Variantes de recherche pour un même numéro (local vs indicatif pays).
 *
 * @return string[]
 */
function users_phone_lookup_variants($telephone) {
    $digits = users_normalize_phone_digits($telephone);
    if ($digits === '') {
        return [];
    }

    $variants = [$digits];

    // Sénégal : 221 + 9 chiffres (70, 76, 77, 78…)
    if (strlen($digits) === 12 && strpos($digits, '221') === 0) {
        $local = substr($digits, 3);
        if (strlen($local) === 9) {
            $variants[] = $local;
            $variants[] = '0' . $local;
        }
    }
    if (strlen($digits) === 9 && preg_match('/^[67]/', $digits)) {
        $variants[] = '221' . $digits;
    }
    if (strlen($digits) === 10 && $digits[0] === '0' && preg_match('/^0[67]/', $digits)) {
        $variants[] = substr($digits, 1);
        $variants[] = '221' . substr($digits, 1);
    }

    // France : 33 + 9 chiffres
    if (strlen($digits) === 11 && strpos($digits, '33') === 0) {
        $local = substr($digits, 2);
        if (strlen($local) === 9) {
            $variants[] = $local;
            $variants[] = '0' . $local;
        }
    }
    if (strlen($digits) === 10 && $digits[0] === '0') {
        $variants[] = '33' . substr($digits, 1);
    }

    return array_values(array_unique(array_filter($variants)));
}

/**
 * Expression SQL : téléphone normalisé (chiffres uniquement).
 */
function users_phone_normalized_sql($column = 'telephone') {
    return "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column},''), ' ', ''), '-', ''), '+', ''), '.', '')";
}

/**
 * Email placeholder pour comptes créés sans email (colonne NOT NULL).
 */
function users_phone_placeholder_email($tel_digits) {
    $tel_digits = users_normalize_phone_digits($tel_digits);
    $base = 'tel+' . $tel_digits . '@guest.sugarpaper.local';
    if (!user_email_exists($base)) {
        return $base;
    }
    return 'tel+' . $tel_digits . '+' . bin2hex(random_bytes(4)) . '@guest.sugarpaper.local';
}

/**
 * Récupère un utilisateur par téléphone (formats locaux ou internationaux).
 */
function get_user_by_telephone($telephone) {
    global $db;

    $variants = users_phone_lookup_variants($telephone);
    if (empty($variants)) {
        return false;
    }

    $norm = users_phone_normalized_sql('telephone');

    try {
        foreach ($variants as $variant) {
            $stmt = $db->prepare("
                SELECT * FROM users
                WHERE telephone IS NOT NULL AND TRIM(telephone) != ''
                  AND {$norm} = :d
                LIMIT 1
            ");
            $stmt->execute(['d' => $variant]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return $user;
            }
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un compte client depuis Google, puis le lie à Firebase.
 */
function create_google_user($nom, $prenom, $email, $telephone, $firebase_uid, $auth_provider = 'google') {
    $password_hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
    $user_id = create_user($nom, $prenom, $email, $telephone, $password_hash);
    if ($user_id) {
        update_user_google_identity($user_id, $firebase_uid, $auth_provider);
    }
    return $user_id;
}

/**
 * Crée un nouvel utilisateur
 * @param string $nom Le nom de l'utilisateur
 * @param string $prenom Le prénom de l'utilisateur
 * @param string $email L'email de l'utilisateur
 * @param string $telephone Le téléphone de l'utilisateur
 * @param string $password_hash Le mot de passe hashé
 * @return int|false L'ID de l'utilisateur créé ou False en cas d'erreur
 */
function create_user($nom, $prenom, $email, $telephone, $password_hash) {
    global $db;

    $email_bind = null;
    if ($email !== null && trim((string) $email) !== '') {
        $email_bind = trim((string) $email);
    }

    $tel_digits = users_normalize_phone_digits($telephone);
    if ($tel_digits === '') {
        return false;
    }

    if ($email_bind === null) {
        $email_bind = users_phone_placeholder_email($tel_digits);
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
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un client via checkout invité (nom + téléphone, mot de passe système).
 *
 * @return int|false
 */
function create_user_guest_checkout($nom, $telephone, $password_hash) {
    global $db;

    $nom = trim((string) $nom);
    $tel_digits = users_normalize_phone_digits($telephone);
    if ($nom === '' || $tel_digits === '') {
        return false;
    }

    $email_bind = users_phone_placeholder_email($tel_digits);
    $has_flag = users_has_column('inscription_checkout_invite');

    try {
        if ($has_flag) {
            $stmt = $db->prepare("
                INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut, inscription_checkout_invite)
                VALUES (:nom, '', :email, :telephone, :password, NOW(), 'actif', 1)
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut)
                VALUES (:nom, '', :email, :telephone, :password, NOW(), 'actif')
            ");
        }

        $result = $stmt->execute([
            'nom' => $nom,
            'email' => $email_bind,
            'telephone' => $tel_digits,
            'password' => $password_hash,
        ]);

        if ($result) {
            return (int) $db->lastInsertId();
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
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
            'email' => $data['email'],
            'telephone' => $data['telephone']
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les utilisateurs avec leurs statistiques de commandes
 * @return array Tableau des utilisateurs avec leurs statistiques
 */
function get_all_users_with_stats() {
    global $db;
    
    try {
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
                COUNT(DISTINCT CASE WHEN c.statut = 'livree' THEN c.id END) as nb_commandes_livrees
            FROM users u
            LEFT JOIN commandes c ON u.id = c.user_id
            GROUP BY u.id
            ORDER BY nb_commandes DESC, nb_commandes_livrees DESC, u.date_creation DESC
        ");
        $stmt->execute();
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
 * Indique si l'utilisateur a des commandes ou demandes encore en cours.
 */
function user_has_pending_orders($user_id) {
    global $db;

    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM commandes
            WHERE user_id = :user_id
              AND statut IN ('en_attente', 'confirmee', 'en_preparation', 'expediee')
        ");
        $stmt->execute(['user_id' => $user_id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (PDOException $e) {
        return true;
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM commandes_personnalisees
            WHERE user_id = :user_id
              AND statut IN ('en_attente', 'confirmee', 'en_preparation', 'devis_envoye', 'acceptee')
        ");
        $stmt->execute(['user_id' => $user_id]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Supprime définitivement un compte client et les données associées.
 * Les commandes passées sont conservées (anonymisation du lien user_id).
 *
 * @return array ['ok' => bool, 'error' => string]
 */
function delete_user_account($user_id) {
    global $db;

    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return ['ok' => false, 'error' => 'Compte invalide.'];
    }

    $user = get_user_by_id($user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'Compte introuvable.'];
    }

    if (user_has_pending_orders($user_id)) {
        return [
            'ok' => false,
            'error' => 'Impossible de supprimer votre compte tant que des commandes sont en cours. Finalisez-les ou contactez le support.',
        ];
    }

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM panier WHERE user_id = :user_id')->execute(['user_id' => $user_id]);

        try {
            $db->prepare('DELETE FROM favoris WHERE user_id = :user_id')->execute(['user_id' => $user_id]);
        } catch (PDOException $e) {
            // Table optionnelle
        }

        try {
            $db->prepare('DELETE FROM produits_visites WHERE user_id = :user_id')->execute(['user_id' => $user_id]);
        } catch (PDOException $e) {
            // Table optionnelle
        }

        try {
            $db->prepare("UPDATE fcm_tokens SET user_id = NULL WHERE user_id = :user_id AND type = 'user'")
                ->execute(['user_id' => $user_id]);
        } catch (PDOException $e) {
            // Table optionnelle
        }

        if (!empty($user['email'])) {
            try {
                $db->prepare('DELETE FROM user_password_reset WHERE email = :email')
                    ->execute(['email' => $user['email']]);
            } catch (PDOException $e) {
                // Table optionnelle
            }
        }

        $client_nom = trim((string) ($user['nom'] ?? ''));
        $client_prenom = trim((string) ($user['prenom'] ?? ''));
        $client_email = trim((string) ($user['email'] ?? ''));
        $client_telephone = users_normalize_phone_digits($user['telephone'] ?? '');

        $db->prepare("
            UPDATE commandes SET
                user_id = NULL,
                client_nom = COALESCE(NULLIF(client_nom, ''), :client_nom),
                client_prenom = COALESCE(NULLIF(client_prenom, ''), :client_prenom),
                client_email = COALESCE(NULLIF(client_email, ''), :client_email),
                client_telephone = COALESCE(NULLIF(client_telephone, ''), :client_telephone)
            WHERE user_id = :user_id
        ")->execute([
            'user_id' => $user_id,
            'client_nom' => $client_nom,
            'client_prenom' => $client_prenom,
            'client_email' => $client_email,
            'client_telephone' => $client_telephone,
        ]);

        try {
            $db->prepare("
                UPDATE commandes_personnalisees SET
                    user_id = NULL,
                    nom = COALESCE(NULLIF(nom, ''), :nom),
                    prenom = COALESCE(NULLIF(prenom, ''), :prenom),
                    email = COALESCE(NULLIF(email, ''), :email),
                    telephone = COALESCE(NULLIF(telephone, ''), :telephone)
                WHERE user_id = :user_id
            ")->execute([
                'user_id' => $user_id,
                'nom' => $client_nom,
                'prenom' => $client_prenom,
                'email' => $client_email,
                'telephone' => $client_telephone,
            ]);
        } catch (PDOException $e) {
            // Table optionnelle
        }

        try {
            $db->prepare('UPDATE tracking_watch_tokens SET user_id = NULL WHERE user_id = :user_id')
                ->execute(['user_id' => $user_id]);
        } catch (PDOException $e) {
            // Table optionnelle
        }

        $deleted = $db->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $user_id]);
        if (!$deleted) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'La suppression du compte a échoué.'];
        }

        $db->commit();
        return ['ok' => true, 'error' => ''];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[delete_user_account] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Une erreur technique est survenue. Veuillez réessayer ou contacter le support.'];
    }
}

