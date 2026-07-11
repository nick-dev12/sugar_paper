<?php
/**
 * Mélange aléatoire des listes produits : ordre différent par visiteur et à chaque actualisation.
 */

if (!function_exists('catalogue_ensure_session')) {
    function catalogue_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!function_exists('session_start_persistent')) {
            require_once __DIR__ . '/session_user.php';
        }
        session_start_persistent();
    }
}

if (!function_exists('catalogue_shuffle_visiteur_cle')) {
    function catalogue_shuffle_visiteur_cle()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            catalogue_ensure_session();
        }
        if (!empty($_SESSION['user_id'])) {
            return 'u' . (int) $_SESSION['user_id'];
        }
        if (empty($_SESSION['catalogue_visiteur_id'])) {
            $_SESSION['catalogue_visiteur_id'] = bin2hex(random_bytes(8));
        }
        return 'v' . (string) $_SESSION['catalogue_visiteur_id'];
    }
}

if (!function_exists('catalogue_nouveau_seed')) {
    /**
     * Nouvelle graine aléatoire (chaque appel = nouvel ordre), différenciée par visiteur.
     */
    function catalogue_nouveau_seed($contexte = 'default')
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            catalogue_ensure_session();
        }
        $visiteur = catalogue_shuffle_visiteur_cle();
        $nonce = function_exists('random_int') ? random_int(1, 999999999) : mt_rand(1, 999999999);
        $raw = (string) $contexte . '|' . $visiteur . '|' . microtime(true) . '|' . $nonce;
        $seed = abs(crc32($raw)) % 2147483645 + 1;
        if (!isset($_SESSION['catalogue_seeds']) || !is_array($_SESSION['catalogue_seeds'])) {
            $_SESSION['catalogue_seeds'] = [];
        }
        $_SESSION['catalogue_seeds'][$contexte] = $seed;
        return $seed;
    }
}

if (!function_exists('catalogue_seed_pagination')) {
    /**
     * Graine pour listes paginées : nouvelle à chaque visite de page, conservée pour la suite (API, page 2…).
     *
     * @param string   $contexte
     * @param int|null $seed_param   Graine transmise en query string (pagination / API)
     * @param bool     $forcer_nouveau true = nouvelle graine (chargement initial de la page)
     */
    function catalogue_seed_pagination($contexte, $seed_param = null, $forcer_nouveau = false)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            catalogue_ensure_session();
        }
        if ($seed_param !== null && $seed_param !== '') {
            $seed = max(1, (int) $seed_param);
            if (!isset($_SESSION['catalogue_seeds']) || !is_array($_SESSION['catalogue_seeds'])) {
                $_SESSION['catalogue_seeds'] = [];
            }
            $_SESSION['catalogue_seeds'][$contexte] = $seed;
            return $seed;
        }
        if (!$forcer_nouveau && !empty($_SESSION['catalogue_seeds'][$contexte])) {
            return (int) $_SESSION['catalogue_seeds'][$contexte];
        }
        return catalogue_nouveau_seed($contexte);
    }
}

if (!function_exists('catalogue_melanger_produits')) {
    /**
     * Mélange un tableau de produits (Fisher-Yates).
     *
     * @param array    $produits
     * @param int|null $seed Graine optionnelle pour un ordre déterministe
     */
    function catalogue_melanger_produits($produits, $seed = null)
    {
        if (!is_array($produits) || count($produits) < 2) {
            return is_array($produits) ? array_values($produits) : [];
        }
        $produits = array_values($produits);
        if ($seed !== null) {
            mt_srand((int) $seed);
        } else {
            $visiteur = catalogue_shuffle_visiteur_cle();
            $nonce = function_exists('random_int') ? random_int(0, 9999) : mt_rand(0, 9999);
            mt_srand((int) (microtime(true) * 1000000) + crc32($visiteur) + $nonce);
        }
        shuffle($produits);
        return $produits;
    }
}

if (!function_exists('catalogue_tirer_produits_similaires')) {
    /**
     * Mélange les candidats « similaires » puis en retourne $limit.
     * Graine liée au produit consulté + visiteur : ordre différent d'une fiche à l'autre,
     * stable lors d'un rafraîchissement de la même fiche.
     *
     * @param array $candidats Liste de produits (tableaux)
     * @param int   $produit_courant_id Produit affiché (exclu si encore présent)
     * @param int   $limit Nombre affiché (défaut 8)
     * @return array
     */
    function catalogue_tirer_produits_similaires(array $candidats, $produit_courant_id, $limit = 8)
    {
        $produit_courant_id = (int) $produit_courant_id;
        $limit = max(1, min(24, (int) $limit));
        if ($candidats === []) {
            return [];
        }

        $filtres = [];
        foreach ($candidats as $p) {
            if (!is_array($p)) {
                continue;
            }
            if ((int) ($p['id'] ?? 0) === $produit_courant_id) {
                continue;
            }
            $filtres[] = $p;
        }
        if (count($filtres) < 2) {
            return array_slice($filtres, 0, $limit);
        }

        $visiteur = catalogue_shuffle_visiteur_cle();
        $seed = abs(crc32($visiteur . '|produit-similaires|' . $produit_courant_id)) % 2147483645 + 1;
        $melange = catalogue_melanger_produits($filtres, $seed);

        return array_slice($melange, 0, $limit);
    }
}
