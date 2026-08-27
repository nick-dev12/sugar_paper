/**
 * Admin catalogue personnalisé — formulaires reveal
 */
document.addEventListener('DOMContentLoaded', function () {
  var addCard = document.getElementById('add-dossier-card') || document.getElementById('add-produit-card');
  var revealBtn = document.getElementById('btn-add-dossier') || document.getElementById('btn-add-produit');
  var revealForm = document.getElementById('form-add-dossier') || document.getElementById('form-add-produit');

  if (addCard && revealBtn && revealForm) {
    revealBtn.addEventListener('click', function () {
      addCard.classList.add('is-open');
      revealForm.classList.add('is-open');
      revealForm.removeAttribute('hidden');
    });

    addCard.querySelectorAll('.btn-cancel-reveal').forEach(function (btn) {
      btn.addEventListener('click', function () {
        addCard.classList.remove('is-open');
        revealForm.classList.remove('is-open');
        if (revealForm.tagName === 'FORM') {
          revealForm.reset();
        }
      });
    });
  }

  document.querySelectorAll('.btn-edit-dossier').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-dossier-id');
      var form = document.getElementById('edit-dossier-' + id);
      if (!form) {
        return;
      }
      document.querySelectorAll('.cp-edit-dossier-form').forEach(function (f) {
        if (f !== form) {
          f.hidden = true;
          f.classList.remove('is-open');
        }
      });
      form.hidden = false;
      form.classList.add('is-open');
    });
  });

  document.querySelectorAll('.btn-cancel-edit-dossier').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('.cp-edit-dossier-form');
      if (form) {
        form.hidden = true;
        form.classList.remove('is-open');
      }
    });
  });
});
