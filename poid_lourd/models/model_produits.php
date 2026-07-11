<?php
/**
 * Modèle pour la gestion des produits
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Indique si une colonne existe sur la table produits (cache SHOW COLUMNS)
 */
function produits_has_column(string $name): bool {
    static $cols = null;
    global $db;
    if ($cols === null) {
        $cols = [];
        if (!$db) {
            return false;
        }
        try {
            $stmt = $db->query('SHOW COLUMNS FROM produits');
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[$r['Field']] = true;
            }
        } catch (PDOException $e) {
            $cols = [];
        }
    }
    return isset($cols[$name]);
}

/**
 * Indique si le produit accepte la négociation de prix (défaut : oui si colonne absente)
 *
 * @param array<string, mixed>|null $produit
 */
function produit_prix_negociable($produit): bool
{
    if (!is_array($produit)) {
        return false;
    }
    if (!produits_has_column('prix_negociable')) {
        return true;
    }
    return (int) ($produit['prix_negociable'] ?? 1) === 1;
}

/**
 * Lit prix_negociable depuis POST (1 = oui par défaut)
 */
function produit_prix_negociable_from_post(int $default = 1): int
{
    if (!produits_has_column('prix_negociable')) {
        return $default;
    }
    if (!isset($_POST['prix_negociable'])) {
        return $default;
    }
    $v = (string) $_POST['prix_negociable'];
    return ($v === '0' || $v === 'non') ? 0 : 1;
}

/**
 * Statuts produit visibles et commandables côté client (catalogue + fiche produit).
 */
function produit_statuts_catalogue_client(): array
{
    return ['actif', 'rupture_stock'];
}

function produit_est_visible_client(string $statut): bool
{
    return in_array((string) $statut, produit_statuts_catalogue_client(), true);
}

/**
 * Fragment SQL pour filtrer les produits publiés côté client.
 */
function produit_sql_statut_catalogue(string $alias = 'p'): string
{
    return $alias . ".statut IN ('actif', 'rupture_stock')";
}

/**
 * Fragment SQL : jointure admin (boutique) pour enrichir les listes produits marketplace
 * @return array{join: string, select: string}
 */
function produits_sql_vendeur_fragment(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $logo_select = ', NULL AS vendeur_boutique_logo';
    if (!produits_has_column('admin_id')) {
        $cached = [
            'join' => '',
            'select' => ', NULL AS vendeur_boutique_nom, NULL AS vendeur_boutique_slug' . $logo_select,
        ];
    } else {
        require_once __DIR__ . '/model_admin.php';
        if (function_exists('admin_has_column') && admin_has_column('boutique_logo')) {
            $logo_select = ', vend.boutique_logo AS vendeur_boutique_logo';
        }
        $cached = [
            'join' => ' LEFT JOIN admin vend ON p.admin_id = vend.id ',
            'select' => ', vend.boutique_nom AS vendeur_boutique_nom, vend.boutique_slug AS vendeur_boutique_slug' . $logo_select,
        ];
    }
    return $cached;
}

/**
 * Code pays marketplace actif (null = pas de filtre)
 */
function produits_country_filter_code_or_null(int|string|null $boutique_admin_id = null): ?string
{
    if ($boutique_admin_id !== null && $boutique_admin_id !== '') {
        return null;
    }
    if (!produits_has_column('admin_id')) {
        return null;
    }
    require_once __DIR__ . '/../includes/marketplace_country_filter.php';
    if (!marketplace_country_filter_applies()) {
        return null;
    }
    require_once __DIR__ . '/../models/model_admin.php';
    if (!admin_has_boutique_country_column()) {
        return null;
    }
    return marketplace_get_selected_country_code();
}

/**
 * Code région marketplace actif (null = pas de filtre)
 */
function produits_region_filter_code_or_null(int|string|null $boutique_admin_id = null): ?string
{
    if ($boutique_admin_id !== null && $boutique_admin_id !== '') {
        return null;
    }
    if (!produits_has_column('admin_id')) {
        return null;
    }
    require_once __DIR__ . '/../includes/marketplace_region_filter.php';
    require_once __DIR__ . '/../includes/marketplace_country_filter.php';
    if (!marketplace_region_filter_applies()) {
        return null;
    }
    require_once __DIR__ . '/../models/model_admin.php';
    if (!admin_has_boutique_region_column()) {
        return null;
    }
    return marketplace_get_selected_region_code();
}

/**
 * Fragment SQL filtre pays + région sur vendeurs actifs.
 * @return array{sql: string, country: string|null, region: string|null}
 */
function produits_geo_sql_with_alias(int|string|null $boutique_admin_id = null, string $alias = 'p'): array
{
    $country = produits_country_filter_code_or_null($boutique_admin_id);
    $region = produits_region_filter_code_or_null($boutique_admin_id);
    if ($country === null && $region === null) {
        return ['sql' => '', 'country' => null, 'region' => null];
    }
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
    if ($a === '') {
        $a = 'p';
    }
    $conds = ["a.role = 'vendeur'", "a.statut = 'actif'"];
    if ($country !== null) {
        $conds[] = "COALESCE(NULLIF(TRIM(a.boutique_country), ''), 'SN') = :mp_marketplace_country";
    }
    if ($region !== null) {
        $conds[] = 'a.boutique_region = :mp_marketplace_region';
    }
    return [
        'sql' => ' AND ' . $a . '.admin_id IN (SELECT a.id FROM admin a WHERE ' . implode(' AND ', $conds) . ')',
        'country' => $country,
        'region' => $region,
    ];
}

/**
 * Clause SQL filtre région avec alias table produits
 * @return array{sql: string, code: string|null}
 */
function produits_region_sql_with_alias(int|string|null $boutique_admin_id = null, string $alias = 'p'): array
{
    $geo = produits_geo_sql_with_alias($boutique_admin_id, $alias);
    return [
        'sql' => $geo['sql'],
        'code' => $geo['region'],
        'country' => $geo['country'],
    ];
}

/**
 * Clause SQL filtre région sans alias (requêtes COUNT sur produits seule)
 * @return array{sql: string, code: string|null}
 */
function produits_region_sql_plain(int|string|null $boutique_admin_id = null): array
{
    $geo = produits_geo_sql_with_alias($boutique_admin_id, 'p');
    if ($geo['sql'] === '') {
        return ['sql' => '', 'code' => null, 'country' => null];
    }
    return [
        'sql' => str_replace('p.admin_id', 'admin_id', $geo['sql']),
        'code' => $geo['region'],
        'country' => $geo['country'],
    ];
}

/**
 * Ajoute le filtre région à une clause WHERE avec paramètres positionnels (?)
 */
function produits_append_region_positional(string &$where, array &$exec_params, int|string|null $boutique_admin_id = null): void
{
    $country = produits_country_filter_code_or_null($boutique_admin_id);
    $region = produits_region_filter_code_or_null($boutique_admin_id);
    if ($country === null && $region === null) {
        return;
    }
    $conds = ["a.role = 'vendeur'", "a.statut = 'actif'"];
    if ($country !== null) {
        $conds[] = "COALESCE(NULLIF(TRIM(a.boutique_country), ''), 'SN') = ?";
        $exec_params[] = $country;
    }
    if ($region !== null) {
        $conds[] = 'a.boutique_region = ?';
        $exec_params[] = $region;
    }
    $where .= ' AND p.admin_id IN (SELECT a.id FROM admin a WHERE ' . implode(' AND ', $conds) . ')';
}

/**
 * Lie le paramètre région sur un PDOStatement si actif
 */
function produits_bind_region_stmt(PDOStatement $stmt, int|string|null $boutique_admin_id = null): void
{
    $country = produits_country_filter_code_or_null($boutique_admin_id);
    $region = produits_region_filter_code_or_null($boutique_admin_id);
    if ($country !== null) {
        $stmt->bindValue(':mp_marketplace_country', $country, PDO::PARAM_STR);
    }
    if ($region !== null) {
        $stmt->bindValue(':mp_marketplace_region', $region, PDO::PARAM_STR);
    }
}

/**
 * Fusionne pays/région dans un tableau de paramètres nommés PDO.
 */
function produits_merge_geo_filter_params(array &$params, array $rf): void
{
    if (!is_array($rf)) {
        return;
    }
    if (!empty($rf['country'])) {
        $params['mp_marketplace_country'] = $rf['country'];
    }
    if (!empty($rf['code'])) {
        $params['mp_marketplace_region'] = $rf['code'];
    }
}

/**
 * Fragment SQL : nom affiché = catégorie générale (rayon categories_generales) si liée au produit
 * ou à la catégorie feuille, avec repli sur le nom de la catégorie SQL (c.nom).
 *
 * @return array{join: string, categorie_nom_sql: string}
 */
