/**
 * Prévisualisation des images — formulaire certification vendeur (UI uniquement)
 */
(function () {
    'use strict';

    function resetUpload(box, input, preview, img, label, nameEl) {
        input.value = '';
        img.removeAttribute('src');
        preview.hidden = true;
        box.classList.remove('has-preview');
        if (label) {
            label.classList.remove('is-hidden');
        }
        if (nameEl) {
            nameEl.textContent = nameEl.getAttribute('data-default') || nameEl.textContent;
        }
    }

    function initCertUpload(box) {
        var input = box.querySelector('input[type="file"]');
        if (!input || box.dataset.certUploadReady === '1') {
            return;
        }
        box.dataset.certUploadReady = '1';

        var label = box.querySelector('label');
        var nameEl = box.querySelector('.cert-upload__name');
        if (nameEl && !nameEl.getAttribute('data-default')) {
            nameEl.setAttribute('data-default', nameEl.textContent);
        }

        var preview = document.createElement('div');
        preview.className = 'cert-upload__preview';
        preview.hidden = true;
        preview.innerHTML =
            '<img class="cert-upload__preview-img" alt="Aperçu de l\'image sélectionnée">' +
            '<p class="cert-upload__preview-filename"></p>' +
            '<button type="button" class="cert-upload__preview-remove">' +
            '<i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer et recommencer</button>';
        box.appendChild(preview);

        var img = preview.querySelector('.cert-upload__preview-img');
        var filenameEl = preview.querySelector('.cert-upload__preview-filename');
        var removeBtn = preview.querySelector('.cert-upload__preview-remove');

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                resetUpload(box, input, preview, img, label, nameEl);
                return;
            }
            if (!/^image\//.test(file.type)) {
                resetUpload(box, input, preview, img, label, nameEl);
                return;
            }

            var reader = new FileReader();
            reader.onload = function (ev) {
                img.src = ev.target && ev.target.result ? ev.target.result : '';
                if (filenameEl) {
                    filenameEl.textContent = file.name;
                }
                preview.hidden = false;
                box.classList.add('has-preview');
                if (label) {
                    label.classList.add('is-hidden');
                }
            };
            reader.readAsDataURL(file);
        });

        removeBtn.addEventListener('click', function () {
            resetUpload(box, input, preview, img, label, nameEl);
        });
    }

    function boot() {
        document.querySelectorAll('.cert-upload').forEach(initCertUpload);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
