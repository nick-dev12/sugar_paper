<?php
/**
 * Assistant IA JotForm — pages publiques uniquement
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

$jotform_is_auth_page = (strpos($jotform_script_path, '/user/connexion.php') !== false)
    || (strpos($jotform_script_path, '/user/inscription.php') !== false)
    || (strpos($jotform_script_path, '/user/mot-de-passe-oublie.php') !== false)
    || preg_match('#/user/(connexion|inscription|mot-de-passe-oublie)\.php#', $jotform_request_uri);

if ($jotform_is_admin_area || $jotform_is_auth_page) {
    return;
}

define('JOTFORM_AI_ASSISTANT_INCLUDED', true);

if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
?>
<link rel="stylesheet" href="/css/jotform-ai-assistant.css<?php echo asset_version_query(); ?>">
<script src="https://cdn.jotfor.ms/agent/embedjs/019f778143a0700083020339cfca9d410011/embed.js"></script>
<script>
    (function () {
        var GAP_ABOVE_NAV = -10;
        var FALLBACK_NAV = 73;

        function isCompactViewport() {
            return window.matchMedia('(max-width: 992px)').matches;
        }

        function viewportHeight() {
            if (window.visualViewport && window.visualViewport.height) {
                return window.visualViewport.height;
            }
            return window.innerHeight || document.documentElement.clientHeight || 0;
        }

        function getNavHeight() {
            if (!isCompactViewport()) {
                return 0;
            }
            var nav = document.getElementById('bottomNav');
            if (!nav) {
                return FALLBACK_NAV;
            }
            var styles = window.getComputedStyle(nav);
            if (styles.display === 'none' || styles.visibility === 'hidden') {
                return 0;
            }
            var h = Math.ceil(nav.getBoundingClientRect().height);
            return h >= 56 ? h : FALLBACK_NAV;
        }

        function getBottomOffsetPx() {
            var navHeight = getNavHeight();
            if (navHeight < 1) {
                return 24;
            }
            return navHeight + GAP_ABOVE_NAV;
        }

        function getTopOffsetPx() {
            if (!isCompactViewport()) {
                return 12;
            }
            return 10;
        }

        function widgetHasPanel(root) {
            var iframe = root.querySelector('iframe');
            if (iframe) {
                var cs = window.getComputedStyle(iframe);
                var ir = iframe.getBoundingClientRect();
                if (cs.display !== 'none' && cs.visibility !== 'hidden' && ir.height >= 200 && ir.width >= 200) {
                    return true;
                }
            }
            return false;
        }

        function pinFixed(node, bottomPx, leftPx) {
            node.style.setProperty('position', 'fixed', 'important');
            node.style.setProperty('bottom', bottomPx + 'px', 'important');
            node.style.setProperty('top', 'auto', 'important');
            node.style.setProperty('left', leftPx + 'px', 'important');
            node.style.setProperty('right', 'auto', 'important');
            node.style.setProperty('inset', 'auto auto ' + bottomPx + 'px ' + leftPx + 'px', 'important');
            node.style.setProperty('transform', 'none', 'important');
            node.style.setProperty('filter', 'none', 'important');
            node.style.setProperty('z-index', '10018', 'important');
            node.style.setProperty('margin', '0', 'important');
        }

        function pinPanel(node, bottomPx) {
            var compact = isCompactViewport();
            var maxH = Math.max(220, Math.floor(viewportHeight() - getTopOffsetPx() - bottomPx));
            node.style.setProperty('position', 'fixed', 'important');
            node.style.setProperty('bottom', bottomPx + 'px', 'important');
            node.style.setProperty('top', 'auto', 'important');
            node.style.setProperty('left', compact ? '8px' : '16px', 'important');
            node.style.setProperty('right', compact ? '8px' : 'auto', 'important');
            node.style.setProperty('max-height', maxH + 'px', 'important');
            node.style.setProperty('height', maxH + 'px', 'important');
            node.style.setProperty('width', compact ? 'auto' : '', 'important');
            node.style.setProperty('overflow', 'hidden', 'important');
            node.style.setProperty('z-index', '10018', 'important');
            node.style.setProperty('transform', 'none', 'important');
        }

        function applyPosition() {
            var bottomPx = getBottomOffsetPx();
            var topPx = getTopOffsetPx();
            var leftPx = isCompactViewport() ? 10 : 16;
            var maxH = Math.max(220, Math.floor(viewportHeight() - topPx - bottomPx));

            document.documentElement.style.setProperty('--jf-agent-bottom-offset', bottomPx + 'px');
            document.documentElement.style.setProperty('--jf-agent-left-offset', leftPx + 'px');
            document.documentElement.style.setProperty('--jf-agent-top-offset', topPx + 'px');
            document.documentElement.style.setProperty('--jf-agent-panel-max-height', maxH + 'px');

            var roots = document.querySelectorAll('[id^="JotformAgent-"]');
            if (!roots.length) {
                return false;
            }

            roots.forEach(function (root) {
                var open = widgetHasPanel(root);
                root.querySelectorAll('.jficc, .embedded-agent-container').forEach(function (wrap) {
                    wrap.style.setProperty('transform', 'none', 'important');
                    wrap.style.setProperty('filter', 'none', 'important');
                });

                if (open) {
                    pinPanel(root, bottomPx);
                    return;
                }

                pinFixed(root, bottomPx, leftPx);
                root.style.setProperty('width', 'auto', 'important');
                root.style.setProperty('height', 'auto', 'important');
                root.style.setProperty('max-width', 'none', 'important');
                root.style.setProperty('max-height', 'none', 'important');
                root.style.setProperty('overflow', 'visible', 'important');
                root.style.setProperty('pointer-events', 'none', 'important');

                root.querySelectorAll(
                    '.ai-agent-chat-avatar-container, .ai-agent-chat-avatar'
                ).forEach(function (node) {
                    pinFixed(node, bottomPx, leftPx);
                    node.style.setProperty('pointer-events', 'auto', 'important');
                });
            });

            document.querySelectorAll(
                '.ai-agent-chat-avatar-container, .ai-agent-chat-avatar'
            ).forEach(function (node) {
                if (node.closest('[id^="JotformAgent-"]')) {
                    return;
                }
                pinFixed(node, bottomPx, leftPx);
            });

            return true;
        }

        function boot() {
            applyPosition();
            var tries = 0;
            var timer = window.setInterval(function () {
                tries += 1;
                applyPosition();
                if (tries >= 80) {
                    window.clearInterval(timer);
                }
            }, 250);

            window.addEventListener('resize', applyPosition);
            window.addEventListener('orientationchange', applyPosition);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', applyPosition);
            }

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
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
</script>