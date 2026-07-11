<?php
/**
 * Modèle pour la gestion des administrateurs
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Rôles autorisés pour les comptes admin (alignés sur ENUM MySQL après migration B2B)
 */
function admin_roles_valides() {
    return ['admin', 'gestion_stock', 'commercial', 'comptabilite', 'rh', 'caissier', 'vendeur', 'plateforme'];
}

/**
 * Libellé affichage d'un rôle
 */
function admin_role_label($role) {
    $labels = [
        'admin' => 'Administrateur',
        'gestion_stock' => 'Gestion des stocks',
        'utilisateur' => 'Gestion des stocks',
        'commercial' => 'Commercial',
        'comptabilite' => 'Comptabilité',
        'rh' => 'Ressources humaines',
        'caissier' => 'Caissier (caissière)',
        'vendeur' => 'Vendeur (boutique)',
        'plateforme' => 'Plateforme',
    ];
    $r = (string) $role;
    if ($r === 'utilisateur') {
        $r = 'gestion_stock';
    }
    return isset($labels[$r]) ? $labels[$r] : $r;
}

/**
 * Normalise un rôle (legacy utilisateur → gestion_stock)
 */
function normalize_admin_role($role) {
    $r = (string) $role;
    if ($r === 'utilisateur') {
        return 'gestion_stock';
    }
    return in_array($r, admin_roles_valides(), true) ? $r : 'gestion_stock';
}

/**
 * Vérifie si un administrateur existe déjà avec cet email
 * @param string $email L'email à vérifier
 * @return bool True si l'email existe, False sinon
 */
function admin_email_exists($email)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();

        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si au moins un administrateur existe déjà
 * @return bool True si un admin existe, False sinon
 */
function admin_exists()
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Insère un nouvel administrateur dans la base de données
 * @param string $nom Le nom de l'administrateur
 * @param string $prenom Le prénom de l'administrateur
 * @param string $email L'email de l'administrateur
 * @param string $password_hash Le mot de passe hashé
 * @param string $role Voir admin_roles_valides() (défaut: gestion_stock)
 * @return bool|int L'ID de l'admin créé en cas de succès, False en cas d'échec
 */