function produits_sql_rayon_categorie_nom_fragment(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    require_once __DIR__ . '/model_categories.php';
    if (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()) {
        $cached = ['join' => '', 'categorie_nom_sql' => 'c.nom'];
        return $cached;
    }
    $has_pc = produits_has_column('categorie_generale_id');
    $has_cc = function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column();
    if (!$has_pc && !$has_cc) {
        $cached = ['join' => '', 'categorie_nom_sql' => 'c.nom'];
        return $cached;
    }
    if ($has_pc && $has_cc) {
        $id_expr = 'COALESCE(NULLIF(p.categorie_generale_id, 0), NULLIF(c.categorie_generale_id, 0))';
    } elseif ($has_pc) {
        $id_expr = 'NULLIF(p.categorie_generale_id, 0)';
    } else {
        $id_expr = 'NULLIF(c.categorie_generale_id, 0)';
    }
    $cached = [
        'join' => ' LEFT JOIN categories_generales cg_rayon ON cg_rayon.id = ' . $id_expr . ' ',
        'categorie_nom_sql' => 'COALESCE(cg_rayon.nom, c.nom)',
    ];
    return $cached;
}

/**
 * Préfixe identifiant : 3 premières lettres du nom boutique (A-Z).
 */
function produit_identifiant_prefix_from_boutique_nom(string $boutique_nom): string
{
    $nom = trim((string) $boutique_nom);
    if ($nom === '') {
        return 'FPL';
    }
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom);
        if ($t !== false && $t !== '') {
            $nom = $t;
        }
    }
    $letters = preg_replace('/[^A-Za-z]/', '', $nom);
    $letters = strtoupper($letters);
    if ($letters === '') {
        return 'FPL';
    }
    if (strlen($letters) < 3) {
        return str_pad($letters, 3, 'X');
    }
    return substr($letters, 0, 3);
}

/**
 * Format valide : 3 lettres + 6 chiffres (ex. SHO482913, FPL000042).
 */
function produit_identifiant_interne_is_valid_format(string $code): bool
{
    $code = strtoupper(trim((string) $code));
    return (bool) preg_match('/^[A-Z]{3}\d{6}$/', $code);
}

/**
 * Vérifie si un identifiant interne existe déjà.
 */
