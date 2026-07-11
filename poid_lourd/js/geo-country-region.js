/**
 * Met à jour la liste des régions selon le pays sélectionné (UI uniquement).
 */
(function () {
    function initGeoCountryRegion(countryId, regionId, dataElId) {
        var dataEl = document.getElementById(dataElId || 'geoRegionsData');
        var countrySel = typeof countryId === 'string' ? document.getElementById(countryId) : countryId;
        var regionSel = typeof regionId === 'string' ? document.getElementById(regionId) : regionId;
        if (!dataEl || !countrySel || !regionSel) {
            return;
        }
        var regions = {};
        try {
            regions = JSON.parse(dataEl.textContent || '{}');
        } catch (e) {
            return;
        }
        var preserved = regionSel.getAttribute('data-selected') || regionSel.value || '';

        function refresh(clearSelection) {
            var country = (countrySel.value || '').toUpperCase();
            var selected = clearSelection ? '' : preserved;
            regionSel.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = regionSel.getAttribute('data-empty-label') || 'Sélectionnez une région';
            if (selected === '') {
                empty.selected = true;
            }
            regionSel.appendChild(empty);
            var list = regions[country] || [];
            list.forEach(function (item) {
                var opt = document.createElement('option');
                opt.value = item.code;
                opt.textContent = item.label;
                if (item.code === selected) {
                    opt.selected = true;
                }
                regionSel.appendChild(opt);
            });
        }

        countrySel.addEventListener('change', function () {
            preserved = '';
            refresh(true);
        });
        refresh(false);
    }

    window.initGeoCountryRegion = initGeoCountryRegion;

    document.addEventListener('DOMContentLoaded', function () {
        initGeoCountryRegion('boutique_country', 'boutique_region');
    });
})();
