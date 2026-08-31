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
    var textInput = document.getElementById('perso-text-input');
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
    var hasCustomization = false;

    var state = {
        paperFormat: 'a4',
        shape: 'circle',
        widthCm: 15,
        heightCm: 15,
        text: '',
        font: 'Outfit',
        textSizePct: 50,
        textPosX: 50,
        textPosY: 50,
        textRotation: 0,
        wrapOnCircle: false,
        wrapArcPosition: 'top',
        textColor: '#E5488A'
    };

    var CANVAS_SIZE = 400;

    function isListingForm(form) {
        return !!(form && form.classList && form.classList.contains('perso-cart-form'));
    }

    function getFormContext(form) {
        if (!form) {
            return {
                hiddenPath: document.getElementById('option-image-personnalisation'),
                hiddenMeta: document.getElementById('option-perso-meta'),
                formFileInput: document.getElementById('form-image-personnalisation'),
                statusBox: document.getElementById('perso-status'),
                statusThumb: document.getElementById('perso-status-thumb'),
                btnOpen: document.getElementById('btn-personnaliser')
            };
        }

        return {
            hiddenPath: form.querySelector('.option-image-personnalisation') || document.getElementById('option-image-personnalisation'),
            hiddenMeta: form.querySelector('.option-perso-meta') || document.getElementById('option-perso-meta'),
            formFileInput: form.querySelector('.form-image-personnalisation') || document.getElementById('form-image-personnalisation'),
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
        return {
            format: state.paperFormat,
            shape: state.shape,
            width_cm: state.widthCm,
            height_cm: state.heightCm
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

        if (!isCircle && state.wrapOnCircle) {
            state.wrapOnCircle = false;
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
        var wrapActive = state.wrapOnCircle && state.shape === 'circle';

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
        state.text = '';
        state.font = 'Outfit';
        state.textSizePct = 50;
        state.textPosX = 50;
        state.textPosY = 50;
        state.textRotation = 0;
        state.wrapOnCircle = false;
        state.wrapArcPosition = 'top';
        state.textColor = '#E5488A';
        loadedImage = null;
        hasCustomization = false;

        if (textInput) {
            textInput.value = '';
        }
        setRangeInput(textSize, textSizeVal, 50);
        setRangeInput(textPosX, textPosXVal, 50);
        setRangeInput(textPosY, textPosYVal, 50);
        setRangeInput(textRotation, textRotationVal, 0);
        if (textWrapCircle) {
            textWrapCircle.checked = false;
        }
        if (wrapCircleBtn) {
            wrapCircleBtn.classList.remove('is-active');
            wrapCircleBtn.setAttribute('aria-pressed', 'false');
        }
        wrapPosBtns.forEach(function (btn) {
            var isTop = btn.getAttribute('data-wrap-pos') === 'top';
            btn.classList.toggle('is-active', isTop);
            btn.setAttribute('aria-pressed', isTop ? 'true' : 'false');
        });
        if (textColorInput) {
            textColorInput.value = '#E5488A';
        }
        colorSwatches.forEach(function (sw) {
            sw.classList.toggle('is-active', sw.getAttribute('data-color') === '#E5488A');
        });
        if (filenameEl) {
            filenameEl.textContent = '';
        }
        if (fileInput) {
            fileInput.value = '';
        }

        fontBtns.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-font') === 'Outfit');
        });

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

    function getTextFontSize(bounds) {
        return Math.max(10, (state.textSizePct / 100) * Math.min(bounds.w, bounds.h) * 0.45);
    }

    function getTextFillStyle() {
        return state.textColor || '#E5488A';
    }

    function syncWrapButton() {
        if (!wrapCircleBtn || !textWrapCircle) {
            return;
        }
        var on = state.wrapOnCircle && state.shape === 'circle';
        textWrapCircle.checked = on;
        wrapCircleBtn.classList.toggle('is-active', on);
        wrapCircleBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    function setTextColor(color) {
        if (!color) {
            return;
        }
        state.textColor = color;
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
        renderPreview();
    }

    function applyTextShadow(ctx) {
        if (loadedImage && !state.wrapOnCircle) {
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

    function drawTextStraight(ctx, bounds) {
        var text = state.text.trim();
        if (text === '') {
            return;
        }

        var fontSize = getTextFontSize(bounds);
        var tx = bounds.x + bounds.w * (state.textPosX / 100);
        var ty = bounds.y + bounds.h * (state.textPosY / 100);

        ctx.save();
        ctx.translate(tx, ty);
        ctx.rotate((state.textRotation * Math.PI) / 180);
        ctx.font = '600 ' + fontSize + 'px "' + state.font + '", sans-serif';
        ctx.fillStyle = getTextFillStyle();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        applyTextShadow(ctx);
        ctx.fillText(text, 0, 0, bounds.w * 0.92);
        clearTextShadow(ctx);
        ctx.restore();
    }

    function drawTextOnCircle(ctx, bounds) {
        var text = state.text.trim();
        if (text === '') {
            return;
        }

        var cx = bounds.cx;
        var cy = bounds.cy;
        var maxR = Math.min(bounds.w, bounds.h) / 2;
        var radius = Math.max(20, maxR * (0.25 + 0.7 * (state.textPosY / 100)));
        var fontSize = getTextFontSize(bounds);
        var fillStyle = getTextFillStyle();
        var chars = text.split('');

        ctx.save();
        ctx.font = '600 ' + fontSize + 'px "' + state.font + '", sans-serif';
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
        var arcOffset = ((state.textPosX - 50) / 50) * Math.PI * 0.75;
        var rotOffset = (state.textRotation * Math.PI) / 180;
        var isBottom = state.wrapArcPosition === 'bottom';

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

        if (state.text.trim() !== '') {
            if (state.wrapOnCircle && state.shape === 'circle') {
                drawTextOnCircle(ctx, bounds);
            } else {
                drawTextStraight(ctx, bounds);
            }
        }

        ctx.restore();
        drawShapeOutline(ctx, bounds);

        updateValidateState();
    }

    function hasContent() {
        return !!(loadedImage || (state.text && state.text.trim() !== ''));
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
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function onFileSelected(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }

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
            if (state.shape !== 'circle' && state.wrapOnCircle) {
                state.wrapOnCircle = false;
            }
            updateShapeUi();
            syncWrapButton();
            updateWrapUi();
            renderPreview();
        });
    });

    fontBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.font = btn.getAttribute('data-font') || 'Outfit';
            fontBtns.forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            renderPreview();
        });
    });

    bindRange(dimWidth, dimWidthVal, 'widthCm', function (v) { return parseFloat(v) || 15; }, true);
    bindRange(dimHeight, dimHeightVal, 'heightCm', function (v) { return parseFloat(v) || 15; }, true);
    bindRange(dimDiameter, dimDiameterVal, 'widthCm', function (v) { return parseFloat(v) || 15; }, true);
    bindRange(textSize, textSizeVal, 'textSizePct', function (v) { return parseInt(v, 10) || 50; });
    bindRange(textPosX, textPosXVal, 'textPosX', function (v) { return parseInt(v, 10) || 50; });
    bindRange(textPosY, textPosYVal, 'textPosY', function (v) { return parseInt(v, 10) || 50; });
    bindRange(textRotation, textRotationVal, 'textRotation', function (v) { return parseInt(v, 10) || 0; });

    if (textInput) {
        textInput.addEventListener('input', function () {
            state.text = textInput.value;
            renderPreview();
        });
    }

    if (textWrapCircle && wrapCircleBtn) {
        wrapCircleBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (state.shape !== 'circle') {
                return;
            }
            state.wrapOnCircle = !state.wrapOnCircle;
            syncWrapButton();
            updateWrapUi();
            renderPreview();
        });
    }

    wrapPosBtns.forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!state.wrapOnCircle || state.shape !== 'circle') {
                return;
            }
            state.wrapArcPosition = btn.getAttribute('data-wrap-pos') === 'bottom' ? 'bottom' : 'top';
            wrapPosBtns.forEach(function (b) {
                var active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
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

    updatePaperUi();
    updateShapeUi();
    updateWrapUi();
    syncWrapButton();
    syncMetaToForm();
})();
