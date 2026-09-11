/**
 * Page catalogue produits & catégorie — pagination « Voir plus »
 */
(function () {
  'use strict';

  function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function getApiUrl(grid) {
    var apiQuery = grid.getAttribute('data-api-query') || '';
    var offsetActuel = parseInt(grid.getAttribute('data-offset') || '0', 10);
    var params = new URLSearchParams(apiQuery);
    params.set('offset', String(offsetActuel));
    return 'api/get_produits.php?' + params.toString();
  }

  function chargerPlusProduits(grid) {
    var btn = grid.querySelector('#btn-voir-plus');
    var container = grid.querySelector('#produits-container');
    var countActuel = grid.querySelector('#count-actuel');
    if (!btn || !container) {
      return;
    }

    var offsetActuel = parseInt(grid.getAttribute('data-offset') || '0', 10);
    var totalProduits = parseInt(grid.getAttribute('data-total') || '0', 10);
    var returnUrl = grid.getAttribute('data-return-url') || '/produits.php';

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';

    fetch(getApiUrl(grid))
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

            var stockHTML = '';
            if (produit.stock) {
              stockHTML = '<p class="produit-card-stock-info"><strong>Stock:</strong> ' + produit.stock
                + (produit.poids ? ' (' + escapeHtml(produit.poids) + ')' : '')
                + '</p>';
            }

            var shareBtnHtml = (typeof buildProduitShareButtonHtml === 'function')
              ? buildProduitShareButtonHtml(produit)
              : '';

            div.innerHTML = shareBtnHtml
              + '<a href="produit.php?id=' + produit.id + '" class="product-card-link">'
              + '<div class="image-wrapper"><img src="' + (produit.image_url || '/upload/' + produit.image_principale) + '" '
              + 'alt="' + escapeHtml(produit.nom) + '" loading="lazy" decoding="async" onerror="this.src=\'/image/produit1.jpg\'"></div>'
              + '<div class="produit-content"><p id="nom">' + escapeHtml(produit.nom) + '</p>'
              + (produit.categorie_nom ? '<p id="ville">' + escapeHtml(produit.categorie_nom) + '</p>' : '')
              + '<p class="' + prixClass + '">' + prixHTML + '</p>'
              + stockHTML + '</div></a>'
              + '<form method="POST" action="/add-to-panier.php" class="add-to-cart-form">'
              + '<input type="hidden" name="produit_id" value="' + produit.id + '">'
              + '<input type="hidden" name="quantite" value="1">'
              + '<input type="hidden" name="return_url" value="' + escapeHtml(returnUrl) + '">'
              + '<button type="submit" class="btn-add-cart">'
              + '<i class="fa-solid fa-cart-shopping"></i> Commander</button></form>';

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
        alert('Une erreur est survenue lors du chargement des produits.');
      });
  }

  function initProduitsCatalogueGrid(grid) {
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

  function initProduitsCataloguePage(root) {
    var scope = root || document;
    scope.querySelectorAll('.produits-catalogue-grid').forEach(initProduitsCatalogueGrid);
  }

  window.initProduitsCataloguePage = initProduitsCataloguePage;

  document.addEventListener('DOMContentLoaded', function () {
    initProduitsCataloguePage(document);
  });

  document.addEventListener('page-lazy-loaded', function (event) {
    if (!event.detail || (event.detail.type !== 'produits' && event.detail.type !== 'categorie')) {
      return;
    }
    initProduitsCataloguePage(document);
  });
})();
