<?php
/**
 * Données et helpers pour les modales panier / commande / succès.
 */

require_once __DIR__ . '/modal_json_response.php';
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/panier_invite.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_users.php';
require_once __DIR__ . '/../models/model_zones_livraison.php';
require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/commande_mode_helpers.php';

if (!function_exists('checkout_modals_user_logged_in')) {
    function checkout_modals_user_logged_in()
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }
}

if (!function_exists('checkout_modals_return_url')) {
    function checkout_modals_return_url($fallback = '/index.php')
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $path = parse_url($ref, PHP_URL_PATH);
            $query = parse_url($ref, PHP_URL_QUERY);
            if (is_string($path) && $path !== '' && $path[0] === '/' && strpos($path, '//') === false) {
                $base = basename($path);
                if (!in_array($base, ['panier.php', 'commande.php', 'add-to-panier.php'], true)) {
                    return $path . ($query ? '?' . $query : '');
                }
            }
        }
        return $fallback;
    }
}

if (!function_exists('checkout_modals_append_query')) {
    function checkout_modals_append_query($url, $key, $value)
    {
        $url = (string) $url;
        $parts = parse_url($url);
        $path = (isset($parts['path']) && is_string($parts['path']) && $parts['path'] !== '')
            ? $parts['path']
            : '/index.php';
        $qs = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
        }
        if (!is_array($qs)) {
            $qs = [];
        }
        $qs[(string) $key] = (string) $value;
        $query = http_build_query($qs);
        return $path . ($query !== '' ? '?' . $query : '');
    }
}

/**
 * Après connexion / inscription invité : recharge la page et ouvre le modal panier
 * (le rechargement rattache le token FCM natif iOS/Android sans rebuild de l'app).
 *
 * @param string $url
 * @return string
 */
if (!function_exists('checkout_modals_after_login_url')) {
    function checkout_modals_after_login_url($url)
    {
        $url = (string) $url;
        $parts = parse_url($url);
        $path = (isset($parts['path']) && is_string($parts['path']) && $parts['path'] !== '')
            ? $parts['path']
            : '/index.php';
        $qs = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
        }
        if (!is_array($qs)) {
            $qs = [];
        }
        unset($qs['guest_checkout'], $qs['guest_error'], $qs['open']);
        $qs['open'] = 'panier';
        $qs['notify'] = '1';
        $query = http_build_query($qs);
        return $path . ($query !== '' ? '?' . $query : '');
    }
}

if (!function_exists('checkout_modals_cart_payload')) {
    function checkout_modals_cart_payload($message = '', $message_type = '')
    {
        $panier_items = panier_get_items_courant();
        if (!is_array($panier_items)) {
            $panier_items = [];
        }
        $panier_total = panier_get_total_courant();
        $nombre_total_articles = 0;
        foreach ($panier_items as $item) {
            $nombre_total_articles += (int) ($item['quantite'] ?? 0);
        }

        return [
            'panier_items' => $panier_items,
            'panier_total' => $panier_total,
            'nombre_total_articles' => $nombre_total_articles,
            'user_logged_in' => checkout_modals_user_logged_in(),
            'message' => (string) $message,
            'message_type' => (string) $message_type,
            'count' => $nombre_total_articles,
        ];
    }
}

if (!function_exists('checkout_modals_render_cart')) {
    function checkout_modals_render_cart($message = '', $message_type = '')
    {
        $data = checkout_modals_cart_payload($message, $message_type);
        $panier_items = $data['panier_items'];
        $panier_total = $data['panier_total'];
        $nombre_total_articles = $data['nombre_total_articles'];
        $user_logged_in = $data['user_logged_in'];
        $ckm_message = $data['message'];
        $ckm_message_type = $data['message_type'];
        ob_start();
        include __DIR__ . '/partials/cart_modal_body.php';
        return [
            'html' => (string) ob_get_clean(),
            'count' => $data['count'],
            'empty' => empty($panier_items),
            'logged_in' => $data['user_logged_in'],
        ];
    }
}

if (!function_exists('checkout_modals_checkout_payload')) {
    function checkout_modals_checkout_payload($message = '', $message_type = '')
    {
        $user = false;
        if (checkout_modals_user_logged_in()) {
            $user = get_user_by_id((int) $_SESSION['user_id']);
        }

        $panier_items = panier_get_items_courant();
        $panier_total = panier_get_total_courant();
        $nombre_total_articles = 0;
        foreach ($panier_items as $item) {
            $nombre_total_articles += (int) ($item['quantite'] ?? 0);
        }

        $zones_livraison = get_all_zones_livraison('actif');
        $zone_retrait = zones_livraison_find_retrait($zones_livraison);
        $zones_livraison_delivery = zones_livraison_filter_delivery($zones_livraison, $zone_retrait);
        $commande_mode_selected = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');

        return [
            'user' => $user,
            'panier_items' => $panier_items,
            'panier_total' => $panier_total,
            'nombre_total_articles' => $nombre_total_articles,
            'zones_livraison' => $zones_livraison,
            'zone_retrait' => $zone_retrait,
            'zones_livraison_delivery' => $zones_livraison_delivery,
            'commande_mode_selected' => $commande_mode_selected,
            'message' => (string) $message,
            'message_type' => (string) $message_type,
            'count' => $nombre_total_articles,
        ];
    }
}

