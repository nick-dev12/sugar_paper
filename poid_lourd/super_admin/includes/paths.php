<?php
/**
 * Préfixe URL web du dossier super_admin (ex. /poid_lourd/super_admin/)
 */
function super_admin_web_base() {
    $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/super_admin/');
    if (preg_match('#/super_admin/#', $sn)) {
        return preg_replace('#/super_admin/.*$#', '/super_admin/', $sn);
    }
    return '/super_admin/';
}

function super_admin_href($file) {
    return super_admin_web_base() . ltrim((string) $file, '/');
}
