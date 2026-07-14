<?php
/**
 * Affichage compte connecté — navigation boutique (header + bottom nav)
 * Programmation procédurale uniquement
 */

if (!function_exists('store_nav_account_display_name')) {

    function store_nav_account_display_name($prenom, $nom, $email = '') {
        $prenom = trim((string) $prenom);
        $nom = trim((string) $nom);
        $email = trim((string) $email);

        if ($prenom !== '') {
            return $prenom;
        }

        if ($nom !== '') {
            $parts = preg_split('/\s+/u', $nom);
            return trim((string) ($parts[0] ?? $nom));
        }

        if ($email !== '') {
            $at = strpos($email, '@');
            return $at !== false ? substr($email, 0, $at) : $email;
        }

        return '';
    }

    function store_nav_account_short_label($display_name, $fallback = 'Compte') {
        $display_name = trim((string) $display_name);
        if ($display_name === '') {
            return $fallback;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($display_name) > 12) {
                return mb_substr($display_name, 0, 11) . '…';
            }
        } elseif (strlen($display_name) > 12) {
            return substr($display_name, 0, 11) . '…';
        }

        return $display_name;
    }

    function store_nav_account_info() {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/session_user.php';
            session_start_persistent();
        }

        $info = [
            'url' => '/user/connexion.php',
            'title' => 'Mon compte',
            'subtitle' => 'Identifiez-vous',
            'short_label' => 'Compte',
            'logged_in' => false,
            'btn_class' => 'nav-compte-btn',
        ];

        global $commercant;

        if (isset($_SESSION['commercant_id'])) {
            $info['logged_in'] = true;
            $info['url'] = '/view/profil_commercent.php';
            $display = '';
            if (isset($commercant) && is_array($commercant) && !empty($commercant['nom'])) {
                $display = store_nav_account_display_name('', (string) $commercant['nom']);
            }
            if ($display === '') {
                $display = 'Commerçant';
            }
            $info['subtitle'] = $display;
            $info['short_label'] = store_nav_account_short_label($display);
            return $info;
        }

        if (!empty($_SESSION['user_id'])) {
            $info['logged_in'] = true;
            $info['url'] = '/user/mon-compte.php';
            $display = store_nav_account_display_name(
                $_SESSION['user_prenom'] ?? '',
                $_SESSION['user_nom'] ?? '',
                $_SESSION['user_email'] ?? ''
            );

            if ($display === '') {
                $model_path = __DIR__ . '/../models/model_users.php';
                if (file_exists($model_path)) {
                    require_once $model_path;
                    if (function_exists('get_user_by_id')) {
                        $user = get_user_by_id((int) $_SESSION['user_id']);
                        if ($user) {
                            if (!empty($user['prenom'])) {
                                $_SESSION['user_prenom'] = $user['prenom'];
                            }
                            if (!empty($user['nom'])) {
                                $_SESSION['user_nom'] = $user['nom'];
                            }
                            if (!empty($user['email'])) {
                                $_SESSION['user_email'] = $user['email'];
                            }
                            $display = store_nav_account_display_name(
                                $user['prenom'] ?? '',
                                $user['nom'] ?? '',
                                $user['email'] ?? ''
                            );
                        }
                    }
                }
            }

            if ($display === '') {
                $display = 'Mon compte';
            }

            $info['subtitle'] = $display;
            $info['short_label'] = store_nav_account_short_label($display);
            return $info;
        }

        if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_email'])) {
            $info['logged_in'] = true;
            $info['url'] = '/admin/dashboard.php';
            $info['btn_class'] = 'nav-compte-btn nav-compte-btn--admin';
            $display = store_nav_account_display_name(
                $_SESSION['admin_prenom'] ?? '',
                $_SESSION['admin_nom'] ?? '',
                $_SESSION['admin_email'] ?? ''
            );

            if ($display === '') {
                $model_path = __DIR__ . '/../models/model_admin.php';
                if (file_exists($model_path)) {
                    require_once $model_path;
                    if (function_exists('get_admin_by_id')) {
                        $admin = get_admin_by_id((int) $_SESSION['admin_id']);
                        if ($admin) {
                            if (!empty($admin['prenom'])) {
                                $_SESSION['admin_prenom'] = $admin['prenom'];
                            }
                            if (!empty($admin['nom'])) {
                                $_SESSION['admin_nom'] = $admin['nom'];
                            }
                            $display = store_nav_account_display_name(
                                $admin['prenom'] ?? '',
                                $admin['nom'] ?? '',
                                $admin['email'] ?? ''
                            );
                        }
                    }
                }
            }

            if ($display === '') {
                $display = 'Admin';
            }

            $info['subtitle'] = $display;
            $info['short_label'] = store_nav_account_short_label($display);
            return $info;
        }

        return $info;
    }
}
