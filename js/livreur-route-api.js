/**
 * API itinéraire livreur — sans autoroutes à péage (via PHP / Valhalla).
 */
(function () {
    'use strict';

    function fetchRoute(fromLat, fromLng, toLat, toLng) {
        var params = new URLSearchParams({
            from_lat: String(fromLat),
            from_lng: String(fromLng),
            to_lat: String(toLat),
            to_lng: String(toLng),
        });

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
