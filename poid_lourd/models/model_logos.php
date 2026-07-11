<?php
/**
 * Modèle pour la gestion des logos partenaires
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';

/**
 * @param string|null $statut
 * @param int|false|null $boutique_admin_id false = tout (admin) ; null = plateforme ; int = vendeur
 */
function get_all_logos($statut = 'actif', $boutique_admin_id = false) {
    global $db;
    try {
        $where = [];
        $params = [];
        if ($statut) {
            $where[] = 'statut = :statut';
            $params['statut'] = $statut;
        }
        if (db_table_has_column('logos', 'admin_id') && $boutique_admin_id !== false) {
            if ($boutique_admin_id === null || (int) $boutique_admin_id === 0) {
                $where[] = 'admin_id IS NULL';
            } else {
                $where[] = 'admin_id = :aid';
                $params['aid'] = (int) $boutique_admin_id;
            }
        }
        $sql = 'SELECT * FROM logos';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ordre ASC, date_creation DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $logos ? $logos : [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_logo_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT * FROM logos WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        $logo = $stmt->fetch(PDO::FETCH_ASSOC);
        return $logo ? $logo : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int|null $admin_id Propriétaire vendeur ou null = plateforme
 */
function create_logo($image, $ordre = 0, $statut = 'actif', $admin_id = null) {
    global $db;
    try {
        if (db_table_has_column('logos', 'admin_id')) {
            $aid = $admin_id !== null && (int) $admin_id > 0 ? (int) $admin_id : null;
            $stmt = $db->prepare('
                INSERT INTO logos (image, ordre, statut, date_creation, admin_id)
                VALUES (:image, :ordre, :statut, NOW(), :admin_id)
            ');
            $stmt->execute([
                'image' => $image,
                'ordre' => (int) $ordre,
                'statut' => in_array($statut, ['actif', 'inactif'], true) ? $statut : 'actif',
                'admin_id' => $aid,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO logos (image, ordre, statut, date_creation)
                VALUES (:image, :ordre, :statut, NOW())
            ');
            $stmt->execute([
                'image' => $image,
                'ordre' => (int) $ordre,
                'statut' => in_array($statut, ['actif', 'inactif'], true) ? $statut : 'actif',
            ]);
        }
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function update_logo($id, $image = null, $ordre = null, $statut = null) {
    global $db;
    try {
        $sets = [];
        $params = ['id' => (int) $id];
        if ($image !== null) {
            $sets[] = 'image = :image';
            $params['image'] = $image;
        }
        if ($ordre !== null) {
            $sets[] = 'ordre = :ordre';
            $params['ordre'] = (int) $ordre;
        }
        if ($statut !== null && in_array($statut, ['actif', 'inactif'], true)) {
            $sets[] = 'statut = :statut';
            $params['statut'] = $statut;
        }
        if (empty($sets)) {
            return true;
        }
        $sql = 'UPDATE logos SET ' . implode(', ', $sets) . ', date_modification = NOW() WHERE id = :id';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_logo($id) {
    global $db;
    try {
        $stmt = $db->prepare('DELETE FROM logos WHERE id = :id');
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}
