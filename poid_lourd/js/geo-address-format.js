/**
 * Format d'adresse : lieu + quartier + arrondissement + ville + pays (espaces, sans virgule).
 */
(function (global) {
    'use strict';

    var SEP = ' ';

    function extractQuartierCommune(segment) {
        segment = (segment || '').trim();
        var m = segment.match(/^commune\s+de\s+(.+)$/i);
        if (!m) return '';
        var inner = m[1].trim();
        var dash = inner.match(/^(.+?)\s*-\s*(.+)$/);
        if (dash) return dash[2].trim() || dash[1].trim();
        return inner;
    }

    function extractAdminLevel(segment, level) {
        segment = (segment || '').trim();
        if (!segment) return '';
        var re = new RegExp('^' + level + '\\s+de\\s+(.+)$', 'i');
        var m = segment.match(re);
        return m ? m[1].trim() : '';
    }

    function extractVilleRegion(segment) {
        return extractAdminLevel(segment, 'région') || extractAdminLevel(segment, 'region');
    }

    function isNoise(part) {
        part = (part || '').trim();
        return !part || /^\d+$/.test(part) || /^\d{4,6}$/.test(part);
    }

    function mergeParts(parts, max) {
        max = max || 5;
        var out = [];
        for (var i = 0; i < parts.length; i++) {
            var p = (parts[i] || '').trim();
            if (!p || out.indexOf(p) !== -1) continue;
            out.push(p);
            if (out.length >= max) break;
        }
        return out.join(SEP);
    }

    function pick(addr, keys) {
        for (var i = 0; i < keys.length; i++) {
            if (addr[keys[i]]) return String(addr[keys[i]]).trim();
        }
        return '';
    }

    function addSegment(segments, value) {
        value = (value || '').trim();
        if (!value || segments.indexOf(value) !== -1) return;
        segments.push(value);
    }

    function collectFromParts(addr) {
        if (!addr) return [];
        var segments = [];
        var house = pick(addr, ['house_number']);
        var road = pick(addr, ['road', 'pedestrian', 'footway', 'path', 'residential', 'cycleway']);
        if (road) addSegment(segments, house ? (house + ' ' + road) : road);
        else addSegment(segments, pick(addr, ['amenity', 'shop', 'building', 'tourism', 'leisure', 'place', 'landuse']));

        var quartier = pick(addr, ['suburb', 'quarter', 'neighbourhood', 'hamlet', 'city_district']);
        if (!quartier) quartier = extractQuartierCommune(pick(addr, ['municipality']));
        addSegment(segments, quartier);

        var county = pick(addr, ['county', 'state_district', 'district']);
        if (county) {
            var arr = extractAdminLevel(county, 'arrondissement');
            addSegment(segments, arr || (county.toLowerCase().indexOf('arrondissement') === -1 ? county : ''));
        }

        var ville = pick(addr, ['city', 'town', 'village']);
        if (!ville) {
            var regionRaw = pick(addr, ['state', 'region']);
            ville = extractVilleRegion(regionRaw) || regionRaw;
        }
        addSegment(segments, ville);
        addSegment(segments, pick(addr, ['country']));

        return segments;
    }

    function collectFromDisplayName(displayName) {
        if (!displayName) return [];
        var parts = displayName.split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        var lieu = '';
        var quartier = '';
        var arrondissement = '';
        var ville = '';
        var pays = '';

        for (var i = 0; i < parts.length; i++) {
            var p = parts[i];
            if (/^(senegal|sénégal)$/i.test(p)) { pays = p; continue; }
            if (isNoise(p)) continue;
            if (/^(département|departement)\s+de\b/i.test(p)) continue;

            var q = extractQuartierCommune(p);
            if (q) { quartier = q; continue; }
            var arr = extractAdminLevel(p, 'arrondissement');
            if (arr) { arrondissement = arr; continue; }
            var reg = extractVilleRegion(p);
            if (reg) { ville = reg; continue; }
            if (/^(commune|arrondissement|région|region)\s+de\b/i.test(p)) continue;
            if (!lieu) lieu = p;
        }

        return [lieu, quartier, arrondissement, ville, pays].filter(Boolean);
    }

    function fromParts(addr) {
        return mergeParts(collectFromParts(addr), 5);
    }

    function fromDisplayName(displayName) {
        return mergeParts(collectFromDisplayName(displayName), 5);
    }

    function fromNominatim(data) {
        if (!data) return '';
        if (data.address) {
            var built = collectFromParts(data.address);
            if (built.length) return mergeParts(built, 5);
        }
        return fromDisplayName(data.display_name || '');
    }

    global.GeoAddressFormat = {
        fromNominatim: fromNominatim,
        fromParts: fromParts,
        fromDisplayName: fromDisplayName,
        collectFromNominatim: function (data) {
            if (!data) return [];
            if (data.address) {
                var p = collectFromParts(data.address);
                if (p.length) return p;
            }
            return collectFromDisplayName(data.display_name || '');
        },
        mergeParts: mergeParts
    };
})(typeof window !== 'undefined' ? window : this);
