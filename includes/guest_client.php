<?php
/**
 * Informations client invité (nom + téléphone) en session.
 */

if (!function_exists('guest_client_save')) {
    function guest_client_save($nom, $telephone)
    {
        $nom = trim((string) $nom);
        $telephone = trim((string) $telephone);
        if ($nom === '' || $telephone === '') {
            return false;
        }
        $_SESSION['guest_client'] = [
            'nom' => $nom,
            'telephone' => $telephone,
        ];
        return true;
    }
}

if (!function_exists('guest_client_get')) {
    function guest_client_get()
    {
        if (empty($_SESSION['guest_client']) || !is_array($_SESSION['guest_client'])) {
            return null;
        }
        $nom = trim((string) ($_SESSION['guest_client']['nom'] ?? ''));
        $telephone = trim((string) ($_SESSION['guest_client']['telephone'] ?? ''));
        if ($nom === '' || $telephone === '') {
            return null;
        }
        return ['nom' => $nom, 'telephone' => $telephone];
    }
}

if (!function_exists('guest_client_has_info')) {
    function guest_client_has_info()
    {
        return guest_client_get() !== null;
    }
}

if (!function_exists('guest_client_clear')) {
    function guest_client_clear()
    {
        unset($_SESSION['guest_client']);
    }
}

if (!function_exists('guest_est_invite')) {
    function guest_est_invite()
    {
        return !isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0;
    }
}
