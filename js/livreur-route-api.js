/**
 * API itinéraire livreur — sans autoroutes à péage (via PHP / Valhalla).
 */
(function () {
    'use strict';

    function fetchRoute(fromLat, fromLng, toLat, toLng, options) {
        options = options || {};
        var params = new URLSearchParams({
            from_lat: String(fromLat),
            from_lng: String(fromLng),
            to_lat: String(toLat),
            to_lng: String(toLng),
        });

        if (options.token) {
            params.set('token', String(options.token));
        }
        if (options.cpId) {
            params.set('cp_id', String(options.cpId));
        } else if (options.blId) {
            params.set('bl_id', String(options.blId));
        } else if (options.commandeId) {
            params.set('commande_id', String(options.commandeId));
        }

        return fetch('/api/routing/directions.php?' + params.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data || !data.ok) {
                    throw new Error((data && data.error) ? data.error : 'route_failed');
                }
                return data;
            });
        });
    }

    window.LivreurRouteApi = {
        fetchRoute: fetchRoute,
    };
})();
