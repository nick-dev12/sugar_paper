<?php
/**
 * Contrôleur pour la gestion des utilisateurs
 * Programmation procédurale uniquement
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/../models/model_users.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/login_rate_limit.php';
if (file_exists(__DIR__ . '/../models/model_vendeur_comptes_acces.php')) {
    require_once __DIR__ . '/../models/model_vendeur_comptes_acces.php';
}

/**
 * Connexion unifiée : vérifie admin puis users.
 * Permet aux admins de se connecter depuis la page user/connexion.php.
 * @return array ['success' => bool, 'message' => string, 'type' => 'admin'|'user'|null, 'admin' => array|null, 'user' => array|null, 'vendeur_collaborateur' => array|null]
 */
function process_unified_login() {
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
    }

    $login_identifier = login_attempt_extract_identifier_from_post();
    if ($login_identifier !== '') {
        login_attempt_bind_identifier($login_identifier);
        if (login_attempt_is_locked()) {
            return login_attempt_locked_result_array();
        }
    }

    $login_mode = isset($_POST['login_mode']) ? trim((string) $_POST['login_mode']) : 'email';
    if ($login_mode === 'phone') {
        return process_unified_phone_login();
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $accepte_conditions = isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1';

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors), 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
    }

    // 1. Vérifier d'abord la table admin
    $admin = get_admin_by_email($email);
    if ($admin && $admin['statut'] === 'actif' && password_verify($password, $admin['password'])) {
        login_attempt_clear();
        update_admin_last_login($admin['id']);
        return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'admin', 'admin' => $admin, 'user' => null, 'vendeur_collaborateur' => null];
    }

    // 2. Sinon vérifier la table users
    $user = get_user_by_email($email);
    if ($user) {
        if ($user['statut'] !== 'actif') {
            return login_failure_result_array('Email ou mot de passe incorrect.');
        }
        if (!$accepte_conditions) {
            return [
                'success' => false,
                'message' => 'Vous devez accepter les conditions d\'utilisation pour vous connecter.',
                'type' => null,
                'admin' => null,
                'user' => null,
                'vendeur_collaborateur' => null,
            ];
        }
        if (password_verify($password, $user['password'])) {
            login_attempt_clear();
            update_user_accepte_conditions($user['id'], true);
            return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'user', 'admin' => null, 'user' => $user, 'vendeur_collaborateur' => null];
        }
        return login_failure_result_array('Email ou mot de passe incorrect.');
    }

    return login_failure_result_array('Email ou mot de passe incorrect.');
}

/**
 * Connexion unifiée par téléphone + code (même secret hashé que le mot de passe en base).
 * Vérifie d’abord admin, puis compte d’accès collaborateur vendeur, puis compte client (CGU requises pour le client).
 * @return array
 */
