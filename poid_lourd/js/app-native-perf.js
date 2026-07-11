/**
 * Optimisations runtime pour l’app Flutter (WebView) et mobile tactile.
 * Chargé sur toutes les pages via pwa_meta.php ; actif si .is-native-app ou mobile.
 */
(function () {
  'use strict';

  function isNativeApp() {
    return (
      window.__COLOBANES_NATIVE_APP === true ||
      document.documentElement.classList.contains('is-native-app') ||
      (navigator.userAgent && navigator.userAgent.indexOf('ColobanesApp') !== -1)
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

  function run() {
    if (!shouldOptimize()) {
      return;
    }
    markNative();
    disableAos();
    stopSkeletonShimmer();
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

  window.ColobanesPerf = {
    refresh: run,
    isNativeApp: isNativeApp,
  };
})();
