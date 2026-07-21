<?php
/**
 * Modèle pour la gestion des tokens FCM (Firebase Cloud Messaging)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin.php';

/**
 * Rôles admin autorisés à recevoir les alertes commandes (push admin)
 * @return array
 */
function fcm_notify_admin_roles_eligible() {
    return ['admin', 'utilisateur'];
}

/**
 * Vérifie si un compte admin peut recevoir les alertes push commandes
 * @param int $admin_id
 * @return bool
 */
function fcm_admin_is_eligible_for_notify($admin_id) {
    $admin = get_admin_by_id((int) $admin_id);
    if (!$admin || ($admin['statut'] ?? '') !== 'actif') {
        return false;
    }
    $role = normalize_admin_role($admin['role'] ?? '');
    return in_array($role, fcm_notify_admin_roles_eligible(), true);
}

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

    if (empty($token) || !in_array($type, ['user', 'admin'], true)) {
        return false;
    }

    if ($type === 'admin') {
        $user_id = null;
        if ($admin_id === null && isset($_SESSION['admin_id'])) {
            $admin_id = (int) $_SESSION['admin_id'];
        }
        if ($admin_id === null || $admin_id <= 0) {
            return false;
        }
        if (!fcm_admin_is_eligible_for_notify($admin_id)) {
            return false;
        }
    } else {
        $admin_id = null;
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }
        if ($user_id === null || $user_id <= 0) {
            return false;
        }
    }

    try {
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $stmt = $db->prepare("SELECT id FROM fcm_tokens WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE fcm_tokens SET
                    type = :type,
                    user_id = :user_id,
                    admin_id = :admin_id,
                    user_agent = :user_agent,
                    date_creation = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'type' => $type,
                'user_id' => $type === 'user' ? $user_id : null,
                'admin_id' => $type === 'admin' ? $admin_id : null,
                'user_agent' => $user_agent,
                'id' => $existing['id'],
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
            'user_agent' => substr($user_agent, 0, 500),
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
        $stmt = $db->prepare("
            SELECT token FROM fcm_tokens
            WHERE user_id = :user_id AND type = 'user'
              AND token IS NOT NULL AND token != ''
        ");
        $stmt->execute(['user_id' => (int) $user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les tokens FCM d'un admin (compte staff connecté)
 * @param int $admin_id ID de l'admin
 * @return array Liste des tokens
 */
function get_fcm_tokens_by_admin($admin_id) {
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT ft.token
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id
            WHERE ft.admin_id = :admin_id
              AND ft.type = 'admin'
              AND ft.token IS NOT NULL AND ft.token != ''
              AND a.statut = 'actif'
              AND a.role IN ('admin', 'utilisateur')
        ");
        $stmt->execute(['admin_id' => (int) $admin_id]);
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
        return $stmt->execute(['admin_id' => (int) $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

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
        return $stmt->execute(['user_id' => (int) $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Tokens FCM de tous les admins éligibles (rôles admin + utilisateur, actifs)
 * @return array Liste des tokens
 */
function get_all_fcm_tokens_admin() {
    global $db;

    $roles = fcm_notify_admin_roles_eligible();
    $placeholders = implode(',', array_fill(0, count($roles), '?'));

    try {
        $stmt = $db->prepare("
            SELECT DISTINCT ft.token
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id
            WHERE ft.type = 'admin'
              AND ft.admin_id IS NOT NULL
              AND ft.token IS NOT NULL AND ft.token != ''
              AND a.statut = 'actif'
              AND a.role IN ($placeholders)
        ");
        $stmt->execute($roles);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Supprime les tokens invalides après échec FCM
 * @param array $tokens
 */
function fcm_delete_invalid_tokens(array $tokens) {
    foreach ($tokens as $token) {
        if (is_string($token) && $token !== '') {
            delete_fcm_token_by_value($token);
        }
    }
}