function process_unified_phone_login() {
    $tel = isset($_POST['telephone']) ? trim((string) $_POST['telephone']) : '';
    $secret = isset($_POST['pin']) ? (string) $_POST['pin'] : '';
    $accepte_conditions = isset($_POST['accepte_conditions_phone']) && $_POST['accepte_conditions_phone'] === '1';

    $digits = preg_replace('/\D/', '', $tel);
    if ($digits === '') {
        return ['success' => false, 'message' => 'Le numéro de téléphone est obligatoire.', 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
    }
    if ($secret === '') {
        return ['success' => false, 'message' => 'Le code PIN ou mot de passe est obligatoire.', 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
    }

    $admin = get_admin_by_telephone($tel);
    if ($admin && ($admin['statut'] ?? '') === 'actif' && password_verify($secret, $admin['password'])) {
        login_attempt_clear();
        update_admin_last_login($admin['id']);
        return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'admin', 'admin' => $admin, 'user' => null, 'vendeur_collaborateur' => null];
    }

    if (function_exists('get_vendeur_compte_acces_by_telephone_for_unified_login')) {
        $collab = get_vendeur_compte_acces_by_telephone_for_unified_login($tel);
        if ($collab) {
            if (($collab['statut'] ?? '') !== 'actif') {
                return login_failure_result_array('Téléphone ou code incorrect.');
            }
            if (!password_verify($secret, $collab['password'])) {
                // Laisser tomber vers la vérification client (même téléphone possible en théorie)
                $collab = null;
            } else {
                $owner = get_admin_by_id((int) ($collab['vendeur_admin_id'] ?? 0));
                if (!$owner || ($owner['statut'] ?? '') !== 'actif' || normalize_admin_role($owner['role'] ?? '') !== 'vendeur') {
                    return login_failure_result_array('Téléphone ou code incorrect.');
                }
                login_attempt_clear();
                update_vendeur_compte_acces_last_login((int) $collab['id']);
                update_admin_last_login($owner['id']);
                return [
                    'success' => true,
                    'message' => 'Connexion réussie !',
                    'type' => 'admin',
                    'admin' => $owner,
                    'user' => null,
                    'vendeur_collaborateur' => $collab,
                ];
            }
        }
    }

    $user = get_user_by_telephone($tel);
    if (!$user) {
        return login_failure_result_array('Téléphone ou code incorrect.');
    }
    if (($user['statut'] ?? '') !== 'actif') {
        return login_failure_result_array('Téléphone ou code incorrect.');
    }
    if (!$accepte_conditions) {
        return ['success' => false, 'message' => 'Vous devez accepter les conditions d\'utilisation pour vous connecter.', 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
    }
    if (!password_verify($secret, $user['password'])) {
        return login_failure_result_array('Téléphone ou code incorrect.');
    }

    login_attempt_clear();
    update_user_accepte_conditions($user['id'], true);
    return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'user', 'admin' => null, 'user' => $user, 'vendeur_collaborateur' => null];
}

/**
 * Traite l'inscription d'un nouvel utilisateur
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_user_inscription() {
    $errors = [];
    $success = false;
    $message = '';
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $email_raw = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    $email = $email_raw;
    $telephone_raw = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $telephone_digits = users_normalize_phone_digits($telephone_raw);
    $pin = isset($_POST['pin']) ? (string) $_POST['pin'] : '';
    $pin_confirm = isset($_POST['pin_confirm']) ? (string) $_POST['pin_confirm'] : '';
    $prenom = '';

    // Validation du nom
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    // Email facultatif
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'email n\'est pas valide.';
        } elseif (user_email_exists($email)) {
            $errors[] = 'Cet email est déjà utilisé.';
        }
    }

    // Validation du téléphone (normalisation internationale → chiffres)
    if ($telephone_raw === '' || $telephone_digits === '') {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (strlen($telephone_digits) < 8) {
        $errors[] = 'Le numéro de téléphone semble incomplet (trop peu de chiffres).';
    } elseif (get_user_by_telephone($telephone_digits)) {
        $errors[] = 'Ce numéro de téléphone est déjà enregistré.';
    }

    // Code PIN à 6 chiffres (remplace le mot de passe)
    if ($pin === '' || $pin_confirm === '') {
        $errors[] = 'Le code PIN et sa confirmation sont obligatoires.';
    } elseif (!preg_match('/^\d{6}$/', $pin)) {
        $errors[] = 'Le code PIN doit comporter exactement 6 chiffres.';
    } elseif ($pin !== $pin_confirm) {
        $errors[] = 'Les deux saisies du code PIN ne correspondent pas.';
    }

    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        $password_hash = password_hash($pin, PASSWORD_BCRYPT);
        $email_db = $email !== '' ? $email : null;

        $creation_err = null;
        $user_id = create_user($nom, $prenom, $email_db, $telephone_digits, $password_hash, $creation_err);
        
        if ($user_id) {
            try {
                require_once __DIR__ . '/../includes/geo_location_service.php';
                $geo_lat = geo_parse_coord($_POST['insc_geo_lat'] ?? null);
                $geo_lng = geo_parse_coord($_POST['insc_geo_lng'] ?? null);
                $geo_precision = geo_parse_precision($_POST['insc_geo_precision'] ?? null);
                if (geo_coords_valid($geo_lat, $geo_lng)) {
                    geo_save_user_last_location((int) $user_id, $geo_lat, $geo_lng, $geo_precision);
                }
            } catch (Throwable $e) {
                error_log('[inscription-client] geo: ' . $e->getMessage());
            }
            $success = true;
            $message = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
        } else {
            $errors[] = $creation_err !== null && $creation_err !== ''
                ? $creation_err
                : 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode("\n", $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la connexion d'un utilisateur
 * @return array Tableau avec 'success' (bool), 'message' (string) et 'user' (array|false)
 */
function process_user_login() {
    $errors = [];
    $success = false;
    $message = '';
    $user = false;
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'user' => false];
    }
    
    // Récupération des données
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $accepte_conditions = isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1';
    
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
    
    // Validation de l'acceptation des conditions
    if (!$accepte_conditions) {
        $errors[] = 'Vous devez accepter les conditions d\'utilisation pour vous connecter.';
    }
    
    // Si aucune erreur de validation, vérifier les identifiants
    if (empty($errors)) {
        // Récupérer l'utilisateur par email
        $user = get_user_by_email($email);
        
        if ($user) {
            // Vérifier le statut
            if ($user['statut'] !== 'actif') {
                $errors[] = 'Votre compte est désactivé. Contactez le support.';
            } elseif (password_verify($password, $user['password'])) {
                // Mot de passe correct
                $success = true;
                $message = 'Connexion réussie !';
                
                // Mettre à jour l'acceptation des conditions d'utilisation
                if ($accepte_conditions) {
                    update_user_accepte_conditions($user['id'], true);
                }
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        } else {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message, 'user' => $user];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message, 'user' => false];
    }
}

