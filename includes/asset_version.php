<?php
/**
 * Version des assets pour cache busting
 * Force les navigateurs à recharger les CSS/JS modifiés
 *
 * @return string Timestamp du fichier CSS le plus récent, ou version config
 */
function get_asset_version() {
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $config_file = __DIR__ . '/../config/assets.php';
    if (file_exists($config_file)) {
        $config = require $config_file;
        if (!empty($config['version'])) {
            $version = $config['version'];
            return $version;
        }
    }
    $dirs = [
        __DIR__ . '/../css',
        __DIR__ . '/../js',
    ];
    $max = 0;
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.{css,js}', GLOB_BRACE) as $f) {
            $m = @filemtime($f);
            if ($m && $m > $max) {
                $max = $m;
            }
        }
    }
    $version = $max ? (string) $max : '';
    return $version;
}

/**
 * Retourne la chaîne ?v=xxx pour cache busting
 * @return string Ex: ?v=1234567890 ou ''
 */
function asset_version_query() {
    $v = get_asset_version();
    return $v ? '?v=' . $v : '';
}
