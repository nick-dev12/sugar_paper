/**
 * Personnalisation cupcakes — 12 formes sur feuille A4
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-personnalisation-cupcakes');
    if (!modal) {
        return;
    }

    var PAPER = { label: 'A4', widthCm: 21, heightCm: 29.7 };
    var CIRCLE_COUNT = 12;
    var COLS = 3;
    var ROWS = 4;
    // Canvas portrait A4 (≈ 210×297 mm à ~2.83 px/mm)
    var CANVAS_W = 595;
    var CANVAS_H = 842;
    var IMAGE_SCALE_MIN = 50;
    var IMAGE_SCALE_MAX = 400;
    var EDGE_MARGIN_CM = 0.7;
    var GAP_CM = 1.2;
    var HEART_DRAW_SCALE = 1.14;

    var btnClose = document.getElementById('cupcakes-modal-close');
    var btnCancel = document.getElementById('cupcakes-cancel');
    var btnValidate = document.getElementById('cupcakes-validate');
    var fileInput = document.getElementById('cupcakes-image-input');
    var uploadLabel = document.getElementById('cupcakes-upload-label');
    var uploadText = document.getElementById('cupcakes-upload-text');
    var filenameEl = document.getElementById('cupcakes-upload-filename');
    var canvas = document.getElementById('cupcakes-preview-canvas');
    var previewViewport = document.getElementById('cupcakes-preview-viewport');
    var imageManipulator = document.getElementById('cupcakes-image-manipulator');
    var imageManipBox = document.getElementById('cupcakes-image-manip-box');
    var imageHintEl = document.getElementById('cupcakes-image-hint');
    var imageResetBtn = document.getElementById('cupcakes-image-reset');
    var imageDeleteBtn = document.getElementById('cupcakes-image-delete');
    var dimDiameter = document.getElementById('cupcakes-dim-diameter');
    var dimDiameterVal = document.getElementById('cupcakes-dim-diameter-val');
    var paperInfoEl = document.getElementById('cupcakes-paper-info');
    var activeCircleLabel = document.getElementById('cupcakes-active-circle-label');
    var shapeBtns = modal.querySelectorAll('.perso-shape-btn');
    var modeBtns = modal.querySelectorAll('.perso-image-mode-btn');

    var activeForm = null;
    var lastRenderLayout = null;
    var manipDrag = null;
    var touchPointers = {};

    var state = {
        shape: 'circle',
        diameterCm: 5,
        imageMode: 'shared',
        activeIndex: -1,
        shared: makeSlot(),
        circles: []
    };

    for (var i = 0; i < CIRCLE_COUNT; i++) {
        state.circles.push(makeSlot());
    }

    var textManipBox = document.getElementById('cupcakes-text-manip-box');
    var textEngine = null;
    if (window.PersoTextEngine) {
        textEngine = window.PersoTextEngine.create({
            modal: modal,
            prefix: 'cupcakes',
            getShape: function () {
                return state.shape === 'circle' ? 'circle' : 'rect';
            },
            getBounds: function () {
                return getRawLayoutBounds();
            },
            getDefaultTextPosition: function () {
                return { x: 50, y: 50 };
            },
            adjustTextBounds: function (bounds) {
                return getTextBounds(bounds);
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
                    selectCircle(0, false);
                }
                return state.circles[state.activeIndex];
            },
            ensureSlotSelected: function () {
                if (state.imageMode === 'shared') {
                    return true;
                }
                if (state.activeIndex < 0) {
                    selectCircle(0, false);
                }
                return state.activeIndex >= 0;
            },
            hasBackgroundImage: function () {
                var slot = getActiveSlot();
                return !!(slot && slot.image);
            },
            onChange: function () {
                renderPreview();
            }
        });
        textEngine.resetStore(state.shared);
        state.circles.forEach(function (circle) {
            textEngine.resetStore(circle);
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
        var store = state.imageMode === 'shared' ? state.shared : state.circles[index];
        return store && store.texts ? store.texts : [];
    }

    function getTextDrawShape() {
        return state.shape === 'circle' ? 'circle' : 'rect';
    }

    function getRawLayoutBounds() {
        if (!lastRenderLayout || !lastRenderLayout.circles || !lastRenderLayout.circles.length) {
            return null;
        }
        if (state.activeIndex >= 0) {
            return lastRenderLayout.circles[state.activeIndex];
        }
        return lastRenderLayout.circles[0];
    }

    function getShapeDrawBounds(bounds) {
        if (!bounds || state.shape !== 'heart') {
            return bounds;
        }
        var scale = HEART_DRAW_SCALE;
        var w = bounds.w * scale;
        var h = bounds.h * scale;
        return {
            index: bounds.index,
            x: bounds.cx - w / 2,
            y: bounds.cy - h / 2,
            w: w,
            h: h,
            cx: bounds.cx,
            cy: bounds.cy,
            r: w / 2
        };
    }

    function getTextBounds(bounds) {
        if (!bounds) {
            return bounds;
        }
        if (state.shape === 'circle') {
            return bounds;
        }
        var insetX = bounds.w * 0.14;
        var insetTop = bounds.h * 0.22;
        var insetBottom = bounds.h * 0.34;
        var w = Math.max(1, bounds.w - insetX * 2);
        var h = Math.max(1, bounds.h - insetTop - insetBottom);
        return {
            index: bounds.index,
            x: bounds.x + insetX,
            y: bounds.y + insetTop,
            w: w,
            h: h,
            cx: bounds.x + insetX + w / 2,
            cy: bounds.y + insetTop + h / 2,
            r: Math.min(w, h) / 2
        };
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

    function getActiveSlot() {
        if (state.activeIndex < 0) {
            return null;
        }
        if (state.imageMode === 'shared') {
            return state.shared;
        }
        return state.circles[state.activeIndex] || null;
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
        if (activeCircleLabel) {
            activeCircleLabel.textContent = '—';
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
        var pad = 8;
        var availW = canvasW - pad * 2;
        var availH = canvasH - pad * 2;
        var aspect = PAPER.widthCm / PAPER.heightCm;
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
            pxPerCm: w / PAPER.widthCm
        };
    }

    /**
     * Grille 3×4 centrée : diamètre demandé + écart fixe entre cercles.
     * Les cercles ne sont pas « étirés » pour remplir la feuille.
     */
    function getCircleLayouts(paperBounds) {
        var edge = EDGE_MARGIN_CM * paperBounds.pxPerCm;
        var usableW = Math.max(1, paperBounds.w - edge * 2);
        var usableH = Math.max(1, paperBounds.h - edge * 2);
        var gapPx = GAP_CM * paperBounds.pxPerCm;

        var maxDByW = (usableW - (COLS - 1) * gapPx) / COLS;
        var maxDByH = (usableH - (ROWS - 1) * gapPx) / ROWS;
        var maxDFit = Math.max(1, Math.min(maxDByW, maxDByH));
        var dPx;
        if (state.shape === 'heart') {
            dPx = (maxDFit / HEART_DRAW_SCALE) * 0.98;
        } else {
            dPx = Math.min(state.diameterCm * paperBounds.pxPerCm, maxDFit);
        }

        var totalW = COLS * dPx + (COLS - 1) * gapPx;
        var totalH = ROWS * dPx + (ROWS - 1) * gapPx;
        var startX = paperBounds.x + (paperBounds.w - totalW) / 2;
        var startY = paperBounds.y + (paperBounds.h - totalH) / 2;

        var layouts = [];
        var idx = 0;
        for (var r = 0; r < ROWS; r++) {
            for (var c = 0; c < COLS; c++) {
                var x = startX + c * (dPx + gapPx);
                var y = startY + r * (dPx + gapPx);
                var cell = {
                    index: idx,
                    x: x,
                    y: y,
                    w: dPx,
                    h: dPx,
                    cx: x + dPx / 2,
                    cy: y + dPx / 2,
                    r: dPx / 2
                };
                layouts.push(getShapeDrawBounds(cell));
                idx++;
            }
        }
        return layouts;
    }

    function isPointInHeart(x, y, bounds) {
        if (!canvas) {
            return x >= bounds.x && x <= bounds.x + bounds.w && y >= bounds.y && y <= bounds.y + bounds.h;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return false;
        }
        ctx.save();
        appendHeartPath(ctx, bounds);
        var inside = ctx.isPointInPath(x, y);
        ctx.restore();
        return inside;
    }

    function appendHeartPath(ctx, bounds) {
        var x = bounds.x;
        var y = bounds.y;
        var w = bounds.w;
        var h = bounds.h;
        var cx = bounds.cx;
        var tipY = y + h * 0.90;
        var cleftY = y + h * 0.30;

        ctx.moveTo(cx, tipY);
        ctx.bezierCurveTo(
            x + w * 0.02, y + h * 0.70,
            x + w * 0.02, y + h * 0.40,
            x + w * 0.24, y + h * 0.26
        );
        ctx.bezierCurveTo(
            x + w * 0.34, y + h * 0.10,
            x + w * 0.44, y + h * 0.10,
            cx, cleftY
        );
        ctx.bezierCurveTo(
            x + w * 0.56, y + h * 0.10,
            x + w * 0.66, y + h * 0.10,
            x + w * 0.76, y + h * 0.26
        );
        ctx.bezierCurveTo(
            x + w * 0.98, y + h * 0.40,
            x + w * 0.98, y + h * 0.70,
            cx, tipY
        );
        ctx.closePath();
    }

    function appendShapePath(ctx, bounds) {
        ctx.beginPath();
        if (state.shape === 'heart') {
            appendHeartPath(ctx, bounds);
        } else {
            ctx.arc(bounds.cx, bounds.cy, bounds.r, 0, Math.PI * 2);
            ctx.closePath();
        }
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
        appendShapePath(ctx, bounds);
        ctx.clip();
        if (slot && slot.image) {
            var params = getImageDrawParams(bounds, slot.image, slot);
            if (params) {
                ctx.drawImage(slot.image, params.x, params.y, params.w, params.h);
            }
        } else {
            ctx.fillStyle = 'rgba(229, 72, 138, 0.08)';
            appendShapePath(ctx, bounds);
            ctx.fill();
        }
        if (textEngine) {
            textEngine.drawTextsArray(ctx, bounds, getTextDrawShape(), getTextsForRender(bounds.index));
        }
        ctx.restore();

        ctx.save();
        var isActive = hasSelection() && bounds.index === state.activeIndex;
        ctx.strokeStyle = isActive ? '#E5488A' : 'rgba(42, 26, 34, 0.35)';
        ctx.lineWidth = isActive ? 3 : 1.5;
        appendShapePath(ctx, bounds);
        ctx.stroke();
        ctx.restore();
    }

    function slotForIndex(index) {
        if (state.imageMode === 'shared') {
            return state.shared;
        }
        return state.circles[index] || null;
    }

    function hasContent() {
        var hasImage = state.imageMode === 'shared'
            ? !!state.shared.image
            : state.circles.some(function (slot) {
                return !!slot.image;
            });
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
            return state.circles.map(function (slot) {
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
                ? 'Importer une image (tous les cercles)'
                : 'Importer pour le cercle sélectionné';
        }
        if (activeCircleLabel) {
            activeCircleLabel.textContent = hasSelection() ? String(state.activeIndex + 1) : '—';
        }
        updateImageUi();
    }

    function updatePaperUi() {
        if (paperInfoEl) {
            paperInfoEl.textContent = 'Feuille ' + PAPER.label + ' — '
                + formatCm(PAPER.widthCm) + ' × ' + formatCm(PAPER.heightCm)
                + ' cm · 12 emplacements';
        }
        if (dimDiameter) {
            dimDiameter.value = String(state.diameterCm);
        }
        if (dimDiameterVal) {
            dimDiameterVal.textContent = formatCm(state.diameterCm);
        }
    }

    function updateShapeUi() {
        shapeBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-shape') === state.shape;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
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

    function hitTestCircle(x, y) {
        if (!lastRenderLayout || !lastRenderLayout.circles) {
            return -1;
        }
        for (var i = 0; i < lastRenderLayout.circles.length; i++) {
            var b = lastRenderLayout.circles[i];
            if (state.shape === 'circle') {
                var dx = x - b.cx;
                var dy = y - b.cy;
                if ((dx * dx + dy * dy) <= (b.r * b.r)) {
                    return i;
                }
            } else if (isPointInHeart(x, y, b)) {
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
        if (!imageManipulator || !imageManipBox || !lastRenderLayout || !canvas || !previewViewport) {
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
        var bounds = lastRenderLayout.circles[state.activeIndex];
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
        var left = params.x * scaleX;
        var top = params.y * scaleY;
        var width = params.w * scaleX;
        var height = params.h * scaleY;
        imageManipulator.hidden = false;
        imageManipulator.setAttribute('aria-hidden', 'false');
        imageManipBox.style.left = left + 'px';
        imageManipBox.style.top = top + 'px';
        imageManipBox.style.width = width + 'px';
        imageManipBox.style.height = height + 'px';
    }

    function renderPreview() {
        if (!canvas) {
            return;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        if (canvas.width !== CANVAS_W) {
            canvas.width = CANVAS_W;
        }
        if (canvas.height !== CANVAS_H) {
            canvas.height = CANVAS_H;
        }
        ctx.clearRect(0, 0, CANVAS_W, CANVAS_H);

        // Fond hors feuille
        ctx.fillStyle = 'rgba(229, 72, 138, 0.06)';
        ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);

        var paperBounds = getPaperBounds(CANVAS_W, CANVAS_H);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);
        ctx.strokeStyle = 'rgba(42, 26, 34, 0.28)';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);

        var layouts = getCircleLayouts(paperBounds);
        layouts.forEach(function (bounds) {
            drawSlotContent(ctx, bounds, slotForIndex(bounds.index));
        });

        lastRenderLayout = {
            paperBounds: paperBounds,
            circles: layouts
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
            btnOpen: document.getElementById('btn-personnaliser-cupcakes') || document.querySelector('.js-open-cupcakes-perso-modal')
        };
    }

    function buildMetaObject() {
        var circlesMeta = state.circles.map(function (slot) {
            var row = {
                offset_x: slot.offsetX,
                offset_y: slot.offsetY,
                scale_pct: slot.scalePct
            };
            if (state.imageMode === 'per_circle') {
                var texts = mapTextsForMeta(slot.texts);
                if (texts.length) {
                    row.texts = texts;
                }
            }
            return row;
        });
        var meta = {
            type: 'cupcakes',
            format: 'a4',
            shape: state.shape,
            diameter_cm: state.diameterCm,
            width_cm: state.diameterCm,
            height_cm: state.diameterCm,
            image_mode: state.imageMode,
            circles: circlesMeta
        };
        if (state.imageMode === 'shared') {
            meta.image = {
                offset_x: state.shared.offsetX,
                offset_y: state.shared.offsetY,
                scale_pct: state.shared.scalePct
            };
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
            callback(new File([blob], 'personnalisation-cupcakes.png', { type: 'image/png' }));
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

    function loadFileIntoSlot(file, slot, thenSelectUpload) {
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
            if (thenSelectUpload && fileInput) {
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
                if (activeCircleLabel) {
                    activeCircleLabel.textContent = '1';
                }
            }
            updateImageUi();
            renderPreview();
            return;
        }
        if (!hasSelection()) {
            state.activeIndex = 0;
            if (activeCircleLabel) {
                activeCircleLabel.textContent = '1';
            }
        }
        var slot = getActiveSlot();
        if (!slot) {
            return;
        }
        loadFileIntoSlot(file, slot);
    }

    function selectCircle(index, openPickerIfEmpty) {
        if (index < 0 || index >= CIRCLE_COUNT) {
            clearSelection();
            return;
        }
        state.activeIndex = index;
        if (activeCircleLabel) {
            activeCircleLabel.textContent = String(index + 1);
        }
        updateImageUi();
        if (textEngine) {
            textEngine.setEditTarget('');
            textEngine.syncToControls();
        }
        renderPreview();
        var slot = getActiveSlot();
        if (openPickerIfEmpty && state.imageMode === 'per_circle' && slot && !slot.image && fileInput) {
            fileInput.click();
        }
    }

    function resetState() {
        clearSlotImage(state.shared);
        state.circles.forEach(clearSlotImage);
        state.shape = 'circle';
        state.diameterCm = 5;
        state.imageMode = 'shared';
        state.activeIndex = -1;
        if (fileInput) {
            fileInput.value = '';
        }
        if (textEngine) {
            textEngine.resetStore(state.shared);
            state.circles.forEach(function (circle) {
                textEngine.resetStore(circle);
            });
            textEngine.setEditTarget('');
            textEngine.syncToControls();
        }
        updateModeUi();
        updatePaperUi();
        updateShapeUi();
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
        updateShapeUi();
        renderPreview();
    }

    function closeModal() {
        manipDrag = null;
        touchPointers = {};
        hideImageManipulator();
        if (textEngine) {
            textEngine.finishDrag();
            textEngine.hideManipulator();
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function applyImageMove(slot, canvasX, canvasY, startX, startY, startOffsetX, startOffsetY) {
        if (!lastRenderLayout || !hasSelection()) {
            return;
        }
        var bounds = lastRenderLayout.circles[state.activeIndex];
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
        var bounds = lastRenderLayout.circles[state.activeIndex];
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
        var openBtn = event.target.closest('.js-open-cupcakes-perso-modal, #btn-personnaliser-cupcakes');
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

    shapeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.shape = btn.getAttribute('data-shape') || 'circle';
            updateShapeUi();
            if (textEngine) {
                textEngine.syncToControls();
            }
            renderPreview();
        });
    });

    if (dimDiameter) {
        dimDiameter.addEventListener('input', function () {
            state.diameterCm = clamp(parseFloat(dimDiameter.value) || 5, 1, 5);
            if (dimDiameterVal) {
                dimDiameterVal.textContent = formatCm(state.diameterCm);
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
            var hit = hitTestCircle(pt.x, pt.y);
            if (hit < 0) {
                clearSelection();
                return;
            }
            selectCircle(hit, true);
            if (textEngine && textEngine.handleCanvasPointerDown(event)) {
                event.preventDefault();
                hideImageManipulator();
                return;
            }
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
            if (!pt || !slot) {
                return;
            }
            event.preventDefault();
            if (manipDrag.type === 'move') {
                applyImageMove(slot, pt.x, pt.y, manipDrag.startX, manipDrag.startY, manipDrag.startOffsetX, manipDrag.startOffsetY);
            } else if (manipDrag.type === 'resize') {
                var bounds = lastRenderLayout && lastRenderLayout.circles[state.activeIndex];
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
                var bounds = lastRenderLayout.circles[state.activeIndex];
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
    updateShapeUi();
    renderPreview();
})();
