<?php
/**
 * Inscription / connexion automatique lors du checkout invité (nom + téléphone).
 */

require_once __DIR__ . '/guest_client.php';

if (!defined('GUEST_CHECKOUT_DEFAULT_PASSWORD')) {
    define('GUEST_CHECKOUT_DEFAULT_PASSWORD', 'SugarPaper@26');
}

if (!function_exists('guest_checkout_csrf_token')) {
    function guest_checkout_csrf_token()
    {
        if (empty($_SESSION['guest_checkout_csrf'])) {
            $_SESSION['guest_checkout_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['guest_checkout_csrf'];
    }
}

if (!function_exists('guest_checkout_csrf_verify')) {
    function guest_checkout_csrf_verify($token)
    {
        $token = (string) $token;
        if ($token === '' || empty($_SESSION['guest_checkout_csrf'])) {
            return false;
        }
        return hash_equals((string) $_SESSION['guest_checkout_csrf'], $token);
    }
}

if (!function_exists('guest_checkout_pin_attempts_remaining')) {
    function guest_checkout_pin_attempts_remaining()
    {
        $max = 5;
        $count = (int) ($_SESSION['guest_checkout_pin_attempts'] ?? 0);
        return max(0, $max - $count);
    }
}

if (!function_exists('guest_checkout_register_pin_failure')) {
    function guest_checkout_register_pin_failure()
    {
        $_SESSION['guest_checkout_pin_attempts'] = (int) ($_SESSION['guest_checkout_pin_attempts'] ?? 0) + 1;
    }
}

if (!function_exists('guest_checkout_clear_pin_attempts')) {
    function guest_checkout_clear_pin_attempts()
    {
        unset($_SESSION['guest_checkout_pin_attempts']);
    }
}

if (!function_exists('guest_checkout_pin_validate')) {
    /**
     * Valide le PIN checkout invité (sans confirmation — 1 seul champ).
     * @param string $pin
     * @param bool $is_existing_user
     * @param string|null $pin_confirm Ignoré (rétrocompat)
     */
    function guest_checkout_pin_validate($pin, $is_existing_user, $pin_confirm = null)
    {
        $pin = (string) $pin;
        // Ancien appel (pin, pin_confirm, is_existing) — détecter si 2e arg = string
        if (!is_bool($is_existing_user) && $pin_confirm !== null && is_bool($pin_confirm)) {
            $is_existing_user = $pin_confirm;
        } elseif (!is_bool($is_existing_user)) {
            $is_existing_user = (bool) $is_existing_user;
        }

        if ($pin === '') {
            return ['ok' => false, 'message' => 'Le code PIN est obligatoire.'];
        }

        if ($is_existing_user) {
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                return ['ok' => false, 'message' => 'Le code PIN doit comporter 4 à 6 chiffres.'];
            }
            return ['ok' => true, 'message' => ''];
        }

        if (!preg_match('/^\d{4}$/', $pin)) {
            return ['ok' => false, 'message' => 'Le code PIN doit comporter exactement 4 chiffres.'];
        }

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('guest_checkout_login_user_session')) {
    function guest_checkout_login_user_session($user)
    {
        if (!$user || empty($user['id'])) {
            return false;
        }

        if (function_exists('session_regenerate_persistent')) {
            session_regenerate_persistent();
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_nom'] = $user['nom'] ?? '';
        $_SESSION['user_prenom'] = $user['prenom'] ?? '';
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_telephone'] = $user['telephone'] ?? '';
        $_SESSION['user_statut'] = $user['statut'] ?? 'actif';
        $_SESSION['fcm_resync_user'] = 1;

        if (file_exists(__DIR__ . '/panier_invite.php')) {
            require_once __DIR__ . '/panier_invite.php';
            panier_fusionner_invite_apres_connexion((int) $user['id']);
        }

        guest_client_clear();
        unset($_SESSION['guest_checkout_pending'], $_SESSION['guest_checkout_phone_exists']);

        return true;
    }
}

if (!function_exists('guest_checkout_link_past_orders')) {
    function guest_checkout_link_past_orders($user_id, $telephone)
    {
        global $db;

        $user_id = (int) $user_id;
        if ($user_id < 1 || !isset($db) || !($db instanceof PDO)) {
            return 0;
        }

        require_once __DIR__ . '/../models/model_users.php';
        $digits = users_normalize_phone_digits($telephone);
        if ($digits === '') {
            return 0;
        }

        try {
            $stmt = $db->prepare("
                UPDATE commandes
                SET user_id = :user_id
                WHERE user_id IS NULL
                  AND client_telephone IS NOT NULL
                  AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(client_telephone,''), ' ', ''), '-', ''), '+', ''), '.', '') = :digits
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'digits' => $digits,
            ]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('guest_checkout_link_past_commandes_personnalisees')) {
    function guest_checkout_link_past_commandes_personnalisees($user_id, $telephone)
    {
        global $db;

        $user_id = (int) $user_id;
        if ($user_id < 1 || !isset($db) || !($db instanceof PDO)) {
            return 0;
        }

        require_once __DIR__ . '/../models/model_users.php';
        $variants = users_phone_lookup_variants($telephone);
        if (empty($variants)) {
            return 0;
        }

        $norm = "REPLACE(REPLACE(" . users_phone_normalized_sql('telephone') . ", '(', ''), ')', '')";
        $placeholders = [];
        $params = ['user_id' => $user_id];
        foreach ($variants as $i => $variant) {
            $key = 'p' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $variant;
        }

        try {
            $stmt = $db->prepare("
                UPDATE commandes_personnalisees
                SET user_id = :user_id
                WHERE (user_id IS NULL OR user_id = 0)
                  AND telephone IS NOT NULL AND TRIM(telephone) != ''
                  AND {$norm} IN (" . implode(', ', $placeholders) . ")
            ");
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('guest_checkout_save_pending')) {
    function guest_checkout_save_pending($nom, $telephone)
    {
        require_once __DIR__ . '/../models/model_users.php';

        $nom = trim((string) $nom);
        $telephone = trim((string) $telephone);
        $digits = users_normalize_phone_digits($telephone);

        if ($nom === '' || $digits === '') {
            return ['success' => false, 'message' => 'Nom et téléphone obligatoires.'];
        }
        if (strlen($digits) < 8) {
            return ['success' => false, 'message' => 'Le numéro de téléphone semble incomplet.'];
        }

        $_SESSION['guest_checkout_pending'] = [
            'nom' => $nom,
            'telephone' => $telephone,
            'telephone_digits' => $digits,
        ];
        $existing = get_user_by_telephone($digits);
        $_SESSION['guest_checkout_phone_exists'] = $existing ? 1 : 0;
        guest_client_save($nom, $telephone);

        return [
            'success' => true,
            'message' => '',
            'phone_exists' => (bool) $existing,
        ];
    }
}

if (!function_exists('guest_checkout_get_pending')) {
    function guest_checkout_get_pending()
    {
        if (empty($_SESSION['guest_checkout_pending']) || !is_array($_SESSION['guest_checkout_pending'])) {
            return null;
        }
        require_once __DIR__ . '/../models/model_users.php';
        $nom = trim((string) ($_SESSION['guest_checkout_pending']['nom'] ?? ''));
        $telephone = trim((string) ($_SESSION['guest_checkout_pending']['telephone'] ?? ''));
        if ($nom === '' || $telephone === '') {
            return null;
        }
        $digits = users_normalize_phone_digits($telephone);
        $existing = get_user_by_telephone($digits);
        if ($existing) {
            $_SESSION['guest_checkout_phone_exists'] = 1;
        }
        return [
            'nom' => $nom,
            'telephone' => $telephone,
            'telephone_digits' => $digits,
            'phone_exists' => (bool) $existing,
        ];
    }
}

if (!function_exists('guest_checkout_register_or_login')) {
    /**
     * Crée ou connecte un client après saisie nom + téléphone (checkout invité).
     * Mot de passe système : GUEST_CHECKOUT_DEFAULT_PASSWORD (nouveaux comptes).
     * Comptes existants : connexion automatique par numéro de téléphone.
     *
     * @param string $nom
     * @param string $telephone
     * @param bool $accepte_conditions
     * @return array{success:bool,message:string,user?:array,created?:bool,phone_exists?:bool}
     */
    function guest_checkout_register_or_login($nom, $telephone, $accepte_conditions = true, $unused = null)
    {
        require_once __DIR__ . '/../models/model_users.php';

        $nom = trim((string) $nom);
        $telephone = trim((string) $telephone);
        $digits = users_normalize_phone_digits($telephone);

        if ($nom === '' || $digits === '') {
            return ['success' => false, 'message' => 'Nom et téléphone obligatoires.'];
        }
        if (strlen($digits) < 8) {
            return ['success' => false, 'message' => 'Le numéro de téléphone semble incomplet.'];
        }

        $accepte_conditions = true;

        guest_client_save($nom, $telephone);

        $existing = get_user_by_telephone($digits);
        $_SESSION['guest_checkout_phone_exists'] = $existing ? 1 : 0;

        if ($existing) {
            if (($existing['statut'] ?? '') !== 'actif') {
                return ['success' => false, 'message' => 'Ce compte est inactif. Contactez le support.'];
            }

            update_user_accepte_conditions((int) $existing['id'], true);
            guest_checkout_login_user_session($existing);
            guest_checkout_link_past_orders((int) $existing['id'], $digits);
            guest_checkout_link_past_commandes_personnalisees((int) $existing['id'], $digits);

            return [
                'success' => true,
                'message' => 'Connexion réussie.',
                'user' => $existing,
                'created' => false,
                'phone_exists' => true,
            ];
        }

        if (strlen($nom) < 2) {
            return ['success' => false, 'message' => 'Le nom doit contenir au moins 2 caractères.'];
        }

        $password_hash = password_hash(GUEST_CHECKOUT_DEFAULT_PASSWORD, PASSWORD_BCRYPT);
        $user_id = create_user_guest_checkout($nom, $digits, $password_hash);
        if (!$user_id) {
            $existing_retry = get_user_by_telephone($digits);
            if ($existing_retry) {
                $_SESSION['guest_checkout_phone_exists'] = 1;
                update_user_accepte_conditions((int) $existing_retry['id'], true);
                guest_checkout_login_user_session($existing_retry);
                guest_checkout_link_past_orders((int) $existing_retry['id'], $digits);
                guest_checkout_link_past_commandes_personnalisees((int) $existing_retry['id'], $digits);
                return [
                    'success' => true,
                    'message' => 'Connexion réussie.',
                    'user' => $existing_retry,
                    'created' => false,
                    'phone_exists' => true,
                ];
            }
            return ['success' => false, 'message' => 'Impossible de créer le compte. Veuillez réessayer.'];
        }

        update_user_accepte_conditions((int) $user_id, true);
        $user = get_user_by_id((int) $user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'Compte créé mais connexion impossible. Réessayez.'];
        }

        guest_checkout_login_user_session($user);
        guest_checkout_link_past_orders((int) $user_id, $digits);
        guest_checkout_link_past_commandes_personnalisees((int) $user_id, $digits);

        return [
            'success' => true,
            'message' => 'Compte créé et connecté.',
            'user' => $user,
            'created' => true,
            'phone_exists' => false,
        ];
    }
}

if (!function_exists('guest_checkout_safe_redirect')) {
    function guest_checkout_safe_redirect($url, $default = '/panier.php')
    {
        $url = trim((string) $url);
        if ($url === '' || strpos($url, '//') !== false || $url[0] !== '/') {
            return $default;
        }
        return $url;
    }
}