/**
 * Traite la demande de réinitialisation de mot de passe (mot de passe oublié) - Clients
 * @return array Tableau avec 'success', 'message', 'email', 'reset_link', 'token'
 */
function process_user_forgot_password() {
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
    } elseif (!user_email_exists($email)) {
        $success = true;
        $message = 'Si cet email est associé à un compte, vous recevrez un lien de réinitialisation.';
        return ['success' => $success, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

        if (create_user_password_reset_token($email, $token, $expires_at)) {
            require_once __DIR__ . '/../includes/site_url.php';
            $base_url = get_site_base_url();
            $reset_link = $base_url . '/user/reinitialiser-mot-de-passe.php?token=' . $token;

            if (function_exists('mail_send_reset_link')) {
                $mail_result = mail_send_reset_link($email, $reset_link, 'user');
                if (!$mail_result['success']) {
                    $message = 'Le lien a été généré mais l\'envoi de l\'email a échoué : ' . ($mail_result['error'] ?? 'Erreur inconnue');
                    return ['success' => false, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
                }
            }

            $success = true;
            $message = 'Si cet email est associé à un compte, vous recevrez un lien de réinitialisation.';
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
 * Traite la réinitialisation du mot de passe (nouveau mot de passe) - Clients
 * @return array Tableau avec 'success', 'message'
 */
function process_user_reset_password() {
    $errors = [];
    $success = false;
    $message = '';
    $token_data = null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if (empty($token)) {
        $errors[] = 'Token invalide ou manquant.';
    } else {
        $token_data = get_valid_user_reset_token($token);
        if (!$token_data) {
            $errors[] = 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez faire une nouvelle demande.';
        }
    }

    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }

    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (empty($errors) && $token_data) {
        $user = get_user_by_email($token_data['email']);
        if ($user) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            if (update_user_password($user['id'], $password_hash) && mark_user_reset_token_used($token)) {
                $success = true;
                $message = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.';
            } else {
                $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
            }
        } else {
            $errors[] = 'Compte introuvable.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return ['success' => $success, 'message' => $message];
}

?>

