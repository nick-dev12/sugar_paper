<?php
/**
 * Avis / notes produits (clients — commandes livrées et payées).
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/../conn/conn.php';

function produits_avis_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits_avis'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function produits_avis_snooze_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits_avis_popup_snooze'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/** Statuts commande éligibles à la notation (colis reçu / confirmé). */
function produits_avis_statuts_eligibles() {
    return ['livree', 'paye'];
}

/**
 * Normalise une note (tiers ou demi-étoile) entre 0.33 et 5.00.
 */
function produits_avis_normaliser_note($note) {
    $n = round((float) $note, 2);
    if ($n < 0.33) {
        $n = 0.33;
    }
    if ($n > 5.0) {
        $n = 5.0;
    }
    $steps = [];
    for ($i = 1; $i <= 15; $i++) {
        $steps[] = round($i / 3, 2);
    }
    $best = $steps[0];
    $bestDiff = abs($n - $best);
    foreach ($steps as $s) {
        $d = abs($n - $s);
        if ($d < $bestDiff) {
            $bestDiff = $d;
            $best = $s;
        }
    }
    return $best;
}

/**
 * Produits d'une commande payée/livrée non encore notés par l'utilisateur.
 *
 * @return array<int, array>
 */
function produits_avis_get_pending_for_user($user_id, $limit = 12, $commande_id = null) {
    global $db;
    $user_id = (int) $user_id;
    $commande_id = $commande_id !== null ? (int) $commande_id : 0;
    $limit = max(1, min(30, (int) $limit));
    if ($user_id <= 0 || !produits_avis_table_exists() || !$db) {
        return [];
    }
    $statuts = produits_avis_statuts_eligibles();
    $ph = implode(',', array_fill(0, count($statuts), '?'));
    $params = array_merge([$user_id], $statuts, [$user_id]);
    $filter_cmd = '';
    if ($commande_id > 0) {
        $filter_cmd = ' AND cmd.id = ?';
        $params[] = $commande_id;
    }
    try {
        $sql = "
            SELECT cp.produit_id, cp.commande_id, cmd.numero_commande, cmd.statut,
                   COALESCE(NULLIF(TRIM(p.nom), ''), 'Produit') AS nom,
                   p.image_principale
            FROM commande_produits cp
            INNER JOIN commandes cmd ON cmd.id = cp.commande_id
            INNER JOIN produits p ON p.id = cp.produit_id
            WHERE cmd.user_id = ?
              AND cmd.statut IN ($ph)
              AND NOT EXISTS (
                  SELECT 1 FROM produits_avis pa
                  WHERE pa.user_id = ?
                    AND pa.produit_id = cp.produit_id
                    AND pa.commande_id = cp.commande_id
              )
              $filter_cmd
            ORDER BY cmd.date_commande DESC, cp.id ASC
            LIMIT " . $limit;
        $st = $db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function produits_avis_get_snooze_date_maj($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !produits_avis_snooze_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT date_maj FROM produits_avis_popup_snooze WHERE user_id = :u LIMIT 1');
        $st->execute(['u' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['date_maj'])) {
            return null;
        }
        return (string) $row['date_maj'];
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Y a-t-il des produits à noter sur des commandes livrées après la date indiquée ?
 */
function produits_avis_has_pending_since($user_id, $since_datetime) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !produits_avis_table_exists() || !$db || empty($since_datetime)) {
        return false;
    }
    $statuts = produits_avis_statuts_eligibles();
    $ph = implode(',', array_fill(0, count($statuts), '?'));
    $params = array_merge([$user_id], $statuts, [$user_id], [$since_datetime]);
    try {
        $sql = "
            SELECT COUNT(*) FROM commande_produits cp
            INNER JOIN commandes cmd ON cmd.id = cp.commande_id
            WHERE cmd.user_id = ?
              AND cmd.statut IN ($ph)
              AND NOT EXISTS (
                  SELECT 1 FROM produits_avis pa
                  WHERE pa.user_id = ?
                    AND pa.produit_id = cp.produit_id
                    AND pa.commande_id = cp.commande_id
              )
              AND COALESCE(cmd.date_livraison, cmd.date_commande) > ?
        ";
        $st = $db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Commandes (parmi la liste) ayant au moins un produit non noté.
 *
 * @param int[] $commande_ids
 * @return array<int, bool>
 */
function produits_avis_commandes_notation_en_attente($user_id, array $commande_ids) {
    $out = [];
    foreach ($commande_ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $out[$id] = false;
        }
    }
    if (empty($out) || !produits_avis_table_exists()) {
        return $out;
    }
    foreach (produits_avis_get_pending_for_user((int) $user_id, 30) as $row) {
        $cid = (int) ($row['commande_id'] ?? 0);
        if ($cid > 0 && array_key_exists($cid, $out)) {
            $out[$cid] = true;
        }
    }
    return $out;
}

function produits_avis_commande_a_noter($user_id, $commande_id) {
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0) {
        return false;
    }
    $map = produits_avis_commandes_notation_en_attente((int) $user_id, [$commande_id]);
    return !empty($map[$commande_id]);
}

function produits_avis_popup_snooze_active($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !produits_avis_snooze_table_exists() || !$db) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT snooze_until FROM produits_avis_popup_snooze WHERE user_id = :u LIMIT 1');
        $st->execute(['u' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['snooze_until'])) {
            return false;
        }
        return strtotime((string) $row['snooze_until']) > time();
    } catch (PDOException $e) {
        return false;
    }
}

