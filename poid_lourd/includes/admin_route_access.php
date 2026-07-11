<?php
/**
 * Contrôle d'accès aux routes admin par rôle (liste blanche).
 * Programmation procédurale uniquement.
 */

if (!function_exists('admin_route_relative_path')) {

    /**
     * Chemin relatif sous admin/ (ex. devis/bl_enregistrer.php)
     */
    function admin_route_relative_path() {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#/admin/(.+\.php)$#', $script, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Rôle effectif en session (migration utilisateur → gestion_stock)
     */
    function admin_normalize_role_for_route($role) {
        $r = (string) $role;
        if ($r === 'utilisateur') {
            return 'gestion_stock';
        }
        return $r;
    }

    /**
     * URL de secours (relative à admin/) si la route est interdite
     */
    function admin_role_default_redirect_path($role) {
        $r = admin_normalize_role_for_route($role);
        switch ($r) {
            case 'commercial':
                return 'devis/index.php';
            case 'caissier':
                return 'caisse/encaisser-ticket.php';
            case 'comptabilite':
                return 'comptabilite/index.php';
            case 'rh':
                return 'contacts/index.php';
            case 'gestion_stock':
                return 'stock/index.php';
            case 'vendeur':
                return 'dashboard.php';
            default:
                return 'dashboard.php';
        }
    }

    /**
     * Indique si le rôle peut accéder au script courant
     */
    function admin_route_is_allowed($role, $relativePath) {
        $r = admin_normalize_role_for_route($role);
        // Admin, plateforme et vendeur marketplace : même arborescence d’écrans que l’admin
        if ($r === 'admin' || $r === 'plateforme' || $r === 'vendeur') {
            return true;
        }

        $p = $relativePath;
        if ($p === '') {
            return false;
        }

        // Pages communes à tous les comptes connectés (hub compte + raccourcis)
        if ($p === 'profil.php' || $p === 'parametres.php') {
            return true;
        }

        $starts = function ($prefix) use ($p) {
            return strpos($p, $prefix) === 0;
        };

        switch ($r) {
            case 'commercial':
                return $starts('devis/')
                    || $starts('commandes/')
                    || $starts('caisse/')
                    || $starts('commercial/');

            case 'comptabilite':
                if ($p === 'comptabilite/index.php' || $p === 'comptabilite/bl-fiche-client.php') {
                    return true;
                }
                if ($p === 'commandes/historique-ventes.php') {
                    return true;
                }
                $compta_devis = [
                    'devis/facture_mensuelle.php',
                    'devis/facture_mensuelle_generer.php',
                    'devis/facture_mensuelle_valider.php',
                    'devis/bl_voir.php',
                    'devis/bl_modifier.php',
                ];
                return in_array($p, $compta_devis, true);

            case 'rh':
                return $starts('contacts/')
                    || $starts('users/')
                    || $p === 'comptes/index.php'
                    || $p === 'comptes/employe-activite.php'
                    || $p === 'comptes/employe-activite-liste.php'
                    || $starts('comptes/employes/');

            case 'caissier':
                return $p === 'caisse/encaisser-ticket.php'
                    || $p === 'caisse/historique-encaissements.php'
                    || $p === 'caisse/post.php';

            case 'gestion_stock':
                return $starts('stock/')
                    || $starts('produits/')
                    || $p === 'categories/produits.php'
                    || $p === 'categories/modifier.php'
                    || $p === 'categories/ajouter.php'
                    || $p === 'categories/supprimer.php';

            default:
                return false;
        }
    }

    /**
     * URL absolue (chemin) vers une page sous admin/
     */
    function admin_route_build_url($relativeUnderAdmin) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($script);
        if (preg_match('#^(.*)/admin(?:/|$)#', $dir, $m)) {
            return $m[1] . '/admin/' . ltrim($relativeUnderAdmin, '/');
        }
        return '/admin/' . ltrim($relativeUnderAdmin, '/');
    }

    /**
     * Applique le contrôle d'accès (à apporter après vérification de session admin).
     */
    function admin_route_enforce() {
        if (!isset($_SESSION['admin_id'])) {
            return;
        }

        $role = $_SESSION['admin_role'] ?? 'admin';
        $_SESSION['admin_role'] = admin_normalize_role_for_route($role);

        $rel = admin_route_relative_path();
        if (admin_route_is_allowed($_SESSION['admin_role'], $rel)) {
            return;
        }

        $target = admin_role_default_redirect_path($_SESSION['admin_role']);
        header('Location: ' . admin_route_build_url($target));
        exit;
    }

    /**
     * Pour scripts AJAX (JSON) : réponse vide / 403 sans redirection HTML.
     */
    function admin_route_enforce_json_empty() {
        if (!isset($_SESSION['admin_id'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([]);
            exit;
        }
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        $_SESSION['admin_role'] = $role;
        $rel = admin_route_relative_path();
        if ($role === 'admin' || $role === 'plateforme' || admin_route_is_allowed($role, $rel)) {
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
}

if (!function_exists('admin_vendeur_filter_id')) {
    /**
     * ID vendeur pour filtrer commandes / stats (null = pas de filtre).
     */
    function admin_vendeur_filter_id() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        if (($_SESSION['admin_role'] ?? '') === 'vendeur') {
            return (int) ($_SESSION['admin_id'] ?? 0);
        }
        return null;
    }
}

if (!function_exists('admin_categories_list_for_session')) {
    /**
     * Catégories pour filtres et formulaires admin : tout pour la plateforme,
     * uniquement les rayons de la boutique pour le rôle vendeur.
     */
    function admin_categories_list_for_session() {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['admin_id'])) {
            return [];
        }
        require_once dirname(__DIR__) . '/models/model_categories.php';
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        if ($role === 'vendeur') {
            $plat = function_exists('get_plateforme_sous_categories_for_form')
                ? get_plateforme_sous_categories_for_form() : [];
            if (!empty($plat)) {
                return $plat;
            }
            return get_categories_for_vendeur_stock((int) $_SESSION['admin_id']);
        }
        return get_all_categories();
    }
}

if (!function_exists('admin_vendeur_assert_categorie_editable')) {
    /**
     * Autorise le vendeur uniquement à modifier / supprimer ses propres catégories (pas les rayons plateforme).
     */
    function admin_vendeur_assert_categorie_editable($categorie_id) {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['admin_id'])) {
            return;
        }
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        if ($role !== 'vendeur') {
            return;
        }
        $cid = (int) $categorie_id;
        $vid = (int) $_SESSION['admin_id'];
        if ($cid <= 0 || $vid <= 0) {
            header('Location: ' . admin_route_build_url('stock/index.php'));
            exit;
        }
        require_once dirname(__DIR__) . '/models/model_categories.php';
        if (!categorie_est_modifiable_par_vendeur($cid, $vid)) {
            header('Location: ' . admin_route_build_url('stock/index.php'));
            exit;
        }
    }
}

