(function () {
    var storeNavSelector = 'nav.nav-planete-gateau';
    var section1Selector = 'section.section1';
    var desktopMinWidth = 800;
    var resizeTimer = null;

    function getSafeTopMin() {
        return 10;
    }

    function isDesktopLayout() {
        return window.innerWidth >= desktopMinWidth;
    }

    function getPositionAnchor(nav) {
        var section1 = document.querySelector(section1Selector);
        if (isDesktopLayout() && section1) {
            return section1;
        }
        return nav;
    }

    function updateFloatingBackPosition() {
        var btn = document.getElementById('floatingBackBtn');
        var nav = document.querySelector(storeNavSelector);
        var root = document.documentElement;

        if (!btn) {
            return;
        }

        if (!nav) {
            root.classList.remove('has-store-nav');
            root.classList.remove('has-store-nav-desktop');
            btn.style.top = '';
            return;
        }

        root.classList.add('has-store-nav');

        var section1 = document.querySelector(section1Selector);
        var useSection1 = isDesktopLayout() && section1;
        root.classList.toggle('has-store-nav-desktop', !!useSection1);

        var gap = 8;
        var anchor = useSection1 ? section1 : nav;
        var anchorBottom = anchor.getBoundingClientRect().bottom;
        var top = Math.round(anchorBottom + gap);
        var minTop = getSafeTopMin();

        if (top < minTop) {
            top = minTop;
        }

        btn.style.top = top + 'px';
    }

    function schedulePositionUpdate() {
        if (resizeTimer) {
            window.clearTimeout(resizeTimer);
        }
        resizeTimer = window.setTimeout(updateFloatingBackPosition, 50);
    }

    function initFloatingBackButton() {
        var btn = document.getElementById('floatingBackBtn');
        if (!btn || btn.dataset.bound === '1') {
            return;
        }
        btn.dataset.bound = '1';

        btn.addEventListener('click', function () {
            var fallback = btn.getAttribute('data-fallback-home') || '/index.php';
            var referrer = document.referrer || '';
            var sameOriginReferrer = false;

            if (referrer) {
                try {
                    sameOriginReferrer = new URL(referrer).origin === window.location.origin;
                } catch (e) {
                    sameOriginReferrer = false;
                }
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            if (sameOriginReferrer && referrer !== window.location.href) {
                window.location.href = referrer;
                return;
            }

            window.location.href = fallback;
        });

        updateFloatingBackPosition();
        window.addEventListener('resize', schedulePositionUpdate, { passive: true });
        window.addEventListener('scroll', schedulePositionUpdate, { passive: true });

        if (typeof ResizeObserver !== 'undefined') {
            var nav = document.querySelector(storeNavSelector);
            var section1 = document.querySelector(section1Selector);
            var observer = new ResizeObserver(schedulePositionUpdate);

            if (nav) {
                observer.observe(nav);
            }
            if (section1) {
                observer.observe(section1);
            }
        }

        window.addEventListener('load', updateFloatingBackPosition, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFloatingBackButton);
    } else {
        initFloatingBackButton();
    }
})();
