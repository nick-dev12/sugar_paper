<?php
/**
 * Modèle pour la gestion des tokens FCM (Firebase Cloud Messaging)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Enregistre ou met à jour un token FCM
 * @param string $token Le token FCM
 * @param string $type 'user' ou 'admin'
 * @param int|null $user_id ID utilisateur (pour type='user')
 * @param int|null $admin_id ID admin (pour type='admin')
 * @return bool True en cas de succès
 */
function save_fcm_token($token, $type, $user_id = null, $admin_id = null) {
    global $db;
    
    if (empty($token) || !in_array($type, ['user', 'admin'])) {
        return false;
    }
    
    try {
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        
        $stmt = $db->prepare("SELECT id FROM fcm_tokens WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $stmt = $db->prepare("
                UPDATE fcm_tokens SET user_id = :user_id, admin_id = :admin_id, user_agent = :user_agent, date_creation = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'user_id' => $type === 'user' ? $user_id : null,
                'admin_id' => $type === 'admin' ? $admin_id : null,
                'user_agent' => $user_agent,
                'id' => $existing['id']
            ]);
        }
        
        $stmt = $db->prepare("
            INSERT INTO fcm_tokens (token, type, user_id, admin_id, user_agent, date_creation)
            VALUES (:token, :type, :user_id, :admin_id, :user_agent, NOW())
        ");
        
        return $stmt->execute([
            'token' => $token,
            'type' => $type,
            'user_id' => $type === 'user' ? $user_id : null,
            'admin_id' => $type === 'admin' ? $admin_id : null,
            'user_agent' => substr($user_agent, 0, 500)
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les tokens FCM d'un utilisateur (client)
 * @param int $user_id ID de l'utilisateur
 * @return array Liste des tokens
 */
function get_fcm_tokens_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT token FROM fcm_tokens WHERE user_id = :user_id AND type = 'user'");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les tokens FCM d'un admin
 * @param int $admin_id ID de l'admin
 * @return array Liste des tokens
 */
function get_fcm_tokens_by_admin($admin_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT token FROM fcm_tokens WHERE admin_id = :admin_id AND type = 'admin'");
        $stmt->execute(['admin_id' => $admin_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Supprime les tokens FCM d'un admin (à la déconnexion)
 * @param int $admin_id ID de l'admin
 * @return bool True en cas de succès
 */
function delete_fcm_tokens_by_admin($admin_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE fcm_tokens SET admin_id = NULL WHERE admin_id = :admin_id AND type = 'admin'");
        return $stmt->execute(['admin_id' => $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime les tokens FCM d'un utilisateur (à la déconnexion)
 * @param int $user_id ID de l'utilisateur
 * @return bool True en cas de succès
 */
/**
 * Supprime un token FCM invalide (ex. « Device unregistered »)
 * @param string $token
 * @return bool
 */
function delete_fcm_token_by_value($token) {
    global $db;

    if ($token === '') {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM fcm_tokens WHERE token = :token');
        return $stmt->execute(['token' => $token]);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_fcm_tokens_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE fcm_tokens SET user_id = NULL WHERE user_id = :user_id AND type = 'user'");
        return $stmt->execute(['user_id' => $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les tokens FCM des administrateurs
 * @return array Liste des tokens
 */
function get_all_fcm_tokens_admin() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT DISTINCT token FROM fcm_tokens WHERE type = 'admin' AND admin_id IS NOT NULL AND token IS NOT NULL AND token != ''");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Indique si un utilisateur a au moins un token FCM actif
 * @param int $user_id
 * @return bool
 */
function user_has_fcm_tokens($user_id) {
    $tokens = get_fcm_tokens_by_user((int) $user_id);
    return !empty($tokens);
}

/**
 * Indique si un admin/vendeur a au moins un token FCM actif
 * @param int $admin_id
 * @return bool
 */
function admin_has_fcm_tokens($admin_id) {
    $tokens = get_fcm_tokens_by_admin((int) $admin_id);
    return !empty($tokens);
}

/**
 * Tokens FCM de tous les clients (comptes users actifs)
 * @return array
 */
function get_all_fcm_tokens_clients() {
    global $db;

    try {
        $stmt = $db->query("
            SELECT DISTINCT ft.token
            FROM fcm_tokens ft
            INNER JOIN users u ON u.id = ft.user_id AND u.statut = 'actif'
            WHERE ft.type = 'user'
              AND ft.user_id IS NOT NULL
              AND ft.token IS NOT NULL
              AND ft.token != ''
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Tokens FCM de tous les vendeurs (admin role vendeur actif)
 * @return array
 */
function get_all_fcm_tokens_vendeurs() {
    global $db;

    try {
        $stmt = $db->query("
            SELECT DISTINCT ft.token
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id AND a.role = 'vendeur' AND a.statut = 'actif'
            WHERE ft.type = 'admin'
              AND ft.admin_id IS NOT NULL
              AND ft.token IS NOT NULL
              AND ft.token != ''
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Nombre de clients avec token FCM actif
 */
function count_fcm_clients_cibles() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT COUNT(DISTINCT ft.user_id)
            FROM fcm_tokens ft
            INNER JOIN users u ON u.id = ft.user_id AND u.statut = 'actif'
            WHERE ft.type = 'user' AND ft.user_id IS NOT NULL
        ");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Nombre de vendeurs avec token FCM actif
 */
function count_fcm_vendeurs_cibles() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT COUNT(DISTINCT ft.admin_id)
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id AND a.role = 'vendeur' AND a.statut = 'actif'
            WHERE ft.type = 'admin' AND ft.admin_id IS NOT NULL
        ");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
