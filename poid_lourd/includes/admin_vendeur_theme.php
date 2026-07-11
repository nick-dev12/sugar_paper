<?php
/**
 * Surcharge CSS des couleurs vendeur dans l'espace admin (vitrine + back-office).
 */

require_once __DIR__ . '/boutique_vendeur_display.php';

if (!function_exists('admin_vendeur_theme_colors_from_session')) {
    /**
     * @return array{c1: string, c2: string}
     */
    function admin_vendeur_theme_colors_from_session() {
        $empty = ['c1' => '', 'c2' => ''];
        if (!isset($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] <= 0) {
            return $empty;
        }
        if (($_SESSION['admin_role'] ?? '') !== 'vendeur') {
            return $empty;
        }

        $c1 = '';
        $c2 = '';
        if (!empty($_SESSION['admin_boutique_couleur_principale'])) {
            $c1 = boutique_normalize_hex_color($_SESSION['admin_boutique_couleur_principale']);
        }
        if (!empty($_SESSION['admin_boutique_couleur_accent'])) {
            $c2 = boutique_normalize_hex_color($_SESSION['admin_boutique_couleur_accent']);
        }

        if ($c1 === '' || $c2 === '') {
            if (!function_exists('get_admin_by_id')) {
                require_once __DIR__ . '/../models/model_admin.php';
            }
            $admin = get_admin_by_id((int) $_SESSION['admin_id']);
            if ($admin && ($admin['role'] ?? '') === 'vendeur') {
                if ($c1 === '') {
                    $c1 = boutique_normalize_hex_color($admin['boutique_couleur_principale'] ?? '');
                }
                if ($c2 === '') {
                    $c2 = boutique_normalize_hex_color($admin['boutique_couleur_accent'] ?? '');
                }
                $_SESSION['admin_boutique_couleur_principale'] = $c1;
                $_SESSION['admin_boutique_couleur_accent'] = $c2;
            }
        }

        return ['c1' => $c1, 'c2' => $c2];
    }
}

if (!function_exists('admin_vendeur_theme_sync_session')) {
    /**
     * Met à jour la session après enregistrement des couleurs vitrine.
     *
     * @param array $admin Ligne admin (vendeur)
     */
    function admin_vendeur_theme_sync_session(array $admin) {
        if (($admin['role'] ?? '') !== 'vendeur') {
            return;
        }
        $_SESSION['admin_boutique_couleur_principale'] = boutique_normalize_hex_color($admin['boutique_couleur_principale'] ?? '');
        $_SESSION['admin_boutique_couleur_accent'] = boutique_normalize_hex_color($admin['boutique_couleur_accent'] ?? '');
    }
}

if (!function_exists('admin_echo_vendeur_theme_style_override')) {
    /**
     * À appeler dans l'espace admin vendeur (ex. via nav.php), après variables.css / admin-dashboard.css.
     */
    function admin_echo_vendeur_theme_style_override() {
        $colors = admin_vendeur_theme_colors_from_session();
        $c1 = $colors['c1'];
        $c2 = $colors['c2'];
        if ($c1 === '' && $c2 === '') {
            return;
        }

        echo '<style id="admin-vendeur-theme">' . "\n";
        echo ":root {\n";
        if ($c1 !== '') {
            echo "  --couleur-dominante: {$c1};\n";
            echo "  --bleu-principal: {$c1};\n";
            echo "  --bleu: {$c1};\n";
            echo "  --boutons-secondaires: {$c1};\n";
            echo "  --couleur-dominante-hover: color-mix(in srgb, {$c1} 78%, black);\n";
            echo "  --bleu-fonce: color-mix(in srgb, {$c1} 62%, black);\n";
            echo "  --bleu-clair: color-mix(in srgb, {$c1} 82%, white);\n";
            echo "  --boutons-secondaires-hover: color-mix(in srgb, {$c1} 78%, black);\n";
            echo "  --admin-nav-icon-1: {$c1};\n";
            echo "  --admin-nav-icon-4: color-mix(in srgb, {$c1} 72%, white);\n";
            echo "  --admin-nav-icon-6: color-mix(in srgb, {$c1} 58%, white);\n";
        }
        if ($c2 !== '') {
            echo "  --accent-promo: {$c2};\n";
            echo "  --orange: {$c2};\n";
            echo "  --orange-clair: color-mix(in srgb, {$c2} 75%, white);\n";
            echo "  --orange-fonce: color-mix(in srgb, {$c2} 82%, black);\n";
            echo "  --admin-nav-icon-3: {$c2};\n";
            echo "  --admin-nav-icon-5: color-mix(in srgb, {$c2} 70%, white);\n";
            echo "  --admin-nav-icon-7: color-mix(in srgb, {$c2} 80%, black);\n";
            echo "  --admin-nav-icon-out: color-mix(in srgb, {$c2} 80%, black);\n";
        }
        echo "}\n";

        if ($c1 !== '' || $c2 !== '') {
            echo "#adminVendeurBottomDock {\n";
            if ($c1 !== '') {
                echo "  --vdock-c1: {$c1};\n";
                echo "  --vdock-c5: {$c1};\n";
            }
            if ($c2 !== '') {
                echo "  --vdock-c3: {$c2};\n";
            }
            echo "}\n";
        }

        $hero_main = $c1 !== '' ? $c1 : '';
        $hero_accent = $c2 !== '' ? $c2 : '';
        if ($hero_main !== '' || $hero_accent !== '') {
            echo ".dash-v2-hero,\n";
            echo ".cmd-v2-hero,\n";
            echo ".stk-hero {\n";
            if ($hero_main !== '') {
                echo "  background: {$hero_main} !important;\n";
                echo "  box-shadow: 0 16px 40px color-mix(in srgb, {$hero_main} 34%, transparent) !important;\n";
            }
            echo "}\n";
            if ($hero_accent !== '') {
                echo ".pbv-hero {\n";
                echo "  background: {$hero_accent} !important;\n";
                echo "  box-shadow: 0 16px 40px color-mix(in srgb, {$hero_accent} 34%, transparent) !important;\n";
                echo "}\n";
            }
        }

        echo "</style>\n";
    }
}
