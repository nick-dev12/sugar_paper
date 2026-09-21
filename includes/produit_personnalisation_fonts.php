<?php
/**
 * Catalogue de polices pour les modales de personnalisation (Google Fonts).
 */

if (!function_exists('produit_personnalisation_font_catalog')) {

    /**
     * @return list<array{name: string, label: string, group: string, fallback: string, weight: int, google_param: string|null}>
     */
    function produit_personnalisation_font_catalog()
    {
        return [
            // Script & calligraphie
            ['name' => 'Great Vibes', 'label' => 'Great Vibes', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Sacramento', 'label' => 'Sacramento', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Parisienne', 'label' => 'Parisienne', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Alex Brush', 'label' => 'Alex Brush', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Dancing Script', 'label' => 'Dancing Script', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 600, 'google_param' => 'wght@600;700'],
            ['name' => 'Pacifico', 'label' => 'Pacifico', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Satisfy', 'label' => 'Satisfy', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Caveat', 'label' => 'Caveat', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 600, 'google_param' => 'wght@500;600;700'],
            ['name' => 'Lobster', 'label' => 'Lobster', 'group' => 'Script & calligraphie', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],

            // Élégant
            ['name' => 'Playfair Display', 'label' => 'Playfair Display', 'group' => 'Élégant', 'fallback' => 'serif', 'weight' => 600, 'google_param' => 'wght@600;700'],
            ['name' => 'Fraunces', 'label' => 'Fraunces', 'group' => 'Élégant', 'fallback' => 'serif', 'weight' => 600, 'google_param' => 'opsz,wght@9..144,600'],
            ['name' => 'Cormorant Garamond', 'label' => 'Cormorant', 'group' => 'Élégant', 'fallback' => 'serif', 'weight' => 600, 'google_param' => 'wght@500;600;700'],
            ['name' => 'Italiana', 'label' => 'Italiana', 'group' => 'Élégant', 'fallback' => 'serif', 'weight' => 400, 'google_param' => null],

            // Gourmand & fête
            ['name' => 'Cookie', 'label' => 'Cookie', 'group' => 'Gourmand & fête', 'fallback' => 'cursive', 'weight' => 400, 'google_param' => null],
            ['name' => 'Amatic SC', 'label' => 'Amatic SC', 'group' => 'Gourmand & fête', 'fallback' => 'cursive', 'weight' => 700, 'google_param' => 'wght@700'],
            ['name' => 'Bebas Neue', 'label' => 'Bebas Neue', 'group' => 'Gourmand & fête', 'fallback' => 'sans-serif', 'weight' => 400, 'google_param' => null],

            // Moderne (lisible)
            ['name' => 'Outfit', 'label' => 'Outfit', 'group' => 'Moderne', 'fallback' => 'sans-serif', 'weight' => 600, 'google_param' => 'wght@500;600'],
            ['name' => 'Montserrat', 'label' => 'Montserrat', 'group' => 'Moderne', 'fallback' => 'sans-serif', 'weight' => 600, 'google_param' => 'wght@500;600;700'],
        ];
    }

    /**
     * @return list<string>
     */
    function produit_personnalisation_allowed_fonts()
    {
        $names = [];
        foreach (produit_personnalisation_font_catalog() as $font) {
            $names[] = $font['name'];
        }
        return $names;
    }

    function produit_personnalisation_fonts_google_css_url()
    {
        $parts = [];
        foreach (produit_personnalisation_font_catalog() as $font) {
            $slug = str_replace(' ', '+', $font['name']);
            if (!empty($font['google_param'])) {
                $parts[] = 'family=' . $slug . ':' . $font['google_param'];
            } else {
                $parts[] = 'family=' . $slug;
            }
        }

        return 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
    }

    function produit_personnalisation_fonts_enqueue_link()
    {
        if (defined('PERSONNALISATION_EDITOR_FONTS_LOADED')) {
            return;
        }
        define('PERSONNALISATION_EDITOR_FONTS_LOADED', true);
        $url = produit_personnalisation_fonts_google_css_url();
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    /**
     * @param string $active_font
     */
    function produit_personnalisation_render_font_picker($active_font = 'Outfit')
    {
        produit_personnalisation_fonts_enqueue_link();
        $active_font = trim((string) $active_font);
        if ($active_font === '' || !in_array($active_font, produit_personnalisation_allowed_fonts(), true)) {
            $active_font = 'Outfit';
        }

        $current_group = null;
        echo '<div class="perso-font-picker" role="group" aria-label="Choisir une police">' . "\n";
        foreach (produit_personnalisation_font_catalog() as $font) {
            if ($current_group !== $font['group']) {
                if ($current_group !== null) {
                    echo "</div>\n";
                }
                $current_group = $font['group'];
                echo '<div class="perso-font-group">' . "\n";
                echo '<span class="perso-font-group-label">' . htmlspecialchars($font['group'], ENT_QUOTES, 'UTF-8') . '</span>' . "\n";
            }

            $is_active = ($font['name'] === $active_font);
            $name_attr = htmlspecialchars($font['name'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($font['label'], ENT_QUOTES, 'UTF-8');
            $fallback = htmlspecialchars($font['fallback'], ENT_QUOTES, 'UTF-8');
            $weight = (int) $font['weight'];
            $active_class = $is_active ? ' is-active' : '';

            echo '<button type="button" class="perso-font-btn' . $active_class . '"'
                . ' data-font="' . $name_attr . '"'
                . ' data-font-weight="' . $weight . '"'
                . ' style="font-family:\'' . $name_attr . '\',' . $fallback . '">'
                . $label
                . '</button>' . "\n";
        }
        if ($current_group !== null) {
            echo "</div>\n";
        }
        echo "</div>\n";
    }
}
