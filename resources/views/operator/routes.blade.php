@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}" class="active">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Manage Routes</h1>

<div class="kbs-card kbs-form" style="margin-bottom:1.5rem">
    <form id="routeForm" method="POST" action="{{ route('operator.routes.store') }}">
        @csrf
        <div class="kbs-grid kbs-grid-3">
            <div>
                <label>Route Name</label>
                <input name="name" required placeholder="Nyabugogo to Kimironko" value="{{ old('name') }}">
            </div>
            <div>
                <label>Route Code</label>
                <input name="code" required placeholder="KBS-03" value="{{ old('code') }}">
            </div>
            <div>
                <label>Ticket Price (RWF)</label>
                <input type="number" name="base_price" required min="100" value="{{ old('base_price', 600) }}">
            </div>
            <div>
                <label>Estimated Duration (minutes)</label>
                <input type="number" name="estimated_duration_minutes" required min="1" value="{{ old('estimated_duration_minutes', 45) }}">
            </div>
            <div>
                <label>Origin</label>
                <input id="origin_display" readonly placeholder="First entered bus stop">
            </div>
            <div>
                <label>Destination</label>
                <input id="destination_display" readonly placeholder="Last entered bus stop">
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:.75rem">
            <h3 style="margin:0">Bus Stops</h3>
            <button type="button" class="kbs-btn kbs-btn-ghost" onclick="addStopRow()">Add Stop</button>
        </div>

        <div id="routeStops" class="kbs-route-stops"></div>

        <div id="routeMap" class="kbs-live-map" style="height:420px;margin:1rem 0"></div>
        <button class="kbs-btn kbs-btn-primary">Save Route</button>
    </form>
</div>

<h2>Active Routes</h2>
<div class="kbs-grid">
@foreach($routes as $route)
    @php
        $routeMapPoints = $route->stops->map(fn ($stop) => [(float) $stop->latitude, (float) $stop->longitude])->values();
    @endphp
    <div class="kbs-card kbs-route-card" data-route='@json($routeMapPoints)'>
        <strong>{{ $route->code }} - {{ $route->name }}</strong>
        <p style="margin:.35rem 0;color:var(--kbs-muted)">
            {{ $route->originStop->name }} to {{ $route->destinationStop->name }}
            @if($route->base_price)
                - {{ number_format($route->base_price) }} RWF
            @endif
            - {{ $route->estimated_duration_minutes }} min
        </p>
        <small>{{ $route->stops->pluck('name')->join(' -> ') }}</small>
    </div>
@endforeach
</div>

