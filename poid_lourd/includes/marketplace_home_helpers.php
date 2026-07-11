<?php
/**
 * Tirage aléatoire sur une liste de produits « qualifiés », ou catalogue complet
 * si le volume ne dépasse pas le seuil (règle métier : > 5 produits = garde le mode « intelligent »).
 *
 * @param array $produits_source Produits ciblés (recherches, ventes, favoris, etc.)
 * @param int   $limite         Nombre max à retourner
 * @param int   $seuil          Tant que count > seuil, on mélange la source (sinon fallback catalogue)
 * @return array
 */
function marketplace_produits_aleatoires_avec_seuil($produits_source, $limite, $seuil = 5)
{
    $produits_source = is_array($produits_source) ? array_values($produits_source) : [];
    require_once __DIR__ . '/catalogue_shuffle.php';
    if (count($produits_source) > $seuil) {
        $produits_source = catalogue_melanger_produits($produits_source);
        return array_slice($produits_source, 0, $limite);
    }
    if (!function_exists('get_all_produits_paginated')) {
        require_once __DIR__ . '/../models/model_produits.php';
    }
    $seed = catalogue_nouveau_seed('marketplace_fallback');
    $tous = get_all_produits_paginated(0, max(200, $limite * 6), null, $seed);
    if (empty($tous)) {
        return [];
    }
    return array_slice($tous, 0, $limite);
}

/**
 * Retourne les produits dans l'ordre fourni (sans mélange), dédoublonnés par id.
 * Si la source est vide, tirage aléatoire sur le catalogue (sans doublon).
 *
 * @param array  $produits_source Liste ordonnée (recherches, notes, etc.)
 * @param int    $limite          Nombre max à retourner
 * @param string $contexte_fallback Contexte de graine pour le fallback aléatoire
 * @param bool   $fallback_aleatoire Si false et source vide, retourne []
 * @return array
 */
function marketplace_produits_ordre_ou_aleatoire($produits_source, $limite, $contexte_fallback = 'marketplace_fallback', $fallback_aleatoire = true)
{
    $limite = max(1, (int) $limite);
    $produits_source = is_array($produits_source) ? array_values($produits_source) : [];
    if (!empty($produits_source)) {
        $out = [];
        $seen = [];
        foreach ($produits_source as $p) {
            if (!is_array($p)) {
                continue;
            }
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $p;
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }
    if (!$fallback_aleatoire) {
        return [];
    }
    return marketplace_produits_aleatoires_avec_seuil([], $limite, 0);
}
