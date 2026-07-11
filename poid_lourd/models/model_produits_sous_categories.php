<?php
/**
 * Liaison N-N produits ↔ sous-catégories (categories feuilles plateforme)
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_produits.php';

/**
 * @return bool
 */
function produits_sous_categories_table_exists() {
    static $ok = null;
    global $db;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return $ok;
    }
    try {
        $st = $db->query("SHOW TABLES LIKE " . $db->quote('produits_sous_categories'));
        $ok = (bool) $st && (bool) $st->fetch(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return int[]
 */
function get_sous_categorie_ids_for_produit($produit_id) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_sous_categories_table_exists()) {
        return [];
    }
    try {
        $st = $db->prepare('
            SELECT `categorie_id` FROM `produits_sous_categories`
            WHERE `produit_id` = :p
            ORDER BY `categorie_id` ASC
        ');
        $st->execute(['p' => $produit_id]);
        $out = [];
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $out[] = (int) $r[0];
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Remplace toutes les liaisons pour un produit.
 *
 * @param int[] $categorie_ids
 */
function save_produits_sous_categories_for_produit($produit_id, array $categorie_ids) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_sous_categories_table_exists()) {
        return;
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $categorie_ids), function ($x) {
        return (int) $x > 0;
    })));
    sort($ids, SORT_NUMERIC);
    try {
        $db->beginTransaction();
        $d = $db->prepare('DELETE FROM `produits_sous_categories` WHERE `produit_id` = :p');
        $d->execute(['p' => $produit_id]);
        if (!empty($ids)) {
            $ins = $db->prepare('
                INSERT INTO `produits_sous_categories` (`produit_id`, `categorie_id`) VALUES (:p, :c)
            ');
            foreach ($ids as $cid) {
                $ins->execute(['p' => $produit_id, 'c' => $cid]);
            }
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

/**
 * Nombre de produits liés (categorie feuille directe ou pivot).
 */
function count_produits_referencing_sous_categorie($categorie_id) {
    global $db;
    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0) {
        return 0;
    }
    $n = 0;
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM `produits` WHERE `categorie_id` = :c');
        $st->execute(['c' => $categorie_id]);
        $n += (int) $st->fetchColumn();
    } catch (PDOException $e) {
    }
    if (produits_sous_categories_table_exists()) {
        try {
            $st2 = $db->prepare('SELECT COUNT(*) FROM `produits_sous_categories` WHERE `categorie_id` = :c');
            $st2->execute(['c' => $categorie_id]);
            $n += (int) $st2->fetchColumn();
        } catch (PDOException $e) {
        }
    }
    return $n;
}
