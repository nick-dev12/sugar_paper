<?php
/**
 * Types de boutique — options configurables par le super administrateur.
 */

function boutique_types_table_exists()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boutique_types'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * @return array<int, array<string, mixed>>
 */
function boutique_types_list_all($include_inactive = true)
{
    global $db;
    if (!boutique_types_table_exists()) {
        return [];
    }
    try {
        $sql = 'SELECT * FROM `boutique_types`';
        if (!$include_inactive) {
            $sql .= ' WHERE `actif` = 1';
        }
        $sql .= ' ORDER BY `sort_ordre` ASC, `nom` ASC';
        $st = $db->query($sql);
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function boutique_types_list_active()
{
    return boutique_types_list_all(false);
}

function get_boutique_type_by_id($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !boutique_types_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM `boutique_types` WHERE `id` = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

function boutique_type_is_valid_active($id)
{
    $row = get_boutique_type_by_id((int) $id);
    return $row && (int) ($row['actif'] ?? 0) === 1;
}

function boutique_type_nom_disponible($nom, $exclude_id = 0)
{
    global $db;
    if (!boutique_types_table_exists()) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    try {
        $st = $db->prepare('
            SELECT COUNT(*) FROM `boutique_types`
            WHERE `nom` = :nom AND `id` <> :id
        ');
        $st->execute(['nom' => $nom, 'id' => (int) $exclude_id]);
        return ((int) $st->fetchColumn()) === 0;
    } catch (PDOException $e) {
        return false;
    }
}

function count_vendeurs_par_boutique_type_id($type_id)
{
    global $db;
    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return 0;
    }
    if (!function_exists('admin_has_boutique_type_id_column')) {
        require_once __DIR__ . '/model_admin.php';
    }
    if (!admin_has_boutique_type_id_column()) {
        return 0;
    }
    try {
        $st = $db->prepare("
            SELECT COUNT(*) FROM `admin`
            WHERE `role` = 'vendeur' AND `boutique_type_id` = :tid
        ");
        $st->execute(['tid' => $type_id]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function boutique_type_insert_row($nom, $description, $sort_ordre, $actif = 1)
{
    global $db;
    if (!boutique_types_table_exists() || !boutique_type_nom_disponible($nom, 0)) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO `boutique_types` (`nom`, `description`, `sort_ordre`, `actif`, `date_creation`)
            VALUES (:nom, :descr, :so, :actif, NOW())
        ');
        if ($st->execute([
            'nom' => $nom,
            'descr' => $description !== null && (string) $description !== '' ? (string) $description : null,
            'so' => (int) $sort_ordre,
            'actif' => (int) $actif === 1 ? 1 : 0,
        ])) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
    }
    return false;
}

function boutique_type_update_row($id, $nom, $description, $sort_ordre, $actif = 1)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !boutique_types_table_exists() || !get_boutique_type_by_id($id)) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '' || !boutique_type_nom_disponible($nom, $id)) {
        return false;
    }
    try {
        $st = $db->prepare('
            UPDATE `boutique_types`
            SET `nom` = :nom, `description` = :descr, `sort_ordre` = :so, `actif` = :actif
            WHERE `id` = :id
        ');
        return $st->execute([
            'id' => $id,
            'nom' => $nom,
            'descr' => $description !== null && (string) $description !== '' ? (string) $description : null,
            'so' => (int) $sort_ordre,
            'actif' => (int) $actif === 1 ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function boutique_type_delete_row($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !boutique_types_table_exists()) {
        return false;
    }
    if (count_vendeurs_par_boutique_type_id($id) > 0) {
        return false;
    }
    try {
        $st = $db->prepare('DELETE FROM `boutique_types` WHERE `id` = :id');
        return $st->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}
