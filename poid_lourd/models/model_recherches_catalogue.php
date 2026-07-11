<?php
/**
 * Journal des requêtes catalogue (recherche produits) — tendances accueil.
 * Procédural uniquement.
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Crée la table si absente.
 */
function recherches_catalogue_ensure_table()
{
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    global $db;
    if (!$db) {
        $ok = false;
        return false;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `recherches_catalogue` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `terme` varchar(500) NOT NULL DEFAULT '',
            `date_recherche` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `user_id` int(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_date_recherche` (`date_recherche`),
            KEY `idx_terme` (`terme`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
        return true;
    } catch (PDOException $e) {
        $ok = false;
        return false;
    }
}

/**
 * Enregistre une recherche lancée depuis le catalogue.
 */
function log_recherche_catalogue($terme, $user_id = null)
{
    $t = trim((string) $terme);
    if ($t === '') {
        return false;
    }
    if (function_exists('mb_strlen') && mb_strlen($t) > 500) {
        $t = mb_substr($t, 0, 500);
    } elseif (strlen($t) > 500) {
        $t = substr($t, 0, 500);
    }
    if (!recherches_catalogue_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $uid = $user_id !== null && (int) $user_id > 0 ? (int) $user_id : null;
        $stmt = $db->prepare('
            INSERT INTO recherches_catalogue (terme, user_id, date_recherche)
            VALUES (:terme, :user_id, NOW())
        ');
        return $stmt->execute(['terme' => $t, 'user_id' => $uid]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Termes les plus saisis (90 derniers jours).
 *
 * @return array<int, array{t:string, c:int}>
 */
function recherches_catalogue_top_termes($limit = 15)
{
    if (!recherches_catalogue_ensure_table()) {
        return [];
    }
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT LOWER(TRIM(terme)) AS t, COUNT(*) AS c
            FROM recherches_catalogue
            WHERE date_recherche >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND TRIM(terme) <> ''
            GROUP BY LOWER(TRIM(terme))
            ORDER BY c DESC, t ASC
            LIMIT " . (int) max(1, $limit) . "
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Produits correspondant aux recherches les plus fréquentes (dédoublonnés).
 * Pour alimentation de l’accueil ; mélange et limite côté appelant.
 *
 * @return array
 */
function get_produits_lies_aux_recherches_frequentes($max_uniques = 40)
{
    require_once __DIR__ . '/model_produits.php';
    $termes = recherches_catalogue_top_termes(15);
    if (empty($termes)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($termes as $row) {
        $q = isset($row['t']) ? trim((string) $row['t']) : '';
        if ($q === '') {
            continue;
        }
        $prods = search_produits_with_filters($q, null, null, null, 'date', 0, 8, null);
        if (empty($prods) || !is_array($prods)) {
            continue;
        }
        foreach ($prods as $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $p;
            if (count($out) >= $max_uniques) {
                break 2;
            }
        }
    }
    return $out;
}
