<?php
/**
 * Skeleton shimmer — chargement visuel des images et cartes (hors super_admin).
 */
function skeleton_shimmer_is_enabled(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && strpos($script, '/super_admin/') !== false) {
        return false;
    }
    return true;
}

function skeleton_shimmer_include_head(): void
{
    if (!skeleton_shimmer_is_enabled()) {
        return;
    }
    if (!function_exists('asset_version_query')) {
        require_once __DIR__ . '/asset_version.php';
    }
    $vq = asset_version_query();
    echo '<script>document.documentElement.classList.add("sk-shimmer-pending");</script>' . "\n";
    echo '<link rel="stylesheet" href="/css/skeleton-shimmer.css' . htmlspecialchars($vq, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script src="/js/skeleton-shimmer.js' . htmlspecialchars($vq, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
