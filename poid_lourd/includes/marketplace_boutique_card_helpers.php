<?php
/**
 * Helpers affichage cartes boutique marketplace.
 */

if (!function_exists('marketplace_boutique_has_logo')) {
    function marketplace_boutique_has_logo(array $boutique): bool
    {
        return trim((string) ($boutique['boutique_logo'] ?? '')) !== '';
    }
}

if (!function_exists('marketplace_boutiques_produits_vignettes')) {
    /**
     * Vignettes produits publiés par boutique (max N par vendeur).
     *
     * @param array<int, int|string> $admin_ids
     * @return array<int, array<int, array{id:int,nom:string,image_url:string,href:string}>>
     */
    function marketplace_boutiques_produits_vignettes(array $admin_ids, int $limit_per = 10): array
    {
        global $db;

        $admin_ids = array_values(array_unique(array_filter(array_map('intval', $admin_ids), function ($id) {
            return $id > 0;
        })));
        if ($admin_ids === [] || !isset($db) || !($db instanceof PDO)) {
            return [];
        }

        $limit_per = max(1, min($limit_per, 10));
        $placeholders = implode(',', array_fill(0, count($admin_ids), '?'));

        try {
            $stmt = $db->prepare("
                SELECT p.id, p.admin_id, p.image_principale, p.nom
                FROM produits p
                WHERE p.admin_id IN ($placeholders)
                AND p.statut IN ('actif', 'rupture_stock')
                AND TRIM(COALESCE(p.image_principale, '')) <> ''
                ORDER BY p.admin_id ASC, p.date_creation DESC
            ");
            $stmt->execute($admin_ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return [];
            }

            if (!function_exists('upload_image_url')) {
                require_once dirname(__DIR__) . '/includes/image_optimizer.php';
            }

            $out = [];
            foreach ($rows as $row) {
                $aid = (int) ($row['admin_id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                if (!isset($out[$aid])) {
                    $out[$aid] = [];
                }
                if (count($out[$aid]) >= $limit_per) {
                    continue;
                }
                $pid = (int) ($row['id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $out[$aid][] = [
                    'id' => $pid,
                    'nom' => trim((string) ($row['nom'] ?? 'Produit')),
                    'image_url' => upload_image_url((string) ($row['image_principale'] ?? ''), 'sm'),
                    'href' => 'produit.php?id=' . $pid,
                ];
            }
            return $out;
        } catch (PDOException $e) {
            error_log('[marketplace_boutiques_produits_vignettes] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('marketplace_boutiques_map_deco_random_positions')) {
    /**
     * Positions aléatoires évitant le panneau de recherche (coin haut-gauche desktop, bas mobile).
     *
     * @return array<int, array{left:float,top:float,left_m:float,top_m:float,delay:float}>
     */
    function marketplace_boutiques_map_deco_random_positions(int $count): array
    {
        $count = max(5, $count);
        $zones_desktop = [
            ['left_min' => 78, 'left_max' => 95, 'top_min' => 8, 'top_max' => 52],
            ['left_min' => 78, 'left_max' => 95, 'top_min' => 56, 'top_max' => 88],
            ['left_min' => 54, 'left_max' => 74, 'top_min' => 72, 'top_max' => 90],
            ['left_min' => 16, 'left_max' => 40, 'top_min' => 72, 'top_max' => 90],
            ['left_min' => 82, 'left_max' => 94, 'top_min' => 30, 'top_max' => 48],
            ['left_min' => 58, 'left_max' => 76, 'top_min' => 78, 'top_max' => 92],
        ];
        $zones_mobile = [
            ['left_min' => 8, 'left_max' => 26, 'top_min' => 8, 'top_max' => 36],
            ['left_min' => 30, 'left_max' => 50, 'top_min' => 6, 'top_max' => 34],
            ['left_min' => 54, 'left_max' => 74, 'top_min' => 10, 'top_max' => 38],
            ['left_min' => 76, 'left_max' => 94, 'top_min' => 8, 'top_max' => 36],
            ['left_min' => 40, 'left_max' => 62, 'top_min' => 20, 'top_max' => 40],
            ['left_min' => 18, 'left_max' => 86, 'top_min' => 4, 'top_max' => 30],
        ];

        shuffle($zones_desktop);
        shuffle($zones_mobile);

        $positions = [];
        $min_gap = 7.0;

        for ($i = 0; $i < $count; $i++) {
            $zd = $zones_desktop[$i % count($zones_desktop)];
            $zm = $zones_mobile[$i % count($zones_mobile)];
            $attempts = 0;
            $left = 0.0;
            $top = 0.0;
            $left_m = 0.0;
            $top_m = 0.0;

            do {
                $left = round(mt_rand((int) ($zd['left_min'] * 10), (int) ($zd['left_max'] * 10)) / 10, 1);
                $top = round(mt_rand((int) ($zd['top_min'] * 10), (int) ($zd['top_max'] * 10)) / 10, 1);
                $left_m = round(mt_rand((int) ($zm['left_min'] * 10), (int) ($zm['left_max'] * 10)) / 10, 1);
                $top_m = round(mt_rand((int) ($zm['top_min'] * 10), (int) ($zm['top_max'] * 10)) / 10, 1);
                $ok = true;
                foreach ($positions as $existing) {
                    $dx = $left - (float) $existing['left'];
                    $dy = $top - (float) $existing['top'];
                    if (($dx * $dx + $dy * $dy) < ($min_gap * $min_gap)) {
                        $ok = false;
                        break;
                    }
                }
                $attempts++;
            } while (!$ok && $attempts < 40);

            $positions[] = [
                'left' => $left,
                'top' => $top,
                'left_m' => $left_m,
                'top_m' => $top_m,
                'delay' => round($i * 0.55, 2),
            ];
        }

        return $positions;
    }
}

if (!function_exists('marketplace_boutiques_map_deco_logos')) {
    /**
     * Boutiques aléatoires avec logo pour les marqueurs décoratifs de la carte.
     *
     * @return array<int, array{logo:string,left:float,top:float,left_m:float,top_m:float,delay:float}>
     */
    function marketplace_boutiques_map_deco_logos(int $limit = 5, ?string $country = null): array
    {
        if (!function_exists('marketplace_list_boutiques')) {
            require_once dirname(__DIR__) . '/models/model_boutiques_marketplace.php';
        }
        if (!function_exists('marketplace_boutique_has_logo')) {
            require_once __DIR__ . '/marketplace_boutique_card_helpers.php';
        }

        $limit = max(5, $limit);

        $pool = marketplace_list_boutiques('', 80, 0, $country, null, null, 0, false);
        $pool = array_values(array_filter($pool, 'marketplace_boutique_has_logo'));
        if ($pool === []) {
            return [];
        }
        shuffle($pool);
        $picked = array_slice($pool, 0, $limit);
        $positions = marketplace_boutiques_map_deco_random_positions(count($picked));

        $out = [];
        foreach ($picked as $i => $boutique) {
            $logo = marketplace_boutique_logo_url($boutique);
            if ($logo === '') {
                continue;
            }
            $pos = $positions[$i] ?? $positions[0];
            $out[] = [
                'logo' => $logo,
                'left' => (float) $pos['left'],
                'top' => (float) $pos['top'],
                'left_m' => (float) $pos['left_m'],
                'top_m' => (float) $pos['top_m'],
                'delay' => (float) $pos['delay'],
            ];
        }
        return $out;
    }
}

if (!function_exists('marketplace_boutique_public_url')) {
    function marketplace_boutique_public_url(string $slug): string
    {
        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }
        if (!function_exists('boutique_url')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }
        $slug = trim($slug);
        if ($slug === '') {
            return '/boutiques.php';
        }
        return rtrim(get_site_base_url(), '/') . boutique_url('index.php', $slug);
    }
}

if (!function_exists('marketplace_boutique_share_payload')) {
    /**
     * Données partage vitrine — même logique que vendeur_share_boutique_get_data().
     *
     * @return array{url:string,subject:string,message:string,modal_title:string,hint:string}
     */
    function marketplace_boutique_share_payload(string $slug, string $nom): array
    {
        $nom = trim($nom);
        if ($nom === '') {
            $nom = 'Boutique';
        }
        $url = marketplace_boutique_public_url($slug);
        $subject = 'Découvrez la boutique « ' . $nom . ' » sur COLObanes';

        return [
            'url' => $url,
            'subject' => $subject,
            'message' => $subject . ' : ' . $url,
            'modal_title' => 'Partager cette boutique',
            'hint' => 'Le lien ouvre la boutique publique sur COLObanes.',
        ];
    }
}

if (!function_exists('marketplace_boutique_card_theme')) {
    /**
     * @return array{main:string,dark:string,accent:string,has_custom:bool}
     */
    function marketplace_boutique_card_theme(array $boutique): array
    {
        if (!function_exists('boutique_normalize_hex_color')) {
            require_once __DIR__ . '/boutique_vendeur_display.php';
        }
        $main = boutique_normalize_hex_color($boutique['boutique_couleur_principale'] ?? '');
        $accent = boutique_normalize_hex_color($boutique['boutique_couleur_accent'] ?? '');
        $main_hex = $main !== '' ? $main : '#3564a6';
        $accent_hex = $accent !== '' ? $accent : '#2d5690';
        return [
            'main' => $main_hex,
            'dark' => '#0d0d0d',
            'accent' => $accent !== '' ? $accent : '#ff6b35',
            'band' => $accent !== '' ? $accent_hex : '#2d5690',
            'has_custom' => ($main !== '' || $accent !== ''),
        ];
    }
}

if (!function_exists('marketplace_boutique_logo_url')) {
    function marketplace_boutique_logo_url(array $boutique): string
    {
        $logo_rel = trim((string) ($boutique['boutique_logo'] ?? ''));
        if ($logo_rel === '') {
            return '';
        }
        return '/upload/' . str_replace('\\', '/', $logo_rel);
    }
}

if (!function_exists('marketplace_boutique_prepare_card')) {
    /**
     * @return array<string, mixed>|null
     */
    function marketplace_boutique_prepare_card(array $boutique): ?array
    {
        if (!function_exists('boutique_vitrine_entry_href')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }
        if (!function_exists('boutique_pickup_info_from_admin')) {
            require_once __DIR__ . '/boutique_vendeur_display.php';
        }

        $slug = trim((string) ($boutique['boutique_slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        $nom = trim((string) ($boutique['boutique_nom'] ?? ''));
        if ($nom === '') {
            $nom = 'Boutique';
        }

        $theme = marketplace_boutique_card_theme($boutique);
        $pickup = boutique_pickup_info_from_admin($boutique, $nom);
        $share = marketplace_boutique_share_payload($slug, $nom);

        if (!function_exists('geo_coords_valid')) {
            require_once __DIR__ . '/geo_location_service.php';
        }

        $has_geo = $pickup['lat'] !== null
            && $pickup['lng'] !== null
            && geo_coords_valid((float) $pickup['lat'], (float) $pickup['lng']);

        $maps_dir_url = '';
        $geo_share_url = '';
        if ($has_geo) {
            $maps_dir_url = geo_nav_google_maps_dir((float) $pickup['lat'], (float) $pickup['lng']);
            $geo_share_url = 'https://maps.google.com/?q=' . $pickup['lat'] . ',' . $pickup['lng'];
        }

        $nb_produits = (int) ($boutique['nb_produits'] ?? 0);
        $produits_vignettes = [];
        if (!empty($boutique['_produits_vignettes']) && is_array($boutique['_produits_vignettes'])) {
            $produits_vignettes = array_slice($boutique['_produits_vignettes'], 0, 10);
        }

        return [
            'id' => (int) ($boutique['id'] ?? 0),
            'nom' => $nom,
            'slug' => $slug,
            'region' => trim((string) ($boutique['boutique_region'] ?? '')),
            'adresse' => $pickup['adresse_ligne'],
            'telephone' => trim((string) ($boutique['telephone'] ?? '')),
            'logo_url' => marketplace_boutique_logo_url($boutique),
            'vitrine_href' => boutique_vitrine_entry_href($slug),
            'maps_url' => $maps_dir_url,
            'share_url' => $share['url'],
            'share_title' => $share['subject'],
            'share_text' => $share['message'],
            'share_modal_title' => $share['modal_title'],
            'share_hint' => $share['hint'],
            'geo_share_url' => $geo_share_url,
            'geo_share_title' => 'Point de retrait — ' . $nom,
            'lat' => $has_geo ? (float) $pickup['lat'] : null,
            'lng' => $has_geo ? (float) $pickup['lng'] : null,
            'has_geo' => $has_geo,
            'nb_produits' => $nb_produits,
            'produits_vignettes' => $produits_vignettes,
            'distance_km' => isset($boutique['distance_km']) ? (float) $boutique['distance_km'] : null,
            'theme' => $theme,
        ];
    }
}

if (!function_exists('marketplace_boutiques_map_payload')) {
    /**
     * @param array<int, array<string, mixed>> $boutiques
     * @return array<int, array<string, mixed>>
     */
    function marketplace_boutiques_map_payload(array $boutiques): array
    {
        if (!function_exists('geo_coords_valid')) {
            require_once __DIR__ . '/geo_location_service.php';
        }

        $out = [];
        foreach ($boutiques as $boutique) {
            if (!is_array($boutique)) {
                continue;
            }
            $card = marketplace_boutique_prepare_card($boutique);
            if ($card === null || $card['lat'] === null || $card['lng'] === null) {
                continue;
            }
            if (!geo_coords_valid((float) $card['lat'], (float) $card['lng'])) {
                continue;
            }
            $out[] = [
                'id' => $card['id'],
                'nom' => $card['nom'],
                'slug' => $card['slug'],
                'logo' => $card['logo_url'],
                'lat' => (float) $card['lat'],
                'lng' => (float) $card['lng'],
                'distance_km' => $card['distance_km'],
                'type_id' => isset($boutique['boutique_type_id']) ? (int) $boutique['boutique_type_id'] : 0,
                'adresse' => $card['adresse'],
                'region' => $card['region'],
                'share_url' => $card['share_url'],
                'share_title' => $card['share_title'],
                'share_text' => $card['share_text'],
                'share_modal_title' => $card['share_modal_title'],
                'share_hint' => $card['share_hint'],
                'geo_share_url' => $card['geo_share_url'],
                'geo_share_title' => $card['geo_share_title'],
                'vitrine_href' => $card['vitrine_href'],
                'maps_url' => $card['maps_url'],
                'theme_main' => $card['theme']['main'],
            ];
        }
        return $out;
    }
}
