<?php
/**
 * Limite uploads vidéo (admin) — 200 Mo max.
 * PHP : upload_max_filesize et post_max_size ≥ 200M (php.ini / .user.ini).
 */
declare(strict_types=1);

if (!defined('VIDEO_UPLOAD_MAX_BYTES')) {
    define('VIDEO_UPLOAD_MAX_BYTES', 200 * 1024 * 1024);
}

/**
 * @return int
 */
function video_upload_max_mo_int()
{
    return (int) (VIDEO_UPLOAD_MAX_BYTES / (1024 * 1024));
}

/**
 * Tente d’aligner les limites PHP (effet limité en runtime pour post/upload max).
 * @return void
 */
function video_upload_apply_php_limits()
{
    $mo = video_upload_max_mo_int() . 'M';
    @ini_set('upload_max_filesize', $mo);
    @ini_set('post_max_size', $mo);
    @ini_set('max_execution_time', '600');
    @ini_set('max_input_time', '600');
    @ini_set('memory_limit', '512M');
}

/**
 * @return string
 */
function video_upload_limit_error_message()
{
    $mo = video_upload_max_mo_int();
    $post = ini_get('post_max_size') ?: '?';
    $upload = ini_get('upload_max_filesize') ?: '?';
    $extra = '';

    if (PHP_SAPI === 'cli-server') {
        $extra = ' Vous utilisez le serveur PHP intégré (port 5000) : redémarrez-le dans Cursor (PHP Server → Restart) après modification du php.ini.';
    } else {
        $extra = ' Redémarrez Apache/WampServer après modification du php.ini.';
    }

    return 'Le fichier est trop volumineux (maximum ' . $mo . ' Mo). '
        . 'Configuration PHP actuelle : post_max_size=' . $post . ', upload_max_filesize=' . $upload . '. '
        . 'Augmentez ces valeurs à au moins ' . $mo . 'M dans php.ini'
        . $extra;
}

/**
 * @param int $bytes
 * @return bool
 */
function video_upload_size_is_valid($bytes)
{
    return (int) $bytes > 0 && (int) $bytes <= VIDEO_UPLOAD_MAX_BYTES;
}
