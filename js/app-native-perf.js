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
    window.__SUGARPAPER_KB_HANDLER = true;

    var lastKb = -1;
    var focused = null;
    var settleTimer = 0;
    var pendingOverlap = 0;

    function isField(el) {
      if (!el || el.nodeType !== 1) return false;
      var tag = (el.tagName || '').toUpperCase();
      if (el.isContentEditable) return true;
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') return false;
      var type = (el.type || '').toLowerCase();
      return type !== 'checkbox' && type !== 'radio' && type !== 'button' &&
             type !== 'submit' && type !== 'reset' && type !== 'file' &&
             type !== 'hidden' && type !== 'range' && type !== 'color';
    }

    function overlapNow(allowGuess) {
      var vv = window.visualViewport;
      var fromVv = vv ? Math.max(0, Math.round(window.innerHeight - vv.height)) : 0;
      if (fromVv > 48) return fromVv;
      if (allowGuess && focused) {
        return Math.round(window.innerHeight * 0.38);
      }
      return 0;
    }

    function applyKb(px) {
      if (px === lastKb) return;
      lastKb = px;
      var root = document.documentElement;
      root.style.setProperty('--native-kb', px + 'px');
      if (px > 48) root.classList.add('native-kb-open');
      else root.classList.remove('native-kb-open');
    }

    function scrollFocused() {
      var el = focused;
      if (!el || !el.getBoundingClientRect) return;
      var vv = window.visualViewport;
      var rect = el.getBoundingClientRect();
      var top;
      var bottom;
      if (vv && vv.height < window.innerHeight - 48) {
        top = vv.offsetTop + 8;
        bottom = vv.offsetTop + vv.height - 12;
      } else {
        top = 8;
        bottom = Math.round(window.innerHeight * 0.48);
      }
      if (rect.bottom > bottom) {
        window.scrollBy(0, Math.ceil(rect.bottom - bottom + 24));
      } else if (rect.top < top) {
        window.scrollBy(0, Math.floor(rect.top - top - 8));
      }
    }

    function onKeyboardSettled() {
      applyKb(pendingOverlap);
      scrollFocused();
    }

    function onViewportResize() {
      pendingOverlap = overlapNow(false);
      if (pendingOverlap > 48) {
        document.documentElement.classList.add('native-kb-open');
      }
      clearTimeout(settleTimer);
      settleTimer = setTimeout(onKeyboardSettled, 180);
    }

    document.addEventListener('focusin', function (e) {
      if (!isField(e.target)) return;
      focused = e.target;
      document.documentElement.classList.add('native-kb-open');
      clearTimeout(settleTimer);
      settleTimer = setTimeout(function () {
        pendingOverlap = overlapNow(true);
        onKeyboardSettled();
      }, 280);
    }, true);

    document.addEventListener('focusout', function () {
      focused = null;
      clearTimeout(settleTimer);
      settleTimer = setTimeout(function () {
        if (isField(document.activeElement)) return;
        pendingOverlap = overlapNow(false);
        if (pendingOverlap < 48) applyKb(0);
      }, 100);
    }, true);

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', onViewportResize, { passive: true });
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