function create_admin($nom, $prenom, $email, $password_hash, $role = 'gestion_stock')
{
    global $db;

    $role = normalize_admin_role($role);

    try {
        $stmt = $db->prepare("
            INSERT INTO admin (nom, prenom, email, password, date_creation, statut, role) 
            VALUES (:nom, :prenom, :email, :password, NOW(), 'actif', :role)
        ");

        $result = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password_hash,
            'role' => $role
        ]);

        if ($result) {
            return $db->lastInsertId();
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un administrateur par son email
 * @param string $email L'email de l'administrateur
 * @return array|false Les données de l'admin ou False si non trouvé
 */
function get_admin_by_email($email)
{
    global $db;

    if ($email === null || $email === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM admin WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ? $admin : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Indique si une colonne existe dans la table admin.
 */
function admin_has_column($column)
{
    static $cache = [];
    global $db;

    $column = trim((string) $column);
    if ($column === '') {
        return false;
    }
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    if (!isset($db) || !($db instanceof PDO)) {
        $cache[$column] = false;
        return false;
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM admin LIKE " . $db->quote($column));
        $cache[$column] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        return $cache[$column];
    } catch (PDOException $e) {
        $cache[$column] = false;
        return false;
    }
}

/**
 * Récupère un admin/vendeur lié à un UID Firebase.
 */
function get_admin_by_firebase_uid($firebase_uid)
{
    global $db;

    $firebase_uid = trim((string) $firebase_uid);
    if ($firebase_uid === '' || !admin_has_column('firebase_uid')) {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM admin WHERE firebase_uid = :uid LIMIT 1");
        $stmt->execute(['uid' => $firebase_uid]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ? $admin : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Lie un compte admin/vendeur existant à Firebase/Google.
 */
function update_admin_google_identity($admin_id, $firebase_uid, $auth_provider = 'google')
{
    global $db;

    if (!admin_has_column('firebase_uid')) {
        return true;
    }

    $auth_provider = trim((string) $auth_provider);
    if ($auth_provider === '') {
        $auth_provider = 'google';
    }

    $sets = ['firebase_uid = :firebase_uid'];
    $params = [
        'id' => (int) $admin_id,
        'firebase_uid' => trim((string) $firebase_uid),
    ];
    if (admin_has_column('auth_provider')) {
        $sets[] = 'auth_provider = :auth_provider';
        $params['auth_provider'] = $auth_provider;
    }

    try {
        $stmt = $db->prepare('UPDATE admin SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un admin par téléphone (vendeurs / connexion PIN)
 */
function get_admin_by_telephone($telephone)
{
    global $db;

    $digits = preg_replace('/\D/', '', (string) $telephone);
    if ($digits === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM admin
            WHERE telephone IS NOT NULL AND TRIM(telephone) != ''
              AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', ''), '.', '') = :d
            LIMIT 1
        ");
        $stmt->execute(['d' => $digits]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            return $admin;
        }

        $t = preg_replace('/\s+/', '', (string) $telephone);
        if ($t === '' || $t === $digits) {
            return false;
        }
        $stmt = $db->prepare("SELECT * FROM admin WHERE telephone = :t LIMIT 1");
        $stmt->execute(['t' => $t]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ? $admin : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un compte par slug boutique (URL partageable)
 */
function get_admin_by_boutique_slug($slug)
{
    global $db;

    $s = trim((string) $slug, '/');
    if ($s === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM admin WHERE boutique_slug = :s LIMIT 1");
        $stmt->execute(['s' => $s]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ? $admin : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Téléphone déjà utilisé par un admin
 */
function admin_telephone_exists($telephone)
{
    global $db;

    $t = preg_replace('/\s+/', '', (string) $telephone);
    if ($t === '') {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin WHERE telephone = :t");
        $stmt->execute(['t' => $t]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Slug boutique déjà pris (optionnel : exclure un admin pour la mise à jour profil).
 */
function admin_boutique_slug_exists($slug, $exclude_admin_id = 0)
{
    global $db;

    $slug = trim((string) $slug);
    if ($slug === '') {
        return false;
    }

    try {
        $exclude_admin_id = (int) $exclude_admin_id;
        if ($exclude_admin_id > 0) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM admin WHERE boutique_slug = :s AND id != :id');
            $stmt->execute(['s' => $slug, 'id' => $exclude_admin_id]);
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM admin WHERE boutique_slug = :s');
            $stmt->execute(['s' => $slug]);
        }
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Génère un slug unique à partir du nom de boutique.
 */
function admin_generate_unique_boutique_slug($boutique_nom, $exclude_admin_id = 0)
{
    if (!function_exists('marketplace_slugify')) {
        require_once dirname(__DIR__) . '/includes/marketplace_helpers.php';
    }

    $base = marketplace_slugify($boutique_nom);
    $slug = $base;
    $i = 0;
    while (admin_boutique_slug_exists($slug, $exclude_admin_id)) {
        $i++;
        $slug = $base . '-' . $i;
    }
    return $slug;
}

/**
 * Synchronise nom + slug boutique vendeur en session.
 */
function admin_sync_vendeur_boutique_session_from_admin(array $admin)
{
    if (($admin['role'] ?? '') !== 'vendeur') {
        return;
    }
    $_SESSION['admin_boutique_nom'] = trim((string) ($admin['boutique_nom'] ?? ''));
    $_SESSION['admin_boutique_slug'] = trim((string) ($admin['boutique_slug'] ?? ''));
}

function admin_sync_vendeur_boutique_session($admin_id)
{
    $admin = get_admin_by_id((int) $admin_id);
    if ($admin) {
        admin_sync_vendeur_boutique_session_from_admin($admin);
    }
}

/**
 * Indique si la colonne boutique_country existe sur admin
 */
function admin_has_boutique_country_column()
{
    static $exists = null;
    global $db;
    if ($exists !== null) {
        return $exists;
    }
    $exists = false;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_country'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Indique si la colonne boutique_region existe sur admin
 */
function admin_has_boutique_region_column()
{
    static $exists = null;
    global $db;
    if ($exists !== null) {
        return $exists;
    }
    $exists = false;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_region'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Indique si la colonne boutique_type_id existe sur admin
 */
function admin_has_boutique_type_id_column()
{
    static $exists = null;
    global $db;
    if ($exists !== null) {
        return $exists;
    }
    $exists = false;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_type_id'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Met à jour le type de boutique du vendeur.
 */
function update_admin_boutique_type($id, $boutique_type_id)
{
    global $db;
    $id = (int) $id;
    $boutique_type_id = (int) $boutique_type_id;
    if ($id <= 0 || $boutique_type_id <= 0 || !admin_has_boutique_type_id_column()) {
        return false;
    }
    if (!function_exists('boutique_type_is_valid_active')) {
        require_once __DIR__ . '/model_boutique_types.php';
    }
    if (!boutique_type_is_valid_active($boutique_type_id)) {
        return false;
    }
    try {
        $st = $db->prepare('UPDATE admin SET boutique_type_id = :tid WHERE id = :id AND role = \'vendeur\'');
        return $st->execute(['id' => $id, 'tid' => $boutique_type_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un compte vendeur (boutique)
 * @param string $identite Nom affiché (champ unique UI)
 * @param string|null $email
 * @param string $telephone
 * @param string $password_hash hash du PIN / mot de passe
 * @param string $boutique_nom Nom commercial
 * @param string $boutique_slug Slug URL unique
 * @param string|null $boutique_region Code région Sénégal
 * @param string|null $boutique_country Code pays ISO (détecté à l'inscription)
 * @param int|null $boutique_type_id Type de boutique (référence boutique_types)
 * @return bool|int id ou false
 */
function create_vendeur_boutique($identite, $email, $telephone, $password_hash, $boutique_nom, $boutique_slug, $boutique_region = null, $boutique_country = null, $boutique_type_id = null)
{
    global $db;

    $identite = trim((string) $identite);
    $boutique_nom = trim((string) $boutique_nom);
    $boutique_slug = trim((string) $boutique_slug);
    $telephone = preg_replace('/\s+/', '', (string) $telephone);
    $email = $email !== null && trim((string) $email) !== '' ? trim((string) $email) : null;
    $boutique_region = $boutique_region !== null && trim((string) $boutique_region) !== ''
        ? trim((string) $boutique_region)
        : null;
    $boutique_country = strtoupper(trim((string) ($boutique_country ?? 'SN')));
    if ($boutique_country === '') {
        $boutique_country = 'SN';
    }
    if (!function_exists('marketplace_country_is_valid')) {
        require_once __DIR__ . '/../includes/marketplace_countries.php';
    }
    if (!marketplace_country_is_valid($boutique_country)) {
        $boutique_country = 'SN';
    }

    try {
        $cols = 'nom, prenom, email, password, date_creation, statut, role, boutique_slug, boutique_nom, telephone';
        $vals = ':nom, \'\', :email, :password, NOW(), \'actif\', \'vendeur\', :boutique_slug, :boutique_nom, :telephone';
        $params = [
            'nom' => $identite,
            'email' => $email,
            'password' => $password_hash,
            'boutique_slug' => $boutique_slug,
            'boutique_nom' => $boutique_nom,
            'telephone' => $telephone,
        ];
        if (admin_has_boutique_country_column()) {
            $cols .= ', boutique_country';
            $vals .= ', :boutique_country';
            $params['boutique_country'] = $boutique_country;
        }
        if (admin_has_boutique_region_column()) {
            $cols .= ', boutique_region';
            $vals .= ', :boutique_region';
            $params['boutique_region'] = $boutique_region;
        }
        if (admin_has_boutique_type_id_column() && $boutique_type_id !== null && (int) $boutique_type_id > 0) {
            $cols .= ', boutique_type_id';
            $vals .= ', :boutique_type_id';
            $params['boutique_type_id'] = (int) $boutique_type_id;
        }
        $stmt = $db->prepare("INSERT INTO admin ($cols) VALUES ($vals)");
        $ok = $stmt->execute($params);
        if ($ok) {
            return (int) $db->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un vendeur depuis Google, puis le lie à Firebase.
 */
function create_google_vendeur_boutique($identite, $email, $telephone, $boutique_nom, $boutique_slug, $boutique_region, $firebase_uid, $auth_provider = 'google', $boutique_country = null, $boutique_type_id = null)
{
    $password_hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
    $admin_id = create_vendeur_boutique($identite, $email, $telephone, $password_hash, $boutique_nom, $boutique_slug, $boutique_region, $boutique_country, $boutique_type_id);
    if ($admin_id) {
        update_admin_google_identity($admin_id, $firebase_uid, $auth_provider);
    }
    return $admin_id;
}

/**
 * Récupère un administrateur par son ID
 * @param int $id L'ID de l'administrateur
 * @return array|false Les données de l'admin ou False si non trouvé
 */
function get_admin_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM admin WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ? $admin : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour les informations d'un administrateur
 * @param int $id L'ID de l'administrateur
 * @param array $data Les nouvelles données
 * @return bool True en cas de succès, False sinon
 */
function update_admin($id, $data)
{
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE admin SET
                nom = :nom,
                prenom = :prenom,
                email = :email,
                telephone = :telephone
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour la dernière connexion d'un administrateur
 * @param int $admin_id L'ID de l'administrateur
 * @return bool True en cas de succès, False sinon
 */
function update_admin_last_login($admin_id)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE admin SET derniere_connexion = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $admin_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le nom et la région de la boutique vendeur (profil / gestion boutique).
 *
 * @param int $id ID admin vendeur
 * @param string $boutique_nom Nom commercial
 * @param string $boutique_region Code région Sénégal
 * @return bool
 */
function update_vendeur_boutique_profil($id, $boutique_nom, $boutique_region)
{
    global $db;

    $id = (int) $id;
    $boutique_nom = trim((string) $boutique_nom);
    $boutique_region = trim((string) $boutique_region);

    if ($id <= 0 || $boutique_nom === '') {
        return false;
    }

    $current = get_admin_by_id($id);
    if (!$current || ($current['role'] ?? '') !== 'vendeur') {
        return false;
    }

    $new_slug = admin_generate_unique_boutique_slug($boutique_nom, $id);

    try {
        if (admin_has_boutique_region_column()) {
            $stmt = $db->prepare('
                UPDATE admin SET
                    boutique_nom = :boutique_nom,
                    boutique_slug = :boutique_slug,
                    boutique_region = :boutique_region
                WHERE id = :id AND role = \'vendeur\'
            ');
            $ok = $stmt->execute([
                'id' => $id,
                'boutique_nom' => $boutique_nom,
                'boutique_slug' => $new_slug,
                'boutique_region' => $boutique_region !== '' ? $boutique_region : null,
            ]);
        } else {
            $stmt = $db->prepare('
                UPDATE admin SET
                    boutique_nom = :boutique_nom,
                    boutique_slug = :boutique_slug
                WHERE id = :id AND role = \'vendeur\'
            ');
            $ok = $stmt->execute([
                'id' => $id,
                'boutique_nom' => $boutique_nom,
                'boutique_slug' => $new_slug,
            ]);
        }

        if ($ok) {
            $old_slug = trim((string) ($current['boutique_slug'] ?? ''));
            if ($old_slug !== '' && $old_slug !== $new_slug) {
                if (!function_exists('boutique_slug_redirect_save')) {
                    require_once dirname(__DIR__) . '/includes/boutique_slug_redirect.php';
                }
                boutique_slug_redirect_save($old_slug, $id);
            }
            $updated = get_admin_by_id($id);
            if ($updated) {
                admin_sync_vendeur_boutique_session_from_admin($updated);
            }
        }

        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Mise à jour branding vitrine vendeur (logo, couleurs, adresse affichée).
 * Colonnes attendues : boutique_logo, boutique_couleur_principale, boutique_couleur_accent, boutique_adresse
 *
 * @param int $id ID admin vendeur
 * @param array $data Clés : boutique_logo (?string), boutique_couleur_principale, boutique_couleur_accent, boutique_adresse
 * @return bool
 */
function update_admin_boutique_branding($id, array $data)
{
    global $db;

    if ((int) $id <= 0) {
        return false;
    }

    try {
        $sets = [];
        $params = ['id' => (int) $id];

        if (array_key_exists('boutique_logo', $data)) {
            $sets[] = 'boutique_logo = :boutique_logo';
            $params['boutique_logo'] = $data['boutique_logo'] !== null && $data['boutique_logo'] !== ''
                ? (string) $data['boutique_logo']
                : null;
        }
        if (array_key_exists('boutique_couleur_principale', $data)) {
            $sets[] = 'boutique_couleur_principale = :boutique_couleur_principale';
            $params['boutique_couleur_principale'] = $data['boutique_couleur_principale'] !== null && $data['boutique_couleur_principale'] !== ''
                ? (string) $data['boutique_couleur_principale']
                : null;
        }
        if (array_key_exists('boutique_couleur_accent', $data)) {
            $sets[] = 'boutique_couleur_accent = :boutique_couleur_accent';
            $params['boutique_couleur_accent'] = $data['boutique_couleur_accent'] !== null && $data['boutique_couleur_accent'] !== ''
                ? (string) $data['boutique_couleur_accent']
                : null;
        }
        if (array_key_exists('boutique_adresse', $data)) {
            $sets[] = 'boutique_adresse = :boutique_adresse';
            $params['boutique_adresse'] = $data['boutique_adresse'] !== null && trim((string) $data['boutique_adresse']) !== ''
                ? trim((string) $data['boutique_adresse'])
                : null;
        }
        if (admin_has_boutique_country_column() && array_key_exists('boutique_country', $data)) {
            $sets[] = 'boutique_country = :boutique_country';
            $country = $data['boutique_country'] !== null ? strtoupper(trim((string) $data['boutique_country'])) : '';
            if (!function_exists('marketplace_country_is_valid')) {
                require_once __DIR__ . '/../includes/marketplace_countries.php';
            }
            $params['boutique_country'] = ($country !== '' && marketplace_country_is_valid($country))
                ? $country
                : 'SN';
        }
        if (admin_has_boutique_region_column() && array_key_exists('boutique_region', $data)) {
            $sets[] = 'boutique_region = :boutique_region';
            $region = $data['boutique_region'];
            $params['boutique_region'] = $region !== null && trim((string) $region) !== ''
                ? trim((string) $region)
                : null;
        }

        if ($sets === []) {
            return false;
        }

        $stmt = $db->prepare('
            UPDATE admin SET ' . implode(', ', $sets) . "
            WHERE id = :id AND role = 'vendeur'
        ");

        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le mot de passe d'un administrateur
 * @param int $admin_id L'ID de l'administrateur
 * @param string $password_hash Le nouveau mot de passe hashé
 * @return bool True en cas de succès, False sinon
 */
function update_admin_password($admin_id, $password_hash)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE admin SET password = :password WHERE id = :id");
        return $stmt->execute(['id' => $admin_id, 'password' => $password_hash]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un token de réinitialisation de mot de passe
 * @param string $email L'email de l'admin
 * @param string $token Le token généré
 * @param string $expires_at Date d'expiration (format DATETIME)
 * @return bool True en cas de succès, False sinon
 */
function create_password_reset_token($email, $token, $expires_at)
{
    global $db;

    try {
        // Supprimer les anciens tokens pour cet email
        $stmt = $db->prepare("DELETE FROM admin_password_reset WHERE email = :email");
        $stmt->execute(['email' => $email]);

        $stmt = $db->prepare("
            INSERT INTO admin_password_reset (email, token, expires_at) 
            VALUES (:email, :token, :expires_at)
        ");
        return $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un token de réinitialisation valide
 * @param string $token Le token à vérifier
 * @return array|false Les données du token ou False si invalide/expiré
 */
function get_valid_reset_token($token)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT * FROM admin_password_reset 
            WHERE token = :token AND used = 0 AND expires_at > NOW()
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Marque un token comme utilisé
 * @param string $token Le token à marquer
 * @return bool True en cas de succès, False sinon
 */
function mark_reset_token_used($token)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE admin_password_reset SET used = 1 WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les emails de tous les administrateurs actifs
 * @return array Liste des emails
 */
function get_all_admin_emails()
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT email FROM admin WHERE statut = 'actif' AND email IS NOT NULL AND email != ''");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère tous les comptes administrateurs
 * @return array Liste des admins
 */
function get_all_admins()
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, date_creation, derniere_connexion, statut, 
                   COALESCE(role, 'admin') as role 
            FROM admin 
            ORDER BY date_creation DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Met à jour le rôle d'un administrateur
 * @param int $id ID de l'admin
 * @param string $role Voir admin_roles_valides()
 * @return bool
 */
function update_admin_role($id, $role)
{
    global $db;

    if (!in_array($role, admin_roles_valides(), true)) {
        return false;
    }

    try {
        $stmt = $db->prepare("UPDATE admin SET role = :role WHERE id = :id");
        return $stmt->execute(['id' => $id, 'role' => $role]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'un administrateur
 * @param int $id ID de l'admin
 * @param string $statut 'actif' ou 'inactif'
 * @return bool
 */
function update_admin_statut($id, $statut)
{
    global $db;

    if (!in_array($statut, ['actif', 'inactif'])) {
        return false;
    }

    try {
        $stmt = $db->prepare("UPDATE admin SET statut = :statut WHERE id = :id");
        return $stmt->execute(['id' => $id, 'statut' => $statut]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vendeurs avec vitrine publique (pour sitemap SEO).
 *
 * @return array<int, array{id:int, slug:string, boutique_nom:string, date_creation:?string, logo_rel:?string}>
 */
function get_actifs_vendeurs_pour_sitemap()
{
    global $db;

    try {
        $has_logo = false;
        try {
            $chk = $db->query('SHOW COLUMNS FROM `admin` LIKE ' . $db->quote('boutique_logo'));
            $has_logo = $chk && $chk->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $has_logo = false;
        }

        $stmt = $db->prepare('
            SELECT id, boutique_slug AS slug, boutique_nom, date_creation ' . ($has_logo ? ', boutique_logo' : '') . "
            FROM admin
            WHERE statut = 'actif'
              AND boutique_slug IS NOT NULL
              AND TRIM(boutique_slug) <> ''
              AND COALESCE(TRIM(role), '') = 'vendeur'
            ORDER BY boutique_slug ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $slug = trim((string) ($r['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $item = [
                'id' => (int) $r['id'],
                'slug' => $slug,
                'boutique_nom' => isset($r['boutique_nom']) ? (string) $r['boutique_nom'] : '',
                'date_creation' => isset($r['date_creation']) ? $r['date_creation'] : null,
                'logo_rel' => null,
            ];
            if ($has_logo && !empty($r['boutique_logo'])) {
                $rel = trim((string) $r['boutique_logo']);
                $rel = ltrim($rel, '/');
                if ($rel !== '' && strpos($rel, '..') === false) {
                    $item['logo_rel'] = $rel;
                }
            }
            $out[] = $item;
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

?>