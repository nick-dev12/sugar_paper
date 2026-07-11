<?php
/**
 * Panier invité (session PHP) — fusion en BDD à la connexion.
 * Programmation procédurale uniquement.
 */

if (!function_exists('panier_invite_ensure_session')) {
    function panier_invite_ensure_session()
    {
        if (!isset($_SESSION['panier_invite']) || !is_array($_SESSION['panier_invite'])) {
            $_SESSION['panier_invite'] = ['next_id' => 1, 'lines' => []];
        }
        if (!isset($_SESSION['panier_invite']['lines']) || !is_array($_SESSION['panier_invite']['lines'])) {
            $_SESSION['panier_invite']['lines'] = [];
        }
        if (!isset($_SESSION['panier_invite']['next_id'])) {
            $_SESSION['panier_invite']['next_id'] = 1;
        }
    }
}

if (!function_exists('panier_invite_add_line')) {
    /**
     * @return int|false ID de ligne session ou false
     */
    function panier_invite_add_line(
        $produit_id,
        $quantite,
        $couleur = null,
        $poids = null,
        $taille = null,
        $variante_id = null,
        $variante_nom = null,
        $variante_image = null,
        $surcout_poids = 0,
        $surcout_taille = 0,
        $prix_unitaire = null,
        $vendeur_id = null
    ) {
        panier_invite_ensure_session();
        require_once __DIR__ . '/../models/model_produits.php';

        $produit_id = (int) $produit_id;
        $quantite = (int) $quantite;
        if ($produit_id <= 0 || $quantite <= 0) {
            return false;
        }

        if ($vendeur_id === null || $vendeur_id === '') {
            $pr = get_produit_by_id($produit_id);
            $vendeur_id = ($pr && !empty($pr['admin_id'])) ? (int) $pr['admin_id'] : 0;
        } else {
            $vendeur_id = (int) $vendeur_id;
        }

        $vid = $variante_id ? (int) $variante_id : 0;
        $couleur_s = $couleur !== null && $couleur !== '' ? (string) $couleur : '';
        $poids_s = $poids !== null && $poids !== '' ? (string) $poids : '';
        $taille_s = $taille !== null && $taille !== '' ? (string) $taille : '';

        foreach ($_SESSION['panier_invite']['lines'] as $lid => $line) {
            $match = (int) ($line['produit_id'] ?? 0) === $produit_id
                && (int) ($line['vendeur_id'] ?? 0) === (int) $vendeur_id
                && (string) ($line['couleur'] ?? '') === $couleur_s
                && (string) ($line['poids'] ?? '') === $poids_s
                && (string) ($line['taille'] ?? '') === $taille_s
                && (int) ($line['variante_id'] ?? 0) === $vid;
            if ($match) {
                $_SESSION['panier_invite']['lines'][$lid]['quantite'] = (int) ($line['quantite'] ?? 0) + $quantite;
                if ($prix_unitaire !== null && $prix_unitaire > 0) {
                    $_SESSION['panier_invite']['lines'][$lid]['prix_unitaire'] = (float) $prix_unitaire;
                }
                return (int) $lid;
            }
        }

        $new_id = (int) $_SESSION['panier_invite']['next_id'];
        $_SESSION['panier_invite']['next_id'] = $new_id + 1;
        $_SESSION['panier_invite']['lines'][$new_id] = [
            'produit_id' => $produit_id,
            'quantite' => $quantite,
            'couleur' => $couleur_s !== '' ? $couleur_s : null,
            'poids' => $poids_s !== '' ? $poids_s : null,
            'taille' => $taille_s !== '' ? $taille_s : null,
            'variante_id' => $vid > 0 ? $vid : null,
            'variante_nom' => $variante_nom,
            'variante_image' => $variante_image,
            'surcout_poids' => (float) $surcout_poids,
            'surcout_taille' => (float) $surcout_taille,
            'prix_unitaire' => ($prix_unitaire !== null && $prix_unitaire > 0) ? (float) $prix_unitaire : null,
            'vendeur_id' => (int) $vendeur_id,
            'date_ajout' => date('Y-m-d H:i:s'),
        ];

        return $new_id;
    }
}

if (!function_exists('panier_invite_enrich_line')) {
    function panier_invite_enrich_line($line_id, array $line)
    {
        require_once __DIR__ . '/../models/model_produits.php';

        $produit = get_produit_by_id((int) ($line['produit_id'] ?? 0));
        if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
            return null;
        }

        $item = $produit;
        $item['panier_id'] = (int) $line_id;
        $item['quantite'] = (int) ($line['quantite'] ?? 1);
        $item['date_ajout'] = $line['date_ajout'] ?? null;
        $item['panier_couleur'] = $line['couleur'] ?? null;
        $item['panier_poids'] = $line['poids'] ?? null;
        $item['panier_taille'] = $line['taille'] ?? null;
        $item['panier_variante_id'] = $line['variante_id'] ?? null;
        $item['panier_variante_nom'] = $line['variante_nom'] ?? null;
        $item['panier_variante_image'] = $line['variante_image'] ?? null;
        $item['panier_surcout_poids'] = $line['surcout_poids'] ?? 0;
        $item['panier_surcout_taille'] = $line['surcout_taille'] ?? 0;
        $item['panier_prix_unitaire'] = $line['prix_unitaire'] ?? null;
        $item['vendeur_id'] = (int) ($line['vendeur_id'] ?? 0);
        if ($item['vendeur_id'] <= 0 && !empty($produit['admin_id'])) {
            $item['vendeur_id'] = (int) $produit['admin_id'];
        }

        if (!empty($produit['vendeur_boutique_nom'])) {
            $item['vendeur_boutique_nom'] = $produit['vendeur_boutique_nom'];
            $item['vendeur_boutique_slug'] = $produit['vendeur_boutique_slug'] ?? '';
        } elseif ($item['vendeur_id'] > 0 && function_exists('get_admin_by_id')) {
            require_once __DIR__ . '/../models/model_admin.php';
            $adm = get_admin_by_id($item['vendeur_id']);
            if ($adm) {
                $item['vendeur_boutique_nom'] = $adm['boutique_nom'] ?? 'Boutique';
                $item['vendeur_boutique_slug'] = $adm['boutique_slug'] ?? '';
            }
        }

        if (empty($item['categorie_nom']) && !empty($produit['categorie_id'])) {
            require_once __DIR__ . '/../models/model_categories.php';
            $cat = get_categorie_by_id((int) $produit['categorie_id']);
            if ($cat) {
                $item['categorie_nom'] = $cat['nom'] ?? '';
            }
        }

        return $item;
    }
}