function produit_identifiant_interne_exists(string $code): bool
{
    global $db;
    if (!$db || !produits_has_column('identifiant_interne')) {
        return false;
    }
    $code = strtoupper(trim((string) $code));
    if (!produit_identifiant_interne_is_valid_format($code)) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM produits WHERE UPPER(TRIM(identifiant_interne)) = :c');
        $stmt->execute(['c' => $code]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Génère un identifiant interne : 3 lettres boutique + 6 chiffres aléatoires.
 * @param int|null $admin_id ID vendeur pour le préfixe boutique, null = FPL (legacy)
 */
function generate_next_identifiant_interne_produit(?int $admin_id = null): ?string
{
    global $db;
    if (!$db || !produits_has_column('identifiant_interne')) {
        return null;
    }

    $prefix = 'FPL';
    if ($admin_id !== null && (int) $admin_id > 0) {
        require_once __DIR__ . '/model_admin.php';
        $admin = get_admin_by_id((int) $admin_id);
        if ($admin) {
            $bn = trim((string) ($admin['boutique_nom'] ?? ''));
            if ($bn === '') {
                $bn = trim((string) ($admin['nom'] ?? ''));
            }
            $prefix = produit_identifiant_prefix_from_boutique_nom($bn);
        }
    }

    for ($i = 0; $i < 60; $i++) {
        $code = $prefix . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (!produit_identifiant_interne_exists($code)) {
            return $code;
        }
    }

    return null;
}

/**
 * Récupère tous les produits
 * @param string $statut Filtrer par statut (optionnel)
 * @param int|null $boutique_admin_id Limiter au vendeur (marketplace)
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_all_produits(?string $statut = null, int|string|null $boutique_admin_id = null): array|false
{
    global $db;

    try {
        $vj = produits_sql_vendeur_fragment();
        $sql = "
                SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                " . $vj['join'] . "
                WHERE 1=1
            ";
        $params = [];
        if ($statut) {
            $sql .= ' AND p.statut = :statut';
            $params['statut'] = $statut;
        }
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $sql .= ' AND p.admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_with_alias($boutique_admin_id);
        $sql .= $rf['sql'];
        produits_merge_geo_filter_params($params, $rf);
        $sql .= ' ORDER BY p.date_creation DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'une catégorie spécifique
 * @param int $categorie_id L'ID de la catégorie
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_produits_by_categorie(int $categorie_id, int|string|null $boutique_admin_id = null): array
{
    global $db;

    try {
        require_once __DIR__ . '/model_categories.php';
        $catIds = function_exists('category_expanded_ids_for_products')
            ? category_expanded_ids_for_products((int) $categorie_id)
            : [(int) $categorie_id];
        if (empty($catIds)) {
            return [];
        }
        $vj = produits_sql_vendeur_fragment();
        $placeholders = implode(', ', array_fill(0, count($catIds), '?'));
        $where = 'p.categorie_id IN (' . $placeholders . ') AND p.statut IN (\'actif\', \'rupture_stock\')';
        $execParams = array_values($catIds);
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = ?';
            $execParams[] = (int) $boutique_admin_id;
        }
        produits_append_region_positional($where, $execParams, $boutique_admin_id);
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $vj['join'] . "
            WHERE $where
            ORDER BY p.date_creation DESC
        ");
        $stmt->execute($execParams);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Produits actifs rattachés à un rayon plateforme (categories_generales).
 * Utilise produits.categorie_generale_id si présent, et/ou les catégories feuilles liées au rayon.
 *
 * @param int $generale_id ID categories_generales
 * @param int|null $boutique_admin_id Filtre vendeur (boutique) ou null = marketplace
 * @param int|null $filter_genre_id Si > 0 : uniquement produits ayant ce genre (pivot) — le genre doit être autorisé pour ce rayon
 * @param int|null $filter_sous_categorie_id Si > 0 : produits ayant cette sous-catégorie (categorie_id ou pivot produits_sous_categories)
 * @return array
 */
function get_produits_by_categorie_generale(int $generale_id, int|string|null $boutique_admin_id = null, ?int $filter_genre_id = null, ?int $filter_sous_categorie_id = null): array {
    global $db;
    $generale_id = (int) $generale_id;
    if ($generale_id <= 0) {
        return [];
    }
    $filter_genre_id = (isset($filter_genre_id) && (int) $filter_genre_id > 0) ? (int) $filter_genre_id : 0;
    $filter_sous_categorie_id = (isset($filter_sous_categorie_id) && (int) $filter_sous_categorie_id > 0) ? (int) $filter_sous_categorie_id : 0;
    if ($filter_genre_id > 0) {
        require_once __DIR__ . '/model_genres.php';
        if (!produits_genres_table_exists() || !function_exists('genre_id_is_allowed_for_categorie_generale')
            || !genre_id_is_allowed_for_categorie_generale($filter_genre_id, $generale_id)) {
            $filter_genre_id = 0;
        }
    }
    require_once __DIR__ . '/model_categories.php';
    if (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()) {
        return [];
    }
    $gen_row = get_categorie_generale_by_id($generale_id);
    if (!$gen_row) {
        return [];
    }

    $leaf_ids = [];
    if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
        $leaf_ids = categorie_generale_leaf_category_ids($generale_id);
    }

    $conds = [];
    $exec_params = [];
    if (produits_has_column('categorie_generale_id')) {
        $conds[] = 'p.categorie_generale_id = ?';
        $exec_params[] = $generale_id;
    }
    if (!empty($leaf_ids)) {
        $ph = implode(',', array_fill(0, count($leaf_ids), '?'));
        $conds[] = "p.categorie_id IN ($ph)";
        foreach ($leaf_ids as $lid) {
            $exec_params[] = (int) $lid;
        }
    }
    if (empty($conds)) {
        return [];
    }

    $where_or = '(' . implode(' OR ', $conds) . ')';
    $vj = produits_sql_vendeur_fragment();
    $where = "p.statut IN ('actif', 'rupture_stock') AND $where_or";
    if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
        $where .= ' AND p.admin_id = ?';
        $exec_params[] = (int) $boutique_admin_id;
    }
    if ($filter_genre_id > 0) {
        $where .= ' AND EXISTS (SELECT 1 FROM `produits_genres` pgf WHERE pgf.produit_id = p.id AND pgf.genre_id = ?)';
        $exec_params[] = $filter_genre_id;
    }

    if ($filter_sous_categorie_id > 0) {
        require_once __DIR__ . '/model_produits_sous_categories.php';
        if (produits_sous_categories_table_exists()) {
            $where .= ' AND (p.`categorie_id` = ? OR EXISTS (SELECT 1 FROM `produits_sous_categories` psc WHERE psc.`produit_id` = p.`id` AND psc.`categorie_id` = ?))';
            $exec_params[] = $filter_sous_categorie_id;
            $exec_params[] = $filter_sous_categorie_id;
        } else {
            $where .= ' AND p.`categorie_id` = ?';
            $exec_params[] = $filter_sous_categorie_id;
        }
    }

    produits_append_region_positional($where, $exec_params, $boutique_admin_id);

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE $where
            ORDER BY p.date_creation DESC
        ");
        $stmt->execute($exec_params);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Rayons (categories_generales) contenant au moins un produit publié (statut actif).
 *
 * @param int|null $boutique_admin_id ID vendeur pour restreindre à sa boutique, ou null pour tous les vendeurs
 * @return array<int, array> Lignes de la table categories_generales avec la clé nb_produits_actifs
 */
function get_categories_generales_avec_produits_actifs(int|string|null $boutique_admin_id = null): array {
    global $db;
    require_once __DIR__ . '/model_categories.php';
    if (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT * FROM `categories_generales`
            ORDER BY `sort_ordre` ASC, `nom` ASC
        ');
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($list as $row) {
        $gid = (int) ($row['id'] ?? 0);
        if ($gid <= 0) {
            continue;
        }
        $prods = get_produits_by_categorie_generale($gid, $boutique_admin_id);
        $n = is_array($prods) ? count($prods) : 0;
        if ($n > 0) {
            $row['nb_produits_actifs'] = $n;
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * Produits similaires marketplace : même rayon (categories_generales), toutes boutiques, hors l’article courant.
 *
 * @param int $exclude_produit_id Produit à exclure
 * @param int $generale_id ID categories_generales
 * @param int $limit Nombre max (défaut 8)
 * @return array
 */
function get_produits_similaires_rayon_generale(int $exclude_produit_id, int $generale_id, int $limit = 8): array {
    global $db;
    $exclude_produit_id = (int) $exclude_produit_id;
    $generale_id = (int) $generale_id;
    $limit = max(1, min(64, (int) $limit));
    if ($generale_id <= 0 || $exclude_produit_id <= 0) {
        return [];
    }
    require_once __DIR__ . '/model_categories.php';
    if (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()) {
        return [];
    }
    if (!get_categorie_generale_by_id($generale_id)) {
        return [];
    }

    $leaf_ids = [];
    if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
        $leaf_ids = categorie_generale_leaf_category_ids($generale_id);
    }

    $conds = [];
    $exec_params = [$exclude_produit_id];
    if (produits_has_column('categorie_generale_id')) {
        $conds[] = 'p.categorie_generale_id = ?';
        $exec_params[] = $generale_id;
    }
    if (!empty($leaf_ids)) {
        $ph = implode(',', array_fill(0, count($leaf_ids), '?'));
        $conds[] = "p.categorie_id IN ($ph)";
        foreach ($leaf_ids as $lid) {
            $exec_params[] = (int) $lid;
        }
    }
    if (empty($conds)) {
        return [];
    }

    $where_or = '(' . implode(' OR ', $conds) . ')';
    $vj = produits_sql_vendeur_fragment();
    $where = "p.statut IN ('actif', 'rupture_stock') AND p.id != ? AND $where_or";
    produits_append_region_positional($where, $exec_params, null);

    try {
        $sql = "
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE $where
            ORDER BY p.date_creation DESC
            LIMIT " . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($exec_params);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un produit par son ID
 * @param int $id L'ID du produit
 * @return array|false Les données du produit ou False si non trouvé
 */
function get_produit_by_id(int $id): array|false
{
    global $db;

    try {
        $vj = produits_sql_vendeur_fragment();
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $vj['join'] . "
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un produit par son identifiant interne FPLxxxxxx (insensible à la casse)
 * @param string $code Ex. FPL000042
 * @param bool $only_actif Si true, uniquement les produits actifs ou en promo (exclut inactif)
 * @return array|false
 */
function get_produit_by_identifiant_interne(string $code, bool $only_actif = false): array|false
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return false;
    }
    $code = strtoupper(trim((string) $code));
    if (!produit_identifiant_interne_is_valid_format($code)) {
        return false;
    }

    try {
        $vj = produits_sql_vendeur_fragment();
        $sql = "
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE UPPER(TRIM(p.identifiant_interne)) = :code
        ";
        if ($only_actif) {
            $sql .= " AND p.statut IN ('actif', 'rupture_stock')";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Extrait les 5 derniers chiffres de la partie numérique du code (style caisse : saisie rapide)
 * Ex. FPL000151 → "00151", FPL100001 → "00001"
 */
function produit_identifiant_derniers_5_chiffres(string $identifiant_interne): string
{
    $d = preg_replace('/\D/', '', (string) $identifiant_interne);
    if (strlen($d) < 5) {
        return '';
    }

    return substr($d, -5);
}

/**
 * Expression SQL MySQL : les 5 derniers chiffres du numéro (après retrait du préfixe FPL)
 * @param string $table_prefix Préfixe de table/colonne, ex. 'p' → p.identifiant_interne ; '' → identifiant_interne
 */
function produits_sql_identifiant_suffix_5_expr(string $table_prefix = 'p'): string
{
    $col = $table_prefix === '' ? 'identifiant_interne' : $table_prefix . '.identifiant_interne';

    return "RIGHT(UPPER(TRIM($col)), 5)";
}

/**
 * Liste des produits dont le code se termine par ces 5 chiffres (recherche rapide)
 */
function get_produits_by_identifiant_suffix_5_chiffres(string $suffix5, int $offset = 0, int $limit = 20, bool $only_actif = true, int|string|null $boutique_admin_id = null): array
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return [];
    }
    $suffix5 = preg_replace('/\D/', '', (string) $suffix5);
    if (strlen($suffix5) !== 5) {
        return [];
    }

    $statut_sql = $only_actif ? "p.statut IN ('actif', 'rupture_stock')" : '1=1';
    $extra = '';
    if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
        $extra = ' AND p.admin_id = :boutique_admin_id';
    }
    $rf = produits_region_sql_with_alias($boutique_admin_id);
    $extra .= $rf['sql'];
    $vj = produits_sql_vendeur_fragment();
    $sql = '
        SELECT p.*, c.nom as categorie_nom ' . $vj['select'] . '
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        ' . $vj['join'] . '
        WHERE ' . $statut_sql . '
        AND p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\'
        AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :suf
        ' . $extra . '
        ORDER BY p.date_creation DESC
        LIMIT :limit OFFSET :offset
    ';

    try {
        $stmt = $db->prepare($sql);
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':suf', $suffix5, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits correspondant aux 5 derniers chiffres
 */
function count_produits_by_identifiant_suffix_5_chiffres(string $suffix5, bool $only_actif = true, int|string|null $boutique_admin_id = null): int
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return 0;
    }
    $suffix5 = preg_replace('/\D/', '', (string) $suffix5);
    if (strlen($suffix5) !== 5) {
        return 0;
    }

    $statut_sql = $only_actif ? "statut IN ('actif', 'rupture_stock')" : '1=1';
    $sql = '
        SELECT COUNT(*) FROM produits
        WHERE ' . $statut_sql . '
        AND identifiant_interne IS NOT NULL AND TRIM(identifiant_interne) != \'\'
        AND ' . produits_sql_identifiant_suffix_5_expr('') . ' = :suf
    ';
    $params = ['suf' => $suffix5];
    if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
        $sql .= ' AND admin_id = :boutique_admin_id';
        $params['boutique_admin_id'] = (int) $boutique_admin_id;
    }
    $rf = produits_region_sql_plain($boutique_admin_id);
    $sql .= $rf['sql'];
    produits_merge_geo_filter_params($params, $rf);

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Attribue un identifiant FPLxxxxxx si absent (produits anciens)
 * @return string|null Le code attribué ou existant
 */
function ensure_produit_identifiant_interne(int $produit_id): ?string
{
    global $db;

    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_has_column('identifiant_interne')) {
        return null;
    }

    $p = get_produit_by_id($produit_id);
    if (!$p) {
        return null;
    }
    if (!empty($p['identifiant_interne'])) {
        return trim($p['identifiant_interne']);
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $admin_id = isset($p['admin_id']) ? (int) $p['admin_id'] : 0;
        $ident = generate_next_identifiant_interne_produit($admin_id > 0 ? $admin_id : null);
        if (!$ident) {
            return null;
        }
        try {
            $stmt = $db->prepare('
                UPDATE produits
                SET identifiant_interne = :i
                WHERE id = :id AND (identifiant_interne IS NULL OR identifiant_interne = \'\')
            ');
            $stmt->execute(['i' => $ident, 'id' => $produit_id]);
            if ($stmt->rowCount() > 0) {
                return $ident;
            }
            $p2 = get_produit_by_id($produit_id);
            if ($p2 && !empty($p2['identifiant_interne'])) {
                return trim($p2['identifiant_interne']);
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                continue;
            }
            return null;
        }
    }

    return null;
}

/**
 * Récupère tous les produits actifs avec pagination
 * @param int $offset Nombre de produits à ignorer (pour pagination)
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits
 */
function get_all_produits_paginated(int $offset = 0, int $limit = 20, int|string|null $boutique_admin_id = null, int|string|null $shuffle_seed = null): array
{
    global $db;

    try {
        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $where = "p.statut IN ('actif', 'rupture_stock')";
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = :boutique_admin_id';
        }
        $rf = produits_region_sql_with_alias($boutique_admin_id);
        $where .= $rf['sql'];
        $order = 'p.date_creation DESC';
        if ($shuffle_seed !== null && $shuffle_seed !== '') {
            $order = 'RAND(' . max(1, (int) $shuffle_seed) . ')';
        }
        $stmt = $db->prepare("
            SELECT p.*, " . $rj['categorie_nom_sql'] . " as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $rj['join'] . "
            " . $vj['join'] . "
            WHERE $where
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ");

        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Terme de recherche normalisé (minuscules, espaces).
 */
function produits_recherche_normalize(string $recherche): string
{
    $t = trim(preg_replace('/\s+/u', ' ', $recherche));
    if ($t === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($t, 'UTF-8');
    }
    return strtolower($t);
}

/**
 * Motif LIKE insensible à la casse (%terme%).
 */
function produits_recherche_like_pattern(string $recherche): string
{
    $t = produits_recherche_normalize($recherche);
    return $t === '' ? '' : '%' . $t . '%';
}

/**
 * Condition SQL LIKE insensible à la casse (paramètres nommés uniques pour PDO natif).
 *
 * @return array{sql: string, param_keys: array<int, string>}
 */
function produits_sql_recherche_like_condition(string $alias = 'p', bool $include_categorie = false): array
{
    $prefix = '';
    if ($alias !== '') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($a === '') {
            $a = 'p';
        }
        $prefix = $a . '.';
    }
    $param_keys = ['term_nom', 'term_desc'];
    $parts = [
        'LOWER(' . $prefix . 'nom) LIKE :term_nom',
        'LOWER(' . $prefix . 'description) LIKE :term_desc',
    ];
    if (produits_has_column('identifiant_interne')) {
        $param_keys[] = 'term_ident';
        $parts[] = 'LOWER(' . $prefix . 'identifiant_interne) LIKE :term_ident';
    }
    if ($include_categorie) {
        $param_keys[] = 'term_cat';
        $parts[] = 'LOWER(c.nom) LIKE :term_cat';
    }
    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'param_keys' => $param_keys,
    ];
}

/**
 * Paramètres LIKE (même motif pour chaque colonne).
 *
 * @param array<int, string> $param_keys
 * @return array<string, string>
 */
function produits_recherche_like_params_for_keys(string $recherche, array $param_keys): array
{
    $pattern = produits_recherche_like_pattern($recherche);
    $params = [];
    foreach ($param_keys as $key) {
        $params[$key] = $pattern;
    }
    return $params;
}

/**
 * Lie les paramètres LIKE sur un PDOStatement.
 */
function produits_bind_recherche_like_stmt(PDOStatement $stmt, string $recherche, array $param_keys): void
{
    foreach (produits_recherche_like_params_for_keys($recherche, $param_keys) as $key => $pattern) {
        $stmt->bindValue(':' . $key, $pattern, PDO::PARAM_STR);
    }
}

/**
 * Tri par pertinence (correspondance exacte > début de nom > contenu > description).
 */
function produits_sql_recherche_relevance_order(string $alias = 'p', string $fallback_order = 'p.date_creation DESC'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
    if ($a === '') {
        $a = 'p';
    }
    return 'CASE
            WHEN LOWER(' . $a . '.nom) = :rel_exact THEN 0
            WHEN LOWER(' . $a . '.nom) LIKE :rel_start THEN 1
            WHEN LOWER(' . $a . '.nom) LIKE :rel_contains_nom THEN 2
            WHEN LOWER(' . $a . '.description) LIKE :rel_contains_desc THEN 3
            ELSE 4
        END ASC,
        CHAR_LENGTH(' . $a . '.nom) ASC,
        ' . $fallback_order;
}

/**
 * Lie les paramètres de pertinence sur un PDOStatement.
 */
function produits_bind_recherche_relevance_stmt(PDOStatement $stmt, string $recherche): void
{
    $t = produits_recherche_normalize($recherche);
    $contains = '%' . $t . '%';
    $stmt->bindValue(':rel_exact', $t, PDO::PARAM_STR);
    $stmt->bindValue(':rel_start', $t . '%', PDO::PARAM_STR);
    $stmt->bindValue(':rel_contains_nom', $contains, PDO::PARAM_STR);
    $stmt->bindValue(':rel_contains_desc', $contains, PDO::PARAM_STR);
}

/**
 * Journalise une erreur SQL recherche (prod : PDO natif sans emulate).
 */
function produits_log_recherche_sql_error(PDOException $e, string $context): void
{
    error_log('[recherche produits][' . $context . '] ' . $e->getMessage());
}

/**
 * Recherche des produits par nom ou description
 * @param string $recherche Terme de recherche
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre max de résultats
 * @return array Tableau des produits trouvés
 */
function search_produits(string $recherche, int $offset = 0, int $limit = 20, int|string|null $boutique_admin_id = null): array
{
    global $db;

    if (empty(trim($recherche))) {
        return get_all_produits_paginated($offset, $limit, $boutique_admin_id);
    }

    $t = trim($recherche);
    if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $t)) {
        return get_produits_by_identifiant_suffix_5_chiffres($t, $offset, $limit, true, $boutique_admin_id);
    }
    if (produits_has_column('identifiant_interne') && produit_identifiant_interne_is_valid_format(strtoupper($t))) {
        $p = get_produit_by_identifiant_interne(strtoupper($t), true);
        if (!$p) {
            return [];
        }
        require_once __DIR__ . '/../includes/marketplace_region_filter.php';
        if (!produit_visible_in_marketplace_region($p)) {
            return [];
        }
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            return ((int) $p['admin_id'] === (int) $boutique_admin_id) ? [$p] : [];
        }
        return [$p];
    }

    try {
        $like = produits_sql_recherche_like_condition('p', true);
        $where = "p.statut IN ('actif', 'rupture_stock') AND " . $like['sql'];
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = :boutique_admin_id';
        }
        $where .= produits_region_sql_with_alias($boutique_admin_id)['sql'];
        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $order = produits_sql_recherche_relevance_order('p', 'p.date_creation DESC');
        $stmt = $db->prepare("
            SELECT p.*, " . $rj['categorie_nom_sql'] . " as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $rj['join'] . "
            " . $vj['join'] . "
            WHERE $where
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ");
        produits_bind_recherche_like_stmt($stmt, $recherche, $like['param_keys']);
        produits_bind_recherche_relevance_stmt($stmt, $recherche);
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        produits_log_recherche_sql_error($e, 'search_produits');
        return [];
    }
}

/**
 * Compte les produits correspondant à une recherche
 * @param string $recherche Terme de recherche
 * @return int Nombre de produits
 */
function count_search_produits(string $recherche, int|string|null $boutique_admin_id = null): int
{
    global $db;

    if (empty(trim($recherche))) {
        return count_all_produits_actifs($boutique_admin_id);
    }

    $t = trim($recherche);
    if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $t)) {
        return count_produits_by_identifiant_suffix_5_chiffres($t, true, $boutique_admin_id);
    }
    if (produits_has_column('identifiant_interne') && produit_identifiant_interne_is_valid_format(strtoupper($t))) {
        $p = get_produit_by_identifiant_interne(strtoupper($t), true);
        if (!$p) {
            return 0;
        }
        require_once __DIR__ . '/../includes/marketplace_region_filter.php';
        if (!produit_visible_in_marketplace_region($p)) {
            return 0;
        }
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            return ((int) $p['admin_id'] === (int) $boutique_admin_id) ? 1 : 0;
        }
        return 1;
    }

    try {
        $like = produits_sql_recherche_like_condition('', false);
        $where = "statut IN ('actif', 'rupture_stock') AND " . $like['sql'];
        $params = produits_recherche_like_params_for_keys($recherche, $like['param_keys']);
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_plain($boutique_admin_id);
        $where .= $rf['sql'];
        produits_merge_geo_filter_params($params, $rf);
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        produits_log_recherche_sql_error($e, 'count_search_produits');
        return 0;
    }
}

/**
 * Recherche des produits avec filtres (recherche texte + prix min/max + catégorie)
 * @param string $recherche Terme de recherche (optionnel)
 * @param float|null $prix_min Prix minimum en FCFA (optionnel)
 * @param float|null $prix_max Prix maximum en FCFA (optionnel)
 * @param int|null $categorie_id ID catégorie (optionnel)
 * @param string $tri Tri: 'date', 'prix_asc', 'prix_desc', 'nom' (défaut: date)
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre max de résultats
 * @return array Tableau des produits trouvés
 */
function search_produits_with_filters(string $recherche = '', float|int|string|null $prix_min = null, float|int|string|null $prix_max = null, int|string|null $categorie_id = null, string $tri = 'date', int $offset = 0, int $limit = 50, int|string|null $boutique_admin_id = null): array
{
    global $db;

    try {
        $conditions = ["p.statut IN ('actif', 'rupture_stock')"];
        $params = [];

        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $conditions[] = 'p.admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_with_alias($boutique_admin_id);
        if ($rf['sql'] !== '') {
            $conditions[] = ltrim($rf['sql'], ' AND ');
            produits_merge_geo_filter_params($params, $rf);
        }

        if (!empty(trim($recherche))) {
            $tr = trim($recherche);
            if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $tr)) {
                $conditions[] = 'p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :suffix5';
                $params['suffix5'] = $tr;
            } elseif (produits_has_column('identifiant_interne') && produit_identifiant_interne_is_valid_format(strtoupper($tr))) {
                $conditions[] = 'UPPER(TRIM(p.identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $like = produits_sql_recherche_like_condition('p', true);
                $conditions[] = $like['sql'];
                $params = array_merge(
                    $params,
                    produits_recherche_like_params_for_keys($tr, $like['param_keys'])
                );
            }
        }

        if ($prix_min !== null && $prix_min !== '') {
            $prix_min = (float) $prix_min;
            $conditions[] = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) >= :prix_min";
            $params['prix_min'] = $prix_min;
        }

        if ($prix_max !== null && $prix_max !== '') {
            $prix_max = (float) $prix_max;
            $conditions[] = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) <= :prix_max";
            $params['prix_max'] = $prix_max;
        }

        if ($categorie_id !== null && $categorie_id !== '') {
            require_once __DIR__ . '/model_categories.php';
            $catIds = function_exists('category_expanded_ids_for_products')
                ? category_expanded_ids_for_products((int) $categorie_id)
                : [(int) $categorie_id];
            if (!empty($catIds)) {
                $ph = [];
                foreach (array_values($catIds) as $ci => $cid) {
                    $key = 'catf_' . $ci;
                    $ph[] = ':' . $key;
                    $params[$key] = (int) $cid;
                }
                $conditions[] = 'p.categorie_id IN (' . implode(', ', $ph) . ')';
            }
        }

        $recherche_texte = !empty(trim($recherche)) ? trim($recherche) : '';
        $order = "p.date_creation DESC";
        if ($tri === 'prix_asc') {
            $order = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) ASC";
        } elseif ($tri === 'prix_desc') {
            $order = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) DESC";
        } elseif ($tri === 'nom') {
            $order = "p.nom ASC";
        } elseif ($recherche_texte !== '' && $tri === 'date') {
            $order = produits_sql_recherche_relevance_order('p', 'p.date_creation DESC');
        }

        $where = implode(' AND ', $conditions);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $stmt = $db->prepare("
            SELECT p.*, " . $rj['categorie_nom_sql'] . " as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $rj['join'] . "
            " . $vj['join'] . "
            WHERE $where
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            if ($k === 'limit' || $k === 'offset') {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        if ($recherche_texte !== '' && $tri === 'date') {
            produits_bind_recherche_relevance_stmt($stmt, $recherche_texte);
        }
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ?: [];
    } catch (PDOException $e) {
        produits_log_recherche_sql_error($e, 'search_produits_with_filters');
        return [];
    }
}

