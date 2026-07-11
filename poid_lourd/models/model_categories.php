<?php
/**
 * Modèle pour la gestion des catégories
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Colonne présente sur la table categories
 */
function categories_table_has_column($name) {
    static $map = null;
    global $db;
    if (!$db) {
        return false;
    }
    $field = (string) $name;
    if ($field === '') {
        return false;
    }
    if ($map === null) {
        $map = [];
        try {
            $st = $db->query('SHOW COLUMNS FROM `categories`');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[$row['Field']] = true;
            }
        } catch (PDOException $e) {
            $map = [];
        }
    }
    return !empty($map[$field]);
}

/**
 * Récupère toutes les catégories actives
 * @return array|false Tableau des catégories ou False en cas d'erreur
 */
function get_all_categories()
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories ORDER BY nom ASC");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Catégories ayant au moins un produit actif pour ce vendeur (boutique)
 */
function get_all_categories_for_vendeur($vendeur_id)
{
    global $db;

    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id')) {
        return get_all_categories();
    }
    $vid = (int) $vendeur_id;
    if ($vid <= 0) {
        return [];
    }

    try {
        $stmt = $db->prepare("
            SELECT DISTINCT c.*
            FROM categories c
            INNER JOIN produits p ON p.categorie_id = c.id AND p.statut IN ('actif', 'rupture_stock')
            WHERE p.admin_id = :vid
            ORDER BY c.nom ASC
        ");
        $stmt->execute(['vid' => $vid]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Liste des catégories pour la page stock vendeur : uniquement les siennes,
 * sans les rayons plateforme (`est_plateforme` sans propriétaire).
 */
function get_categories_for_vendeur_stock($vendeur_id) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    if ($vendeur_id <= 0 || !categories_table_has_column('admin_id')) {
        return [];
    }
    require_once __DIR__ . '/model_produits.php';
    $with_orphan = produits_has_column('admin_id');
    $exclude_plat = '';
    if (categories_table_has_column('est_plateforme')) {
        $exclude_plat = ' AND NOT (c.`est_plateforme` = 1 AND (c.`admin_id` IS NULL OR c.`admin_id` = 0)) ';
    }
    // Même nom qu’un rayon categories_generales, sans propriétaire = doublon plateforme
    $exclude_nom_generale = '';
    if (categories_generales_table_exists()) {
        $exclude_nom_generale = '
                AND NOT (
                    (c.`admin_id` IS NULL OR c.`admin_id` = 0)
                    AND EXISTS (
                        SELECT 1 FROM `categories_generales` cg WHERE cg.`nom` = c.`nom`
                    )
                )';
    }
    try {
        if ($with_orphan) {
            $sql = "
                SELECT DISTINCT c.*
                FROM `categories` c
                WHERE (
                    c.`admin_id` = :v
                    OR (
                        (c.`admin_id` IS NULL OR c.`admin_id` = 0)
                        AND EXISTS (
                            SELECT 1 FROM `produits` p
                            WHERE p.`categorie_id` = c.`id` AND p.`admin_id` = :v2
                        )
                    )
                )
                {$exclude_plat}
                {$exclude_nom_generale}
                ORDER BY c.`nom` ASC
            ";
            $st = $db->prepare($sql);
            $st->execute(['v' => $vendeur_id, 'v2' => $vendeur_id]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $sql = "
            SELECT c.*
            FROM `categories` c
            WHERE c.`admin_id` = :v
            {$exclude_plat}
            {$exclude_nom_generale}
            ORDER BY c.`nom` ASC
        ";
        $st = $db->prepare($sql);
        $st->execute(['v' => $vendeur_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Remplit la table pivot pour les sous-catégories plateforme qui n’y figurent pas encore (après migration ou données anciennes).
 */
function plateforme_ensure_liaison_rows_for_legacy_categories() {
    if (!categories_generales_liaisons_table_exists() || !categories_has_categorie_generale_id_column()) {
        return;
    }
    global $db;
    try {
        $db->exec("
            INSERT IGNORE INTO `categories_categories_generales` (`categorie_id`, `categorie_generale_id`)
            SELECT c.`id`, c.`categorie_generale_id`
            FROM `categories` c
            WHERE c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0
              AND (c.`admin_id` IS NULL OR c.`admin_id` = 0)
              AND NOT EXISTS (
                SELECT 1 FROM `categories_categories_generales` x WHERE x.`categorie_id` = c.`id`
              )
        ");
    } catch (PDOException $e) {
    }
}

/**
 * Sous-catégories communes (super admin) pour formulaires vendeur : categories.admin_id NULL + categorie_generale_id.
 */
function get_plateforme_sous_categories_for_form() {
    global $db;
    if (!categories_has_categorie_generale_id_column() || !categories_generales_table_exists()) {
        return [];
    }
    plateforme_ensure_liaison_rows_for_legacy_categories();
    try {
        if (categories_generales_liaisons_table_exists()) {
            $st = $db->query("
                SELECT c.`id`, c.`nom`, c.`categorie_generale_id`, c.`description`, c.`image`,
                       GROUP_CONCAT(cg.`nom` ORDER BY cg.`sort_ordre` ASC, cg.`nom` ASC SEPARATOR ', ') AS `generale_nom`,
                       MIN(cg.`sort_ordre`) AS `generale_sort`
                FROM `categories` c
                INNER JOIN `categories_categories_generales` ccg ON ccg.`categorie_id` = c.`id`
                INNER JOIN `categories_generales` cg ON cg.`id` = ccg.`categorie_generale_id`
                WHERE (c.`admin_id` IS NULL OR c.`admin_id` = 0)
                GROUP BY c.`id`, c.`nom`, c.`categorie_generale_id`, c.`description`, c.`image`, c.`sort_ordre`
                ORDER BY `generale_sort` ASC, c.`nom` ASC
            ");
        } else {
            $st = $db->query("
                SELECT c.`id`, c.`nom`, c.`categorie_generale_id`, c.`description`, c.`image`,
                       cg.`nom` AS `generale_nom`, cg.`sort_ordre` AS `generale_sort`
                FROM `categories` c
                INNER JOIN `categories_generales` cg ON cg.`id` = c.`categorie_generale_id`
                WHERE (c.`admin_id` IS NULL OR c.`admin_id` = 0)
                  AND c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0
                ORDER BY cg.`sort_ordre` ASC, cg.`nom` ASC, c.`nom` ASC
            ");
        }
        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Catégories affichées dans Stock vendeur : sous-catégories plateforme ayant au moins un produit de la boutique.
 */
function get_categories_platform_for_vendeur_stock($vendeur_id) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    if ($vendeur_id <= 0 || !categories_has_categorie_generale_id_column()) {
        return [];
    }
    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id')) {
        return [];
    }
    try {
        $st = $db->prepare("
            SELECT DISTINCT c.*
            FROM `categories` c
            INNER JOIN `produits` p ON p.`categorie_id` = c.`id` AND p.`admin_id` = :vid
            WHERE (c.`admin_id` IS NULL OR c.`admin_id` = 0)
              AND c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0
            ORDER BY c.`nom` ASC
        ");
        $st->execute(['vid' => $vendeur_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Rayons (categories_generales) pour le filtre stock vendeur :
 * uniquement les catégories parentes où la boutique a publié au moins un produit.
 *
 * @return array<int, array{id:int,nom:string,sort_ordre?:int}>
 */
function get_generales_for_vendeur_stock_filter($vendeur_id) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    if ($vendeur_id <= 0 || !categories_generales_table_exists()) {
        return [];
    }
    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id')) {
        return [];
    }

    $has_pcg = produits_has_column('categorie_generale_id');
    $has_ccg = categories_has_categorie_generale_id_column();
    $has_pivot = categories_generales_liaisons_table_exists();

    $joins = ' INNER JOIN `produits` p ON p.`admin_id` = :vid LEFT JOIN `categories` c ON c.`id` = p.`categorie_id` ';
    $conds = [];

    if ($has_pcg) {
        $conds[] = '(p.`categorie_generale_id` IS NOT NULL AND p.`categorie_generale_id` > 0 AND p.`categorie_generale_id` = cg.`id`)';
    }
    if ($has_ccg) {
        $conds[] = '(c.`categorie_generale_id` IS NOT NULL AND c.`categorie_generale_id` > 0 AND c.`categorie_generale_id` = cg.`id`)';
    }
    if ($has_pivot) {
        $conds[] = 'EXISTS (
            SELECT 1 FROM `categories_categories_generales` ccg
            WHERE ccg.`categorie_id` = p.`categorie_id` AND ccg.`categorie_generale_id` = cg.`id`
        )';
    }
    if (empty($conds)) {
        return [];
    }

    try {
        $sql = '
            SELECT DISTINCT cg.`id`, cg.`nom`, cg.`sort_ordre`
            FROM `categories_generales` cg
            ' . $joins . '
            WHERE (' . implode(' OR ', $conds) . ')
            ORDER BY cg.`sort_ordre` ASC, cg.`nom` ASC
        ';
        $st = $db->prepare($sql);
        $st->execute(['vid' => $vendeur_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Résout l'ID du rayon (categories_generales) d'un produit pour filtres stock.
 */
function produit_rayon_id_resolu($produit) {
    if (!is_array($produit)) {
        return 0;
    }
    if (!empty($produit['categorie_generale_id'])) {
        return (int) $produit['categorie_generale_id'];
    }
    $cid = (int) ($produit['categorie_id'] ?? 0);
    if ($cid <= 0) {
        return 0;
    }
    if (categories_has_categorie_generale_id_column()) {
        $cat = get_categorie_by_id($cid);
        if ($cat && !empty($cat['categorie_generale_id'])) {
            return (int) $cat['categorie_generale_id'];
        }
    }
    if (function_exists('plateforme_get_rayons_ids_for_categorie')) {
        $rayons = plateforme_get_rayons_ids_for_categorie($cid);
        if (!empty($rayons)) {
            return (int) $rayons[0];
        }
    }
    return 0;
}

/**
 * Top 2 rayons (categories_generales) pour la vitrine boutique vendeur.
 * Score : visites produits + ventes (quantités), repli sur le nombre de produits actifs.
 *
 * @return array<int, array<string, mixed>>
 */
function get_top_rayons_for_vendeur_vitrine($vendeur_id, $limit = 2) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    $limit = max(1, (int) $limit);
    if ($vendeur_id <= 0) {
        return [];
    }

    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id') || !categories_generales_table_exists()) {
        return _top_categories_vendeur_vitrine_fallback($vendeur_id, $limit);
    }

    try {
        $st = $db->prepare("
            SELECT p.*
            FROM produits p
            WHERE p.admin_id = :vid AND p.statut IN ('actif', 'rupture_stock')
        ");
        $st->execute(['vid' => $vendeur_id]);
        $produits = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return _top_categories_vendeur_vitrine_fallback($vendeur_id, $limit);
    }

    if (empty($produits)) {
        return [];
    }

    $produit_ids = [];
    $produit_par_id = [];
    $generale_produits = [];
    foreach ($produits as $p) {
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $produit_ids[] = $pid;
        $produit_par_id[$pid] = $p;
        $gid = produit_rayon_id_resolu($p);
        if ($gid > 0) {
            if (!isset($generale_produits[$gid])) {
                $generale_produits[$gid] = 0;
            }
            $generale_produits[$gid]++;
        }
    }

    if (empty($generale_produits)) {
        return _top_categories_vendeur_vitrine_fallback($vendeur_id, $limit);
    }

    $scores = [];
    foreach (array_keys($generale_produits) as $gid) {
        $scores[(int) $gid] = [
            'visites' => 0,
            'ventes' => 0,
            'produits' => (int) $generale_produits[$gid],
            'score' => (int) $generale_produits[$gid],
        ];
    }

    if (!empty($produit_ids)) {
        $ph = implode(', ', array_fill(0, count($produit_ids), '?'));
        try {
            $stV = $db->prepare("
                SELECT pv.produit_id, COUNT(*) AS nb
                FROM produits_visites pv
                WHERE pv.produit_id IN ($ph)
                GROUP BY pv.produit_id
            ");
            $stV->execute($produit_ids);
            foreach ($stV->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $pid = (int) ($row['produit_id'] ?? 0);
                $nb = (int) ($row['nb'] ?? 0);
                if ($pid <= 0 || $nb <= 0 || !isset($produit_par_id[$pid])) {
                    continue;
                }
                $gid = produit_rayon_id_resolu($produit_par_id[$pid]);
                if ($gid > 0 && isset($scores[$gid])) {
                    $scores[$gid]['visites'] += $nb;
                    $scores[$gid]['score'] += $nb;
                }
            }
        } catch (PDOException $e) {
        }

        try {
            $stS = $db->prepare("
                SELECT cp.produit_id, SUM(cp.quantite) AS nb
                FROM commande_produits cp
                WHERE cp.produit_id IN ($ph)
                GROUP BY cp.produit_id
            ");
            $stS->execute($produit_ids);
            foreach ($stS->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $pid = (int) ($row['produit_id'] ?? 0);
                $nb = (int) ($row['nb'] ?? 0);
                if ($pid <= 0 || $nb <= 0 || !isset($produit_par_id[$pid])) {
                    continue;
                }
                $gid = produit_rayon_id_resolu($produit_par_id[$pid]);
                if ($gid > 0 && isset($scores[$gid])) {
                    $scores[$gid]['ventes'] += $nb;
                    $scores[$gid]['score'] += $nb * 2;
                }
            }
        } catch (PDOException $e) {
        }
    }

    uasort($scores, function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        if ($a['ventes'] !== $b['ventes']) {
            return $b['ventes'] <=> $a['ventes'];
        }
        if ($a['visites'] !== $b['visites']) {
            return $b['visites'] <=> $a['visites'];
        }
        return $b['produits'] <=> $a['produits'];
    });

    $top_ids = array_slice(array_keys($scores), 0, $limit);
    $out = [];
    foreach ($top_ids as $gid) {
        $row = get_categorie_generale_by_id((int) $gid);
        if (!$row || empty($row['nom'])) {
            continue;
        }
        $row['is_generale'] = true;
        $row['score_visites'] = $scores[$gid]['visites'];
        $row['score_ventes'] = $scores[$gid]['ventes'];
        $row['nb_produits'] = $scores[$gid]['produits'];
        $out[] = $row;
    }

    if (count($out) < $limit) {
        $fallback = _top_categories_vendeur_vitrine_fallback($vendeur_id, $limit - count($out));
        foreach ($fallback as $fb) {
            $dup = false;
            foreach ($out as $ex) {
                if ((int) ($ex['id'] ?? 0) === (int) ($fb['id'] ?? 0) && !empty($ex['is_generale']) === !empty($fb['is_generale'])) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $out[] = $fb;
            }
        }
    }

    return array_slice($out, 0, $limit);
}

/**
 * Repli vitrine : catégories feuille du vendeur (visites + ventes).
 *
 * @return array<int, array<string, mixed>>
 */
function _top_categories_vendeur_vitrine_fallback($vendeur_id, $limit = 2) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    $limit = max(1, (int) $limit);
    $cats = get_all_categories_for_vendeur($vendeur_id);
    if (empty($cats)) {
        return [];
    }

    $scores = [];
    foreach ($cats as $c) {
        $cid = (int) ($c['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $scores[$cid] = ['visites' => 0, 'ventes' => 0, 'produits' => 0, 'score' => 0, 'row' => $c];
    }

    try {
        $st = $db->prepare("
            SELECT p.categorie_id,
                   COUNT(DISTINCT pv.id) AS nb_visites,
                   COALESCE(SUM(cp.quantite), 0) AS nb_ventes,
                   COUNT(DISTINCT p.id) AS nb_produits
            FROM produits p
            LEFT JOIN produits_visites pv ON pv.produit_id = p.id
            LEFT JOIN commande_produits cp ON cp.produit_id = p.id
            WHERE p.admin_id = :vid AND p.statut IN ('actif', 'rupture_stock')
            GROUP BY p.categorie_id
        ");
        $st->execute(['vid' => $vendeur_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $cid = (int) ($row['categorie_id'] ?? 0);
            if ($cid <= 0 || !isset($scores[$cid])) {
                continue;
            }
            $v = (int) ($row['nb_visites'] ?? 0);
            $s = (int) ($row['nb_ventes'] ?? 0);
            $p = (int) ($row['nb_produits'] ?? 0);
            $scores[$cid]['visites'] = $v;
            $scores[$cid]['ventes'] = $s;
            $scores[$cid]['produits'] = $p;
            $scores[$cid]['score'] = $v + ($s * 2) + $p;
        }
    } catch (PDOException $e) {
        foreach ($scores as $cid => &$sc) {
            $sc['score'] = 1;
        }
        unset($sc);
    }

    uasort($scores, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $out = [];
    foreach (array_slice($scores, 0, $limit, true) as $sc) {
        $row = $sc['row'];
        $row['is_generale'] = false;
        $row['score_visites'] = $sc['visites'];
        $row['score_ventes'] = $sc['ventes'];
        $row['nb_produits'] = $sc['produits'];
        $out[] = $row;
    }
    return $out;
}

/**
 * Sous-catégorie définie par la plateforme (categories.categorie_generale_id renseigné, sans vendeur propriétaire).
 */
function categorie_est_sous_categorie_plateforme($categorie) {
    if (!is_array($categorie)) {
        $categorie = get_categorie_by_id((int) $categorie);
    }
    if (!$categorie) {
        return false;
    }
    $aid = isset($categorie['admin_id']) ? (int) $categorie['admin_id'] : 0;
    if ($aid !== 0) {
        return false;
    }
    $cg = isset($categorie['categorie_generale_id']) ? (int) $categorie['categorie_generale_id'] : 0;
    if ($cg > 0) {
        return true;
    }
    if (categories_generales_liaisons_table_exists()) {
        $ids = plateforme_get_rayons_ids_for_categorie((int) ($categorie['id'] ?? 0));
        return !empty($ids);
    }
    return false;
}

/**
 * Le vendeur peut modifier / supprimer uniquement ses propres catégories (héritage).
 */
function categorie_est_modifiable_par_vendeur($categorie_id, $vendeur_id) {
    $categorie_id = (int) $categorie_id;
    $vendeur_id = (int) $vendeur_id;
    if ($categorie_id <= 0 || $vendeur_id <= 0) {
        return false;
    }
    $c = get_categorie_by_id($categorie_id);
    if (!$c) {
        return false;
    }
    return (int) ($c['admin_id'] ?? 0) === $vendeur_id;
}

/**
 * Le vendeur peut utiliser cette catégorie pour ses produits (propriétaire ou produits déjà liés).
 */
function categorie_est_utilisable_par_vendeur($categorie_id, $vendeur_id) {
    $categorie_id = (int) $categorie_id;
    $vendeur_id = (int) $vendeur_id;
    if ($categorie_id <= 0 || $vendeur_id <= 0) {
        return false;
    }
    $c = get_categorie_by_id($categorie_id);
    if (!$c) {
        return false;
    }
    if (categorie_est_sous_categorie_plateforme($c)) {
        return true;
    }
    if ((int) ($c['admin_id'] ?? 0) === $vendeur_id) {
        return true;
    }
    require_once __DIR__ . '/model_produits.php';
    if (!produits_has_column('admin_id')) {
        return false;
    }
    global $db;
    try {
        $st = $db->prepare('SELECT 1 FROM `produits` WHERE `categorie_id` = :c AND `admin_id` = :v LIMIT 1');
        $st->execute(['c' => $categorie_id, 'v' => $vendeur_id]);
        return (bool) $st->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une catégorie par son ID
 * @param int $id L'ID de la catégorie
 * @return array|false Les données de la catégorie ou False si non trouvée
 */
function get_categorie_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

        return $categorie ? $categorie : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une catégorie par son nom
 * @param string $nom Le nom de la catégorie
 * @return array|false Les données de la catégorie ou False si non trouvée
 */
function get_categorie_by_nom($nom)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE nom = :nom");
        $stmt->execute(['nom' => $nom]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

        return $categorie ? $categorie : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée une nouvelle catégorie
 * @param string $nom Le nom de la catégorie
 * @param string $description La description
 * @param string|null $image Le chemin de l'image
 * @param int|null $admin_id Propriétaire vendeur (marketplace)
 * @param int|null $categorie_generale_id Rayon plateforme (categories_generales.id)
 * @return int|false L'ID de la catégorie créée ou False en cas d'erreur
 */
function create_categorie($nom, $description = null, $image = null, $admin_id = null, $categorie_generale_id = null)
{
    global $db;

    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }

    $cols = ['`nom`', '`description`', '`image`', '`date_creation`'];
    $holders = [':nom', ':description', ':image', 'NOW()'];
    $params = [
        'nom' => $nom,
        'description' => $description,
        'image' => $image,
    ];

    $aid = $admin_id !== null ? (int) $admin_id : 0;
    if ($aid > 0 && categories_table_has_column('admin_id')) {
        $cols[] = '`admin_id`';
        $holders[] = ':admin_id';
        $params['admin_id'] = $aid;
    }

    $cgid = $categorie_generale_id !== null ? (int) $categorie_generale_id : 0;
    if ($cgid > 0 && categories_table_has_column('categorie_generale_id')) {
        $cols[] = '`categorie_generale_id`';
        $holders[] = ':categorie_generale_id';
        $params['categorie_generale_id'] = $cgid;
    }

    try {
        $sql = 'INSERT INTO `categories` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')';
        $stmt = $db->prepare($sql);
        if ($stmt->execute($params)) {
            return (int) $db->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour une catégorie
 * @param int $id L'ID de la catégorie
 * @param string $nom Le nom de la catégorie
 * @param string $description La description
 * @param string|null $image Le chemin de l'image
 * @return bool True en cas de succès, False sinon
 */
function update_categorie($id, $nom, $description = null, $image = null)
{
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE categories SET
                nom = :nom,
                description = :description,
                image = :image
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nom' => $nom,
            'description' => $description,
            'image' => $image
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une catégorie
 * @param int $id L'ID de la catégorie
 * @return bool True en cas de succès, False sinon
 */
function delete_categorie($id)
{
    global $db;

    try {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        if (categories_generales_liaisons_table_exists()) {
            $st = $db->prepare('DELETE FROM `categories_categories_generales` WHERE `categorie_id` = :id');
            $st->execute(['id' => $id]);
        }
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si une catégorie a des produits associés
 * @param int $categorie_id L'ID de la catégorie
 * @return bool True si la catégorie a des produits, False sinon
 */
function categorie_has_produits($categorie_id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = :id");
        $stmt->execute(['id' => $categorie_id]);
        $count = $stmt->fetchColumn();

        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère toutes les catégories avec le nombre de produits
 * @return array Tableau des catégories avec le nombre de produits
 */
function get_all_categories_with_count()
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT c.*, COUNT(p.id) as nb_produits
            FROM categories c
            LEFT JOIN produits p ON c.id = p.categorie_id AND p.statut IN ('actif', 'rupture_stock')
            GROUP BY c.id
            ORDER BY c.nom ASC
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les catégories les plus populaires (basées sur les visites et commandes)
 * @param int $limit Nombre maximum de catégories à retourner (par défaut 5)
 * @return array Tableau des catégories les plus populaires mélangées aléatoirement
 */
function get_top_categories($limit = 5)
{
    global $db;

    try {
        // Récupérer les catégories avec le nombre de visites et de commandes
        $stmt = $db->prepare("
            SELECT 
                c.*,
                COALESCE(visites_stats.nb_visites, 0) as nb_visites,
                COALESCE(commandes_stats.nb_commandes, 0) as nb_commandes,
                (COALESCE(visites_stats.nb_visites, 0) + COALESCE(commandes_stats.nb_commandes, 0)) as score_popularite
            FROM categories c
            LEFT JOIN (
                SELECT p.categorie_id, COUNT(pv.id) as nb_visites
                FROM produits_visites pv
                INNER JOIN produits p ON pv.produit_id = p.id
                WHERE p.statut IN ('actif', 'rupture_stock')
                GROUP BY p.categorie_id
            ) visites_stats ON c.id = visites_stats.categorie_id
            LEFT JOIN (
                SELECT p.categorie_id, COUNT(cp.id) as nb_commandes
                FROM commande_produits cp
                INNER JOIN produits p ON cp.produit_id = p.id
                WHERE p.statut IN ('actif', 'rupture_stock')
                GROUP BY p.categorie_id
            ) commandes_stats ON c.id = commandes_stats.categorie_id
            HAVING score_popularite > 0
            ORDER BY score_popularite DESC, c.nom ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT); // Récupérer plus pour avoir de la variété
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucune catégorie avec visites/commandes, récupérer toutes les catégories
        if (empty($categories)) {
            $categories = get_all_categories();
        }

        // Mélanger aléatoirement les catégories
        if (!empty($categories)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($categories);
            // Limiter au nombre demandé
            $categories = array_slice($categories, 0, $limit);
        }

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner toutes les catégories mélangées
        $categories = get_all_categories();
        if (!empty($categories)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($categories);
            $categories = array_slice($categories, 0, $limit);
        }
        return $categories ? $categories : [];
    }
}

/**
 * Hiérarchie catégories (migration marketplace) active si colonne parent_id existe
 */
function categories_hierarchy_enabled() {
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
        $st = $db->query("SHOW COLUMNS FROM `categories` LIKE 'parent_id'");
        $cached = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Colonne est_plateforme (rayons officiels mega-menu / formulaire vendeur)
 */
function categories_est_plateforme_column() {
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
        $st = $db->query("SHOW COLUMNS FROM `categories` LIKE 'est_plateforme'");
        $cached = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Nettoie une chaîne de classes Font Awesome pour l’attribut HTML class (XSS).
 */
function fa_icon_classes_sanitize($s) {
    $s = trim((string) $s);
    $s = preg_replace('/\s+/', ' ', $s);
    if ($s === '' || !preg_match('/^[a-zA-Z0-9\s\-_]+$/', $s)) {
        return 'fa-solid fa-layer-group';
    }
    return $s;
}

/**
 * Classe Font Awesome pour icône menu (champ icone en BDD)
 */
function categorie_fa_icon_class(array $row) {
    $ic_raw = trim((string) ($row['icone'] ?? ''));
    if ($ic_raw === '' || stripos($ic_raw, 'fa-') === false) {
        return fa_icon_classes_sanitize('fa-solid fa-layer-group');
    }
    if (preg_match('/^(fa-solid|fa-regular|fa-brands)\s+/i', $ic_raw)) {
        return fa_icon_classes_sanitize($ic_raw);
    }
    return fa_icon_classes_sanitize('fa-solid ' . $ic_raw);
}

/**
 * URL publique de l’image d’une catégorie / sous-catégorie, ou null si absente ou fichier introuvable.
 */
function categorie_image_public_path($categorie_row) {
    if (!is_array($categorie_row)) {
        return null;
    }
    $im = trim((string) ($categorie_row['image'] ?? ''));
    if ($im === '') {
        return null;
    }
    $im = ltrim(str_replace('\\', '/', $im), '/');
    if (!function_exists('image_optimizer_resolve_relative_path')) {
        $opt = dirname(__DIR__) . '/includes/image_optimizer.php';
        if (is_file($opt)) {
            require_once $opt;
        }
    }
    if (function_exists('image_optimizer_resolve_relative_path')) {
        $im = image_optimizer_resolve_relative_path($im);
    }
    $root = dirname(__DIR__);
    $file_path = $root . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $im);
    if (!is_file($file_path)) {
        return null;
    }
    return '/upload/' . $im;
}

/**
 * Sous-catégories plateforme rattachées à un rayon (categories_generales), avec visuels.
 *
 * @return array<int, array<string, mixed>>
 */
function get_subcategories_for_categorie_generale($generale_id) {
    global $db;
    $generale_id = (int) $generale_id;
    if ($generale_id <= 0 || !categories_has_categorie_generale_id_column() || !categories_generales_table_exists()) {
        return [];
    }
    try {
        if (categories_generales_liaisons_table_exists()) {
            $st = $db->prepare("
                SELECT c.`id`, c.`nom`, c.`image`, c.`icone`, c.`sort_ordre`
                FROM `categories` c
                INNER JOIN `categories_categories_generales` ccg ON ccg.`categorie_id` = c.`id`
                WHERE ccg.`categorie_generale_id` = :gid
                  AND (c.`admin_id` IS NULL OR c.`admin_id` = 0)
                ORDER BY c.`sort_ordre` ASC, c.`nom` ASC
            ");
            $st->execute(['gid' => $generale_id]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $st = $db->prepare("
            SELECT c.`id`, c.`nom`, c.`image`, c.`icone`, c.`sort_ordre`
            FROM `categories` c
            WHERE c.`categorie_generale_id` = :gid
              AND (c.`admin_id` IS NULL OR c.`admin_id` = 0)
            ORDER BY c.`sort_ordre` ASC, c.`nom` ASC
        ");
        $st->execute(['gid' => $generale_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Table dédiée catégories générales (rayons plateforme)
 */
function categories_generales_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories_generales'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Colonnes attr_* présentes sur categories_generales (migration attributs produit).
 */
function categories_generales_attr_columns_exist() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db || !categories_generales_table_exists()) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories_generales' AND COLUMN_NAME = 'attr_poids'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Colonne image présente sur categories_generales (visuel rayon).
 */
function categories_generales_image_column_exists() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db || !categories_generales_table_exists()) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories_generales' AND COLUMN_NAME = 'image'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Supprime un fichier image rayon (chemin relatif sous upload/).
 */
function categories_generales_delete_image_file($image_path) {
    $image_path = trim(str_replace('\\', '/', (string) $image_path), '/');
    if ($image_path === '') {
        return;
    }
    $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image_path);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * @param array<string,mixed>|false|null $row Ligne categories_generales
 * @return array{poids:bool,taille:bool,mesure:bool,couleur:bool}
 */
function categorie_generale_parse_attributs_row($row) {
    $defaults = ['poids' => true, 'taille' => true, 'mesure' => true, 'couleur' => true];
    if (!is_array($row) || $row === []) {
        return $defaults;
    }
    if (!array_key_exists('attr_poids', $row)) {
        return $defaults;
    }
    return [
        'poids' => !empty((int) $row['attr_poids']),
        'taille' => !empty((int) $row['attr_taille']),
        'mesure' => !empty((int) $row['attr_mesure']),
        'couleur' => !empty((int) $row['attr_couleur']),
    ];
}

/**
 * Carte rayon_id → { p,t,m,c } pour le formulaire vendeur (JSON).
 *
 * @return array<string, array{p:int,t:int,m:int,c:int}>
 */
function get_categorie_generale_attributs_map_for_js() {
    $list = categories_generales_list_all();
    $out = [];
    foreach ($list as $cg) {
        $id = (int) ($cg['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $a = categorie_generale_parse_attributs_row($cg);
        $out[(string) $id] = [
            'p' => $a['poids'] ? 1 : 0,
            't' => $a['taille'] ? 1 : 0,
            'm' => $a['mesure'] ? 1 : 0,
            'c' => $a['couleur'] ? 1 : 0,
        ];
    }
    return $out;
}

/**
 * Colonne categories.categorie_generale_id (lien sous-cat. vendeur → rayon)
 */
function categories_has_categorie_generale_id_column() {
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
        $st = $db->query("SHOW COLUMNS FROM `categories` LIKE 'categorie_generale_id'");
        $cached = (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Table pivot categories ↔ categories_generales (plusieurs rayons par sous-catégorie plateforme).
 */
function categories_generales_liaisons_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories_categories_generales'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * @param array<int|string> $ids
 * @return int[]
 */
function plateforme_rayons_ids_normalize($ids) {
    if (!is_array($ids)) {
        $ids = [$ids];
    }
    $out = [];
    foreach ($ids as $x) {
        $i = (int) $x;
        if ($i > 0) {
            $out[$i] = true;
        }
    }
    $keys = array_keys($out);
    sort($keys, SORT_NUMERIC);
    return $keys;
}

/**
 * IDs des rayons (categories_generales) liés à une sous-catégorie plateforme.
 *
 * @return int[]
 */
function plateforme_get_rayons_ids_for_categorie($categorie_id) {
    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0) {
        return [];
    }
    if (categories_generales_liaisons_table_exists()) {
        global $db;
        try {
            $st = $db->prepare('
                SELECT `categorie_generale_id` FROM `categories_categories_generales`
                WHERE `categorie_id` = :cid
                ORDER BY `categorie_generale_id` ASC
            ');
            $st->execute(['cid' => $categorie_id]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN);
            $ids = [];
            foreach ($rows ?: [] as $r) {
                $ids[] = (int) $r;
            }
            if (!empty($ids)) {
                return $ids;
            }
        } catch (PDOException $e) {
        }
    }
    $c = get_categorie_by_id($categorie_id);
    if (!$c) {
        return [];
    }
    $g = (int) ($c['categorie_generale_id'] ?? 0);
    return $g > 0 ? [$g] : [];
}

/**
 * La sous-catégorie plateforme est liée au rayon (categories_generales) donné.
 */
function categorie_plateforme_liee_au_rayon($categorie_id, $generale_id) {
    $categorie_id = (int) $categorie_id;
    $generale_id = (int) $generale_id;
    if ($categorie_id <= 0 || $generale_id <= 0) {
        return false;
    }
    $rayons = plateforme_get_rayons_ids_for_categorie($categorie_id);
    return in_array($generale_id, $rayons, true);
}

/**
 * Nombre de sous-catégories plateforme rattachées à un rayon (pour validation formulaire vendeur).
 */
function plateforme_sous_categorie_count_for_rayon($generale_id) {
    $generale_id = (int) $generale_id;
    if ($generale_id <= 0) {
        return 0;
    }
    $rows = get_plateforme_sous_categories_for_form();
    $n = 0;
    foreach ($rows as $r) {
        $cid = (int) ($r['id'] ?? 0);
        if ($cid > 0 && categorie_plateforme_liee_au_rayon($cid, $generale_id)) {
            $n++;
        }
    }
    return $n;
}

/**
 * Enregistre les rayons liés à une sous-catégorie (et synchronise categories.categorie_generale_id sur le 1er id).
 *
 * @param int[] $generale_ids
 */
function plateforme_set_rayons_for_categorie($categorie_id, array $generale_ids) {
    global $db;
    $categorie_id = (int) $categorie_id;
    $ids = plateforme_rayons_ids_normalize($generale_ids);
    if ($categorie_id <= 0 || empty($ids)) {
        return false;
    }
    foreach ($ids as $gid) {
        if (!get_categorie_generale_by_id($gid)) {
            return false;
        }
    }
    $primary = (int) $ids[0];
    if (categories_generales_liaisons_table_exists()) {
        try {
            $db->beginTransaction();
            $st = $db->prepare('DELETE FROM `categories_categories_generales` WHERE `categorie_id` = :cid');
            $st->execute(['cid' => $categorie_id]);
            $ins = $db->prepare('
                INSERT INTO `categories_categories_generales` (`categorie_id`, `categorie_generale_id`)
                VALUES (:cid, :gid)
            ');
            foreach ($ids as $gid) {
                $ins->execute(['cid' => $categorie_id, 'gid' => (int) $gid]);
            }
            if (categories_table_has_column('categorie_generale_id')) {
                $st2 = $db->prepare('UPDATE `categories` SET `categorie_generale_id` = :g WHERE `id` = :id');
                $st2->execute(['g' => $primary, 'id' => $categorie_id]);
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
    if (categories_table_has_column('categorie_generale_id')) {
        try {
            $st = $db->prepare('UPDATE `categories` SET `categorie_generale_id` = :g WHERE `id` = :id');
            return $st->execute(['g' => $primary, 'id' => $categorie_id]);
        } catch (PDOException $e) {
            return false;
        }
    }
    return false;
}

function get_categorie_generale_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !categories_generales_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM `categories_generales` WHERE `id` = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

function get_categorie_generale_by_nom($nom) {
    global $db;
    $nom = trim((string) $nom);
    if ($nom === '' || !categories_generales_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM `categories_generales` WHERE `nom` = :n LIMIT 1');
        $st->execute(['n' => $nom]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Parse filtre URL produits : "G12" = rayon id 12, sinon id catégorie feuille.
 * @return array{leaf_id:?int,generale_id:?int}
 */
function marketplace_parse_categorie_filter_param($raw) {
    $raw = isset($raw) ? trim((string) $raw) : '';
    if ($raw === '') {
        return ['leaf_id' => null, 'generale_id' => null];
    }
    if (preg_match('/^G(\d+)$/i', $raw, $m)) {
        return ['leaf_id' => null, 'generale_id' => (int) $m[1]];
    }
    if (ctype_digit($raw)) {
        return ['leaf_id' => (int) $raw, 'generale_id' => null];
    }
    return ['leaf_id' => null, 'generale_id' => null];
}

/**
 * IDs des catégories feuilles vendeur rattachées à un rayon
 */
function categorie_generale_leaf_category_ids($generale_id) {
    global $db;
    $generale_id = (int) $generale_id;
    if ($generale_id <= 0 || !categories_has_categorie_generale_id_column()) {
        return [];
    }
    try {
        $st = $db->prepare("
            SELECT `id` FROM `categories`
            WHERE `categorie_generale_id` = :g
        ");
        $st->execute(['g' => $generale_id]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Préremplissage formulaire vendeur depuis une ligne categories (produit ou pré-sélection)
 */
function vendeur_prefill_from_categorie_row($categorie_row) {
    $out = ['generale' => 0, 'sub' => 0];
    if (empty($categorie_row) || !is_array($categorie_row)) {
        return $out;
    }
    if (categorie_est_sous_categorie_plateforme($categorie_row)) {
        $out['sub'] = (int) ($categorie_row['id'] ?? 0);
        $out['generale'] = (int) ($categorie_row['categorie_generale_id'] ?? 0);
        return $out;
    }
    if ((int) ($categorie_row['admin_id'] ?? 0) > 0) {
        $out['sub'] = (int) ($categorie_row['id'] ?? 0);
        $out['generale'] = (int) ($categorie_row['categorie_generale_id'] ?? 0);
        if ($out['generale'] <= 0 && !empty($categorie_row['parent_id'])) {
            $par = get_categorie_by_id((int) $categorie_row['parent_id']);
            if ($par && categories_generales_table_exists()) {
                $cg = get_categorie_generale_by_nom((string) ($par['nom'] ?? ''));
                if ($cg) {
                    $out['generale'] = (int) $cg['id'];
                }
            }
        }
        return $out;
    }
    if (categories_generales_table_exists() && categorie_is_generale_plateforme($categorie_row)) {
        $cg = get_categorie_generale_by_nom((string) ($categorie_row['nom'] ?? ''));
        if ($cg) {
            $out['generale'] = (int) $cg['id'];
        }
        return $out;
    }
    return $out;
}

function categorie_is_generale_plateforme(array $categorie) {
    if (categories_est_plateforme_column()) {
        return (int) ($categorie['est_plateforme'] ?? 0) === 1
            && ((int) ($categorie['parent_id'] ?? 0) <= 0)
            && ((int) ($categorie['admin_id'] ?? 0) <= 0);
    }
    $pid = $categorie['parent_id'] ?? null;
    $aid = $categorie['admin_id'] ?? null;
    $noParent = $pid === null || $pid === '' || (int) $pid === 0;
    $noOwner = $aid === null || $aid === '' || (int) $aid === 0;
    return $noParent && $noOwner;
}

/**
 * Catégories générales (racine plateforme), ordre menu — table categories_generales si migrée
 */
function get_general_categories_ordered() {
    global $db;
    if (!categories_hierarchy_enabled()) {
        return get_all_categories();
    }
    if (categories_generales_table_exists()) {
        try {
            $stmt = $db->query("
                SELECT * FROM `categories_generales`
                ORDER BY `sort_ordre` ASC, `nom` ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows ? $rows : [];
        } catch (PDOException $e) {
            return [];
        }
    }
    try {
        if (categories_est_plateforme_column()) {
            $stmt = $db->query("
                SELECT * FROM `categories`
                WHERE `est_plateforme` = 1
                  AND (`parent_id` IS NULL OR `parent_id` = 0)
                  AND (`admin_id` IS NULL OR `admin_id` = 0)
                ORDER BY `sort_ordre` ASC, `nom` ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        }
        $stmt = $db->query("
            SELECT * FROM `categories`
            WHERE (`parent_id` IS NULL OR `parent_id` = 0)
              AND (`admin_id` IS NULL OR `admin_id` = 0)
            ORDER BY `sort_ordre` ASC, `nom` ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_child_category_ids($parent_id) {
    global $db;
    $parent_id = (int) $parent_id;
    if ($parent_id <= 0) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT `id` FROM `categories` WHERE `parent_id` = :p');
        $st->execute(['p' => $parent_id]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * IDs catégorie pour listing produits : si générale, inclut les sous-catégories
 */
function category_expanded_ids_for_products($categorie_id) {
    $cid = (int) $categorie_id;
    if ($cid <= 0) {
        return [];
    }
    if (!categories_hierarchy_enabled()) {
        return [$cid];
    }
    $c = get_categorie_by_id($cid);
    if (!$c) {
        return [];
    }
    if (categories_has_categorie_generale_id_column() && (int) ($c['admin_id'] ?? 0) <= 0) {
        $cg = get_categorie_generale_by_nom((string) ($c['nom'] ?? ''));
        if ($cg && (int) $cg['id'] > 0) {
            return categorie_generale_leaf_category_ids((int) $cg['id']);
        }
    }
    if (categorie_is_generale_plateforme($c)) {
        if (categories_has_categorie_generale_id_column()) {
            $cg = get_categorie_generale_by_nom((string) ($c['nom'] ?? ''));
            if ($cg) {
                return categorie_generale_leaf_category_ids((int) $cg['id']);
            }
        }
        $children = get_child_category_ids($cid);
        return array_values(array_unique(array_merge([$cid], $children)));
    }
    return [$cid];
}

function get_vendeur_subcategories_for_parent($vendeur_id, $parent_id) {
    global $db;
    $vendeur_id = (int) $vendeur_id;
    $parent_id = (int) $parent_id;
    if ($vendeur_id <= 0 || $parent_id <= 0 || !categories_hierarchy_enabled()) {
        return [];
    }
    try {
        $st = $db->prepare("
            SELECT * FROM `categories`
            WHERE `parent_id` = :p AND `admin_id` = :v
            ORDER BY `nom` ASC
        ");
        $st->execute(['p' => $parent_id, 'v' => $vendeur_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Crée une sous-catégorie pour un vendeur sous une catégorie générale
 * @return int|false id
 */
function create_vendeur_subcategorie($nom, $categorie_generale_ou_parent_id, $admin_id) {
    global $db;
    $nom = trim((string) $nom);
    $ref_id = (int) $categorie_generale_ou_parent_id;
    $admin_id = (int) $admin_id;
    if ($nom === '' || $ref_id <= 0 || $admin_id <= 0 || !categories_hierarchy_enabled()) {
        return false;
    }
    try {
        if (categories_has_categorie_generale_id_column()) {
            $cg = get_categorie_generale_by_id($ref_id);
            if (!$cg) {
                return false;
            }
            $st = $db->prepare("
                INSERT INTO `categories` (`nom`, `description`, `image`, `date_creation`, `parent_id`, `categorie_generale_id`, `admin_id`, `sort_ordre`)
                VALUES (:nom, '', NULL, NOW(), NULL, :cgid, :aid, 0)
            ");
            if ($st->execute(['nom' => $nom, 'cgid' => $ref_id, 'aid' => $admin_id])) {
                return (int) $db->lastInsertId();
            }
            return false;
        }
        $parent = get_categorie_by_id($ref_id);
        if (!$parent || !categorie_is_generale_plateforme($parent)) {
            return false;
        }
        $st = $db->prepare("
            INSERT INTO `categories` (`nom`, `description`, `image`, `date_creation`, `parent_id`, `admin_id`, `sort_ordre`)
            VALUES (:nom, '', NULL, NOW(), :pid, :aid, 0)
        ");
        if ($st->execute(['nom' => $nom, 'pid' => $ref_id, 'aid' => $admin_id])) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
        return false;
    }
    return false;
}

/**
 * Sous-catégories (vendeur) ayant au moins un produit actif sous une générale
 */
function get_subcategories_with_active_products_for_general($general_id, $boutique_admin_id = null) {
    global $db;
    $general_id = (int) $general_id;
    if ($general_id <= 0 || !categories_hierarchy_enabled()) {
        return [];
    }
    $boutique_admin_id = $boutique_admin_id !== null && $boutique_admin_id !== '' ? (int) $boutique_admin_id : null;
    $use_cg = categories_has_categorie_generale_id_column() && categories_generales_table_exists();
    require_once __DIR__ . '/model_produits_sous_categories.php';
    $psc_ok = function_exists('produits_sous_categories_table_exists') && produits_sous_categories_table_exists();
    try {
        if ($use_cg) {
            if ($boutique_admin_id !== null && $boutique_admin_id > 0) {
                $psc_sql = $psc_ok ? "
                    OR EXISTS (
                        SELECT 1 FROM `produits_sous_categories` psc
                        INNER JOIN `produits` p2 ON p2.`id` = psc.`produit_id` AND p2.`statut` IN ('actif', 'rupture_stock') AND p2.`admin_id` = :aid2
                        WHERE psc.`categorie_id` = c.`id`
                    )" : '';
                $st = $db->prepare("
                    SELECT DISTINCT c.`id`, c.`nom`
                    FROM `categories` c
                    WHERE c.`categorie_generale_id` = :gid
                      AND (
                        EXISTS (
                            SELECT 1 FROM `produits` p
                            WHERE p.`categorie_id` = c.`id` AND p.`statut` IN ('actif', 'rupture_stock') AND p.`admin_id` = :aid
                        )
                        $psc_sql
                      )
                    ORDER BY c.`nom` ASC
                ");
                $st->execute(['gid' => $general_id, 'aid' => $boutique_admin_id, 'aid2' => $boutique_admin_id]);
            } else {
                $psc_sql = $psc_ok ? "
                    OR EXISTS (
                        SELECT 1 FROM `produits_sous_categories` psc
                        INNER JOIN `produits` p2 ON p2.`id` = psc.`produit_id` AND p2.`statut` IN ('actif', 'rupture_stock')
                        WHERE psc.`categorie_id` = c.`id`
                    )" : '';
                $st = $db->prepare("
                    SELECT DISTINCT c.`id`, c.`nom`
                    FROM `categories` c
                    WHERE c.`categorie_generale_id` = :gid
                      AND (
                        EXISTS (
                            SELECT 1 FROM `produits` p
                            WHERE p.`categorie_id` = c.`id` AND p.`statut` IN ('actif', 'rupture_stock')
                        )
                        $psc_sql
                      )
                    ORDER BY c.`nom` ASC
                ");
                $st->execute(['gid' => $general_id]);
            }
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if ($boutique_admin_id !== null && $boutique_admin_id > 0) {
            $st = $db->prepare("
                SELECT DISTINCT c.`id`, c.`nom`
                FROM `categories` c
                INNER JOIN `produits` p ON p.`categorie_id` = c.`id` AND p.`statut` IN ('actif', 'rupture_stock') AND p.`admin_id` = :aid
                WHERE c.`parent_id` = :gid AND c.`admin_id` = :aid2
                ORDER BY c.`nom` ASC
            ");
            $st->execute(['gid' => $general_id, 'aid' => $boutique_admin_id, 'aid2' => $boutique_admin_id]);
        } else {
            $st = $db->prepare("
                SELECT DISTINCT c.`id`, c.`nom`
                FROM `categories` c
                INNER JOIN `produits` p ON p.`categorie_id` = c.`id` AND p.`statut` IN ('actif', 'rupture_stock')
                WHERE c.`parent_id` = :gid AND c.`admin_id` IS NOT NULL
                ORDER BY c.`nom` ASC
            ");
            $st->execute(['gid' => $general_id]);
        }
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Arbre pour méga-menu / nav : générales + sous-catégories avec produits publiés
 * @param int|null $boutique_admin_id filtre vendeur (boutique) ou null = tout le marketplace
 */
function get_megamenu_categories($boutique_admin_id = null) {
    $generals = get_general_categories_ordered();
    $out = [];
    foreach ($generals as $g) {
        $gid = (int) $g['id'];
        $subs = get_subcategories_with_active_products_for_general($gid, $boutique_admin_id);
        $out[] = [
            'general' => $g,
            'subcategories' => $subs,
        ];
    }
    return $out;
}

/**
 * JSON pour formulaire produit vendeur : { "generaleId": [ {id,nom}, ... ], ... }
 */
function get_vendeur_subcategories_grouped_json($vendeur_id) {
    unset($vendeur_id);
    $rows = get_plateforme_sous_categories_for_form();
    if (empty($rows)) {
        return '{}';
    }
    $grouped = [];
    foreach ($rows as $r) {
        $p = (string) (int) ($r['categorie_generale_id'] ?? 0);
        if ($p === '0') {
            continue;
        }
        if (!isset($grouped[$p])) {
            $grouped[$p] = [];
        }
        $grouped[$p][] = ['id' => (int) $r['id'], 'nom' => (string) ($r['nom'] ?? '')];
    }
    return json_encode($grouped, JSON_UNESCAPED_UNICODE);
}

/**
 * Sous-catégories pour le formulaire produit vendeur (liste commune définie par le super admin).
 */
function get_all_vendeur_subcategories_for_form($vendeur_id) {
    unset($vendeur_id);
    return get_plateforme_sous_categories_for_form();
}

/**
 * Rattache la sous-catégorie vendeur au rayon choisi (categories.categorie_generale_id).
 */
function vendeur_align_subcategorie_generale($categorie_id, $categorie_generale_id, $vendeur_admin_id) {
    $categorie_id = (int) $categorie_id;
    $categorie_generale_id = (int) $categorie_generale_id;
    $vendeur_admin_id = (int) $vendeur_admin_id;
    if ($categorie_id <= 0 || $categorie_generale_id <= 0 || $vendeur_admin_id <= 0) {
        return;
    }
    if (!categories_has_categorie_generale_id_column()) {
        return;
    }
    $c = get_categorie_by_id($categorie_id);
    if (!$c || (int) ($c['admin_id'] ?? 0) !== $vendeur_admin_id) {
        return;
    }
    global $db;
    try {
        $st = $db->prepare('
            UPDATE `categories`
            SET `categorie_generale_id` = :g
            WHERE `id` = :id AND `admin_id` = :v
        ');
        $st->execute(['g' => $categorie_generale_id, 'id' => $categorie_id, 'v' => $vendeur_admin_id]);
    } catch (PDOException $e) {
    }
}

/**
 * Compte les produits rattachés à une catégorie (toutes boutiques).
 */
function count_produits_par_categorie_id($categorie_id) {
    global $db;
    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM `produits` WHERE `categorie_id` = :c');
        $st->execute(['c' => $categorie_id]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Nom de sous-catégorie plateforme disponible pour un rayon (hors id exclus), avec table pivot ou colonne seule.
 */
function plateforme_sous_categorie_nom_disponible_pour_rayon($nom, $categorie_generale_id, $exclude_id = 0) {
    global $db;
    $nom = trim((string) $nom);
    $categorie_generale_id = (int) $categorie_generale_id;
    $exclude_id = (int) $exclude_id;
    if ($nom === '' || $categorie_generale_id <= 0) {
        return false;
    }
    if (categories_generales_liaisons_table_exists()) {
        $sql = '
            SELECT c.`id` FROM `categories` c
            WHERE (c.`admin_id` IS NULL OR c.`admin_id` = 0)
              AND c.`nom` = :n
        ';
        if ($exclude_id > 0) {
            $sql .= ' AND c.`id` != :ex';
        }
        $sql .= ' AND (
            EXISTS (
                SELECT 1 FROM `categories_categories_generales` ccg
                WHERE ccg.`categorie_id` = c.`id` AND ccg.`categorie_generale_id` = :g
            )
            OR (
                c.`categorie_generale_id` = :g2
                AND NOT EXISTS (
                    SELECT 1 FROM `categories_categories_generales` ccg2 WHERE ccg2.`categorie_id` = c.`id`
                )
            )
        ) LIMIT 1';
        try {
            $st = $db->prepare($sql);
            $p = ['n' => $nom, 'g' => $categorie_generale_id, 'g2' => $categorie_generale_id];
            if ($exclude_id > 0) {
                $p['ex'] = $exclude_id;
            }
            $st->execute($p);
            return $st->fetchColumn() === false;
        } catch (PDOException $e) {
            return false;
        }
    }
    try {
        $sql = '
            SELECT `id` FROM `categories`
            WHERE `nom` = :n AND `categorie_generale_id` = :g
              AND ( `admin_id` IS NULL OR `admin_id` = 0 )
        ';
        if ($exclude_id > 0) {
            $sql .= ' AND `id` != :ex';
        }
        $sql .= ' LIMIT 1';
        $st = $db->prepare($sql);
        $p = ['n' => $nom, 'g' => $categorie_generale_id];
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
 * Nom disponible pour chacun des rayons donnés (même libellé sous plusieurs rayons = une seule ligne categories).
 *
 * @param int[] $generale_ids
 */
function plateforme_sous_categorie_nom_disponible_multi($nom, array $generale_ids, $exclude_id = 0) {
    $nom = trim((string) $nom);
    $ids = plateforme_rayons_ids_normalize($generale_ids);
    if ($nom === '' || empty($ids)) {
        return false;
    }
    foreach ($ids as $gid) {
        if (!plateforme_sous_categorie_nom_disponible_pour_rayon($nom, $gid, $exclude_id)) {
            return false;
        }
    }
    return true;
}

/**
 * Nom de sous-catégorie plateforme unique pour un rayon donné (hors id exclus).
 */
function plateforme_sous_categorie_nom_disponible($nom, $categorie_generale_id, $exclude_id = 0) {
    return plateforme_sous_categorie_nom_disponible_multi($nom, [$categorie_generale_id], $exclude_id);
}

/**
 * Crée une sous-catégorie commune (admin_id NULL).
 * $generale_ids : un ou plusieurs IDs de rayons (categories_generales).
 *
 * @param int|int[] $generale_ids
 */
function plateforme_create_sous_categorie($nom, $description, $image, $generale_ids, $sort_ordre = 0) {
    global $db;
    $nom = trim((string) $nom);
    $sort_ordre = (int) $sort_ordre;
    $ids = plateforme_rayons_ids_normalize(is_array($generale_ids) ? $generale_ids : [$generale_ids]);
    if ($nom === '' || empty($ids)) {
        return false;
    }
    foreach ($ids as $gid) {
        if (!get_categorie_generale_by_id($gid)) {
            return false;
        }
    }
    if (!plateforme_sous_categorie_nom_disponible_multi($nom, $ids, 0)) {
        return false;
    }
    $primary = (int) $ids[0];
    $cols = ['`nom`', '`description`', '`image`', '`date_creation`'];
    $holders = [':nom', ':description', ':image', 'NOW()'];
    $params = [
        'nom' => $nom,
        'description' => $description !== null ? (string) $description : null,
        'image' => $image,
    ];
    if (categories_table_has_column('categorie_generale_id')) {
        $cols[] = '`categorie_generale_id`';
        $holders[] = ':categorie_generale_id';
        $params['categorie_generale_id'] = $primary;
    } else {
        return false;
    }
    if (categories_table_has_column('sort_ordre')) {
        $cols[] = '`sort_ordre`';
        $holders[] = ':sort_ordre';
        $params['sort_ordre'] = $sort_ordre;
    }
    if (categories_table_has_column('admin_id')) {
        $cols[] = '`admin_id`';
        $holders[] = 'NULL';
    }
    if (categories_table_has_column('parent_id')) {
        $cols[] = '`parent_id`';
        $holders[] = 'NULL';
    }
    if (categories_table_has_column('est_plateforme')) {
        $cols[] = '`est_plateforme`';
        $holders[] = '0';
    }
    try {
        $sql = 'INSERT INTO `categories` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')';
        $stmt = $db->prepare($sql);
        if ($stmt->execute($params)) {
            $newId = (int) $db->lastInsertId();
            if (categories_generales_liaisons_table_exists()) {
                plateforme_set_rayons_for_categorie($newId, $ids);
            }
            return $newId;
        }
    } catch (PDOException $e) {
    }
    return false;
}

/**
 * Met à jour une sous-catégorie plateforme (id, nom, description, image, rayons, ordre).
 *
 * @param int|int[] $generale_ids
 */
function plateforme_update_sous_categorie($id, $nom, $description, $image, $generale_ids, $sort_ordre = null) {
    global $db;
    $id = (int) $id;
    $ids = plateforme_rayons_ids_normalize(is_array($generale_ids) ? $generale_ids : [$generale_ids]);
    if ($id <= 0 || empty($ids)) {
        return false;
    }
    $c = get_categorie_by_id($id);
    if (!$c || !categorie_est_sous_categorie_plateforme($c)) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    foreach ($ids as $gid) {
        if (!get_categorie_generale_by_id($gid)) {
            return false;
        }
    }
    if (!plateforme_sous_categorie_nom_disponible_multi($nom, $ids, $id)) {
        return false;
    }
    $sets = ['`nom` = :nom', '`description` = :description', '`image` = :image'];
    $params = [
        'id' => $id,
        'nom' => $nom,
        'description' => $description !== null ? (string) $description : null,
        'image' => $image,
    ];
    if ($sort_ordre !== null && categories_table_has_column('sort_ordre')) {
        $sets[] = '`sort_ordre` = :so';
        $params['so'] = (int) $sort_ordre;
    }
    try {
        $sql = 'UPDATE `categories` SET ' . implode(', ', $sets) . ' WHERE `id` = :id AND (`admin_id` IS NULL OR `admin_id` = 0)';
        $st = $db->prepare($sql);
        if (!$st->execute($params)) {
            return false;
        }
        if (categories_generales_liaisons_table_exists()) {
            return plateforme_set_rayons_for_categorie($id, $ids);
        }
        $primary = (int) $ids[0];
        if (categories_table_has_column('categorie_generale_id')) {
            $st2 = $db->prepare('UPDATE `categories` SET `categorie_generale_id` = :g WHERE `id` = :id AND (`admin_id` IS NULL OR `admin_id` = 0)');
            return $st2->execute(['g' => $primary, 'id' => $id]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une sous-catégorie plateforme si aucun produit.
 */
function plateforme_delete_sous_categorie($id) {
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    $c = get_categorie_by_id($id);
    if (!$c || !categorie_est_sous_categorie_plateforme($c)) {
        return false;
    }
    if (categorie_has_produits($id)) {
        return false;
    }
    return delete_categorie($id);
}

/** Liste complète categories_generales */
function categories_generales_list_all() {
    global $db;
    if (!categories_generales_table_exists()) {
        return [];
    }
    try {
        $st = $db->query('SELECT * FROM `categories_generales` ORDER BY `sort_ordre` ASC, `nom` ASC');
        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        return [];
    }
}

function categories_generales_insert_row($nom, $description, $icone, $sort_ordre, $attr_poids = 1, $attr_taille = 1, $attr_mesure = 1, $attr_couleur = 1, $image = null) {
    global $db;
    if (!categories_generales_table_exists()) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    if (get_categorie_generale_by_nom($nom)) {
        return false;
    }
    $ap = (int) (bool) $attr_poids;
    $at = (int) (bool) $attr_taille;
    $am = (int) (bool) $attr_mesure;
    $ac = (int) (bool) $attr_couleur;
    $img_val = ($image !== null && trim((string) $image) !== '') ? trim((string) $image) : null;
    $has_img_col = categories_generales_image_column_exists();
    try {
        if (categories_generales_attr_columns_exist()) {
            $cols = '`nom`, `description`, `icone`, `sort_ordre`, `attr_poids`, `attr_taille`, `attr_mesure`, `attr_couleur`, `date_creation`';
            $vals = ':nom, :descr, :icone, :so, :ap, :at, :am, :ac, NOW()';
        } else {
            $cols = '`nom`, `description`, `icone`, `sort_ordre`, `date_creation`';
            $vals = ':nom, :descr, :icone, :so, NOW()';
        }
        if ($has_img_col) {
            $cols .= ', `image`';
            $vals .= ', :img';
        }
        $sql = 'INSERT INTO `categories_generales` (' . $cols . ') VALUES (' . $vals . ')';
        $st = $db->prepare($sql);
        $params = [
            'nom' => $nom,
            'descr' => $description !== null ? (string) $description : null,
            'icone' => $icone !== null && (string) $icone !== '' ? (string) $icone : null,
            'so' => (int) $sort_ordre,
        ];
        if (categories_generales_attr_columns_exist()) {
            $params['ap'] = $ap;
            $params['at'] = $at;
            $params['am'] = $am;
            $params['ac'] = $ac;
        }
        if ($has_img_col) {
            $params['img'] = $img_val;
        }
        if ($st->execute($params)) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
    }
    return false;
}

function categories_generales_update_row($id, $nom, $description, $icone, $sort_ordre, $attr_poids = null, $attr_taille = null, $attr_mesure = null, $attr_couleur = null, $image = null) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !categories_generales_table_exists()) {
        return false;
    }
    $row = get_categorie_generale_by_id($id);
    if (!$row) {
        return false;
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return false;
    }
    $other = get_categorie_generale_by_nom($nom);
    if ($other && (int) $other['id'] !== $id) {
        return false;
    }
    $def_attr = function ($key, $param) use ($row) {
        if ($param !== null) {
            return (int) (bool) $param;
        }
        return array_key_exists($key, $row) ? (int) (!empty($row[$key])) : 1;
    };
    $img_val = null;
    if ($image !== null) {
        $img_val = trim((string) $image) !== '' ? trim((string) $image) : null;
    } elseif (array_key_exists('image', $row)) {
        $prev = trim((string) ($row['image'] ?? ''));
        $img_val = $prev !== '' ? $prev : null;
    }
    $has_img_col = categories_generales_image_column_exists();
    try {
        if (categories_generales_attr_columns_exist()) {
            $sql = '
                UPDATE `categories_generales`
                SET `nom` = :nom, `description` = :descr, `icone` = :icone, `sort_ordre` = :so,
                    `attr_poids` = :ap, `attr_taille` = :at, `attr_mesure` = :am, `attr_couleur` = :ac'
                . ($has_img_col ? ', `image` = :img' : '') . '
                WHERE `id` = :id
            ';
            $st = $db->prepare($sql);
            $params = [
                'id' => $id,
                'nom' => $nom,
                'descr' => $description !== null ? (string) $description : null,
                'icone' => $icone !== null && (string) $icone !== '' ? (string) $icone : null,
                'so' => (int) $sort_ordre,
                'ap' => $def_attr('attr_poids', $attr_poids),
                'at' => $def_attr('attr_taille', $attr_taille),
                'am' => $def_attr('attr_mesure', $attr_mesure),
                'ac' => $def_attr('attr_couleur', $attr_couleur),
            ];
            if ($has_img_col) {
                $params['img'] = $img_val;
            }
            return $st->execute($params);
        }
        $sql = '
            UPDATE `categories_generales`
            SET `nom` = :nom, `description` = :descr, `icone` = :icone, `sort_ordre` = :so'
            . ($has_img_col ? ', `image` = :img' : '') . '
            WHERE `id` = :id
        ';
        $st = $db->prepare($sql);
        $params = [
            'id' => $id,
            'nom' => $nom,
            'descr' => $description !== null ? (string) $description : null,
            'icone' => $icone !== null && (string) $icone !== '' ? (string) $icone : null,
            'so' => (int) $sort_ordre,
        ];
        if ($has_img_col) {
            $params['img'] = $img_val;
        }
        return $st->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une catégorie générale si aucune sous-catégorie ne la référence.
 */
function categories_generales_delete_row($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !categories_generales_table_exists()) {
        return false;
    }
    $row_del = get_categorie_generale_by_id($id);
    if (!$row_del) {
        return false;
    }
    if (categories_generales_liaisons_table_exists()) {
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `categories_categories_generales` WHERE `categorie_generale_id` = :id');
            $st->execute(['id' => $id]);
            if ((int) $st->fetchColumn() > 0) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    if (categories_has_categorie_generale_id_column()) {
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `categories` WHERE `categorie_generale_id` = :id');
            $st->execute(['id' => $id]);
            if ((int) $st->fetchColumn() > 0) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    try {
        $st = $db->prepare('DELETE FROM `categories_generales` WHERE `id` = :id');
        $ok = $st->execute(['id' => $id]);
        if ($ok && $row_del && function_exists('categories_generales_delete_image_file')) {
            categories_generales_delete_image_file($row_del['image'] ?? '');
        }
        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

?>