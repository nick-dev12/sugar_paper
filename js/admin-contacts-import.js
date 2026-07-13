/**
 * Import contacts — Contact Picker (Android) + fichiers VCF/CSV
 * Soumission via formulaire PHP (pas d'AJAX).
 */
(function () {
    'use strict';

    function supportsNativePickContacts() {
        return !!(
            window.__SUGARPAPER_NATIVE_APP &&
            window.SugarPaperNative &&
            typeof window.SugarPaperNative.pickContacts === 'function'
        );
    }

    function supportsNativeGetAllContacts() {
        return !!(
            window.__SUGARPAPER_NATIVE_APP &&
            window.SugarPaperNative &&
            typeof window.SugarPaperNative.getDeviceContacts === 'function'
        );
    }

    function supportsContactPicker() {
        if (supportsNativePickContacts()) {
            return true;
        }
        return !!(navigator.contacts && typeof navigator.contacts.select === 'function');
    }

    function pickFromNativeApp() {
        return window.SugarPaperNative.pickContacts().then(function (result) {
            if (result && result.cancelled) {
                var cancelled = new Error('Import annulé');
                cancelled.cancelled = true;
                throw cancelled;
            }
            if (result && result.success && Array.isArray(result.contacts)) {
                return result.contacts;
            }
            throw new Error((result && result.error) ? result.error : 'Import natif impossible');
        });
    }

    function importAllFromNativeApp() {
        return window.SugarPaperNative.getDeviceContacts().then(function (result) {
            if (result && result.success && Array.isArray(result.contacts)) {
                return result.contacts;
            }
            throw new Error((result && result.error) ? result.error : 'Lecture des contacts impossible');
        });
    }

    function pickFromContactPickerApi() {
        return navigator.contacts.select(['name', 'tel', 'email'], { multiple: true })
            .then(function (contacts) {
                return (contacts || []).map(mapPickerContact);
            });
    }

    function splitFullName(full) {
        full = String(full || '').trim().replace(/\s+/g, ' ');
        if (!full) {
            return { nom: 'Sans nom', prenom: '' };
        }
        var parts = full.split(' ');
        if (parts.length === 1) {
            return { nom: parts[0], prenom: '' };
        }
        return {
            prenom: parts[0],
            nom: parts.slice(1).join(' ')
        };
    }

    function firstOf(arr) {
        if (!arr || !arr.length) {
            return '';
        }
        var v = arr[0];
        if (v && typeof v === 'object') {
            return String(v.value || v.tel || v.email || '').trim();
        }
        return String(v || '').trim();
    }

    function normalizePhoneRows(rows) {
        var out = [];
        var seen = {};
        rows.forEach(function (row) {
            if (!row) {
                return;
            }
            var tel = String(row.telephone || '').trim();
            if (!tel) {
                return;
            }
            var key = tel.replace(/\D+/g, '');
            if (key.length < 6 || seen[key]) {
                return;
            }
            seen[key] = true;
            out.push({
                nom: String(row.nom || '').trim() || 'Sans nom',
                prenom: String(row.prenom || '').trim(),
                telephone: tel,
                email: String(row.email || '').trim()
            });
        });
        return out;
    }

    function mapPickerContact(c) {
        var fullName = firstOf(c.name);
        var parts = splitFullName(fullName);
        return {
            nom: parts.nom,
            prenom: parts.prenom,
            telephone: firstOf(c.tel),
            email: firstOf(c.email)
        };
    }

    function unescapeVcard(value) {
        return String(value || '')
            .replace(/\\n/gi, ' ')
            .replace(/\\,/g, ',')
            .replace(/\\;/g, ';')
            .replace(/\\\\/g, '\\')
            .trim();
    }

    function parseVcard(text) {
        var rows = [];
        var blocks = String(text || '').split(/BEGIN:VCARD/i);
        blocks.forEach(function (block) {
            if (!/END:VCARD/i.test(block)) {
                return;
            }
            var fn = '';
            var family = '';
            var given = '';
            var tel = '';
            var email = '';
            var lines = block.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            var unfolded = [];
            lines.forEach(function (line) {
                if (/^[ \t]/.test(line) && unfolded.length) {
                    unfolded[unfolded.length - 1] += line.replace(/^[ \t]/, '');
                } else {
                    unfolded.push(line);
                }
            });
            unfolded.forEach(function (line) {
                var m = line.match(/^([^:;]+)(;[^:]*)?:(.*)$/);
                if (!m) {
                    return;
                }
                var key = m[1].toUpperCase();
                var value = unescapeVcard(m[3]);
                if (key === 'FN') {
                    fn = value;
                } else if (key === 'N') {
                    var nParts = value.split(';');
                    family = (nParts[0] || '').trim();
                    given = (nParts[1] || '').trim();
                } else if (key === 'TEL' && !tel) {
                    tel = value.replace(/^tel:/i, '').trim();
                } else if (key === 'EMAIL' && !email) {
                    email = value.replace(/^mailto:/i, '').trim();
                }
            });
            var nom = family;
            var prenom = given;
            if (!nom && !prenom && fn) {
                var split = splitFullName(fn);
                nom = split.nom;
                prenom = split.prenom;
            }
            if (!nom) {
                nom = fn || prenom || 'Sans nom';
                if (prenom && nom === prenom) {
                    prenom = '';
                }
            }
            if (tel) {
                rows.push({ nom: nom, prenom: prenom, telephone: tel, email: email });
            }
        });
        return rows;
    }

    function parseCsv(text) {
        var lines = String(text || '').replace(/^\uFEFF/, '').split(/\r?\n/).filter(function (l) {
            return l.trim() !== '';
        });
        if (!lines.length) {
            return [];
        }

        function splitLine(line) {
            var cells = [];
            var cur = '';
            var inQuotes = false;
            for (var i = 0; i < line.length; i++) {
                var ch = line[i];
                if (ch === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        cur += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if ((ch === ',' || ch === ';') && !inQuotes) {
                    cells.push(cur.trim());
                    cur = '';
                } else {
                    cur += ch;
                }
            }
            cells.push(cur.trim());
            return cells;
        }

        var header = splitLine(lines[0]).map(function (h) {
            return h.toLowerCase().replace(/["']/g, '').trim();
        });
        var hasHeader = header.some(function (h) {
            return /nom|name|prenom|first|tel|phone|mobile|email|mail/.test(h);
        });

        function idx(names) {
            for (var i = 0; i < names.length; i++) {
                var j = header.indexOf(names[i]);
                if (j >= 0) {
                    return j;
                }
            }
            return -1;
        }

        var iNom = hasHeader ? idx(['nom', 'name', 'lastname', 'last_name', 'family']) : 0;
        var iPrenom = hasHeader ? idx(['prenom', 'prénom', 'firstname', 'first_name', 'first']) : 1;
        var iTel = hasHeader ? idx(['telephone', 'téléphone', 'tel', 'phone', 'mobile', 'portable']) : 2;
        var iEmail = hasHeader ? idx(['email', 'mail', 'e-mail']) : 3;
        var start = hasHeader ? 1 : 0;
        var rows = [];

        for (var li = start; li < lines.length; li++) {
            var cols = splitLine(lines[li]);
            var tel = iTel >= 0 ? (cols[iTel] || '') : '';
            var nom = iNom >= 0 ? (cols[iNom] || '') : '';
            var prenom = iPrenom >= 0 ? (cols[iPrenom] || '') : '';
            var email = iEmail >= 0 ? (cols[iEmail] || '') : '';
            if (!tel && cols.length === 1) {
                continue;
            }
            if (!nom && prenom) {
                nom = prenom;
                prenom = '';
            }
            if (tel) {
                rows.push({ nom: nom || 'Sans nom', prenom: prenom, telephone: tel, email: email });
            }
        }
        return rows;
    }

    function parseFileContent(filename, text) {
        var lower = String(filename || '').toLowerCase();
        if (lower.indexOf('.vcf') !== -1 || /BEGIN:VCARD/i.test(text)) {
            return parseVcard(text);
        }
        return parseCsv(text);
    }

    function setStatus(el, message, isError) {
        if (!el) {
            return;
        }
        el.hidden = !message;
        el.textContent = message || '';
        el.classList.toggle('is-error', !!isError);
    }

    function submitImport(form, hiddenInput, rows, statusEl) {
        var clean = normalizePhoneRows(rows);
        if (!clean.length) {
            setStatus(statusEl, 'Aucun contact avec numéro de téléphone trouvé.', true);
            return;
        }
        setStatus(statusEl, 'Enregistrement de ' + clean.length + ' contact(s)…', false);
        hiddenInput.value = JSON.stringify(clean);
        form.submit();
    }

    function initInvoiceContactsImport() {
        var btnOpen = document.getElementById('btn-import-contacts-invoice');
        var modal = document.getElementById('modal-import-contacts-invoice');
        var btnClose = document.getElementById('modal-import-contacts-invoice-close');
        var btnCancel = document.getElementById('modal-import-contacts-invoice-cancel');
        var btnPhone = document.getElementById('btn-import-phone-contacts');
        var btnImportAll = document.getElementById('btn-import-all-phone-contacts');
        var fileInput = document.getElementById('import-contacts-file-invoice');
        var form = document.getElementById('form-import-contacts-invoice');
        var hiddenInput = document.getElementById('import_contacts_data_invoice');
        var statusEl = document.getElementById('import-contacts-status');
        var phoneHint = document.getElementById('import-phone-hint');
        var allHint = document.getElementById('import-all-phone-hint');

        if (!btnOpen || !modal || !form || !hiddenInput) {
            return;
        }

        var pickerOk = supportsContactPicker();
        var nativeOk = supportsNativePickContacts();
        var nativeAllOk = supportsNativeGetAllContacts();

        if (btnImportAll) {
            if (nativeAllOk) {
                btnImportAll.hidden = false;
                if (allHint) {
                    allHint.textContent = 'Tous les contacts avec numéro, sans sélection un par un';
                }
            } else {
                btnImportAll.hidden = true;
            }
        }

        if (btnPhone) {
            if (!pickerOk) {
                btnPhone.disabled = true;
                btnPhone.classList.add('is-disabled');
                if (phoneHint) {
                    phoneHint.textContent = 'App Sugar Paper (iOS/Android) ou Chrome Android — sinon fichier .vcf';
                }
            } else if (nativeOk && phoneHint) {
                phoneHint.textContent = 'Liste app avec « Tout sélectionner » (iOS / Android)';
            } else if (phoneHint) {
                phoneHint.textContent = 'Sélecteur Android (pas de tout sélectionner) — Chrome uniquement';
            }
        }

        function openModal() {
            setStatus(statusEl, '', false);
            if (fileInput) {
                fileInput.value = '';
            }
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function handlePickerError(err) {
            var msg = (err && err.message) ? err.message : 'Accès aux contacts annulé ou refusé.';
            if (/annul/i.test(msg)) {
                setStatus(statusEl, 'Import annulé.', true);
            } else {
                setStatus(statusEl, msg, true);
            }
        }

        btnOpen.addEventListener('click', openModal);
        if (btnClose) {
            btnClose.addEventListener('click', closeModal);
        }
        if (btnCancel) {
            btnCancel.addEventListener('click', closeModal);
        }
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        if (btnPhone && pickerOk) {
            btnPhone.addEventListener('click', function () {
                setStatus(statusEl, 'Ouverture du carnet d’adresses…', false);
                var pickerPromise = nativeOk ? pickFromNativeApp() : pickFromContactPickerApi();
                pickerPromise
                    .then(function (rows) {
                        if (!rows || !rows.length) {
                            setStatus(statusEl, 'Aucun contact sélectionné.', true);
                            return;
                        }
                        submitImport(form, hiddenInput, rows, statusEl);
                    })
                    .catch(handlePickerError);
            });
        }

        if (btnImportAll && nativeAllOk) {
            btnImportAll.addEventListener('click', function () {
                var ok = window.confirm(
                    'Importer tous les contacts du téléphone qui ont un numéro ?\n\n' +
                    'Les doublons (même numéro déjà en carnet) seront ignorés.'
                );
                if (!ok) {
                    return;
                }
                setStatus(statusEl, 'Lecture de tous les contacts…', false);
                btnImportAll.disabled = true;
                importAllFromNativeApp()
                    .then(function (rows) {
                        if (!rows || !rows.length) {
                            setStatus(statusEl, 'Aucun contact avec numéro trouvé.', true);
                            return;
                        }
                        submitImport(form, hiddenInput, rows, statusEl);
                    })
                    .catch(handlePickerError)
                    .finally(function () {
                        btnImportAll.disabled = false;
                    });
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) {
                    return;
                }
                setStatus(statusEl, 'Lecture de « ' + file.name + ' »…', false);
                var reader = new FileReader();
                reader.onload = function () {
                    try {
                        var rows = parseFileContent(file.name, String(reader.result || ''));
                        submitImport(form, hiddenInput, rows, statusEl);
                    } catch (err) {
                        setStatus(statusEl, 'Impossible de lire ce fichier.', true);
                    }
                };
                reader.onerror = function () {
                    setStatus(statusEl, 'Erreur de lecture du fichier.', true);
                };
                reader.readAsText(file);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInvoiceContactsImport);
    } else {
        initInvoiceContactsImport();
    }
})();
