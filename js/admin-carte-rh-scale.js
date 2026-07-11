/**
 * Réduction responsive de la carte RH — même principe que facture_content.php (transform: scale sur le wrap).
 */
(function () {
    var MARGIN_X = 8;

    function fitCarteRhScale(viewport) {
        var wrap = viewport.querySelector('.er-carte-rh-scale');
        if (!wrap) {
            return;
        }

        if (window.matchMedia('print').matches) {
            wrap.style.transform = 'none';
            viewport.style.height = 'auto';
            return;
        }

        if (document.body.classList.contains('page-employes-carte-impression')) {
            wrap.style.transform = 'none';
            viewport.style.height = 'auto';
            return;
        }

        wrap.style.transform = 'none';
        viewport.style.height = 'auto';

        var naturalW = wrap.offsetWidth;
        var naturalH = wrap.offsetHeight;
        if (naturalW <= 0 || naturalH <= 0) {
            return;
        }

        var available = viewport.clientWidth > 0
            ? viewport.clientWidth - MARGIN_X
            : window.innerWidth - MARGIN_X;
        if (available <= 0) {
            available = window.innerWidth - MARGIN_X;
        }

        var scale = Math.min(1, available / naturalW);
        wrap.style.transform = scale < 1 ? 'scale(' + scale + ')' : 'none';
        viewport.style.height = Math.ceil(naturalH * scale) + 'px';
    }

    function bindCarteRhScale(viewport) {
        function run() {
            fitCarteRhScale(viewport);
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(run);
        }
        window.addEventListener('load', run);
        window.addEventListener('resize', run);
        window.addEventListener('orientationchange', function () {
            setTimeout(run, 100);
        });

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(run);
            ro.observe(viewport);
            var wrap = viewport.querySelector('.er-carte-rh-scale');
            if (wrap) {
                ro.observe(wrap);
            }
        }

        var tabInputs = document.querySelectorAll('input[name="er_fiche_tab"]');
        for (var t = 0; t < tabInputs.length; t++) {
            tabInputs[t].addEventListener('change', function () {
                setTimeout(run, 80);
            });
        }

        run();
    }

    function boot() {
        var viewports = document.querySelectorAll('.er-carte-rh-viewport');
        for (var i = 0; i < viewports.length; i++) {
            bindCarteRhScale(viewports[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