@php
    $defaultStops = [
        ['name' => '', 'code' => '', 'district' => 'Kigali', 'latitude' => '', 'longitude' => ''],
        ['name' => '', 'code' => '', 'district' => 'Kigali', 'latitude' => '', 'longitude' => ''],
    ];
    $oldStops = old('route_stops', $defaultStops);
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const oldStops = @json($oldStops);

    const map = L.map('routeMap').setView([-1.9441, 30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let routeLine = L.polyline([], { color: '#f4b400', weight: 6, opacity: .85 }).addTo(map);
    let markers = [];
    let autocompleteTimer = null;
    let autocompleteController = null;
    let routeRequestId = 0;

    function stopRowTemplate(index, stop = {}) {
        return `
            <div class="kbs-stop-row" data-stop-row>
                <div class="kbs-place-field">
                    <label>Stop Place</label>
                    <input name="route_stops[${index}][name]" value="${escapeHtml(stop.name || '')}" required placeholder="Start typing a place name" autocomplete="off" data-place-input>
                    <input type="hidden" name="route_stops[${index}][latitude]" value="${escapeHtml(stop.latitude || '')}">
                    <input type="hidden" name="route_stops[${index}][longitude]" value="${escapeHtml(stop.longitude || '')}">
                    <div class="kbs-place-suggestions" data-place-suggestions hidden></div>
                </div>
                <div>
                    <label>Code</label>
                    <input name="route_stops[${index}][code]" value="${escapeHtml(stop.code || '')}" placeholder="Optional" autocomplete="off">
                </div>
                <div>
                    <label>District</label>
                    <input name="route_stops[${index}][district]" value="${escapeHtml(stop.district || 'Kigali')}">
                </div>
                <div>
                    <label>Coordinates</label>
                    <span class="kbs-coordinate-pill" data-coordinate-label>${coordinateLabel(stop)}</span>
                </div>
                <div class="kbs-stop-actions">
                    <button type="button" class="kbs-btn kbs-btn-ghost" onclick="removeStopRow(this)">Remove</button>
                </div>
            </div>
        `;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function coordinateLabel(stop) {
        return stop.latitude && stop.longitude
            ? `${Number(stop.latitude).toFixed(6)}, ${Number(stop.longitude).toFixed(6)}`
            : 'Choose a place to set coordinates';
    }

    function reindexRows() {
        document.querySelectorAll('[data-stop-row]').forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/route_stops\[\d+\]/, `route_stops[${index}]`);
            });
        });
    }

    function addStopRow(stop = {}) {
        const container = document.getElementById('routeStops');
        container.insertAdjacentHTML('beforeend', stopRowTemplate(container.children.length, stop));
        bindStopInputs();
        updateRouteVisualization();
    }

    function removeStopRow(button) {
        const rows = document.querySelectorAll('[data-stop-row]');
        if (rows.length <= 2) return;
        button.closest('[data-stop-row]').remove();
        reindexRows();
        updateRouteVisualization();
    }

    function bindStopInputs() {
        document.querySelectorAll('[data-stop-row] input').forEach(input => {
            input.removeEventListener('input', updateRouteVisualization);
            input.addEventListener('input', updateRouteVisualization);
        });

        document.querySelectorAll('[data-place-input]').forEach(input => {
            input.removeEventListener('input', handlePlaceInput);
            input.addEventListener('input', handlePlaceInput);
        });
    }

    function rowData(row) {
        return {
            name: row.querySelector('[name$="[name]"]').value,
            lat: parseFloat(row.querySelector('[name$="[latitude]"]').value),
            lng: parseFloat(row.querySelector('[name$="[longitude]"]').value),
        };
    }

    function handlePlaceInput(event) {
        const input = event.target;
        const row = input.closest('[data-stop-row]');
        const query = input.value.trim();
        input.setCustomValidity('');
        row.querySelector('[name$="[latitude]"]').value = '';
        row.querySelector('[name$="[longitude]"]').value = '';
        row.querySelector('[data-coordinate-label]').textContent = 'Choose a place to set coordinates';
        updateRouteVisualization();

        clearTimeout(autocompleteTimer);
        if (query.length < 3) {
            hideSuggestions(row);
            return;
        }

        autocompleteTimer = setTimeout(() => fetchPlaceSuggestions(row, query), 300);
    }

    function hideSuggestions(row) {
        const suggestions = row.querySelector('[data-place-suggestions]');
        suggestions.hidden = true;
        suggestions.innerHTML = '';
    }

    async function fetchPlaceSuggestions(row, query) {
        if (autocompleteController) autocompleteController.abort();
        autocompleteController = new AbortController();

        const params = new URLSearchParams({
            format: 'jsonv2',
            addressdetails: '1',
            limit: '6',
            countrycodes: 'rw',
            q: query
        });

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                signal: autocompleteController.signal,
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) return;
            const places = await response.json();
            renderPlaceSuggestions(row, places);
        } catch (error) {
            if (error.name !== 'AbortError') hideSuggestions(row);
        }
    }

    function renderPlaceSuggestions(row, places) {
        const suggestions = row.querySelector('[data-place-suggestions]');
        if (!places.length) {
            suggestions.innerHTML = '<div class="kbs-place-suggestion">No matching places found</div>';
            suggestions.hidden = false;
            return;
        }

        suggestions.innerHTML = places.map((place, index) => {
            const address = place.address || {};
            const district = address.city || address.town || address.county || address.state || address.suburb || 'Rwanda';
            const label = place.name || place.display_name.split(',')[0];
            return `
                <button type="button" class="kbs-place-suggestion" data-place-index="${index}">
                    ${escapeHtml(label)}
                    <small>${escapeHtml(place.display_name)}</small>
                </button>
            `;
        }).join('');

        suggestions.querySelectorAll('[data-place-index]').forEach(button => {
            button.addEventListener('click', () => selectPlace(row, places[button.dataset.placeIndex]));
        });
        suggestions.hidden = false;
    }

    function selectPlace(row, place) {
        const address = place.address || {};
        const name = place.name || place.display_name.split(',')[0];
        const district = address.city || address.town || address.county || address.state || address.suburb || 'Kigali';
        const lat = Number(place.lat);
        const lng = Number(place.lon);

        row.querySelector('[name$="[name]"]').value = name;
        row.querySelector('[name$="[district]"]').value = district;
        row.querySelector('[name$="[latitude]"]').value = lat.toFixed(7);
        row.querySelector('[name$="[longitude]"]').value = lng.toFixed(7);
        row.querySelector('[name$="[name]"]').setCustomValidity('');
        row.querySelector('[data-coordinate-label]').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        hideSuggestions(row);
        updateRouteVisualization();
    }

    function updateRouteVisualization() {
        markers.forEach(marker => marker.remove());
        markers = [];

        const rows = Array.from(document.querySelectorAll('[data-stop-row]'));
        const stops = rows.map(rowData).filter(stop => !Number.isNaN(stop.lat) && !Number.isNaN(stop.lng));

        document.getElementById('origin_display').value = rows[0]?.querySelector('[name$="[name]"]').value || '';
        document.getElementById('destination_display').value = rows[rows.length - 1]?.querySelector('[name$="[name]"]').value || '';

        const coords = stops.map((stop, index) => {
            const point = [stop.lat, stop.lng];
            const isFirst = index === 0;
            const isLast = index === stops.length - 1;

            markers.push(L.circleMarker(point, {
                radius: isFirst || isLast ? 9 : 6,
                color: isFirst ? '#0b5e3c' : isLast ? '#c0392b' : '#5f736c',
                fillColor: isFirst ? '#0b5e3c' : isLast ? '#c0392b' : '#fff',
                fillOpacity: .9,
                weight: 2
            }).bindPopup(stop.name || `Stop ${index + 1}`).addTo(map));

            return point;
        });

        drawRoutePath(coords);
    }

    async function drawRoutePath(coords) {
        const requestId = ++routeRequestId;
        if (coords.length < 2) {
            routeLine.setLatLngs(coords);
            return;
        }

        routeLine.setLatLngs(coords);

        try {
            const coordinateText = coords.map(point => `${point[1]},${point[0]}`).join(';');
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinateText}?overview=full&geometries=geojson`);
            if (!response.ok) throw new Error('Route lookup failed');
            const data = await response.json();
            const roadPath = data.routes?.[0]?.geometry?.coordinates?.map(point => [point[1], point[0]]);
            if (requestId === routeRequestId && roadPath?.length) {
                routeLine.setLatLngs(roadPath);
            }
        } catch (error) {
            routeLine.setLatLngs(coords);
        }

        if (requestId === routeRequestId && routeLine.getLatLngs().length > 1) {
            map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
        }
    }

    oldStops.forEach(stop => addStopRow(stop));

    document.getElementById('routeForm').addEventListener('submit', event => {
        const missing = Array.from(document.querySelectorAll('[data-stop-row]')).find(row => {
            return !row.querySelector('[name$="[latitude]"]').value || !row.querySelector('[name$="[longitude]"]').value;
        });

        if (!missing) return;

        event.preventDefault();
        const input = missing.querySelector('[name$="[name]"]');
        input.setCustomValidity('Choose this stop from the place suggestions so coordinates are set automatically.');
        input.reportValidity();
        input.focus();
    });

    document.querySelectorAll('.kbs-route-card').forEach(card => {
        card.addEventListener('click', () => {
            const coords = JSON.parse(card.dataset.route);
            document.querySelectorAll('.kbs-route-card').forEach(item => item.classList.remove('is-active'));
            card.classList.add('is-active');
            drawRoutePath(coords);
        });
    });

    document.addEventListener('click', event => {
        if (event.target.closest('.kbs-place-field')) return;
        document.querySelectorAll('[data-stop-row]').forEach(hideSuggestions);
    });
</script>
@endsection
