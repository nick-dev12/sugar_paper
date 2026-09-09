/**
 * Personnalisation contours de gâteau — 3 bandes sur feuille A4/A3
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-personnalisation-contours');
    if (!modal) {
        return;
    }

    // Feuilles en orientation paysage (largeur × hauteur)
    var PAPERS = {
        a4: { label: 'A4', widthCm: 29.7, heightCm: 21, canvasW: 842, canvasH: 595 },
        a3: { label: 'A3', widthCm: 42, heightCm: 29.7, canvasW: 992, canvasH: 701 }
    };
    var CONTOUR_COUNT = 3;
    var IMAGE_SCALE_MIN = 10;
    var IMAGE_SCALE_MAX = 800;
    var EDGE_MARGIN_CM = 0.7;
    var GAP_CM = 0.9;
    var HEIGHT_MIN_CM = 2;
    var HEIGHT_MAX_CM = 6;

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

    var textManipBox = document.getElementById('contours-text-manip-box');
    var textEngine = null;
    if (window.PersoTextEngine) {
        textEngine = window.PersoTextEngine.create({
            modal: modal,
            prefix: 'contours',
            getShape: function () {
                return 'rect';
            },
            getBounds: function () {
                if (!lastRenderLayout || !lastRenderLayout.contours || !lastRenderLayout.contours.length) {
                    return null;
                }
                if (state.activeIndex >= 0) {
                    return lastRenderLayout.contours[state.activeIndex];
                }
                return lastRenderLayout.contours[0];
            },
            isModalOpen: function () {
                return modal.classList.contains('is-open');
            },
            clientToCanvas: function (clientX, clientY) {
                if (!canvas) {
                    return { x: 0, y: 0 };
                }
                var rect = canvas.getBoundingClientRect();
                var scaleX = canvas.width / rect.width;
                var scaleY = canvas.height / rect.height;
                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            },
            canvasPointToViewport: function (cx, cy) {
                if (!canvas) {
                    return { x: 0, y: 0 };
                }
                var rect = canvas.getBoundingClientRect();
                return {
                    x: cx * (rect.width / canvas.width),
                    y: cy * (rect.height / canvas.height)
                };
            },
            isSharedMode: function () {
                return state.imageMode === 'shared';
            },
            getTextsStore: function () {
                if (state.imageMode === 'shared') {
                    return state.shared;
                }
                if (state.activeIndex < 0) {
                    selectContour(0, false);
                }
                return state.contours[state.activeIndex];
            },
            ensureSlotSelected: function () {
                if (state.imageMode === 'shared') {
                    return true;
                }
                if (state.activeIndex < 0) {
                    selectContour(0, false);
                }
                return state.activeIndex >= 0;
            },
            hasBackgroundImage: function () {
                var slot = getActiveSlot();
                return slotHasImages(slot);
            },
            onChange: function () {
                renderPreview();
            }
        });
        textEngine.resetStore(state.shared);
        state.contours.forEach(function (contour) {
            textEngine.resetStore(contour);
        });
        textEngine.bindUiEvents();
    }

    function mapTextsForMeta(texts) {
        if (!texts || !texts.length) {
            return [];
        }
        return texts.map(function (t) {
            return {
                id: t.id,
                text: t.text,
                font: t.font,
                textSizePct: t.textSizePct,
                textPosX: t.textPosX,
                textPosY: t.textPosY,
                textRotation: t.textRotation,
                wrapOnCircle: t.wrapOnCircle,
                wrapArcPosition: t.wrapArcPosition,
                textColor: t.textColor
            };
        }).filter(function (t) {
            return (t.text || '').trim() !== '';
        });
    }

    function getTextsForRender(index) {
        var store = state.imageMode === 'shared' ? state.shared : state.contours[index];
        return store && store.texts ? store.texts : [];
    }

    function makeSlot() {
        return {
            layers: [],
            activeLayerId: ''
        };
    }

    function createImageLayer() {
        return {
            id: 'lyr_' + Math.random().toString(36).slice(2, 10),
            image: null,
            file: null,
            objectUrl: '',
            offsetX: 50,
            offsetY: 50,
            scalePct: 100,
            filename: ''
        };
    }

    function getActiveLayer(slot) {
        if (!slot || !slot.layers || !slot.layers.length) {
            return null;
        }
        var i;
        for (i = 0; i < slot.layers.length; i++) {
            if (slot.layers[i].id === slot.activeLayerId) {
                return slot.layers[i];
            }
        }
        return slot.layers[slot.layers.length - 1];
    }

    function slotHasImages(slot) {
        if (!slot || !slot.layers) {
            return false;
        }
        return slot.layers.some(function (layer) {
            return !!layer.image;
        });
    }

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function clampImageOffset(value) {
        return clamp(Math.round(value), -100, 200);
    }

    function wheelZoomFactor(deltaY) {
        return Math.pow(1.002, -deltaY);
    }

    function bindModalDeselect() {
        modal.addEventListener('pointerdown', function (event) {
            if (!modal.classList.contains('is-open')) {
                return;
            }
            if (event.target.closest(
                '#contours-preview-canvas, .perso-text-manipulator, .perso-image-manipulator, ' +
                '.perso-text-manip-box, .perso-image-manip-box, .perso-manip-delete, ' +
                '.perso-toolbar, .perso-modal-actions'
            )) {
                return;
            }
            if (event.target.closest(
                'input, textarea, button, label, select, a, .perso-text-item, .perso-upload-compact, ' +
                '.perso-font-btn, .perso-paper-btn, .perso-shape-btn, .perso-image-mode-btn, .perso-wrap-btn, ' +
                '.perso-wrap-pos-btn, .perso-color-swatch, .perso-color-custom, .perso-text-add-btn, ' +
                '.perso-image-reset-btn, .perso-modal-close, .perso-btn, .perso-dimension-field, .perso-text-list'
            )) {
                return;
            }
            clearSelection();
        });
        if (previewViewport) {
            previewViewport.addEventListener('pointerdown', function (event) {
                if (!modal.classList.contains('is-open')) {
                    return;
                }
                if (event.target === canvas || event.target.closest('.perso-text-manipulator, .perso-image-manipulator')) {
                    return;
                }
                clearSelection();
            });
        }
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
        return Math.max(HEIGHT_MIN_CM, Math.min(HEIGHT_MAX_CM, Math.floor(maxFit * 10) / 10));
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
        if (textEngine) {
            textEngine.setEditTarget('');
            textEngine.hideManipulator();
        }
        if (activeLabel) {
            activeLabel.textContent = '—';
        }
        updateImageUi();
        renderPreview();
    }

    function resetLayerTransform(layer) {
        layer.offsetX = 50;
        layer.offsetY = 50;
        layer.scalePct = 100;
    }

    function revokeLayerUrl(layer) {
        if (layer.objectUrl) {
            URL.revokeObjectURL(layer.objectUrl);
            layer.objectUrl = '';
        }
    }

    function clearSlotImages(slot) {
        if (!slot) {
            return;
        }
        if (slot.layers) {
            slot.layers.forEach(revokeLayerUrl);
        }
        slot.layers = [];
        slot.activeLayerId = '';
    }

    function mapLayersForMeta(layers) {
        if (!layers || !layers.length) {
            return [];
        }
        return layers.filter(function (layer) {
            return !!layer.image;
        }).map(function (layer) {
            return {
                offset_x: layer.offsetX,
                offset_y: layer.offsetY,
                scale_pct: layer.scalePct
            };
        });
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
            });
        }
        return layouts;
    }

    function appendContourPath(ctx, bounds) {
        ctx.beginPath();
        ctx.rect(bounds.x, bounds.y, bounds.w, bounds.h);
        ctx.closePath();
    }

    function getImageDrawParams(bounds, img, layer) {
        if (!img || !bounds || !layer) {
            return null;
        }
        var coverScale = Math.max(bounds.w / img.width, bounds.h / img.height);
        var userScale = (layer.scalePct || 100) / 100;
        var scale = coverScale * userScale;
        var dw = img.width * scale;
        var dh = img.height * scale;
        var cx = bounds.x + bounds.w * ((layer.offsetX || 50) / 100);
        var cy = bounds.y + bounds.h * ((layer.offsetY || 50) / 100);
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
        if (slot && slot.layers && slot.layers.length) {
            slot.layers.forEach(function (layer) {
                if (!layer.image) {
                    return;
                }
                var params = getImageDrawParams(bounds, layer.image, layer);
                if (params) {
                    ctx.drawImage(layer.image, params.x, params.y, params.w, params.h);
                }
            });
        } else {
            ctx.fillStyle = 'rgba(194, 102, 56, 0.10)';
            appendContourPath(ctx, bounds);
            ctx.fill();
        }
        if (textEngine) {
            textEngine.drawTextsArray(ctx, bounds, 'rect', getTextsForRender(bounds.index));
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
        var hasImage = state.imageMode === 'shared'
            ? slotHasImages(state.shared)
            : state.contours.some(slotHasImages);
        if (hasImage) {
            return true;
        }
        if (!textEngine) {
            return false;
        }
        return textEngine.hasAnyTextContent(function () {
            if (state.imageMode === 'shared') {
                return [state.shared.texts || []];
            }
            return state.contours.map(function (slot) {
                return slot.texts || [];
            });
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
            paperInfoEl.textContent = 'Feuille ' + paper.label + ' paysage — '
                + formatCm(paper.widthCm) + ' × ' + formatCm(paper.heightCm)
                + ' cm · hauteur max. contour : ' + formatCm(maxH) + ' cm';
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
        if (dimHeight) {
            dimHeight.min = String(HEIGHT_MIN_CM);
        }
    }

    function updateImageUi() {
        var slot = getActiveSlot();
        var layer = slot ? getActiveLayer(slot) : null;
        var hasImg = !!(layer && layer.image);
        var layerCount = slot && slot.layers ? slot.layers.filter(function (l) { return !!l.image; }).length : 0;
        if (imageHintEl) {
            imageHintEl.hidden = !hasImg;
        }
        if (imageResetBtn) {
            imageResetBtn.hidden = !hasImg;
        }
        if (filenameEl) {
            if (hasImg) {
                var label = layer.filename || 'Image sélectionnée';
                filenameEl.textContent = layerCount > 1 ? label + ' (' + layerCount + ' images)' : label;
            } else if (state.imageMode === 'shared' && slotHasImages(state.shared)) {
                var sharedLayer = getActiveLayer(state.shared);
                var sharedCount = state.shared.layers.filter(function (l) { return !!l.image; }).length;
                var sharedLabel = sharedLayer.filename || 'Image sélectionnée';
                filenameEl.textContent = sharedCount > 1 ? sharedLabel + ' (' + sharedCount + ' images)' : sharedLabel;
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
        if (textEngine && textEngine.getEditTarget() === 'text') {
            hideImageManipulator();
            return;
        }
        if (!hasSelection()) {
            hideImageManipulator();
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        var slot = getActiveSlot();
        var layer = slot ? getActiveLayer(slot) : null;
        if (!bounds || !layer || !layer.image) {
            hideImageManipulator();
            return;
        }
        var params = getImageDrawParams(bounds, layer.image, layer);
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
        if (textEngine) {
            if (textEngine.getEditTarget() === 'text') {
                textEngine.updateManipulator();
            } else {
                textEngine.hideManipulator();
            }
        }
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
            var row = {
                layers: mapLayersForMeta(slot.layers)
            };
            if (state.imageMode === 'per_contour') {
                var texts = mapTextsForMeta(slot.texts);
                if (texts.length) {
                    row.texts = texts;
                }
            }
            return row;
        });
        var meta = {
            type: 'contours_gateau',
            format: state.format,
            orientation: 'landscape',
            height_cm: state.heightCm,
            width_cm: Math.round(usableWidth * 10) / 10,
            image_mode: state.imageMode,
            contours: contoursMeta
        };
        if (state.imageMode === 'shared') {
            var sharedLayers = mapLayersForMeta(state.shared.layers);
            if (sharedLayers.length) {
                meta.layers = sharedLayers;
                meta.image = sharedLayers[0];
            }
            var sharedTexts = mapTextsForMeta(state.shared.texts);
            if (sharedTexts.length) {
                meta.texts = sharedTexts;
            }
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

    function appendFileToSlot(file, slot) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0 || !slot) {
            return;
        }
        var layer = createImageLayer();
        layer.file = file;
        layer.filename = file.name || '';
        layer.objectUrl = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            layer.image = img;
            slot.layers.push(layer);
            slot.activeLayerId = layer.id;
            updateImageUi();
            renderPreview();
            if (fileInput) {
                fileInput.value = '';
            }
        };
        img.onerror = function () {
            revokeLayerUrl(layer);
            window.alert('Impossible de charger cette image.');
            updateImageUi();
            renderPreview();
        };
        img.src = layer.objectUrl;
    }

    function onFileSelected(file) {
        if (state.imageMode === 'shared') {
            appendFileToSlot(file, state.shared);
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
        appendFileToSlot(file, slot);
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
        if (textEngine) {
            textEngine.setEditTarget('');
            textEngine.syncToControls();
        }
        renderPreview();
        var slot = getActiveSlot();
        if (openPickerIfEmpty && state.imageMode === 'per_contour' && slot && !slotHasImages(slot) && fileInput) {
            fileInput.click();
        }
    }

    function resetState() {
        clearSlotImages(state.shared);
        state.contours.forEach(clearSlotImages);
        state.format = 'a4';
        state.heightCm = 5;
        state.imageMode = 'shared';
        state.activeIndex = -1;
        if (fileInput) {
            fileInput.value = '';
        }
        if (textEngine) {
            textEngine.resetStore(state.shared);
            state.contours.forEach(function (contour) {
                textEngine.resetStore(contour);
            });
            textEngine.setEditTarget('');
            textEngine.syncToControls();
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
        if (textEngine) {
            textEngine.finishDrag();
            textEngine.hideManipulator();
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function applyImageMove(layer, canvasX, canvasY, startX, startY, startOffsetX, startOffsetY) {
        if (!lastRenderLayout || !hasSelection() || !layer) {
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        if (!bounds) {
            return;
        }
        if (typeof startX === 'number') {
            var dx = canvasX - startX;
            var dy = canvasY - startY;
            layer.offsetX = clampImageOffset(startOffsetX + (dx / bounds.w) * 100);
            layer.offsetY = clampImageOffset(startOffsetY + (dy / bounds.h) * 100);
            return;
        }
        layer.offsetX = clampImageOffset(((canvasX - bounds.x) / bounds.w) * 100);
        layer.offsetY = clampImageOffset(((canvasY - bounds.y) / bounds.h) * 100);
    }

    function applyImageZoomAt(layer, newScalePct, focalX, focalY) {
        if (!layer || !layer.image || !lastRenderLayout) {
            return;
        }
        var bounds = lastRenderLayout.contours[state.activeIndex];
        if (!bounds) {
            return;
        }
        var before = getImageDrawParams(bounds, layer.image, layer);
        if (!before || before.w <= 0 || before.h <= 0) {
            layer.scalePct = clampImageScale(newScalePct);
            return;
        }
        var fracX = (focalX - before.x) / before.w;
        var fracY = (focalY - before.y) / before.h;
        layer.scalePct = clampImageScale(newScalePct);
        var after = getImageDrawParams(bounds, layer.image, layer);
        if (!after) {
            return;
        }
        var newCx = focalX - fracX * after.w + after.w / 2;
        var newCy = focalY - fracY * after.h + after.h / 2;
        layer.offsetX = clampImageOffset(((newCx - bounds.x) / bounds.w) * 100);
        layer.offsetY = clampImageOffset(((newCy - bounds.y) / bounds.h) * 100);
    }

    function removeActiveImage() {
        var slot = getActiveSlot();
        if (!slot || !slot.layers.length) {
            return;
        }
        var activeId = slot.activeLayerId;
        var idx = -1;
        var i;
        for (i = 0; i < slot.layers.length; i++) {
            if (slot.layers[i].id === activeId) {
                idx = i;
                break;
            }
        }
        if (idx < 0) {
            idx = slot.layers.length - 1;
        }
        revokeLayerUrl(slot.layers[idx]);
        slot.layers.splice(idx, 1);
        if (slot.layers.length) {
            slot.activeLayerId = slot.layers[slot.layers.length - 1].id;
        } else {
            slot.activeLayerId = '';
        }
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
            var sourceSlot = state.imageMode === 'shared' ? state.shared : getActiveSlot();
            var sourceLayer = sourceSlot ? getActiveLayer(sourceSlot) : null;
            var sourceFile = sourceLayer && sourceLayer.file;
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
            if (textEngine) {
                textEngine.syncToControls();
            }
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
            var layer = slot ? getActiveLayer(slot) : null;
            if (!layer) {
                return;
            }
            resetLayerTransform(layer);
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
            if (textEngine && textEngine.handleCanvasPointerDown(event)) {
                event.preventDefault();
                hideImageManipulator();
                return;
            }
            var slot = getActiveSlot();
            var layer = slot ? getActiveLayer(slot) : null;
            if (!layer || !layer.image) {
                return;
            }
            event.preventDefault();
            manipDrag = {
                type: 'move',
                startX: pt.x,
                startY: pt.y,
                startOffsetX: layer.offsetX,
                startOffsetY: layer.offsetY
            };
            try {
                canvas.setPointerCapture(event.pointerId);
            } catch (e) { /* ignore */ }
        });

        canvas.addEventListener('pointermove', function (event) {
            if (textEngine && textEngine.getManipDrag()) {
                textEngine.onPointerMove(event.clientX, event.clientY);
                renderPreview();
                return;
            }
            if (!manipDrag) {
                return;
            }
            var pt = canvasPointFromEvent(event);
            var slot = getActiveSlot();
            var layer = slot ? getActiveLayer(slot) : null;
            if (!pt || !layer) {
                return;
            }
            event.preventDefault();
            if (manipDrag.type === 'move') {
                applyImageMove(layer, pt.x, pt.y, manipDrag.startX, manipDrag.startY, manipDrag.startOffsetX, manipDrag.startOffsetY);
            } else if (manipDrag.type === 'resize') {
                var bounds = lastRenderLayout && lastRenderLayout.contours[state.activeIndex];
                if (!bounds) {
                    return;
                }
                var params = getImageDrawParams(bounds, layer.image, layer);
                if (!params) {
                    return;
                }
                var dist = Math.hypot(pt.x - params.cx, pt.y - params.cy);
                if (manipDrag.startDist > 0) {
                    applyImageZoomAt(layer, manipDrag.startScale * (dist / manipDrag.startDist), params.cx, params.cy);
                }
            }
            renderPreview();
        });

        function endDrag() {
            if (textEngine) {
                textEngine.finishDrag();
            }
            manipDrag = null;
        }
        canvas.addEventListener('pointerup', endDrag);
        canvas.addEventListener('pointercancel', endDrag);

        canvas.addEventListener('wheel', function (event) {
            if (!hasSelection()) {
                return;
            }
            var slot = getActiveSlot();
            var layer = slot ? getActiveLayer(slot) : null;
            if (!layer || !layer.image) {
                return;
            }
            var pt = canvasPointFromEvent(event);
            if (!pt) {
                return;
            }
            event.preventDefault();
            applyImageZoomAt(layer, layer.scalePct * wheelZoomFactor(event.deltaY), pt.x, pt.y);
            renderPreview();
        }, { passive: false });
    }

    if (textManipBox && textEngine) {
        textManipBox.addEventListener('pointerdown', function (event) {
            textEngine.handleManipBoxPointerDown(event);
        });
    }

    window.addEventListener('pointermove', function (event) {
        if (!textEngine || !textEngine.getManipDrag()) {
            return;
        }
        textEngine.onPointerMove(event.clientX, event.clientY);
        renderPreview();
    });

    window.addEventListener('pointerup', function () {
        if (textEngine && textEngine.getManipDrag()) {
            textEngine.finishDrag();
        }
    });

    window.addEventListener('pointercancel', function () {
        if (textEngine && textEngine.getManipDrag()) {
            textEngine.finishDrag();
        }
    });

    if (imageManipBox) {
        imageManipBox.addEventListener('pointerdown', function (event) {
            if (textEngine) {
                textEngine.setEditTarget('');
            }
            var handle = event.target.closest('.perso-image-handle');
            var slot = getActiveSlot();
            var layer = slot ? getActiveLayer(slot) : null;
            if (!layer || !layer.image || !lastRenderLayout) {
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
                var params = getImageDrawParams(bounds, layer.image, layer);
                if (!params) {
                    return;
                }
                manipDrag = {
                    type: 'resize',
                    startDist: Math.hypot(pt.x - params.cx, pt.y - params.cy),
                    startScale: layer.scalePct
                };
            } else {
                manipDrag = {
                    type: 'move',
                    startX: pt.x,
                    startY: pt.y,
                    startOffsetX: layer.offsetX,
                    startOffsetY: layer.offsetY
                };
            }
        });
    }

    bindModalDeselect();
    updateModeUi();
    updatePaperUi();
    renderPreview();
})();
