/**

 * Filtrage temps réel + période + pagination factures (30/page) — onglets Invoice admin.

 */

(function () {

    'use strict';



    var PAGE_SIZE = 30;



    function normalizeQuery(value) {

        return (value || '').trim().toLowerCase();

    }



    function todayYmd() {

        var t = new Date();

        var y = t.getFullYear();

        var m = String(t.getMonth() + 1).padStart(2, '0');

        var d = String(t.getDate()).padStart(2, '0');

        return y + '-' + m + '-' + d;

    }



    function addDaysYmd(ymd, days) {

        var parts = ymd.split('-');

        var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));

        dt.setDate(dt.getDate() + days);

        var y = dt.getFullYear();

        var m = String(dt.getMonth() + 1).padStart(2, '0');

        var d = String(dt.getDate()).padStart(2, '0');

        return y + '-' + m + '-' + d;

    }



    function firstDayOfMonthYmd(ymd) {

        var parts = ymd.split('-');

        return parts[0] + '-' + parts[1] + '-01';

    }



    function formatYmdFr(ymd) {

        if (!ymd) {

            return '';

        }

        var parts = ymd.split('-');

        if (parts.length !== 3) {

            return ymd;

        }

        return parts[2] + '/' + parts[1] + '/' + parts[0];

    }



    function presetRange(preset) {

        var today = todayYmd();

        if (preset === 'today') {

            return { from: today, to: today, preset: 'today' };

        }

        if (preset === 'week') {

            return { from: addDaysYmd(today, -6), to: today, preset: 'week' };

        }

        if (preset === 'month') {

            return { from: firstDayOfMonthYmd(today), to: today, preset: 'month' };

        }

        return { from: null, to: null, preset: 'all' };

    }



    function periodSummaryText(range) {

        if (!range.from && !range.to) {

            return 'Période : toutes les dates';

        }

        if (range.from === range.to) {

            return 'Période : aujourd\'hui (' + formatYmdFr(range.from) + ')';

        }

        return 'Période : du ' + formatYmdFr(range.from) + ' au ' + formatYmdFr(range.to);

    }



    function itemMatches(el, query) {

        if (!query) {

            return true;

        }

        var haystack = el.getAttribute('data-search') || el.textContent || '';

        return haystack.toLowerCase().indexOf(query) !== -1;

    }



    function itemMatchesDate(el, dateFrom, dateTo) {

        if (!dateFrom && !dateTo) {

            return true;

        }

        var d = el.getAttribute('data-date');

        if (!d) {

            return false;

        }

        if (dateFrom && d < dateFrom) {

            return false;

        }

        if (dateTo && d > dateTo) {

            return false;

        }

        return true;

    }



    function itemMatchesPayment(el, filter) {

        if (!filter) {

            return true;

        }

        return el.getAttribute('data-payee') === filter;

    }



    function initPeriodFilter(config, onChange) {

        var toggle = config.periodToggle ? document.querySelector(config.periodToggle) : null;

        var panel = config.periodPanel ? document.querySelector(config.periodPanel) : null;

        var dateFromInput = config.dateFromInput ? document.querySelector(config.dateFromInput) : null;

        var dateToInput = config.dateToInput ? document.querySelector(config.dateToInput) : null;

        var applyBtn = config.periodApply ? document.querySelector(config.periodApply) : null;

        var summaryEl = config.periodSummary ? document.querySelector(config.periodSummary) : null;

        var presetButtons = panel ? panel.querySelectorAll('.invoice-period-preset') : [];

        var initialPreset = config.defaultPeriodPreset || 'today';

        var range = presetRange(initialPreset);

        if (dateFromInput) {

            dateFromInput.value = range.from || '';

        }

        if (dateToInput) {

            dateToInput.value = range.to || '';

        }

        if (summaryEl) {

            summaryEl.textContent = periodSummaryText(range);

        }



        function setPresetActive(preset) {

            for (var i = 0; i < presetButtons.length; i++) {

                var btn = presetButtons[i];

                btn.classList.toggle('is-active', btn.getAttribute('data-preset') === preset);

            }

        }



        function applyRange(nextRange, closePanel) {

            range = nextRange;

            if (dateFromInput) {

                dateFromInput.value = range.from || '';

            }

            if (dateToInput) {

                dateToInput.value = range.to || '';

            }

            setPresetActive(range.preset || '');

            if (summaryEl) {

                summaryEl.textContent = periodSummaryText(range);

            }

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

                var preset = this.getAttribute('data-preset') || 'today';

                applyRange(presetRange(preset), true);

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

                    if (dateFromInput) {

                        dateFromInput.value = from;

                    }

                    if (dateToInput) {

                        dateToInput.value = to;

                    }

                }

                applyRange({ from: from || null, to: to || null, preset: '' }, true);

            });

        }



        return {

            getRange: function () {

                return range;

            }

        };

    }



    function formatFcfa(amount) {
        return Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    function updateFactureKpis(matching, config) {
        if (!config.kpiPayeEl && !config.kpiImpayeEl && !config.kpiLivraisonEl && !config.kpiToutEl) {
            return;
        }
        var paye = 0;
        var impaye = 0;
        var livraison = 0;
        for (var i = 0; i < matching.length; i++) {
            var el = matching[i];
            var montantHorsLivraison = parseInt(el.getAttribute('data-montant-hors-livraison') || el.getAttribute('data-montant') || '0', 10);
            var montantLivraison = parseInt(el.getAttribute('data-montant-livraison') || '0', 10);
            livraison += montantLivraison;
            if (el.getAttribute('data-payee') === '1') {
                paye += montantHorsLivraison;
            } else {
                impaye += montantHorsLivraison;
            }
        }
        var payeEl = config.kpiPayeEl ? document.querySelector(config.kpiPayeEl) : null;
        var impayeEl = config.kpiImpayeEl ? document.querySelector(config.kpiImpayeEl) : null;
        var livraisonEl = config.kpiLivraisonEl ? document.querySelector(config.kpiLivraisonEl) : null;
        var toutEl = config.kpiToutEl ? document.querySelector(config.kpiToutEl) : null;
        if (payeEl) {
            payeEl.textContent = formatFcfa(paye);
        }
        if (impayeEl) {
            impayeEl.textContent = formatFcfa(impaye);
        }
        if (livraisonEl) {
            livraisonEl.textContent = formatFcfa(livraison);
        }
        if (toutEl) {
            toutEl.textContent = formatFcfa(paye + impaye);
        }
    }

    function initInvoiceList(config) {

        var container = document.querySelector(config.container);

        var searchInput = document.querySelector(config.searchInput);

        if (!container || !searchInput) {

            return;

        }



        var loadMoreBtn = config.loadMoreBtn ? document.querySelector(config.loadMoreBtn) : null;
        var loadMoreWrap = config.loadMoreWrap ? document.querySelector(config.loadMoreWrap) : null;

        var noResultsEl = config.noResults ? document.querySelector(config.noResults) : null;

        var noResultsTextEl = config.noResultsText ? document.querySelector(config.noResultsText) : null;

        var tableWrap = config.tableWrap ? document.querySelector(config.tableWrap) : null;

        var countEl = config.countEl ? document.querySelector(config.countEl) : null;

        var countMatchingEl = config.countMatchingEl ? document.querySelector(config.countMatchingEl) : null;



        var items = Array.prototype.slice.call(container.querySelectorAll(config.itemSelector));

        if (!items.length) {

            return;

        }



        var visibleLimit = PAGE_SIZE;
        var currentPage = 1;
        var usePagination = !!config.usePagination;
        var paginationRoot = config.paginationRoot ? document.querySelector(config.paginationRoot) : null;
        var paginationPages = config.paginationPages ? document.querySelector(config.paginationPages) : null;
        var paginationPrev = config.paginationPrev ? document.querySelector(config.paginationPrev) : null;
        var paginationNext = config.paginationNext ? document.querySelector(config.paginationNext) : null;
        var paginationInfo = config.paginationInfo ? document.querySelector(config.paginationInfo) : null;

        var filterQuery = '';

        var paymentFilter = null;

        // Filtre période uniquement pour devis/factures (pas pour contacts, sans data-date)
        var hasPeriodFilter = !!config.periodToggle;

        var initialPreset = config.defaultPeriodPreset || 'today';

        var initialRange = hasPeriodFilter ? presetRange(initialPreset) : { from: null, to: null, preset: 'all' };

        var dateFrom = hasPeriodFilter ? initialRange.from : null;

        var dateTo = hasPeriodFilter ? initialRange.to : null;



        function updateDateGroups() {

            if (!config.container) {

                return;

            }

            var tbody = document.querySelector(config.container);

            if (!tbody) {

                return;

            }

            var groups = tbody.querySelectorAll('.invoice-date-group-row');

            for (var g = 0; g < groups.length; g++) {

                var groupRow = groups[g];

                var groupDate = groupRow.getAttribute('data-date-group');

                var hasVisible = false;

                for (var i = 0; i < items.length; i++) {

                    var el = items[i];

                    if (el.getAttribute('data-date') === groupDate && !el.hidden) {

                        hasVisible = true;

                        break;

                    }

                }

                groupRow.hidden = !hasVisible;

                groupRow.setAttribute('aria-hidden', hasVisible ? 'false' : 'true');

            }

        }



        function getBaseMatchingItems() {

            return items.filter(function (el) {

                return itemMatches(el, filterQuery) && itemMatchesDate(el, dateFrom, dateTo);

            });

        }



        function getMatchingItems() {

            return getBaseMatchingItems().filter(function (el) {

                return itemMatchesPayment(el, paymentFilter);

            });

        }



        function updateNoResultsMessage(matchingCount) {

            if (!noResultsTextEl) {

                return;

            }

            if (matchingCount > 0) {

                return;

            }

            if (filterQuery && (dateFrom || dateTo)) {

                noResultsTextEl.textContent = config.emptySearchPeriodText || 'Aucun résultat pour cette recherche et cette période.';

            } else if (filterQuery) {

                noResultsTextEl.textContent = config.emptySearchText || 'Aucun résultat ne correspond à votre recherche.';

            } else if (paymentFilter === '1') {

                noResultsTextEl.textContent = config.emptyPayeText || 'Aucune facture payée pour cette période.';

            } else if (paymentFilter === '0') {

                noResultsTextEl.textContent = config.emptyImpayeText || 'Aucune facture impayée pour cette période.';

            } else if (dateFrom || dateTo) {

                noResultsTextEl.textContent = config.emptyPeriodText || 'Aucun élément pour cette période.';

            } else {

                noResultsTextEl.textContent = config.emptySearchText || 'Aucun résultat ne correspond à votre recherche.';

            }

        }



        function getTotalPages(matchingCount) {
            return Math.max(1, Math.ceil(matchingCount / PAGE_SIZE));
        }

        function updateLoadMoreButton(matchingCount) {
            if (!loadMoreBtn) {
                return;
            }
            var remaining = matchingCount - visibleLimit;
            if (remaining > 0) {
                loadMoreBtn.hidden = false;
                if (loadMoreWrap) {
                    loadMoreWrap.hidden = false;
                }
                var nextBatch = Math.min(PAGE_SIZE, remaining);
                loadMoreBtn.textContent = 'Voir plus (' + nextBatch + ')';
            } else {
                loadMoreBtn.hidden = true;
                if (loadMoreWrap) {
                    loadMoreWrap.hidden = true;
                }
            }
        }

        function buildPageNumbers(totalPages, page) {
            var pages = [];
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    pages.push(i);
                }
                return pages;
            }
            pages.push(1);
            var start = Math.max(2, page - 1);
            var endNum = Math.min(totalPages - 1, page + 1);
            if (start > 2) {
                pages.push('…');
            }
            for (var j = start; j <= endNum; j++) {
                pages.push(j);
            }
            if (endNum < totalPages - 1) {
                pages.push('…');
            }
            pages.push(totalPages);
            return pages;
        }

        function updatePagination(matchingCount) {
            if (!usePagination || !paginationRoot) {
                return;
            }
            var totalPages = getTotalPages(matchingCount);
            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }
            /* Afficher la pagination seulement s'il y a plus de 30 factures filtrées */
            if (matchingCount === 0 || matchingCount <= PAGE_SIZE) {
                paginationRoot.hidden = true;
                return;
            }
            paginationRoot.hidden = false;

            if (paginationPrev) {
                paginationPrev.disabled = currentPage <= 1;
            }
            if (paginationNext) {
                paginationNext.disabled = currentPage >= totalPages;
            }

            if (paginationPages) {
                paginationPages.innerHTML = '';
                var nums = buildPageNumbers(totalPages, currentPage);
                for (var i = 0; i < nums.length; i++) {
                    var n = nums[i];
                    if (n === '…') {
                        var dots = document.createElement('span');
                        dots.className = 'invoice-list-pagination__ellipsis';
                        dots.textContent = '…';
                        dots.setAttribute('aria-hidden', 'true');
                        paginationPages.appendChild(dots);
                        continue;
                    }
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'invoice-list-pagination__page' + (n === currentPage ? ' is-active' : '');
                    btn.textContent = String(n);
                    btn.setAttribute('aria-label', 'Page ' + n);
                    if (n === currentPage) {
                        btn.setAttribute('aria-current', 'page');
                    }
                    btn.setAttribute('data-page', String(n));
                    paginationPages.appendChild(btn);
                }
            }

            if (paginationInfo) {
                var start = (currentPage - 1) * PAGE_SIZE + 1;
                var endIdx = Math.min(currentPage * PAGE_SIZE, matchingCount);
                paginationInfo.textContent = start + '–' + endIdx + ' sur ' + matchingCount + ' · Page ' + currentPage + ' / ' + totalPages;
            }
        }

        function goToPage(page) {
            var matchingCount = getMatchingItems().length;
            var totalPages = getTotalPages(matchingCount);
            currentPage = Math.min(Math.max(1, page), totalPages);
            apply();
            if (tableWrap && typeof tableWrap.scrollIntoView === 'function') {
                tableWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function apply() {

            var matching = getMatchingItems();

            var shown = 0;



            items.forEach(function (el) {

                el.hidden = true;

                el.classList.remove('invoice-list-item--visible');

            });



            var startIndex = 0;
            var endIndex = matching.length;
            if (usePagination) {
                var totalPagesApply = getTotalPages(matching.length);
                if (currentPage > totalPagesApply) {
                    currentPage = totalPagesApply;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }
                startIndex = (currentPage - 1) * PAGE_SIZE;
                endIndex = startIndex + PAGE_SIZE;
            } else {
                endIndex = visibleLimit;
            }

            matching.forEach(function (el, index) {
                if (index >= startIndex && index < endIndex) {
                    el.hidden = false;
                    el.classList.add('invoice-list-item--visible');
                    shown++;
                }
            });



            if (noResultsEl) {

                noResultsEl.hidden = matching.length > 0;

            }

            updateNoResultsMessage(matching.length);

            if (tableWrap) {

                tableWrap.hidden = matching.length === 0;

            }

            if (countEl) {

                countEl.textContent = String(shown);

            }

            if (countMatchingEl) {

                countMatchingEl.textContent = String(matching.length);

            }



            if (usePagination) {
                updatePagination(matching.length);
            } else {
                updateLoadMoreButton(matching.length);
            }

            updateFactureKpis(getBaseMatchingItems(), config);

            updateDateGroups();

        }



        searchInput.addEventListener('input', function () {

            filterQuery = normalizeQuery(searchInput.value);
            visibleLimit = PAGE_SIZE;
            currentPage = 1;
            apply();

        });



        if (loadMoreBtn && !usePagination) {
            loadMoreBtn.addEventListener('click', function () {
                visibleLimit += PAGE_SIZE;
                apply();
            });
        }

        if (usePagination) {
            if (paginationPrev) {
                paginationPrev.addEventListener('click', function () {
                    if (currentPage > 1) {
                        goToPage(currentPage - 1);
                    }
                });
            }
            if (paginationNext) {
                paginationNext.addEventListener('click', function () {
                    goToPage(currentPage + 1);
                });
            }
            if (paginationPages) {
                paginationPages.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-page]');
                    if (!btn) {
                        return;
                    }
                    var page = parseInt(btn.getAttribute('data-page') || '0', 10);
                    if (page > 0) {
                        goToPage(page);
                    }
                });
            }
        }



        if (hasPeriodFilter) {

            initPeriodFilter(config, function (range) {

                dateFrom = range.from;
                dateTo = range.to;
                visibleLimit = PAGE_SIZE;
                currentPage = 1;
                apply();

            });

        }



        if (config.enablePaymentKpiFilter) {

            var kpiToutBtn = config.kpiToutCard ? document.querySelector(config.kpiToutCard) : null;

            var kpiPayeBtn = config.kpiPayeCard ? document.querySelector(config.kpiPayeCard) : null;

            var kpiImpayeBtn = config.kpiImpayeCard ? document.querySelector(config.kpiImpayeCard) : null;



            function setPaymentKpiActive() {

                var isAll = paymentFilter === null;

                if (kpiToutBtn) {

                    kpiToutBtn.classList.toggle('is-active', isAll);

                    kpiToutBtn.setAttribute('aria-pressed', isAll ? 'true' : 'false');

                }

                if (kpiPayeBtn) {

                    kpiPayeBtn.classList.toggle('is-active', paymentFilter === '1');

                    kpiPayeBtn.setAttribute('aria-pressed', paymentFilter === '1' ? 'true' : 'false');

                }

                if (kpiImpayeBtn) {

                    kpiImpayeBtn.classList.toggle('is-active', paymentFilter === '0');

                    kpiImpayeBtn.setAttribute('aria-pressed', paymentFilter === '0' ? 'true' : 'false');

                }

            }



            function setPaymentFilter(nextFilter) {
                paymentFilter = nextFilter;
                visibleLimit = PAGE_SIZE;
                currentPage = 1;
                setPaymentKpiActive();
                apply();
            }



            if (kpiToutBtn) {

                kpiToutBtn.addEventListener('click', function () {

                    setPaymentFilter(null);

                });

            }

            if (kpiPayeBtn) {

                kpiPayeBtn.addEventListener('click', function () {

                    setPaymentFilter('1');

                });

            }

            if (kpiImpayeBtn) {

                kpiImpayeBtn.addEventListener('click', function () {

                    setPaymentFilter('0');

                });

            }

            setPaymentKpiActive();

        }



        apply();

    }



    function initInvoiceClickableRows() {
        function goToRow(row) {
            var href = row.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        }

        document.addEventListener('click', function (e) {
            var row = e.target.closest('.invoice-list-item--clickable');
            if (!row || row.hidden) {
                return;
            }
            goToRow(row);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var row = e.target.closest('.invoice-list-item--clickable');
            if (!row || row.hidden) {
                return;
            }
            e.preventDefault();
            goToRow(row);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initInvoiceClickableRows();

        initInvoiceList({

            container: '#devis-list-body',

            itemSelector: '.invoice-list-item',

            searchInput: '#search-devis',

            loadMoreBtn: '#devis-load-more',
            loadMoreWrap: '#devis-load-more-wrap',

            noResults: '#devis-no-results',

            noResultsText: '#devis-no-results-text',

            tableWrap: '#devis-table-wrap',

            periodToggle: '#devis-period-toggle',

            periodPanel: '#devis-period-panel',

            dateFromInput: '#devis-date-debut',

            dateToInput: '#devis-date-fin',

            periodApply: '#devis-period-apply',

            periodSummary: '#devis-period-summary',

            emptySearchText: 'Aucun devis ne correspond à votre recherche.',

            emptyPeriodText: 'Aucun devis pour cette période.',

            emptySearchPeriodText: 'Aucun devis ne correspond à votre recherche pour cette période.'

        });



        initInvoiceList({

            container: '#facture-list-body',

            itemSelector: '.invoice-list-item',

            searchInput: '#search-facture',

            usePagination: true,
            paginationRoot: '#facture-pagination',
            paginationPages: '#facture-pagination-pages',
            paginationPrev: '#facture-page-prev',
            paginationNext: '#facture-page-next',
            paginationInfo: '#facture-pagination-info',

            noResults: '#facture-no-results',

            noResultsText: '#facture-no-results-text',

            tableWrap: '#facture-table-wrap',

            periodToggle: '#facture-period-toggle',

            periodPanel: '#facture-period-panel',

            dateFromInput: '#facture-date-debut',

            dateToInput: '#facture-date-fin',

            periodApply: '#facture-period-apply',

            periodSummary: '#facture-period-summary',

            defaultPeriodPreset: 'all',

            emptySearchText: 'Aucune facture ne correspond à votre recherche.',

            emptyPeriodText: 'Aucune facture pour cette période.',

            emptySearchPeriodText: 'Aucune facture ne correspond à votre recherche pour cette période.',
            emptyPayeText: 'Aucune facture payée pour cette période.',
            emptyImpayeText: 'Aucune facture impayée pour cette période.',
            enablePaymentKpiFilter: true,
            kpiToutCard: '.invoice-facture-kpi--tout',
            kpiPayeCard: '.invoice-facture-kpi--paye',
            kpiImpayeCard: '.invoice-facture-kpi--impaye',
            kpiToutEl: '#facture-kpi-tout',
            kpiPayeEl: '#facture-kpi-paye',
            kpiImpayeEl: '#facture-kpi-impaye',
            kpiLivraisonEl: '#facture-kpi-livraison'

        });



        initInvoiceList({

            container: '#contacts-list-body',

            itemSelector: '.invoice-list-item',

            searchInput: '#search-contacts',

            loadMoreBtn: '#contacts-load-more',

            noResults: '#contacts-no-results',

            tableWrap: '#contacts-table-wrap',

            countEl: '#contacts-count-visible',

            countMatchingEl: '#contacts-count-matching'

        });

        initInvoiceList({
            container: '#commandes-a-traiter-body',
            itemSelector: '.invoice-list-item',
            searchInput: '#search-commandes-a-traiter',
            usePagination: true,
            paginationRoot: '#commandes-a-traiter-pagination',
            paginationPages: '#commandes-a-traiter-pagination-pages',
            paginationPrev: '#commandes-a-traiter-page-prev',
            paginationNext: '#commandes-a-traiter-page-next',
            paginationInfo: '#commandes-a-traiter-pagination-info',
            noResults: '#commandes-a-traiter-no-results',
            noResultsText: '#commandes-a-traiter-no-results-text',
            tableWrap: '#commandes-a-traiter-table-wrap',
            emptySearchText: 'Aucune commande ne correspond à votre recherche.'
        });

        initInvoiceList({
            container: '#commandes-livrees-body',
            itemSelector: '.invoice-list-item',
            searchInput: '#search-commandes-livrees',
            usePagination: true,
            paginationRoot: '#commandes-livrees-pagination',
            paginationPages: '#commandes-livrees-pagination-pages',
            paginationPrev: '#commandes-livrees-page-prev',
            paginationNext: '#commandes-livrees-page-next',
            paginationInfo: '#commandes-livrees-pagination-info',
            noResults: '#commandes-livrees-no-results',
            noResultsText: '#commandes-livrees-no-results-text',
            tableWrap: '#commandes-livrees-table-wrap',
            emptySearchText: 'Aucune commande livrée ne correspond à votre recherche.'
        });

        initInvoiceList({
            container: '#commandes-annulees-body',
            itemSelector: '.invoice-list-item',
            searchInput: '#search-commandes-annulees',
            usePagination: true,
            paginationRoot: '#commandes-annulees-pagination',
            paginationPages: '#commandes-annulees-pagination-pages',
            paginationPrev: '#commandes-annulees-page-prev',
            paginationNext: '#commandes-annulees-page-next',
            paginationInfo: '#commandes-annulees-pagination-info',
            noResults: '#commandes-annulees-no-results',
            noResultsText: '#commandes-annulees-no-results-text',
            tableWrap: '#commandes-annulees-table-wrap',
            emptySearchText: 'Aucune commande annulée ne correspond à votre recherche.'
        });

    });

})();

