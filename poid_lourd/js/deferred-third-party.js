/**
 * Chargement différé des scripts tiers (Jotform, GTranslate) — ne bloque pas le rendu initial.
 */
(function (win, doc) {
  'use strict';

  var cfg = win.__COLOBANES_LAZY || {};
  var loaded = { jotform: false, gtranslate: false };
  var pending = { jotform: [], gtranslate: [] };

  function injectScript(src, id, onload, onerror) {
    if (!src) {
      return null;
    }
    if (id && doc.getElementById(id)) {
      var existing = doc.getElementById(id);
      if (onload) {
        if (existing.getAttribute('data-loaded') === '1') {
          onload();
        } else {
          existing.addEventListener('load', onload, { once: true });
        }
      }
      return existing;
    }
    var s = doc.createElement('script');
    s.src = src;
    s.async = true;
    if (id) {
      s.id = id;
    }
    s.addEventListener('load', function () {
      s.setAttribute('data-loaded', '1');
      if (onload) {
        onload();
      }
    }, { once: true });
    if (onerror) {
      s.addEventListener('error', onerror, { once: true });
    }
    doc.body.appendChild(s);
    return s;
  }

  function flushQueue(key) {
    var list = pending[key].slice();
    pending[key].length = 0;
    list.forEach(function (fn) {
      try {
        fn();
      } catch (e) { /* ignore */ }
    });
  }

  function whenIdle(fn, timeoutMs) {
    timeoutMs = timeoutMs || 4500;
    if (typeof win.requestIdleCallback === 'function') {
      win.requestIdleCallback(function () {
        fn();
      }, { timeout: timeoutMs });
      return;
    }
    win.setTimeout(fn, Math.min(timeoutMs, 2500));
  }

  function needsGtranslateEarly() {
    try {
      var stored = win.localStorage && win.localStorage.getItem('nav_selected_lang');
      if (stored && stored !== 'fr') {
        return true;
      }
    } catch (e) { /* ignore */ }
    var parts = ('; ' + doc.cookie).split('; googtrans=');
    if (parts.length < 2) {
      return false;
    }
    var raw = parts.pop().split(';').shift() || '';
    try {
      raw = decodeURIComponent(raw.replace(/\+/g, ' ')).trim();
    } catch (e1) {
      raw = raw.trim();
    }
    return raw !== '' && raw.indexOf('/fr/fr') === -1 && raw !== '/fr';
  }

  function loadJotform(callback) {
    var url = cfg.jotform;
    if (!url) {
      return;
    }
    if (loaded.jotform) {
      if (callback) {
        callback();
      }
      return;
    }
    if (callback) {
      pending.jotform.push(callback);
    }
    if (pending.jotform.length > 1) {
      return;
    }
    injectScript(url, 'colobanes-jotform-agent', function () {
      loaded.jotform = true;
      flushQueue('jotform');
    });
  }

  function loadGtranslate(callback) {
    var url = cfg.gtranslate || 'https://cdn.gtranslate.net/widgets/latest/dropdown.js';
    if (loaded.gtranslate) {
      if (callback) {
        callback();
      }
      return;
    }
    if (callback) {
      pending.gtranslate.push(callback);
    }
    if (pending.gtranslate.length > 1) {
      return;
    }
    injectScript(url, 'colobanes-gtranslate', function () {
      loaded.gtranslate = true;
      flushQueue('gtranslate');
    });
  }

  function scheduleJotformIdle() {
    if (!cfg.jotform) {
      return;
    }
    whenIdle(function () {
      loadJotform();
    }, 10000);
  }

  function scheduleGtranslateIdle() {
    if (needsGtranslateEarly()) {
      whenIdle(function () {
        loadGtranslate();
      }, 2000);
    }
  }

  function bindNavLangPrefetch() {
    var trigger = doc.getElementById('navLangTrigger');
    if (!trigger) {
      return;
    }
    trigger.addEventListener('click', function () {
      loadGtranslate();
    }, { passive: true, once: false });
  }

  win.ColobanesPerf = {
    loadJotform: loadJotform,
    loadGtranslate: loadGtranslate,
    needsGtranslateEarly: needsGtranslateEarly
  };

  function init() {
    scheduleJotformIdle();
    scheduleGtranslateIdle();
    bindNavLangPrefetch();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
