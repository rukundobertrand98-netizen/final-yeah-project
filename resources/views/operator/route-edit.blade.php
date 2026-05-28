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
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <h1 style="margin:0">Edit Route: {{ $route->code }}</h1>
    <div style="display:flex;gap:.5rem">
        <a href="{{ route('operator.routes') }}" class="kbs-btn kbs-btn-ghost">← Back to Routes</a>
        <form method="POST" action="{{ route('operator.routes.delete', $route) }}" onsubmit="return confirm('Are you sure? Routes with bookings will be deactivated instead of deleted.')">
            @csrf @method('DELETE')
            <button class="kbs-btn kbs-btn-ghost" style="color:#c0392b">Delete Route</button>
        </form>
    </div>
</div>

<div class="kbs-card kbs-form" style="margin:1.5rem 0">
    <form id="routeForm" method="POST" action="{{ route('operator.routes.update', $route) }}">
        @csrf @method('PUT')
        <div class="kbs-grid kbs-grid-3">
            <div>
                <label>Route Name</label>
                <input name="name" required value="{{ old('name', $route->name) }}">
            </div>
            <div>
                <label>Route Code</label>
                <input name="code" required value="{{ old('code', $route->code) }}">
            </div>
            <div>
                <label>Ticket Price (RWF)</label>
                <input type="number" name="base_price" required min="100" value="{{ old('base_price', $route->base_price) }}">
            </div>
            <div>
                <label>Estimated Duration (minutes)</label>
                <input type="number" name="estimated_duration_minutes" required min="1" value="{{ old('estimated_duration_minutes', $route->estimated_duration_minutes) }}">
            </div>
            <div>
                <label>Origin</label>
                <input id="origin_display" readonly value="{{ $route->originStop->name }}">
            </div>
            <div>
                <label>Destination</label>
                <input id="destination_display" readonly value="{{ $route->destinationStop->name }}">
            </div>
        </div>
        <div style="margin:.75rem 0">
            <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $route->is_active))>
                Route is active
            </label>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:.75rem">
            <h3 style="margin:0">Bus Stops</h3>
            <button type="button" class="kbs-btn kbs-btn-ghost" onclick="addStopRow()">Add Stop</button>
        </div>

        <div id="routeStops" class="kbs-route-stops"></div>

        <div id="routeMap" class="kbs-live-map" style="height:420px;margin:1rem 0"></div>
        <button class="kbs-btn kbs-btn-primary">Update Route</button>
    </form>
</div>

<h2>Schedules on this Route</h2>

<div class="kbs-card kbs-form" style="margin-bottom:1rem">
    <h3 style="margin-top:0">Add New Schedule</h3>
    <form method="POST" action="{{ route('operator.schedules.store') }}">
        @csrf
        <input type="hidden" name="route_id" value="{{ $route->id }}">
        <div class="kbs-grid kbs-grid-3">
            <div><label>Bus</label><select name="bus_id" required>@foreach($buses as $b)<option value="{{ $b->id }}">{{ $b->plate_number }} ({{ $b->model ?: $b->capacity.' seats' }})</option>@endforeach</select></div>
            <div><label>Driver</label><select name="driver_id"><option value="">—</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
            <div><label>Date</label><input type="date" name="travel_date" required></div>
            <div><label>Departure</label><input type="time" name="departure_time" required></div>
            <div><label>Arrival</label><input type="time" name="arrival_time"></div>
            <div><label>Price (RWF)</label><input type="number" name="price" value="{{ (int) $route->base_price }}" required min="100"></div>
        </div>
        <button class="kbs-btn kbs-btn-primary">Add Schedule</button>
    </form>
</div>

