<?php
/**
 * Mode de réception commande : livraison ou retrait sur site.
 */

if (!function_exists('commande_mode_livraison_normalize')) {
    function commande_mode_livraison_normalize($mode): string
    {
        return ($mode === 'retrait') ? 'retrait' : 'livraison';
    }
}

if (!function_exists('commande_is_retrait')) {
    function commande_is_retrait(array $commande): bool
    {
        return commande_mode_livraison_normalize($commande['mode_livraison'] ?? 'livraison') === 'retrait';
    }
}

if (!function_exists('commande_mode_livraison_label')) {
    function commande_mode_livraison_label($mode): string
    {
        return commande_mode_livraison_normalize($mode) === 'retrait'
            ? 'Retrait sur site'
            : 'Livraison';
    }
}

if (!function_exists('commande_build_retrait_adresse')) {
    /**
     * Adresse textuelle enregistrée pour une commande en retrait boutique.
     */
    function commande_build_retrait_adresse(int $vendeur_id): string
    {
        if ($vendeur_id <= 0) {
            return 'Retrait sur site';
        }
        if (!function_exists('get_admin_by_id')) {
            require_once __DIR__ . '/../models/model_admin.php';
        }
        if (!function_exists('boutique_adresse_publique')) {
            require_once __DIR__ . '/boutique_vendeur_display.php';
        }
        $adm = get_admin_by_id($vendeur_id);
        if (!$adm || !is_array($adm)) {
            return 'Retrait sur site';
        }
        $nom = trim((string) ($adm['boutique_nom'] ?? ''));
        if ($nom === '') {
            $nom = trim((string) ($adm['nom'] ?? '')) ?: 'Boutique';
        }
        $adresse = trim((string) ($adm['boutique_adresse'] ?? ''));
        $region = trim((string) ($adm['boutique_region'] ?? ''));
        $parts = ['Retrait sur site — ' . $nom];
        $ligne = boutique_adresse_publique($adm);
        if ($ligne !== '') {
            $parts[] = $ligne;
        } elseif ($region !== '') {
            $parts[] = $region;
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('commande_pickup_boutiques_from_panier')) {
    /**
     * Boutiques concernées par le panier (nom + adresse pour affichage retrait).
     *
     * @return array<int, array{id: int, nom: string, adresse: string, region: string, telephone: string}>
     */
    function commande_pickup_boutiques_from_panier(array $panier_items): array
    {
        if (!function_exists('group_panier_items_by_vendeur')) {
            require_once __DIR__ . '/../models/model_panier.php';
        }
        if (!function_exists('get_admin_by_id')) {
            require_once __DIR__ . '/../models/model_admin.php';
        }
        if (!function_exists('boutique_pickup_info_from_admin')) {
            require_once __DIR__ . '/boutique_vendeur_display.php';
        }
        $out = [];
        foreach (group_panier_items_by_vendeur($panier_items) as $vid => $ginfo) {
            $vid = (int) $vid;
            if ($vid <= 0) {
                continue;
            }
            $adm = get_admin_by_id($vid);
            $pickup = boutique_pickup_info_from_admin($adm && is_array($adm) ? $adm : null, (string) ($ginfo['label'] ?? 'Boutique'));
            $out[] = [
                'id' => $vid,
                'nom' => $pickup['nom'],
                'adresse' => $pickup['adresse'],
                'region' => $pickup['region'],
                'telephone' => $pickup['telephone'],
                'adresse_ligne' => $pickup['adresse_ligne'],
                'lat' => $pickup['lat'],
                'lng' => $pickup['lng'],
                'maps_url' => $pickup['maps_url'],
            ];
        }
        return $out;
    }
}
