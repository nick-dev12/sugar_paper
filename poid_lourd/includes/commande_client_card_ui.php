<?php
/**
 * Helpers UI cartes commande client (mes-commandes, mon-compte).
 */

if (!function_exists('uc_boutique_info_commande')) {
    function uc_boutique_info_commande(array $commande)
    {
        static $cache = [];
        $vid = (int) ($commande['vendeur_id'] ?? 0);
        if ($vid <= 0) {
            return ['nom' => 'Boutique', 'telephone' => ''];
        }
        if (!isset($cache[$vid])) {
            require_once __DIR__ . '/../models/model_admin.php';
            $admin = get_admin_by_id($vid);
            if (!is_array($admin)) {
                $cache[$vid] = ['nom' => 'Boutique', 'telephone' => ''];
            } else {
                $nom = trim((string) ($admin['boutique_nom'] ?? ''));
                $cache[$vid] = [
                    'nom' => $nom !== '' ? $nom : 'Boutique',
                    'telephone' => trim((string) ($admin['telephone'] ?? '')),
                ];
            }
        }
        return $cache[$vid];
    }
}

if (!function_exists('cmd_user_label')) {
    function cmd_user_label($s)
    {
        return match ($s) {
            'en_attente' => 'En attente',
            'prise_en_charge' => 'Confirm&eacute;e',
            'livraison_en_cours' => 'En livraison',
            'livree' => 'Livr&eacute;e',
            'paye' => 'Re&ccedil;ue',
            'annulee' => 'Annul&eacute;e',
            default => ucfirst(str_replace('_', ' ', (string) $s)),
        };
    }
}

if (!function_exists('cmd_user_badge')) {
    function cmd_user_badge($s)
    {
        return match ($s) {
            'en_attente' => 'ub--wait',
            'prise_en_charge' => 'ub--confirm',
            'livraison_en_cours' => 'ub--delivery',
            'livree', 'paye' => 'ub--done',
            'annulee' => 'ub--cancel',
            default => 'ub--wait',
        };
    }
}

if (!function_exists('cmd_user_icon')) {
    function cmd_user_icon($s)
    {
        return match ($s) {
            'en_attente' => 'fa-clock',
            'prise_en_charge' => 'fa-box-open',
            'livraison_en_cours' => 'fa-truck',
            'livree', 'paye' => 'fa-circle-check',
            'annulee' => 'fa-ban',
            default => 'fa-clock',
        };
    }
}

if (!function_exists('cmd_timeline_steps')) {
    function cmd_timeline_steps($statut)
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
            foreach ($steps as $s) {
                $result[] = $s + ['state' => 'done'];
            }
            return $result;
        }
        $order = ['en_attente' => 0, 'prise_en_charge' => 1, 'livraison_en_cours' => 2, 'livree' => 3, 'paye' => 3];
        $cur = $order[$statut] ?? -1;
        $result = [];
        foreach ($steps as $i => $s) {
            if ($i < $cur) {
                $result[] = $s + ['state' => 'done'];
            } elseif ($i === $cur) {
                $result[] = $s + ['state' => 'current'];
            } else {
                $result[] = $s + ['state' => 'pending'];
            }
        }
        return $result;
    }
}

if (!function_exists('client_commande_card_render')) {
    /**
     * @param array $commande
     * @param array $ctx interactive_actions, form_action, commandes_avis_stats, commandes_noter_pending
     */
    function client_commande_card_render(array $commande, array $ctx = [])
    {
        require_once __DIR__ . '/commande_card_helpers.php';
        require_once __DIR__ . '/../models/model_admin.php';
        require_once __DIR__ . '/boutique_vendeur_display.php';

        $ctx = array_merge([
            'interactive_actions' => false,
            'form_action' => '',
            'commandes_avis_stats' => [],
            'commandes_noter_pending' => [],
            'show_footer' => true,
            'link_to_tracking' => false,
        ], $ctx);

        $st = $commande['statut'] ?? 'en_attente';
        $is_urgent = $st === 'en_attente';
        $timeline = cmd_timeline_steps($st);
        $boutique_info = uc_boutique_info_commande($commande);
        $boutique_nom = $boutique_info['nom'];
        $boutique_tel = $boutique_info['telephone'];
        $date_cmd = !empty($commande['date_commande'])
            ? date('d/m/Y', strtotime((string) $commande['date_commande']))
            : '&mdash;';
        $cmd_id = (int) ($commande['id'] ?? 0);
        $can_cancel = $ctx['interactive_actions']
            && in_array($st, ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation'], true);
        $can_confirm = $ctx['interactive_actions'] && $st === 'livraison_en_cours';
        $can_reorder = $ctx['interactive_actions'] && $st === 'annulee';
        $cmd_avis = ($cmd_id > 0 && isset($ctx['commandes_avis_stats'][$cmd_id]))
            ? $ctx['commandes_avis_stats'][$cmd_id]
            : ['moyenne' => 0.0, 'count' => 0];
        $can_noter = $ctx['interactive_actions']
            && in_array($st, ['livree', 'paye'], true)
            && $cmd_id > 0
            && !empty($ctx['commandes_noter_pending'][$cmd_id]);

        $boutique_maps_url = '';
        $boutique_geo_lat = null;
        $boutique_geo_lng = null;
        $boutique_geo_label = '';
        $boutique_geo_share_url = '';
        $vendeur_boutique_id = (int) ($commande['vendeur_id'] ?? 0);
        if ($vendeur_boutique_id > 0) {
            $adm_boutique = get_admin_by_id($vendeur_boutique_id);
            $boutique_geo = boutique_pickup_info_from_admin(
                $adm_boutique && is_array($adm_boutique) ? $adm_boutique : null,
                $boutique_nom
            );
            $boutique_maps_url = trim((string) ($boutique_geo['maps_url'] ?? ''));
            $boutique_geo_lat = $boutique_geo['lat'] ?? null;
            $boutique_geo_lng = $boutique_geo['lng'] ?? null;
            $boutique_geo_label = 'Point de retrait — ' . $boutique_nom;
            if ($boutique_geo_lat !== null && $boutique_geo_lng !== null) {
                $boutique_geo_share_url = 'https://maps.google.com/?q=' . $boutique_geo_lat . ',' . $boutique_geo_lng;
            } elseif ($boutique_maps_url !== '') {
                $boutique_geo_share_url = $boutique_maps_url;
            }
        }

        $galerie_pack = commande_carte_galerie_urls($cmd_id, $boutique_nom);
        $cmd_galerie_urls = $galerie_pack['urls'];
        $cmd_galerie_nom = $galerie_pack['nom'];
        $cmd_thumb_src = $galerie_pack['thumb_url'];
        $card_form_action = (string) $ctx['form_action'];
        $show_footer = !empty($ctx['show_footer']);
        $link_to_tracking = !empty($ctx['link_to_tracking']) && $cmd_id > 0;
        $suivi_href = $link_to_tracking
            ? 'commande-categorie.php?commande_id=' . $cmd_id
            : '';

        include __DIR__ . '/partials/commande_card_client_full.php';
    }
}