/**
 * Compte les produits avec les mêmes filtres que search_produits_with_filters
 */
function count_search_produits_with_filters(string $recherche = '', float|int|string|null $prix_min = null, float|int|string|null $prix_max = null, int|string|null $categorie_id = null, int|string|null $boutique_admin_id = null): int
{
    global $db;

    try {
        $conditions = ["statut IN ('actif', 'rupture_stock')"];
        $params = [];

        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $conditions[] = 'admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_plain($boutique_admin_id);
        if ($rf['sql'] !== '') {
            $conditions[] = ltrim($rf['sql'], ' AND ');
            produits_merge_geo_filter_params($params, $rf);
        }

        if (!empty(trim($recherche))) {
            $tr = trim($recherche);
            if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $tr)) {
                $conditions[] = 'identifiant_interne IS NOT NULL AND TRIM(identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('') . ' = :suffix5';
                $params['suffix5'] = $tr;
            } elseif (produits_has_column('identifiant_interne') && produit_identifiant_interne_is_valid_format(strtoupper($tr))) {
                $conditions[] = 'UPPER(TRIM(identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $like = produits_sql_recherche_like_condition('', false);
                $conditions[] = $like['sql'];
                $params = array_merge(
                    $params,
                    produits_recherche_like_params_for_keys($tr, $like['param_keys'])
                );
            }
        }

        if ($prix_min !== null && $prix_min !== '') {
            $prix_min = (float) $prix_min;
            $conditions[] = "(CASE WHEN prix_promotion IS NOT NULL AND prix_promotion > 0 AND prix_promotion < prix THEN prix_promotion ELSE prix END) >= :prix_min";
            $params['prix_min'] = $prix_min;
        }

        if ($prix_max !== null && $prix_max !== '') {
            $prix_max = (float) $prix_max;
            $conditions[] = "(CASE WHEN prix_promotion IS NOT NULL AND prix_promotion > 0 AND prix_promotion < prix THEN prix_promotion ELSE prix END) <= :prix_max";
            $params['prix_max'] = $prix_max;
        }

        if ($categorie_id !== null && $categorie_id !== '') {
            require_once __DIR__ . '/model_categories.php';
            $catIds = function_exists('category_expanded_ids_for_products')
                ? category_expanded_ids_for_products((int) $categorie_id)
                : [(int) $categorie_id];
            if (!empty($catIds)) {
                $ph = [];
                foreach (array_values($catIds) as $ci => $cid) {
                    $key = 'catc_' . $ci;
                    $ph[] = ':' . $key;
                    $params[$key] = (int) $cid;
                }
                $conditions[] = 'categorie_id IN (' . implode(', ', $ph) . ')';
            }
        }

        $where = implode(' AND ', $conditions);
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE $where");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        produits_log_recherche_sql_error($e, 'count_search_produits_with_filters');
        return 0;
    }
}

