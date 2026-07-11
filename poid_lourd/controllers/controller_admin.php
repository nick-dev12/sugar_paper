<?php
/**
 * Contrôleur pour la gestion des administrateurs
 * Programmation procédurale uniquement
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/../models/model_admin.php';
if (file_exists(__DIR__ . '/../models/model_vendeur_comptes_acces.php')) {
    require_once __DIR__ . '/../models/model_vendeur_comptes_acces.php';
}
require_once __DIR__ . '/../includes/site_url.php';

/**
 * Traite l'inscription d'un nouvel administrateur
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_admin_inscription() {
    $errors = [];
    $success = false;
    $message = '';
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Validation du nom
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $nom)) {
        $errors[] = 'Le nom contient des caractères invalides.';
    }
    
    // Validation du prénom
    if (empty($prenom)) {
        $errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $prenom)) {
        $errors[] = 'Le prénom contient des caractères invalides.';
    }
    
    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } elseif (admin_email_exists($email)) {
        $errors[] = 'Cet email est déjà utilisé.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    
    // Validation de la confirmation du mot de passe
    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    // Rôle (voir model_admin admin_roles_valides())
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'gestion_stock';
    if (!in_array($role, admin_roles_valides(), true)) {
        $role = 'gestion_stock';
    }

    // Si un admin est connecté, il doit avoir le rôle admin pour ajouter des comptes
    $admin_connecte = isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
    if ($admin_connecte && ($_SESSION['admin_role'] ?? '') !== 'admin') {
        $errors[] = 'Vous n\'avez pas les droits pour ajouter des comptes.';
    }

    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        // Hashage du mot de passe
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Premier compte = toujours administrateur ; sinon rôle choisi (comptes, commercial, compta, RH…)
        $role_final = $role;
        if (!admin_exists()) {
            $role_final = 'admin';
        }

        // Création de l'administrateur
        $admin_id = create_admin($nom, $prenom, $email, $password_hash, $role_final);

        if ($admin_id) {
            $success = true;
            if ($admin_connecte) {
                $message = 'Compte ajouté avec succès !';
            } else {
                $message = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
            }
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la connexion d'un administrateur
 * @return array Tableau avec 'success' (bool), 'message' (string) et 'admin' (array|false)
 */
/**
 * Connexion vendeur : téléphone + PIN 6 chiffres (hashé en base comme un mot de passe).
 */
function process_vendeur_pin_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'admin' => false, 'vendeur_collaborateur' => null];
    }

    $tel = isset($_POST['telephone']) ? trim((string) $_POST['telephone']) : '';
    $pin = isset($_POST['pin']) ? (string) $_POST['pin'] : '';
    $tel_norm = preg_replace('/\s+/', '', $tel);
    $errors = [];

    if ($tel_norm === '') {
        $errors[] = 'Le téléphone est obligatoire.';
    }
    if (strlen($pin) < 4) {
        $errors[] = 'Le code doit comporter au moins 4 caractères.';
    }
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors), 'admin' => false, 'vendeur_collaborateur' => null];
    }

    $admin = get_admin_by_telephone($tel_norm);
    if ($admin) {
        if (($admin['statut'] ?? '') !== 'actif') {
            return ['success' => false, 'message' => 'Votre compte est désactivé. Contactez la plateforme.', 'admin' => false, 'vendeur_collaborateur' => null];
        }
        if (!password_verify($pin, $admin['password'])) {
            return ['success' => false, 'message' => 'Téléphone ou code incorrect.', 'admin' => false, 'vendeur_collaborateur' => null];
        }
        update_admin_last_login($admin['id']);
        return ['success' => true, 'message' => 'Connexion réussie !', 'admin' => $admin, 'vendeur_collaborateur' => null];
    }

    if (!function_exists('get_vendeur_compte_acces_by_telephone')) {
        return ['success' => false, 'message' => 'Téléphone ou code incorrect.', 'admin' => false, 'vendeur_collaborateur' => null];
    }

    $collab = get_vendeur_compte_acces_by_telephone($tel_norm);
    if (!$collab) {
        return ['success' => false, 'message' => 'Téléphone ou code incorrect.', 'admin' => false, 'vendeur_collaborateur' => null];
    }
    if (($collab['statut'] ?? '') !== 'actif') {
        return ['success' => false, 'message' => 'Ce compte d’accès est désactivé. Contactez le gérant de la boutique.', 'admin' => false, 'vendeur_collaborateur' => null];
    }
    if (!password_verify($pin, $collab['password'])) {
        return ['success' => false, 'message' => 'Téléphone ou code incorrect.', 'admin' => false, 'vendeur_collaborateur' => null];
    }

    $owner = get_admin_by_id((int) ($collab['vendeur_admin_id'] ?? 0));
    if (!$owner || ($owner['statut'] ?? '') !== 'actif' || ($owner['role'] ?? '') !== 'vendeur') {
        return ['success' => false, 'message' => 'Boutique indisponible. Contactez la plateforme.', 'admin' => false, 'vendeur_collaborateur' => null];
    }

    update_vendeur_compte_acces_last_login((int) $collab['id']);
    update_admin_last_login($owner['id']);

    return [
        'success' => true,
        'message' => 'Connexion réussie !',
        'admin' => $owner,
        'vendeur_collaborateur' => $collab,
    ];
}

