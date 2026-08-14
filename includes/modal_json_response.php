<?php
/**
 * Helpers réponse JSON pour les modales checkout.
 */
if (!function_exists('modal_json_response')) {
    function modal_json_response(array $data, int $code = 200)
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (ob_get_level() > 0) {
            ob_clean();
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

if (!function_exists('modal_request_is_ajax')) {
    function modal_request_is_ajax()
    {
        if (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($xhr) === 'xmlhttprequest';
    }
}

if (!function_exists('modal_panier_count')) {
    function modal_panier_count()
    {
        require_once __DIR__ . '/panier_invite.php';
        require_once __DIR__ . '/../models/model_panier.php';

        $items = panier_get_items_courant();
        $count = 0;
        foreach ($items as $item) {
            $count += (int) ($item['quantite'] ?? 0);
        }
        return $count;
    }
}

if (!function_exists('modal_update_nav_badges')) {
    function modal_update_nav_badges()
    {
        return ['count' => modal_panier_count()];
    }
}
