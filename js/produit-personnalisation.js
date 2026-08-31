/**
 * Personnalisation — format A4/A3, dimensions cm, formes cercle/carré, export canvas
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-personnalisation');
    if (!modal) {
        return;
    }

    var PAPER_FORMATS = {
        a4: { label: 'A4', widthCm: 21, heightCm: 29.7 },
        a3: { label: 'A3', widthCm: 29.7, heightCm: 42 }
    };

    var btnClose = document.getElementById('perso-modal-close');
    var btnCancel = document.getElementById('perso-cancel');
    var btnValidate = document.getElementById('perso-validate');
    var fileInput = document.getElementById('perso-image-input');
    var uploadLabel = document.getElementById('perso-upload-label');
    var filenameEl = document.getElementById('perso-upload-filename');
    var canvas = document.getElementById('perso-preview-canvas');
    var previewViewport = document.getElementById('perso-preview-viewport');
    var textManipulator = document.getElementById('perso-text-manipulator');
    var textManipBox = document.getElementById('perso-text-manip-box');
    var textInput = document.getElementById('perso-text-input');
    var textListEl = document.getElementById('perso-text-list');
    var textAddBtn = document.getElementById('perso-text-add');
    var dimWidth = document.getElementById('perso-dim-width');
    var dimHeight = document.getElementById('perso-dim-height');
    var dimDiameter = document.getElementById('perso-dim-diameter');
    var dimWidthVal = document.getElementById('perso-dim-width-val');
    var dimHeightVal = document.getElementById('perso-dim-height-val');
    var dimDiameterVal = document.getElementById('perso-dim-diameter-val');
    var dimWidthWrap = document.getElementById('perso-dim-width-wrap');
    var dimHeightWrap = document.getElementById('perso-dim-height-wrap');
    var dimDiameterWrap = document.getElementById('perso-dim-diameter-wrap');
    var paperInfoEl = document.getElementById('perso-paper-info');
    var paperBtns = modal.querySelectorAll('.perso-paper-btn');
    var shapeBtns = modal.querySelectorAll('.perso-shape-btn');
    var textSize = document.getElementById('perso-text-size');
    var textSizeVal = document.getElementById('perso-text-size-val');
    var textPosX = document.getElementById('perso-text-pos-x');
    var textPosXVal = document.getElementById('perso-text-pos-x-val');
    var textPosY = document.getElementById('perso-text-pos-y');
    var textPosYVal = document.getElementById('perso-text-pos-y-val');
    var textRotation = document.getElementById('perso-text-rotation');
    var textRotationVal = document.getElementById('perso-text-rotation-val');
    var textWrapCircle = document.getElementById('perso-text-wrap-circle');
    var wrapCircleBtn = document.getElementById('perso-wrap-circle-btn');
    var wrapCircleHint = document.getElementById('perso-wrap-circle-hint');
    var wrapPosBtns = modal.querySelectorAll('.perso-wrap-pos-btn');
    var textColorInput = document.getElementById('perso-text-color');
    var colorSwatches = modal.querySelectorAll('.perso-color-swatch');
    var textControls = modal.querySelector('.perso-text-controls');
    var posXLabel = textPosX ? textPosX.closest('.perso-dimension-field') : null;
    var posYLabel = textPosY ? textPosY.closest('.perso-dimension-field') : null;
    var rotLabel = textRotation ? textRotation.closest('.perso-dimension-field') : null;
    var fontBtns = modal.querySelectorAll('.perso-font-btn');

    var activeForm = null;
    var previewObjectUrl = '';
    var loadedImage = null;
    var sourceUploadFile = null;
    var hasCustomization = false;

    var state = {
        paperFormat: 'a4',
        shape: 'circle',
        widthCm: 15,
        heightCm: 15,
        texts: [],
        activeTextId: ''
    };

    var CANVAS_SIZE = 640;
    var lastRenderLayout = null;
    var manipDrag = null;

    function clampPct(value) {
        return Math.max(0, Math.min(100, Math.round(value)));
    }

    function clampSizePct(value) {
        return Math.max(20, Math.min(100, Math.round(value)));
    }

    function clampRotation(value) {
        var n = Math.round(value);
        while (n > 180) {
            n -= 360;
        }
        while (n < -180) {
            n += 360;
        }
        return n;
    }

    function getCanvasDisplayScale() {
        if (!canvas) {
            return 1;
        }
        var rect = canvas.getBoundingClientRect();
        if (!rect.width) {
            return 1;
        }
        return rect.width / CANVAS_SIZE;
    }

    function clientToCanvas(clientX, clientY) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = CANVAS_SIZE / (rect.width || CANVAS_SIZE);
        var scaleY = CANVAS_SIZE / (rect.height || CANVAS_SIZE);
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function canvasPointToViewport(cx, cy) {
        var canvasRect = canvas.getBoundingClientRect();
        var vpRect = previewViewport ? previewViewport.getBoundingClientRect() : canvasRect;
        var scale = getCanvasDisplayScale();
        return {
            x: canvasRect.left - vpRect.left + cx * scale,
            y: canvasRect.top - vpRect.top + cy * scale,
            scale: scale
        };
    }

    function measureStraightTextLayout(ctx, bounds, textObj) {
        var text = (textObj.text || '').replace(/\r\n/g, '\n');
        if (text.trim() === '') {
            return null;
        }

        var fontSize = getTextFontSize(bounds, textObj);
        var lines = text.split('\n');
        var lineHeight = fontSize * 1.25;
        ctx.save();
        ctx.font = '600 ' + fontSize + 'px "' + (textObj.font || 'Outfit') + '", sans-serif';
        var maxLineW = 0;
        lines.forEach(function (line) {
            maxLineW = Math.max(maxLineW, ctx.measureText(line).width);
        });
        ctx.restore();

        return {
            cx: bounds.x + bounds.w * ((textObj.textPosX || 50) / 100),
            cy: bounds.y + bounds.h * ((textObj.textPosY || 50) / 100),
            width: Math.min(maxLineW, bounds.w * 0.92),
            height: Math.max(lineHeight, lines.length * lineHeight),
            rotation: textObj.textRotation || 0
        };
    }

    function measureWrapTextLayout(bounds, textObj) {
        var maxR = Math.min(bounds.w, bounds.h) / 2;
        var radius = Math.max(20, maxR * (0.25 + 0.7 * ((textObj.textPosY || 50) / 100)));
        var arcOffset = (((textObj.textPosX || 50) - 50) / 50) * Math.PI * 0.75;
        var rotOffset = ((textObj.textRotation || 0) * Math.PI) / 180;
        var baseAngle = textObj.wrapArcPosition === 'bottom' ? Math.PI / 2 : -Math.PI / 2;
        var angle = baseAngle + arcOffset + rotOffset;
        return {
            cx: bounds.cx + Math.cos(angle) * radius,
            cy: bounds.cy + Math.sin(angle) * radius,
            width: Math.max(48, radius * 0.55),
            height: Math.max(32, radius * 0.28),
            rotation: (angle * 180 / Math.PI) + (textObj.wrapArcPosition === 'bottom' ? 90 : -90),
            wrap: true
        };
    }

    function getTextLayout(textObj) {
        if (!lastRenderLayout || !canvas || !textObj) {
            return null;
        }
        if ((textObj.text || '').trim() === '') {
            return null;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return null;
        }
        var bounds = lastRenderLayout.designBounds;
        if (textObj.wrapOnCircle && state.shape === 'circle') {
            return measureWrapTextLayout(bounds, textObj);
        }
        return measureStraightTextLayout(ctx, bounds, textObj);
    }

    function pointInRotatedRect(px, py, cx, cy, w, h, rotDeg) {
        var rad = -(rotDeg * Math.PI) / 180;
        var dx = px - cx;
        var dy = py - cy;
        var lx = dx * Math.cos(rad) - dy * Math.sin(rad);
        var ly = dx * Math.sin(rad) + dy * Math.cos(rad);
        return Math.abs(lx) <= w / 2 && Math.abs(ly) <= h / 2;
    }

    function hitTestTextAt(canvasX, canvasY) {
        if (!lastRenderLayout || !canvas) {
            return null;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return null;
        }
        var bounds = lastRenderLayout.designBounds;
        for (var i = state.texts.length - 1; i >= 0; i--) {
            var textObj = state.texts[i];
            if ((textObj.text || '').trim() === '') {
                continue;
            }
            var layout;
            if (textObj.wrapOnCircle && state.shape === 'circle') {
                layout = measureWrapTextLayout(bounds, textObj);
            } else {
                layout = measureStraightTextLayout(ctx, bounds, textObj);
            }
            if (!layout) {
                continue;
            }
            if (pointInRotatedRect(canvasX, canvasY, layout.cx, layout.cy, layout.width + 20, layout.height + 20, layout.rotation)) {
                return textObj.id;
            }
        }
        return null;
    }

    function hideTextManipulator() {
        if (!textManipulator) {
            return;
        }
        textManipulator.hidden = true;
        textManipulator.setAttribute('aria-hidden', 'true');
        textManipulator.classList.remove('is-visible');
    }

    function updateTextManipulator() {
        if (!textManipulator || !textManipBox || !previewViewport || !canvas) {
            return;
        }
        if (!modal.classList.contains('is-open')) {
            hideTextManipulator();
            return;
        }

        var active = getActiveText();
        if (!active || (active.text || '').trim() === '') {
            hideTextManipulator();
            return;
        }

        var layout = getTextLayout(active);
        if (!layout) {
            hideTextManipulator();
            return;
        }

        var vp = canvasPointToViewport(layout.cx, layout.cy);
        var boxW = Math.max(40, layout.width * vp.scale + 12);
        var boxH = Math.max(28, layout.height * vp.scale + 12);

        textManipulator.hidden = false;
        textManipulator.setAttribute('aria-hidden', 'false');
        textManipulator.classList.add('is-visible');

        textManipBox.classList.toggle('is-wrap-mode', !!(active.wrapOnCircle && state.shape === 'circle'));
        textManipBox.style.width = boxW + 'px';
        textManipBox.style.height = boxH + 'px';
        textManipBox.style.left = (vp.x - boxW / 2) + 'px';
        textManipBox.style.top = (vp.y - boxH / 2) + 'px';
        textManipBox.style.transform = 'rotate(' + layout.rotation + 'deg)';
    }

    function applyTextMove(active, canvasX, canvasY) {
        if (!lastRenderLayout || !active) {
            return;
        }
        var bounds = lastRenderLayout.designBounds;
        if (active.wrapOnCircle && state.shape === 'circle') {
            var dx = canvasX - bounds.cx;
            var dy = canvasY - bounds.cy;
            var dist = Math.hypot(dx, dy);
            var maxR = Math.min(bounds.w, bounds.h) / 2;
            active.textPosY = clampPct(((dist / maxR - 0.25) / 0.7) * 100);
            var angle = Math.atan2(dy, dx);
            var base = active.wrapArcPosition === 'bottom' ? Math.PI / 2 : -Math.PI / 2;
            var arcOffset = angle - base - (((active.textRotation || 0) * Math.PI) / 180);
            active.textPosX = clampPct(50 + (arcOffset / (Math.PI * 0.75)) * 50);
            return;
        }
        active.textPosX = clampPct(((canvasX - bounds.x) / bounds.w) * 100);
        active.textPosY = clampPct(((canvasY - bounds.y) / bounds.h) * 100);
    }

    function applyTextRotate(active, canvasX, canvasY, layout) {
        if (!active || !layout) {
            return;
        }
        var angle = Math.atan2(canvasY - layout.cy, canvasX - layout.cx) * 180 / Math.PI + 90;
        active.textRotation = clampRotation(angle);
    }

    function applyTextResize(active, canvasX, canvasY, layout, startDist, startSize) {
        if (!active || !layout || startDist <= 0) {
            return;
        }
        var dist = Math.hypot(canvasX - layout.cx, canvasY - layout.cy);
        var ratio = dist / startDist;
        active.textSizePct = clampSizePct(startSize * ratio);
    }

    function finishManipDrag() {
        if (!manipDrag) {
            return;
        }
        if (textManipBox) {
            textManipBox.classList.remove('is-dragging');
        }
        manipDrag = null;
        syncControlsFromActiveText();
        renderTextList();
    }

    function startManipDrag(mode, handle, clientX, clientY) {
        var active = getActiveText();
        if (!active || !lastRenderLayout) {
            return;
        }
        syncActiveTextFromControls();
        var layout = getTextLayout(active);
        if (!layout) {
            return;
        }
        var canvasPt = clientToCanvas(clientX, clientY);
        manipDrag = {
            mode: mode,
            handle: handle || '',
            textId: active.id,
            startX: canvasPt.x,
            startY: canvasPt.y,
            startPosX: active.textPosX,
            startPosY: active.textPosY,
            startRotation: active.textRotation,
            startSizePct: active.textSizePct,
            layoutCx: layout.cx,
            layoutCy: layout.cy,
            startDist: Math.max(12, Math.hypot(canvasPt.x - layout.cx, canvasPt.y - layout.cy))
        };
        if (textManipBox) {
            textManipBox.classList.add('is-dragging');
        }
    }

    function onManipPointerMove(clientX, clientY) {
        if (!manipDrag) {
            return;
        }
        var active = getActiveText();
        if (!active || active.id !== manipDrag.textId) {
            return;
        }
        var canvasPt = clientToCanvas(clientX, clientY);
        var layout = getTextLayout(active);

        if (manipDrag.mode === 'move') {
            applyTextMove(active, canvasPt.x, canvasPt.y);
        } else if (manipDrag.mode === 'rotate') {
            if (layout) {
                applyTextRotate(active, canvasPt.x, canvasPt.y, layout);
            }
        } else if (manipDrag.mode === 'resize') {
            if (layout) {
                applyTextResize(active, canvasPt.x, canvasPt.y, layout, manipDrag.startDist, manipDrag.startSizePct);
            }
        }

        syncControlsFromActiveText();
        renderPreview();
    }

    function bindTextManipulatorEvents() {
        if (!canvas || !textManipBox) {
            return;
        }

        canvas.addEventListener('pointerdown', function (event) {
            if (!modal.classList.contains('is-open') || event.button > 0) {
                return;
            }
            var canvasPt = clientToCanvas(event.clientX, event.clientY);
            var hitId = hitTestTextAt(canvasPt.x, canvasPt.y);
            if (hitId && hitId !== state.activeTextId) {
                selectText(hitId);
            }
            if (hitId) {
                startManipDrag('move', 'box', event.clientX, event.clientY);
                if (canvas.setPointerCapture) {
                    try {
                        canvas.setPointerCapture(event.pointerId);
                    } catch (err) {
                        /* ignore */
                    }
                }
                event.preventDefault();
            }
        });

        textManipBox.addEventListener('pointerdown', function (event) {
            if (!modal.classList.contains('is-open')) {
                return;
            }
            var handleEl = event.target.closest('.perso-text-handle');
            var handle = handleEl ? (handleEl.getAttribute('data-handle') || '') : '';
            var mode = 'move';
            if (handle === 'rotate') {
                mode = 'rotate';
            } else if (handle && handle !== '') {
                mode = 'resize';
            }
            startManipDrag(mode, handle, event.clientX, event.clientY);
            if (textManipBox.setPointerCapture) {
                try {
                    textManipBox.setPointerCapture(event.pointerId);
                } catch (err) {
                    /* ignore */
                }
            }
            event.preventDefault();
            event.stopPropagation();
        });

        textManipBox.addEventListener('pointerup', function (event) {
            finishManipDrag();
            if (textManipBox.releasePointerCapture) {
                try {
                    textManipBox.releasePointerCapture(event.pointerId);
                } catch (err) {
                    /* ignore */
                }
            }
        });

        textManipBox.addEventListener('pointercancel', function () {
            finishManipDrag();
        });

        window.addEventListener('pointermove', function (event) {
            if (!manipDrag) {
                return;
            }
            onManipPointerMove(event.clientX, event.clientY);
        });

        window.addEventListener('pointerup', function () {
            finishManipDrag();
        });

        window.addEventListener('resize', function () {
            updateTextManipulator();
        });
    }

    function createTextId() {
        return 'txt_' + Math.random().toString(36).slice(2, 10);
    }

    function createDefaultText(offsetY) {
        offsetY = typeof offsetY === 'number' ? offsetY : 50;
        return {
            id: createTextId(),
            text: '',
            font: 'Outfit',
            textSizePct: 50,
            textPosX: 50,
            textPosY: Math.max(10, Math.min(90, offsetY)),
            textRotation: 0,
            wrapOnCircle: false,
            wrapArcPosition: 'top',
            textColor: '#E5488A'
        };
    }

    function getActiveText() {
        for (var i = 0; i < state.texts.length; i++) {
            if (state.texts[i].id === state.activeTextId) {
                return state.texts[i];
            }
        }
        return state.texts[0] || null;
    }

    function syncActiveTextFromControls() {
        var active = getActiveText();
        if (!active) {
            return;
        }
        if (textInput) {
            active.text = textInput.value;
        }
        if (textSize) {
            active.textSizePct = parseInt(textSize.value, 10) || 50;
        }
        if (textPosX) {
            active.textPosX = parseInt(textPosX.value, 10) || 50;
        }
        if (textPosY) {
            active.textPosY = parseInt(textPosY.value, 10) || 50;
        }
        if (textRotation) {
            active.textRotation = parseInt(textRotation.value, 10) || 0;
        }
        if (textWrapCircle) {
            active.wrapOnCircle = !!textWrapCircle.checked;
        }
    }

    function syncControlsFromActiveText() {
        var active = getActiveText();
        if (!active) {
            return;
        }
        if (textInput) {
            textInput.value = active.text || '';
        }
        setRangeInput(textSize, textSizeVal, active.textSizePct);
        setRangeInput(textPosX, textPosXVal, active.textPosX);
        setRangeInput(textPosY, textPosYVal, active.textPosY);
        setRangeInput(textRotation, textRotationVal, active.textRotation);
        setTextColor(active.textColor || '#E5488A', true);
        if (textWrapCircle) {
            textWrapCircle.checked = !!(active.wrapOnCircle && state.shape === 'circle');
        }
        fontBtns.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-font') === active.font);
        });
        syncWrapButton();
        wrapPosBtns.forEach(function (btn) {
            var pos = btn.getAttribute('data-wrap-pos') === 'bottom' ? 'bottom' : 'top';
            var isActive = (active.wrapArcPosition || 'top') === pos;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        updateWrapUi();
    }

    function renderTextList() {
        if (!textListEl) {
            return;
        }
        textListEl.innerHTML = '';
        state.texts.forEach(function (textObj, index) {
            var item = document.createElement('div');
            item.className = 'perso-text-item' + (textObj.id === state.activeTextId ? ' is-active' : '');
            item.setAttribute('role', 'listitem');
            item.dataset.textId = textObj.id;

            var label = document.createElement('span');
            label.className = 'perso-text-item-label';
            var preview = (textObj.text || '').trim().replace(/\s+/g, ' ');
            label.textContent = preview !== '' ? preview : ('Texte ' + (index + 1));

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'perso-text-item-remove';
            removeBtn.setAttribute('aria-label', 'Supprimer ce texte');
            removeBtn.innerHTML = '&times;';
            removeBtn.dataset.removeId = textObj.id;

            item.appendChild(label);
            if (state.texts.length > 1) {
                item.appendChild(removeBtn);
            }
            textListEl.appendChild(item);
        });
    }

    function selectText(textId) {
        syncActiveTextFromControls();
        state.activeTextId = textId;
        syncControlsFromActiveText();
        renderTextList();
        renderPreview();
    }

    function addTextBlock() {
        syncActiveTextFromControls();
        var offset = 40 + (state.texts.length * 8);
        var textObj = createDefaultText(Math.min(85, offset));
        state.texts.push(textObj);
        state.activeTextId = textObj.id;
        syncControlsFromActiveText();
        renderTextList();
        renderPreview();
    }

    function removeTextBlock(textId) {
        if (state.texts.length <= 1) {
            return;
        }
        syncActiveTextFromControls();
        state.texts = state.texts.filter(function (t) { return t.id !== textId; });
        if (!getActiveText()) {
            state.activeTextId = state.texts[0].id;
        }
        syncControlsFromActiveText();
        renderTextList();
        renderPreview();
    }

    function isListingForm(form) {
        return !!(form && form.classList && form.classList.contains('perso-cart-form'));
    }

    function getFormContext(form) {
        if (!form) {
            return {
                hiddenPath: document.getElementById('option-image-personnalisation'),
                hiddenMeta: document.getElementById('option-perso-meta'),
                formFileInput: document.getElementById('form-image-personnalisation'),
                formSourceFileInput: document.getElementById('form-image-personnalisation-source'),
                statusBox: document.getElementById('perso-status'),
                statusThumb: document.getElementById('perso-status-thumb'),
                btnOpen: document.getElementById('btn-personnaliser')
            };
        }

        return {
            hiddenPath: form.querySelector('.option-image-personnalisation') || document.getElementById('option-image-personnalisation'),
            hiddenMeta: form.querySelector('.option-perso-meta') || document.getElementById('option-perso-meta'),
            formFileInput: form.querySelector('.form-image-personnalisation') || document.getElementById('form-image-personnalisation'),
            formSourceFileInput: form.querySelector('.form-image-personnalisation-source') || document.getElementById('form-image-personnalisation-source'),
            statusBox: document.getElementById('perso-status'),
            statusThumb: document.getElementById('perso-status-thumb'),
            btnOpen: form.querySelector('.js-open-perso-modal') || document.getElementById('btn-personnaliser')
        };
    }

    function getActiveContext() {
        return getFormContext(activeForm);
    }

    function revokePreviewUrl() {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = '';
        }
    }

    function formatCm(value) {
        var n = Math.round(parseFloat(value) * 10) / 10;
        return String(n).replace('.', ',');
    }

    function getPaperConfig() {
        return PAPER_FORMATS[state.paperFormat] || PAPER_FORMATS.a4;
    }

    function getPaperLimits() {
        var paper = getPaperConfig();
        return {
            maxW: Math.max(5, paper.widthCm - 2),
            maxH: Math.max(5, paper.heightCm - 2),
            maxD: Math.max(5, Math.min(paper.widthCm - 2, paper.heightCm - 2))
        };
    }

    function clampDimensions() {
        var limits = getPaperLimits();
        if (state.shape === 'circle') {
            var d = Math.max(5, Math.min(parseFloat(state.widthCm) || 15, limits.maxD));
            state.widthCm = d;
            state.heightCm = d;
        } else {
            state.widthCm = Math.max(5, Math.min(parseFloat(state.widthCm) || 15, limits.maxW));
            state.heightCm = Math.max(5, Math.min(parseFloat(state.heightCm) || 15, limits.maxH));
        }
    }

    function buildMetaObject() {
        clampDimensions();
        syncActiveTextFromControls();
        return {
            format: state.paperFormat,
            shape: state.shape,
            width_cm: state.widthCm,
            height_cm: state.heightCm,
            texts: state.texts.map(function (t) {
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
            })
        };
    }

    function syncMetaToForm() {
        var ctxForm = getActiveContext();
        if (ctxForm.hiddenMeta) {
            ctxForm.hiddenMeta.value = JSON.stringify(buildMetaObject());
        }
    }

    function setRangeInput(input, valEl, value, asCm) {
        if (input) {
            input.value = String(value);
        }
        if (valEl) {
            valEl.textContent = asCm ? formatCm(value) : String(value);
        }
    }

    function updatePaperUi() {
        var paper = getPaperConfig();
        var limits = getPaperLimits();

        if (paperInfoEl) {
            paperInfoEl.textContent = 'Feuille ' + paper.label + ' — ' + formatCm(paper.widthCm) + ' × ' + formatCm(paper.heightCm) + ' cm';
        }

        paperBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-paper') === state.paperFormat;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (dimWidth) {
            dimWidth.min = '5';
            dimWidth.max = String(limits.maxW);
            dimWidth.step = '0.5';
        }
        if (dimHeight) {
            dimHeight.min = '5';
            dimHeight.max = String(limits.maxH);
            dimHeight.step = '0.5';
        }
        if (dimDiameter) {
            dimDiameter.min = '5';
            dimDiameter.max = String(limits.maxD);
            dimDiameter.step = '0.5';
        }

        clampDimensions();
        setRangeInput(dimWidth, dimWidthVal, state.widthCm, true);
        setRangeInput(dimHeight, dimHeightVal, state.heightCm, true);
        setRangeInput(dimDiameter, dimDiameterVal, state.widthCm, true);
    }

    function updateShapeUi() {
        var isCircle = state.shape === 'circle';

        shapeBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-shape') === state.shape;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (dimDiameterWrap) {
            dimDiameterWrap.hidden = !isCircle;
        }
        if (dimWidthWrap) {
            dimWidthWrap.hidden = isCircle;
        }
        if (dimHeightWrap) {
            dimHeightWrap.hidden = isCircle;
        }

        if (!isCircle) {
            state.texts.forEach(function (t) {
                if (t.wrapOnCircle) {
                    t.wrapOnCircle = false;
                }
            });
            syncWrapButton();
        }

        if (wrapCircleBtn) {
            wrapCircleBtn.classList.toggle('is-disabled', !isCircle);
            wrapCircleBtn.disabled = !isCircle;
        }
        if (wrapCircleHint) {
            wrapCircleHint.textContent = isCircle
                ? 'Le texte suit le contour intérieur du cercle.'
                : 'Disponible uniquement avec la forme cercle.';
        }
    }

    function updateWrapUi() {
        var active = getActiveText();
        var wrapActive = !!(active && active.wrapOnCircle && state.shape === 'circle');

        if (textControls) {
            textControls.classList.toggle('is-wrap-active', wrapActive);
        }
        if (posXLabel) {
            var lx = posXLabel.querySelector('.perso-dim-label-text');
            if (lx) {
                lx.textContent = wrapActive ? 'Décalage sur l\'arc' : 'Position horizontale';
            }
        }
        if (posYLabel) {
            var ly = posYLabel.querySelector('.perso-dim-label-text');
            if (ly) {
                ly.textContent = wrapActive ? 'Rayon (centre → bord)' : 'Position verticale';
            }
        }
        if (rotLabel) {
            var lr = rotLabel.querySelector('.perso-dim-label-text');
            if (lr) {
                lr.textContent = wrapActive ? 'Rotation sur l\'arc' : 'Orientation';
            }
        }
    }

    function resetState() {
        state.paperFormat = 'a4';
        state.shape = 'circle';
        state.widthCm = 15;
        state.heightCm = 15;
        state.texts = [createDefaultText()];
        state.activeTextId = state.texts[0].id;
        loadedImage = null;
        sourceUploadFile = null;
        hasCustomization = false;

        syncControlsFromActiveText();
        if (filenameEl) {
            filenameEl.textContent = '';
        }
        if (fileInput) {
            fileInput.value = '';
        }

        renderTextList();
        updatePaperUi();
        updateShapeUi();
        revokePreviewUrl();
        updateWrapUi();
        syncMetaToForm();
        updateValidateState();
        renderPreview();
    }

    function getPaperBounds(w, h) {
        var paper = getPaperConfig();
        var aspect = paper.widthCm / paper.heightCm;
        var maxW = w - 40;
        var maxH = h - 40;
        var pw = maxW;
        var ph = pw / aspect;
        if (ph > maxH) {
            ph = maxH;
            pw = ph * aspect;
        }
        return {
            x: (w - pw) / 2,
            y: (h - ph) / 2,
            w: pw,
            h: ph,
            pxPerCm: pw / paper.widthCm
        };
    }

    function getDesignBounds(paperBounds) {
        var pxPerCm = paperBounds.pxPerCm;
        var bw = state.widthCm * pxPerCm;
        var bh = state.shape === 'circle' ? bw : state.heightCm * pxPerCm;
        var cx = paperBounds.x + paperBounds.w / 2;
        var cy = paperBounds.y + paperBounds.h / 2;
        return {
            x: cx - bw / 2,
            y: cy - bh / 2,
            w: bw,
            h: bh,
            cx: cx,
            cy: cy,
            r: Math.min(bw, bh) / 2
        };
    }

    function getTextFontSize(bounds, textObj) {
        return Math.max(10, ((textObj.textSizePct || 50) / 100) * Math.min(bounds.w, bounds.h) * 0.45);
    }

    function getTextFillStyle(textObj) {
        return textObj.textColor || '#E5488A';
    }

    function syncWrapButton() {
        if (!wrapCircleBtn || !textWrapCircle) {
            return;
        }
        var active = getActiveText();
        var on = !!(active && active.wrapOnCircle && state.shape === 'circle');
        textWrapCircle.checked = on;
        wrapCircleBtn.classList.toggle('is-active', on);
        wrapCircleBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    function setTextColor(color, skipRender) {
        if (!color) {
            return;
        }
        var active = getActiveText();
        if (active) {
            active.textColor = color;
        }
        if (textColorInput) {
            textColorInput.value = color;
        }
        var matched = false;
        colorSwatches.forEach(function (sw) {
            var isActive = sw.getAttribute('data-color') === color;
            sw.classList.toggle('is-active', isActive);
            if (isActive) {
                matched = true;
            }
        });
        if (!matched) {
            colorSwatches.forEach(function (sw) {
                sw.classList.remove('is-active');
            });
        }
        if (!skipRender) {
            renderPreview();
        }
    }

    function applyTextShadow(ctx, textObj) {
        if (loadedImage && !(textObj && textObj.wrapOnCircle)) {
            ctx.shadowColor = 'rgba(0,0,0,0.45)';
            ctx.shadowBlur = 4;
        }
    }

    function clearTextShadow(ctx) {
        ctx.shadowBlur = 0;
        ctx.shadowColor = 'transparent';
    }

    function applyShapeClip(ctx, bounds) {
        ctx.beginPath();
        if (state.shape === 'square') {
            ctx.rect(bounds.x, bounds.y, bounds.w, bounds.h);
        } else {
            ctx.arc(bounds.cx, bounds.cy, bounds.r, 0, Math.PI * 2);
        }
        ctx.clip();
    }

    function drawShapeOutline(ctx, bounds) {
        ctx.beginPath();
        if (state.shape === 'square') {
            ctx.rect(bounds.x, bounds.y, bounds.w, bounds.h);
        } else {
            ctx.arc(bounds.cx, bounds.cy, bounds.r, 0, Math.PI * 2);
        }
        ctx.strokeStyle = 'rgba(229, 72, 138, 0.55)';
        ctx.lineWidth = 2;
        ctx.stroke();
    }

    function drawPaperOutline(ctx, paperBounds) {
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.strokeRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);
        ctx.setLineDash([]);
    }

    function drawTextStraight(ctx, bounds, textObj) {
        var text = (textObj.text || '').replace(/\r\n/g, '\n');
        if (text.trim() === '') {
            return;
        }

        var fontSize = getTextFontSize(bounds, textObj);
        var tx = bounds.x + bounds.w * ((textObj.textPosX || 50) / 100);
        var ty = bounds.y + bounds.h * ((textObj.textPosY || 50) / 100);
        var lines = text.split('\n');
        var lineHeight = fontSize * 1.25;
        var startY = -((lines.length - 1) * lineHeight) / 2;

        ctx.save();
        ctx.translate(tx, ty);
        ctx.rotate(((textObj.textRotation || 0) * Math.PI) / 180);
        ctx.font = '600 ' + fontSize + 'px "' + (textObj.font || 'Outfit') + '", sans-serif';
        ctx.fillStyle = getTextFillStyle(textObj);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        applyTextShadow(ctx, textObj);
        lines.forEach(function (line, index) {
            ctx.fillText(line, 0, startY + index * lineHeight, bounds.w * 0.92);
        });
        clearTextShadow(ctx);
        ctx.restore();
    }

    function drawTextOnCircle(ctx, bounds, textObj) {
        var text = (textObj.text || '').replace(/\r\n/g, '\n').replace(/\n/g, ' ').trim();
        if (text === '') {
            return;
        }

        var cx = bounds.cx;
        var cy = bounds.cy;
        var maxR = Math.min(bounds.w, bounds.h) / 2;
        var radius = Math.max(20, maxR * (0.25 + 0.7 * ((textObj.textPosY || 50) / 100)));
        var fontSize = getTextFontSize(bounds, textObj);
        var fillStyle = getTextFillStyle(textObj);
        var chars = text.split('');

        ctx.save();
        ctx.font = '600 ' + fontSize + 'px "' + (textObj.font || 'Outfit') + '", sans-serif';
        ctx.fillStyle = fillStyle;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        var totalWidth = 0;
        chars.forEach(function (ch) {
            totalWidth += ctx.measureText(ch).width;
        });

        if (totalWidth <= 0) {
            ctx.restore();
            return;
        }

        var anglePerPx = 1 / radius;
        var totalAngle = Math.min(totalWidth * anglePerPx, Math.PI * 1.85);
        var arcOffset = (((textObj.textPosX || 50) - 50) / 50) * Math.PI * 0.75;
        var rotOffset = ((textObj.textRotation || 0) * Math.PI) / 180;
        var isBottom = textObj.wrapArcPosition === 'bottom';

        if (isBottom) {
            var angleBottom = Math.PI / 2 + arcOffset + rotOffset + totalAngle / 2;
            chars.forEach(function (ch) {
                var w = ctx.measureText(ch).width;
                if (w <= 0) {
                    return;
                }
                var charAngle = w * anglePerPx;
                angleBottom -= charAngle / 2;

                var pxB = cx + Math.cos(angleBottom) * radius;
                var pyB = cy + Math.sin(angleBottom) * radius;
                if (!isFinite(pxB) || !isFinite(pyB)) {
                    angleBottom -= charAngle / 2;
                    return;
                }

                ctx.save();
                ctx.translate(pxB, pyB);
                ctx.rotate(angleBottom - Math.PI / 2);
                ctx.fillText(ch, 0, 0);
                ctx.restore();

                angleBottom -= charAngle / 2;
            });
        } else {
            var angleTop = -Math.PI / 2 + arcOffset + rotOffset - totalAngle / 2;
            chars.forEach(function (ch) {
                var w = ctx.measureText(ch).width;
                if (w <= 0) {
                    return;
                }
                var charAngle = w * anglePerPx;
                angleTop += charAngle / 2;

                var pxT = cx + Math.cos(angleTop) * radius;
                var pyT = cy + Math.sin(angleTop) * radius;
                if (!isFinite(pxT) || !isFinite(pyT)) {
                    angleTop += charAngle / 2;
                    return;
                }

                ctx.save();
                ctx.translate(pxT, pyT);
                ctx.rotate(angleTop + Math.PI / 2);
                ctx.fillText(ch, 0, 0);
                ctx.restore();

                angleTop += charAngle / 2;
            });
        }

        ctx.restore();
    }

    function renderPreview() {
        if (!canvas) {
            return;
        }

        clampDimensions();
        syncMetaToForm();

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var w = CANVAS_SIZE;
        var h = CANVAS_SIZE;

        if (canvas.width !== w) {
            canvas.width = w;
        }
        if (canvas.height !== h) {
            canvas.height = h;
        }
        ctx.clearRect(0, 0, w, h);

        var paperBounds = getPaperBounds(w, h);
        var bounds = getDesignBounds(paperBounds);

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(paperBounds.x, paperBounds.y, paperBounds.w, paperBounds.h);
        drawPaperOutline(ctx, paperBounds);

        ctx.save();
        applyShapeClip(ctx, bounds);

        if (loadedImage) {
            var img = loadedImage;
            var scale = Math.max(bounds.w / img.width, bounds.h / img.height);
            var dw = img.width * scale;
            var dh = img.height * scale;
            var dx = bounds.x + (bounds.w - dw) / 2;
            var dy = bounds.y + (bounds.h - dh) / 2;
            ctx.drawImage(img, dx, dy, dw, dh);
        } else {
            ctx.fillStyle = 'rgba(229, 72, 138, 0.08)';
            ctx.beginPath();
            if (state.shape === 'square') {
                ctx.rect(bounds.x, bounds.y, bounds.w, bounds.h);
            } else {
                ctx.arc(bounds.cx, bounds.cy, bounds.r, 0, Math.PI * 2);
            }
            ctx.fill();
        }

        if (state.texts.some(function (t) { return (t.text || '').trim() !== ''; })) {
            state.texts.forEach(function (textObj) {
                if ((textObj.text || '').trim() === '') {
                    return;
                }
                if (textObj.wrapOnCircle && state.shape === 'circle') {
                    drawTextOnCircle(ctx, bounds, textObj);
                } else {
                    drawTextStraight(ctx, bounds, textObj);
                }
            });
        }

        ctx.restore();
        drawShapeOutline(ctx, bounds);

        lastRenderLayout = {
            paperBounds: paperBounds,
            designBounds: bounds
        };
        updateTextManipulator();
        updateValidateState();
    }

    function hasContent() {
        if (loadedImage) {
            return true;
        }
        return state.texts.some(function (t) {
            return (t.text || '').trim() !== '';
        });
    }

    function updateValidateState() {
        if (btnValidate) {
            btnValidate.disabled = !hasContent();
        }
    }

    function updateValidateLabel() {
        if (!btnValidate) {
            return;
        }
        btnValidate.textContent = isListingForm(activeForm)
            ? 'Valider et ajouter au panier'
            : 'Valider la personnalisation';
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

    function updateStatus(active, previewUrl) {
        hasCustomization = !!active;
        var ctxForm = getActiveContext();

        if (ctxForm.hiddenPath) {
            ctxForm.hiddenPath.value = hasCustomization ? 'pending' : '';
        }
        syncMetaToForm();
        if (ctxForm.statusBox) {
            ctxForm.statusBox.classList.toggle('is-visible', hasCustomization);
        }
        if (ctxForm.statusThumb && previewUrl) {
            ctxForm.statusThumb.src = previewUrl;
        }
        if (ctxForm.btnOpen) {
            ctxForm.btnOpen.classList.toggle('is-active', hasCustomization);
        }
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
            callback(new File([blob], 'personnalisation-sugar-paper.png', { type: 'image/png' }));
        }, 'image/png', 0.92);
    }

    function openModalForButton(btn) {
        var formId = btn.getAttribute('data-perso-form');
        var newForm = formId ? document.getElementById(formId) : document.getElementById('add-to-panier-form');

        if (newForm !== activeForm) {
            resetState();
        }

        activeForm = newForm;
        updateValidateLabel();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        renderPreview();
    }

    function closeModal() {
        finishManipDrag();
        hideTextManipulator();
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function onFileSelected(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }

        sourceUploadFile = file;
        revokePreviewUrl();
        previewObjectUrl = URL.createObjectURL(file);

        var img = new Image();
        img.onload = function () {
            loadedImage = img;
            if (filenameEl) {
                filenameEl.textContent = file.name;
            }
            renderPreview();
        };
        img.onerror = function () {
            loadedImage = null;
            window.alert('Impossible de charger cette image.');
        };
        img.src = previewObjectUrl;
    }

    function validateCustomization() {
        if (!hasContent()) {
            return;
        }

        var ctxForm = getActiveContext();
        syncActiveTextFromControls();
        syncMetaToForm();

        canvasToFile(function (file) {
            if (!file) {
                window.alert('Impossible de générer l\'aperçu. Réessayez.');
                return;
            }

            if (!assignFileToForm(file, ctxForm.formFileInput)) {
                window.alert('Votre navigateur ne permet pas d\'ajouter cette image. Essayez un autre navigateur.');
                return;
            }

            if (sourceUploadFile && ctxForm.formSourceFileInput) {
                assignFileToForm(sourceUploadFile, ctxForm.formSourceFileInput);
            }

            updateStatus(true, canvas.toDataURL('image/png'));
            hasCustomization = true;

            closeModal();
            var formToCheckout = activeForm || document.getElementById('add-to-panier-form');
            if (formToCheckout && window.SugarCheckoutModals && typeof window.SugarCheckoutModals.addFormToCartAndCheckout === 'function') {
                window.SugarCheckoutModals.addFormToCartAndCheckout(formToCheckout);
            } else if (formToCheckout) {
                formToCheckout.submit();
            }
        });
    }

    function bindTextRange(input, valEl, key, parser) {
        if (!input) {
            return;
        }
        input.addEventListener('input', function () {
            var active = getActiveText();
            if (!active) {
                return;
            }
            active[key] = parser(input.value);
            if (valEl) {
                valEl.textContent = String(active[key]);
            }
            renderTextList();
            renderPreview();
        });
    }

    document.addEventListener('click', function (event) {
        var openBtn = event.target.closest('.js-open-perso-modal, #btn-personnaliser');
        if (openBtn) {
            event.preventDefault();
            openModalForButton(openBtn);
        }
    });

    paperBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.paperFormat = btn.getAttribute('data-paper') || 'a4';
            if (!PAPER_FORMATS[state.paperFormat]) {
                state.paperFormat = 'a4';
            }
            clampDimensions();
            updatePaperUi();
            updateShapeUi();
            renderPreview();
        });
    });

    shapeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.shape = btn.getAttribute('data-shape') || 'circle';
            if (state.shape === 'circle') {
                state.heightCm = state.widthCm;
            }
            if (state.shape !== 'circle') {
                state.texts.forEach(function (t) {
                    t.wrapOnCircle = false;
                });
            }
            updateShapeUi();
            syncWrapButton();
            updateWrapUi();
            renderPreview();
        });
    });

    fontBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var active = getActiveText();
            if (active) {
                active.font = btn.getAttribute('data-font') || 'Outfit';
            }
            fontBtns.forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            renderPreview();
        });
    });

    function bindRange(input, valEl, stateKey, parser, asCm) {
        if (!input) {
            return;
        }
        input.addEventListener('input', function () {
            state[stateKey] = parser(input.value);
            if (state.shape === 'circle' && (stateKey === 'widthCm' || stateKey === 'heightCm')) {
                state.heightCm = state.widthCm;
            }
            if (valEl) {
                valEl.textContent = asCm ? formatCm(state[stateKey]) : String(state[stateKey]);
            }
            if (stateKey === 'widthCm' && dimDiameterVal && state.shape === 'circle') {
                setRangeInput(dimDiameter, dimDiameterVal, state.widthCm, true);
            }
            renderPreview();
        });
    }

    bindRange(dimWidth, dimWidthVal, 'widthCm', function (v) { return parseFloat(v) || 15; }, true);
    bindRange(dimHeight, dimHeightVal, 'heightCm', function (v) { return parseFloat(v) || 15; }, true);
    bindRange(dimDiameter, dimDiameterVal, 'widthCm', function (v) { return parseFloat(v) || 15; }, true);
    bindTextRange(textSize, textSizeVal, 'textSizePct', function (v) { return parseInt(v, 10) || 50; });
    bindTextRange(textPosX, textPosXVal, 'textPosX', function (v) { return parseInt(v, 10) || 50; });
    bindTextRange(textPosY, textPosYVal, 'textPosY', function (v) { return parseInt(v, 10) || 50; });
    bindTextRange(textRotation, textRotationVal, 'textRotation', function (v) { return parseInt(v, 10) || 0; });

    if (textInput) {
        textInput.addEventListener('input', function () {
            var active = getActiveText();
            if (active) {
                active.text = textInput.value;
            }
            renderTextList();
            renderPreview();
        });
    }

    if (textAddBtn) {
        textAddBtn.addEventListener('click', function (event) {
            event.preventDefault();
            addTextBlock();
        });
    }

    if (textListEl) {
        textListEl.addEventListener('click', function (event) {
            var removeBtn = event.target.closest('.perso-text-item-remove');
            if (removeBtn && removeBtn.dataset.removeId) {
                event.preventDefault();
                event.stopPropagation();
                removeTextBlock(removeBtn.dataset.removeId);
                return;
            }
            var item = event.target.closest('.perso-text-item');
            if (item && item.dataset.textId) {
                selectText(item.dataset.textId);
            }
        });
    }

    if (textWrapCircle && wrapCircleBtn) {
        wrapCircleBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (state.shape !== 'circle') {
                return;
            }
            var active = getActiveText();
            if (!active) {
                return;
            }
            active.wrapOnCircle = !active.wrapOnCircle;
            syncWrapButton();
            updateWrapUi();
            renderPreview();
        });
    }

    wrapPosBtns.forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var active = getActiveText();
            if (!active || !active.wrapOnCircle || state.shape !== 'circle') {
                return;
            }
            active.wrapArcPosition = btn.getAttribute('data-wrap-pos') === 'bottom' ? 'bottom' : 'top';
            wrapPosBtns.forEach(function (b) {
                var isActive = b === btn;
                b.classList.toggle('is-active', isActive);
                b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            renderPreview();
        });
    });

    colorSwatches.forEach(function (sw) {
        sw.addEventListener('click', function (event) {
            event.stopPropagation();
            setTextColor(sw.getAttribute('data-color'));
        });
    });

    if (textColorInput) {
        textColorInput.addEventListener('input', function (event) {
            event.stopPropagation();
            setTextColor(textColorInput.value);
        });
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

    bindTextManipulatorEvents();

    state.texts = [createDefaultText()];
    state.activeTextId = state.texts[0].id;
    renderTextList();
    syncControlsFromActiveText();
    updatePaperUi();
    updateShapeUi();
    updateWrapUi();
    syncWrapButton();
    syncMetaToForm();
})();