function produits_avis_set_popup_snooze($user_id, $hours = 48) {
    global $db;
    $user_id = (int) $user_id;
    $hours = max(1, min(168, (int) $hours));
    if ($user_id <= 0 || !produits_avis_snooze_table_exists() || !$db) {
        return false;
    }
    $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
    try {
        $st = $db->prepare("
            INSERT INTO produits_avis_popup_snooze (user_id, snooze_until)
            VALUES (:u, :until)
            ON DUPLICATE KEY UPDATE snooze_until = VALUES(snooze_until), date_maj = NOW()
        ");
        return $st->execute(['u' => $user_id, 'until' => $until]);
    } catch (PDOException $e) {
        return false;
    }
}

function produits_avis_clear_popup_snooze($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !produits_avis_snooze_table_exists() || !$db) {
        return false;
    }
    try {
        $st = $db->prepare('DELETE FROM produits_avis_popup_snooze WHERE user_id = :u');
        return $st->execute(['u' => $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function produits_avis_should_show_popup($user_id) {
    if (!produits_avis_table_exists()) {
        return false;
    }
    if (count(produits_avis_get_pending_for_user($user_id, 1)) === 0) {
        return false;
    }
    if (!produits_avis_popup_snooze_active($user_id)) {
        return true;
    }
    $since = produits_avis_get_snooze_date_maj($user_id);
    if ($since === null || $since === '') {
        return true;
    }
    return produits_avis_has_pending_since($user_id, $since);
}

/**
 * Enregistre ou met à jour une note.
 */
function produits_avis_save($user_id, $produit_id, $commande_id, $note) {
    global $db;
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    $commande_id = (int) $commande_id;
    $note = produits_avis_normaliser_note($note);
    if ($user_id <= 0 || $produit_id <= 0 || $commande_id <= 0 || !produits_avis_table_exists() || !$db) {
        return false;
    }
    require_once __DIR__ . '/model_commandes.php';
    $cmd = get_commande_by_id($commande_id, $user_id);
    if (!$cmd || !in_array((string) ($cmd['statut'] ?? ''), produits_avis_statuts_eligibles(), true)) {
        return false;
    }
    $found = false;
    foreach (get_commande_produits($commande_id) as $ln) {
        if ((int) ($ln['produit_id'] ?? 0) === $produit_id) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        return false;
    }
    try {
        $st = $db->prepare("
            INSERT INTO produits_avis (user_id, produit_id, commande_id, note, date_creation)
            VALUES (:u, :p, :c, :n, NOW())
            ON DUPLICATE KEY UPDATE note = VALUES(note), date_creation = NOW()
        ");
        return $st->execute(['u' => $user_id, 'p' => $produit_id, 'c' => $commande_id, 'n' => $note]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Moyennes par produit_id.
 *
 * @param int[] $produit_ids
 * @return array<int, array{moyenne: float, count: int}>
 */
function produits_avis_moyennes_for_products(array $produit_ids) {
    global $db;
    $out = [];
    if (!produits_avis_table_exists() || !$db) {
        return $out;
    }
    $ids = [];
    foreach ($produit_ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (empty($ids)) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = $db->prepare("
            SELECT produit_id, ROUND(AVG(note), 2) AS moyenne, COUNT(*) AS nb
            FROM produits_avis
            WHERE produit_id IN ($ph)
            GROUP BY produit_id
        ");
        $st->execute(array_values($ids));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = (int) ($row['produit_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $out[$pid] = [
                'moyenne' => (float) ($row['moyenne'] ?? 0),
                'count' => (int) ($row['nb'] ?? 0),
            ];
        }
    } catch (PDOException $e) {
    }
    return $out;
}

function produits_avis_enrich_products(array $products) {
    if (empty($products) || !produits_avis_table_exists()) {
        return $products;
    }
    $ids = [];
    foreach ($products as $p) {
        if (is_array($p) && !empty($p['id'])) {
            $ids[] = (int) $p['id'];
        }
    }
    $stats = produits_avis_moyennes_for_products($ids);
    foreach ($products as &$p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        if ($pid > 0 && isset($stats[$pid])) {
            $p['avis_moyenne'] = $stats[$pid]['moyenne'];
            $p['avis_count'] = $stats[$pid]['count'];
        } else {
            $p['avis_moyenne'] = 0.0;
            $p['avis_count'] = 0;
        }
    }
    unset($p);
    return $products;
}

/**
 * Moyenne des notes des produits d'une commande (tous clients).
 */
function produits_avis_moyenne_commande($commande_id) {
    global $db;
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0 || !produits_avis_table_exists() || !$db) {
        return ['moyenne' => 0.0, 'count' => 0];
    }
    try {
        $st = $db->prepare("
            SELECT ROUND(AVG(pa.note), 2) AS moyenne, COUNT(*) AS nb
            FROM produits_avis pa
            INNER JOIN commande_produits cp ON cp.produit_id = pa.produit_id AND cp.commande_id = :cid
            WHERE pa.produit_id IN (
                SELECT produit_id FROM commande_produits WHERE commande_id = :cid2
            )
        ");
        $st->execute(['cid' => $commande_id, 'cid2' => $commande_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) ($row['nb'] ?? 0) <= 0) {
            return ['moyenne' => 0.0, 'count' => 0];
        }
        return ['moyenne' => (float) $row['moyenne'], 'count' => (int) $row['nb']];
    } catch (PDOException $e) {
        return ['moyenne' => 0.0, 'count' => 0];
    }
}

function produits_avis_moyennes_commandes(array $commande_ids) {
    global $db;
    $out = [];
    if (!produits_avis_table_exists() || !$db || empty($commande_ids)) {
        return $out;
    }
    $ids = [];
    foreach ($commande_ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (empty($ids)) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $sql = "
            SELECT cp.commande_id, ROUND(AVG(pa.note), 2) AS moyenne, COUNT(DISTINCT pa.id) AS nb
            FROM commande_produits cp
            INNER JOIN produits_avis pa ON pa.produit_id = cp.produit_id
            WHERE cp.commande_id IN ($ph)
            GROUP BY cp.commande_id
        ";
        $st = $db->prepare($sql);
        $st->execute(array_values($ids));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int) ($row['commande_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $out[$cid] = [
                'moyenne' => (float) ($row['moyenne'] ?? 0),
                'count' => (int) ($row['nb'] ?? 0),
            ];
        }
    } catch (PDOException $e) {
    }
    return $out;
}

/**
 * Produits les mieux notés (moyenne des avis clients), pour vitrine accueil.
 *
 * @param int $max_rows  Nombre max de produits
 * @param int $min_avis  Nombre minimum d'avis pour être éligible
 * @return array
 */
function get_produits_mieux_notes_marketplace($max_rows = 40, $min_avis = 1)
{
    global $db;
    $max_rows = max(4, (int) $max_rows);
    $min_avis = max(1, (int) $min_avis);
    if (!produits_avis_table_exists() || !$db) {
        return [];
    }
    try {
        require_once __DIR__ . '/model_produits.php';
        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $region_sql = produits_region_sql_with_alias(null)['sql'];
        $sql = "
            SELECT p.*, " . $rj['categorie_nom_sql'] . " AS categorie_nom,
                   ROUND(AVG(pa.note), 2) AS avis_moyenne,
                   COUNT(pa.id) AS avis_count
            " . $vj['select'] . "
            FROM produits p
            INNER JOIN produits_avis pa ON pa.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $rj['join'] . "
            " . $vj['join'] . "
            WHERE p.statut IN ('actif', 'rupture_stock') $region_sql
            GROUP BY p.id
            HAVING avis_count >= " . (int) $min_avis . "
            ORDER BY avis_moyenne DESC, avis_count DESC, p.id DESC
            LIMIT " . (int) $max_rows;
        $stmt = $db->prepare($sql);
        produits_bind_region_stmt($stmt, null);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($produits) ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}
