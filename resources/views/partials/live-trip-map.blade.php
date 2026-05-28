@php
    $mapId = $mapId ?? 'kbsLiveMap';
    $dataUrl = $dataUrl ?? null;
    $pollSeconds = config('kbs.tracking.poll_interval_seconds', 15);
@endphp

<div class="kbs-live-map-wrap">
    <div class="kbs-live-map-status" id="{{ $mapId }}Status">
        <span class="kbs-badge kbs-badge-info">Loading map…</span>
    </div>
    <div id="{{ $mapId }}" class="kbs-live-map"></div>
    <div class="kbs-live-map-legend">
        <span><i class="kbs-dot kbs-dot-departure"></i> From (your departure)</span>
        <span><i class="kbs-dot kbs-dot-destination"></i> To (your destination)</span>
        <span><i class="kbs-dot kbs-dot-bus"></i> Bus</span>
        <span><i class="kbs-dot kbs-dot-stop"></i> Route stop</span>
    </div>
</div>

@once
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush
@endonce

@push('scripts')
<script>
(function () {
    const mapId = @json($mapId);
    const dataUrl = @json($dataUrl);
    const pollMs = {{ (int) $pollSeconds * 1000 }};
    if (!dataUrl) return;

    let map, layers = {}, pollTimer;

    function initMap(center) {
        const el = document.getElementById(mapId);
        if (!el || map) return;
        map = L.map(mapId).setView(center, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
    }

    function marker(lat, lng, options) {
        return L.circleMarker([lat, lng], options);
    }

    function clearLayers() {
        Object.values(layers).forEach(m => map && m.remove());
        layers = {};
    }

    function updateStatus(data) {
        const box = document.getElementById(mapId + 'Status');
        if (!box) return;
        let html = `<strong>${data.route_name}</strong> · ${data.trip_status}`;
        html += `<br><span class="kbs-route-label">From:</span> <strong>${data.departure.name}</strong>`;
        html += ` &nbsp;→&nbsp; <span class="kbs-route-label">To:</span> <strong>${data.destination.name}</strong>`;

        if (data.bus) {
            if (data.nearest_stop) {
                const near = data.nearest_stop.is_your_departure
                    ? `<span class="kbs-badge kbs-badge-success">Bus is at/near your departure stop — be ready!</span>`
                    : `Bus near stop: <strong>${data.nearest_stop.name}</strong>`;
                html += `<br>${near}`;
            }
            if (data.distance_to_departure_km !== null) {
                html += `<br>Distance to your departure stop: <strong>${data.distance_to_departure_km} km</strong>`;
            }
            if (data.approaching_pickup) {
                html += `<br><span class="kbs-badge kbs-badge-success">Your bus is approaching ${data.departure.name}. Please prepare for boarding. Estimated arrival: ${data.estimated_arrival_minutes ?? 5} minutes.</span>`;
            }
        } else {
            html += `<br><em>Bus location will appear when the driver starts the trip.</em>`;
        }
        box.innerHTML = html;
    }

    let cachedRoadPath = null;

    async function fetchRoadPath(coords) {
        if (!coords || coords.length < 2) return null;
        try {
            const coordinateText = coords.map(point => `${point[1]},${point[0]}`).join(';');
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinateText}?overview=full&geometries=geojson`);
            if (!response.ok) return null;
            const data = await response.json();
            return data.routes?.[0]?.geometry?.coordinates?.map(point => [point[1], point[0]]) || null;
        } catch (e) {
            return null;
        }
    }

    async function render(data) {
        const center = data.bus
            ? [data.bus.latitude, data.bus.longitude]
            : [data.departure.latitude, data.departure.longitude];
        initMap(center);
        clearLayers();

        layers.departure = marker(data.departure.latitude, data.departure.longitude, {
            radius: 10, color: '#0b5e3c', fillColor: '#0b5e3c', fillOpacity: 0.9, weight: 2
        }).bindPopup(`<b>From (Departure)</b><br>${data.departure.name}`).addTo(map);

        const orderedRouteStops = [...(data.route_stops || [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
        const routePoints = orderedRouteStops.map(stop => [stop.latitude, stop.longitude]);
        if (routePoints.length > 1) {
            let pathPoints = routePoints;
            if (data.map_path && data.map_path.length > 1) {
                pathPoints = data.map_path;
            } else if (!cachedRoadPath) {
                cachedRoadPath = await fetchRoadPath(routePoints);
            }
            if (cachedRoadPath) pathPoints = cachedRoadPath;

            layers.route = L.polyline(pathPoints, {
                color: '#f4b400',
                weight: 5,
                opacity: 0.85
            }).addTo(map);
            layers.route.bindPopup('<b>Operator route path</b><br>This line follows the real bus road path through all stops.');
        }

        layers.destination = marker(data.destination.latitude, data.destination.longitude, {
            radius: 10, color: '#c0392b', fillColor: '#c0392b', fillOpacity: 0.9, weight: 2
        }).bindPopup(`<b>To (Destination)</b><br>${data.destination.name}`).addTo(map);

        orderedRouteStops.forEach(stop => {
            if (stop.is_departure || stop.is_destination) return;
            const m = marker(stop.latitude, stop.longitude, {
                radius: 6, color: '#5f736c', fillColor: '#fff', fillOpacity: 0.9, weight: 2
            }).bindPopup(stop.name);
            m.addTo(map);
        });

        if (data.bus) {
            layers.bus = marker(data.bus.latitude, data.bus.longitude, {
                radius: 12, color: '#f4b400', fillColor: '#f4b400', fillOpacity: 1, weight: 3
            }).bindPopup(`<b>KBS Bus</b><br>Updated: ${new Date(data.bus.recorded_at).toLocaleTimeString()}`).addTo(map);
        }

        if (data.nearest_stop && data.bus) {
            layers.nearest = marker(data.nearest_stop.latitude, data.nearest_stop.longitude, {
                radius: 8, color: '#2980b9', fillColor: '#3498db', fillOpacity: 0.6, weight: 2, dashArray: '4 4'
            }).bindPopup(`<b>Nearest stop center</b><br>${data.nearest_stop.name}`).addTo(map);
        }

        const bounds = [];
        bounds.push([data.departure.latitude, data.departure.longitude]);
        bounds.push([data.destination.latitude, data.destination.longitude]);
        if (data.bus) bounds.push([data.bus.latitude, data.bus.longitude]);
        routePoints.forEach(point => bounds.push(point));
        if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });

        updateStatus(data);
    }

    async function fetchAndRender() {
        try {
            const res = await fetch(dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            render(await res.json());
        } catch (e) { console.warn('Tracking poll failed', e); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchAndRender();
        pollTimer = setInterval(fetchAndRender, pollMs);
    });

    window.addEventListener('beforeunload', () => clearInterval(pollTimer));
})();
</script>
@endpush
