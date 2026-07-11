(function () {
    'use strict';

    function initImageModal(config) {
        var overlay = document.getElementById(config.overlayId);
        if (!overlay) {
            return;
        }

        var form = document.getElementById(config.formId);
        var dropZone = document.getElementById(config.dropId);
        var fileInput = document.getElementById(config.fileInputId);
        var previewImg = document.getElementById(config.previewImgId);
        var submitBtn = document.getElementById(config.submitId);
        var openTriggers = document.querySelectorAll(config.openSelector);
        var closeTriggers = overlay.querySelectorAll('[data-sl-close-modal]');
        var objectUrl = null;
        var currentRemoteSrc = '';

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
            submitBtn.disabled = config.requireFile ? !hasFile : false;
        }

        function showRemotePreview(src) {
            currentRemoteSrc = src || '';
            revokePreviewUrl();
            if (previewImg && currentRemoteSrc !== '') {
                previewImg.src = currentRemoteSrc;
                previewImg.alt = 'Image actuelle';
            }
            if (dropZone) {
                dropZone.classList.add('is-has-preview');
            }
            updateSubmitState();
        }

        function clearPreview() {
            revokePreviewUrl();
            currentRemoteSrc = '';
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

        function showFilePreview(file) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                return;
            }
            revokePreviewUrl();
            currentRemoteSrc = '';
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

        function openModal(payload) {
            if (typeof config.onOpen === 'function') {
                config.onOpen(payload || {});
            }
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
            if (typeof config.onClose === 'function') {
                config.onClose();
            }
        }

        function handleFiles(fileList) {
            if (!fileList || !fileList.length || !fileInput) {
                return;
            }
            try {
                var dt = new DataTransfer();
                dt.items.add(fileList[0]);
                fileInput.files = dt.files;
            } catch (e) {
                return;
            }
            showFilePreview(fileList[0]);
        }

        openTriggers.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openModal({
                    slideId: btn.getAttribute('data-slide-id') || '',
                    imageSrc: btn.getAttribute('data-slide-img') || ''
                });
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

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files[0]) {
                    showFilePreview(fileInput.files[0]);
                } else if (config.fallbackRemoteOnClear && currentRemoteSrc) {
                    showRemotePreview(currentRemoteSrc);
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

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.hidden) {
                closeModal();
            }
        });

        if (overlay.getAttribute('data-open-on-load') === '1') {
            var loadPayload = {};
            if (config.slideIdInput) {
                loadPayload.slideId = config.slideIdInput.value || '';
            }
            if (config.initialImageSrc) {
                loadPayload.imageSrc = config.initialImageSrc;
            }
            openModal(loadPayload);
        }

        return {
            openModal: openModal,
            closeModal: closeModal,
            showRemotePreview: showRemotePreview,
            clearPreview: clearPreview,
            updateSubmitState: updateSubmitState
        };
    }

    var editSlideIdInput = document.getElementById('slEditSlideId');

    var addModal = initImageModal({
        overlayId: 'slAddOverlay',
        formId: 'slAddForm',
        dropId: 'slAddDrop',
        fileInputId: 'slAddImage',
        previewImgId: 'slAddPreviewImg',
        submitId: 'slAddSubmit',
        openSelector: '[data-sl-open-add]',
        requireFile: true
    });

    initImageModal({
        overlayId: 'slEditOverlay',
        formId: 'slEditForm',
        dropId: 'slEditDrop',
        fileInputId: 'slEditImage',
        previewImgId: 'slEditPreviewImg',
        submitId: 'slEditSubmit',
        openSelector: '[data-sl-open-edit]',
        requireFile: true,
        slideIdInput: editSlideIdInput,
        initialImageSrc: editSlideIdInput ? (editSlideIdInput.getAttribute('data-initial-img') || '') : '',
        onOpen: function (payload) {
            if (editSlideIdInput) {
                editSlideIdInput.value = payload.slideId || '';
            }
            if (payload.imageSrc) {
                var previewImg = document.getElementById('slEditPreviewImg');
                var dropZone = document.getElementById('slEditDrop');
                if (previewImg) {
                    previewImg.src = payload.imageSrc;
                    previewImg.alt = 'Image actuelle';
                }
                if (dropZone) {
                    dropZone.classList.add('is-has-preview');
                }
            }
            var submitBtn = document.getElementById('slEditSubmit');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        },
        onClose: function () {
            if (editSlideIdInput) {
                editSlideIdInput.value = '';
                editSlideIdInput.removeAttribute('data-initial-img');
            }
        }
    });

    if (addModal) {
        addModal.updateSubmitState();
    }
})();