if (!function_exists('checkout_modals_render_checkout')) {
    function checkout_modals_render_checkout($message = '', $message_type = '')
    {
        $data = checkout_modals_checkout_payload($message, $message_type);
        if (empty($data['panier_items'])) {
            return checkout_modals_render_cart('Votre panier est vide.', 'error') + [
                'need_cart' => true,
            ];
        }
        if (!checkout_modals_user_logged_in()) {
            return checkout_modals_render_cart('', '') + [
                'need_guest' => true,
            ];
        }

        $user = $data['user'];
        $panier_items = $data['panier_items'];
        $panier_total = $data['panier_total'];
        $nombre_total_articles = $data['nombre_total_articles'];
        $zones_livraison = $data['zones_livraison'];
        $zone_retrait = $data['zone_retrait'];
        $zones_livraison_delivery = $data['zones_livraison_delivery'];
        $commande_mode_selected = $data['commande_mode_selected'];
        $ckm_message = $data['message'];
        $ckm_message_type = $data['message_type'];

        ob_start();
        include __DIR__ . '/partials/checkout_modal_body.php';
        return [
            'html' => (string) ob_get_clean(),
            'count' => $data['count'],
            'panier_total' => (float) $data['panier_total'],
            'logged_in' => true,
        ];
    }
}

if (!function_exists('checkout_modals_success_payload')) {
    function checkout_modals_success_payload($numero_commande)
    {
        $numero_commande = trim((string) $numero_commande);
        $social_config = [];
        if (file_exists(__DIR__ . '/../config/social.php')) {
            $social_config = require __DIR__ . '/../config/social.php';
        }
        $whatsapp_raw = $social_config['whatsapp'] ?? '221773292123';
        $whatsapp_clean = preg_replace('/[^0-9]/', '', (string) $whatsapp_raw);
        if ($whatsapp_clean === '') {
            $whatsapp_clean = '221773292123';
        }
        $whatsapp_display = '+221 77 329 2123';
        if (strlen($whatsapp_clean) >= 12 && strpos($whatsapp_clean, '221') === 0) {
            $whatsapp_display = '+221 ' . substr($whatsapp_clean, 3, 2) . ' ' . substr($whatsapp_clean, 5, 3) . ' ' . substr($whatsapp_clean, 8, 4);
        }

        $commande_details = $numero_commande !== '' ? get_commande_by_numero($numero_commande) : false;
        $commande_produits = [];
        if ($commande_details && !empty($commande_details['id'])) {
            $commande_produits = get_produits_by_commande((int) $commande_details['id']);
            if (!is_array($commande_produits)) {
                $commande_produits = [];
            }
        }

        $client_nom = '';
        if ($commande_details) {
            $client_nom = trim(($commande_details['user_prenom'] ?? '') . ' ' . ($commande_details['user_nom'] ?? ''));
            if ($client_nom === '') {
                $client_nom = trim((string) ($commande_details['client_prenom'] ?? '') . ' ' . (string) ($commande_details['client_nom'] ?? ''));
            }
        }
        $client_tel = '';
        if ($commande_details) {
            $client_tel = trim((string) ($commande_details['user_telephone'] ?? $commande_details['telephone_livraison'] ?? $commande_details['client_telephone'] ?? ''));
        }
        $adresse = $commande_details ? trim((string) ($commande_details['adresse_livraison'] ?? '')) : '';
        $montant = $commande_details && isset($commande_details['montant_total'])
            ? number_format((float) $commande_details['montant_total'], 0, ',', ' ') . ' FCFA'
            : '';

        $wa_lines = [];
        $wa_lines[] = 'Bonjour Sugar Paper 👋';
        $wa_lines[] = '';
        $wa_lines[] = 'Je viens de passer une commande sur le site.';
        $wa_lines[] = '';
        $wa_lines[] = '📦 Numéro : ' . $numero_commande;
        if ($client_nom !== '') {
            $wa_lines[] = '👤 Client : ' . $client_nom;
        }
        if ($client_tel !== '') {
            $wa_lines[] = '📞 Téléphone : ' . $client_tel;
        }
        if ($adresse !== '') {
            $wa_lines[] = '📍 Adresse : ' . $adresse;
        }
        if ($montant !== '') {
            $wa_lines[] = '💰 Montant : ' . $montant;
        }
        if (!empty($commande_produits)) {
            $wa_lines[] = '';
            $wa_lines[] = '🛒 Produits :';
            foreach ($commande_produits as $prod) {
                $nom_prod = trim((string) ($prod['produit_nom'] ?? $prod['nom'] ?? 'Produit'));
                $qty = (int) ($prod['quantite'] ?? 1);
                $prix_l = isset($prod['prix_total'])
                    ? number_format((float) $prod['prix_total'], 0, ',', ' ') . ' FCFA'
                    : '';
                $ligne = '- ' . $nom_prod . ' × ' . $qty;
                if ($prix_l !== '') {
                    $ligne .= ' (' . $prix_l . ')';
                }
                $wa_lines[] = $ligne;
            }
        }
        $wa_lines[] = '';
        $wa_lines[] = 'Merci !';
        $whatsapp_message = implode("\n", $wa_lines);
        $whatsapp_url = 'https://wa.me/' . $whatsapp_clean . '?text=' . rawurlencode($whatsapp_message);

        return [
            'numero_commande' => $numero_commande,
            'whatsapp_url' => $whatsapp_url,
            'whatsapp_display' => $whatsapp_display,
            'montant' => $montant,
        ];
    }
}

if (!function_exists('checkout_modals_render_success')) {
    function checkout_modals_render_success($numero_commande)
    {
        $data = checkout_modals_success_payload($numero_commande);
        $numero_commande = $data['numero_commande'];
        $whatsapp_url = $data['whatsapp_url'];
        $whatsapp_display = $data['whatsapp_display'];
        $montant = $data['montant'];
        ob_start();
        include __DIR__ . '/partials/order_success_modal_body.php';
        return [
            'html' => (string) ob_get_clean(),
            'numero' => $numero_commande,
            'count' => 0,
        ];
    }
}
