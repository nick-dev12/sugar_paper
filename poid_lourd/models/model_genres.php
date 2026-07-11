<?php
/**
 * Genres produits — liaison N-N produits_genres ; liaison optionnelle aux rayons (genres_categories_generales).
 */

/**
 * Table genres présente.
 */
function genres_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'genres'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Table pivot produits_genres présente.
 */
/**
 * Table pivot genres ↔ catégories générales (rayons).
 */
function genres_cg_links_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'genres_categories_generales'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function produits_genres_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits_genres'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Colonne produits.categorie_id accepte NULL (migration genres).
 */
function produits_categorie_id_accepts_null() {
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
            SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits' AND COLUMN_NAME = 'categorie_id'
        ");
        $r = $st ? $st->fetchColumn() : null;
        $cached = $r !== false && strtoupper((string) $r) === 'YES';
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Mode vendeur : genres (cases à cocher) au lieu des sous-catégories liées aux rayons.
 */
function vendeur_genres_mode_actif() {
    return genres_table_exists() && produits_genres_table_exists() && produits_categorie_id_accepts_null();
}

/**
 * @return array<int, array<string,mixed>>
 */
function genres_list_all() {
    global $db;
    if (!genres_table_exists()) {
        return [];
    }
    try {
        $st = $db->query('SELECT * FROM `genres` ORDER BY `sort_ordre` ASC, `nom` ASC');
        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_genre_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !genres_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM `genres` WHERE `id` = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function genre_nom_disponible($nom, $exclude_id = 0) {
    global $db;
    $nom = trim((string) $nom);
    $exclude_id = (int) $exclude_id;
    if ($nom === '' || !genres_table_exists()) {
        return false;
    }
    try {
        $sql = 'SELECT `id` FROM `genres` WHERE `nom` = :n';
        if ($exclude_id > 0) {
            $sql .= ' AND `id` != :ex';
        }
        $sql .= ' LIMIT 1';
        $st = $db->prepare($sql);
        $p = ['n' => $nom];
        if ($exclude_id > 0) {
            $p['ex'] = $exclude_id;
        }
        $st->execute($p);
        return $st->fetchColumn() === false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return int[]
 */
function get_categorie_generale_ids_for_genre($genre_id) {
    global $db;
    $genre_id = (int) $genre_id;
    if ($genre_id <= 0 || !genres_cg_links_table_exists()) {
        return [];
    }
    try {
        $st = $db->prepare('
            SELECT `categorie_generale_id` FROM `genres_categories_generales`
            WHERE `genre_id` = :g ORDER BY `categorie_generale_id` ASC
        ');
        $st->execute(['g' => $genre_id]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = (int) $r;
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int[] $categorie_generale_ids
 */
function save_genre_categorie_generale_links($genre_id, array $categorie_generale_ids) {
    global $db;
    $genre_id = (int) $genre_id;
    if ($genre_id <= 0 || !genres_cg_links_table_exists()) {
        return false;
    }
    $clean = [];
    foreach ($categorie_generale_ids as $x) {
        $i = (int) $x;
        if ($i > 0) {
            $clean[$i] = true;
        }
    }
    $clean = array_keys($clean);
    try {
        $db->beginTransaction();
        $st = $db->prepare('DELETE FROM `genres_categories_generales` WHERE `genre_id` = :g');
        $st->execute(['g' => $genre_id]);
        if (!empty($clean)) {
            $ins = $db->prepare('
                INSERT INTO `genres_categories_generales` (`genre_id`, `categorie_generale_id`)
                VALUES (:g, :c)
            ');
            foreach ($clean as $cg) {
                $ins->execute(['g' => $genre_id, 'c' => $cg]);
            }
        }
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function delete_genre_categorie_generale_links_for_genre($genre_id) {
    global $db;
    $genre_id = (int) $genre_id;
    if ($genre_id <= 0 || !genres_cg_links_table_exists()) {
        return;
    }
    try {
        $st = $db->prepare('DELETE FROM `genres_categories_generales` WHERE `genre_id` = :g');
        $st->execute(['g' => $genre_id]);
    } catch (PDOException $e) {
    }
}

/**
 * Nombre de genres liés à un rayon (au moins une ligne pivot pour ce rayon).
 */
function count_genres_linked_to_categorie_generale($categorie_generale_id) {
    global $db;
    $categorie_generale_id = (int) $categorie_generale_id;
    if ($categorie_generale_id <= 0 || !genres_cg_links_table_exists()) {
        return 0;
    }
    try {
        $st = $db->prepare('
            SELECT COUNT(DISTINCT `genre_id`) FROM `genres_categories_generales`
            WHERE `categorie_generale_id` = :c
        ');
        $st->execute(['c' => $categorie_generale_id]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Genres (table genres) liés à un rayon plateforme via genres_categories_generales.
 *
 * @return array<int, array<string, mixed>>
 */
function get_genres_linked_to_categorie_generale($categorie_generale_id) {
    global $db;
    $categorie_generale_id = (int) $categorie_generale_id;
    if ($categorie_generale_id <= 0 || !genres_cg_links_table_exists() || !genres_table_exists()) {
        return [];
    }
    try {
        $st = $db->prepare('
            SELECT g.*
            FROM `genres` g
            INNER JOIN `genres_categories_generales` gcg ON gcg.genre_id = g.id
            WHERE gcg.categorie_generale_id = :c
            ORDER BY g.sort_ordre ASC, g.nom ASC
        ');
        $st->execute(['c' => $categorie_generale_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function genre_id_is_allowed_for_categorie_generale($genre_id, $categorie_generale_id) {
    global $db;
    $genre_id = (int) $genre_id;
    $categorie_generale_id = (int) $categorie_generale_id;
    if ($genre_id <= 0 || $categorie_generale_id <= 0 || !genres_cg_links_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('
            SELECT 1 FROM `genres_categories_generales`
            WHERE `genre_id` = :g AND `categorie_generale_id` = :c LIMIT 1
        ');
        $st->execute(['g' => $genre_id, 'c' => $categorie_generale_id]);
        return (bool) $st->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function genre_insert_row($nom, $description, $image, $sort_ordre) {
    global $db;
    if (!genres_table_exists() || !genre_nom_disponible($nom, 0)) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO `genres` (`nom`, `description`, `image`, `sort_ordre`, `date_creation`)
            VALUES (:nom, :descr, :img, :so, NOW())
        ');
        if ($st->execute([
            'nom' => $nom,
            'descr' => $description !== null && (string) $description !== '' ? (string) $description : null,
            'img' => $image !== null && (string) $image !== '' ? (string) $image : null,
            'so' => (int) $sort_ordre,
        ])) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
    }
    return false;
}

function genre_update_row($id, $nom, $description, $image, $sort_ordre) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !genres_table_exists() || !get_genre_by_id($id)) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '' || !genre_nom_disponible($nom, $id)) {
        return false;
    }
    try {
        $st = $db->prepare('
            UPDATE `genres` SET `nom` = :nom, `description` = :descr, `image` = :img, `sort_ordre` = :so
            WHERE `id` = :id
        ');
        return $st->execute([
            'id' => $id,
            'nom' => $nom,
            'descr' => $description !== null && (string) $description !== '' ? (string) $description : null,
            'img' => $image !== null && (string) $image !== '' ? (string) $image : null,
            'so' => (int) $sort_ordre,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function genre_delete_row($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !genres_table_exists()) {
        return false;
    }
    if (produits_genres_table_exists()) {
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `produits_genres` WHERE `genre_id` = :id');
            $st->execute(['id' => $id]);
            if ((int) $st->fetchColumn() > 0) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    delete_genre_categorie_generale_links_for_genre($id);
    try {
        $st = $db->prepare('DELETE FROM `genres` WHERE `id` = :id');
        return $st->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return int[]
 */
function get_genre_ids_for_produit($produit_id) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_genres_table_exists()) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT `genre_id` FROM `produits_genres` WHERE `produit_id` = :p ORDER BY `genre_id` ASC');
        $st->execute(['p' => $produit_id]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = (int) $r;
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int[] $genre_ids
 */
function save_produits_genres_for_produit($produit_id, array $genre_ids) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_genres_table_exists()) {
        return false;
    }
    $ids = [];
    foreach ($genre_ids as $g) {
        $i = (int) $g;
        if ($i > 0 && get_genre_by_id($i)) {
            $ids[$i] = true;
        }
    }
    $ids = array_keys($ids);
    try {
        $db->beginTransaction();
        $st = $db->prepare('DELETE FROM `produits_genres` WHERE `produit_id` = :p');
        $st->execute(['p' => $produit_id]);
        if (!empty($ids)) {
            $ins = $db->prepare('INSERT INTO `produits_genres` (`produit_id`, `genre_id`) VALUES (:p, :g)');
            foreach ($ids as $gid) {
                $ins->execute(['p' => $produit_id, 'g' => $gid]);
            }
        }
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
 * Nombre de produits liés à un genre (pivot).
 */
function count_produits_par_genre_id($genre_id) {
    global $db;
    $genre_id = (int) $genre_id;
    if ($genre_id <= 0 || !produits_genres_table_exists()) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM `produits_genres` WHERE `genre_id` = :g');
        $st->execute(['g' => $genre_id]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function delete_produits_genres_for_produit($produit_id) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_genres_table_exists()) {
        return;
    }
    try {
        $st = $db->prepare('DELETE FROM `produits_genres` WHERE `produit_id` = :p');
        $st->execute(['p' => $produit_id]);
    } catch (PDOException $e) {
    }
}
