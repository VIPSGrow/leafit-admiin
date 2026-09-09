/**
 * MapProvider: unified map abstraction over Google Maps JS API and Leaflet (OpenStreetMap).
 * Active backend is chosen by window.MAP_PROVIDER ('google' | 'openstreetmap'), injected server-side
 * from the configured map_provider setting. All callers (scripts.js, live-tracking-map.js) must go
 * through this object instead of calling google.maps.* / L.* directly, so provider stays swappable.
 */
(function (window) {
    'use strict';

    var PROVIDER = window.MAP_PROVIDER === 'openstreetmap' ? 'openstreetmap' : 'google';
    var mpBaseUrl = (window.baseUrl || '/').replace(/\/$/, '') + '/';

    function isGoogle() { return PROVIDER === 'google'; }

    // Build a self-contained pin icon (pure CSS/SVG, no external image files) so it never
    // depends on Leaflet's default marker image paths resolving correctly (those 404 when
    // leaflet.js is loaded from a CDN with no bundler to rewrite the path, and the marker
    // then renders invisible instead of erroring).
    function buildLeafletIcon(color, iconUrl, iconAnchor) {
        if (iconUrl) {
            return L.icon({
                iconUrl: iconUrl,
                iconSize: [32, 32],
                iconAnchor: iconAnchor || [16, 32]
            });
        }
        if (color) {
            return L.divIcon({
                className: '',
                html: '<div style="width:18px;height:18px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 0 2px rgba(0,0,0,.5);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });
        }
        return L.divIcon({
            className: '',
            html: '<svg width="26" height="38" viewBox="0 0 26 38" xmlns="http://www.w3.org/2000/svg">' +
                '<path d="M13 0C5.8 0 0 5.8 0 13c0 9.75 13 25 13 25s13-15.25 13-25C26 5.8 20.2 0 13 0z" fill="#e74c3c" stroke="#fff" stroke-width="1.5"/>' +
                '<circle cx="13" cy="13" r="5" fill="#fff"/>' +
                '</svg>',
            iconSize: [26, 38],
            iconAnchor: [13, 38],
            popupAnchor: [0, -38]
        });
    }

    // ---- map ----
    function createMap(elId, opts) {
        var el = document.getElementById(elId);
        if (!el) return null;
        opts = opts || {};
        if (isGoogle()) {
            return {
                type: 'google',
                map: new google.maps.Map(el, {
                    center: { lat: opts.lat, lng: opts.lng },
                    zoom: opts.zoom || 14,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: !!opts.fullscreenControl
                })
            };
        }
        var leafletMap = L.map(el, { zoomControl: true }).setView([opts.lat, opts.lng], opts.zoom || 14);
        // L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        //     maxZoom: 19,
        //     attribution: '&copy; OpenStreetMap contributors'
        // }).addTo(leafletMap);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: ['a', 'b', 'c', 'd'],
            maxZoom: 19
        }).addTo(leafletMap);
        return { type: 'openstreetmap', map: leafletMap };
    }

    function setCenter(mapHandle, pos, zoom) {
        if (!mapHandle) return;
        if (mapHandle.type === 'google') {
            mapHandle.map.setCenter({ lat: pos.lat, lng: pos.lng });
            if (zoom) mapHandle.map.setZoom(zoom);
        } else {
            mapHandle.map.setView([pos.lat, pos.lng], zoom || mapHandle.map.getZoom());
        }
    }

    // ---- markers ----
    function addMarker(mapHandle, opts) {
        opts = opts || {};
        if (!mapHandle) return null;
        if (mapHandle.type === 'google') {
            var gOpts = {
                position: { lat: opts.lat, lng: opts.lng },
                map: mapHandle.map,
                draggable: !!opts.draggable,
                title: opts.title || ''
            };
            if (opts.iconUrl) {
                var anchor = opts.iconAnchor || [16, 32];
                gOpts.icon = {
                    url: opts.iconUrl,
                    scaledSize: new google.maps.Size(32, 32),
                    anchor: new google.maps.Point(anchor[0], anchor[1])
                };
            } else if (opts.color) {
                gOpts.icon = {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 10,
                    fillColor: opts.color,
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                };
            } else {
                gOpts.animation = google.maps.Animation.DROP;
            }
            return { type: 'google', marker: new google.maps.Marker(gOpts) };
        }
        var lMarker = L.marker([opts.lat, opts.lng], {
            draggable: !!opts.draggable,
            title: opts.title || '',
            icon: buildLeafletIcon(opts.color, opts.iconUrl, opts.iconAnchor)
        }).addTo(mapHandle.map);
        return { type: 'openstreetmap', marker: lMarker };
    }

    function setMarkerPosition(markerHandle, pos) {
        if (!markerHandle) return;
        if (markerHandle.type === 'google') {
            markerHandle.marker.setPosition({ lat: pos.lat, lng: pos.lng });
        } else {
            markerHandle.marker.setLatLng([pos.lat, pos.lng]);
        }
    }

    function bindPopup(markerHandle, html) {
        if (!markerHandle) return;
        if (markerHandle.type === 'google') {
            if (markerHandle.infoWindow) {
                markerHandle.infoWindow.setContent(html);
                return;
            }
            markerHandle.infoWindow = new google.maps.InfoWindow({ content: html });
            markerHandle.marker.addListener('click', function () {
                markerHandle.infoWindow.open({ anchor: markerHandle.marker, map: markerHandle.marker.getMap() });
            });
        } else {
            markerHandle.marker.bindPopup(html);
        }
    }

    function onMarkerDrag(markerHandle, cb) {
        if (!markerHandle) return;
        if (markerHandle.type === 'google') {
            markerHandle.marker.addListener('dragend', function (e) {
                cb({ lat: e.latLng.lat(), lng: e.latLng.lng() });
            });
        } else {
            markerHandle.marker.on('dragend', function (e) {
                var pos = e.target.getLatLng();
                cb({ lat: pos.lat, lng: pos.lng });
            });
        }
    }

    function onMapClick(mapHandle, cb) {
        if (!mapHandle) return;
        if (mapHandle.type === 'google') {
            mapHandle.map.addListener('click', function (e) {
                cb({ lat: e.latLng.lat(), lng: e.latLng.lng() });
            });
        } else {
            mapHandle.map.on('click', function (e) {
                cb({ lat: e.latlng.lat, lng: e.latlng.lng });
            });
        }
    }

    // ---- polyline ----
    function addPolyline(mapHandle, positions, opts) {
        opts = opts || {};
        if (!mapHandle) return null;
        if (mapHandle.type === 'google') {
            return {
                type: 'google',
                polyline: new google.maps.Polyline({
                    path: positions.map(function (p) { return { lat: p.lat, lng: p.lng }; }),
                    geodesic: true,
                    strokeColor: opts.color || '#3498db',
                    strokeOpacity: 0.8,
                    strokeWeight: 3,
                    map: mapHandle.map
                })
            };
        }
        var lPolyline = L.polyline(positions.map(function (p) { return [p.lat, p.lng]; }), {
            color: opts.color || '#3498db',
            opacity: 0.8,
            weight: 3
        }).addTo(mapHandle.map);
        return { type: 'openstreetmap', polyline: lPolyline };
    }

    function setPolylinePath(polylineHandle, positions) {
        if (!polylineHandle) return;
        if (polylineHandle.type === 'google') {
            polylineHandle.polyline.setPath(positions.map(function (p) { return { lat: p.lat, lng: p.lng }; }));
        } else {
            polylineHandle.polyline.setLatLngs(positions.map(function (p) { return [p.lat, p.lng]; }));
        }
    }

    // Re-layout a map after its container becomes visible (e.g. an inactive stepper step).
    function resize(mapHandle, markerHandle) {
        if (!mapHandle) return;
        if (mapHandle.type === 'google') {
            google.maps.event.trigger(mapHandle.map, 'resize');
            if (markerHandle) {
                mapHandle.map.setCenter(markerHandle.marker.getPosition());
            }
        } else {
            mapHandle.map.invalidateSize();
            if (markerHandle) {
                mapHandle.map.setView(markerHandle.marker.getLatLng());
            }
        }
    }

    function fitBounds(mapHandle, positions) {
        if (!mapHandle || !positions.length) return;
        if (mapHandle.type === 'google') {
            var bounds = new google.maps.LatLngBounds();
            positions.forEach(function (p) { bounds.extend({ lat: p.lat, lng: p.lng }); });
            mapHandle.map.fitBounds(bounds);
        } else {
            mapHandle.map.fitBounds(positions.map(function (p) { return [p.lat, p.lng]; }), { padding: [30, 30] });
        }
    }

    // Extract a city-level name from a Google Geocoder result's address_components.
    function extractGoogleCity(result) {
        var components = result.address_components || [];
        var typePriority = ['locality', 'administrative_area_level_2', 'administrative_area_level_1', 'postal_town'];
        for (var i = 0; i < typePriority.length; i++) {
            for (var j = 0; j < components.length; j++) {
                if (components[j].types && components[j].types.indexOf(typePriority[i]) !== -1) {
                    return components[j].long_name;
                }
            }
        }
        return '';
    }

    // ---- geocoding (reverse: lat/lng -> address) ----
    // Google: uses google.maps.Geocoder client-side (unchanged behaviour).
    // OpenStreetMap: calls our own backend (which proxies Nominatim), same envelope shape as Google mode.
    function reverseGeocode(pos, cb) {
        if (isGoogle()) {
            if (typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
                cb({ error: true });
                return;
            }
            var geocoder = new google.maps.Geocoder();
            geocoder.geocode({ location: { lat: pos.lat, lng: pos.lng } }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    cb({ error: false, formatted_address: results[0].formatted_address, city: extractGoogleCity(results[0]) });
                } else {
                    cb({ error: true });
                }
            });
            return;
        }
        var apiUrl = mpBaseUrl + 'api/v1/get_place_details_for_web?latitude=' + encodeURIComponent(pos.lat) + '&longitude=' + encodeURIComponent(pos.lng);
        $.ajax({
            url: apiUrl,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response && response.error === false && response.data && response.data.result) {
                    cb({
                        error: false,
                        formatted_address: response.data.result.formatted_address,
                        city: response.data.result.city || ''
                    });
                } else {
                    cb({ error: true });
                }
            },
            error: function () { cb({ error: true }); }
        });
    }

    window.MapProvider = {
        provider: PROVIDER,
        isGoogle: isGoogle,
        createMap: createMap,
        setCenter: setCenter,
        addMarker: addMarker,
        setMarkerPosition: setMarkerPosition,
        bindPopup: bindPopup,
        onMarkerDrag: onMarkerDrag,
        onMapClick: onMapClick,
        addPolyline: addPolyline,
        setPolylinePath: setPolylinePath,
        fitBounds: fitBounds,
        resize: resize,
        reverseGeocode: reverseGeocode
    };
})(window);
