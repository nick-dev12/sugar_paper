<?php
/**
 * Assistant IA JotForm — affiché sur tout le site
 * Position : bas gauche, au-dessus de la barre de navigation responsive
 */

if (defined('JOTFORM_AI_ASSISTANT_INCLUDED')) {
    return;
}

if (!empty($skip_jotform_ai_assistant)) {
    return;
}

$jotform_script_path = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$jotform_request_uri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');
$jotform_is_admin_area = (strpos($jotform_script_path, '/admin/') !== false)
    || (strpos($jotform_request_uri, '/admin/') !== false);

if ($jotform_is_admin_area) {
    return;
}

define('JOTFORM_AI_ASSISTANT_INCLUDED', true);

if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
?>
<link rel="stylesheet" href="/css/jotform-ai-assistant.css<?php echo asset_version_query(); ?>">
<script src="https://cdn.jotfor.ms/agent/embedjs/019f778143a0700083020339cfca9d410011/embed.js" defer></script>
<script>
(function () {
    var AGENT_ROOT_ID = 'JotformAgent-019f778143a0700083020339cfca9d410011';
    var GAP_ABOVE_NAV = 14;

    function isMobileViewport() {
        return window.matchMedia('(max-width: 992px)').matches;
    }

    function getBottomNavHeight() {
        var nav = document.getElementById('bottomNav');
        if (!nav || !isMobileViewport()) {
            return 0;
        }
        var styles = window.getComputedStyle(nav);
        if (styles.display === 'none' || styles.visibility === 'hidden') {
            return 0;
        }
        return Math.ceil(nav.getBoundingClientRect().height);
    }

    function getBottomOffsetPx() {
        var navHeight = getBottomNavHeight();
        if (navHeight > 0) {
            return navHeight + GAP_ABOVE_NAV;
        }
        return 24;
    }

    function syncCssVariables() {
        var navHeight = getBottomNavHeight();
        var stackHeight = navHeight > 0 ? (navHeight + GAP_ABOVE_NAV) : 24;
        document.documentElement.style.setProperty('--bottom-nav-stack-height', stackHeight + 'px');
        document.documentElement.style.setProperty('--jf-agent-bottom-offset', stackHeight + 'px');
    }

    function applyPositionToNode(node, bottomPx) {
        node.style.setProperty('left', '14px', 'important');
        node.style.setProperty('right', 'auto', 'important');
        node.style.setProperty('bottom', bottomPx + 'px', 'important');
        node.style.setProperty('top', 'auto', 'important');
        node.style.setProperty('z-index', '10030', 'important');
    }

    function applyPosition() {
        syncCssVariables();
        var bottomPx = getBottomOffsetPx();
        var root = document.getElementById(AGENT_ROOT_ID);
        if (!root) {
            return false;
        }

        var targets = [root];
        root.querySelectorAll('*').forEach(function (node) {
            var style = window.getComputedStyle(node);
            if (style.position === 'fixed' || style.position === 'sticky') {
                targets.push(node);
            }
        });

        document.querySelectorAll('[id^="JotformAgent-"]').forEach(function (node) {
            if (targets.indexOf(node) === -1) {
                targets.push(node);
            }
        });

        targets.forEach(function (node) {
            applyPositionToNode(node, bottomPx);
        });

        return true;
    }

    function watchAgent() {
        applyPosition();

        var tries = 0;
        var timer = window.setInterval(function () {
            tries += 1;
            applyPosition();
            if (tries >= 160) {
                window.clearInterval(timer);
            }
        }, 250);
    }

    function boot() {
        watchAgent();
        window.addEventListener('resize', applyPosition);
        window.addEventListener('orientationchange', applyPosition);

        if (document.body && typeof MutationObserver !== 'undefined') {
            var scheduled = false;
            var observer = new MutationObserver(function () {
                if (scheduled) {
                    return;
                }
                scheduled = true;
                window.requestAnimationFrame(function () {
                    scheduled = false;
                    applyPosition();
                });
            });
            observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
