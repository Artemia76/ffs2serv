/*
 * Page d'accueil : pilotes en direct (carte Leaflet + tableau), rafraîchis toutes les 5 secondes
 * à partir de GET /api/live.
 */
(function () {
    'use strict';

    var root = document.getElementById('live');
    if (!root || typeof L === 'undefined') {
        return;
    }

    var REFRESH = 5000;
    var PHASES = {
        IDLE: 'À l\'arrêt', Boarding: 'Embarquement', Pushback: 'Repoussage', Taxiway: 'Roulage',
        Takeoff: 'Décollage', Climb: 'Montée', Cruise: 'Croisière', Mission: 'Mission', InFlight: 'En vol',
        StepLevel: 'Changement de niveau', Descent: 'Descente', Approach: 'Approche', Final: 'Finale',
        GoAround: 'Remise des gaz', Landing: 'Atterrissage', JoinGate: 'Vers la porte'
    };

    var map = L.map('live-map', { worldCopyJump: true }).setView([46.5, 2.5], 5);
    L.tileLayer(root.dataset.tiles, {
        maxZoom: 18,
        attribution: root.dataset.attribution,
        // politique d'utilisation des tuiles OpenStreetMap : Referer obligatoire
        referrerPolicy: 'strict-origin-when-cross-origin'
    }).addTo(map);

    var markers = {};
    var trails = {};
    var currentTab = 'all';
    var lastData = null;
    var fitted = false;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    function visible(pilot) {
        return currentTab === 'all' || String(pilot.server_id) === currentTab;
    }

    function icon(pilot) {
        var heading = pilot.position ? pilot.position.heading : 0;
        var html = '<img src="' + root.dataset.icon + '" alt="" style="transform: rotate(' + heading + 'deg)">';
        return L.divIcon({
            className: 'plane-icon' + (pilot.me ? ' me' : '') + (pilot.position && pilot.position.on_ground ? ' ground' : ''),
            html: html, iconSize: [28, 28], iconAnchor: [14, 14]
        });
    }

    function tooltip(pilot) {
        var p = pilot.position;
        var lines = [pilot.callsign + (pilot.aircraft ? ' · ' + pilot.aircraft : '')];
        if (p) {
            lines.push(p.altitude.toLocaleString('fr-FR') + ' ft · ' + p.gs + ' kt · cap ' + p.heading + '°');
        }
        if (pilot.phase) { lines.push(PHASES[pilot.phase] || pilot.phase); }
        lines.push(pilot.server + (pilot.team ? ' · équipe ' + pilot.team : ''));
        return lines.map(function (line) {
            var div = document.createElement('div');
            div.textContent = line;
            return div.outerHTML;
        }).join('');
    }

    function renderTabs(data) {
        var tabs = document.getElementById('live-tabs');
        tabs.textContent = '';
        var total = data.pilots.length;
        var entries = [{ id: 'all', label: 'Tous', count: total }].concat(data.servers.map(function (s) {
            return { id: String(s.id), label: s.name + (s.private ? ' 🔒' : ''), count: s.pilots };
        }));
        entries.forEach(function (entry) {
            var button = el('button', 'tab', entry.label + ' ');
            button.type = 'button';
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', String(entry.id === currentTab));
            button.appendChild(el('span', 'count', String(entry.count)));
            button.addEventListener('click', function () {
                currentTab = entry.id;
                render(lastData);
            });
            tabs.appendChild(button);
        });
    }

    function renderTable(pilots) {
        var tbody = document.querySelector('#live-table tbody');
        tbody.textContent = '';
        if (!pilots.length) {
            var row = el('tr');
            var cell = el('td', 'muted', 'Aucun pilote connecté pour le moment.');
            cell.colSpan = 7;
            row.appendChild(cell);
            tbody.appendChild(row);
            return;
        }
        pilots.forEach(function (pilot) {
            var p = pilot.position;
            var row = el('tr', pilot.me ? 'me' : '');
            var name = el('td');
            var link = el('a', '', pilot.callsign);
            link.href = root.dataset.pilotUrl + encodeURIComponent(pilot.callsign);
            name.appendChild(link);
            if (pilot.global) { name.appendChild(el('span', 'badge', 'global')); }
            if (pilot.team) { name.appendChild(el('span', 'badge', pilot.team)); }
            row.appendChild(name);
            row.appendChild(el('td', '', pilot.aircraft || '—'));
            row.appendChild(el('td', '', pilot.phase ? (PHASES[pilot.phase] || pilot.phase) : '—'));
            row.appendChild(el('td', '', p ? p.altitude.toLocaleString('fr-FR') + ' ft' : '—'));
            row.appendChild(el('td', '', p ? p.gs + ' kt' : '—'));
            row.appendChild(el('td', '', p ? p.heading + '°' : '—'));
            row.appendChild(el('td', '', pilot.sim || '—'));
            if (p) {
                row.classList.add('clickable');
                row.addEventListener('click', function (event) {
                    if (event.target.tagName !== 'A') {
                        map.setView([p.lat, p.lon], Math.max(map.getZoom(), 8));
                        markers[pilot.callsign] && markers[pilot.callsign].openTooltip();
                    }
                });
            }
            tbody.appendChild(row);
        });
    }

    function renderMap(pilots) {
        var seen = {};
        var bounds = [];
        pilots.forEach(function (pilot) {
            if (!pilot.position) { return; }
            var key = pilot.callsign;
            var latlng = [pilot.position.lat, pilot.position.lon];
            seen[key] = true;
            bounds.push(latlng);
            if (markers[key]) {
                markers[key].setLatLng(latlng).setIcon(icon(pilot)).setTooltipContent(tooltip(pilot));
            } else {
                markers[key] = L.marker(latlng, { icon: icon(pilot), title: key })
                    .bindTooltip(tooltip(pilot), { direction: 'top', offset: [0, -12] }).addTo(map);
            }
            var trail = (pilot.trail || []).concat([latlng]);
            if (trails[key]) {
                trails[key].setLatLngs(trail);
            } else {
                trails[key] = L.polyline(trail, { className: 'trail' + (pilot.me ? ' me' : ''), weight: 2 }).addTo(map);
            }
        });
        Object.keys(markers).forEach(function (key) {
            if (!seen[key]) {
                map.removeLayer(markers[key]);
                map.removeLayer(trails[key]);
                delete markers[key];
                delete trails[key];
            }
        });
        if (!fitted && bounds.length) {
            fitted = true;
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });
        }
    }

    function render(data) {
        if (!data) { return; }
        var pilots = data.pilots.filter(visible);
        renderTabs(data);
        renderTable(pilots);
        renderMap(pilots);
        var online = document.getElementById('stat-online');
        if (online) { online.textContent = String(data.pilots.length); }
        document.getElementById('live-updated').textContent =
            'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR');
    }

    function refresh() {
        fetch(root.dataset.api, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.json();
            })
            .then(function (data) {
                lastData = data;
                render(data);
            })
            .catch(function () {
                document.getElementById('live-updated').textContent = 'Connexion au serveur perdue, nouvelle tentative…';
            })
            .finally(function () {
                setTimeout(refresh, document.hidden ? REFRESH * 4 : REFRESH);
            });
    }

    refresh();
})();