@if($schedules->count())
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Date</th><th>Time</th><th>Bus</th><th>Driver</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($schedules as $s)
            <tr id="schedule-row-{{ $s->id }}">
                <td colspan="7" style="padding:0">
                    <form method="POST" action="{{ route('operator.schedules.update', $s) }}" style="display:contents">
                        @csrf @method('PUT')
                        <input type="hidden" name="route_id" value="{{ $route->id }}">
                        <table style="width:100%;border:0"><tr>
                            <td style="width:14%"><input type="date" name="travel_date" value="{{ $s->travel_date->format('Y-m-d') }}" required style="width:100%"></td>
                            <td style="width:14%">
                                <input type="time" name="departure_time" value="{{ $s->departure_time }}" required style="width:100%">
                                <input type="time" name="arrival_time" value="{{ $s->arrival_time }}" style="width:100%;margin-top:2px" placeholder="Arrival">
                            </td>
                            <td style="width:18%">
                                <select name="bus_id" required style="width:100%">
                                    @foreach($buses as $b)
                                        <option value="{{ $b->id }}" @selected($s->bus_id === $b->id)>{{ $b->plate_number }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="width:18%">
                                <select name="driver_id" style="width:100%">
                                    <option value="">—</option>
                                    @foreach($drivers as $d)
                                        <option value="{{ $d->id }}" @selected($s->driver_id === $d->id)>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="width:10%"><input type="number" name="price" value="{{ (int) $s->price }}" required min="100" style="width:100%"></td>
                            <td style="width:12%">
                                <select name="status" style="width:100%">
                                    @foreach(['scheduled','boarding','in_progress','delayed','completed','cancelled'] as $st)
                                        <option value="{{ $st }}" @selected($s->status === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="width:14%;white-space:nowrap">
                                <button type="submit" class="kbs-btn kbs-btn-primary" style="padding:.3rem .6rem;font-size:.85rem">Save</button>
                    </form>
                                <form method="POST" action="{{ route('operator.schedules.delete', $s) }}" style="display:inline" onsubmit="return confirm('Delete this schedule?')">
                                    @csrf @method('DELETE')
                                    <button class="kbs-btn kbs-btn-ghost" style="padding:.3rem .6rem;font-size:.85rem;color:#c0392b">Del</button>
                                </form>
                            </td>
                        </tr></table>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
<div class="kbs-card"><p>No schedules yet for this route. Add one above.</p></div>
@endif

@php
    $existingStops = old('route_stops', $route->stops->map(fn($s) => [
        'name' => $s->name,
        'code' => $s->code,
        'district' => $s->district,
        'latitude' => (string) $s->latitude,
        'longitude' => (string) $s->longitude,
    ])->values()->all());
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const oldStops = @json($existingStops);

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
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
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
        if (query.length < 3) { hideSuggestions(row); return; }
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
        const params = new URLSearchParams({ format: 'jsonv2', addressdetails: '1', limit: '6', countrycodes: 'rw', q: query });
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                signal: autocompleteController.signal, headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) return;
            renderPlaceSuggestions(row, await response.json());
        } catch (error) { if (error.name !== 'AbortError') hideSuggestions(row); }
    }

    function renderPlaceSuggestions(row, places) {
        const suggestions = row.querySelector('[data-place-suggestions]');
        if (!places.length) {
            suggestions.innerHTML = '<div class="kbs-place-suggestion">No matching places found</div>';
            suggestions.hidden = false;
            return;
        }
        suggestions.innerHTML = places.map((place, index) => {
            const label = place.name || place.display_name.split(',')[0];
            return `<button type="button" class="kbs-place-suggestion" data-place-index="${index}">${escapeHtml(label)}<small>${escapeHtml(place.display_name)}</small></button>`;
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
                fillOpacity: .9, weight: 2
            }).bindPopup(stop.name || `Stop ${index + 1}`).addTo(map));
            return point;
        });
        drawRoutePath(coords);
    }

    async function drawRoutePath(coords) {
        const requestId = ++routeRequestId;
        if (coords.length < 2) { routeLine.setLatLngs(coords); return; }
        routeLine.setLatLngs(coords);
        try {
            const coordinateText = coords.map(point => `${point[1]},${point[0]}`).join(';');
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinateText}?overview=full&geometries=geojson`);
            if (!response.ok) throw new Error('Route lookup failed');
            const data = await response.json();
            const roadPath = data.routes?.[0]?.geometry?.coordinates?.map(point => [point[1], point[0]]);
            if (requestId === routeRequestId && roadPath?.length) routeLine.setLatLngs(roadPath);
        } catch (error) { routeLine.setLatLngs(coords); }
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

    document.addEventListener('click', event => {
        if (event.target.closest('.kbs-place-field')) return;
        document.querySelectorAll('[data-stop-row]').forEach(hideSuggestions);
    });
</script>
@endsection
