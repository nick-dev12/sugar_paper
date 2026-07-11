<?php
/**
 * Branding facture : vendeur boutique ou défaut plateforme COLObanes
 */

if (!function_exists('facture_default_branding')) {
    function facture_default_branding()
    {
        return [
            'entreprise_nom' => 'COLObanes',
            'entreprise_rc' => 'SN.DKR.2022.A.702',
            'entreprise_ninea' => '009116684',
            'entreprise_adresse' => 'Rond point ZAC MBAO, Dakar',
            'entreprise_tel1' => '338700070',
            'entreprise_tel2' => '',
            'entreprise_site' => 'https://www.colobanes.sn',
            'entreprise_email' => 'contact@colobanes.sn',
            'facture_logo_url' => '/image/logo_market.png',
            'facture_couleur_principale' => '#3564a6',
            'facture_couleur_accent' => '#ff6b35',
            'facture_branding_vendeur' => false,
        ];
    }
}

if (!function_exists('facture_format_tel_display')) {
    function facture_format_tel_display($tel)
    {
        $digits = preg_replace('/\D/', '', (string) $tel);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 12 && substr($digits, 0, 3) === '221') {
            return substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            return $digits;
        }
        return $digits;
    }
}

if (!function_exists('facture_resolve_branding_from_commande')) {
    /**
     * @param array|null $commande
     * @return array<string, mixed>
     */
    function facture_resolve_branding_from_commande($commande)
    {
        $branding = facture_default_branding();

        if (!is_array($commande)) {
            return $branding;
        }

        $vendeur_id = (int) ($commande['vendeur_id'] ?? 0);
        if ($vendeur_id <= 0) {
            return $branding;
        }

        if (!function_exists('get_admin_by_id')) {
            require_once __DIR__ . '/../models/model_admin.php';
        }
        if (!function_exists('boutique_vendeur_display_from_row')) {
            require_once __DIR__ . '/boutique_vendeur_display.php';
        }
        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }
        if (!function_exists('boutique_url')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }

        $admin = get_admin_by_id($vendeur_id);
        if (!$admin || (($admin['role'] ?? '') !== 'vendeur')) {
            return $branding;
        }

        $display = boutique_vendeur_display_from_row($admin);

        $nom = $display['boutique_nom'];
        if ($nom === '') {
            $nom = trim($display['prenom'] . ' ' . $display['nom']);
        }
        if ($nom === '') {
            $nom = 'Ma boutique';
        }

        $logo = '/image/logo_market.png';
        if ($display['boutique_logo'] !== '') {
            $logo = '/upload/' . str_replace('\\', '/', $display['boutique_logo']);
        }

        $c1 = boutique_normalize_hex_color($display['boutique_couleur_principale'] ?? '');
        if ($c1 === '') {
            $c1 = '#3564a6';
        }
        $c2 = boutique_normalize_hex_color($display['boutique_couleur_accent'] ?? '');
        if ($c2 === '') {
            $c2 = '#ff6b35';
        }

        $site = rtrim(get_site_base_url(), '/');
        $slug = trim((string) ($admin['boutique_slug'] ?? ''));
        if ($slug !== '') {
            $site = $site . boutique_url('index.php', $slug);
        } else {
            $site = 'https://www.colobanes.sn';
        }

        $adresse = $display['boutique_adresse'];
        if ($adresse === '' && function_exists('senegal_region_label')) {
            require_once __DIR__ . '/senegal_regions.php';
            $region = trim((string) ($admin['boutique_region'] ?? ''));
            if ($region !== '') {
                $adresse = senegal_region_label($region);
            }
        }

        $tel = facture_format_tel_display($display['telephone']);
        $email = $display['email'] !== '' ? $display['email'] : $branding['entreprise_email'];

        $branding['entreprise_nom'] = $nom;
        $branding['entreprise_rc'] = '';
        $branding['entreprise_ninea'] = '';
        $branding['entreprise_adresse'] = $adresse;
        $branding['entreprise_tel1'] = $tel;
        $branding['entreprise_tel2'] = '';
        $branding['entreprise_site'] = $site;
        $branding['entreprise_email'] = $email;
        $branding['facture_logo_url'] = $logo;
        $branding['facture_couleur_principale'] = $c1;
        $branding['facture_couleur_accent'] = $c2;
        $branding['facture_branding_vendeur'] = true;

        return $branding;
    }
}