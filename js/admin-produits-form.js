(function () {
            var input = document.getElementById('images_produit');
            var container = document.getElementById('preview-images');
            if (!input || !container) {
                return;
            }
            var accumulatedFiles = [];

            function updateInputFiles() {
                var dt = new DataTransfer();
                for (var i = 0; i < accumulatedFiles.length; i++) {
                    dt.items.add(accumulatedFiles[i]);
                }
                input.files = dt.files;
            }

            function addPreviews(newFiles) {
                for (var i = 0; i < newFiles.length; i++) {
                    (function (file, idx) {
                        if (!file.type.match('image.*')) return;
                        var pos = accumulatedFiles.length;
                        accumulatedFiles.push(file);
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            var div = document.createElement('div');
                            div.className = 'preview-item';
                            div.dataset.index = pos;
                            var badge = document.createElement('span');
                            badge.className = 'preview-badge';
                            badge.textContent = pos === 0 ? 'Principale' : (pos + 1);
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Aperçu ' + (pos + 1);
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'preview-remove';
                            btn.innerHTML = '&times;';
                            btn.title = 'Retirer';
                            btn.onclick = function (ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                var idx = parseInt(div.dataset.index, 10);
                                accumulatedFiles.splice(idx, 1);
                                div.remove();
                                for (var j = 0; j < container.children.length; j++) {
                                    container.children[j].dataset.index = j;
                                    container.children[j].querySelector('.preview-badge').textContent =
                                        j === 0 ? 'Principale' : (j + 1);
                                }
                                updateInputFiles();
                            };
                            div.appendChild(badge);
                            div.appendChild(img);
                            div.appendChild(btn);
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    })(newFiles[i], i);
                }
                updateInputFiles();
            }

            input.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    var newFiles = [];
                    for (var i = 0; i < this.files.length; i++) {
                        newFiles.push(this.files[i]);
                    }
                    addPreviews(newFiles);
                }
            });

            document.querySelector('.form-add').addEventListener('submit', function (e) {
                if (!input || !container) {
                    return;
                }
                if (accumulatedFiles.length === 0) {
                    e.preventDefault();
                    alert('Veuillez ajouter au moins une image.');
                    return false;
                }
            });
        })();
        (function () {
            function openOptionCard(card) {
                if (!card) return;
                card.classList.add('is-open');
                var form = card.querySelector('.option-reveal-form');
                if (form) {
                    form.hidden = false;
                    form.classList.add('is-open');
                }
                var first = card.querySelector('.option-reveal-form input:not([type="hidden"])');
                if (first) first.focus();
            }

            function closeOptionCard(card) {
                if (!card) return;
                card.classList.remove('is-open');
                var form = card.querySelector('.option-reveal-form');
                if (form) {
                    form.hidden = true;
                    form.classList.remove('is-open');
                }
            }

            function updateOptionCount(listId, badgeId) {
                var list = document.getElementById(listId);
                var badge = document.getElementById(badgeId);
                if (!badge) return;
                var n = list ? list.children.length : 0;
                badge.textContent = n;
                badge.hidden = n === 0;
            }
            window.updateProduitOptionCount = updateOptionCount;

            document.querySelectorAll('.btn-reveal-option').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openOptionCard(btn.closest('.option-card'));
                });
            });
            document.querySelectorAll('.btn-cancel-option').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    closeOptionCard(btn.closest('.option-card'));
                });
            });
        })();
        (function () {
            var couleurInput = document.getElementById('couleur-input');
            var btnAdd = document.getElementById('btn-add-couleur');
            var list = document.getElementById('couleurs-list');
            var hidden = document.getElementById('couleurs-hidden');
            var hexPreview = document.getElementById('couleur-hex-preview');
            var couleurs = [];
            try {
                if (hidden && hidden.value) {
                    var parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed)) {
                        couleurs = parsed.filter(function (c) {
                            return typeof c === 'string' && /^#[0-9A-Fa-f]{6}$/.test(c);
                        });
                    }
                }
            } catch (e) { }

            function updateHidden() {
                if (hidden) hidden.value = JSON.stringify(couleurs);
            }

            function render() {
                if (!list) return;
                list.innerHTML = '';
                couleurs.forEach(function (hex, i) {
                    var div = document.createElement('div');
                    div.className = 'couleur-swatch';
                    div.innerHTML = '<span class="swatch-preview" style="background:' + hex +
                        '"></span><span class="swatch-hex">' + hex +
                        '</span><button type="button" class="swatch-remove" data-i="' + i +
                        '" title="Retirer">&times;</button>';
                    list.appendChild(div);
                });
                updateHidden();
                if (window.updateProduitOptionCount) {
                    window.updateProduitOptionCount('couleurs-list', 'couleurs-count');
                }
            }
            if (couleurInput && hexPreview) {
                couleurInput.addEventListener('input', function () {
                    hexPreview.textContent = (couleurInput.value || '').toUpperCase();
                });
            }
            if (btnAdd && couleurInput) {
                btnAdd.addEventListener('click', function () {
                    var hex = couleurInput.value;
                    if (hex && couleurs.indexOf(hex) === -1) {
                        couleurs.push(hex);
                        render();
                    }
                });
            }
            if (list) {
                list.addEventListener('click', function (e) {
                    var btn = e.target.closest('.swatch-remove');
                    if (btn) {
                        var i = parseInt(btn.dataset.i, 10);
                        couleurs.splice(i, 1);
                        render();
                    }
                });
            }
            render();
        })();
        (function () {
            function initOptionsWithSurcharge(idInput, idSurcharge, idList, idHidden, btnId, badgeId) {
                var input = document.getElementById(idInput);
                var surchargeInput = document.getElementById(idSurcharge);
                var list = document.getElementById(idList);
                var hidden = document.getElementById(idHidden);
                var btn = document.getElementById(btnId);
                var values = [];
                try {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        var parsed = JSON.parse(hidden.value);
                        if (Array.isArray(parsed)) values = parsed;
                        else values = (hidden.value.split(',').map(function (s) {
                            return {
                                v: s.trim(),
                                s: 0
                            };
                        })).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                } catch (e) {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        values = hidden.value.split(',').map(function (s) {
                            return {
                                v: s.trim(),
                                s: 0
                            };
                        }).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                }
                values = values.filter(function (item) {
                    var v = typeof item === 'object' ? item.v : item;
                    return v && v !== '[]' && String(v).trim() !== '';
                });

                function updateHidden() {
                    if (hidden) hidden.value = JSON.stringify(values);
                }

                function render() {
                    if (!list) return;
                    list.innerHTML = '';
                    values.forEach(function (item, i) {
                        var v = typeof item === 'object' ? item.v : item;
                        var s = typeof item === 'object' ? (item.s || 0) : 0;
                        var surc = s > 0 ? ' <span class="tag-surcharge">+' + s + ' FCFA</span>' : '';
                        var div = document.createElement('div');
                        div.className = 'option-tag';
                        div.innerHTML = '<span>' + (v.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + surc +
                            '</span><button type="button" class="tag-remove" data-i="' + i +
                            '" title="Retirer">&times;</button>';
                        list.appendChild(div);
                    });
                    updateHidden();
                    if (window.updateProduitOptionCount && badgeId) {
                        window.updateProduitOptionCount(idList, badgeId);
                    }
                }
                if (btn && input) {
                    btn.addEventListener('click', function () {
                        var val = (input.value || '').trim();
                        var surc = surchargeInput ? (parseInt(surchargeInput.value, 10) || 0) : 0;
                        if (val) {
                            var exists = values.some(function (x) {
                                return (typeof x === 'object' ? x.v : x) === val;
                            });
                            if (!exists) {
                                values.push({
                                    v: val,
                                    s: surc
                                });
                                input.value = '';
                                if (surchargeInput) surchargeInput.value = '';
                                render();
                                input.focus();
                            }
                        } else {
                            input.focus();
                        }
                    });
                    input.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            btn.click();
                        }
                    });
                }
                if (list) {
                    list.addEventListener('click', function (e) {
                        var b = e.target.closest('.tag-remove');
                        if (b) {
                            values.splice(parseInt(b.dataset.i, 10), 1);
                            render();
                        }
                    });
                }
                render();
            }
            initOptionsWithSurcharge('poids-input', 'poids-surcharge', 'poids-list', 'poids-hidden', 'btn-add-poids', 'poids-count');
            initOptionsWithSurcharge('taille-input', 'taille-surcharge', 'taille-list', 'taille-hidden',
                'btn-add-taille', 'taille-count');
        })();
        (function () {
            var container = document.getElementById('variantes-container');
            var btnAdd = document.getElementById('btn-add-variante');
            var idx = 1;

            function getVarianteRowHtml() {
                return '<div class="variante-row">' +
                    '<input type="hidden" name="variantes_id[]" value="">' +
                    '<input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)" class="variante-nom">' +
                    '<input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">' +
                    '<input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">' +
                    '<div class="variante-image-wrap">' +
                    '<div class="variante-image-area">' +
                    '<input type="file" name="variantes_image[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="variante-image-input">' +
                    '<span class="variante-image-label"><i class="fas fa-image"></i> Image</span>' +
                    '<img class="variante-preview-img" src="" alt="" style="display: none;">' +
                    '</div></div>' +
                    '<button type="button" class="btn-remove-variante" title="Supprimer">&times;</button></div>';
            }

            function previewVarianteImage(input) {
                var wrap = input.closest('.variante-image-wrap');
                if (!wrap) return;
                var img = wrap.querySelector('.variante-preview-img');
                var label = wrap.querySelector('.variante-image-label');
                if (!img || !label) return;
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        label.style.display = 'none';
                    };
                    reader.readAsDataURL(input.files[0]);
                } else {
                    img.src = '';
                    img.style.display = 'none';
                    label.style.display = '';
                }
            }
            if (container) {
                container.addEventListener('change', function (e) {
                    if (e.target.classList.contains('variante-image-input')) {
                        previewVarianteImage(e.target);
                    }
                });
            }
            if (btnAdd && container) {
                btnAdd.addEventListener('click', function () {
                    var div = document.createElement('div');
                    div.className = 'variante-item';
                    div.dataset.index = idx++;
                    div.innerHTML = getVarianteRowHtml();
                    container.appendChild(div);
                    div.querySelector('.btn-remove-variante').addEventListener('click', function () {
                        div.remove();
                    });
                });
                container.addEventListener('click', function (e) {
                    var b = e.target.closest('.btn-remove-variante');
                    if (b && container.children.length > 1) b.closest('.variante-item').remove();
                });
            }
        })();
