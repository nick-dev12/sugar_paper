/**
 * Personnalisation photo comestible — prévisualisation sur modèle gâteau
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-personnalisation');
    if (!modal) {
        return;
    }

    var btnOpen = document.getElementById('btn-personnaliser');
    var btnClose = document.getElementById('perso-modal-close');
    var btnCancel = document.getElementById('perso-cancel');
    var btnValidate = document.getElementById('perso-validate');
    var fileInput = document.getElementById('perso-image-input');
    var formFileInput = document.getElementById('form-image-personnalisation');
    var uploadLabel = document.getElementById('perso-upload-label');
    var filenameEl = document.getElementById('perso-upload-filename');
    var printWrap = document.getElementById('gateau-preview-print');
    var printImg = document.getElementById('gateau-preview-print-img');
    var hiddenPath = document.getElementById('option-image-personnalisation');
    var statusBox = document.getElementById('perso-status');
    var statusThumb = document.getElementById('perso-status-thumb');

    var previewObjectUrl = '';
    var pendingFile = null;
    var hasCustomization = false;

    function revokePreviewUrl() {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = '';
        }
    }

    function showPreviewFromUrl(url) {
        if (!printWrap || !printImg || !url) {
            return;
        }
        printImg.src = url;
        printWrap.classList.add('has-image');
    }

    function clearPreview() {
        revokePreviewUrl();
        pendingFile = null;
        if (printImg) {
            printImg.removeAttribute('src');
        }
        if (printWrap) {
            printWrap.classList.remove('has-image');
        }
        if (filenameEl) {
            filenameEl.textContent = '';
        }
        if (fileInput) {
            fileInput.value = '';
        }
        if (btnValidate) {
            btnValidate.disabled = true;
        }
    }

    function assignFileToForm(file) {
        if (!formFileInput || !file || typeof DataTransfer === 'undefined') {
            return false;
        }
        var dt = new DataTransfer();
        dt.items.add(file);
        formFileInput.files = dt.files;
        return formFileInput.files.length > 0;
    }

    function updateStatus(active, previewUrl, label) {
        hasCustomization = !!active;
        if (hiddenPath) {
            hiddenPath.value = hasCustomization ? 'pending' : '';
        }
        if (statusBox) {
            statusBox.classList.toggle('is-visible', hasCustomization);
        }
        if (statusThumb && previewUrl) {
            statusThumb.src = previewUrl;
        }
        if (btnOpen) {
            btnOpen.classList.toggle('is-active', hasCustomization);
        }
        if (filenameEl && label) {
            filenameEl.textContent = label;
        }
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (hasCustomization && statusThumb && statusThumb.src) {
            showPreviewFromUrl(statusThumb.src);
            if (btnValidate) {
                btnValidate.disabled = false;
            }
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (!hasCustomization) {
            clearPreview();
        }
    }

    function onFileSelected(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }
        pendingFile = file;
        revokePreviewUrl();
        previewObjectUrl = URL.createObjectURL(file);
        showPreviewFromUrl(previewObjectUrl);
        if (filenameEl) {
            filenameEl.textContent = file.name;
        }
        if (btnValidate) {
            btnValidate.disabled = false;
        }
    }

    function validateCustomization() {
        if (pendingFile) {
            if (!assignFileToForm(pendingFile)) {
                window.alert('Votre navigateur ne permet pas d’ajouter cette image. Essayez une autre photo ou un autre navigateur.');
                return;
            }
            updateStatus(true, previewObjectUrl, pendingFile.name);
            pendingFile = null;
        }
        if (hasCustomization) {
            closeModal();
        }
    }

    if (btnOpen) {
        btnOpen.addEventListener('click', openModal);
    }
    if (btnClose) {
        btnClose.addEventListener('click', closeModal);
    }
    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }
    if (btnValidate) {
        btnValidate.addEventListener('click', validateCustomization);
    }

    modal.querySelector('.perso-modal-backdrop').addEventListener('click', closeModal);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                onFileSelected(fileInput.files[0]);
            }
        });
    }

    if (uploadLabel && fileInput) {
        uploadLabel.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });
    }
})();
