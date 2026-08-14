/**
 * Onglets Commandes / Factures + recherche + filtre période — page livreurs admin.
 */
(function () {
    'use strict';

    function normalizeQuery(value) {
        return (value || '').trim().toLowerCase();
    }

    function todayYmd() {
        var t = new Date();
        return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
    }

    function addDaysYmd(ymd, days) {
        var parts = ymd.split('-');
        var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        dt.setDate(dt.getDate() + days);
        return dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    }

    function firstDayOfMonthYmd(ymd) {
        var parts = ymd.split('-');
        return parts[0] + '-' + parts[1] + '-01';
    }

    function formatYmdFr(ymd) {
        if (!ymd) return '';
        var parts = ymd.split('-');
        if (parts.length !== 3) return ymd;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function presetRange(preset) {
        var today = todayYmd();
        if (preset === 'today') return { from: today, to: today, preset: 'today' };
        if (preset === 'week') return { from: addDaysYmd(today, -6), to: today, preset: 'week' };
        if (preset === 'month') return { from: firstDayOfMonthYmd(today), to: today, preset: 'month' };
        return { from: null, to: null, preset: 'all' };
    }

    function periodSummaryText(range) {
        if (!range.from && !range.to) return 'Période : toutes les dates';
        if (range.from === range.to) return 'Période : aujourd\'hui (' + formatYmdFr(range.from) + ')';
        return 'Période : du ' + formatYmdFr(range.from) + ' au ' + formatYmdFr(range.to);
    }

    function itemMatchesQuery(el, query) {
        if (!query) return true;
        var haystack = el.getAttribute('data-search') || '';
        return haystack.indexOf(query) !== -1;
    }

    function itemMatchesDate(el, dateFrom, dateTo) {
        if (!dateFrom && !dateTo) return true;
        var d = el.getAttribute('data-date');
        if (!d) return false;
        if (dateFrom && d < dateFrom) return false;
        if (dateTo && d > dateTo) return false;
        return true;
    }

    function initPeriodFilter(onChange) {
        var toggle = document.getElementById('livreur-period-toggle');
        var panel = document.getElementById('livreur-period-panel');
        var dateFromInput = document.getElementById('livreur-date-debut');
        var dateToInput = document.getElementById('livreur-date-fin');
        var applyBtn = document.getElementById('livreur-period-apply');
        var summaryEl = document.getElementById('livreur-period-summary');
        var presetButtons = panel ? panel.querySelectorAll('.invoice-period-preset') : [];
        var range = presetRange('today');

        if (dateFromInput) dateFromInput.value = range.from || '';
        if (dateToInput) dateToInput.value = range.to || '';
        if (summaryEl) summaryEl.textContent = periodSummaryText(range);

        function setPresetActive(preset) {
            for (var i = 0; i < presetButtons.length; i++) {
                var btn = presetButtons[i];
                btn.classList.toggle('is-active', btn.getAttribute('data-preset') === preset);
            }
        }

        function applyRange(nextRange, closePanel) {
            range = nextRange;
            if (dateFromInput) dateFromInput.value = range.from || '';
            if (dateToInput) dateToInput.value = range.to || '';
            setPresetActive(range.preset || '');
            if (summaryEl) summaryEl.textContent = periodSummaryText(range);
            if (closePanel && panel && toggle) {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
            onChange(range);
        }

        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                var open = panel.hidden;
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        for (var p = 0; p < presetButtons.length; p++) {
            presetButtons[p].addEventListener('click', function () {
                applyRange(presetRange(this.getAttribute('data-preset') || 'today'), true);
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var from = dateFromInput ? dateFromInput.value : '';
                var to = dateToInput ? dateToInput.value : '';
                if (from && to && from > to) {
                    var tmp = from;
                    from = to;
                    to = tmp;
                    if (dateFromInput) dateFromInput.value = from;
                    if (dateToInput) dateToInput.value = to;
                }
                applyRange({ from: from || null, to: to || null, preset: '' }, true);
            });
        }

        return {
            getRange: function () { return range; }
        };
    }

    function initTabs(onTabChange) {
        var tabButtons = document.querySelectorAll('.livreur-delivery-tab');
        var panels = {
            commandes: document.getElementById('livreur-panel-commandes'),
            facture: document.getElementById('livreur-panel-facture'),
            personnalisees: document.getElementById('livreur-panel-personnalisees')
        };
        var cfg = window.LIVREUR_INDEX_UI || {};
        var activeTab = cfg.activeTab === 'commandes' || cfg.activeTab === 'personnalisees'
            ? cfg.activeTab
            : 'facture';

        function switchTab(tabName) {
            if (!panels[tabName]) return;
            activeTab = tabName;

            for (var i = 0; i < tabButtons.length; i++) {
                var btn = tabButtons[i];
                var isActive = btn.getAttribute('data-livreur-tab') === tabName;
                btn.classList.toggle('is-active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            }

            Object.keys(panels).forEach(function (key) {
                var panel = panels[key];
                if (!panel) return;
                var show = key === tabName;
                panel.hidden = !show;
                panel.classList.toggle('is-active', show);
            });

            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                if (tabName === 'facture') {
                    url.searchParams.delete('tab');
                } else {
                    url.searchParams.set('tab', tabName);
                }
                window.history.replaceState({}, '', url.toString());
            }

            if (typeof onTabChange === 'function') {
                onTabChange(activeTab);
            }
        }

        for (var t = 0; t < tabButtons.length; t++) {
            tabButtons[t].addEventListener('click', function () {
                if (this.disabled) return;
                switchTab(this.getAttribute('data-livreur-tab') || 'commandes');
            });
        }

        return {
            getActiveTab: function () { return activeTab; },
            switchTab: switchTab
        };
    }

    function init() {
        var cfg = window.LIVREUR_INDEX_UI || {};
        var searchInput = document.getElementById('livreur-search-input');
        var summaryEl = document.getElementById('livreur-search-summary');
        var panelCommandes = document.getElementById('livreur-panel-commandes');
        var panelFacture = document.getElementById('livreur-panel-facture');
        var panelPersonnalisees = document.getElementById('livreur-panel-personnalisees');
        if (!panelCommandes && !panelFacture && !panelPersonnalisees) return;

        var tabApi = initTabs(function () {
            updateSearchPlaceholder();
            applyFilters();
        });

        var panelsConfig = {
            commandes: {
                tbody: document.getElementById('livreur-cmd-list-body'),
                noResults: document.getElementById('livreur-no-results-commandes'),
                labelSingular: 'commande',
                labelPlural: 'commandes'
            },
            facture: {
                tbody: document.getElementById('livreur-facture-list-body'),
                noResults: document.getElementById('livreur-no-results-factures'),
                labelSingular: 'facture',
                labelPlural: 'factures'
            },
            personnalisees: {
                tbody: document.getElementById('livreur-cp-list-body'),
                noResults: document.getElementById('livreur-no-results-personnalisees'),
                labelSingular: 'commande personnalisée',
                labelPlural: 'commandes personnalisées'
            }
        };

        function getRowsForTab(tabName) {
            var conf = panelsConfig[tabName];
            if (!conf || !conf.tbody) return [];
            return conf.tbody.querySelectorAll('tr[data-date]');
        }

        function countRowsForPeriod(tabName) {
            var rows = getRowsForTab(tabName);
            var count = 0;
            for (var i = 0; i < rows.length; i++) {
                if (itemMatchesDate(rows[i], currentRange.from, currentRange.to)) {
                    count++;
                }
            }
            return count;
        }

        function updateTabCounts() {
            var factureCountEl = document.getElementById('livreur-tab-count-facture');
            var commandesCountEl = document.getElementById('livreur-tab-count-commandes');
            var persoCountEl = document.getElementById('livreur-tab-count-personnalisees');
            if (factureCountEl) {
                factureCountEl.textContent = String(countRowsForPeriod('facture'));
            }
            if (commandesCountEl) {
                commandesCountEl.textContent = String(countRowsForPeriod('commandes'));
            }
            if (persoCountEl) {
                persoCountEl.textContent = String(countRowsForPeriod('personnalisees'));
            }
        }

        var periodApi = null;
        var currentRange = { from: null, to: null };

        if (cfg.enablePeriod) {
            currentRange = presetRange('today');
            periodApi = initPeriodFilter(function (range) {
                currentRange = range;
                applyFilters();
            });
            if (periodApi) currentRange = periodApi.getRange();
        } else {
            var today = todayYmd();
            currentRange = { from: today, to: today };
        }

        function updateSearchPlaceholder() {
            if (!searchInput) return;
            var tab = tabApi.getActiveTab();
            if (tab === 'facture') {
                searchInput.placeholder = 'Nom client, téléphone…';
            } else if (tab === 'personnalisees') {
                searchInput.placeholder = 'Nom client, téléphone, n° CP…';
            } else {
                searchInput.placeholder = 'Nom client, téléphone, n° commande…';
            }
        }

        function applyFilters() {
            var tab = tabApi.getActiveTab();
            var conf = panelsConfig[tab];
            var rows = getRowsForTab(tab);
            var query = normalizeQuery(searchInput ? searchInput.value : '');
            var visible = 0;
            var total = rows.length;

            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var show = itemMatchesQuery(row, query) && itemMatchesDate(row, currentRange.from, currentRange.to);
                row.hidden = !show;
                if (show) visible++;
            }

            if (conf && conf.noResults) {
                conf.noResults.hidden = visible > 0 || total === 0;
            }

            if (summaryEl) {
                var label = visible > 1 ? conf.labelPlural : conf.labelSingular;
                if (query) {
                    summaryEl.textContent = visible + ' ' + label + ' sur ' + total + ' pour « ' + (searchInput ? searchInput.value.trim() : '') + ' »';
                } else {
                    summaryEl.textContent = visible + ' ' + label + ' affichée' + (visible > 1 ? 's' : '');
                }
            }

            updateTabCounts();
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
            searchInput.addEventListener('search', applyFilters);
        }

        updateSearchPlaceholder();
        applyFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
