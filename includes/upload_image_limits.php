<?php
/**
 * Limite globale des images envoyées au site.
 * Les valeurs upload_max_filesize et post_max_size de PHP doivent être
 * supérieures ou égales à cette limite.
 */
if (!defined('UPLOAD_MAX_IMAGE_BYTES')) {
    // 50 Mo : cohérent avec les formulaires (bannières / images 4K).
    define('UPLOAD_MAX_IMAGE_BYTES', 50 * 1024 * 1024);
}
