(function () {
    'use strict';

    var overlay = document.getElementById('slAddOverlay');
    if (!overlay) {
        return;
    }

    var form = document.getElementById('slAddForm');
    var dropZone = document.getElementById('slAddDrop');
    var fileInput = document.getElementById('slAddImage');
    var previewWrap = document.getElementById('slAddPreview');
    var previewImg = document.getElementById('slAddPreviewImg');
    var submitBtn = document.getElementById('slAddSubmit');
    var openTriggers = document.querySelectorAll('[data-sl-open-add]');
    var closeTriggers = overlay.querySelectorAll('[data-sl-close-add]');
    var objectUrl = null;

    function setBodyLock(locked) {
        document.body.classList.toggle('sl-add-modal-open', !!locked);
    }

    function revokePreviewUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function updateSubmitState() {
        if (!submitBtn || !fileInput) {
            return;
        }
        var hasFile = fileInput.files && fileInput.files.length > 0;
        submitBtn.disabled = !hasFile;
    }

    function clearPreview() {
        revokePreviewUrl();
        if (previewImg) {
            previewImg.removeAttribute('src');
        }
        if (dropZone) {
            dropZone.classList.remove('is-has-preview');
        }
        if (fileInput) {
            fileInput.value = '';
        }
        updateSubmitState();
    }

    function showPreview(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }
        revokePreviewUrl();
        objectUrl = URL.createObjectURL(file);
        if (previewImg) {
            previewImg.src = objectUrl;
            previewImg.alt = file.name || 'Aperçu de l\'affiche';
        }
        if (dropZone) {
            dropZone.classList.add('is-has-preview');
        }
        updateSubmitState();
    }

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        setBodyLock(true);
        var closeBtn = overlay.querySelector('.sl-add-modal__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        setBodyLock(false);
        clearPreview();
    }

    function handleFiles(fileList) {
        if (!fileList || !fileList.length) {
            return;
        }
        var file = fileList[0];
        if (!fileInput) {
            return;
        }
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
        } catch (e) {
            return;
        }
        showPreview(file);
    }

    openTriggers.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    });

    closeTriggers.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            closeModal();
        });
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) {
            closeModal();
        }
    });

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                showPreview(fileInput.files[0]);
            } else {
                clearPreview();
            }
        });
    }

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(function (evtName) {
            dropZone.addEventListener(evtName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (evtName) {
            dropZone.addEventListener(evtName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('is-dragover');
            });
        });

        dropZone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files) {
                handleFiles(e.dataTransfer.files);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…';
            }
        });
    }

    if (overlay.getAttribute('data-open-on-load') === '1') {
        openModal();
    }

    updateSubmitState();
})();
