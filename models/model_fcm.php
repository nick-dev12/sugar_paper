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
function fcm_notify_admin_roles_eligible()
{
    return ['admin', 'utilisateur'];
}

/**
 * Vérifie si un compte admin peut recevoir les alertes push commandes
 * @param int $admin_id
 * @return bool
 */
function fcm_admin_is_eligible_for_notify($admin_id)
{
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
function save_fcm_token($token, $type, $user_id = null, $admin_id = null)
{
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

        // Un token FCM = un appareil. On le rattache toujours au compte actuellement connecté.
        $stmt = $db->prepare("SELECT id, admin_id, user_id, type FROM fcm_tokens WHERE token = :token LIMIT 1");
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
            $ok = $stmt->execute([
                'type' => $type,
                'user_id' => $type === 'user' ? $user_id : null,
                'admin_id' => $type === 'admin' ? $admin_id : null,
                'user_agent' => $user_agent,
                'id' => $existing['id'],
            ]);
            return (bool) $ok;
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
function get_fcm_tokens_by_user($user_id)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT ft.token
            FROM fcm_tokens ft
            INNER JOIN users u ON u.id = ft.user_id
            WHERE ft.user_id = :user_id
              AND ft.type = 'user'
              AND u.statut = 'actif'
              AND ft.token IS NOT NULL AND ft.token != ''
        ");
        $stmt->execute(['user_id' => (int) $user_id]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $tokens ? array_values(array_unique($tokens)) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les tokens FCM d'un admin (compte staff connecté)
 * @param int $admin_id ID de l'admin
 * @return array Liste des tokens
 */
function get_fcm_tokens_by_admin($admin_id)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT ft.token, a.role, a.statut
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id
            WHERE ft.admin_id = :admin_id
              AND ft.type = 'admin'
              AND ft.token IS NOT NULL AND ft.token != ''
        ");
        $stmt->execute(['admin_id' => (int) $admin_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $eligible_roles = fcm_notify_admin_roles_eligible();
        $tokens = [];

        foreach ($rows as $row) {
            if (($row['statut'] ?? '') !== 'actif') {
                continue;
            }
            $role = normalize_admin_role($row['role'] ?? '');
            if (in_array($role, $eligible_roles, true)) {
                $tokens[] = $row['token'];
            }
        }

        return $tokens;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Supprime les tokens FCM d'un admin (désactivation explicite uniquement — pas à la déconnexion)
 * @param int $admin_id ID de l'admin
 * @return bool True en cas de succès
 */
function delete_fcm_tokens_by_admin($admin_id)
{
    global $db;

    try {
        $stmt = $db->prepare("DELETE FROM fcm_tokens WHERE admin_id = :admin_id AND type = 'admin'");
        return $stmt->execute(['admin_id' => (int) $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_fcm_token_by_value($token)
{
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

function delete_fcm_tokens_by_user($user_id)
{
    global $db;

    try {
        $stmt = $db->prepare("DELETE FROM fcm_tokens WHERE user_id = :user_id AND type = 'user'");
        return $stmt->execute(['user_id' => (int) $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Tokens FCM regroupés par admin éligible (un envoi individuel par compte)
 * @return array [admin_id => ['admin_id'=>int,'email'=>string,'role'=>string,'tokens'=>string[]], ...]
 */
function get_fcm_admin_token_groups()
{
    global $db;
    $groups = [];

    try {
        $stmt = $db->query("
            SELECT ft.token, ft.admin_id, a.email, a.role, a.statut
            FROM fcm_tokens ft
            INNER JOIN admin a ON a.id = ft.admin_id
            WHERE ft.type = 'admin'
              AND ft.admin_id IS NOT NULL
              AND ft.admin_id > 0
              AND ft.token IS NOT NULL AND ft.token != ''
            ORDER BY ft.admin_id ASC, ft.id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $eligible_roles = fcm_notify_admin_roles_eligible();

        foreach ($rows as $row) {
            if (($row['statut'] ?? '') !== 'actif') {
                continue;
            }
            $role = normalize_admin_role($row['role'] ?? '');
            if (!in_array($role, $eligible_roles, true)) {
                continue;
            }
            $admin_id = (int) $row['admin_id'];
            $token = trim((string) $row['token']);
            if ($admin_id <= 0 || $token === '') {
                continue;
            }
            if (!isset($groups[$admin_id])) {
                $groups[$admin_id] = [
                    'admin_id' => $admin_id,
                    'email' => (string) ($row['email'] ?? ''),
                    'role' => $role,
                    'tokens' => [],
                ];
            }
            if (!in_array($token, $groups[$admin_id]['tokens'], true)) {
                $groups[$admin_id]['tokens'][] = $token;
            }
        }
    } catch (PDOException $e) {
        return [];
    }

    return $groups;
}

/**
 * Tokens FCM de tous les admins éligibles (rôles admin + utilisateur, actifs)
 * Liste plate dédupliquée (compatibilité)
 * @return array Liste des tokens
 */
function get_all_fcm_tokens_admin()
{
    $tokens = [];
    foreach (get_fcm_admin_token_groups() as $group) {
        foreach ($group['tokens'] as $token) {
            $tokens[] = $token;
        }
    }
    return array_values(array_unique($tokens));
}

/**
 * Indique si le compte admin connecté a au moins un token FCM valide
 * @param int $admin_id
 * @return bool
 */
function admin_has_fcm_tokens($admin_id)
{
    return count(get_fcm_tokens_by_admin((int) $admin_id)) > 0;
}

/**
 * Réassocie les tokens admin orphelins (admin_id NULL) au compte connecté si le token correspond
 * Appelé après activation / resync login
 * @param int $admin_id
 * @param string $token
 * @return bool
 */
function fcm_relink_orphan_admin_token($admin_id, $token)
{
    global $db;

    $admin_id = (int) $admin_id;
    $token = trim((string) $token);
    if ($admin_id <= 0 || $token === '' || !fcm_admin_is_eligible_for_notify($admin_id)) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            UPDATE fcm_tokens
            SET admin_id = :admin_id, type = 'admin', user_id = NULL, date_creation = NOW()
            WHERE token = :token AND type = 'admin'
        ");
        return $stmt->execute(['admin_id' => $admin_id, 'token' => $token]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime les tokens admin orphelins (admin_id NULL) — inutilisables pour les alertes
 * @return int Nombre de lignes supprimées
 */
function fcm_cleanup_orphan_admin_tokens()
{
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM fcm_tokens WHERE type = 'admin' AND (admin_id IS NULL OR admin_id = 0)");
        $stmt->execute();
        return (int) $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Supprime les tokens invalides après échec FCM
 * @param array $tokens
 */
function fcm_delete_invalid_tokens(array $tokens)
{
    foreach ($tokens as $token) {
        if (is_string($token) && $token !== '') {
            delete_fcm_token_by_value($token);
        }
    }
}
