/**
 * Page section-produits — pagination « Voir plus » et intro SEO
 */
(function () {
  'use strict';

  function formatNumber(n) {
    return Number(n).toLocaleString('fr-FR');
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function initSeoToggle(root) {
    var scope = root || document;
    scope.querySelectorAll('.page-header-seo-toggle').forEach(function (btn) {
      if (btn.dataset.seoToggleBound === '1') {
        return;
      }
      btn.dataset.seoToggleBound = '1';
      btn.addEventListener('click', function () {
        var wrap = btn.closest('.page-header-seo-intro');
        if (!wrap) {
          return;
        }
        var textEl = wrap.querySelector('.page-header-seo-text');
        var previewText = wrap.getAttribute('data-preview-text') || '';
        var fullText = wrap.getAttribute('data-full-text') || '';
        var expanded = btn.getAttribute('aria-expanded') === 'true';

        if (expanded) {
          if (textEl) {
            textEl.textContent = previewText;
          }
          btn.textContent = 'Voir plus';
          btn.setAttribute('aria-expanded', 'false');
        } else {
          if (textEl) {
            textEl.textContent = fullText;
          }
          btn.textContent = 'Voir moins';
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  function chargerPlusProduits(grid) {
    var btn = grid.querySelector('#btn-voir-plus');
    var container = grid.querySelector('#produits-container');
    var countActuel = grid.querySelector('#count-actuel');
    if (!btn || !container) {
      return;
    }

    var sectionKey = grid.getAttribute('data-section-key') || '';
    var offsetActuel = parseInt(grid.getAttribute('data-offset') || '0', 10);
    var limit = parseInt(grid.getAttribute('data-limit') || '20', 10);
    var totalProduits = parseInt(grid.getAttribute('data-total') || '0', 10);
    var returnUrl = grid.getAttribute('data-return-url') || '';
    var sectionUsesPerso = grid.getAttribute('data-uses-perso') === '1';
    var sectionUsesCp = grid.getAttribute('data-uses-cp') === '1';

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';

    fetch('api/get_produits_section.php?section=' + encodeURIComponent(sectionKey) + '&offset=' + offsetActuel + '&limit=' + limit)
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.success && data.produits.length > 0) {
          data.produits.forEach(function (produit) {
            var div = document.createElement('div');
            div.className = 'carousel';
            div.setAttribute('data-produit-id', produit.id);

            var prixClass = produit.show_price_from ? 'prix prix--from' : 'prix';
            var fromLabel = produit.show_price_from
              ? '<span class="prix-from-label">À partir de</span>'
              : '';
            var prixHTML = '';
            if (produit.has_promotion) {
              prixHTML = fromLabel + '<span class="span2">' + formatNumber(produit.prix) + ' FCFA</span>'
                + '<span class="prix-promo">' + formatNumber(produit.prix_affichage) + ' FCFA</span>'
                + '<span class="span3">-' + produit.pourcentage_promo + '%</span>';
            } else {
              prixHTML = fromLabel + formatNumber(produit.prix_affichage) + '<span class="span1"> FCFA</span>';
            }

            var shareBtnHtml = (typeof buildProduitShareButtonHtml === 'function')
              ? buildProduitShareButtonHtml(produit)
              : '';

            var formHtml = '';
            if (sectionUsesCp && produit.cp) {
              formHtml = '<div class="add-to-cart-form">'
                + '<a href="produit.php?id=' + produit.id + '" class="btn-add-cart btn-personnaliser-card">'
                + '<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Personnaliser</a>'
                + '</div>';
            } else if (sectionUsesPerso) {
              formHtml = '<div class="add-to-cart-form">'
                + '<a href="produit.php?id=' + produit.id + '" class="btn-add-cart btn-personnaliser-card">'
                + '<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Personnalisation</a>'
                + '</div>';
            } else {
              formHtml = '<form method="POST" action="/add-to-panier.php" class="add-to-cart-form">'
                + '<input type="hidden" name="produit_id" value="' + produit.id + '">'
                + '<input type="hidden" name="quantite" value="1">'
                + '<input type="hidden" name="return_url" value="' + escapeHtml(returnUrl) + '">'
                + '<button type="submit" class="btn-add-cart">'
                + '<i class="fa-solid fa-cart-shopping"></i> Commander</button>'
                + '</form>';
            }

            div.innerHTML = shareBtnHtml
              + '<a href="produit.php?id=' + produit.id + '" class="product-card-link">'
              + '<div class="image-wrapper"><img src="' + (produit.image_url || '/image/produit1.jpg') + '" alt="' + escapeHtml(produit.nom) + '" loading="lazy" decoding="async" onerror="this.src=\'/image/produit1.jpg\'"></div>'
              + '<div class="produit-content"><p id="nom">' + escapeHtml(produit.nom) + '</p>'
              + (produit.categorie_nom ? '<p id="ville">' + escapeHtml(produit.categorie_nom) + '</p>' : '')
              + '<p class="' + prixClass + '">' + prixHTML + '</p></div></a>'
              + formHtml;

            container.appendChild(div);
          });

          offsetActuel += data.produits.length;
          grid.setAttribute('data-offset', String(offsetActuel));
          if (countActuel) {
            countActuel.textContent = offsetActuel;
          }

          if (offsetActuel >= totalProduits) {
            btn.style.display = 'none';
          } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
          }
        } else {
          btn.style.display = 'none';
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
      });
  }

  function initSectionProduitsGrid(grid) {
    if (!grid || grid.dataset.paginationBound === '1') {
      return;
    }
    grid.dataset.paginationBound = '1';

    var btnVoirPlus = grid.querySelector('#btn-voir-plus');
    if (btnVoirPlus) {
      btnVoirPlus.addEventListener('click', function () {
        chargerPlusProduits(grid);
      });
    }
  }

  function initSectionProduitsPage(root) {
    var scope = root || document;
    scope.querySelectorAll('.section-produits-grid').forEach(initSectionProduitsGrid);
    initSeoToggle(scope === document ? document : scope);
  }

  window.initSectionProduitsPage = initSectionProduitsPage;

  document.addEventListener('DOMContentLoaded', function () {
    initSectionProduitsPage(document);
  });

  document.addEventListener('page-lazy-loaded', function (event) {
    if (!event.detail || event.detail.type !== 'section-produits') {
      return;
    }
    initSectionProduitsPage(document);
  });
})();
