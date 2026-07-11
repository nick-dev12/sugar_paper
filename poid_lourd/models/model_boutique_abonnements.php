<?php
/**
 * Modèle — abonnements client → boutique vendeur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function boutique_abonnements_table_exists()
{
    global $db;

    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $db->query("SHOW TABLES LIKE 'boutique_abonnements'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return bool
 */
function boutique_abonnement_subscribe($user_id, $admin_id)
{
    global $db;

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO boutique_abonnements (user_id, admin_id, date_abonnement)
            VALUES (:user_id, :admin_id, NOW())
            ON DUPLICATE KEY UPDATE date_abonnement = NOW()
        ");
        return $stmt->execute([
            'user_id' => $user_id,
            'admin_id' => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return bool
 */
function boutique_abonnement_unsubscribe($user_id, $admin_id)
{
    global $db;

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM boutique_abonnements WHERE user_id = :user_id AND admin_id = :admin_id');
        return $stmt->execute([
            'user_id' => $user_id,
            'admin_id' => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return bool
 */
function boutique_abonnement_is_subscribed($user_id, $admin_id)
{
    global $db;

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return false;
    }

    try {
        $stmt = $db->prepare('SELECT 1 FROM boutique_abonnements WHERE user_id = :user_id AND admin_id = :admin_id LIMIT 1');
        $stmt->execute([
            'user_id' => $user_id,
            'admin_id' => $admin_id,
        ]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Boutiques auxquelles le client est abonné
 *
 * @param int $user_id
 * @return array<int, array<string, mixed>>
 */
function boutique_abonnements_list_by_user($user_id)
{
    global $db;

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !boutique_abonnements_table_exists()) {
        return [];
    }

    $logo_col = '';
    try {
        $chk = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_logo'");
        if ($chk && $chk->fetch(PDO::FETCH_ASSOC)) {
            $logo_col = ', a.boutique_logo';
        }
    } catch (PDOException $e) {
        $logo_col = '';
    }

    try {
        $sql = "
            SELECT ba.id, ba.admin_id, ba.date_abonnement,
                   a.boutique_slug, a.boutique_nom, a.nom AS vendeur_nom
                   {$logo_col}
            FROM boutique_abonnements ba
            INNER JOIN admin a ON a.id = ba.admin_id
            WHERE ba.user_id = :user_id
              AND a.statut = 'actif'
              AND a.boutique_slug IS NOT NULL
              AND TRIM(a.boutique_slug) <> ''
            ORDER BY ba.date_abonnement DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Clients abonnés à la boutique d'un vendeur
 *
 * @param int $admin_id
 * @return array<int, array<string, mixed>>
 */
function boutique_abonnements_list_by_vendeur($admin_id)
{
    global $db;

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return [];
    }

    try {
        $stmt = $db->prepare("
            SELECT ba.id, ba.user_id, ba.date_abonnement,
                   u.nom, u.prenom, u.email, u.telephone, u.statut
            FROM boutique_abonnements ba
            INNER JOIN users u ON u.id = ba.user_id
            WHERE ba.admin_id = :admin_id
            ORDER BY ba.date_abonnement DESC
        ");
        $stmt->execute(['admin_id' => $admin_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $admin_id
 * @return int
 */
function boutique_abonnements_count_by_vendeur($admin_id)
{
    global $db;

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return 0;
    }

    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM boutique_abonnements WHERE admin_id = :admin_id');
        $stmt->execute(['admin_id' => $admin_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * IDs utilisateurs abonnés (pour notifications push)
 *
 * @param int $admin_id
 * @return array<int, int>
 */
function boutique_abonnements_subscriber_user_ids($admin_id)
{
    global $db;

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !boutique_abonnements_table_exists()) {
        return [];
    }

    try {
        $stmt = $db->prepare("
            SELECT DISTINCT ba.user_id
            FROM boutique_abonnements ba
            INNER JOIN users u ON u.id = ba.user_id AND u.statut = 'actif'
            WHERE ba.admin_id = :admin_id
        ");
        $stmt->execute(['admin_id' => $admin_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids ?: []);
    } catch (PDOException $e) {
        return [];
    }
}