/**
 * Compte le nombre total de produits actifs
 * @return int Nombre total de produits actifs
 */
function count_all_produits_actifs(int|string|null $boutique_admin_id = null): int
{
    global $db;

    try {
        $where = "statut IN ('actif', 'rupture_stock')";
        $params = [];
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_plain($boutique_admin_id);
        $where .= $rf['sql'];
        produits_merge_geo_filter_params($params, $rf);
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les produits en promotion (prix_promotion défini et inférieur au prix)
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits en promo
 */
function get_produits_en_promo(int $offset = 0, int $limit = 50, int|string|null $boutique_admin_id = null): array
{
    global $db;

    try {
        $where = "p.statut IN ('actif', 'rupture_stock') 
            AND p.prix_promotion IS NOT NULL 
            AND p.prix_promotion > 0 
            AND p.prix_promotion < p.prix";
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = :boutique_admin_id';
        }
        $where .= produits_region_sql_with_alias($boutique_admin_id)['sql'];
        $vj = produits_sql_vendeur_fragment();
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $vj['join'] . "
            WHERE $where
            ORDER BY (p.prix - p.prix_promotion) DESC, p.date_creation DESC
            LIMIT :limit OFFSET :offset
        ");
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits en promotion
 * @return int Nombre de produits en promo
 */
function count_produits_en_promo(int|string|null $boutique_admin_id = null): int
{
    global $db;

    try {
        $where = "statut IN ('actif', 'rupture_stock') 
            AND prix_promotion IS NOT NULL 
            AND prix_promotion > 0 
            AND prix_promotion < prix";
        $params = [];
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND admin_id = :boutique_admin_id';
            $params['boutique_admin_id'] = (int) $boutique_admin_id;
        }
        $rf = produits_region_sql_plain($boutique_admin_id);
        $where .= $rf['sql'];
        produits_merge_geo_filter_params($params, $rf);
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les produits les plus récents (nouveautés)
 * @param int $limit Nombre maximum de produits à retourner (par défaut 4)
 * @return array Tableau des produits les plus récents
 */
function get_produits_nouveautes(int $limit = 4, int|string|null $boutique_admin_id = null): array
{
    global $db;

    try {
        $where = "p.statut IN ('actif', 'rupture_stock')";
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = :boutique_admin_id';
        }
        $where .= produits_region_sql_with_alias($boutique_admin_id)['sql'];
        $vj = produits_sql_vendeur_fragment();
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $vj['join'] . "
            WHERE $where
            ORDER BY p.date_creation DESC, p.date_modification DESC
            LIMIT :limit
        ");

        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère tous les produits nouveautés avec pagination
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits les plus récents
 */
function get_produits_nouveautes_paginated(int $offset = 0, int $limit = 20, int|string|null $boutique_admin_id = null, int|string|null $shuffle_seed = null): array
{
    global $db;

    try {
        $where = "p.statut IN ('actif', 'rupture_stock')";
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where .= ' AND p.admin_id = :boutique_admin_id';
        }
        $where .= produits_region_sql_with_alias($boutique_admin_id)['sql'];
        $order = 'p.date_creation DESC, p.date_modification DESC';
        if ($shuffle_seed !== null && $shuffle_seed !== '') {
            $order = 'RAND(' . max(1, (int) $shuffle_seed) . ')';
        }
        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $stmt = $db->prepare("
            SELECT p.*, " . $rj['categorie_nom_sql'] . " as categorie_nom " . $vj['select'] . "
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            " . $rj['join'] . "
            " . $vj['join'] . "
            WHERE $where
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ");
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les produits vedettes (les plus ajoutés au panier et les plus commandés)
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits vedettes mélangés aléatoirement
 */
function get_produits_vedettes(int $limit = 20, int|string|null $boutique_admin_id = null): array
{
    global $db;

    try {
        $where_extra = '';
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $where_extra = ' AND p.admin_id = :boutique_admin_id';
        }
        $where_extra .= produits_region_sql_with_alias($boutique_admin_id)['sql'];
        // Récupérer les produits les plus ajoutés au panier et les plus commandés
        $vj = produits_sql_vendeur_fragment();
        $sql = "
            SELECT DISTINCT
                p.*,
                c.nom as categorie_nom,
                COALESCE(panier_stats.nb_ajouts_panier, 0) as nb_ajouts_panier,
                COALESCE(commande_stats.nb_commandes, 0) as nb_commandes,
                (COALESCE(panier_stats.nb_ajouts_panier, 0) + COALESCE(commande_stats.nb_commandes, 0)) as score_popularite
                " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            LEFT JOIN (
                SELECT produit_id, COUNT(*) as nb_ajouts_panier
                FROM panier
                GROUP BY produit_id
            ) panier_stats ON p.id = panier_stats.produit_id
            LEFT JOIN (
                SELECT produit_id, COUNT(*) as nb_commandes
                FROM commande_produits
                GROUP BY produit_id
            ) commande_stats ON p.id = commande_stats.produit_id
            WHERE p.statut IN ('actif', 'rupture_stock') $where_extra
            HAVING score_popularite > 0
            ORDER BY score_popularite DESC, p.date_creation DESC
            LIMIT :limit
        ";
        $stmt = $db->prepare($sql);
        if ($boutique_admin_id !== null && $boutique_admin_id !== '' && produits_has_column('admin_id')) {
            $stmt->bindValue(':boutique_admin_id', (int) $boutique_admin_id, PDO::PARAM_INT);
        }
        produits_bind_region_stmt($stmt, $boutique_admin_id);

        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT); // Récupérer plus pour avoir de la variété
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucun produit vedette (pas encore de statistiques), récupérer tous les produits actifs
        if (empty($produits)) {
            $produits = get_all_produits_paginated(0, max($limit * 2, 50), $boutique_admin_id);
        }

        // Mélanger aléatoirement les produits à chaque appel
        if (!empty($produits)) {
            // Utiliser une graine basée sur le temps pour varier l'ordre
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($produits);
            // Limiter au nombre demandé après le mélange
            $produits = array_slice($produits, 0, $limit);
        }

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner tous les produits actifs mélangés
        $produits = get_all_produits_paginated(0, max($limit * 2, 50), $boutique_admin_id);
        if (!empty($produits)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($produits);
            $produits = array_slice($produits, 0, $limit);
        }
        return $produits ? $produits : [];
    }
}

/**
 * Crée un nouveau produit
 * @param array $data Les données du produit
 * @return int|false L'ID du produit créé ou False en cas d'erreur
 */
function create_produit(array $data): int|false
{
    global $db;

    try {
        $cols = "nom, description, prix, prix_promotion, stock, categorie_id, image_principale, images, poids, unite, date_creation, statut";
        $vals = ":nom, :description, :prix, :prix_promotion, :stock, :categorie_id, :image_principale, :images, :poids, :unite, NOW(), :statut";
        if (produits_has_column('admin_id') && isset($data['admin_id'])) {
            $cols = "admin_id, " . $cols;
            $vals = ":admin_id, " . $vals;
        }
        $params = [
            'nom' => $data['nom'],
            'description' => $data['description'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'stock' => $data['stock'],
            'categorie_id' => $data['categorie_id'],
            'image_principale' => $data['image_principale'] ?? null,
            'images' => $data['images'] ?? null,
            'poids' => $data['poids'] ?? null,
            'unite' => $data['unite'] ?? 'unité',
            'statut' => $data['statut'] ?? 'actif'
        ];
        if (produits_has_column('admin_id') && isset($data['admin_id'])) {
            $params['admin_id'] = (int) $data['admin_id'];
        }
        if (produits_has_column('identifiant_interne')) {
            $admin_id_ident = isset($data['admin_id']) ? (int) $data['admin_id'] : 0;
            $ident = isset($data['identifiant_interne']) && $data['identifiant_interne'] !== ''
                ? $data['identifiant_interne']
                : generate_next_identifiant_interne_produit($admin_id_ident > 0 ? $admin_id_ident : null);
            if ($ident) {
                $cols = "identifiant_interne, " . $cols;
                $vals = ":identifiant_interne, " . $vals;
                $params['identifiant_interne'] = $ident;
            }
        }
        if (produits_has_column('etage')) {
            $cols .= ", etage";
            $vals .= ", :etage";
            $params['etage'] = isset($data['etage']) && $data['etage'] !== '' ? trim($data['etage']) : null;
        }
        if (produits_has_column('numero_rayon')) {
            $cols .= ", numero_rayon";
            $vals .= ", :numero_rayon";
            $params['numero_rayon'] = isset($data['numero_rayon']) && $data['numero_rayon'] !== '' ? trim($data['numero_rayon']) : null;
        }
        if (produits_has_column('categorie_generale_id')) {
            $cols .= ", categorie_generale_id";
            $vals .= ", :categorie_generale_id";
            $cg = $data['categorie_generale_id'] ?? null;
            $params['categorie_generale_id'] = ($cg !== null && $cg !== '') ? (int) $cg : null;
        }
        if (produits_has_column('mesure')) {
            $cols .= ", mesure";
            $vals .= ", :mesure";
            $params['mesure'] = isset($data['mesure']) && (string) $data['mesure'] !== '' ? trim((string) $data['mesure']) : null;
        }
        if (produits_has_column('prix_negociable')) {
            $cols .= ", prix_negociable";
            $vals .= ", :prix_negociable";
            $params['prix_negociable'] = isset($data['prix_negociable']) ? (int) $data['prix_negociable'] : 1;
        }
        $with_extras = isset($data['couleurs']) || isset($data['taille']);
        if ($with_extras) {
            $cols .= ", couleurs, taille";
            $vals .= ", :couleurs, :taille";
            $params['couleurs'] = $data['couleurs'] ?? null;
            $params['taille'] = $data['taille'] ?? null;
        }
        try {
            $stmt = $db->prepare("INSERT INTO produits ($cols) VALUES ($vals)");
            $result = $stmt->execute($params);
        } catch (PDOException $e) {
            if ($with_extras && (strpos($e->getMessage(), 'couleurs') !== false || strpos($e->getMessage(), 'taille') !== false)) {
                $cols = "nom, description, prix, prix_promotion, stock, categorie_id, image_principale, images, poids, unite, date_creation, statut";
                $vals = ":nom, :description, :prix, :prix_promotion, :stock, :categorie_id, :image_principale, :images, :poids, :unite, NOW(), :statut";
                unset($params['couleurs'], $params['taille']);
                $stmt = $db->prepare("INSERT INTO produits ($cols) VALUES ($vals)");
                $result = $stmt->execute($params);
            } else {
                throw $e;
            }
        }

        if ($result) {
            return (int) $db->lastInsertId();
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un produit
 * @param int $id L'ID du produit
 * @param array $data Les nouvelles données du produit
 * @return bool True en cas de succès, False sinon
 */
function update_produit(int $id, array $data): bool
{
    global $db;

    try {
        $sets = "nom = :nom, description = :description, prix = :prix, prix_promotion = :prix_promotion, stock = :stock, categorie_id = :categorie_id, image_principale = :image_principale, images = :images, poids = :poids, unite = :unite, statut = :statut, date_modification = NOW()";
        $params = [
            'id' => $id,
            'nom' => $data['nom'],
            'description' => $data['description'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'stock' => $data['stock'],
            'categorie_id' => $data['categorie_id'],
            'image_principale' => $data['image_principale'] ?? null,
            'images' => $data['images'] ?? null,
            'poids' => $data['poids'] ?? null,
            'unite' => $data['unite'] ?? 'unité',
            'statut' => $data['statut'] ?? 'actif'
        ];
        if (produits_has_column('etage')) {
            $sets .= ", etage = :etage";
            $params['etage'] = isset($data['etage']) && $data['etage'] !== '' ? trim($data['etage']) : null;
        }
        if (produits_has_column('numero_rayon')) {
            $sets .= ", numero_rayon = :numero_rayon";
            $params['numero_rayon'] = isset($data['numero_rayon']) && $data['numero_rayon'] !== '' ? trim($data['numero_rayon']) : null;
        }
        if (produits_has_column('categorie_generale_id') && array_key_exists('categorie_generale_id', $data)) {
            $sets .= ", categorie_generale_id = :categorie_generale_id";
            $cg = $data['categorie_generale_id'];
            $params['categorie_generale_id'] = ($cg !== null && $cg !== '') ? (int) $cg : null;
        }
        if (produits_has_column('mesure') && array_key_exists('mesure', $data)) {
            $sets .= ", mesure = :mesure";
            $params['mesure'] = isset($data['mesure']) && (string) $data['mesure'] !== '' ? trim((string) $data['mesure']) : null;
        }
        if (produits_has_column('prix_negociable') && array_key_exists('prix_negociable', $data)) {
            $sets .= ", prix_negociable = :prix_negociable";
            $params['prix_negociable'] = (int) $data['prix_negociable'] ? 1 : 0;
        }
        $with_extras = isset($data['couleurs']) || isset($data['taille']);
        if ($with_extras) {
            $sets .= ", couleurs = :couleurs, taille = :taille";
            $params['couleurs'] = $data['couleurs'] ?? null;
            $params['taille'] = $data['taille'] ?? null;
        }
        try {
            $stmt = $db->prepare("UPDATE produits SET $sets WHERE id = :id");
            return $stmt->execute($params);
        } catch (PDOException $e) {
            if ($with_extras && (strpos($e->getMessage(), 'couleurs') !== false || strpos($e->getMessage(), 'taille') !== false)) {
                $sets = "nom = :nom, description = :description, prix = :prix, prix_promotion = :prix_promotion, stock = :stock, categorie_id = :categorie_id, image_principale = :image_principale, images = :images, poids = :poids, unite = :unite, statut = :statut, date_modification = NOW()";
                unset($params['couleurs'], $params['taille']);
                $stmt = $db->prepare("UPDATE produits SET $sets WHERE id = :id");
                return $stmt->execute($params);
            }
            throw $e;
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un produit
 * @param int $id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function delete_produit(int $id): bool
{
    global $db;

    try {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        if (file_exists(__DIR__ . '/model_genres.php')) {
            require_once __DIR__ . '/model_genres.php';
            if (function_exists('delete_produits_genres_for_produit')) {
                delete_produits_genres_for_produit($id);
            }
        }
        $stmt = $db->prepare("DELETE FROM produits WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'un produit
 * @param int $id L'ID du produit
 * @param string $statut Le nouveau statut
 * @return bool True en cas de succès, False sinon
 */
function update_produit_statut(int $id, string $statut): bool
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE produits SET statut = :statut, date_modification = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'statut' => $statut]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Parse poids ou taille avec surcoûts (JSON ou comma-separated)
 * @param string|null $raw Valeur brute (JSON [{"v":"500g","s":300}] ou "500g, 1kg")
 * @return array [["v"=>"500g","s"=>0], ["v"=>"1kg","s"=>300]]
 */
function parse_options_with_surcharge(?string $raw): array
{
    if (empty(trim($raw ?? '')))
        return [];
    $raw = trim($raw);
    if ($raw === '[]' || $raw === '[ ]' || strtolower($raw) === 'null') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (!is_array($dec) || empty($dec)) {
            return [];
        }
        $out = [];
        foreach ($dec as $item) {
            if (is_array($item) && isset($item['v']) && trim((string) $item['v']) !== '') {
                $out[] = ['v' => trim($item['v']), 's' => isset($item['s']) ? (float) $item['s'] : 0];
            } elseif (is_string($item) && trim($item) !== '') {
                $out[] = ['v' => trim($item), 's' => 0];
            }
        }
        return $out;
    }
    $arr = array_map('trim', array_filter(explode(',', $raw)));
    $arr = array_values(array_filter($arr, function ($x) {
        $v = trim((string) $x);
        return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
    }));
    return array_map(function ($x) {
        return ['v' => $x, 's' => 0]; }, $arr);
}

/**
 * Récupère le surcoût pour une option (poids ou taille)
 * @param array $options Résultat de parse_options_with_surcharge
 * @param string $value Valeur sélectionnée (ex: "1kg")
 * @return float Surcoût en FCFA
 */
function get_surcharge_for_option(array $options, string $value): float
{
    if (empty($value))
        return 0;
    foreach ($options as $opt) {
        if (trim($opt['v']) === trim($value)) {
            return (float) ($opt['s'] ?? 0);
        }
    }
    return 0;
}

/**
 * Décrémente le stock d'un produit (produits.stock)
 * @param int $produit_id ID du produit
 * @param int $quantite Quantité à soustraire
 * @return int|false Nouvelle quantité ou False en cas d'erreur
 */
function decrement_produit_stock(int $produit_id, int $quantite): int|false
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE produits SET stock = GREATEST(0, stock - :qty), date_modification = NOW() WHERE id = :id");
        $stmt->execute(['id' => (int) $produit_id, 'qty' => (int) $quantite]);
        $stmt = $db->prepare("SELECT stock FROM produits WHERE id = :id");
        $stmt->execute(['id' => (int) $produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['stock'] : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche des produits en stock pour commande manuelle
 * @param string $recherche Terme de recherche (nom produit ou catégorie)
 * @param int $limit Nombre max de résultats
 * @return array Produits avec stock > 0
 */
function search_produits_en_stock_commande_manuelle(string $recherche = '', int $limit = 30): array
{
    global $db;

    try {
        $sql = "
            SELECT p.id, p.nom, p.prix, p.prix_promotion, p.stock, p.image_principale,
                   c.nom as categorie_nom,
                   p.stock as stock_dispo
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE p.statut IN ('actif', 'rupture_stock') AND p.stock > 0
        ";
        $params = ['limit' => (int) $limit];

        if (!empty(trim($recherche))) {
            $sql .= " AND (p.nom LIKE :term OR c.nom LIKE :term2)";
            $params['term'] = '%' . trim($recherche) . '%';
            $params['term2'] = '%' . trim($recherche) . '%';
        }

        $sql .= " ORDER BY p.nom ASC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @deprecated Table stock_articles supprimée. Retourne toujours [].
 * Le stock est géré uniquement par produits.stock.
 */
function get_produits_by_stock_article(int $stock_article_id): array
{
    return [];
}

/**
 * Produits les plus vendus (quantités sur commandes, hors commandes annulées).
 * Retourne une liste riche (catégorie, vendeur) pour affichage marketplace.
 *
 * @param int $max_rows Nombre max de produits « candidats » (avant mélange côté accueil)
 * @return array
 */
function get_produits_plus_vendus_marketplace(int $max_rows = 60): array
{
    global $db;
    $max_rows = max(10, (int) $max_rows);
    try {
        $vj = produits_sql_vendeur_fragment();
        $rj = produits_sql_rayon_categorie_nom_fragment();
        $region_sql = produits_region_sql_with_alias(null)['sql'];
        $sql = "
            SELECT p.*, " . $rj['categorie_nom_sql'] . " AS categorie_nom, COALESCE(v.qte, 0) AS qte_vendue
            " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $rj['join'] . "
            " . $vj['join'] . "
            INNER JOIN (
                SELECT cp.produit_id, SUM(cp.quantite) AS qte
                FROM commande_produits cp
                INNER JOIN commandes co ON co.id = cp.commande_id
                WHERE co.statut <> 'annulee'
                GROUP BY cp.produit_id
            ) v ON v.produit_id = p.id
            WHERE p.statut IN ('actif', 'rupture_stock') $region_sql
            ORDER BY v.qte DESC, p.id DESC
            LIMIT " . (int) $max_rows;
        $stmt = $db->prepare($sql);
        produits_bind_region_stmt($stmt, null);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère des nouveautés (récent) puis mélange — pour panneau « Nouveautés » accueil.
 *
 * @param int $retour  Ex. 4
 * @param int $pool    Taille du pool le plus récent
 * @return array
 */
function get_produits_nouveautes_aleatoires_panneau(int $retour = 4, int $pool = 50): array
{
    $retour = max(1, (int) $retour);
    $pool = max($retour, (int) $pool);
    $rows = get_produits_nouveautes_paginated(0, $pool, null);
    if (empty($rows) || !is_array($rows)) {
        $rows = get_all_produits_paginated(0, max(30, $pool), null);
    }
    if (empty($rows)) {
        return [];
    }
    require_once __DIR__ . '/../includes/catalogue_shuffle.php';
    $rows = catalogue_melanger_produits($rows);
    return array_slice($rows, 0, $retour);
}

/**
 * Colonnes modération plateforme (blocage produit) disponibles.
 */
function produit_moderation_plateforme_active(): bool
{
    return produits_has_column('bloque_motif') && produits_has_column('bloque_champs');
}

/**
 * Libellé statut produit (admin / vendeur).
 */
function produit_statut_label(string $statut): string
{
    $map = [
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'rupture_stock' => 'Rupture',
        'bloque' => 'Bloqué',
    ];
    return $map[(string) $statut] ?? ucfirst((string) $statut);
}

/**
 * Champs à corriger pour lever un blocage (labels).
 *
 * @return array<string, string>
 */
function produit_bloque_champs_labels(string $champs_csv): array
{
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', (string) $champs_csv))) as $c) {
        if ($c === 'nom') {
            $out['nom'] = 'nom du produit';
        } elseif ($c === 'image') {
            $out['image'] = 'image principale';
        }
    }
    return $out;
}

/**
 * Liste des chemins images d'un produit (principale + galerie JSON).
 *
 * @param array<string, mixed> $produit
 * @return array<int, string>
 */
function produit_images_list_from_row(array $produit): array
{
    $out = [];
    $seen = [];
    if (!empty($produit['images'])) {
        $raw = trim((string) $produit['images']);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) && $raw !== '' && strpos($raw, ',') !== false) {
            $decoded = array_map('trim', explode(',', $raw));
        }
        if (is_array($decoded)) {
            foreach ($decoded as $img) {
                if (is_array($img)) {
                    $img = (string) ($img['path'] ?? $img['url'] ?? $img['src'] ?? '');
                }
                $path = trim(str_replace('\\', '/', (string) $img), '/');
                if (str_starts_with($path, 'upload/')) {
                    $path = substr($path, 7);
                }
                if ($path !== '' && !isset($seen[$path])) {
                    $seen[$path] = true;
                    $out[] = $path;
                }
            }
        }
    }
    $main = trim(str_replace('\\', '/', (string) ($produit['image_principale'] ?? '')), '/');
    if ($main !== '' && !isset($seen[$main])) {
        array_unshift($out, $main);
    } elseif ($main === '' && empty($out)) {
        return [];
    }
    if ($main !== '' && !empty($out) && $out[0] !== $main) {
        $out = array_values(array_unique(array_merge([$main], $out)));
    }
    return $out;
}

/**
 * Produits publiés d'une boutique (actif, rupture, bloqué) — Super Admin.
 *
 * @return array
 */
function super_admin_get_produits_boutique(int $admin_id): array
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return [];
    }
    $extra = produit_moderation_plateforme_active()
        ? ', p.bloque_motif, p.bloque_champs, p.bloque_nom_ref, p.bloque_image_ref, p.bloque_date'
        : '';
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.nom, p.image_principale, p.images, p.statut, p.prix, p.prix_promotion, p.stock, p.date_modification
            $extra
            FROM produits p
            WHERE p.admin_id = :aid
              AND p.statut IN ('actif', 'rupture_stock', 'bloque')
            ORDER BY p.date_modification DESC, p.id DESC
        ");
        $stmt->execute(['aid' => $admin_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Bloque un produit côté plateforme (masqué catalogue client).
 *
 * @param array<int, string> $champs 'nom' et/ou 'image'
 */
function super_admin_bloquer_produit(int $produit_id, string $motif, array $champs): bool
{
    global $db;
    if (!produit_moderation_plateforme_active()) {
        return false;
    }
    $produit_id = (int) $produit_id;
    $motif = trim((string) $motif);
    $allowed = ['nom', 'image'];
    $champs = array_values(array_unique(array_intersect($allowed, $champs)));
    if ($produit_id <= 0 || $motif === '' || empty($champs)) {
        return false;
    }
    $p = get_produit_by_id($produit_id);
    if (!$p) {
        return false;
    }
    $champs_csv = implode(',', $champs);
    try {
        $stmt = $db->prepare("
            UPDATE produits SET
                statut = 'bloque',
                bloque_motif = :motif,
                bloque_champs = :champs,
                bloque_nom_ref = :nom_ref,
                bloque_image_ref = :img_ref,
                bloque_date = NOW(),
                date_modification = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $produit_id,
            'motif' => $motif,
            'champs' => $champs_csv,
            'nom_ref' => (string) ($p['nom'] ?? ''),
            'img_ref' => (string) ($p['image_principale'] ?? ''),
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Débloque manuellement un produit (Super Admin).
 */
function super_admin_debloquer_produit(int $produit_id): bool
{
    global $db;
    if (!produit_moderation_plateforme_active()) {
        return false;
    }
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return false;
    }
    $p = get_produit_by_id($produit_id);
    if (!$p || ($p['statut'] ?? '') !== 'bloque') {
        return false;
    }
    $nouveau = ((int) ($p['stock'] ?? 0) > 0) ? 'actif' : 'rupture_stock';
    try {
        $stmt = $db->prepare("
            UPDATE produits SET
                statut = :st,
                bloque_motif = NULL,
                bloque_champs = NULL,
                bloque_nom_ref = NULL,
                bloque_image_ref = NULL,
                bloque_date = NULL,
                date_modification = NOW()
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $produit_id, 'st' => $nouveau]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Après modification vendeur : débloque si nom/image en cause ont été changés.
 */
function produit_tenter_debloquer_apres_modification(int $produit_id): bool
{
    global $db;
    if (!produit_moderation_plateforme_active()) {
        return false;
    }
    $p = get_produit_by_id((int) $produit_id);
    if (!$p || ($p['statut'] ?? '') !== 'bloque') {
        return false;
    }
    $champs = array_filter(array_map('trim', explode(',', (string) ($p['bloque_champs'] ?? ''))));
    if (empty($champs)) {
        return false;
    }
    $ok = true;
    foreach ($champs as $c) {
        if ($c === 'nom') {
            if (trim((string) ($p['nom'] ?? '')) === trim((string) ($p['bloque_nom_ref'] ?? ''))) {
                $ok = false;
            }
        } elseif ($c === 'image') {
            if (trim((string) ($p['image_principale'] ?? '')) === trim((string) ($p['bloque_image_ref'] ?? ''))) {
                $ok = false;
            }
        } else {
            $ok = false;
        }
    }
    if (!$ok) {
        return false;
    }
    $nouveau = ((int) ($p['stock'] ?? 0) > 0) ? 'actif' : 'rupture_stock';
    try {
        $stmt = $db->prepare("
            UPDATE produits SET
                statut = :st,
                bloque_motif = NULL,
                bloque_champs = NULL,
                bloque_nom_ref = NULL,
                bloque_image_ref = NULL,
                bloque_date = NULL,
                date_modification = NOW()
            WHERE id = :id AND statut = 'bloque'
        ");
        return $stmt->execute(['id' => (int) $produit_id, 'st' => $nouveau]);
    } catch (PDOException $e) {
        return false;
    }
}

?>