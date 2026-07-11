<?php
/**
 * Taille maximale des images uploadées (octets).
 * Aligner php.ini : upload_max_filesize et post_max_size ≥ cette valeur.
 */
if (!defined('UPLOAD_MAX_IMAGE_BYTES')) {
    define('UPLOAD_MAX_IMAGE_BYTES', 20 * 1024 * 1024);
}