if (!function_exists('admin_vendeur_assert_categorie_allowed')) {
    /**
     * Bloque l’accès si la catégorie n’appartient pas à l’espace du vendeur connecté.
     */
    function admin_vendeur_assert_categorie_allowed($categorie_id) {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['admin_id'])) {
            return;
        }
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        if ($role !== 'vendeur') {
            return;
        }
        $cid = (int) $categorie_id;
        $vid = (int) $_SESSION['admin_id'];
        if ($cid <= 0 || $vid <= 0) {
            header('Location: ' . admin_route_build_url('stock/index.php'));
            exit;
        }
        require_once dirname(__DIR__) . '/models/model_categories.php';
        if (!categorie_est_utilisable_par_vendeur($cid, $vid)) {
            header('Location: ' . admin_route_build_url('stock/index.php'));
            exit;
        }
    }
}

if (!function_exists('admin_vendeur_assert_produit_owned')) {
    /**
     * Empêche un vendeur d’ouvrir le formulaire / la fiche d’un produit d’une autre boutique.
     */
    function admin_vendeur_assert_produit_owned($produit) {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['admin_id'])) {
            return;
        }
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        if ($role !== 'vendeur') {
            return;
        }
        if (!$produit || !is_array($produit)) {
            header('Location: ' . admin_route_build_url('produits/index.php'));
            exit;
        }
        $vid = (int) $_SESSION['admin_id'];
        $aid = (int) ($produit['admin_id'] ?? 0);
        if ($aid !== $vid) {
            header('Location: ' . admin_route_build_url('produits/index.php'));
            exit;
        }
    }
}
