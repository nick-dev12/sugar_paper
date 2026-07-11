<?php
/**
 * Annonces plateforme — envoi client / vendeur
 */

require_once __DIR__ . '/../conn/conn.php';

function annonces_table_exists() {
    global $db;
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'platform_annonces'");
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function annonce_audience_label($audience) {
    return $audience === 'vendeur' ? 'Vendeurs' : 'Clients';
}

function annonce_default_link($audience) {
    return $audience === 'vendeur' ? '/admin/annonces.php' : '/user/annonces.php';
}

/**
 * @return array|false
 */
function annonce_get_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !annonces_table_exists()) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT * FROM platform_annonces WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return int|false
 */
function annonce_create($titre, $message, $audience, $super_admin_id, $lien_url = null) {
    global $db;
    if (!annonces_table_exists()) {
        return false;
    }
    $audience = $audience === 'vendeur' ? 'vendeur' : 'client';
    $super_admin_id = (int) $super_admin_id;
    if ($super_admin_id <= 0 || trim($titre) === '' || trim($message) === '') {
        return false;
    }
    $lien = trim((string) $lien_url);
    if ($lien === '') {
        $lien = annonce_default_link($audience);
    }
    try {
        $stmt = $db->prepare("
            INSERT INTO platform_annonces (titre, message, audience, lien_url, super_admin_id, date_envoi)
            VALUES (:titre, :message, :audience, :lien, :sid, NOW())
        ");
        $ok = $stmt->execute([
            'titre' => trim($titre),
            'message' => trim($message),
            'audience' => $audience,
            'lien' => $lien,
            'sid' => $super_admin_id,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    } catch (PDOException $e) {
        return false;
    }
}

function annonce_update_push_stats($id, $cibles, $ok_count, $fail_count) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            UPDATE platform_annonces
            SET nb_destinataires_cibles = :cibles,
                nb_push_envoyes = :ok,
                nb_push_echecs = :fail
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'cibles' => (int) $cibles,
            'ok' => (int) $ok_count,
            'fail' => (int) $fail_count,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array
 */
function annonces_list_super_admin($limit = 100) {
    global $db;
    if (!annonces_table_exists()) {
        return [];
    }
    $limit = max(1, min(500, (int) $limit));
    try {
        $stmt = $db->query("
            SELECT a.*, s.email AS super_admin_email, s.nom AS super_admin_nom
            FROM platform_annonces a
            LEFT JOIN super_admin s ON s.id = a.super_admin_id
            ORDER BY a.date_envoi DESC
            LIMIT $limit
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array
 */
function annonces_list_for_client($user_id, $limit = 50) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !annonces_table_exists()) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));
    try {
        $stmt = $db->prepare("
            SELECT a.*,
                CASE WHEN l.id IS NULL THEN 0 ELSE 1 END AS est_lue
            FROM platform_annonces a
            LEFT JOIN platform_annonce_lectures l
                ON l.annonce_id = a.id AND l.user_id = :uid
            WHERE a.audience = 'client'
            ORDER BY a.date_envoi DESC
            LIMIT $limit
        ");
        $stmt->execute(['uid' => $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array
 */
function annonces_list_for_vendeur($admin_id, $limit = 50) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !annonces_table_exists()) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));
    try {
        $stmt = $db->prepare("
            SELECT a.*,
                CASE WHEN l.id IS NULL THEN 0 ELSE 1 END AS est_lue
            FROM platform_annonces a
            LEFT JOIN platform_annonce_lectures l
                ON l.annonce_id = a.id AND l.admin_id = :aid
            WHERE a.audience = 'vendeur'
            ORDER BY a.date_envoi DESC
            LIMIT $limit
        ");
        $stmt->execute(['aid' => $admin_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function annonce_count_unread_client($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !annonces_table_exists()) {
        return 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM platform_annonces a
            LEFT JOIN platform_annonce_lectures l
                ON l.annonce_id = a.id AND l.user_id = :uid
            WHERE a.audience = 'client' AND l.id IS NULL
        ");
        $stmt->execute(['uid' => $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function annonce_count_unread_vendeur($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !annonces_table_exists()) {
        return 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM platform_annonces a
            LEFT JOIN platform_annonce_lectures l
                ON l.annonce_id = a.id AND l.admin_id = :aid
            WHERE a.audience = 'vendeur' AND l.id IS NULL
        ");
        $stmt->execute(['aid' => $admin_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function annonce_mark_read_client($annonce_id, $user_id) {
    global $db;
    $annonce_id = (int) $annonce_id;
    $user_id = (int) $user_id;
    if ($annonce_id <= 0 || $user_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO platform_annonce_lectures (annonce_id, user_id, date_lecture)
            VALUES (:aid, :uid, NOW())
        ");
        return $stmt->execute(['aid' => $annonce_id, 'uid' => $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function annonce_mark_read_vendeur($annonce_id, $admin_id) {
    global $db;
    $annonce_id = (int) $annonce_id;
    $admin_id = (int) $admin_id;
    if ($annonce_id <= 0 || $admin_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO platform_annonce_lectures (annonce_id, admin_id, date_lecture)
            VALUES (:aid, :aid_admin, NOW())
        ");
        return $stmt->execute(['aid' => $annonce_id, 'aid_admin' => $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function annonce_mark_all_read_client($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !annonces_table_exists()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO platform_annonce_lectures (annonce_id, user_id, date_lecture)
            SELECT a.id, :uid, NOW()
            FROM platform_annonces a
            WHERE a.audience = 'client'
        ");
        return $stmt->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function annonce_mark_all_read_vendeur($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !annonces_table_exists()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO platform_annonce_lectures (annonce_id, admin_id, date_lecture)
            SELECT a.id, :aid, NOW()
            FROM platform_annonces a
            WHERE a.audience = 'vendeur'
        ");
        return $stmt->execute(['aid' => $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}
