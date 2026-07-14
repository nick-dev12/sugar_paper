<?php
/**
 * Modèle suivi GPS livreurs
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/tracking_config.php';

function livreur_tracking_tables_ready() {
    global $db;
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'livreurs'");
        $ready = $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $ready = false;
    }
    return $ready;
}

function livreur_hash_password($password) {
    return password_hash((string) $password, PASSWORD_BCRYPT);
}

function livreur_verify_password($password, $hash) {
    return password_verify((string) $password, (string) $hash);
}

function livreur_generate_token() {
    return bin2hex(random_bytes(32));
}

function livreur_hash_token($token) {
    return hash('sha256', (string) $token);
}

function livreur_get_all($statut = null) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return [];
    }

    try {
        $sql = "SELECT id, nom, prenom, email, telephone, statut, date_creation FROM livreurs WHERE 1=1";
        $params = [];
        if ($statut !== null) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY nom ASC, prenom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function livreur_get_by_id($livreur_id) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, telephone, statut, date_creation
            FROM livreurs WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => (int) $livreur_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_by_email($email) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM livreurs WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => trim((string) $email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_create($nom, $prenom, $email, $telephone, $password, $statut = 'actif') {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    $email = trim((string) $email);
    if ($email === '' || trim((string) $password) === '') {
        return false;
    }
    if (livreur_get_by_email($email)) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO livreurs (nom, prenom, email, telephone, password, statut, date_creation)
            VALUES (:nom, :prenom, :email, :telephone, :password, :statut, NOW())
        ");
        $stmt->execute([
            'nom' => trim((string) $nom),
            'prenom' => trim((string) $prenom),
            'email' => $email,
            'telephone' => trim((string) $telephone) ?: null,
            'password' => livreur_hash_password($password),
            'statut' => $statut === 'inactif' ? 'inactif' : 'actif',
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_update_statut($livreur_id, $statut) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $stmt = $db->prepare("UPDATE livreurs SET statut = :statut WHERE id = :id");
        return $stmt->execute([
            'statut' => $statut === 'inactif' ? 'inactif' : 'actif',
            'id' => (int) $livreur_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_authenticate($email, $password) {
    $livreur = livreur_get_by_email($email);
    if (!$livreur || ($livreur['statut'] ?? '') !== 'actif') {
        return false;
    }
    if (!livreur_verify_password($password, $livreur['password'] ?? '')) {
        return false;
    }
    unset($livreur['password']);
    return $livreur;
}

function livreur_create_session($livreur_id, $commande_id = null, $device_info = null) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    $token = livreur_generate_token();
    $hash = livreur_hash_token($token);
    $ttl = (int) tracking_config_get('livreur_token_ttl_hours', 720);
    if ($ttl < 1) {
        $ttl = 720;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO livreur_sessions (livreur_id, token_hash, commande_id, expires_at, device_info, date_creation)
            VALUES (:livreur_id, :token_hash, :commande_id, DATE_ADD(NOW(), INTERVAL :ttl HOUR), :device_info, NOW())
        ");
        $stmt->bindValue(':livreur_id', (int) $livreur_id, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $hash, PDO::PARAM_STR);
        if ($commande_id !== null) {
            $stmt->bindValue(':commande_id', (int) $commande_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':commande_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':ttl', $ttl, PDO::PARAM_INT);
        $stmt->bindValue(':device_info', $device_info ? substr((string) $device_info, 0, 255) : null, PDO::PARAM_STR);
        $stmt->execute();

        return [
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + ($ttl * 3600)),
            'livreur_id' => (int) $livreur_id,
            'commande_id' => $commande_id !== null ? (int) $commande_id : null,
        ];
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_session_by_token($token) {
    global $db;
    if (!livreur_tracking_tables_ready() || trim((string) $token) === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT s.*, l.nom, l.prenom, l.email, l.statut AS livreur_statut
            FROM livreur_sessions s
            INNER JOIN livreurs l ON l.id = s.livreur_id
            WHERE s.token_hash = :hash
              AND s.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['hash' => livreur_hash_token($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['livreur_statut'] ?? '') !== 'actif') {
            return false;
        }
        return $row;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_revoke_session($token) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $stmt = $db->prepare("DELETE FROM livreur_sessions WHERE token_hash = :hash");
        return $stmt->execute(['hash' => livreur_hash_token($token)]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_update_session_commande($token, $commande_id) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            UPDATE livreur_sessions
            SET commande_id = :commande_id
            WHERE token_hash = :hash AND expires_at > NOW()
        ");
        return $stmt->execute([
            'commande_id' => $commande_id !== null ? (int) $commande_id : null,
            'hash' => livreur_hash_token($token),
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_create_watch_token($commande_id, $type, $admin_id = null, $user_id = null, $bl_id = null) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    $commande_id = $commande_id !== null ? (int) $commande_id : 0;
    $bl_id = $bl_id !== null ? (int) $bl_id : 0;
    if ($commande_id < 1 && $bl_id < 1) {
        return false;
    }

    $token = livreur_generate_token();
    $hash = livreur_hash_token($token);
    $ttl = (int) tracking_config_get('watch_token_ttl_minutes', 480);
    if ($ttl < 5) {
        $ttl = 480;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO tracking_watch_tokens
                (token_hash, commande_id, bl_id, type, admin_id, user_id, expires_at, date_creation)
            VALUES
                (:token_hash, :commande_id, :bl_id, :type, :admin_id, :user_id, DATE_ADD(NOW(), INTERVAL :ttl MINUTE), NOW())
        ");
        $stmt->bindValue(':token_hash', $hash, PDO::PARAM_STR);
        if ($commande_id > 0) {
            $stmt->bindValue(':commande_id', $commande_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':commande_id', null, PDO::PARAM_NULL);
        }
        if ($bl_id > 0) {
            $stmt->bindValue(':bl_id', $bl_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':bl_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':type', $type === 'client' ? 'client' : 'admin', PDO::PARAM_STR);
        $stmt->bindValue(':admin_id', $admin_id !== null ? (int) $admin_id : null, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id !== null ? (int) $user_id : null, PDO::PARAM_INT);
        $stmt->bindValue(':ttl', $ttl, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + ($ttl * 60)),
            'commande_id' => $commande_id > 0 ? $commande_id : null,
            'bl_id' => $bl_id > 0 ? $bl_id : null,
        ];
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_watch_token_row($token, $commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_tracking_tables_ready() || trim((string) $token) === '') {
        return false;
    }

    $hash = livreur_hash_token($token);

    try {
        if ($bl_id !== null && (int) $bl_id > 0 && livreur_bl_livraison_columns_ok()) {
            $row = livreur_merge_watch_token_with_facture($hash, (int) $bl_id);
            if ($row) {
                return $row;
            }
        }

        if ($commande_id !== null && (int) $commande_id > 0) {
            $sql = "
                SELECT w.*, c.numero_commande, c.livreur_id, c.tracking_active,
                       c.delivery_latitude, c.delivery_longitude, c.adresse_livraison,
                       a.nom AS livreur_nom, a.prenom AS livreur_prenom
                FROM tracking_watch_tokens w
                INNER JOIN commandes c ON c.id = :commande_id
                LEFT JOIN admin a ON a.id = c.livreur_id AND a.role IN ('livreur', 'admin')
                WHERE w.token_hash = :hash
                  AND w.expires_at > NOW()
                  AND (w.commande_id IS NULL OR w.commande_id = :commande_id)
                LIMIT 1
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'hash' => $hash,
                'commande_id' => (int) $commande_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['livraison_type'] = 'commande';
                return $row;
            }
        }

        $sql = "
            SELECT w.*, c.numero_commande, c.livreur_id, c.tracking_active,
                   c.delivery_latitude, c.delivery_longitude, c.adresse_livraison,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom
            FROM tracking_watch_tokens w
            INNER JOIN commandes c ON c.id = w.commande_id
            LEFT JOIN admin a ON a.id = c.livreur_id AND a.role IN ('livreur', 'admin')
            WHERE w.token_hash = :hash
              AND w.expires_at > NOW()
        ";
        $params = ['hash' => $hash];
        if ($commande_id !== null && (int) $commande_id > 0) {
            $sql .= " AND w.commande_id = :commande_id";
            $params['commande_id'] = (int) $commande_id;
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['livraison_type'] = 'commande';
        }
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Colonne bl_id sur tracking_watch_tokens (migration livreurs).
 */
