<?php
/**
 * Chargement de la configuration suivi livreurs.
 * Programmation procédurale uniquement.
 */

if (!function_exists('tracking_load_config')) {

    function tracking_load_config() {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $path = __DIR__ . '/../config/tracking.php';
        if (!is_file($path)) {
            $path = __DIR__ . '/../config/tracking.example.php';
        }

        $loaded = require $path;
        $config = is_array($loaded) ? $loaded : [];

        return $config;
    }

    function tracking_config_get($key, $default = null) {
        $config = tracking_load_config();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    function tracking_internal_secret() {
        return trim((string) tracking_config_get('internal_secret', ''));
    }

    function tracking_verify_internal_request() {
        $expected = tracking_internal_secret();
        if ($expected === '' || $expected === 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE') {
            return false;
        }

        $header = $_SERVER['HTTP_X_TRACKING_SECRET'] ?? '';
        if ($header !== '' && hash_equals($expected, $header)) {
            return true;
        }

        $input = $_POST;
        $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($content_type, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = array_merge($input, $decoded);
            }
        }

        $body_secret = isset($input['internal_secret']) ? (string) $input['internal_secret'] : '';
        return $body_secret !== '' && hash_equals($expected, $body_secret);
    }

    function tracking_json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Socket.io + serveur Node configurés (config/tracking.php valide).
     */
    function tracking_realtime_available() {
        $path = __DIR__ . '/../config/tracking.php';
        if (!is_file($path)) {
            return false;
        }
        $secret = tracking_internal_secret();
        if ($secret === '' || $secret === 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE') {
            return false;
        }
        $port = (int) tracking_config_get('node_port', 0);
        return $port > 0;
    }
}