function process_admin_login() {
    $errors = [];
    $success = false;
    $message = '';
    $admin = false;

    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'admin' => false];
    }

    if (isset($_POST['vendeur_login']) && (string) $_POST['vendeur_login'] === '1') {
        return process_vendeur_pin_login();
    }

    // Récupération des données
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    }
    
    // Si aucune erreur de validation, vérifier les identifiants
    if (empty($errors)) {
        // Récupérer l'administrateur par email
        $admin = get_admin_by_email($email);
        
        if ($admin) {
            // Vérifier le statut
            if ($admin['statut'] !== 'actif') {
                $errors[] = 'Votre compte est désactivé. Contactez l\'administrateur.';
            } elseif (password_verify($password, $admin['password'])) {
                // Mot de passe correct
                $success = true;
                $message = 'Connexion réussie !';
                
                // Mettre à jour la dernière connexion
                update_admin_last_login($admin['id']);
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        } else {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message, 'admin' => $admin];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message, 'admin' => false];
    }
}

/**
 * Traite la demande de réinitialisation de mot de passe (mot de passe oublié)
 * @return array Tableau avec 'success', 'message', 'email', 'reset_link', 'token' (pour EmailJS)
 */
function process_forgot_password() {
    $errors = [];
    $success = false;
    $message = '';
    $email = '';
    $reset_link = '';
    $token = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } elseif (!admin_email_exists($email)) {
        // Pour des raisons de sécurité, on affiche le même message que si l'email existait
        $success = true;
        $message = 'Si cet email est associé à un compte admin, vous recevrez un lien de réinitialisation.';
        return ['success' => $success, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

        if (create_password_reset_token($email, $token, $expires_at)) {
            $base_url = get_site_base_url();
            $reset_link = rtrim($base_url, '/') . '/admin/reinitialiser-mot-de-passe.php?token=' . $token;

            if (function_exists('mail_send_reset_link')) {
                $mail_result = mail_send_reset_link($email, $reset_link, 'admin');
                if (!$mail_result['success']) {
                    $message = 'Le lien a été généré mais l\'envoi de l\'email a échoué : ' . ($mail_result['error'] ?? 'Erreur inconnue');
                    return ['success' => false, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
                }
            }

            $success = true;
            $message = 'Si cet email est associé à un compte admin, vous recevrez un lien de réinitialisation.';
        } else {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return [
        'success' => $success,
        'message' => $message,
        'email' => $email,
        'reset_link' => $reset_link,
        'token' => $token
    ];
}

/**
 * Traite la réinitialisation du mot de passe (nouveau mot de passe)
 * @return array Tableau avec 'success', 'message'
 */
function process_reset_password() {
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if (empty($token)) {
        $errors[] = 'Token invalide ou manquant.';
    } else {
        $token_data = get_valid_reset_token($token);
        if (!$token_data) {
            $errors[] = 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez faire une nouvelle demande.';
        }
    }

    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }

    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (empty($errors) && isset($token_data)) {
        $admin = get_admin_by_email($token_data['email']);
        if ($admin) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            if (update_admin_password($admin['id'], $password_hash) && mark_reset_token_used($token)) {
                $success = true;
                $message = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.';
            } else {
                $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
            }
        } else {
            $errors[] = 'Compte administrateur introuvable.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return ['success' => $success, 'message' => $message];
}

/**
 * Inscription vendeur / création de boutique (téléphone + PIN + slug).
 */
function process_inscription_vendeur() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    require_once __DIR__ . '/../includes/marketplace_helpers.php';
    require_once __DIR__ . '/../includes/marketplace_countries.php';
    require_once __DIR__ . '/../includes/geo_regions.php';
    require_once __DIR__ . '/../includes/boutique_types.php';

    $identite = isset($_POST['identite']) ? trim((string) $_POST['identite']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    $telephone = preg_replace('/\s+/', '', trim((string) ($_POST['telephone'] ?? '')));
    $pin = (string) ($_POST['pin'] ?? '');
    $pin2 = (string) ($_POST['pin_confirm'] ?? '');
    $boutique_nom = isset($_POST['boutique_nom']) ? trim((string) $_POST['boutique_nom']) : '';
    $boutique_country = isset($_POST['boutique_country']) ? strtoupper(trim((string) $_POST['boutique_country'])) : '';
    $boutique_region = isset($_POST['boutique_region']) ? trim((string) $_POST['boutique_region']) : '';
    $boutique_type_id_raw = isset($_POST['boutique_type_id']) ? (int) $_POST['boutique_type_id'] : 0;

    $errors = [];
    if (mb_strlen($identite) < 2) {
        $errors[] = 'L\'identité est obligatoire.';
    }
    if ($telephone === '') {
        $errors[] = 'Le téléphone est obligatoire.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    if (!preg_match('/^\d{6}$/', $pin)) {
        $errors[] = 'Le code PIN doit comporter exactement 6 chiffres.';
    }
    if ($pin !== $pin2) {
        $errors[] = 'Les deux saisies du PIN ne correspondent pas.';
    }
    if (mb_strlen($boutique_nom) < 2) {
        $errors[] = 'Le nom de la boutique est obligatoire.';
    }
    if ($boutique_country === '' || !marketplace_country_is_valid($boutique_country)) {
        $errors[] = 'Veuillez sélectionner le pays de votre boutique.';
    }
    if ($boutique_country !== '' && ($boutique_region === '' || !geo_region_is_valid($boutique_country, $boutique_region))) {
        $errors[] = 'Veuillez sélectionner la région de votre boutique.';
    }
    $type_check = boutique_type_validate_inscription($boutique_type_id_raw);
    if (!$type_check['ok']) {
        $errors[] = $type_check['message'];
    }
    $boutique_type_id = $type_check['id'];
    if (admin_telephone_exists($telephone)) {
        $errors[] = 'Ce numéro de téléphone est déjà enregistré.';
    }
    if ($email !== '' && admin_email_exists($email)) {
        $errors[] = 'Cet email est déjà utilisé.';
    }

    $slug = marketplace_slugify($boutique_nom);
    $base_slug = $slug;
    $n = 0;
    while (admin_boutique_slug_exists($slug)) {
        $n++;
        $slug = $base_slug . '-' . $n;
        if ($n > 200) {
            $errors[] = 'Impossible de générer une URL boutique unique. Modifiez le nom.';
            break;
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $hash = password_hash($pin, PASSWORD_BCRYPT);
    $id = create_vendeur_boutique($identite, $email !== '' ? $email : null, $telephone, $hash, $boutique_nom, $slug, $boutique_region, $boutique_country, $boutique_type_id);
    if (!$id) {
        return ['success' => false, 'message' => 'Erreur lors de la création du compte. Réessayez.'];
    }

    require_once __DIR__ . '/../includes/geo_location_service.php';
    require_once __DIR__ . '/../includes/geo_geocoder.php';
    try {
        $boutique_adresse = geo_address_concise_normalize((string) ($_POST['boutique_adresse'] ?? ''));
        $geo_lat = geo_parse_coord($_POST['insc_geo_lat'] ?? null);
        $geo_lng = geo_parse_coord($_POST['insc_geo_lng'] ?? null);

        if ($boutique_adresse !== '') {
            $geocoded = geo_geocode_address($boutique_adresse, $boutique_country);
            if ($geocoded !== null) {
                geo_save_boutique_position_bundle((int) $id, $geocoded['lat'], $geocoded['lng'], 'adresse', $boutique_adresse);
            } elseif (geo_coords_valid($geo_lat, $geo_lng)) {
                geo_save_boutique_position_bundle((int) $id, $geo_lat, $geo_lng, 'gps', $boutique_adresse);
            }
        } elseif (geo_coords_valid($geo_lat, $geo_lng)) {
            geo_save_boutique_position_bundle((int) $id, $geo_lat, $geo_lng, 'gps', null);
        }
    } catch (Throwable $e) {
        error_log('[inscription-vendeur] geo: ' . $e->getMessage());
    }

    return [
        'success' => true,
        'message' => 'Votre boutique a été créée. Connectez-vous avec votre téléphone et votre code PIN.',
        'boutique_slug' => $slug,
    ];
}

?>

