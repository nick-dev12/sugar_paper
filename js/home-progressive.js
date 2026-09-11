/**
 * Chargement progressif — accueil, produit, section-produits, catalogue, catégorie
 */
(function () {
  'use strict';

  var loadingSections = {};
  var lazyObserver = null;
  var revealSelector = '.home-reveal, .produit-reveal, .section-produits-reveal, .produits-reveal';

  function revealElement(el) {
    if (!el || el.classList.contains('is-visible')) {
      return;
    }
    el.classList.add('is-visible');
  }

  function revealInRoot(root) {
    if (!root) {
      return;
    }
    if (root.matches && root.matches(revealSelector)) {
      revealElement(root);
    }
    root.querySelectorAll(revealSelector).forEach(revealElement);
  }

  function fixInvisibleLoadedSections() {
    document.querySelectorAll('body.page-home .home-main > section.home-reveal:not(.is-visible)').forEach(revealElement);
    document.querySelectorAll('body.page-produit .produit-detail-container > .produit-reveal:not(.is-visible)').forEach(revealElement);
    document.querySelectorAll('body.page-section-produits .section-produits-grid.section-produits-reveal:not(.is-visible)').forEach(revealElement);
    document.querySelectorAll('body.page-produits .produits-catalogue-grid.produits-reveal:not(.is-visible)').forEach(revealElement);
    document.querySelectorAll('body.page-categorie .produits-catalogue-grid.produits-reveal:not(.is-visible)').forEach(revealElement);
  }

  function initRevealObserver() {
    var nodes = document.querySelectorAll(
      'body.page-home .home-reveal:not(.is-visible), ' +
      'body.page-produit .produit-reveal:not(.is-visible), ' +
      'body.page-section-produits .section-produits-reveal:not(.is-visible), ' +
      'body.page-produits .produits-reveal:not(.is-visible), ' +
      'body.page-categorie .produits-reveal:not(.is-visible)'
    );
    if (!nodes.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      nodes.forEach(revealElement);
      return;
    }

    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          revealElement(entry.target);
          revealObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -4% 0px', threshold: 0.05 });

    nodes.forEach(function (node) {
      revealObserver.observe(node);
    });
  }

  function getLazyType(container) {
    if (container.hasAttribute('data-categorie-lazy')) {
      return 'categorie';
    }
    if (container.hasAttribute('data-produits-lazy')) {
      return 'produits';
    }
    if (container.hasAttribute('data-section-produits-lazy')) {
      return 'section-produits';
    }
    if (container.hasAttribute('data-produit-lazy')) {
      return 'produit';
    }
    if (container.hasAttribute('data-home-lazy')) {
      return 'home';
    }
    return '';
  }

  function getSectionKey(container) {
    var type = getLazyType(container);
    if (type === 'categorie') {
      return container.getAttribute('data-categorie-lazy') || '';
    }
    if (type === 'produits') {
      return container.getAttribute('data-produits-lazy') || '';
    }
    if (type === 'section-produits') {
      return container.getAttribute('data-section-produits-lazy') || '';
    }
    if (type === 'produit') {
      return container.getAttribute('data-produit-lazy') || '';
    }
    if (type === 'home') {
      return container.getAttribute('data-home-lazy') || '';
    }
    return '';
  }

  function getLoadingKey(container) {
    var type = getLazyType(container);
    var sectionKey = getSectionKey(container);
    if (type === 'categorie') {
      return type + ':' + (container.getAttribute('data-categorie-id') || '') + ':' + sectionKey;
    }
    if (type === 'produits') {
      return type + ':' + (container.getAttribute('data-query') || '') + ':' + sectionKey;
    }
    if (type === 'section-produits') {
      return type + ':' + (container.getAttribute('data-section-key') || '') + ':' + sectionKey;
    }
    if (type === 'produit') {
      return type + ':' + (container.getAttribute('data-produit-id') || '') + ':' + sectionKey;
    }
    return type + ':' + sectionKey;
  }

  function getLazySelector() {
    return '[data-home-lazy][data-loaded="0"], [data-produit-lazy][data-loaded="0"], [data-section-produits-lazy][data-loaded="0"], [data-produits-lazy][data-loaded="0"], [data-categorie-lazy][data-loaded="0"]';
  }

  function getNextLazyContainer() {
    var containers = document.querySelectorAll(getLazySelector());
    return containers.length ? containers[0] : null;
  }

  function observeNextLazySection() {
    if (!('IntersectionObserver' in window)) {
      var fallback = getNextLazyContainer();
      if (fallback) {
        loadLazySection(fallback);
      }
      return;
    }

    if (!lazyObserver) {
      lazyObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            lazyObserver.unobserve(entry.target);
            loadLazySection(entry.target);
          }
        });
      }, { rootMargin: '200px 0px 80px 0px', threshold: 0.01 });
    }

    var next = getNextLazyContainer();
    if (next) {
      lazyObserver.observe(next);
    }
  }

  function buildLazyUrl(container) {
    var type = getLazyType(container);
    var sectionKey = getSectionKey(container);

    if (type === 'categorie') {
      var categorieId = container.getAttribute('data-categorie-id') || '';
      return 'categorie-lazy-section.php?id=' + encodeURIComponent(categorieId) +
        '&part=' + encodeURIComponent(sectionKey);
    }

    if (type === 'produits') {
      var query = container.getAttribute('data-query') || '';
      return 'produits-lazy-section.php?part=' + encodeURIComponent(sectionKey) +
        (query ? '&' + query : '');
    }

    if (type === 'section-produits') {
      var pageSectionKey = container.getAttribute('data-section-key') || '';
      return 'section-produits-lazy-section.php?section=' + encodeURIComponent(pageSectionKey) +
        '&part=' + encodeURIComponent(sectionKey);
    }

    if (type === 'produit') {
      var produitId = container.getAttribute('data-produit-id') || '';
      return 'produit-lazy-section.php?id=' + encodeURIComponent(produitId) +
        '&section=' + encodeURIComponent(sectionKey);
    }

    return 'home-lazy-section.php?section=' + encodeURIComponent(sectionKey);
  }

  function getSkeletonHtml(type) {
    if (type === 'categorie') {
      return '<div class="categorie-lazy-skeleton" aria-hidden="true">' +
        '<span class="categorie-lazy-skeleton__bar categorie-lazy-skeleton__bar--sm"></span>' +
        '<span class="categorie-lazy-skeleton__bar categorie-lazy-skeleton__bar--lg"></span>' +
        '<span class="categorie-lazy-skeleton__grid"></span></div>';
    }
    if (type === 'produits') {
      return '<div class="produits-lazy-skeleton" aria-hidden="true">' +
        '<span class="produits-lazy-skeleton__bar produits-lazy-skeleton__bar--sm"></span>' +
        '<span class="produits-lazy-skeleton__bar produits-lazy-skeleton__bar--lg"></span>' +
        '<span class="produits-lazy-skeleton__grid"></span></div>';
    }
    if (type === 'section-produits') {
      return '<div class="section-produits-lazy-skeleton" aria-hidden="true">' +
        '<span class="section-produits-lazy-skeleton__bar section-produits-lazy-skeleton__bar--sm"></span>' +
        '<span class="section-produits-lazy-skeleton__bar section-produits-lazy-skeleton__bar--lg"></span>' +
        '<span class="section-produits-lazy-skeleton__grid"></span></div>';
    }
    if (type === 'produit') {
      return '<div class="produit-lazy-skeleton" aria-hidden="true">' +
        '<span class="produit-lazy-skeleton__bar produit-lazy-skeleton__bar--sm"></span>' +
        '<span class="produit-lazy-skeleton__bar produit-lazy-skeleton__bar--lg"></span>' +
        '<span class="produit-lazy-skeleton__grid"></span></div>';
    }
    return '<div class="home-lazy-skeleton" aria-hidden="true">' +
      '<span class="home-lazy-skeleton__bar home-lazy-skeleton__bar--sm"></span>' +
      '<span class="home-lazy-skeleton__bar home-lazy-skeleton__bar--lg"></span>' +
      '<span class="home-lazy-skeleton__grid"></span></div>';
  }

  function getErrorHtml(type) {
    var skeletonClass = 'home-lazy';
    if (type === 'produit') {
      skeletonClass = 'produit-lazy';
    } else if (type === 'section-produits') {
      skeletonClass = 'section-produits-lazy';
    } else if (type === 'produits') {
      skeletonClass = 'produits-lazy';
    } else if (type === 'categorie') {
      skeletonClass = 'categorie-lazy';
    }
    return '<div class="' + skeletonClass + '-error">Impossible de charger cette section. ' +
      '<button type="button" class="' + skeletonClass + '-retry">Réessayer</button></div>';
  }

  function runLazyInit(type, container) {
    if (type === 'section-produits' && typeof window.initSectionProduitsPage === 'function') {
      window.initSectionProduitsPage(container);
    }
    if ((type === 'produits' || type === 'categorie') && typeof window.initProduitsCataloguePage === 'function') {
      window.initProduitsCataloguePage(container);
    }
  }

  function markSectionLoaded(container, html) {
    var sectionKey = getSectionKey(container);
    var lazyType = getLazyType(container);
    var template = document.createElement('div');
    template.innerHTML = html.trim();
    var sectionNode = template.firstElementChild;

    if (sectionNode && container.parentNode) {
      container.parentNode.replaceChild(sectionNode, container);
      container = sectionNode;
    } else {
      container.innerHTML = html;
      container.setAttribute('data-loaded', '1');
      container.removeAttribute('aria-busy');
    }

    revealInRoot(container);
    initRevealObserver();

    if (typeof window.homeInitGalerieVideos === 'function') {
      window.homeInitGalerieVideos(container);
    }

    runLazyInit(lazyType, container);

    document.dispatchEvent(new CustomEvent('page-lazy-loaded', {
      detail: { type: lazyType, section: sectionKey }
    }));

    observeNextLazySection();
  }

  function loadLazySection(container) {
    var sectionKey = getSectionKey(container);
    var loadingKey = getLoadingKey(container);
    var lazyType = getLazyType(container);

    if (!sectionKey || !lazyType || container.getAttribute('data-loaded') === '1' || loadingSections[loadingKey]) {
      observeNextLazySection();
      return;
    }

    loadingSections[loadingKey] = true;

    fetch(buildLazyUrl(container), {
      credentials: 'same-origin',
      headers: { 'Accept': 'text/html' }
    })
      .then(function (response) {
        if (response.status === 204) {
          container.remove();
          return '';
        }
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.text();
      })
      .then(function (html) {
        if (!html || !html.trim()) {
          container.remove();
          observeNextLazySection();
          return;
        }
        markSectionLoaded(container, html);
      })
      .catch(function () {
        container.innerHTML = getErrorHtml(lazyType);
        container.removeAttribute('aria-busy');
        var retryBtn = container.querySelector('.home-lazy-retry, .produit-lazy-retry, .section-produits-lazy-retry, .produits-lazy-retry, .categorie-lazy-retry');
        if (retryBtn) {
          retryBtn.addEventListener('click', function () {
            delete loadingSections[loadingKey];
            container.setAttribute('data-loaded', '0');
            container.innerHTML = getSkeletonHtml(lazyType);
            observeNextLazySection();
          });
        }
        observeNextLazySection();
      })
      .finally(function () {
        delete loadingSections[loadingKey];
      });
  }

  function initLazySections() {
    var containers = document.querySelectorAll(getLazySelector());
    if (!containers.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      containers.forEach(loadLazySection);
      return;
    }

    observeNextLazySection();
  }

  document.addEventListener('DOMContentLoaded', function () {
    fixInvisibleLoadedSections();
    initRevealObserver();
    initLazySections();
  });
})();
