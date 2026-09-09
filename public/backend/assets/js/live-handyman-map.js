/**
 * LiveHandymanMap: provider-wide map of all handymen currently on the way to a
 * service. No polyline (unlike LiveTrackingMap) — just current positions, one
 * marker per qualifying order, diffed against each poll response. Built on
 * MapProvider so it renders via Google Maps or Leaflet/OSM per configuration.
 */
(function (window) {
    'use strict';

    function init(config) {
        var map = null;
        var markers = {}; // order_id -> markerHandle

        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : String(str)).html();
        }

        function buildPopupHtml(row) {
            var lastUpdated = row.updated_at ? new Date(row.updated_at.replace(' ', 'T') + 'Z').toLocaleString() : '';
            return '<div style="min-width:200px;">' +
                '<div style="font-weight:600;margin-bottom:4px;">' + escapeHtml(row.handyman_name) + ' (' + escapeHtml(config.popupLabels.handyman) + ')</div>' +
                '<div style="font-size:12px;color:#666;">' + escapeHtml(config.popupLabels.orderId) + ': #' + escapeHtml(row.order_id) + '</div>' +
                '<div style="font-size:12px;color:#666;margin-bottom:8px;">' + escapeHtml(config.popupLabels.lastUpdated) + ': ' + escapeHtml(lastUpdated) + '</div>' +
                '<a href="' + config.viewDetailsBaseUrl + row.handyman_id + '" class="btn btn-primary btn-sm text-white">' + escapeHtml(config.popupLabels.viewDetails) + '</a>' +
                '</div>';
        }

        function ensureMap() {
            if (map) return map;
            map = MapProvider.createMap(config.containerId, { lat: 0, lng: 0, zoom: 2, fullscreenControl: true });
            return map;
        }

        function showEmpty() {
            $('#' + config.messageId).show();
            $('#' + config.containerId).hide();
        }

        function showMap() {
            $('#' + config.messageId).hide();
            $('#' + config.containerId).show();
        }

        function refresh() {
            $.getJSON(config.dataUrl, function (res) {
                if (!res || res.error || !res.data || !res.data.length) {
                    Object.keys(markers).forEach(function (orderId) {
                        MapProvider.setMarkerPosition(markers[orderId], { lat: -9999, lng: -9999 });
                    });
                    markers = {};
                    showEmpty();
                    return;
                }

                showMap();
                ensureMap();

                var seenOrderIds = {};
                var positions = [];
                var addedAny = false;

                res.data.forEach(function (row) {
                    var orderId = String(row.order_id);
                    seenOrderIds[orderId] = true;
                    var pos = { lat: row.latitude, lng: row.longitude };
                    positions.push(pos);

                    if (markers[orderId]) {
                        MapProvider.setMarkerPosition(markers[orderId], pos);
                        MapProvider.bindPopup(markers[orderId], buildPopupHtml(row));
                    } else {
                        var marker = MapProvider.addMarker(map, {
                            lat: pos.lat,
                            lng: pos.lng,
                            title: row.handyman_name,
                            iconUrl: config.handymanIconUrl,
                            iconAnchor: [16, 16]
                        });
                        MapProvider.bindPopup(marker, buildPopupHtml(row));
                        markers[orderId] = marker;
                        addedAny = true;
                    }
                });

                var removedAny = false;
                Object.keys(markers).forEach(function (orderId) {
                    if (!seenOrderIds[orderId]) {
                        MapProvider.setMarkerPosition(markers[orderId], { lat: -9999, lng: -9999 });
                        delete markers[orderId];
                        removedAny = true;
                    }
                });

                if (addedAny || removedAny) {
                    MapProvider.fitBounds(map, positions);
                }
            }).fail(function () {
                showEmpty();
            });
        }

        refresh();
        setInterval(refresh, 30000);
    }

    window.LiveHandymanMap = { init: init };
})(window);
