<?php
/**
 * Helpers partagés — cartes commande (client + vendeur).
 */

if (!function_exists('commande_suivi_format_phone_display')) {
    require_once __DIR__ . '/commande_suivi_ui.php';
}

if (!function_exists('commande_card_label')) {
    function commande_card_label($statut)
    {
        return match ($statut) {
            'en_attente' => 'En attente',
            'prise_en_charge' => 'Confirm&eacute;e',
            'livraison_en_cours' => 'En livraison',
            'livree' => 'Livr&eacute;e',
            'paye' => 'Re&ccedil;ue',
            'annulee' => 'Annul&eacute;e',
            default => ucfirst(str_replace('_', ' ', (string) $statut)),
        };
    }
}

if (!function_exists('commande_card_badge_class')) {
    function commande_card_badge_class($statut)
    {
        return match ($statut) {
            'en_attente' => 'ub--wait',
            'prise_en_charge' => 'ub--confirm',
            'livraison_en_cours' => 'ub--delivery',
            'livree', 'paye' => 'ub--done',
            'annulee' => 'ub--cancel',
            default => 'ub--wait',
        };
    }
}

if (!function_exists('commande_card_icon')) {
    function commande_card_icon($statut)
    {
        return match ($statut) {
            'en_attente' => 'fa-clock',
            'prise_en_charge' => 'fa-box-open',
            'livraison_en_cours' => 'fa-truck',
            'livree', 'paye' => 'fa-circle-check',
            'annulee' => 'fa-ban',
            default => 'fa-clock',
        };
    }
}

if (!function_exists('commande_card_timeline_steps')) {
    function commande_card_timeline_steps($statut)
    {
        $steps = [
            ['key' => 'en_attente', 'label' => 'Re&ccedil;ue', 'icon' => 'fa-circle-dot'],
            ['key' => 'prise_en_charge', 'label' => 'Confirm&eacute;e', 'icon' => 'fa-box-open'],
            ['key' => 'livraison_en_cours', 'label' => 'En livraison', 'icon' => 'fa-truck'],
            ['key' => 'livree', 'label' => 'Livr&eacute;e', 'icon' => 'fa-circle-check'],
        ];

        if ($statut === 'annulee') {
            return null;
        }

        if (in_array($statut, ['livree', 'paye'], true)) {
            $result = [];
            foreach ($steps as $step) {
                $result[] = $step + ['state' => 'done'];
            }
            return $result;
        }

        $order = [
            'en_attente' => 0,
            'prise_en_charge' => 1,
            'livraison_en_cours' => 2,
            'livree' => 3,
            'paye' => 3,
        ];
        $cur = $order[$statut] ?? -1;
        $result = [];

        foreach ($steps as $i => $step) {
            if ($i < $cur) {
                $result[] = $step + ['state' => 'done'];
            } elseif ($i === $cur) {
                $result[] = $step + ['state' => 'current'];
            } else {
                $result[] = $step + ['state' => 'pending'];
            }
        }

        return $result;
    }
}

if (!function_exists('commande_card_client_nom')) {
    function commande_card_client_nom(array $commande)
    {
        $nom = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''));
        return $nom !== '' ? $nom : 'Client inconnu';
    }
}

if (!function_exists('commande_card_telephone')) {
    function commande_card_telephone(array $commande, $context = 'vendor')
    {
        if ($context === 'client') {
            return '';
        }

        $tel = trim((string) ($commande['user_telephone'] ?? ''));
        if ($tel === '') {
            $tel = trim((string) ($commande['telephone_livraison'] ?? ''));
        }
        if ($tel === '') {
            $tel = trim((string) ($commande['client_telephone'] ?? ''));
        }

        if ($tel !== '' && function_exists('commande_suivi_format_phone_display')) {
            return commande_suivi_format_phone_display($tel);
        }

        return $tel;
    }
}

if (!function_exists('commande_carte_galerie_data')) {
    /**
     * Vignette + galerie du premier produit d'une commande (carte client / vendeur).
     *
     * @return array{thumb: string, images: array<int, string>, nom: string}
     */
    function commande_carte_galerie_data(int $commande_id): array
    {
        static $cache = [];
        if ($commande_id <= 0) {
            return ['thumb' => '', 'images' => [], 'nom' => ''];
        }
        if (isset($cache[$commande_id])) {
            return $cache[$commande_id];
        }
        if (!function_exists('get_commande_produits')) {
            require_once __DIR__ . '/../models/model_commandes.php';
        }
        $empty = ['thumb' => '', 'images' => [], 'nom' => ''];
        $lines = get_commande_produits($commande_id);
        if (empty($lines)) {
            $cache[$commande_id] = $empty;
            return $empty;
        }
        $first = $lines[0];
        $nom = trim((string) ($first['nom'] ?? ''));
        $images = [];
        $pid = (int) ($first['produit_id'] ?? 0);
        if ($pid > 0) {
            if (!function_exists('get_produit_by_id')) {
                require_once __DIR__ . '/../models/model_produits.php';
            }
            $produit_row = get_produit_by_id($pid);
            if ($produit_row && function_exists('produit_images_list_from_row')) {
                $images = produit_images_list_from_row($produit_row);
            }
        }
        $line_img = trim((string) ($first['image_afficher'] ?? ''));
        if ($line_img === '') {
            $line_img = trim((string) ($first['image_principale'] ?? ''));
        }
        if ($line_img !== '' && !in_array($line_img, $images, true)) {
            array_unshift($images, $line_img);
        }
        $thumb = $line_img !== '' ? $line_img : ($images[0] ?? '');
        $cache[$commande_id] = [
            'thumb' => $thumb,
            'images' => $images,
            'nom' => $nom,
        ];
        return $cache[$commande_id];
    }
}

if (!function_exists('commande_carte_galerie_urls')) {
    /**
     * URLs web des images galerie pour une commande.
     *
     * @return array{urls: array<int, string>, nom: string, thumb_url: string}
     */
    function commande_carte_galerie_urls(int $commande_id, string $fallback_nom = 'Produit'): array
    {
        if (!function_exists('upload_image_url')) {
            require_once __DIR__ . '/image_optimizer.php';
        }
        $galerie = commande_carte_galerie_data($commande_id);
        $urls = [];
        foreach ($galerie['images'] as $img_rel) {
            $img_rel = trim(str_replace('\\', '/', (string) $img_rel), '/');
            if ($img_rel === '' || strpos($img_rel, '..') !== false) {
                continue;
            }
            $urls[] = upload_image_url($img_rel, 'original');
        }
        if (empty($urls) && !empty($galerie['thumb'])) {
            $thumb_rel = trim(str_replace('\\', '/', (string) $galerie['thumb']), '/');
            if ($thumb_rel !== '' && strpos($thumb_rel, '..') === false) {
                $urls[] = upload_image_url($thumb_rel, 'md');
            }
        }
        $nom = $galerie['nom'] !== '' ? $galerie['nom'] : $fallback_nom;
        $thumb_url = !empty($urls) ? $urls[0] : '/image/produit1.jpg';
        return ['urls' => $urls, 'nom' => $nom, 'thumb_url' => $thumb_url];
    }
}