function livreur_watch_token_has_bl_column() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT bl_id FROM tracking_watch_tokens LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Valide un token watch pour une facture (BL) sans JOIN fragile token↔client B2B.
 *
 * @return array<string, mixed>|false
 */
function livreur_merge_watch_token_with_facture($token_hash, $bl_id) {
    global $db;

    $bl_id = (int) $bl_id;
    if ($bl_id < 1) {
        return false;
    }

    $facture = livreur_get_facture_tracking($bl_id);
    if (!$facture) {
        return false;
    }

    try {
        $has_bl_col = livreur_watch_token_has_bl_column();
        if ($has_bl_col) {
            $stmt = $db->prepare("
                SELECT *
                FROM tracking_watch_tokens
                WHERE token_hash = :hash
                  AND expires_at > NOW()
                  AND (bl_id IS NULL OR bl_id = :bl_id)
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([
                'hash' => $token_hash,
                'bl_id' => $bl_id,
            ]);
        } else {
            $stmt = $db->prepare("
                SELECT *
                FROM tracking_watch_tokens
                WHERE token_hash = :hash
                  AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute(['hash' => $token_hash]);
        }
        $token_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token_row) {
            return false;
        }

        return array_merge($token_row, [
            'numero_bl' => $facture['numero_bl'] ?? '',
            'livreur_id' => $facture['livreur_id'] ?? null,
            'tracking_active' => $facture['tracking_active'] ?? 0,
            'delivery_latitude' => $facture['delivery_latitude'] ?? null,
            'delivery_longitude' => $facture['delivery_longitude'] ?? null,
            'adresse_livraison' => $facture['adresse_livraison'] ?? '',
            'livreur_nom' => $facture['livreur_nom'] ?? '',
            'livreur_prenom' => $facture['livreur_prenom'] ?? '',
            'delivery_countdown_initial_sec' => $facture['delivery_countdown_initial_sec'] ?? null,
            'delivery_countdown_remaining_sec' => $facture['delivery_countdown_remaining_sec'] ?? null,
            'delivery_countdown_running_at' => $facture['delivery_countdown_running_at'] ?? null,
            'livraison_type' => 'facture',
            'bl_id' => $bl_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_commande_tracking($commande_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT c.*,
                   COALESCE(u.prenom, c.client_prenom) AS client_prenom,
                   COALESCE(u.nom, c.client_nom) AS client_nom,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) AS client_telephone,
                   a.nom AS livreur_nom,
                   a.prenom AS livreur_prenom,
                   a.email AS livreur_email
                   " . livreur_admin_photo_profil_sql_select('a') . "
            FROM commandes c
            LEFT JOIN users u ON u.id = c.user_id
            LEFT JOIN admin a ON a.id = c.livreur_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int) $commande_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Commandes du jour éligibles à la livraison (table admin pour le livreur assigné).
 */
function livreur_get_commandes_du_jour() {
    return livreur_get_commandes_livraison_list(true);
}

/**
 * Liste des commandes à livrer (filtrage période côté interface admin).
 *
 * @param bool $only_today Si true, limite aux commandes du jour (vue livreur).
 */
function livreur_get_commandes_livraison_list($only_today = false) {
    global $db;
    try {
        $sql = "
            SELECT c.id, c.numero_commande, c.statut, c.adresse_livraison,
                   c.livreur_id, c.tracking_active, c.date_commande, c.montant_total,
                   c.delivery_latitude, c.delivery_longitude,
                   c.telephone_livraison, c.client_nom, c.client_prenom, c.client_telephone,
                   COALESCE(u.prenom, c.client_prenom) AS user_prenom,
                   COALESCE(u.nom, c.client_nom) AS user_nom,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) AS user_telephone,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom
            FROM commandes c
            LEFT JOIN users u ON u.id = c.user_id
            LEFT JOIN admin a ON a.id = c.livreur_id AND a.role IN ('livreur', 'admin')
            WHERE c.statut NOT IN ('livree', 'paye', 'annulee')
        ";
        if ($only_today) {
            $sql .= " AND DATE(c.date_commande) = CURDATE()";
        }
        $sql .= " ORDER BY c.date_commande DESC";

        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Colonnes livraison GPS sur bons_livraison (migration livreurs).
 */
function livreur_bl_livraison_columns_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT livreur_id, tracking_active, delivery_latitude, delivery_longitude FROM bons_livraison LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Factures B2B (bons de livraison) — même source que l'onglet Facture du hub Invoice.
 *
 * @param bool $only_today Si true, limite aux factures du jour (vue livreur).
 * @return list<array<string, mixed>>
 */
function livreur_get_factures_livraison_list($only_today = false) {
    require_once __DIR__ . '/model_bl.php';
    if (!bl_tables_available()) {
        return [];
    }
    global $db;
    try {
        $join_admin = livreur_bl_livraison_columns_ok()
            ? 'LEFT JOIN admin a ON a.id = b.livreur_id AND a.role IN (\'livreur\', \'admin\')'
            : '';
        $admin_cols = livreur_bl_livraison_columns_ok()
            ? ', a.nom AS livreur_nom, a.prenom AS livreur_prenom'
            : '';
        $stmt = $db->query('
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   c.adresse AS client_adresse,
                   b.statut AS bl_statut
                   ' . $admin_cols . '
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON b.client_b2b_id = c.id
            ' . $join_admin . '
            ORDER BY b.date_creation DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        if (!$only_today) {
            return $rows;
        }
        $today = date('Y-m-d');
        return array_values(array_filter($rows, function ($f) use ($today) {
            $date_source = !empty($f['date_bl']) ? $f['date_bl'] : ($f['date_creation'] ?? '');
            if ($date_source === '') {
                return false;
            }
            return date('Y-m-d', strtotime($date_source)) === $today;
        }));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fragment SQL photo profil livreur (table admin).
 */
function livreur_admin_photo_profil_sql_select($alias = 'a') {
    require_once __DIR__ . '/model_admin.php';
    if (admin_has_column('photo_profil')) {
        return ', ' . $alias . '.photo_profil AS livreur_photo_profil';
    }
    return ', NULL AS livreur_photo_profil';
}

/**
 * URL publique photo profil livreur depuis une ligne tracking.
 */
function livreur_photo_url_from_row(array $row) {
    require_once __DIR__ . '/model_admin.php';
    $url = admin_photo_profil_url((string) ($row['livreur_photo_profil'] ?? ''));
    if ($url !== '') {
        return $url;
    }
    $livreur_id = (int) ($row['livreur_id'] ?? 0);
    if ($livreur_id > 0) {
        $admin = get_admin_by_id($livreur_id);
        if (is_array($admin)) {
            return admin_photo_profil_url((string) ($admin['photo_profil'] ?? ''));
        }
    }
    return '';
}

/**
 * Photo + initiales pour une livraison (facture ou commande).
 *
 * @return array{photo_url: string, initials: string}
 */
function livreur_photo_profile_for_livraison(array $row) {
    $photo_url = livreur_photo_url_from_row($row);
    $initials = livreur_initials_from_row($row);
    if (($row['livreur_prenom'] ?? '') === '' && ($row['livreur_nom'] ?? '') === '') {
        $livreur_id = (int) ($row['livreur_id'] ?? 0);
        if ($livreur_id > 0) {
            require_once __DIR__ . '/model_admin.php';
            $admin = get_admin_by_id($livreur_id);
            if (is_array($admin)) {
                if ($photo_url === '') {
                    $photo_url = admin_photo_profil_url((string) ($admin['photo_profil'] ?? ''));
                }
                $initials = livreur_initials_from_row([
                    'livreur_prenom' => $admin['prenom'] ?? '',
                    'livreur_nom' => $admin['nom'] ?? '',
                ]);
            }
        }
    }
    return [
        'photo_url' => $photo_url,
        'initials' => $initials,
    ];
}

/**
 * Initiales affichées si pas de photo profil.
 */
function livreur_initials_from_row(array $row) {
    $prenom = trim((string) ($row['livreur_prenom'] ?? $row['prenom'] ?? ''));
    $nom = trim((string) ($row['livreur_nom'] ?? $row['nom'] ?? ''));
    if ($prenom !== '') {
        return mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8');
    }
    if ($nom !== '') {
        return mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return 'L';
}

/**
 * Détail facture B2B pour suivi GPS.
 */
function livreur_get_facture_tracking($bl_id) {
    global $db;
    if (!livreur_bl_livraison_columns_ok()) {
        return false;
    }
    require_once __DIR__ . '/model_bl.php';
    if (!bl_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   c.adresse AS client_adresse,
                   COALESCE(b.adresse_livraison, b.adresse_client, c.adresse) AS adresse_livraison,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom, a.email AS livreur_email
                   " . livreur_admin_photo_profil_sql_select('a') . "
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON b.client_b2b_id = c.id
            LEFT JOIN admin a ON a.id = b.livreur_id
            WHERE b.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int) $bl_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $row = bl_row_apply_statut_bl($row);
        $row['client_nom'] = trim((string) ($row['raison_sociale'] ?? ''));
        $row['client_prenom'] = '';
        $row['livraison_type'] = 'facture';
        return $row;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Libellé statut livraison facture (aligné commandes).
 */
function livreur_facture_statut_livraison($facture) {
    if (!empty($facture['tracking_active'])) {
        return 'En livraison';
    }
    if (!empty($facture['livreur_id'])) {
        return 'Prise en charge';
    }
    return 'Disponible';
}

/**
 * Démarre une livraison facture B2B : prise en charge + coords + suivi actif.
 */
function livreur_commencer_livraison_facture($bl_id, $admin_livreur_id, array $coords, $require_today = true) {
    global $db;

    if (!livreur_bl_livraison_columns_ok()) {
        return ['ok' => false, 'error' => 'Module factures livraison non installé. Exécutez la migration livreurs.'];
    }

    $bl_id = (int) $bl_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($bl_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    $driver_lat = livreur_parse_coord($coords['driver_lat'] ?? null);
    $driver_lng = livreur_parse_coord($coords['driver_lng'] ?? null);
    $delivery_lat = livreur_parse_coord($coords['delivery_lat'] ?? null);
    $delivery_lng = livreur_parse_coord($coords['delivery_lng'] ?? null);
    $adresse = trim((string) ($coords['adresse_livraison'] ?? ''));

    if ($driver_lat === null || $driver_lng === null) {
        return ['ok' => false, 'error' => 'Position du livreur requise. Autorisez la géolocalisation.'];
    }
    if ($delivery_lat === null || $delivery_lng === null) {
        return ['ok' => false, 'error' => 'Adresse client introuvable sur la carte. Vérifiez l\'adresse.'];
    }
    if ($adresse === '') {
        return ['ok' => false, 'error' => 'L\'adresse de livraison est obligatoire.'];
    }

    try {
        $sql = "
            SELECT b.id, b.livreur_id, b.numero_bl, b.date_bl, b.date_creation
            FROM bons_livraison b
            WHERE b.id = :id
        ";
        if ($require_today) {
            $sql .= " AND DATE(COALESCE(b.date_bl, b.date_creation)) = CURDATE()";
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $bl_id]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$facture) {
            return ['ok' => false, 'error' => 'Facture introuvable' . ($require_today ? ' ou pas du jour' : '') . '.'];
        }

        $current_livreur = $facture['livreur_id'] !== null ? (int) $facture['livreur_id'] : null;
        if ($current_livreur !== null && $current_livreur !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette facture a déjà été prise par un autre livreur.'];
        }

        $stmt_admin = $db->prepare("SELECT id, role, statut FROM admin WHERE id = :id LIMIT 1");
        $stmt_admin->execute(['id' => $admin_livreur_id]);
        $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        if (!$admin_row || !in_array($admin_row['role'] ?? '', ['livreur', 'admin'], true) || ($admin_row['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Compte livreur invalide.'];
        }

        $db->beginTransaction();

        $upd = $db->prepare("
            UPDATE bons_livraison
            SET livreur_id = :livreur_id,
                delivery_latitude = :delivery_lat,
                delivery_longitude = :delivery_lng,
                adresse_livraison = :adresse,
                tracking_active = 0,
                tracking_started_at = NULL
            WHERE id = :id
              AND (livreur_id IS NULL OR livreur_id = :livreur_id2)
        ");
        $upd->execute([
            'livreur_id' => $admin_livreur_id,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
            'adresse' => $adresse,
            'id' => $bl_id,
            'livreur_id2' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de démarrer cette livraison.'];
        }

        $db->commit();

        return [
            'ok' => true,
            'message' => 'Livraison démarrée pour la facture ' . ($facture['numero_bl'] ?? '') . '.',
            'bl_id' => $bl_id,
            'driver_lat' => $driver_lat,
            'driver_lng' => $driver_lng,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Erreur lors du démarrage de la livraison facture.'];
    }
}

/**
 * Adresse affichée pour une facture B2B (BL).
 */
function livreur_facture_adresse_affichage($facture) {
    $adresse_bl = trim((string) ($facture['adresse_client'] ?? ''));
    if ($adresse_bl !== '') {
        return $adresse_bl;
    }
    return trim((string) ($facture['client_adresse'] ?? ''));
}

/**
 * Chaîne de recherche pour une facture B2B.
 */
function livreur_facture_search_blob($facture) {
    $client_label = trim((string) ($facture['raison_sociale'] ?? ''));
    $telephone = trim((string) ($facture['client_telephone'] ?? ''));
    $parts = [$client_label, $telephone];
    $parts = array_filter($parts, function ($p) {
        return $p !== '';
    });
    $blob = implode(' ', $parts);
    return function_exists('mb_strtolower')
        ? mb_strtolower($blob, 'UTF-8')
        : strtolower($blob);
}

function livreur_commande_search_blob($cmd) {
    $client_nom = trim((string) ($cmd['user_nom'] ?? $cmd['client_nom'] ?? ''));
    $client_prenom = trim((string) ($cmd['user_prenom'] ?? $cmd['client_prenom'] ?? ''));
    $telephone = trim((string) ($cmd['user_telephone'] ?? $cmd['client_telephone'] ?? $cmd['telephone_livraison'] ?? ''));
    $parts = [
        $cmd['numero_commande'] ?? '',
        $client_prenom,
        $client_nom,
        trim($client_prenom . ' ' . $client_nom),
        $telephone,
        $cmd['adresse_livraison'] ?? '',
        livreur_statut_label($cmd['statut'] ?? ''),
    ];
    $parts = array_filter($parts, function ($p) {
        return $p !== '';
    });
    return function_exists('mb_strtolower')
        ? mb_strtolower(implode(' ', $parts), 'UTF-8')
        : strtolower(implode(' ', $parts));
}

function livreur_get_commandes_trackables() {
    return livreur_get_commandes_livraison_list(false);
}

/**
 * Un compte admin (rôle livreur) prend une commande.
 *
 * @param bool $require_today Si true, limite aux commandes du jour (vue livreur).
 */
function livreur_prendre_commande($commande_id, $admin_livreur_id, $require_today = true) {
    global $db;

    $commande_id = (int) $commande_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($commande_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    try {
        $sql = "
            SELECT id, livreur_id, statut, numero_commande
            FROM commandes
            WHERE id = :id
        ";
        if ($require_today) {
            $sql .= " AND DATE(date_commande) = CURDATE()";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            return ['ok' => false, 'error' => $require_today ? 'Commande introuvable ou pas du jour.' : 'Commande introuvable.'];
        }

        if (in_array($commande['statut'] ?? '', ['livree', 'paye', 'annulee'], true)) {
            return ['ok' => false, 'error' => 'Cette commande n\'est plus disponible.'];
        }

        $current_livreur = $commande['livreur_id'] !== null ? (int) $commande['livreur_id'] : null;
        if ($current_livreur !== null && $current_livreur !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette commande a déjà été prise par un autre livreur.'];
        }
        if ($current_livreur === $admin_livreur_id) {
            return ['ok' => true, 'message' => 'Vous avez déjà pris cette commande.', 'already' => true, 'commande_id' => $commande_id];
        }

        $stmt_admin = $db->prepare("SELECT id, role, statut FROM admin WHERE id = :id LIMIT 1");
        $stmt_admin->execute(['id' => $admin_livreur_id]);
        $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        if (!$admin_row || !in_array($admin_row['role'] ?? '', ['livreur', 'admin'], true) || ($admin_row['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Compte livreur invalide.'];
        }

        $db->beginTransaction();

        $upd = $db->prepare("
            UPDATE commandes
            SET livreur_id = :livreur_id
            WHERE id = :id AND (livreur_id IS NULL OR livreur_id = :livreur_id2)
        ");
        $upd->execute([
            'livreur_id' => $admin_livreur_id,
            'id' => $commande_id,
            'livreur_id2' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de prendre cette commande.'];
        }

        $db->commit();

        require_once __DIR__ . '/model_commandes_admin.php';
        $statuts_avant_livraison = ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'expediee'];
        if (in_array($commande['statut'] ?? '', $statuts_avant_livraison, true)) {
            update_commande_statut($commande_id, 'livraison_en_cours');
        }

        return [
            'ok' => true,
            'message' => 'Commande ' . ($commande['numero_commande'] ?? '') . ' prise en charge.',
            'commande_id' => $commande_id,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Erreur lors de la prise de commande.'];
    }
}

/**
 * Un compte admin (rôle livreur) prend une facture B2B.
 *
 * @param bool $require_today Si true, limite aux factures du jour (vue livreur).
 * @return array{ok:bool,error?:string,message?:string,bl_id?:int,already?:bool}
 */
function livreur_prendre_facture($bl_id, $admin_livreur_id, $require_today = true) {
    global $db;

    if (!livreur_bl_livraison_columns_ok()) {
        return ['ok' => false, 'error' => 'Module factures livraison non installé.'];
    }

    $bl_id = (int) $bl_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($bl_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    try {
        $sql = "
            SELECT b.id, b.livreur_id, b.numero_bl, b.date_bl, b.date_creation
            FROM bons_livraison b
            WHERE b.id = :id
        ";
        if ($require_today) {
            $sql .= " AND DATE(COALESCE(b.date_bl, b.date_creation)) = CURDATE()";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $bl_id]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$facture) {
            return ['ok' => false, 'error' => $require_today ? 'Facture introuvable ou pas du jour.' : 'Facture introuvable.'];
        }

        $current_livreur = $facture['livreur_id'] !== null ? (int) $facture['livreur_id'] : null;
        if ($current_livreur !== null && $current_livreur !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette facture a déjà été prise par un autre livreur.'];
        }
        if ($current_livreur === $admin_livreur_id) {
            return ['ok' => true, 'message' => 'Vous avez déjà pris cette facture.', 'already' => true, 'bl_id' => $bl_id];
        }

        $stmt_admin = $db->prepare("SELECT id, role, statut FROM admin WHERE id = :id LIMIT 1");
        $stmt_admin->execute(['id' => $admin_livreur_id]);
        $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        if (!$admin_row || !in_array($admin_row['role'] ?? '', ['livreur', 'admin'], true) || ($admin_row['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Compte livreur invalide.'];
        }

        $upd = $db->prepare("
            UPDATE bons_livraison
            SET livreur_id = :livreur_id
            WHERE id = :id AND (livreur_id IS NULL OR livreur_id = :livreur_id2)
        ");
        $upd->execute([
            'livreur_id' => $admin_livreur_id,
            'id' => $bl_id,
            'livreur_id2' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            return ['ok' => false, 'error' => 'Impossible de prendre cette facture.'];
        }

        return [
            'ok' => true,
            'message' => 'Facture ' . ($facture['numero_bl'] ?? '') . ' prise en charge.',
            'bl_id' => $bl_id,
        ];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Erreur lors de la prise de la facture.'];
    }
}

/**
 * Annule une prise en charge commande (avant démarrage GPS).
 *
 * @return array{ok:bool,error?:string,message?:string,commande_id?:int}
 */
function livreur_abandonner_prise_commande($commande_id, $admin_livreur_id) {
    global $db;

    $commande_id = (int) $commande_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($commande_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    try {
        $stmt = $db->prepare("
            SELECT id, livreur_id, statut, tracking_active, numero_commande
            FROM commandes
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            return ['ok' => false, 'error' => 'Commande introuvable.'];
        }
        if ((int) ($commande['livreur_id'] ?? 0) !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette commande ne vous est pas assignée.'];
        }
        if ((int) ($commande['tracking_active'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'La livraison est déjà démarrée.'];
        }

        $db->beginTransaction();

        $upd = $db->prepare("
            UPDATE commandes
            SET livreur_id = NULL
            WHERE id = :id AND livreur_id = :livreur_id AND (tracking_active IS NULL OR tracking_active = 0)
        ");
        $upd->execute([
            'id' => $commande_id,
            'livreur_id' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible d\'annuler cette prise en charge.'];
        }

        $db->commit();

        if (($commande['statut'] ?? '') === 'livraison_en_cours') {
            require_once __DIR__ . '/model_commandes_admin.php';
            update_commande_statut($commande_id, 'confirmee');
        }

        return [
            'ok' => true,
            'message' => 'Prise en charge annulée.',
            'commande_id' => $commande_id,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Erreur lors de l\'annulation.'];
    }
}

/**
 * Annule une prise en charge facture (avant démarrage GPS).
 *
 * @return array{ok:bool,error?:string,message?:string,bl_id?:int}
 */
function livreur_abandonner_prise_facture($bl_id, $admin_livreur_id) {
    global $db;

    if (!livreur_bl_livraison_columns_ok()) {
        return ['ok' => false, 'error' => 'Module factures livraison non installé.'];
    }

    $bl_id = (int) $bl_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($bl_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    try {
        $stmt = $db->prepare("
            SELECT id, livreur_id, tracking_active, numero_bl
            FROM bons_livraison
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $bl_id]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$facture) {
            return ['ok' => false, 'error' => 'Facture introuvable.'];
        }
        if ((int) ($facture['livreur_id'] ?? 0) !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette facture ne vous est pas assignée.'];
        }
        if ((int) ($facture['tracking_active'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'La livraison est déjà démarrée.'];
        }

        $upd = $db->prepare("
            UPDATE bons_livraison
            SET livreur_id = NULL
            WHERE id = :id AND livreur_id = :livreur_id AND (tracking_active IS NULL OR tracking_active = 0)
        ");
        $upd->execute([
            'id' => $bl_id,
            'livreur_id' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            return ['ok' => false, 'error' => 'Impossible d\'annuler cette prise en charge.'];
        }

        return [
            'ok' => true,
            'message' => 'Prise en charge annulée.',
            'bl_id' => $bl_id,
        ];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Erreur lors de l\'annulation.'];
    }
}

/**
 * Démarre une livraison : prise en charge + coords départ/arrivée + suivi actif.
 *
 * @param array $coords driver_lat, driver_lng, delivery_lat, delivery_lng, adresse_livraison, driver_precision (opt.)
 */
function livreur_commencer_livraison($commande_id, $admin_livreur_id, array $coords, $require_today = true) {
    global $db;

    $commande_id = (int) $commande_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($commande_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    $driver_lat = livreur_parse_coord($coords['driver_lat'] ?? null);
    $driver_lng = livreur_parse_coord($coords['driver_lng'] ?? null);
    $delivery_lat = livreur_parse_coord($coords['delivery_lat'] ?? null);
    $delivery_lng = livreur_parse_coord($coords['delivery_lng'] ?? null);
    $adresse = trim((string) ($coords['adresse_livraison'] ?? ''));

    if ($driver_lat === null || $driver_lng === null) {
        return ['ok' => false, 'error' => 'Position du livreur requise. Autorisez la géolocalisation.'];
    }
    if ($delivery_lat === null || $delivery_lng === null) {
        return ['ok' => false, 'error' => 'Adresse client introuvable sur la carte. Vérifiez l\'adresse.'];
    }
    if ($adresse === '') {
        return ['ok' => false, 'error' => 'L\'adresse de livraison est obligatoire.'];
    }

    try {
        $sql = "
            SELECT id, livreur_id, statut, numero_commande
            FROM commandes
            WHERE id = :id
        ";
        if ($require_today) {
            $sql .= " AND DATE(date_commande) = CURDATE()";
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            return ['ok' => false, 'error' => 'Commande introuvable' . ($require_today ? ' ou pas du jour' : '') . '.'];
        }

        if (in_array($commande['statut'] ?? '', ['livree', 'paye', 'annulee'], true)) {
            return ['ok' => false, 'error' => 'Cette commande n\'est plus disponible.'];
        }

        $current_livreur = $commande['livreur_id'] !== null ? (int) $commande['livreur_id'] : null;
        if ($current_livreur !== null && $current_livreur !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette commande a déjà été prise par un autre livreur.'];
        }

        $stmt_admin = $db->prepare("SELECT id, role, statut FROM admin WHERE id = :id LIMIT 1");
        $stmt_admin->execute(['id' => $admin_livreur_id]);
        $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        if (!$admin_row || !in_array($admin_row['role'] ?? '', ['livreur', 'admin'], true) || ($admin_row['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Compte livreur invalide.'];
        }

        $db->beginTransaction();

        $upd = $db->prepare("
            UPDATE commandes
            SET livreur_id = :livreur_id,
                delivery_latitude = :delivery_lat,
                delivery_longitude = :delivery_lng,
                adresse_livraison = :adresse,
                tracking_active = 0,
                tracking_started_at = NULL
            WHERE id = :id
              AND (livreur_id IS NULL OR livreur_id = :livreur_id2)
        ");
        $upd->execute([
            'livreur_id' => $admin_livreur_id,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
            'adresse' => $adresse,
            'id' => $commande_id,
            'livreur_id2' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de démarrer cette livraison.'];
        }

        $db->commit();

        require_once __DIR__ . '/model_commandes_admin.php';
        $statuts_avant_livraison = ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'expediee'];
        if (in_array($commande['statut'] ?? '', $statuts_avant_livraison, true)) {
            update_commande_statut($commande_id, 'livraison_en_cours');
        }

        return [
            'ok' => true,
            'message' => 'Livraison démarrée pour la commande ' . ($commande['numero_commande'] ?? '') . '.',
            'commande_id' => $commande_id,
            'driver_lat' => $driver_lat,
            'driver_lng' => $driver_lng,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Erreur lors du démarrage de la livraison.'];
    }
}

function livreur_assign_commande($commande_id, $livreur_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            UPDATE commandes
            SET livreur_id = :livreur_id
            WHERE id = :commande_id
        ");
        return $stmt->execute([
            'livreur_id' => (int) $livreur_id,
            'commande_id' => (int) $commande_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_start_tracking($commande_id, $livreur_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            UPDATE commandes
            SET livreur_id = :livreur_id,
                tracking_active = 1,
                tracking_started_at = NOW()
            WHERE id = :commande_id
        ");
        return $stmt->execute([
            'livreur_id' => (int) $livreur_id,
            'commande_id' => (int) $commande_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_web_can_manage_livraison($admin_id, $commande_id = null, $bl_id = null) {
    $admin_id = (int) $admin_id;
    if ($admin_id < 1) {
        return false;
    }
    if ($commande_id !== null && (int) $commande_id > 0) {
        $row = livreur_get_commande_tracking((int) $commande_id);
        return $row && (int) ($row['livreur_id'] ?? 0) === $admin_id;
    }
    if ($bl_id !== null && (int) $bl_id > 0) {
        $row = livreur_get_facture_tracking((int) $bl_id);
        return $row && (int) ($row['livreur_id'] ?? 0) === $admin_id;
    }
    return false;
}

/**
 * Colonnes compte à rebours livraison (migration delivery_countdown).
 */
function livreur_countdown_columns_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT delivery_countdown_remaining_sec, delivery_countdown_running_at FROM commandes LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return array{table:string,id:int}|null
 */
function livreur_countdown_resolve_target($commande_id = null, $bl_id = null) {
    if ($bl_id !== null && (int) $bl_id > 0 && livreur_bl_livraison_columns_ok()) {
        return ['table' => 'bons_livraison', 'id' => (int) $bl_id];
    }
    if ($commande_id !== null && (int) $commande_id > 0) {
        return ['table' => 'commandes', 'id' => (int) $commande_id];
    }
    return null;
}

/**
 * @return array<string, mixed>|false
 */
function livreur_countdown_fetch_row($commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_countdown_columns_ok()) {
        return false;
    }
    $target = livreur_countdown_resolve_target($commande_id, $bl_id);
    if (!$target) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT tracking_active, delivery_countdown_initial_sec,
                   delivery_countdown_remaining_sec, delivery_countdown_running_at
            FROM `' . $target['table'] . '`
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $target['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * État public du compte à rebours (running | late | paused | late_paused).
 *
 * @param array<string, mixed>|false|null $row
 * @return array<string, mixed>|null
 */
function livreur_countdown_state_from_row($row) {
    if (!is_array($row) || !livreur_countdown_columns_ok()) {
        return null;
    }
    if ($row['delivery_countdown_remaining_sec'] === null) {
        return null;
    }

    $remaining_stored = (int) $row['delivery_countdown_remaining_sec'];
    $initial = (int) ($row['delivery_countdown_initial_sec'] ?? $remaining_stored);
    $tracking_active = (int) ($row['tracking_active'] ?? 0) === 1;
    $running_at = $row['delivery_countdown_running_at'] ?? null;

    if ($tracking_active && !empty($running_at)) {
        $elapsed = max(0, time() - strtotime((string) $running_at));
        $current = $remaining_stored - $elapsed;
        return [
            'status' => $current > 0 ? 'running' : 'late',
            'remaining_sec' => $current,
            'initial_sec' => $initial,
            'paused' => false,
            'server_time' => date('c'),
        ];
    }

    return [
        'status' => $remaining_stored <= 0 ? 'late_paused' : 'paused',
        'remaining_sec' => $remaining_stored,
        'initial_sec' => $initial,
        'paused' => true,
        'server_time' => date('c'),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function livreur_countdown_state_for_livraison($commande_id = null, $bl_id = null) {
    $row = livreur_countdown_fetch_row($commande_id, $bl_id);
    if (!$row) {
        return null;
    }
    return livreur_countdown_state_from_row($row);
}

/**
 * Durée cible (s) à partir de la durée d'itinéraire — alignée sur la fourchette ETA max.
 */
function livreur_countdown_seconds_from_route_duration($duration_seconds) {
    $duration_seconds = (int) $duration_seconds;
    if ($duration_seconds < 1) {
        return 0;
    }
    $base_min = max(1, $duration_seconds / 60);
    $min_min = max(5, (int) floor(($base_min * 0.82) / 5) * 5);
    $max_min = max($min_min + 10, (int) ceil(($base_min * 1.28) / 5) * 5);
    if ($max_min - $min_min > 25) {
        $max_min = $min_min + 25;
    }
    return max(60, $max_min * 60);
}

function livreur_countdown_pause_row($commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_countdown_columns_ok()) {
        return false;
    }
    $row = livreur_countdown_fetch_row($commande_id, $bl_id);
    if (!$row || $row['delivery_countdown_remaining_sec'] === null) {
        return true;
    }
    $state = livreur_countdown_state_from_row($row);
    if (!$state) {
        return false;
    }
    $target = livreur_countdown_resolve_target($commande_id, $bl_id);
    if (!$target) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE `' . $target['table'] . '`
            SET delivery_countdown_remaining_sec = :remaining,
                delivery_countdown_running_at = NULL
            WHERE id = :id
        ');
        return $stmt->execute([
            'remaining' => (int) $state['remaining_sec'],
            'id' => $target['id'],
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_countdown_resume($commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_countdown_columns_ok()) {
        return false;
    }
    $row = livreur_countdown_fetch_row($commande_id, $bl_id);
    if (!$row || $row['delivery_countdown_remaining_sec'] === null) {
        return true;
    }
    $target = livreur_countdown_resolve_target($commande_id, $bl_id);
    if (!$target) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE `' . $target['table'] . '`
            SET delivery_countdown_running_at = NOW()
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $target['id']]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_countdown_clear($commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_countdown_columns_ok()) {
        return false;
    }
    $target = livreur_countdown_resolve_target($commande_id, $bl_id);
    if (!$target) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE `' . $target['table'] . '`
            SET delivery_countdown_initial_sec = NULL,
                delivery_countdown_remaining_sec = NULL,
                delivery_countdown_running_at = NULL
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $target['id']]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Initialise le compte à rebours au premier calcul d'itinéraire.
 *
 * @return array{ok:bool,error?:string,countdown?:array<string,mixed>|null}
 */
function livreur_countdown_init_from_duration($admin_id, $commande_id, $bl_id, $duration_seconds) {
    global $db;
    if (!livreur_web_can_manage_livraison($admin_id, $commande_id, $bl_id)) {
        return ['ok' => false, 'error' => 'Accès refusé à cette livraison.'];
    }
    if (!livreur_countdown_columns_ok()) {
        return ['ok' => false, 'error' => 'Compte à rebours non disponible.'];
    }
    $seconds = livreur_countdown_seconds_from_route_duration($duration_seconds);
    if ($seconds < 1) {
        return ['ok' => false, 'error' => 'Durée invalide.'];
    }
    $target = livreur_countdown_resolve_target($commande_id, $bl_id);
    if (!$target) {
        return ['ok' => false, 'error' => 'Livraison introuvable.'];
    }
    try {
        $stmt = $db->prepare('
            SELECT delivery_countdown_initial_sec, delivery_countdown_remaining_sec,
                   tracking_active, delivery_countdown_running_at
            FROM `' . $target['table'] . '`
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $target['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Livraison introuvable.'];
        }
        if ($row['delivery_countdown_initial_sec'] === null) {
            $upd = $db->prepare('
                UPDATE `' . $target['table'] . '`
                SET delivery_countdown_initial_sec = :initial,
                    delivery_countdown_remaining_sec = :remaining,
                    delivery_countdown_running_at = NOW()
                WHERE id = :id
            ');
            $upd->execute([
                'initial' => $seconds,
                'remaining' => $seconds,
                'id' => $target['id'],
            ]);
            if ((int) ($row['tracking_active'] ?? 0) !== 1) {
                livreur_countdown_pause_row($commande_id, $bl_id);
            }
        } elseif ((int) ($row['tracking_active'] ?? 0) === 1 && empty($row['delivery_countdown_running_at'])) {
            livreur_countdown_resume($commande_id, $bl_id);
        }
        $fresh = livreur_countdown_fetch_row($commande_id, $bl_id);
        return [
            'ok' => true,
            'countdown' => livreur_countdown_state_from_row($fresh),
        ];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Erreur compte à rebours.'];
    }
}

function livreur_countdown_pause_active_for_livreur($admin_id, $except_commande_id = null, $except_bl_id = null) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id < 1 || !livreur_countdown_columns_ok()) {
        return;
    }
    try {
        if ($except_commande_id !== null && (int) $except_commande_id > 0) {
            $stmt = $db->prepare('
                SELECT id FROM commandes
                WHERE livreur_id = :livreur_id AND tracking_active = 1 AND id != :except_id
            ');
            $stmt->execute(['livreur_id' => $admin_id, 'except_id' => (int) $except_commande_id]);
        } else {
            $stmt = $db->prepare('
                SELECT id FROM commandes
                WHERE livreur_id = :livreur_id AND tracking_active = 1
            ');
            $stmt->execute(['livreur_id' => $admin_id]);
        }
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            livreur_countdown_pause_row((int) $cid, null);
        }

        if (livreur_bl_livraison_columns_ok()) {
            if ($except_bl_id !== null && (int) $except_bl_id > 0) {
                $stmtBl = $db->prepare('
                    SELECT id FROM bons_livraison
                    WHERE livreur_id = :livreur_id AND tracking_active = 1 AND id != :except_id
                ');
                $stmtBl->execute(['livreur_id' => $admin_id, 'except_id' => (int) $except_bl_id]);
            } else {
                $stmtBl = $db->prepare('
                    SELECT id FROM bons_livraison
                    WHERE livreur_id = :livreur_id AND tracking_active = 1
                ');
                $stmtBl->execute(['livreur_id' => $admin_id]);
            }
            foreach ($stmtBl->fetchAll(PDO::FETCH_COLUMN) as $bid) {
                livreur_countdown_pause_row(null, (int) $bid);
            }
        }
    } catch (PDOException $e) {
        /* silencieux */
    }
}

/**
 * @return array{ok:bool,error?:string,countdown?:array<string,mixed>|null}
 */
function livreur_start_web_tracking($admin_id, $commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_web_can_manage_livraison($admin_id, $commande_id, $bl_id)) {
        return ['ok' => false, 'error' => 'Accès refusé à cette livraison.'];
    }
    livreur_stop_other_active_web_trackings($admin_id, $commande_id, $bl_id);
    try {
        if ($bl_id !== null && (int) $bl_id > 0 && livreur_bl_livraison_columns_ok()) {
            $row = livreur_get_facture_tracking((int) $bl_id);
            if ($row && (int) ($row['tracking_active'] ?? 0) === 1) {
                livreur_countdown_resume(null, (int) $bl_id);
                return [
                    'ok' => true,
                    'countdown' => livreur_countdown_state_for_livraison(null, (int) $bl_id),
                ];
            }
            $stmt = $db->prepare('
                UPDATE bons_livraison
                SET tracking_active = 1,
                    tracking_started_at = COALESCE(tracking_started_at, NOW())
                WHERE id = :id AND livreur_id = :livreur_id
            ');
            $stmt->execute(['id' => (int) $bl_id, 'livreur_id' => (int) $admin_id]);
        } else {
            $row = livreur_get_commande_tracking((int) $commande_id);
            if ($row && (int) ($row['tracking_active'] ?? 0) === 1) {
                livreur_countdown_resume((int) $commande_id, null);
                return [
                    'ok' => true,
                    'countdown' => livreur_countdown_state_for_livraison((int) $commande_id, null),
                ];
            }
            $stmt = $db->prepare('
                UPDATE commandes
                SET tracking_active = 1,
                    tracking_started_at = COALESCE(tracking_started_at, NOW())
                WHERE id = :id AND livreur_id = :livreur_id
            ');
            $stmt->execute(['id' => (int) $commande_id, 'livreur_id' => (int) $admin_id]);
        }
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'error' => 'Impossible d\'activer le suivi GPS.'];
        }
        livreur_countdown_resume($commande_id, $bl_id);
        return [
            'ok' => true,
            'countdown' => livreur_countdown_state_for_livraison($commande_id, $bl_id),
        ];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Erreur lors de l\'activation du suivi.'];
    }
}

/**
 * Désactive le suivi GPS des autres livraisons du même livreur (changement de course).
 */
function livreur_stop_other_active_web_trackings($admin_id, $except_commande_id = null, $except_bl_id = null) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id < 1) {
        return false;
    }
    livreur_countdown_pause_active_for_livreur($admin_id, $except_commande_id, $except_bl_id);
    try {
        if ($except_commande_id !== null && (int) $except_commande_id > 0) {
            $stmt = $db->prepare('
                UPDATE commandes
                SET tracking_active = 0
                WHERE livreur_id = :livreur_id AND tracking_active = 1 AND id != :except_id
            ');
            $stmt->execute([
                'livreur_id' => $admin_id,
                'except_id' => (int) $except_commande_id,
            ]);
        } else {
            $stmt = $db->prepare('
                UPDATE commandes
                SET tracking_active = 0
                WHERE livreur_id = :livreur_id AND tracking_active = 1
            ');
            $stmt->execute(['livreur_id' => $admin_id]);
        }

        if (livreur_bl_livraison_columns_ok()) {
            if ($except_bl_id !== null && (int) $except_bl_id > 0) {
                $stmtBl = $db->prepare('
                    UPDATE bons_livraison
                    SET tracking_active = 0
                    WHERE livreur_id = :livreur_id AND tracking_active = 1 AND id != :except_id
                ');
                $stmtBl->execute([
                    'livreur_id' => $admin_id,
                    'except_id' => (int) $except_bl_id,
                ]);
            } else {
                $stmtBl = $db->prepare('
                    UPDATE bons_livraison
                    SET tracking_active = 0
                    WHERE livreur_id = :livreur_id AND tracking_active = 1
                ');
                $stmtBl->execute(['livreur_id' => $admin_id]);
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array{ok:bool,error?:string}
 */
function livreur_stop_web_tracking($admin_id, $commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_web_can_manage_livraison($admin_id, $commande_id, $bl_id)) {
        return ['ok' => false, 'error' => 'Accès refusé à cette livraison.'];
    }
    try {
        if ($bl_id !== null && (int) $bl_id > 0 && livreur_bl_livraison_columns_ok()) {
            $stmt = $db->prepare('
                UPDATE bons_livraison
                SET tracking_active = 0
                WHERE id = :id AND livreur_id = :livreur_id
            ');
            $stmt->execute(['id' => (int) $bl_id, 'livreur_id' => (int) $admin_id]);
        } else {
            $stmt = $db->prepare('
                UPDATE commandes
                SET tracking_active = 0
                WHERE id = :id AND livreur_id = :livreur_id
            ');
            $stmt->execute(['id' => (int) $commande_id, 'livreur_id' => (int) $admin_id]);
        }
        livreur_countdown_clear($commande_id, $bl_id);
        return ['ok' => true];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Erreur lors de l\'arrêt du suivi.'];
    }
}

/**
 * @return array{ok:bool,error?:string}
 */
function livreur_save_web_position($admin_id, $latitude, $longitude, $commande_id = null, $bl_id = null, $accuracy = null) {
    if (!livreur_web_can_manage_livraison($admin_id, $commande_id, $bl_id)) {
        return ['ok' => false, 'error' => 'Accès refusé.'];
    }
    if ($bl_id !== null && (int) $bl_id > 0) {
        $row = livreur_get_facture_tracking((int) $bl_id);
    } else {
        $row = livreur_get_commande_tracking((int) $commande_id);
    }
    if (!$row || (int) ($row['tracking_active'] ?? 0) !== 1) {
        return ['ok' => false, 'error' => 'Suivi GPS inactif.'];
    }
    $cmd_id = ($commande_id !== null && (int) $commande_id > 0) ? (int) $commande_id : null;
    $facture_id = ($bl_id !== null && (int) $bl_id > 0) ? (int) $bl_id : null;
    $ok = livreur_save_position(
        (int) $admin_id,
        $latitude,
        $longitude,
        $cmd_id,
        $accuracy,
        null,
        null,
        $facture_id
    );
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Enregistrement position impossible.'];
}

function livreur_stop_tracking($commande_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            UPDATE commandes
            SET tracking_active = 0
            WHERE id = :commande_id
        ");
        return $stmt->execute(['commande_id' => (int) $commande_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_save_position($livreur_id, $latitude, $longitude, $commande_id = null, $accuracy = null, $speed = null, $heading = null, $bl_id = null) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    $lat = (float) $latitude;
    $lng = (float) $longitude;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }

    $has_bl_col = livreur_bl_livraison_columns_ok();
    try {
        if ($has_bl_col) {
            $stmt = $db->prepare("
                INSERT INTO livreur_positions
                    (livreur_id, commande_id, bl_id, latitude, longitude, accuracy, speed, heading, recorded_at)
                VALUES
                    (:livreur_id, :commande_id, :bl_id, :latitude, :longitude, :accuracy, :speed, :heading, NOW())
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO livreur_positions
                    (livreur_id, commande_id, latitude, longitude, accuracy, speed, heading, recorded_at)
                VALUES
                    (:livreur_id, :commande_id, :latitude, :longitude, :accuracy, :speed, :heading, NOW())
            ");
        }
        $stmt->bindValue(':livreur_id', (int) $livreur_id, PDO::PARAM_INT);
        if ($commande_id !== null) {
            $stmt->bindValue(':commande_id', (int) $commande_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':commande_id', null, PDO::PARAM_NULL);
        }
        if ($has_bl_col) {
            if ($bl_id !== null) {
                $stmt->bindValue(':bl_id', (int) $bl_id, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':bl_id', null, PDO::PARAM_NULL);
            }
        }
        $stmt->bindValue(':latitude', $lat);
        $stmt->bindValue(':longitude', $lng);
        $stmt->bindValue(':accuracy', $accuracy !== null ? (float) $accuracy : null);
        $stmt->bindValue(':speed', $speed !== null ? (float) $speed : null);
        $stmt->bindValue(':heading', $heading !== null ? (float) $heading : null);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_last_position($livreur_id, $commande_id = null, $bl_id = null) {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return false;
    }

    try {
        $sql = "
            SELECT latitude, longitude, accuracy, speed, heading, recorded_at, commande_id, bl_id
            FROM livreur_positions
            WHERE livreur_id = :livreur_id
        ";
        $params = ['livreur_id' => (int) $livreur_id];
        if ($bl_id !== null && livreur_bl_livraison_columns_ok()) {
            $sql .= " AND bl_id = :bl_id";
            $params['bl_id'] = (int) $bl_id;
        } elseif ($commande_id !== null) {
            $sql .= " AND commande_id = :commande_id";
            $params['commande_id'] = (int) $commande_id;
        }
        $sql .= " ORDER BY recorded_at DESC LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        /* Positions parfois enregistrées sans bl_id/commande_id — repli sur la dernière position du livreur */
        if ($bl_id !== null || $commande_id !== null) {
            $fallback = $db->prepare("
                SELECT latitude, longitude, accuracy, speed, heading, recorded_at, commande_id, bl_id
                FROM livreur_positions
                WHERE livreur_id = :livreur_id
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
            $fallback->execute(['livreur_id' => (int) $livreur_id]);
            $row = $fallback->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_parse_coord($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $f = (float) $value;
    if (!is_finite($f)) {
        return null;
    }
    return $f;
}

/**
 * Livraison assignée au livreur et encore en cours (prise en charge ou GPS actif).
 *
 * @param 'commande'|'facture' $type
 * @param array<string, mixed> $row
 */
function livreur_mes_livraison_en_cours($type, array $row) {
    if ((int) ($row['tracking_active'] ?? 0) === 1) {
        return true;
    }
    if ($type === 'commande') {
        $statut = strtolower(trim((string) ($row['statut'] ?? '')));
        return !in_array($statut, ['livree', 'paye', 'annulee'], true);
    }
    $statut_bl = strtolower(trim((string) ($row['statut_bl'] ?? $row['statut'] ?? '')));
    return !in_array($statut_bl, ['livree', 'livré', 'livre', 'annulee', 'annulée', 'annule'], true);
}

/**
 * Livraisons (commandes + factures) assignées à un admin livreur.
 *
 * @return list<array<string, mixed>>
 */
function livreur_get_mes_livraisons_for_admin($admin_id, $only_today = true, $started_only = false) {
    $admin_id = (int) $admin_id;
    if ($admin_id < 1) {
        return [];
    }

    $items = [];

    foreach (livreur_get_commandes_livraison_list($only_today) as $cmd) {
        if ((int) ($cmd['livreur_id'] ?? 0) !== $admin_id) {
            continue;
        }
        $lat = livreur_parse_coord($cmd['delivery_latitude'] ?? null);
        $lng = livreur_parse_coord($cmd['delivery_longitude'] ?? null);
        $prenom = trim((string) ($cmd['user_prenom'] ?? $cmd['client_prenom'] ?? ''));
        $nom = trim((string) ($cmd['user_nom'] ?? $cmd['client_nom'] ?? ''));
        $client_nom = trim($prenom . ' ' . $nom);
        $tel = trim((string) ($cmd['user_telephone'] ?? $cmd['client_telephone'] ?? $cmd['telephone_livraison'] ?? ''));
        $tracking_active = (int) ($cmd['tracking_active'] ?? 0);
        $geo_ready = $lat !== null && $lng !== null;
        if ($started_only && !livreur_mes_livraison_en_cours('commande', $cmd)) {
            continue;
        }
        $suivi_qs = 'commande_id=' . (int) $cmd['id'];
        if ($tracking_active || $geo_ready) {
            $suivi_qs .= '&autostart=1';
        }
        $items[] = [
            'type' => 'commande',
            'id' => (int) $cmd['id'],
            'numero' => (string) ($cmd['numero_commande'] ?? ''),
            'client_nom' => $client_nom,
            'client_tel' => $tel,
            'adresse' => (string) ($cmd['adresse_livraison'] ?? ''),
            'tracking_active' => $tracking_active,
            'statut' => (string) ($cmd['statut'] ?? ''),
            'statut_label' => livreur_statut_label($cmd['statut'] ?? ''),
            'geo_ready' => $geo_ready,
            'suivi_url' => 'suivi.php?' . $suivi_qs,
            'terminee' => false,
        ];
    }

    if (livreur_bl_livraison_columns_ok()) {
        foreach (livreur_get_factures_livraison_list($only_today) as $facture) {
            if ((int) ($facture['livreur_id'] ?? 0) !== $admin_id) {
                continue;
            }
            $statut_bl = strtolower(trim((string) ($facture['statut_bl'] ?? $facture['statut'] ?? '')));
            if (in_array($statut_bl, ['livree', 'livré', 'livre', 'annulee', 'annulée', 'annule'], true)) {
                continue;
            }
            $lat = livreur_parse_coord($facture['delivery_latitude'] ?? null);
            $lng = livreur_parse_coord($facture['delivery_longitude'] ?? null);
            $adresse = trim((string) ($facture['adresse_livraison'] ?? $facture['adresse_client'] ?? $facture['client_adresse'] ?? ''));
            $tracking_active = (int) ($facture['tracking_active'] ?? 0);
            $geo_ready = $lat !== null && $lng !== null;
            if ($started_only && !livreur_mes_livraison_en_cours('facture', $facture)) {
                continue;
            }
            $suivi_qs = 'bl_id=' . (int) $facture['id'];
            if ($tracking_active || $geo_ready) {
                $suivi_qs .= '&autostart=1';
            }
            $items[] = [
                'type' => 'facture',
                'id' => (int) $facture['id'],
                'numero' => (string) ($facture['numero_bl'] ?? ''),
                'client_nom' => trim((string) ($facture['raison_sociale'] ?? '')),
                'client_tel' => trim((string) ($facture['client_telephone'] ?? '')),
                'adresse' => $adresse,
                'tracking_active' => $tracking_active,
                'statut' => $statut_bl,
                'statut_label' => livreur_facture_statut_livraison($facture),
                'geo_ready' => $geo_ready,
                'suivi_url' => 'suivi.php?' . $suivi_qs,
                'terminee' => false,
            ];
        }
    }

    usort($items, function ($a, $b) {
        $ta = !empty($a['tracking_active']) ? 0 : 1;
        $tb = !empty($b['tracking_active']) ? 0 : 1;
        if ($ta !== $tb) {
            return $ta - $tb;
        }
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });

    return $items;
}

/**
 * Libellé affichage statut commande livraison.
 */
function livreur_statut_label($statut) {
    $labels = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'prise_en_charge' => 'Prise en charge',
        'en_preparation' => 'En préparation',
        'livraison_en_cours' => 'En livraison',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'paye' => 'Payée',
        'annulee' => 'Annulée',
    ];
    return $labels[$statut] ?? $statut;
}

/**
 * Livreurs actuellement en livraison GPS (tracking_active=1), avec dernière position.
 * Un livreur peut avoir plusieurs livraisons : on agrège par livreur_id.
 *
 * @return array<int, array<string, mixed>>
 */
function livreur_get_actifs_sur_carte() {
    global $db;
    if (!livreur_tracking_tables_ready()) {
        return [];
    }

    $by_livreur = [];

    try {
        $stmt = $db->query("
            SELECT c.id AS livraison_id, 'commande' AS livraison_type,
                   c.numero_commande AS numero, c.adresse_livraison,
                   c.livreur_id, c.tracking_active,
                   c.delivery_latitude, c.delivery_longitude,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom, a.email AS livreur_email
                   " . livreur_admin_photo_profil_sql_select('a') . "
            FROM commandes c
            INNER JOIN admin a ON a.id = c.livreur_id
            WHERE c.tracking_active = 1
              AND c.livreur_id IS NOT NULL
              AND c.livreur_id > 0
            ORDER BY c.date_commande DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $lid = (int) $row['livreur_id'];
            if ($lid < 1 || isset($by_livreur[$lid])) {
                continue;
            }
            $by_livreur[$lid] = $row;
        }
    } catch (PDOException $e) {
        /* silencieux */
    }

    if (livreur_bl_livraison_columns_ok()) {
        try {
            $stmt = $db->query("
                SELECT b.id AS livraison_id, 'facture' AS livraison_type,
                       b.numero_bl AS numero, b.adresse_livraison,
                       b.livreur_id, b.tracking_active,
                       b.delivery_latitude, b.delivery_longitude,
                       a.nom AS livreur_nom, a.prenom AS livreur_prenom, a.email AS livreur_email
                       " . livreur_admin_photo_profil_sql_select('a') . "
                FROM bons_livraison b
                INNER JOIN admin a ON a.id = b.livreur_id
                WHERE b.tracking_active = 1
                  AND b.livreur_id IS NOT NULL
                  AND b.livreur_id > 0
                ORDER BY b.date_creation DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $lid = (int) $row['livreur_id'];
                if ($lid < 1 || isset($by_livreur[$lid])) {
                    continue;
                }
                $by_livreur[$lid] = $row;
            }
        } catch (PDOException $e) {
            /* silencieux */
        }
    }

    $out = [];
    foreach ($by_livreur as $lid => $row) {
        $commande_id = ($row['livraison_type'] === 'commande') ? (int) $row['livraison_id'] : null;
        $bl_id = ($row['livraison_type'] === 'facture') ? (int) $row['livraison_id'] : null;
        $pos = livreur_get_last_position($lid, $commande_id, $bl_id);

        $out[] = [
            'livreur_id' => $lid,
            'livreur_nom' => trim(($row['livreur_prenom'] ?? '') . ' ' . ($row['livreur_nom'] ?? '')),
            'livreur_photo_url' => livreur_photo_url_from_row($row),
            'livreur_initials' => livreur_initials_from_row($row),
            'livreur_email' => $row['livreur_email'] ?? '',
            'livraison_type' => $row['livraison_type'],
            'livraison_id' => (int) $row['livraison_id'],
            'numero' => $row['numero'] ?? '',
            'adresse_livraison' => $row['adresse_livraison'] ?? '',
            'delivery_latitude' => livreur_parse_coord($row['delivery_latitude'] ?? null),
            'delivery_longitude' => livreur_parse_coord($row['delivery_longitude'] ?? null),
            'latitude' => $pos ? livreur_parse_coord($pos['latitude'] ?? null) : null,
            'longitude' => $pos ? livreur_parse_coord($pos['longitude'] ?? null) : null,
            'heading' => $pos && isset($pos['heading']) ? (float) $pos['heading'] : null,
            'speed' => $pos && isset($pos['speed']) ? (float) $pos['speed'] : null,
            'recorded_at' => $pos['recorded_at'] ?? null,
            'suivi_url' => $row['livraison_type'] === 'facture'
                ? ('suivi.php?bl_id=' . (int) $row['livraison_id'] . '&regarder=1')
                : ('suivi.php?commande_id=' . (int) $row['livraison_id'] . '&regarder=1'),
        ];
    }

    usort($out, function ($a, $b) {
        return strcasecmp($a['livreur_nom'], $b['livreur_nom']);
    });

    return $out;
}
