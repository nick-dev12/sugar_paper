/**
 * Optimisations runtime pour l’app Flutter (WebView) et mobile tactile.
 * Chargé via pwa_meta.php ; actif si .is-native-app ou mobile.
 */
(function () {
  'use strict';

  function isNativeApp() {
    return (
      window.__SUGARPAPER_NATIVE_APP === true ||
      document.documentElement.classList.contains('is-native-app') ||
      (navigator.userAgent && /SugarPaperApp/i.test(navigator.userAgent))
    );
  }

  function isCoarseMobile() {
    try {
      return window.matchMedia('(max-width: 768px) and (hover: none) and (pointer: coarse)').matches;
    } catch (e) {
      return false;
    }
  }

  function shouldOptimize() {
    return isNativeApp() || isCoarseMobile();
  }

  function markNative() {
    if (isNativeApp()) {
      document.documentElement.classList.add('is-native-app');
    }
  }

  function disableAos() {
    if (typeof AOS !== 'undefined' && AOS.init) {
      try {
        AOS.init({ disable: true });
      } catch (e) { /* ignore */ }
    }
    document.documentElement.classList.remove('aos-not-ready');
    document.querySelectorAll('[data-aos]').forEach(function (el) {
      el.removeAttribute('data-aos');
      el.classList.remove('aos-animate');
    });
  }

  function stopSkeletonShimmer() {
    document.documentElement.classList.remove('sk-shimmer-pending');
    document.documentElement.classList.add('sk-shimmer-done');
  }

  function pauseCarousels() {
    if (typeof window.jQuery === 'undefined') {
      return;
    }
    var $ = window.jQuery;
    $('.owl-carousel').each(function () {
      try {
        var inst = $(this).data('owl.carousel');
        if (inst && typeof inst.trigger === 'function') {
          inst.trigger('stop.owl.autoplay');
        }
      } catch (e) { /* ignore */ }
    });
  }

  function setupKeyboardAvoidance() {
    if (!isNativeApp()) {
      return;
    }
    /* UserScript Flutter (AT_DOCUMENT_START) gère déjà le clavier */
    if (window.__SUGARPAPER_KB_HANDLER === true) {
      return;
    }
    function applyPad() {
      var vv = window.visualViewport;
      if (!vv) return;
      var overlap = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
      document.documentElement.style.setProperty('--native-kb', overlap + 'px');
    }
    function scrollFocused(el) {
      if (!el || !el.scrollIntoView) return;
      var vv = window.visualViewport;
      try {
        el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
      } catch (e1) {
        try { el.scrollIntoView(true); } catch (e2) {}
      }
      if (vv) {
        var rect = el.getBoundingClientRect();
        var visibleBottom = vv.height + vv.offsetTop - 20;
        if (rect.bottom > visibleBottom) {
          window.scrollBy(0, rect.bottom - visibleBottom + 12);
        }
      }
    }
    document.addEventListener('focusin', function (e) {
      var t = e.target;
      if (!t) return;
      var tag = (t.tagName || '').toUpperCase();
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !t.isContentEditable) {
        return;
      }
      setTimeout(function () {
        scrollFocused(t);
        applyPad();
      }, 300);
    }, true);
    document.addEventListener('focusout', function () {
      setTimeout(function () {
        document.documentElement.style.setProperty('--native-kb', '0px');
      }, 120);
    }, true);
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', applyPad);
      window.visualViewport.addEventListener('scroll', applyPad);
    }
  }

  function run() {
    if (!shouldOptimize()) {
      return;
    }
    markNative();
    disableAos();
    stopSkeletonShimmer();
    setupKeyboardAvoidance();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', pauseCarousels);
    } else {
      pauseCarousels();
    }
  }

  markNative();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

  window.SugarPaperPerf = {
    refresh: run,
    isNativeApp: isNativeApp,
  };
})();