if (!function_exists('panier_invite_get_items')) {
    function panier_invite_get_items()
    {
        panier_invite_ensure_session();
        $items = [];
        foreach ($_SESSION['panier_invite']['lines'] as $lid => $line) {
            if (!is_array($line)) {
                continue;
            }
            $enriched = panier_invite_enrich_line((int) $lid, $line);
            if ($enriched) {
                $items[] = $enriched;
            }
        }
        return $items;
    }
}

if (!function_exists('panier_invite_update_quantite')) {
    function panier_invite_update_quantite($line_id, $quantite)
    {
        panier_invite_ensure_session();
        $line_id = (int) $line_id;
        $quantite = (int) $quantite;
        if (!isset($_SESSION['panier_invite']['lines'][$line_id])) {
            return false;
        }
        if ($quantite <= 0) {
            unset($_SESSION['panier_invite']['lines'][$line_id]);
            return true;
        }
        $_SESSION['panier_invite']['lines'][$line_id]['quantite'] = $quantite;
        return true;
    }
}

if (!function_exists('panier_invite_delete_line')) {
    function panier_invite_delete_line($line_id)
    {
        panier_invite_ensure_session();
        $line_id = (int) $line_id;
        if (!isset($_SESSION['panier_invite']['lines'][$line_id])) {
            return false;
        }
        unset($_SESSION['panier_invite']['lines'][$line_id]);
        return true;
    }
}

if (!function_exists('panier_invite_count_items')) {
    function panier_invite_count_items()
    {
        panier_invite_ensure_session();
        $n = 0;
        foreach ($_SESSION['panier_invite']['lines'] as $line) {
            $n += (int) ($line['quantite'] ?? 0);
        }
        return $n;
    }
}

if (!function_exists('panier_invite_get_total')) {
    function panier_invite_get_total()
    {
        require_once __DIR__ . '/../models/model_panier.php';
        $items = panier_invite_get_items();
        return function_exists('panier_items_sous_total') ? panier_items_sous_total($items) : 0.0;
    }
}

if (!function_exists('panier_invite_merge_into_user')) {
    function panier_invite_merge_into_user($user_id)
    {
        panier_invite_ensure_session();
        $user_id = (int) $user_id;
        if ($user_id <= 0 || empty($_SESSION['panier_invite']['lines'])) {
            return;
        }
        require_once __DIR__ . '/../models/model_panier.php';

        foreach ($_SESSION['panier_invite']['lines'] as $line) {
            if (!is_array($line)) {
                continue;
            }
            add_to_panier(
                $user_id,
                (int) ($line['produit_id'] ?? 0),
                (int) ($line['quantite'] ?? 1),
                $line['couleur'] ?? null,
                $line['poids'] ?? null,
                $line['taille'] ?? null,
                !empty($line['variante_id']) ? (int) $line['variante_id'] : null,
                $line['variante_nom'] ?? null,
                $line['variante_image'] ?? null,
                (float) ($line['surcout_poids'] ?? 0),
                (float) ($line['surcout_taille'] ?? 0),
                isset($line['prix_unitaire']) ? (float) $line['prix_unitaire'] : null,
                !empty($line['vendeur_id']) ? (int) $line['vendeur_id'] : null
            );
        }
        unset($_SESSION['panier_invite']);
    }
}

if (!function_exists('panier_utilisateur_est_connecte')) {
    function panier_utilisateur_est_connecte()
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }
}

if (!function_exists('panier_get_items_courant')) {
    function panier_get_items_courant()
    {
        if (panier_utilisateur_est_connecte()) {
            return get_panier_by_user((int) $_SESSION['user_id']);
        }
        return panier_invite_get_items();
    }
}

if (!function_exists('panier_get_total_courant')) {
    function panier_get_total_courant()
    {
        if (panier_utilisateur_est_connecte()) {
            return get_panier_total((int) $_SESSION['user_id']);
        }
        return panier_invite_get_total();
    }
}

if (!function_exists('panier_count_items_courant')) {
    function panier_count_items_courant()
    {
        if (panier_utilisateur_est_connecte()) {
            return count_panier_items((int) $_SESSION['user_id']);
        }
        return panier_invite_count_items();
    }
}

if (!function_exists('panier_fusionner_invite_apres_connexion')) {
    function panier_fusionner_invite_apres_connexion($user_id)
    {
        panier_invite_merge_into_user((int) $user_id);
    }
}
