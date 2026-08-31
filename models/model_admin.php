<?php
/**
 * Modèle pour la gestion des administrateurs
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

/**
 * Rôles autorisés pour les comptes admin
 */
function admin_roles_valides() {
    return ['admin', 'utilisateur', 'livreur', 'contable'];
}

/**
 * Libellé affichage d'un rôle
 */
function admin_role_label($role) {
    $labels = [
        'admin' => 'Administrateur',
        'utilisateur' => 'Utilisateur',
        'livreur' => 'Livreur',
        'contable' => 'Comptable',
        // Anciens rôles (affichage après migration)
        'gestion_stock' => 'Utilisateur',
        'commercial' => 'Utilisateur',
        'commercial_general' => 'Utilisateur',
        'informaticien' => 'Utilisateur',
        'developpeur' => 'Utilisateur',
        'comptabilite' => 'Utilisateur',
        'rh' => 'Utilisateur',
        'caissier' => 'Utilisateur',
    ];
    $r = normalize_admin_role($role);
    return isset($labels[$r]) ? $labels[$r] : $r;
}

/**
 * Normalise un rôle (legacy gestion_stock → utilisateur)
 */
function normalize_admin_role($role) {
    $r = (string) $role;
    if ($r === 'gestion_stock') {
        return 'utilisateur';
    }
    if ($r === 'comptable') {
        return 'contable';
    }
    if ($r === 'contable') {
        return 'contable';
    }
    if (in_array($r, ['commercial', 'commercial_general', 'informaticien', 'developpeur', 'comptabilite', 'rh', 'caissier'], true)) {
        return 'utilisateur';
    }
    return in_array($r, admin_roles_valides(), true) ? $r : 'utilisateur';
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
 * @param string $role Voir admin_roles_valides() (défaut: utilisateur)
 * @return bool|int L'ID de l'admin créé en cas de succès, False en cas d'échec
 */
function create_admin($nom, $prenom, $email, $password_hash, $role = 'utilisateur')
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
 * Récupère un admin lié à un UID Firebase.
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
 * Lie un compte admin existant à Firebase/Google.
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
 * Récupère un administrateur par son email
 * @param string $email L'email de l'administrateur
 * @return array|false Les données de l'admin ou False si non trouvé
 */
function get_admin_by_email($email)
{
    global $db;

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
                email = :email
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email']
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
 * Destinataires des alertes stock : administrateurs + gestion des stocks + commerciaux (comptes actifs).
 *
 * @return list<string>
 */
function get_admin_emails_alerte_stock()
{
    global $db;

    try {
        $stmt = $db->prepare(
            "SELECT DISTINCT email FROM admin WHERE statut = 'actif' AND email IS NOT NULL
             AND TRIM(email) != '' AND COALESCE(role, 'admin') IN ('admin','gestion_stock','commercial','commercial_general','informaticien','developpeur')"
        );
        $stmt->execute();
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $cols ? array_values(array_filter(array_map('trim', $cols))) : [];
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
        $cols = 'id, nom, prenom, email, date_creation, derniere_connexion, statut, COALESCE(role, \'admin\') as role';
        if (admin_has_column('photo_profil')) {
            $cols .= ', photo_profil';
        }
        $stmt = $db->prepare("
            SELECT {$cols}
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
 * URL publique de la photo de profil admin (ou chaîne vide).
 */
function admin_photo_profil_url($photo_profil)
{
    $photo_profil = trim((string) $photo_profil);
    if ($photo_profil === '') {
        return '';
    }
    $photo_profil = ltrim(str_replace('\\', '/', $photo_profil), '/');
    if (strpos($photo_profil, '..') !== false) {
        return '';
    }
    $abs = dirname(__DIR__) . '/upload/' . $photo_profil;
    if (!is_file($abs)) {
        return '';
    }
    return '/upload/' . $photo_profil;
}

/**
 * Met à jour le chemin photo_profil d'un compte admin.
 */
function update_admin_photo_profil($admin_id, $chemin)
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id < 1 || !admin_has_column('photo_profil')) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE admin SET photo_profil = :photo WHERE id = :id');
        return $stmt->execute([
            'photo' => $chemin === null || $chemin === '' ? null : (string) $chemin,
            'id' => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Traite l'upload d'une photo de profil pour un compte admin.
 *
 * @return array{ok:bool, msg:string, chemin?:string}
 */
function admin_photo_profil_process_upload($admin_id, $file)
{
    $admin_id = (int) $admin_id;
    if ($admin_id < 1) {
        return ['ok' => false, 'msg' => 'Identifiant compte invalide.'];
    }
    if (!admin_has_column('photo_profil')) {
        return ['ok' => false, 'msg' => 'Colonne photo non installée. Exécutez la migration admin_photo_profil.'];
    }
    if (!$file || !is_array($file)) {
        return ['ok' => false, 'msg' => 'Aucun fichier reçu.'];
    }

    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'msg' => 'Choisissez une photo à téléverser.'];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'msg' => 'La photo est trop volumineuse.'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le téléversement a échoué. Réessayez.'];
    }

    $max_bytes = 5 * 1024 * 1024;
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > $max_bytes) {
        return ['ok' => false, 'msg' => 'La photo doit faire au plus 5 Mo.'];
    }

    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Fichier téléversé invalide.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($tmp);
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        return ['ok' => false, 'msg' => 'Format non autorisé (JPG, PNG, WEBP ou GIF).'];
    }

    $upload_dir = dirname(__DIR__) . '/upload/admin_photos/';
    if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['ok' => false, 'msg' => 'Impossible de préparer le dossier des photos.'];
    }

    $result = upload_optimize_image_file($file, $upload_dir, 'admin_photos', 'admin_' . $admin_id . '_');
    if (empty($result['success']) || empty($result['relative_path'])) {
        return ['ok' => false, 'msg' => (string) ($result['message'] ?? 'Impossible d’enregistrer la photo sur le serveur.')];
    }

    $relatif = (string) $result['relative_path'];
    $new_base = basename($relatif);

    foreach (glob($upload_dir . 'admin_' . $admin_id . '_*') ?: [] as $old_abs) {
        if (!is_string($old_abs) || !is_file($old_abs)) {
            continue;
        }
        if (basename($old_abs) === $new_base) {
            continue;
        }
        @unlink($old_abs);
    }

    if (!update_admin_photo_profil($admin_id, $relatif)) {
        image_optimizer_delete_with_variants($relatif);
        return ['ok' => false, 'msg' => 'Erreur d’enregistrement en base de données.'];
    }

    return ['ok' => true, 'msg' => 'Photo de profil mise à jour.', 'chemin' => $relatif];
}

/**
 * Comptes admin filtrés par rôle.
 */
function get_admins_by_role($role) {
    global $db;
    $role = normalize_admin_role($role);
    try {
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, date_creation, derniere_connexion, statut, role
            FROM admin
            WHERE role = :role
            ORDER BY nom ASC, prenom ASC
        ");
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Comptes admin éligibles pour la saisie d’absences : actifs, rôle différent de « admin ».
 * (Les comptes au rôle administrateur technique ne sont pas listés comme « absents ».)
 *
 * @return array<int,array<string,mixed>>
 */
function get_admins_eligibles_absences()
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, statut, COALESCE(role, 'admin') AS role
            FROM admin
            WHERE statut = 'actif'
              AND (role IS NULL OR role != 'admin')
            ORDER BY nom ASC, prenom ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
 * Nombre de ventes caisse où ce compte est le vendeur (admin_id) — blocage si > 0 (FK RESTRICT).
 */
function admin_count_caisse_ventes_as_vendeur($admin_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return 0;
    }
    try {
        $st = $db->query("SHOW TABLES LIKE 'caisse_ventes'");
        if (!$st || $st->rowCount() === 0) {
            return 0;
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM caisse_ventes WHERE admin_id = :id');
        $stmt->execute(['id' => $admin_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Supprime définitivement un compte admin (sécurité : compte inactif uniquement, pas le dernier, pas si ventes caisse vendeur).
 *
 * @return array{ok:bool, error?:string}
 */
function delete_admin_account($admin_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Identifiant invalide.'];
    }

    try {
        $stmt = $db->query('SELECT COUNT(*) FROM admin');
        $total = (int) $stmt->fetchColumn();
        if ($total <= 1) {
            return ['ok' => false, 'error' => 'Impossible de supprimer le dernier compte d’accès.'];
        }

        $chk = $db->prepare('SELECT id, statut FROM admin WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $admin_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Compte introuvable.'];
        }
        if (($row['statut'] ?? '') !== 'inactif') {
            return ['ok' => false, 'error' => 'Désactivez d’abord ce compte, puis seulement vous pourrez le supprimer définitivement.'];
        }

        if (admin_count_caisse_ventes_as_vendeur($admin_id) > 0) {
            return ['ok' => false, 'error' => 'Impossible de supprimer ce compte : il est auteur de tickets / ventes caisse.'];
        }

        $del = $db->prepare('DELETE FROM admin WHERE id = :id');
        $del->execute(['id' => $admin_id]);
        if ($del->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'Suppression impossible.'];
        }
        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('[delete_admin_account] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Suppression impossible (données liées ou erreur technique).'];
    }
}
