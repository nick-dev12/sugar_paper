/**
 * Recherche client unifiée : carnet BDD + contacts téléphone (app native).
 * Remplit des champs cachés nom/téléphone via sélection dans les suggestions.
 */
(function (window) {
    'use strict';

    var deviceContactsCache = null;
    var deviceContactsPromise = null;

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function digitsOnly(s) {
        return String(s || '').replace(/\D/g, '');
    }

    function isNativeApp() {
        return !!(
            window.__SUGARPAPER_NATIVE_APP ||
            /SugarPaperApp/i.test(navigator.userAgent || '') ||
            window.flutter_inappwebview
        );
    }

    function supportsDeviceContacts() {
        return isNativeApp() && !!(
            window.flutter_inappwebview ||
            (window.SugarPaperNative && typeof window.SugarPaperNative.getDeviceContacts === 'function')
        );
    }

    function callGetDeviceContacts() {
        if (window.SugarPaperNative && typeof window.SugarPaperNative.getDeviceContacts === 'function') {
            return window.SugarPaperNative.getDeviceContacts();
        }
        if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
            return window.flutter_inappwebview.callHandler('getDeviceContacts');
        }
        return Promise.reject(new Error('Pont natif indisponible'));
    }

    function loadDeviceContacts() {
        if (deviceContactsCache) {
            return Promise.resolve(deviceContactsCache);
        }
        if (!supportsDeviceContacts()) {
            return Promise.resolve([]);
        }
        if (!deviceContactsPromise) {
            deviceContactsPromise = callGetDeviceContacts()
                .then(function (result) {
                    var list = (result && result.contacts) ? result.contacts : [];
                    deviceContactsCache = list.map(function (c) {
                        return {
                            id: null,
                            source: 'device',
                            nom: c.nom || '',
                            prenom: c.prenom || '',
                            nom_complet: c.nom_complet ||
                                [c.prenom, c.nom].filter(Boolean).join(' ') ||
                                'Sans nom',
                            telephone: c.telephone || '',
                            email: c.email || ''
                        };
                    }).filter(function (c) {
                        return digitsOnly(c.telephone).length >= 6;
                    });
                    return deviceContactsCache;
                })
                .catch(function () {
                    deviceContactsPromise = null;
                    return [];
                });
        }
        return deviceContactsPromise;
    }

    function filterDeviceContacts(list, q) {
        var needle = String(q || '').trim().toLowerCase();
        var needleDigits = digitsOnly(needle);
        if (!needle) {
            return [];
        }
        return list.filter(function (c) {
            var hay = (
                (c.nom_complet || '') + ' ' +
                (c.nom || '') + ' ' +
                (c.prenom || '') + ' ' +
                (c.telephone || '')
            ).toLowerCase();
            if (hay.indexOf(needle) !== -1) {
                return true;
            }
            if (needleDigits.length >= 3) {
                return digitsOnly(c.telephone).indexOf(needleDigits) !== -1;
            }
            return false;
        }).slice(0, 12);
    }

    function parseFreeClient(q) {
        var raw = String(q || '').trim();
        if (!raw) {
            return null;
        }
        var phoneMatch = raw.match(/(?:\+?\d[\d\s.\-]{6,}\d)/);
        if (!phoneMatch) {
            return null;
        }
        var phone = phoneMatch[0].trim();
        if (digitsOnly(phone).length < 8) {
            return null;
        }
        var nom = raw.replace(phoneMatch[0], ' ').replace(/\s+/g, ' ').trim()
            .replace(/^[\s,;:.\-–—]+|[\s,;:.\-–—]+$/g, '');
        if (!nom || nom.length < 2) {
            return null;
        }
        return {
            id: null,
            source: 'new',
            nom: nom,
            prenom: '',
            nom_complet: nom,
            telephone: phone
        };
    }

    function mergeResults(dbList, deviceList, freeClient) {
        var out = [];
        var seenPhones = {};

        function add(c, badge) {
            var key = digitsOnly(c.telephone);
            if (key && seenPhones[key]) {
                return;
            }
            if (key) {
                seenPhones[key] = true;
            }
            var row = Object.assign({}, c);
            row._badge = badge;
            out.push(row);
        }

        (dbList || []).forEach(function (c) {
            add(c, c.source === 'contact' ? 'Carnet' : 'Client');
        });
        (deviceList || []).forEach(function (c) {
            add(c, 'Tél.');
        });
        if (freeClient) {
            add(freeClient, 'Nouveau');
        }
        return out.slice(0, 20);
    }

    function init(opts) {
        var searchInput = opts.searchInput;
        var resultsEl = opts.resultsEl;
        var loadingEl = opts.loadingEl;
        var nomInput = opts.nomInput;
        var telInput = opts.telInput;
        var userIdInput = opts.userIdInput || null;
        var selectedWrap = opts.selectedWrap || null;
        var selectedNomEl = opts.selectedNomEl || null;
        var selectedTelEl = opts.selectedTelEl || null;
        var clearBtn = opts.clearBtn || null;
        var ajaxUrl = opts.ajaxUrl || '../devis/ajax_search_clients.php';
        var timeoutId;

        if (!searchInput || !resultsEl || !nomInput || !telInput) {
            return;
        }

        function updateSelectedUi() {
            var nom = (nomInput.value || '').trim();
            var tel = (telInput.value || '').trim();
            var has = !!(nom && tel);
            if (selectedWrap) {
                selectedWrap.style.display = has ? '' : 'none';
                selectedWrap.setAttribute('aria-hidden', has ? 'false' : 'true');
            }
            if (selectedNomEl) {
                selectedNomEl.textContent = nom;
            }
            if (selectedTelEl) {
                selectedTelEl.textContent = tel;
            }
            if (has) {
                searchInput.removeAttribute('required');
                searchInput.setAttribute('aria-invalid', 'false');
            }
        }

        function selectClient(c) {
            var nom = c.nom_complet ||
                [c.prenom, c.nom].filter(Boolean).join(' ') ||
                c.nom ||
                '';
            nomInput.value = nom;
            telInput.value = c.telephone || '';
            if (userIdInput) {
                userIdInput.value = (c.source === 'user' && c.id) ? c.id : '';
            }
            searchInput.value = '';
            resultsEl.innerHTML = '';
            resultsEl.setAttribute('aria-hidden', 'true');
            updateSelectedUi();
        }

        function clearClient() {
            nomInput.value = '';
            telInput.value = '';
            if (userIdInput) {
                userIdInput.value = '';
            }
            searchInput.value = '';
            resultsEl.innerHTML = '';
            resultsEl.setAttribute('aria-hidden', 'true');
            updateSelectedUi();
            searchInput.focus();
        }

        function renderResults(list) {
            resultsEl.innerHTML = '';
            if (!list.length) {
                resultsEl.innerHTML = '<div class="search-no-results">Aucun client trouvé.</div>';
                resultsEl.setAttribute('aria-hidden', 'false');
                return;
            }
            list.forEach(function (c) {
                var el = document.createElement('div');
                el.className = 'search-result-item';
                el.setAttribute('role', 'option');
                var badge = c._badge
                    ? '<span class="sr-badge sr-badge-' +
                        escHtml(String(c._badge).toLowerCase().replace(/[^a-z]/g, '')) +
                        '">' + escHtml(c._badge) + '</span>'
                    : '';
                el.innerHTML =
                    '<span class="sr-nom">' + escHtml(c.nom_complet || '') + badge + '</span>' +
                    '<span class="sr-meta">' + escHtml(c.telephone || '') + '</span>';
                el.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    selectClient(c);
                });
                resultsEl.appendChild(el);
            });
            resultsEl.setAttribute('aria-hidden', 'false');
        }

        function doSearch(q) {
            q = String(q || '').trim();
            if (q.length < 1) {
                resultsEl.innerHTML = '';
                resultsEl.setAttribute('aria-hidden', 'true');
                return;
            }
            if (loadingEl) {
                loadingEl.style.visibility = 'visible';
            }

            var dbPromise = fetch(ajaxUrl + '?q=' + encodeURIComponent(q) + '&limit=15')
                .then(function (r) { return r.json(); })
                .catch(function () { return []; });

            var devicePromise = loadDeviceContacts().then(function (all) {
                return filterDeviceContacts(all, q);
            });

            Promise.all([dbPromise, devicePromise]).then(function (parts) {
                var merged = mergeResults(parts[0] || [], parts[1] || [], parseFreeClient(q));
                renderResults(merged);
            }).finally(function () {
                if (loadingEl) {
                    loadingEl.style.visibility = 'hidden';
                }
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(timeoutId);
            var q = searchInput.value.trim();
            timeoutId = setTimeout(function () { doSearch(q); }, 280);
        });

        searchInput.addEventListener('focus', function () {
            loadDeviceContacts();
            var q = searchInput.value.trim();
            if (q.length >= 1) {
                doSearch(q);
            }
        });

        searchInput.addEventListener('blur', function () {
            setTimeout(function () {
                if (!resultsEl.contains(document.activeElement)) {
                    resultsEl.innerHTML = '';
                    resultsEl.setAttribute('aria-hidden', 'true');
                }
            }, 150);
        });

        resultsEl.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                clearClient();
            });
        }

        var form = nomInput.form;
        if (form) {
            form.addEventListener('submit', function (ev) {
                updateSelectedUi();
                if (!(nomInput.value || '').trim() || !(telInput.value || '').trim()) {
                    ev.preventDefault();
                    searchInput.focus();
                    searchInput.setAttribute('aria-invalid', 'true');
                    if (!resultsEl.querySelector('.search-client-required-msg')) {
                        var msg = document.createElement('div');
                        msg.className = 'search-no-results search-client-required-msg';
                        msg.textContent = 'Sélectionnez un client dans les suggestions (ou saisissez « Nom + téléphone »).';
                        resultsEl.innerHTML = '';
                        resultsEl.appendChild(msg);
                        resultsEl.setAttribute('aria-hidden', 'false');
                    }
                }
            });
        }

        updateSelectedUi();
    }

    window.AdminClientSearchSync = {
        init: init,
        preloadDeviceContacts: loadDeviceContacts,
        supportsDeviceContacts: supportsDeviceContacts
    };
})(window);
