/**
 * Moteur de personnalisation texte réutilisable (cupcakes, contours, etc.)
 */
(function () {
    'use strict';

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

    function createTextId() {
        return 'txt_' + Math.random().toString(36).slice(2, 10);
    }

    window.PersoTextEngine = {
        create: function (options) {
            if (!options || !options.modal || !options.prefix) {
                throw new Error('PersoTextEngine.create: modal and prefix are required');
            }

            var modal = options.modal;
            var prefix = options.prefix;
            var p = function (suffix) {
                return document.getElementById(prefix + '-' + suffix);
            };

            var canvas = p('preview-canvas');
            var previewViewport = p('preview-viewport');
            var textManipulator = p('text-manipulator');
            var textManipBox = p('text-manip-box');
            var textDeleteBtn = p('text-delete');
            var textInput = p('text-input');
            var textListEl = p('text-list');
            var textAddBtn = p('text-add');
            var textSize = p('text-size');
            var textSizeVal = p('text-size-val');
            var textPosX = p('text-pos-x');
            var textPosXVal = p('text-pos-x-val');
            var textPosY = p('text-pos-y');
            var textPosYVal = p('text-pos-y-val');
            var textRotation = p('text-rotation');
            var textRotationVal = p('text-rotation-val');
            var textWrapCircle = p('text-wrap-circle');
            var wrapCircleBtn = p('wrap-circle-btn');
            var wrapPositionEl = p('wrap-position');
            var wrapCircleHint = p('wrap-circle-hint');
            var textColorInput = p('text-color');
            var fontBtns = modal.querySelectorAll('.perso-font-btn');
            var colorSwatches = modal.querySelectorAll('.perso-color-swatch');
            var wrapPosBtns = modal.querySelectorAll('.perso-wrap-pos-btn');
            var textControls = modal.querySelector('.perso-text-controls');
            var posXLabel = textPosX ? textPosX.closest('.perso-dimension-field') : null;
            var posYLabel = textPosY ? textPosY.closest('.perso-dimension-field') : null;
            var rotLabel = textRotation ? textRotation.closest('.perso-dimension-field') : null;

            var editTarget = '';
            var manipDrag = null;
            var uiEventsBound = false;
            var hasBackgroundImage = typeof options.hasBackgroundImage === 'function'
                ? options.hasBackgroundImage
                : function () { return false; };

            function getShape() {
                return options.getShape ? options.getShape() : 'circle';
            }

            function getBounds() {
                return options.getBounds ? options.getBounds() : null;
            }

            function isModalOpen() {
                return options.isModalOpen ? options.isModalOpen() : modal.classList.contains('is-open');
            }

            function clientToCanvas(clientX, clientY) {
                return options.clientToCanvas(clientX, clientY);
            }

            function canvasPointToViewport(cx, cy) {
                return options.canvasPointToViewport(cx, cy);
            }

            function isSharedMode() {
                return options.isSharedMode ? options.isSharedMode() : false;
            }

            function getTextsStore() {
                return options.getTextsStore();
            }

            function ensureSlotSelected() {
                if (options.ensureSlotSelected) {
                    return options.ensureSlotSelected();
                }
                return true;
            }

            function notifyChange() {
                if (options.onChange) {
                    options.onChange();
                }
            }

            function setRangeInput(input, valEl, value) {
                if (input) {
                    input.value = String(value);
                }
                if (valEl) {
                    valEl.textContent = String(value);
                }
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

            function cloneTexts(texts) {
                if (!texts || !texts.length) {
                    return [];
                }
                return texts.map(function (t) {
                    return {
                        id: t.id || createTextId(),
                        text: t.text || '',
                        font: t.font || 'Outfit',
                        textSizePct: typeof t.textSizePct === 'number' ? t.textSizePct : 50,
                        textPosX: typeof t.textPosX === 'number' ? t.textPosX : 50,
                        textPosY: typeof t.textPosY === 'number' ? t.textPosY : 50,
                        textRotation: typeof t.textRotation === 'number' ? t.textRotation : 0,
                        wrapOnCircle: !!t.wrapOnCircle,
                        wrapArcPosition: t.wrapArcPosition === 'bottom' ? 'bottom' : 'top',
                        textColor: t.textColor || '#E5488A'
                    };
                });
            }

            function resetSingleTextBlock(textObj) {
                var offsetY = textObj.textPosY || 50;
                var fresh = createDefaultText(offsetY);
                fresh.id = textObj.id;
                return fresh;
            }

            function getActiveText() {
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return null;
                }
                for (var i = 0; i < store.texts.length; i++) {
                    if (store.texts[i].id === store.activeTextId) {
                        return store.texts[i];
                    }
                }
                return store.texts[0] || null;
            }

            function textBlockCanBeRemoved(textObj) {
                if (!textObj) {
                    return false;
                }
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return false;
                }
                if (store.texts.length > 1) {
                    return true;
                }
                return (textObj.text || '').trim() !== '';
            }

            function getTextFontSize(bounds, textObj) {
                return Math.max(10, ((textObj.textSizePct || 50) / 100) * Math.min(bounds.w, bounds.h) * 0.45);
            }

            function getTextFillStyle(textObj) {
                return textObj.textColor || '#E5488A';
            }

            function applyTextShadow(ctx, textObj) {
                if (hasBackgroundImage() && !(textObj && textObj.wrapOnCircle)) {
                    ctx.shadowColor = 'rgba(0,0,0,0.45)';
                    ctx.shadowBlur = 4;
                }
            }

            function clearTextShadow(ctx) {
                ctx.shadowBlur = 0;
                ctx.shadowColor = 'transparent';
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

            function getEmptyTextLayout(bounds, textObj) {
                return {
                    cx: bounds.x + bounds.w * ((textObj.textPosX || 50) / 100),
                    cy: bounds.y + bounds.h * ((textObj.textPosY || 50) / 100),
                    width: 56,
                    height: 36,
                    rotation: textObj.textRotation || 0
                };
            }

            function getTextHitLayout(textObj, ctx, bounds, shape) {
                if ((textObj.text || '').trim() !== '') {
                    if (textObj.wrapOnCircle && shape === 'circle') {
                        return measureWrapTextLayout(bounds, textObj);
                    }
                    return measureStraightTextLayout(ctx, bounds, textObj);
                }
                return getEmptyTextLayout(bounds, textObj);
            }

            function getTextLayout(textObj, bounds, shape) {
                if (!canvas || !textObj || !bounds) {
                    return null;
                }
                if ((textObj.text || '').trim() === '') {
                    return null;
                }
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    return null;
                }
                if (textObj.wrapOnCircle && shape === 'circle') {
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

            function syncWrapButton() {
                if (!wrapCircleBtn || !textWrapCircle) {
                    return;
                }
                var active = getActiveText();
                var shape = getShape();
                var on = !!(active && active.wrapOnCircle && shape === 'circle');
                textWrapCircle.checked = on;
                wrapCircleBtn.classList.toggle('is-active', on);
                wrapCircleBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            }

            function updateWrapUi() {
                var active = getActiveText();
                var shape = getShape();
                var wrapActive = !!(active && active.wrapOnCircle && shape === 'circle');

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
                if (wrapPositionEl) {
                    wrapPositionEl.hidden = !wrapActive;
                }
                if (wrapCircleBtn) {
                    var isCircle = shape === 'circle';
                    wrapCircleBtn.classList.toggle('is-disabled', !isCircle);
                    wrapCircleBtn.disabled = !isCircle;
                }
                if (wrapCircleHint) {
                    var isCircleHint = shape === 'circle';
                    wrapCircleHint.textContent = isCircleHint
                        ? 'Le texte suit le contour intérieur du cercle.'
                        : 'Disponible uniquement avec la forme cercle.';
                }
            }

            function setTextColor(color, skipNotify) {
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
                if (!skipNotify) {
                    notifyChange();
                }
            }

            function renderTextList() {
                if (!textListEl) {
                    return;
                }
                var store = getTextsStore();
                if (!store || !store.texts) {
                    textListEl.innerHTML = '';
                    return;
                }
                textListEl.innerHTML = '';
                store.texts.forEach(function (textObj, index) {
                    var item = document.createElement('div');
                    item.className = 'perso-text-item' + (textObj.id === store.activeTextId ? ' is-active' : '');
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
                    if (textBlockCanBeRemoved(textObj)) {
                        item.appendChild(removeBtn);
                    }
                    textListEl.appendChild(item);
                });
            }

            function syncFromControls() {
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

            function syncToControls() {
                var active = getActiveText();
                if (!active) {
                    return;
                }
                var shape = getShape();
                if (textInput) {
                    textInput.value = active.text || '';
                }
                setRangeInput(textSize, textSizeVal, active.textSizePct);
                setRangeInput(textPosX, textPosXVal, active.textPosX);
                setRangeInput(textPosY, textPosYVal, active.textPosY);
                setRangeInput(textRotation, textRotationVal, active.textRotation);
                setTextColor(active.textColor || '#E5488A', true);
                if (textWrapCircle) {
                    textWrapCircle.checked = !!(active.wrapOnCircle && shape === 'circle');
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

            function selectText(textId) {
                syncFromControls();
                var store = getTextsStore();
                store.activeTextId = textId;
                editTarget = 'text';
                syncToControls();
                renderTextList();
                notifyChange();
            }

            function addTextBlock() {
                syncFromControls();
                var store = getTextsStore();
                var offset = 40 + (store.texts.length * 8);
                var textObj = createDefaultText(Math.min(85, offset));
                store.texts.push(textObj);
                selectText(textObj.id);
            }

            function removeTextBlock(textId) {
                syncFromControls();
                var store = getTextsStore();
                var target = null;
                for (var i = 0; i < store.texts.length; i++) {
                    if (store.texts[i].id === textId) {
                        target = store.texts[i];
                        break;
                    }
                }
                if (!target || !textBlockCanBeRemoved(target)) {
                    return;
                }

                if (store.texts.length <= 1) {
                    store.texts[0] = resetSingleTextBlock(target);
                    store.activeTextId = store.texts[0].id;
                } else {
                    store.texts = store.texts.filter(function (t) { return t.id !== textId; });
                    if (!getActiveText()) {
                        store.activeTextId = store.texts[0].id;
                    }
                }

                finishDrag();
                editTarget = 'text';
                syncToControls();
                renderTextList();
                hideManipulator();
                updateManipulator();
                notifyChange();
            }

            function removeActiveText() {
                var active = getActiveText();
                if (!active) {
                    return;
                }
                removeTextBlock(active.id);
            }

            function hideManipulator() {
                if (!textManipulator) {
                    return;
                }
                textManipulator.hidden = true;
                textManipulator.setAttribute('aria-hidden', 'true');
                textManipulator.classList.remove('is-visible');
            }

            function updateManipulator() {
                if (!textManipulator || !textManipBox || !previewViewport || !canvas) {
                    return;
                }
                if (!isModalOpen()) {
                    hideManipulator();
                    return;
                }
                if (editTarget !== 'text') {
                    hideManipulator();
                    return;
                }

                var active = getActiveText();
                if (!active) {
                    hideManipulator();
                    return;
                }

                var bounds = getBounds();
                if (!bounds) {
                    hideManipulator();
                    return;
                }

                var shape = getShape();
                var layout = getTextLayout(active, bounds, shape);
                if (!layout) {
                    layout = getEmptyTextLayout(bounds, active);
                }
                if (!layout) {
                    hideManipulator();
                    return;
                }

                var vp = canvasPointToViewport(layout.cx, layout.cy);
                var boxW = Math.max(40, layout.width * vp.scale + 12);
                var boxH = Math.max(28, layout.height * vp.scale + 12);

                textManipulator.hidden = false;
                textManipulator.setAttribute('aria-hidden', 'false');
                textManipulator.classList.add('is-visible');

                textManipBox.classList.toggle('is-wrap-mode', !!(active.wrapOnCircle && shape === 'circle'));
                textManipBox.style.width = boxW + 'px';
                textManipBox.style.height = boxH + 'px';
                textManipBox.style.left = (vp.x - boxW / 2) + 'px';
                textManipBox.style.top = (vp.y - boxH / 2) + 'px';
                textManipBox.style.transform = 'rotate(' + layout.rotation + 'deg)';
                if (textDeleteBtn) {
                    textDeleteBtn.hidden = !textBlockCanBeRemoved(active);
                }
            }

            function applyTextMove(active, canvasX, canvasY, bounds, shape) {
                if (!bounds || !active) {
                    return;
                }
                if (active.wrapOnCircle && shape === 'circle') {
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

            function finishDrag() {
                if (!manipDrag) {
                    return;
                }
                if (textManipBox) {
                    textManipBox.classList.remove('is-dragging');
                }
                manipDrag = null;
                syncToControls();
                renderTextList();
                updateManipulator();
            }

            function startTextDrag(mode, handle, clientX, clientY) {
                var bounds = getBounds();
                if (!bounds) {
                    return;
                }
                var active = getActiveText();
                if (!active) {
                    return;
                }
                syncFromControls();
                var shape = getShape();
                var layout = getTextLayout(active, bounds, shape);
                if (!layout) {
                    layout = getEmptyTextLayout(bounds, active);
                }
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

            function onPointerMove(clientX, clientY) {
                if (!manipDrag) {
                    return;
                }
                var bounds = getBounds();
                if (!bounds) {
                    return;
                }
                var shape = getShape();
                var canvasPt = clientToCanvas(clientX, clientY);
                var store = getTextsStore();
                var active = null;
                for (var i = 0; i < store.texts.length; i++) {
                    if (store.texts[i].id === manipDrag.textId) {
                        active = store.texts[i];
                        break;
                    }
                }
                if (!active) {
                    return;
                }
                var layout = getTextLayout(active, bounds, shape);
                if (!layout) {
                    layout = getEmptyTextLayout(bounds, active);
                }

                if (manipDrag.mode === 'move') {
                    applyTextMove(active, canvasPt.x, canvasPt.y, bounds, shape);
                } else if (manipDrag.mode === 'rotate') {
                    if (layout) {
                        applyTextRotate(active, canvasPt.x, canvasPt.y, layout);
                    }
                } else if (manipDrag.mode === 'resize') {
                    if (layout) {
                        applyTextResize(active, canvasPt.x, canvasPt.y, layout, manipDrag.startDist, manipDrag.startSizePct);
                    }
                }

                syncToControls();
                notifyChange();
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

            function drawTextsArray(ctx, bounds, shape, texts) {
                if (!ctx || !bounds || !texts) {
                    return;
                }
                texts.forEach(function (textObj) {
                    if ((textObj.text || '').trim() === '') {
                        return;
                    }
                    if (textObj.wrapOnCircle && shape === 'circle') {
                        drawTextOnCircle(ctx, bounds, textObj);
                    } else {
                        drawTextStraight(ctx, bounds, textObj);
                    }
                });
            }

            function drawTexts(ctx, bounds, shape) {
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return;
                }
                drawTextsArray(ctx, bounds, shape, store.texts);
            }

            function hitTestText(canvasX, canvasY, bounds, shape) {
                if (!canvas || !bounds) {
                    return null;
                }
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    return null;
                }
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return null;
                }
                for (var i = store.texts.length - 1; i >= 0; i--) {
                    var textObj = store.texts[i];
                    var layout = getTextHitLayout(textObj, ctx, bounds, shape);
                    if (!layout) {
                        continue;
                    }
                    if (pointInRotatedRect(canvasX, canvasY, layout.cx, layout.cy, layout.width + 20, layout.height + 20, layout.rotation)) {
                        return textObj.id;
                    }
                }
                return null;
            }

            function deselectText() {
                finishDrag();
                editTarget = '';
                hideManipulator();
            }

            function handleCanvasPointerDown(event) {
                if (!isModalOpen() || event.button > 0) {
                    return false;
                }
                if (event.target.closest('.perso-manip-delete')) {
                    return false;
                }
                ensureSlotSelected();
                var bounds = getBounds();
                if (!bounds) {
                    return false;
                }
                var shape = getShape();
                var canvasPt = clientToCanvas(event.clientX, event.clientY);
                var hitId = hitTestText(canvasPt.x, canvasPt.y, bounds, shape);

                if (hitId) {
                    selectText(hitId);
                    startTextDrag('move', 'box', event.clientX, event.clientY);
                    return true;
                }

                deselectText();
                return false;
            }

            function handleManipBoxPointerDown(event) {
                if (!isModalOpen()) {
                    return;
                }
                if (event.target.closest('.perso-manip-delete')) {
                    return;
                }
                editTarget = 'text';
                var handleEl = event.target.closest('.perso-text-handle');
                var handle = handleEl ? (handleEl.getAttribute('data-handle') || '') : '';
                var mode = 'move';
                if (handle === 'rotate') {
                    mode = 'rotate';
                } else if (handle && handle !== '') {
                    mode = 'resize';
                }
                startTextDrag(mode, handle, event.clientX, event.clientY);
                event.preventDefault();
                event.stopPropagation();
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
                    notifyChange();
                });
            }

            function bindUiEvents() {
                if (uiEventsBound) {
                    return;
                }
                uiEventsBound = true;

                fontBtns.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var active = getActiveText();
                        if (active) {
                            active.font = btn.getAttribute('data-font') || 'Outfit';
                        }
                        fontBtns.forEach(function (b) {
                            b.classList.toggle('is-active', b === btn);
                        });
                        notifyChange();
                    });
                });

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
                        notifyChange();
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
                        if (getShape() !== 'circle') {
                            return;
                        }
                        var active = getActiveText();
                        if (!active) {
                            return;
                        }
                        active.wrapOnCircle = !active.wrapOnCircle;
                        syncWrapButton();
                        updateWrapUi();
                        notifyChange();
                    });
                }

                wrapPosBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        var active = getActiveText();
                        if (!active || !active.wrapOnCircle || getShape() !== 'circle') {
                            return;
                        }
                        active.wrapArcPosition = btn.getAttribute('data-wrap-pos') === 'bottom' ? 'bottom' : 'top';
                        wrapPosBtns.forEach(function (b) {
                            var isActive = b === btn;
                            b.classList.toggle('is-active', isActive);
                            b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        });
                        notifyChange();
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

                if (textDeleteBtn) {
                    textDeleteBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        removeActiveText();
                    });
                }

                window.addEventListener('resize', function () {
                    updateManipulator();
                });
            }

            function resetStore(store) {
                if (!store) {
                    return;
                }
                var def = createDefaultText();
                store.texts = [def];
                store.activeTextId = def.id;
            }

            function reset() {
                var store = getTextsStore();
                resetStore(store);
                editTarget = '';
                hideManipulator();
                syncToControls();
                renderTextList();
                notifyChange();
            }

            function hasTextContent() {
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return false;
                }
                return store.texts.some(function (t) {
                    return (t.text || '').trim() !== '';
                });
            }

            function hasAnyTextContent(getAllStores) {
                if (typeof getAllStores === 'function') {
                    var all = getAllStores();
                    if (all && all.length) {
                        for (var i = 0; i < all.length; i++) {
                            var texts = all[i];
                            if (texts && texts.some(function (t) { return (t.text || '').trim() !== ''; })) {
                                return true;
                            }
                        }
                        return false;
                    }
                }
                return hasTextContent();
            }

            function loadTexts(array) {
                var store = getTextsStore();
                if (!array || !array.length) {
                    resetStore(store);
                } else {
                    store.texts = cloneTexts(array);
                    store.activeTextId = store.texts[0].id;
                }
                editTarget = '';
                syncToControls();
                renderTextList();
                notifyChange();
            }

            function serializeTexts() {
                var store = getTextsStore();
                if (!store || !store.texts) {
                    return [];
                }
                return cloneTexts(store.texts);
            }

            function getEditTarget() {
                return editTarget;
            }

            function setEditTarget(target) {
                editTarget = target === 'text' ? 'text' : '';
                if (editTarget !== 'text') {
                    hideManipulator();
                } else {
                    updateManipulator();
                }
            }

            function getManipDrag() {
                return manipDrag;
            }

            return {
                reset: reset,
                resetStore: resetStore,
                createDefaultText: createDefaultText,
                cloneTexts: cloneTexts,
                syncFromControls: syncFromControls,
                syncToControls: syncToControls,
                drawTexts: drawTexts,
                drawTextsArray: drawTextsArray,
                hasTextContent: hasTextContent,
                hasAnyTextContent: hasAnyTextContent,
                hitTestText: hitTestText,
                updateManipulator: updateManipulator,
                hideManipulator: hideManipulator,
                getEditTarget: getEditTarget,
                setEditTarget: setEditTarget,
                handleCanvasPointerDown: handleCanvasPointerDown,
                handleManipBoxPointerDown: handleManipBoxPointerDown,
                onPointerMove: onPointerMove,
                finishDrag: finishDrag,
                getManipDrag: getManipDrag,
                startTextDrag: startTextDrag,
                serializeTexts: serializeTexts,
                loadTexts: loadTexts,
                bindUiEvents: bindUiEvents
            };
        }
    };
})();
