(function () {
  'use strict';

  function portalOverlaysToBody() {
    document.querySelectorAll('.prix-neg-modal, .prix-neg-fullscreen, .prix-neg-reject-overlay').forEach(function (el) {
      if (el.parentNode !== document.body) {
        document.body.appendChild(el);
      }
    });
  }

  var openModalStack = [];

  function openModalEl(modal) {
    if (!modal) return;
    if (modal.classList.contains('is-open')) return;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    openModalStack.push(modal);
    document.body.style.overflow = 'hidden';
  }

  function closeModalEl(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modal.hidden = true;
    var idx = openModalStack.indexOf(modal);
    if (idx >= 0) {
      openModalStack.splice(idx, 1);
    }
    if (openModalStack.length === 0) {
      document.body.style.overflow = '';
    }
  }

  function bindModalClose(modal, closeSelector) {
    if (!modal) return;
    modal.querySelectorAll(closeSelector || '[data-prix-neg-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        closeModalEl(modal);
      });
    });
  }

  function initNegociationModal() {
    var modal = document.getElementById('prixNegModal');
    if (!modal) return;

    var openBtns = document.querySelectorAll('[data-prix-neg-open]');
    var form = document.getElementById('prix-neg-form');
    var refDisplay = document.getElementById('prix-neg-ref-display');
    var refInput = document.getElementById('prix-neg-ref-input');
    var addForm = document.getElementById('add-to-panier-form');

    function syncFormFields() {
      if (!form || !addForm) return;
      var map = [
        'option_variante_id',
        'option_variante_nom',
        'option_variante_image',
        'option_couleur',
        'option_poids',
        'option_taille',
        'option_surcout_poids',
        'option_surcout_taille'
      ];
      map.forEach(function (name) {
        var src = addForm.querySelector('[name="' + name + '"]');
        var dst = form.querySelector('[name="' + name + '"]');
        if (!dst) {
          dst = document.createElement('input');
          dst.type = 'hidden';
          dst.name = name;
          form.appendChild(dst);
        }
        if (src) {
          dst.value = src.value;
        } else {
          dst.value = '';
        }
      });
      var prixUnit = document.getElementById('option-prix-unitaire');
      if (prixUnit && refInput) {
        refInput.value = prixUnit.value;
      }
      if (prixUnit && refDisplay) {
        var val = parseFloat(prixUnit.value) || 0;
        refDisplay.textContent = val.toLocaleString('fr-FR') + ' FCFA';
      }
    }

    function openModal() {
      syncFormFields();
      openModalEl(modal);
      var input = form && form.querySelector('#prix-neg-propose');
      if (input) input.focus();
    }

    openBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    });

    bindModalClose(modal);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModalEl(modal);
      }
    });
  }

  function initLoginModal() {
    var modal = document.getElementById('prixNegLoginModal');
    if (!modal) return;

    document.querySelectorAll('[data-prix-neg-login-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var redirect = btn.getAttribute('data-login-redirect') || '/index.php';
        var loginBtn = document.getElementById('prixNegLoginBtn');
        if (loginBtn) {
          loginBtn.href = '/choix-connexion.php?redirect=' + encodeURIComponent(redirect);
        }
        openModalEl(modal);
      });
    });

    bindModalClose(modal);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModalEl(modal);
      }
    });
  }

  function initClientProposeModal() {
    var modal = document.getElementById('prixNegClientModal');
    if (!modal) return;

    var thumb = document.getElementById('prixNegClientModalThumb');
    var img = document.getElementById('prixNegClientModalImg');
    var productEl = document.getElementById('prixNegClientModalProduct');
    var refDisplay = document.getElementById('prixNegClientModalRef');
    var produitId = document.getElementById('prixNegClientProduitId');
    var refInput = document.getElementById('prixNegClientRefInput');
    var proposeInput = document.getElementById('prixNegClientPropose');
    var fields = {
      option_variante_id: document.getElementById('prixNegClientVarId'),
      option_couleur: document.getElementById('prixNegClientCouleur'),
      option_poids: document.getElementById('prixNegClientPoids'),
      option_taille: document.getElementById('prixNegClientTaille'),
      option_variante_nom: document.getElementById('prixNegClientVarNom'),
      option_variante_image: document.getElementById('prixNegClientVarImg'),
      option_surcout_poids: document.getElementById('prixNegClientSurPoids'),
      option_surcout_taille: document.getElementById('prixNegClientSurTaille')
    };

    document.querySelectorAll('[data-prix-neg-client-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var ref = parseFloat(btn.getAttribute('data-prix-reference') || '0') || 0;
        var image = btn.getAttribute('data-produit-image') || '';
        var nom = btn.getAttribute('data-produit-nom') || '';

        if (produitId) produitId.value = btn.getAttribute('data-produit-id') || '';
        if (refInput) refInput.value = String(ref);
        if (refDisplay) refDisplay.textContent = ref.toLocaleString('fr-FR') + ' FCFA';
        if (productEl) productEl.textContent = nom;

        if (thumb && img) {
          if (image) {
            img.src = image;
            img.alt = nom;
            thumb.hidden = false;
          } else {
            thumb.hidden = true;
          }
        }

        if (fields.option_variante_id) {
          fields.option_variante_id.value = btn.getAttribute('data-option-variante-id') || '';
        }
        if (fields.option_couleur) {
          fields.option_couleur.value = btn.getAttribute('data-option-couleur') || '';
        }
        if (fields.option_poids) {
          fields.option_poids.value = btn.getAttribute('data-option-poids') || '';
        }
        if (fields.option_taille) {
          fields.option_taille.value = btn.getAttribute('data-option-taille') || '';
        }
        if (fields.option_variante_nom) {
          fields.option_variante_nom.value = btn.getAttribute('data-option-variante-nom') || '';
        }
        if (fields.option_variante_image) {
          fields.option_variante_image.value = btn.getAttribute('data-option-variante-image') || '';
        }
        if (fields.option_surcout_poids) {
          fields.option_surcout_poids.value = btn.getAttribute('data-option-surcout-poids') || '';
        }
        if (fields.option_surcout_taille) {
          fields.option_surcout_taille.value = btn.getAttribute('data-option-surcout-taille') || '';
        }

        if (proposeInput) {
          proposeInput.value = '';
        }

        openModalEl(modal);
        if (proposeInput) proposeInput.focus();
      });
    });

    bindModalClose(modal, '[data-prix-neg-client-close]');

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModalEl(modal);
      }
    });
  }

  function initVendorOffersPanels() {
    function openOffersPanel(targetId) {
      var panel = targetId ? document.getElementById(targetId) : null;
      if (panel) openModalEl(panel);
    }

    document.querySelectorAll('.prix-neg-produit-card[data-prix-neg-offers-open]').forEach(function (card) {
      var targetId = card.getAttribute('data-prix-neg-offers-open');
      if (!targetId || !document.getElementById(targetId)) return;

      card.addEventListener('click', function () {
        openOffersPanel(targetId);
      });

      card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openOffersPanel(targetId);
        }
      });
    });

    document.querySelectorAll('[data-prix-neg-offers-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var panel = btn.closest('.prix-neg-fullscreen');
        closeModalEl(panel);
      });
    });
  }

  function initFullscreenPanels() {
    document.querySelectorAll('[data-prix-neg-full-open]').forEach(function (btn) {
      var targetId = btn.getAttribute('data-prix-neg-full-open');
      var panel = targetId ? document.getElementById(targetId) : null;
      if (!panel) return;
      btn.addEventListener('click', function () {
        openModalEl(panel);
      });
    });

    document.querySelectorAll('[data-prix-neg-full-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var panel = btn.closest('.prix-neg-fullscreen');
        closeModalEl(panel);
      });
    });
  }

  function initRejectOverlays() {
    document.addEventListener('click', function (e) {
      var closeEl = e.target.closest('[data-prix-neg-reject-close]');
      if (!closeEl) return;
      var overlay = closeEl.closest('.prix-neg-reject-overlay');
      if (!overlay || !overlay.classList.contains('is-open')) return;
      e.preventDefault();
      closeModalEl(overlay);
    });

    document.querySelectorAll('[data-prix-neg-reject-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var overlayId = btn.getAttribute('data-prix-neg-reject-open');
        var overlay = overlayId ? document.getElementById(overlayId) : null;
        if (!overlay) return;
        document.querySelectorAll('.prix-neg-reject-overlay.is-open').forEach(function (o) {
          closeModalEl(o);
        });
        var form = overlay.querySelector('[data-prix-neg-reject-form]');
        var priceInput = overlay.querySelector('[data-prix-neg-reject-input]');
        if (priceInput) priceInput.value = '';
        if (form) {
          var evt = new Event('input', { bubbles: true });
          if (priceInput) priceInput.dispatchEvent(evt);
        }
        openModalEl(overlay);
      });
    });

    document.querySelectorAll('.prix-neg-reject-overlay').forEach(function (overlay) {
      var form = overlay.querySelector('[data-prix-neg-reject-form]');
      if (!form) return;

      var actionInput = form.querySelector('[data-prix-neg-reject-action]');
      var priceInput = form.querySelector('[data-prix-neg-reject-input]');
      var submitBtn = form.querySelector('[data-prix-neg-reject-submit]');

      function syncRejectForm() {
        if (!actionInput || !submitBtn) return;
        var val = priceInput ? parseFloat(priceInput.value) : 0;
        if (val > 0) {
          actionInput.value = 'reject_counter';
          submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer ma proposition';
          submitBtn.classList.remove('prix-neg-btn--reject');
          submitBtn.classList.add('prix-neg-btn--accept');
        } else {
          actionInput.value = 'reject_final';
          submitBtn.innerHTML = '<i class="fas fa-times"></i> Rejeter l\'offre';
          submitBtn.classList.remove('prix-neg-btn--accept');
          submitBtn.classList.add('prix-neg-btn--reject');
        }
      }

      if (priceInput) {
        priceInput.addEventListener('input', syncRejectForm);
      }
      syncRejectForm();
    });
  }

  function initAll() {
    portalOverlaysToBody();
    initNegociationModal();
    initLoginModal();
    initClientProposeModal();
    initVendorOffersPanels();
    initFullscreenPanels();
    initRejectOverlays();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
