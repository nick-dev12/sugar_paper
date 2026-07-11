<?php
/**
 * Helpers affichage commandes v2 (cartes, badges)
 */

require_once __DIR__ . '/../../../includes/commande_card_helpers.php';

if (!function_exists('cmd_v2_statut_label')) {
    function cmd_v2_statut_label($statut)
    {
        return commande_card_label($statut);
    }
}

if (!function_exists('cmd_v2_statut_class')) {
    function cmd_v2_statut_class($statut)
    {
        $map = [
            'en_attente' => 'cmd-badge--attente',
            'prise_en_charge' => 'cmd-badge--prise',
            'livraison_en_cours' => 'cmd-badge--livraison',
            'livree' => 'cmd-badge--livree',
            'paye' => 'cmd-badge--paye',
            'annulee' => 'cmd-badge--annulee',
        ];
        return $map[$statut] ?? 'cmd-badge--attente';
    }
}

if (!function_exists('cmd_v2_client_initial')) {
    function cmd_v2_client_initial($client_nom)
    {
        $trim = trim((string) $client_nom);
        if ($trim === '') {
            return '?';
        }
        return function_exists('mb_strtoupper')
            ? mb_strtoupper(mb_substr($trim, 0, 1, 'UTF-8'), 'UTF-8')
            : strtoupper(substr($trim, 0, 1));
    }
}

if (!function_exists('cmd_v2_render_card')) {
    function cmd_v2_render_card(array $commande, array $options = [])
    {
        $card_context = 'vendor';
        $card_title = commande_card_client_nom($commande);
        $card_phone = commande_card_telephone($commande, 'vendor');
        $cmd_id = (int) ($commande['id'] ?? 0);
        $card_track_url = $cmd_id > 0 ? 'details.php?id=' . $cmd_id : '';
        $card_detail_url = $card_track_url;
        $card_show_urgent = ($commande['statut'] ?? '') === 'en_attente';

        require __DIR__ . '/../../../includes/partials/commande_card_uc.php';
    }
}

if (!function_exists('cmd_v2_tri_commandes')) {
    function cmd_v2_tri_commandes(array $commandes, $tri = 'date_desc')
    {
        $allowed = ['date_desc', 'date_asc', 'montant_desc', 'montant_asc'];
        if (!in_array($tri, $allowed, true)) {
            $tri = 'date_desc';
        }

        usort($commandes, function ($a, $b) use ($tri) {
            $da = strtotime($a['date_commande'] ?? 'now');
            $db = strtotime($b['date_commande'] ?? 'now');
            $ma = (float) ($a['montant_total'] ?? 0);
            $mb = (float) ($b['montant_total'] ?? 0);

            switch ($tri) {
                case 'date_asc':
                    return $da <=> $db;
                case 'montant_desc':
                    return $mb <=> $ma ?: $db <=> $da;
                case 'montant_asc':
                    return $ma <=> $mb ?: $db <=> $da;
                default:
                    return $db <=> $da;
            }
        });

        return array_values($commandes);
    }
}

if (!function_exists('cmd_v2_filtre_statut_commandes')) {
    function cmd_v2_filtre_statut_commandes(array $commandes, $filtre_statut)
    {
        if ($filtre_statut === '' || $filtre_statut === 'toutes') {
            return $commandes;
        }

        return array_values(array_filter($commandes, function ($c) use ($filtre_statut) {
            $st = $c['statut'] ?? '';
            if ($filtre_statut === 'vendues') {
                return in_array($st, ['livree', 'paye'], true);
            }
            if ($filtre_statut === 'en_cours') {
                return !in_array($st, ['livree', 'paye', 'annulee'], true);
            }
            if ($filtre_statut === 'annulees') {
                return $st === 'annulee';
            }
            return true;
        }));
    }
}
