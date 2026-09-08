/**
 * Personnalisation contours de gâteau — 3 bandes sur feuille A4/A3
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-personnalisation-contours');
    if (!modal) {
        return;
    }

    var PAPERS = {
        a4: { label: 'A4', widthCm: 21, heightCm: 29.7, canvasW: 595, canvasH: 842 },
        a3: { label: 'A3', widthCm: 29.7, heightCm: 42, canvasW: 701, canvasH: 992 }
    };
    var CONTOUR_COUNT = 3;
    var IMAGE_SCALE_MIN = 50;
    var IMAGE_SCALE_MAX = 400;
    var EDGE_MARGIN_CM = 0.7;
    var GAP_CM = 0.9;
    var HEIGHT_MIN_CM = 2;
    var HEIGHT_MAX_HARD_CM = 12;
    var CORNER_RATIO = 0.22;

    var btnClose = document.getElementById('contours-modal-close');
    var btnCancel = document.getElementById('contours-cancel');
    var btnValidate = document.getElementById('contours-validate');
    var fileInput = document.getElementById('contours-image-input');
    var uploadText = document.getElementById('contours-upload-text');
    var filenameEl = document.getElementById('contours-upload-filename');
    var canvas = document.getElementById('contours-preview-canvas');
    var previewViewport = document.getElementById('contours-preview-viewport');
    var imageManipulator = document.getElementById('contours-image-manipulator');
    var imageManipBox = document.getElementById('contours-image-manip-box');
    var imageHintEl = document.getElementById('contours-image-hint');
    var imageResetBtn = document.getElementById('contours-image-reset');
    var imageDeleteBtn = document.getElementById('contours-image-delete');
    var dimHeight = document.getElementById('contours-dim-height');
    var dimHeightVal = document.getElementById('contours-dim-height-val');
    var paperInfoEl = document.getElementById('contours-paper-info');
    var activeLabel = document.getElementById('contours-active-label');
    var paperBtns = modal.querySelectorAll('.perso-paper-btn');
    var modeBtns = modal.querySelectorAll('.perso-image-mode-btn');

    var activeForm = null;
    var lastRenderLayout = null;
    var manipDrag = null;

    var state = {
        format: 'a4',
        heightCm: 5,
        imageMode: 'shared',
        activeIndex: -1,
        shared: makeSlot(),
        contours: []
    };

    for (var i = 0; i < CONTOUR_COUNT; i++) {
        state.contours.push(makeSlot());
    }

    function makeSlot() {
        return {
            image: null,
            file: null,
            objectUrl: '',
            offsetX: 50,
            offsetY: 50,
            scalePct: 100,
            filename: ''
        };
    }

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function clampImageOffset(value) {
        return clamp(Math.round(value), -50, 150);
    }

    function clampImageScale(value) {
        return clamp(Math.round(value), IMAGE_SCALE_MIN, IMAGE_SCALE_MAX);
    }

    function formatCm(value) {
        return String(Math.round(value * 10) / 10).replace('.', ',');
    }

    function getPaper() {
        return PAPERS[state.format] || PAPERS.a4;
    }

    function getMaxHeightCm() {
        var paper = getPaper();
        var usable = Math.max(1, paper.heightCm - EDGE_MARGIN_CM * 2);
        var maxFit = (usable - (CONTOUR_COUNT - 1) * GAP_CM) / CONTOUR_COUNT;
        return Math.max(HEIGHT_MIN_CM, Math.min(HEIGHT_MAX_HARD_CM, Math.floor(maxFit * 10) / 10));
    }

    function getActiveSlot() {
        if (state.activeIndex < 0) {
            return null;
        }
        if (state.imageMode === 'shared') {
            return state.shared;
        }
        return state.contours[state.activeIndex] || null;
    }

    function hasSelection() {
        return state.activeIndex >= 0;
    }

    function clearSelection() {
        state.activeIndex = -1;
        manipDrag = null;
        hideImageManipulator();
        if (activeLabel) {
            activeLabel.textContent = '—';
        }
        updateImageUi();
        renderPreview();
    }

    function resetSlotTransform(slot) {
        slot.offsetX = 50;
        slot.offsetY = 50;
        slot.scalePct = 100;
    }

    function revokeSlotUrl(slot) {
        if (slot.objectUrl) {
            URL.revokeObjectURL(slot.objectUrl);
            slot.objectUrl = '';
        }
    }

    function clearSlotImage(slot) {
        revokeSlotUrl(slot);
        slot.image = null;
        slot.file = null;
        slot.filename = '';
        resetSlotTransform(slot);
    }

    function getPaperBounds(canvasW, canvasH) {
        var paper = getPaper();
        var pad = 8;
        var availW = canvasW - pad * 2;
        var availH = canvasH - pad * 2;
        var aspect = paper.widthCm / paper.heightCm;
        var w;
        var h;
        if (availW / availH > aspect) {
            h = availH;
            w = h * aspect;
        } else {
            w = availW;
            h = w / aspect;
        }
        return {
            x: (canvasW - w) / 2,
            y: (canvasH - h) / 2,
            w: w,
            h: h,
            pxPerCm: w / paper.widthCm
        };
    }

    function getContourLayouts(paperBounds) {
        var edge = EDGE_MARGIN_CM * paperBounds.pxPerCm;
        var gapPx = GAP_CM * paperBounds.pxPerCm;
        var usableW = Math.max(1, paperBounds.w - edge * 2);
        var usableH = Math.max(1, paperBounds.h - edge * 2);
        var maxHFit = (usableH - (CONTOUR_COUNT - 1) * gapPx) / CONTOUR_COUNT;
        var hPx = Math.min(state.heightCm * paperBounds.pxPerCm, maxHFit);
        var wPx = usableW;
        var totalH = CONTOUR_COUNT * hPx + (CONTOUR_COUNT - 1) * gapPx;
        var startX = paperBounds.x + edge;
        var startY = paperBounds.y + (paperBounds.h - totalH) / 2;
        var layouts = [];
        for (var idx = 0; idx < CONTOUR_COUNT; idx++) {
            var y = startY + idx * (hPx + gapPx);
            layouts.push({
                index: idx,
                x: startX,
                y: y,
                w: wPx,
                h: hPx,
                cx: startX + wPx / 2,
                cy: y + hPx / 2,
                r: Math.min(hPx * CORNER_RATIO, wPx * 0.08)
            });
        }
        return layouts;
    }

    function appendContourPath(ctx, bounds) {
        var x = bounds.x;
        var y = bounds.y;
        var w = bounds.w;
        var h = bounds.h;
        var r = Math.max(2, Math.min(bounds.r || 8, h / 2, w / 2));
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function getImageDrawParams(bounds, img, slot) {
        if (!img || !bounds || !slot) {
            return null;
        }
        var coverScale = Math.max(bounds.w / img.width, bounds.h / img.height);
        var userScale = (slot.scalePct || 100) / 100;
        var scale = coverScale * userScale;
        var dw = img.width * scale;
        var dh = img.height * scale;
        var cx = bounds.x + bounds.w * ((slot.offsetX || 50) / 100);
        var cy = bounds.y + bounds.h * ((slot.offsetY || 50) / 100);
        return {
            x: cx - dw / 2,
            y: cy - dh / 2,
            w: dw,
            h: dh,
            cx: cx,
            cy: cy
        };
    }

    function drawSlotContent(ctx, bounds, slot) {
        ctx.save();
        appendContourPath(ctx, bounds);
        ctx.clip();
        if (slot && slot.image) {
            var params = getImageDrawParams(bounds, slot.image, slot);
            if (params) {
                ctx.drawImage(slot.image, params.x, params.y, params.w, params.h);
            }
        } else {
            ctx.fillStyle = 'rgba(194, 102, 56, 0.10)';
            appendContourPath(ctx, bounds);
            ctx.fill();
        }
        ctx.restore();

        ctx.save();
        var isActive = hasSelection() && bounds.index === state.activeIndex;
        ctx.strokeStyle = isActive ? '#c26638' : 'rgba(42, 26, 34, 0.35)';
        ctx.lineWidth = isActive ? 3 : 1.5;
        appendContourPath(ctx, bounds);
        ctx.stroke();
        ctx.restore();
    }

    function slotForIndex(index) {
        if (state.imageMode === 'shared') {
            return state.shared;
        }
        return state.contours[index] || null;
    }

    function hasContent() {
        if (state.imageMode === 'shared') {
            return !!state.shared.image;
        }
        return state.contours.some(function (slot) {
            return !!slot.image;
        });
    }

    function updateValidateState() {
        if (btnValidate) {
            btnValidate.disabled = !hasContent();
        }
    }

    function updateModeUi() {
        modeBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-mode') === state.imageMode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (uploadText) {
            uploadText.textContent = state.imageMode === 'shared'
                ? 'Importer une image (tous les contours)'
                : 'Importer pour le contour sélectionné';
        }
        if (activeLabel) {
            activeLabel.textContent = hasSelection() ? String(state.activeIndex + 1) : '—';
        }
        updateImageUi();
    }

    function updatePaperUi() {
        var paper = getPaper();
        var maxH = getMaxHeightCm();
        state.heightCm = clamp(state.heightCm, HEIGHT_MIN_CM, maxH);
        if (paperInfoEl) {
            paperInfoEl.textContent = 'Feuille ' + paper.label + ' — '
                + formatCm(paper.widthCm) + ' × ' + formatCm(paper.heightCm)
                + ' cm · 3 contours max.';
        }
        paperBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-paper') === state.format;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (dimHeight) {
            dimHeight.max = String(maxH);
            dimHeight.value = String(state.heightCm);
        }
        if (dimHeightVal) {
            dimHeightVal.textContent = formatCm(state.heightCm);
        }
        var ratio = paper.widthCm + ' / ' + paper.heightCm;
        if (previewViewport) {
            previewViewport.style.aspectRatio = ratio;
        }
        if (canvas) {
            canvas.style.aspectRatio = ratio;
        }
    }

    function updateImageUi() {
        var slot = getActiveSlot();
        var hasImg = !!(slot && slot.image);
        if (imageHintEl) {
            imageHintEl.hidden = !hasImg;
        }
        if (imageResetBtn) {
            imageResetBtn.hidden = !hasImg;
        }
        if (filenameEl) {
            if (hasImg) {
                filenameEl.textContent = slot.filename || 'Image sélectionnée';
            } else if (state.imageMode === 'shared' && state.shared.image) {
                filenameEl.textContent = state.shared.filename || 'Image sélectionnée';
            } else {
                filenameEl.textContent = '';
            }
        }
        if (!hasImg) {
            hideImageManipulator();
        }
    }

    function canvasPointFromEvent(event) {
        if (!canvas) {
            return null;
        }
        var rect = canvas.getBoundingClientRect();
        var clientX = event.clientX;
        var clientY = event.clientY;
        if (event.touches && event.touches[0]) {
            clientX = event.touches[0].clientX;
            clientY = event.touches[0].clientY;
        }
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function hitTestContour(x, y) {
        if (!lastRenderLayout || !lastRenderLayout.contours) {
            return -1;
        }
        for (var i = 0; i < lastRenderLayout.contours.length; i++) {
            var b = lastRenderLayout.contours[i];
            if (x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h) {
                return i;
            }
        }
        return -1;
    }

    function hideImageManipulator() {
        if (!imageManipulator) {
            return;
        }
        imageManipulator.hidden = true;
        imageManipulator.setAttribute('aria-hidden', 'true');
    }

    function updateImageManipulator() {
        if (!imageManipulator || !imageManipBox || !lastRenderLayout || !canvas) {
            return;
        }
        if (!hasSelection()) {
            hideImageManipulator();
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        var slot = getActiveSlot();
        if (!bounds || !slot || !slot.image) {
            hideImageManipulator();
            return;
        }
        var params = getImageDrawParams(bounds, slot.image, slot);
        if (!params) {
            hideImageManipulator();
            return;
        }
        var rect = canvas.getBoundingClientRect();
        var scaleX = rect.width / canvas.width;
        var scaleY = rect.height / canvas.height;
        imageManipulator.hidden = false;
        imageManipulator.setAttribute('aria-hidden', 'false');
        imageManipBox.style.left = (params.x * scaleX) + 'px';
        imageManipBox.style.top = (params.y * scaleY) + 'px';
        imageManipBox.style.width = (params.w * scaleX) + 'px';
        imageManipBox.style.height = (params.h * scaleY) + 'px';
    }

    function renderPreview() {
        if (!canvas) {
            return;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        var paper = getPaper();
        if (canvas.width !== paper.canvasW) {
            canvas.width = paper.canvasW;
        }
        if (canvas.height !== paper.canvasH) {
            canvas.height = paper.canvasH;
        }
        ctx.clearRect(0, 0, paper.canvasW, paper.canvasH);

        ctx.fillStyle = 'rgba(194, 102, 56, 0.06)';
        ctx.fillRect(0, 0, paper.canvasW, paper.canvasH);

        var paperBounds = getPaperBounds(paper.canvasW, paper.canvasH);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);
        ctx.strokeStyle = 'rgba(42, 26, 34, 0.28)';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);

        var layouts = getContourLayouts(paperBounds);
        layouts.forEach(function (bounds) {
            drawSlotContent(ctx, bounds, slotForIndex(bounds.index));
        });

        lastRenderLayout = {
            paperBounds: paperBounds,
            contours: layouts
        };
        updateImageManipulator();
        updateValidateState();
        syncMetaToForm();
    }

    function getActiveContext() {
        var form = activeForm || document.getElementById('add-to-panier-form');
        if (!form) {
            return {};
        }
        return {
            form: form,
            hiddenPath: form.querySelector('.option-image-personnalisation') || document.getElementById('option-image-personnalisation'),
            hiddenMeta: form.querySelector('.option-perso-meta') || document.getElementById('option-perso-meta'),
            formFileInput: form.querySelector('.form-image-personnalisation') || document.getElementById('form-image-personnalisation'),
            formSourceFileInput: form.querySelector('.form-image-personnalisation-source') || document.getElementById('form-image-personnalisation-source'),
            statusBox: form.querySelector('.perso-status') || document.getElementById('perso-status'),
            statusThumb: form.querySelector('.perso-status-thumb') || document.getElementById('perso-status-thumb'),
            btnOpen: document.getElementById('btn-personnaliser-contours') || document.querySelector('.js-open-contours-perso-modal')
        };
    }

    function buildMetaObject() {
        var paper = getPaper();
        var usableWidth = Math.max(1, paper.widthCm - EDGE_MARGIN_CM * 2);
        var contoursMeta = state.contours.map(function (slot) {
            return {
                offset_x: slot.offsetX,
                offset_y: slot.offsetY,
                scale_pct: slot.scalePct
            };
        });
        var meta = {
            type: 'contours_gateau',
            format: state.format,
            height_cm: state.heightCm,
            width_cm: Math.round(usableWidth * 10) / 10,
            image_mode: state.imageMode,
            contours: contoursMeta
        };
        if (state.imageMode === 'shared') {
            meta.image = {
                offset_x: state.shared.offsetX,
                offset_y: state.shared.offsetY,
                scale_pct: state.shared.scalePct
            };
        }
        return meta;
    }

    function syncMetaToForm() {
        var ctx = getActiveContext();
        if (ctx.hiddenMeta) {
            ctx.hiddenMeta.value = JSON.stringify(buildMetaObject());
        }
    }

    function assignFileToForm(file, formFileInput) {
        if (!formFileInput || !file || typeof DataTransfer === 'undefined') {
            return false;
        }
        var dt = new DataTransfer();
        dt.items.add(file);
        formFileInput.files = dt.files;
        return formFileInput.files.length > 0;
    }

    function canvasToFile(callback) {
        if (!canvas) {
            callback(null);
            return;
        }
        canvas.toBlob(function (blob) {
            if (!blob) {
                callback(null);
                return;
            }
            callback(new File([blob], 'personnalisation-contours.png', { type: 'image/png' }));
        }, 'image/png', 0.92);
    }

    function updateStatus(active, previewUrl) {
        var ctx = getActiveContext();
        if (ctx.hiddenPath) {
            ctx.hiddenPath.value = active ? 'pending' : '';
        }
        syncMetaToForm();
        if (ctx.statusBox) {
            ctx.statusBox.classList.toggle('is-visible', !!active);
        }
        if (ctx.statusThumb && previewUrl) {
            ctx.statusThumb.src = previewUrl;
        }
        if (ctx.btnOpen) {
            ctx.btnOpen.classList.toggle('is-active', !!active);
        }
    }

    function loadFileIntoSlot(file, slot) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }
        revokeSlotUrl(slot);
        slot.file = file;
        slot.filename = file.name || '';
        slot.objectUrl = URL.createObjectURL(file);
        resetSlotTransform(slot);
        var img = new Image();
        img.onload = function () {
            slot.image = img;
            updateImageUi();
            renderPreview();
            if (fileInput) {
                fileInput.value = '';
            }
        };
        img.onerror = function () {
            clearSlotImage(slot);
            window.alert('Impossible de charger cette image.');
            updateImageUi();
            renderPreview();
        };
        img.src = slot.objectUrl;
    }

    function onFileSelected(file) {
        if (state.imageMode === 'shared') {
            loadFileIntoSlot(file, state.shared);
            if (!hasSelection()) {
                state.activeIndex = 0;
                if (activeLabel) {
                    activeLabel.textContent = '1';
                }
            }
            updateImageUi();
            renderPreview();
            return;
        }
        if (!hasSelection()) {
            state.activeIndex = 0;
            if (activeLabel) {
                activeLabel.textContent = '1';
            }
        }
        var slot = getActiveSlot();
        if (!slot) {
            return;
        }
        loadFileIntoSlot(file, slot);
    }

    function selectContour(index, openPickerIfEmpty) {
        if (index < 0 || index >= CONTOUR_COUNT) {
            clearSelection();
            return;
        }
        state.activeIndex = index;
        if (activeLabel) {
            activeLabel.textContent = String(index + 1);
        }
        updateImageUi();
        renderPreview();
        var slot = getActiveSlot();
        if (openPickerIfEmpty && state.imageMode === 'per_contour' && slot && !slot.image && fileInput) {
            fileInput.click();
        }
    }

    function resetState() {
        clearSlotImage(state.shared);
        state.contours.forEach(clearSlotImage);
        state.format = 'a4';
        state.heightCm = 5;
        state.imageMode = 'shared';
        state.activeIndex = -1;
        if (fileInput) {
            fileInput.value = '';
        }
        updateModeUi();
        updatePaperUi();
        hideImageManipulator();
        renderPreview();
    }

    function openModalForButton(btn) {
        var formId = btn.getAttribute('data-perso-form');
        var newForm = formId ? document.getElementById(formId) : document.getElementById('add-to-panier-form');
        if (newForm !== activeForm) {
            resetState();
        }
        activeForm = newForm;
        if (btnValidate) {
            btnValidate.textContent = 'Valider et ajouter au panier';
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        updateModeUi();
        updatePaperUi();
        renderPreview();
    }

    function closeModal() {
        manipDrag = null;
        hideImageManipulator();
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function applyImageMove(slot, canvasX, canvasY, startX, startY, startOffsetX, startOffsetY) {
        if (!lastRenderLayout || !hasSelection()) {
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        if (!bounds || !slot) {
            return;
        }
        if (typeof startX === 'number') {
            var dx = canvasX - startX;
            var dy = canvasY - startY;
            slot.offsetX = clampImageOffset(startOffsetX + (dx / bounds.w) * 100);
            slot.offsetY = clampImageOffset(startOffsetY + (dy / bounds.h) * 100);
            return;
        }
        slot.offsetX = clampImageOffset(((canvasX - bounds.x) / bounds.w) * 100);
        slot.offsetY = clampImageOffset(((canvasY - bounds.y) / bounds.h) * 100);
    }

    function applyImageZoomAt(slot, newScalePct, focalX, focalY) {
        if (!slot || !slot.image || !lastRenderLayout) {
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        if (!bounds) {
            return;
        }
        var before = getImageDrawParams(bounds, slot.image, slot);
        if (!before || before.w <= 0 || before.h <= 0) {
            slot.scalePct = clampImageScale(newScalePct);
            return;
        }
        var fracX = (focalX - before.x) / before.w;
        var fracY = (focalY - before.y) / before.h;
        slot.scalePct = clampImageScale(newScalePct);
        var after = getImageDrawParams(bounds, slot.image, slot);
        if (!after) {
            return;
        }
        var newCx = focalX - fracX * after.w + after.w / 2;
        var newCy = focalY - fracY * after.h + after.h / 2;
        slot.offsetX = clampImageOffset(((newCx - bounds.x) / bounds.w) * 100);
        slot.offsetY = clampImageOffset(((newCy - bounds.y) / bounds.h) * 100);
    }

    function removeActiveImage() {
        var slot = getActiveSlot();
        if (!slot) {
            return;
        }
        clearSlotImage(slot);
        if (fileInput) {
            fileInput.value = '';
        }
        updateImageUi();
        renderPreview();
    }

    function validateCustomization() {
        if (!hasContent()) {
            return;
        }
        var ctx = getActiveContext();
        syncMetaToForm();
        canvasToFile(function (file) {
            if (!file) {
                window.alert('Impossible de générer l’aperçu. Réessayez.');
                return;
            }
            if (!assignFileToForm(file, ctx.formFileInput)) {
                window.alert('Votre navigateur ne permet pas d’ajouter cette image. Essayez un autre navigateur.');
                return;
            }
            var sourceFile = state.imageMode === 'shared'
                ? state.shared.file
                : (getActiveSlot() && getActiveSlot().file);
            if (sourceFile && ctx.formSourceFileInput) {
                assignFileToForm(sourceFile, ctx.formSourceFileInput);
            }
            updateStatus(true, canvas.toDataURL('image/png'));
            closeModal();
            var formToCheckout = activeForm || document.getElementById('add-to-panier-form');
            if (formToCheckout && window.SugarCheckoutModals && typeof window.SugarCheckoutModals.addFormToCartAndCheckout === 'function') {
                window.SugarCheckoutModals.addFormToCartAndCheckout(formToCheckout);
            } else if (formToCheckout) {
                formToCheckout.submit();
            }
        });
    }

    document.addEventListener('click', function (event) {
        var openBtn = event.target.closest('.js-open-contours-perso-modal, #btn-personnaliser-contours');
        if (openBtn) {
            event.preventDefault();
            openModalForButton(openBtn);
        }
    });

    if (btnClose) {
        btnClose.addEventListener('click', closeModal);
    }
    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }
    modal.querySelector('.perso-modal-backdrop').addEventListener('click', closeModal);
    if (btnValidate) {
        btnValidate.addEventListener('click', validateCustomization);
    }

    modeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.imageMode = btn.getAttribute('data-mode') || 'shared';
            updateModeUi();
            renderPreview();
        });
    });

    paperBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.format = btn.getAttribute('data-paper') || 'a4';
            updatePaperUi();
            renderPreview();
        });
    });

    if (dimHeight) {
        dimHeight.addEventListener('input', function () {
            state.heightCm = clamp(parseFloat(dimHeight.value) || 5, HEIGHT_MIN_CM, getMaxHeightCm());
            if (dimHeightVal) {
                dimHeightVal.textContent = formatCm(state.heightCm);
            }
            renderPreview();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                onFileSelected(fileInput.files[0]);
            }
        });
    }

    if (imageResetBtn) {
        imageResetBtn.addEventListener('click', function () {
            var slot = getActiveSlot();
            if (!slot) {
                return;
            }
            resetSlotTransform(slot);
            renderPreview();
        });
    }

    if (imageDeleteBtn) {
        imageDeleteBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            removeActiveImage();
        });
    }

    if (canvas) {
        canvas.addEventListener('pointerdown', function (event) {
            var pt = canvasPointFromEvent(event);
            if (!pt) {
                return;
            }
            var hit = hitTestContour(pt.x, pt.y);
            if (hit < 0) {
                clearSelection();
                return;
            }
            selectContour(hit, true);
            var slot = getActiveSlot();
            if (!slot || !slot.image) {
                return;
            }
            event.preventDefault();
            manipDrag = {
                type: 'move',
                startX: pt.x,
                startY: pt.y,
                startOffsetX: slot.offsetX,
                startOffsetY: slot.offsetY
            };
            try {
                canvas.setPointerCapture(event.pointerId);
            } catch (e) { /* ignore */ }
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!manipDrag) {
                return;
            }
            var pt = canvasPointFromEvent(event);
            var slot = getActiveSlot();
            if (!pt || !slot) {
                return;
            }
            event.preventDefault();
            if (manipDrag.type === 'move') {
                applyImageMove(slot, pt.x, pt.y, manipDrag.startX, manipDrag.startY, manipDrag.startOffsetX, manipDrag.startOffsetY);
            } else if (manipDrag.type === 'resize') {
                var bounds = lastRenderLayout && lastRenderLayout.contours[state.activeIndex];
                if (!bounds) {
                    return;
                }
                var params = getImageDrawParams(bounds, slot.image, slot);
                if (!params) {
                    return;
                }
                var dist = Math.hypot(pt.x - params.cx, pt.y - params.cy);
                if (manipDrag.startDist > 0) {
                    applyImageZoomAt(slot, manipDrag.startScale * (dist / manipDrag.startDist), params.cx, params.cy);
                }
            }
            renderPreview();
        });

        function endDrag() {
            manipDrag = null;
        }
        canvas.addEventListener('pointerup', endDrag);
        canvas.addEventListener('pointercancel', endDrag);

        canvas.addEventListener('wheel', function (event) {
            if (!hasSelection()) {
                return;
            }
            var slot = getActiveSlot();
            if (!slot || !slot.image) {
                return;
            }
            var pt = canvasPointFromEvent(event);
            if (!pt) {
                return;
            }
            event.preventDefault();
            var delta = event.deltaY > 0 ? -8 : 8;
            applyImageZoomAt(slot, slot.scalePct + delta, pt.x, pt.y);
            renderPreview();
        }, { passive: false });
    }

    if (imageManipBox) {
        imageManipBox.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest('.perso-image-handle');
            var slot = getActiveSlot();
            if (!slot || !slot.image || !lastRenderLayout) {
                return;
            }
            var pt = canvasPointFromEvent(event);
            if (!pt) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            if (handle) {
                var bounds = lastRenderLayout.contours[state.activeIndex];
                var params = getImageDrawParams(bounds, slot.image, slot);
                if (!params) {
                    return;
                }
                manipDrag = {
                    type: 'resize',
                    startDist: Math.hypot(pt.x - params.cx, pt.y - params.cy),
                    startScale: slot.scalePct
                };
            } else {
                manipDrag = {
                    type: 'move',
                    startX: pt.x,
                    startY: pt.y,
                    startOffsetX: slot.offsetX,
                    startOffsetY: slot.offsetY
                };
            }
        });
    }

    updateModeUi();
    updatePaperUi();
    renderPreview();
})();
