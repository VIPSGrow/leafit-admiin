/**
 * LiveTrackingMap: shared map + polling logic for order live-tracking pages
 * (admin/partner/handyman live_tracking.php views). Built on MapProvider so it
 * automatically renders via Google Maps or Leaflet/OSM depending on configuration.
 */
(function (window) {
    'use strict';

    function init(config) {
        var map = MapProvider.createMap(config.containerId, {
            lat: (config.orderPos.lat + config.handymanPos.lat) / 2,
            lng: (config.orderPos.lng + config.handymanPos.lng) / 2,
            zoom: 14,
            fullscreenControl: true
        });
        if (!map) return;

        MapProvider.addMarker(map, {
            lat: config.orderPos.lat,
            lng: config.orderPos.lng,
            title: config.orderTitle || 'Service Location',
            iconUrl: config.customerIconUrl,
            iconAnchor: [16, 32]
        });

        var handymanMarker = MapProvider.addMarker(map, {
            lat: config.handymanPos.lat,
            lng: config.handymanPos.lng,
            title: config.handymanTitle || '',
            iconUrl: config.handymanIconUrl,
            iconAnchor: [16, 16]
        });

        var polyline = MapProvider.addPolyline(map, [config.handymanPos, config.orderPos], { color: '#3498db' });

        MapProvider.fitBounds(map, [config.orderPos, config.handymanPos]);

        var pollTimer = setInterval(function () {
            $.getJSON(config.pollUrl, function (res) {
                var stopped = res.order_status && res.order_status !== 'on_the_way';
                if (stopped) {
                    clearInterval(pollTimer);
                    var mapEl = document.getElementById(config.containerId);
                    if (mapEl && config.endedMessage) {
                        mapEl.insertAdjacentHTML('beforebegin',
                            '<div class="alert alert-info mx-3 mt-3">' +
                            '<i class="fas fa-info-circle mr-2"></i>' +
                            config.endedMessage +
                            '</div>'
                        );
                    }
                    return;
                }
                if (res.error || !res.latitude || !res.longitude) return;
                var newPos = { lat: parseFloat(res.latitude), lng: parseFloat(res.longitude) };
                MapProvider.setMarkerPosition(handymanMarker, newPos);
                MapProvider.setPolylinePath(polyline, [newPos, config.orderPos]);
            });
        }, 30000);
    }

    window.LiveTrackingMap = { init: init };
})(window);
