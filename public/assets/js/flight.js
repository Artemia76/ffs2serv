/*
 * Détail d'un vol : trace sur la carte
 */
(function () {
    'use strict';

    var node = document.getElementById('flight-map');
    if (!node || typeof L === 'undefined') {
        return;
    }
    var track = JSON.parse(node.dataset.track || '[]');
    var departure = JSON.parse(node.dataset.departure || 'null');
    var arrival = JSON.parse(node.dataset.arrival || 'null');

    var map = L.map(node);
    L.tileLayer(node.dataset.tiles, { maxZoom: 18, attribution: node.dataset.attribution }).addTo(map);

    var points = track.map(function (p) { return [p[0], p[1]]; });
    if (departure) { points.unshift(departure); }
    if (arrival) { points.push(arrival); }

    if (points.length) {
        L.polyline(points, { className: 'trail flight', weight: 3 }).addTo(map);
        if (departure) { L.circleMarker(departure, { radius: 6, className: 'point departure' }).bindTooltip('Départ').addTo(map); }
        if (arrival) { L.circleMarker(arrival, { radius: 6, className: 'point arrival' }).bindTooltip('Arrivée').addTo(map); }
        map.fitBounds(points, { padding: [30, 30], maxZoom: 11 });
    } else {
        map.setView([46.5, 2.5], 5);
    }
})();
