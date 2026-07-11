<?php
/**
 * Images hero marketplace (accueil) — super administrateur
 */
require_once __DIR__ . '/../conn/conn.php';

function marketplace_hero_table_exists() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->query("SELECT 1 FROM marketplace_hero_affiches LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array
 */
function marketplace_hero_list_actifs() {
    global $db;
    if (!marketplace_hero_table_exists()) {
        return [];
    }
    try {
        $stmt = $db->query("
            SELECT id, image, alt_text, ordre
            FROM marketplace_hero_affiches
            WHERE actif = 'actif'
            ORDER BY ordre ASC, id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Liste complète (admin)
 * @return array
 */
function marketplace_hero_list_all() {
    global $db;
    if (!marketplace_hero_table_exists()) {
        return [];
    }
    try {
        $stmt = $db->query("
            SELECT * FROM marketplace_hero_affiches
            ORDER BY ordre ASC, id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return int|false id inséré
 */
function marketplace_hero_insert($filename, $alt_text, $ordre = 0) {
    global $db;
    if (!marketplace_hero_table_exists()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT INTO marketplace_hero_affiches (image, alt_text, ordre, actif, date_creation)
            VALUES (:img, :alt, :ord, 'actif', NOW())
        ");
        if ($stmt->execute([
            'img' => $filename,
            'alt' => $alt_text,
            'ord' => (int) $ordre,
        ])) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
        return false;
    }
    return false;
}

/**
 * @return bool
 */
function marketplace_hero_delete_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("DELETE FROM marketplace_hero_affiches WHERE id = :id LIMIT 1");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array|false
 */
function marketplace_hero_get_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT * FROM marketplace_hero_affiches WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return bool
 */
function marketplace_hero_update_ordre($id, $ordre) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE marketplace_hero_affiches SET ordre = :o WHERE id = :id");
        return $stmt->execute(['o' => (int) $ordre, 'id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Prochain ordre libre (max + 1)
 */
function marketplace_hero_next_ordre() {
    global $db;
    if (!marketplace_hero_table_exists()) {
        return 0;
    }
    try {
        $stmt = $db->query("SELECT COALESCE(MAX(ordre), -1) + 1 AS n FROM marketplace_hero_affiches");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Réordonne en échangeant deux IDs (adjacents par tri courant)
 */
function marketplace_hero_swap_ordre($id_a, $id_b) {
    $a = marketplace_hero_get_by_id($id_a);
    $b = marketplace_hero_get_by_id($id_b);
    if (!$a || !$b) {
        return false;
    }
    global $db;
    try {
        $db->beginTransaction();
        $oa = (int) $a['ordre'];
        $ob = (int) $b['ordre'];
        $s1 = $db->prepare("UPDATE marketplace_hero_affiches SET ordre = :o WHERE id = :id");
        $s1->execute(['o' => $ob, 'id' => (int) $a['id']]);
        $s1->execute(['o' => $oa, 'id' => (int) $b['id']]);
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

/**
 * Déplace une ligne vers le haut ou le bas dans l'ordre d'affichage
 *
 * @param string $dir 'up' | 'down'
 */
function marketplace_hero_move($id, $dir) {
    $id = (int) $id;
    if ($id <= 0 || ($dir !== 'up' && $dir !== 'down')) {
        return false;
    }
    $list = marketplace_hero_list_all();
    if (count($list) < 2) {
        return false;
    }
    $ids = [];
    foreach ($list as $row) {
        $ids[] = (int) $row['id'];
    }
    $i = array_search($id, $ids, true);
    if ($i === false) {
        return false;
    }
    if ($dir === 'up' && $i > 0) {
        return marketplace_hero_swap_ordre($id, $ids[$i - 1]);
    }
    if ($dir === 'down' && $i < count($ids) - 1) {
        return marketplace_hero_swap_ordre($id, $ids[$i + 1]);
    }
    return false;
}
