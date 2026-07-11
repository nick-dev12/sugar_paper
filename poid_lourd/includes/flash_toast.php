<?php
/**
 * Notifications toast (succès / erreur) — site entier
 * Programmation procédurale uniquement
 */

if (!function_exists('flash_toast_plain')) {
    function flash_toast_plain($message)
    {
        $text = html_entity_decode(strip_tags((string) $message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}

if (!function_exists('flash_toast_push')) {
    function flash_toast_push($type, $message)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!function_exists('session_start_persistent')) {
                require_once __DIR__ . '/session_user.php';
            }
            session_start_persistent();
        }
        $type = in_array($type, ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
        $message = flash_toast_plain($message);
        if ($message === '') {
            return;
        }
        if (!isset($_SESSION['flash_toasts']) || !is_array($_SESSION['flash_toasts'])) {
            $_SESSION['flash_toasts'] = [];
        }
        $_SESSION['flash_toasts'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_toast_queue_page')) {
    function flash_toast_queue_page($type, $message)
    {
        if (!isset($GLOBALS['flash_toast_queue']) || !is_array($GLOBALS['flash_toast_queue'])) {
            $GLOBALS['flash_toast_queue'] = [];
        }
        $message = flash_toast_plain($message);
        if ($message === '') {
            return;
        }
        $type = in_array($type, ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
        $GLOBALS['flash_toast_queue'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_toast_query_once')) {
    /**
     * Affiche un toast issu d’un paramètre GET une seule fois par session.
     */
    function flash_toast_query_once($session_key, $type, $message)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!function_exists('session_start_persistent')) {
                require_once __DIR__ . '/session_user.php';
            }
            session_start_persistent();
        }
        if (!isset($_SESSION['flash_query_once']) || !is_array($_SESSION['flash_query_once'])) {
            $_SESSION['flash_query_once'] = [];
        }
        if (!empty($_SESSION['flash_query_once'][$session_key])) {
            return null;
        }
        $message = flash_toast_plain($message);
        if ($message === '') {
            return null;
        }
        $_SESSION['flash_query_once'][$session_key] = 1;
        $type = in_array($type, ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
        return ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_toast_from_query')) {
    function flash_toast_from_query()
    {
        $items = [];

        if (isset($_GET['added']) && in_array((string) $_GET['added'], ['1', 'success'], true)) {
            $row = flash_toast_query_once('added_panier', 'success', 'Produit ajouté au panier avec succès.');
            if ($row) {
                $items[] = $row;
            }
        }

        if (!empty($_GET['recommande']) && (string) $_GET['recommande'] === '1') {
            $count = max(1, (int) ($_GET['count'] ?? 1));
            $msg = $count > 1
                ? $count . ' produits de votre commande annulée ont été ajoutés au panier avec succès !'
                : 'Les produits ont été ajoutés au panier avec succès !';
            $row = flash_toast_query_once('recommande_panier_' . $count, 'success', $msg);
            if ($row) {
                $items[] = $row;
            }
        }

        if (!empty($_GET['receive_ok'])) {
            $row = flash_toast_query_once('receive_ok', 'success', 'Réception du colis confirmée avec succès !');
            if ($row) {
                $items[] = $row;
            }
        }

        if (!empty($_GET['commande_annulee'])) {
            $row = flash_toast_query_once('commande_annulee', 'success', 'Commande annulée avec succès.');
            if ($row) {
                $items[] = $row;
            }
        }

        if (!empty($_GET['livraison_confirmee'])) {
            $row = flash_toast_query_once('livraison_confirmee', 'success', 'Colis reçu confirmé avec succès !');
            if ($row) {
                $items[] = $row;
            }
        }

        if (isset($_GET['error']) && (string) $_GET['error'] !== '') {
            $err = flash_toast_plain((string) $_GET['error']);
            $row = flash_toast_query_once('error_' . md5($err), 'error', $err);
            if ($row) {
                $items[] = $row;
            }
        }

        return $items;
    }
}

if (!function_exists('http_redirect_safe')) {
    /**
     * Redirection HTTP après POST (évite page blanche / re-soumission).
     */
    function http_redirect_safe($url, $code = 303)
    {
        $url = trim((string) $url);
        if ($url === '' || strpos($url, '//') !== false) {
            $url = '/index.php';
        }
        if ($url[0] !== '/') {
            $url = '/' . $url;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $url, true, (int) $code);
        exit;
    }
}

if (!function_exists('flash_toast_collect')) {
    function flash_toast_collect()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!function_exists('session_start_persistent')) {
                require_once __DIR__ . '/session_user.php';
            }
            session_start_persistent();
        }

        $items = [];

        if (!empty($_SESSION['flash_toasts']) && is_array($_SESSION['flash_toasts'])) {
            $items = array_merge($items, $_SESSION['flash_toasts']);
            unset($_SESSION['flash_toasts']);
        }

        if (!empty($_SESSION['success_message'])) {
            $items[] = ['type' => 'success', 'message' => flash_toast_plain($_SESSION['success_message'])];
            unset($_SESSION['success_message']);
        }

        if (!empty($_SESSION['error_message'])) {
            $items[] = ['type' => 'error', 'message' => flash_toast_plain($_SESSION['error_message'])];
            unset($_SESSION['error_message']);
        }

        if (!empty($GLOBALS['flash_toast_queue']) && is_array($GLOBALS['flash_toast_queue'])) {
            $items = array_merge($items, $GLOBALS['flash_toast_queue']);
            $GLOBALS['flash_toast_queue'] = [];
        }

        $items = array_merge($items, flash_toast_from_query());

        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['message'])) {
                continue;
            }
            $key = ($item['type'] ?? 'info') . '|' . $item['message'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = [
                'type' => in_array($item['type'] ?? '', ['success', 'error', 'info', 'warning'], true)
                    ? $item['type']
                    : 'info',
                'message' => flash_toast_plain($item['message']),
            ];
        }

        return $unique;
    }
}

if (!function_exists('flash_toast_render')) {
    function flash_toast_render()
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        if (!function_exists('asset_version_query')) {
            require_once __DIR__ . '/asset_version.php';
        }

        $items = flash_toast_collect();

        echo '<link rel="stylesheet" href="/css/flash-toast.css' . asset_version_query() . '">' . "\n";
        echo '<script>window.__FLASH_TOASTS__=' . json_encode(
            $items,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';</script>' . "\n";
        echo '<script src="/js/flash-toast.js' . asset_version_query() . '"></script>' . "\n";
    }
}
