@extends('layouts.dashboard')

@section('title', 'Search Routes')
@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Overview</a>
    <a href="{{ route('passenger.search') }}" class="active">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}">My Tickets</a>
@endsection

@section('panel')
<h1>Find Your Bus</h1>

<div class="kbs-card kbs-form" style="margin-bottom:1.5rem">
    <form method="GET">
        <div class="kbs-grid kbs-grid-3">
            <div>
                <label>Pickup Location</label>
                <input list="originStops" id="origin_text" placeholder="Type or select your bus stop">
                <input type="hidden" name="origin_stop_id" id="origin_stop_id" value="{{ request('origin_stop_id') }}">
            </div>
            <div>
                <label>Destination</label>
                <input list="destinationStops" id="destination_text" placeholder="Type or select destination">
                <input type="hidden" name="destination_stop_id" id="destination_stop_id" value="{{ request('destination_stop_id') }}">
            </div>
            <div>
                <label>Travel Date</label>
                <input type="date" name="travel_date" value="{{ request('travel_date', date('Y-m-d')) }}" min="{{ date('Y-m-d') }}">
            </div>
            <div>
                <label>Number of Seats</label>
                <input type="number" name="seats" value="{{ request('seats', 1) }}" min="1" required>
            </div>
        </div>

        <datalist id="originStops">
            @foreach($stops as $stop)
                <option value="{{ $stop->name }}" data-id="{{ $stop->id }}"></option>
            @endforeach
        </datalist>
        <datalist id="destinationStops">
            @foreach($stops as $stop)
                <option value="{{ $stop->name }}" data-id="{{ $stop->id }}"></option>
            @endforeach
        </datalist>

        <button type="submit" class="kbs-btn kbs-btn-primary"><svg><use href="#icon-search"></use></svg>Search Buses</button>
    </form>
</div>

@if(!request()->filled(['origin_stop_id', 'destination_stop_id']) && $routePreviews->count())
    <div style="margin-bottom:1.5rem">
        <h2 style="font-size:1.25rem; margin-bottom:1rem">Discover Available Routes</h2>
        <div class="kbs-grid kbs-grid-3" style="gap:1rem">
            @foreach($routePreviews as $idx => $preview)
                <div class="kbs-card" style="cursor:pointer; padding: 1rem; border: 1px solid var(--kbs-border)" onclick="previewRoute({{ $idx }})">
                    <div style="display:flex; justify-content:space-between; align-items:start">
                        <strong style="color:var(--kbs-primary)">{{ $preview['route_name'] }}</strong>
                        <span class="kbs-badge kbs-badge-info">{{ count($preview['stop_names']) }} stops</span>
                    </div>
                    <p style="font-size:0.85rem; color:var(--kbs-muted); margin-top:0.5rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">
                        {{ $preview['stop_names']->first() }} → {{ $preview['stop_names']->last() }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="kbs-card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <div id="searchMap" class="kbs-live-map" style="height:430px;border:0;border-radius:0"></div>
    <div style="padding:1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        <button type="button" class="kbs-btn kbs-btn-ghost" onclick="locateUser()"><svg><use href="#icon-search"></use></svg>Use My Location</button>
        <span id="location-status" style="color:var(--kbs-muted)">Select stops from the form or click markers on the map.</span>
    </div>
</div>

@if($schedules->count())
    <h2>Available Buses</h2>
    <div class="kbs-grid" style="gap:1rem">
        @foreach($schedules as $schedule)
            @php
                $originId = (int) request('origin_stop_id');
                $destId = (int) request('destination_stop_id');

                $allRouteStops = $schedule->route->stops->sortBy('pivot.sequence')->values();
                $originStop = $allRouteStops->firstWhere('id', $originId);
                $destStop = $allRouteStops->firstWhere('id', $destId);

                $routeSegmentStops = collect();
                if ($schedule->stopsConnectInDirection($originId, $destId)) {
                    $ordered = $schedule->orderedStopsForLeg();
                    $fromIdx = $schedule->stopOrderIndex($originId);
                    $toIdx = $schedule->stopOrderIndex($destId);
                    $routeSegmentStops = $ordered
                        ->filter(fn ($s, $idx) => $idx >= $fromIdx && $idx <= $toIdx)
                        ->values();
                }

                $routeCoords = $routeSegmentStops
                    ->map(fn ($stop) => [(float) $stop->latitude, (float) $stop->longitude])
                    ->values();
                $routeStopNames = $routeSegmentStops
                    ->map(fn ($stop) => $stop->name)
                    ->values();

                $arrivalAtOrigin = $schedule->timeAtStop($originId);
                $arrivalAtDestination = $schedule->timeAtStop($destId);

                $travelDuration = ($arrivalAtOrigin && $arrivalAtDestination)
                    ? $arrivalAtOrigin->diffInMinutes($arrivalAtDestination)
                    : null;

                $availableSeats = $schedule->availableSeatCount();
                $busName = $schedule->bus->model ?: ($schedule->bus->fleet_number ?: $schedule->bus->plate_number);
            @endphp
            <div class="kbs-card kbs-route-result"
                 data-route='@json($routeCoords)'
                 data-route-stop-names='@json($routeStopNames)'
                 @if($schedule->route->map_path) data-map-path='@json($schedule->route->map_path)' @endif
                 style="cursor:pointer;transition:border-color .2s; padding: 1.5rem;">
                
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0; color: var(--kbs-green-dark); font-size: 1.2rem;">
                            {{ $schedule->route->name }}
                        </h3>
                        <div style="margin-top: 0.3rem; font-size: 0.9rem; color: var(--kbs-muted);">
                            {{ $schedule->route->code }} · {{ $busName }}
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--kbs-green-dark);">
                            {{ number_format($schedule->price) }} RWF
                        </div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">per seat</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Departure</div>
                        <div style="font-weight: 500; margin-top: 0.2rem;">{{ $schedule->departure_time }}</div>
                        @if($arrivalAtOrigin && $arrivalAtOrigin->format('H:i') !== $schedule->departure_time)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                At pickup: {{ $arrivalAtOrigin->format('H:i') }}
                            </div>
                        @endif
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Duration</div>
                        <div style="font-weight: 500; margin-top: 0.2rem;">
                            @if($travelDuration)
                                {{ floor($travelDuration / 60) }}h {{ $travelDuration % 60 }}m
                            @else
                                ~{{ $schedule->route->estimated_duration_minutes }} min
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Seats Left</div>
                        <div style="font-weight: 500; margin-top: 0.2rem;">
                            <span class="kbs-badge kbs-badge-{{ $availableSeats > 10 ? 'success' : ($availableSeats > 5 ? 'warning' : 'danger') }}">
                                {{ $availableSeats }} / {{ $schedule->bus->capacity }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Status</div>
                        <div style="margin-top: 0.2rem;">
                            <span class="kbs-badge kbs-badge-{{ $schedule->status === 'in_progress' ? 'success' : 'info' }}">
                                {{ $schedule->liveStatusLabel() }}
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--kbs-border);">
                    <div>
                        <div style="font-size: 0.9rem;">
                            <strong>From:</strong> {{ $originStop->name ?? request('origin_stop_id') }}
                        </div>
                        <div style="font-size: 0.9rem;">
                            <strong>To:</strong> {{ $destStop->name ?? request('destination_stop_id') }}
                        </div>
                    </div>
                    <a href="{{ route('passenger.book', ['schedule' => $schedule, 'origin_stop_id' => request('origin_stop_id'), 'destination_stop_id' => request('destination_stop_id'), 'seats' => request('seats', 1)]) }}" 
                       class="kbs-btn kbs-btn-primary"
                       onclick="event.stopPropagation();">
                        <svg><use href="#icon-ticket"></use></svg>Select Seats
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@elseif(request()->has('origin_stop_id'))
    <div class="kbs-card">
        <strong>No available bus found.</strong>
        <p style="margin:.35rem 0 0;color:var(--kbs-muted)">
            @if(($routePreviews ?? collect())->count() > 0)
                The route exists in the operator setup (shown on the map), but there is no bus schedule with enough available seats for your selected date.
            @else
                The selected pickup and destination are not connected by any operator route.
            @endif
        </p>
    </div>
@endif

@php
    $stopMapData = $stops->map(fn ($stop) => [
        'id' => $stop->id,
        'name' => $stop->name,
        'lat' => (float) $stop->latitude,
        'lng' => (float) $stop->longitude,
    ])->values();
    $routePreviewData = ($routePreviews ?? collect())->values();
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const stops = @json($stopMapData);
    const routePreviews = @json($routePreviewData);

    const map = L.map('searchMap').setView([-1.9441, 30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const originInput = document.getElementById('origin_text');
    const destinationInput = document.getElementById('destination_text');
    const originHidden = document.getElementById('origin_stop_id');
    const destinationHidden = document.getElementById('destination_stop_id');
    const originDatalist = document.getElementById('originStops');
    const destinationDatalist = document.getElementById('destinationStops');
    let routeLine;
    let userMarker;

    function stopById(id) {
        return stops.find(stop => String(stop.id) === String(id));
    }

    function bindDatalist(input, hidden, datalist) {
        input.addEventListener('input', () => {
            const match = Array.from(datalist.options).find(option => option.value === input.value);
            hidden.value = match ? match.dataset.id : '';
            drawSelectedLine();
        });
    }

    function previewRoute(index) {
        const preview = routePreviews[index];
        if (preview) {
            drawSelectedLine(preview.coords, preview.stop_names, preview.map_path || null);
            document.getElementById('location-status').innerText = 
                `Viewing Route: ${preview.route_name}. Select stops on map to set your trip.`;
        }
    }

    function setStop(stop, type) {
        if (type === 'origin') {
            originInput.value = stop.name;
            originHidden.value = stop.id;
        } else {
            destinationInput.value = stop.name;
            destinationHidden.value = stop.id;
        }
        document.getElementById('location-status').innerText = `${type === 'origin' ? 'Origin' : 'Destination'} set to ${stop.name}.`;
        drawSelectedLine();
        map.closePopup();
    }

    let routePathRequestId = 0;

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

    async function drawSelectedLine(coords, stopNames = [], mapPath = null) {
        if (routeLine) routeLine.remove();
        
        if (!coords) {
            const firstResult = document.querySelector('.kbs-route-result');
            if (firstResult) {
                coords = JSON.parse(firstResult.dataset.route);
                mapPath = firstResult.dataset.mapPath ? JSON.parse(firstResult.dataset.mapPath) : null;
            }
        }

        if (!coords || coords.length < 2) {
            document.getElementById('location-status').innerText = 'No valid route path found through bus stops for this selection.';
            return;
        }

        const requestId = ++routePathRequestId;
        routeLine = L.polyline(coords, { color: '#0b5e3c', weight: 5, opacity: .75 }).addTo(map);
        map.fitBounds(routeLine.getBounds(), { padding: [40, 40], maxZoom: 14 });

        const msg = stopNames.length
            ? `Route path: ${stopNames.join(' → ')}`
            : 'Route path loaded through bus stops.';
        document.getElementById('location-status').innerText = msg;

        let roadPath = mapPath;
        if (!roadPath) {
            roadPath = await fetchRoadPath(coords);
        }
        if (requestId === routePathRequestId && roadPath && roadPath.length > 1) {
            routeLine.setLatLngs(roadPath);
            map.fitBounds(routeLine.getBounds(), { padding: [40, 40], maxZoom: 14 });
        }
    }

    stops.forEach(stop => {
        L.circleMarker([stop.lat, stop.lng], {
            radius: 7,
            color: '#0b5e3c',
            fillColor: '#f4b400',
            fillOpacity: .95,
            weight: 2
        }).bindPopup(`
            <strong>${stop.name}</strong><br>
            <button type="button" class="kbs-btn kbs-btn-primary" style="margin-top:.5rem;padding:.35rem .6rem" onclick='setStop(${JSON.stringify(stop)}, "origin")'>Origin</button>
            <button type="button" class="kbs-btn kbs-btn-ghost" style="margin-top:.5rem;padding:.35rem .6rem" onclick='setStop(${JSON.stringify(stop)}, "destination")'>Destination</button>
        `).addTo(map);
    });

    bindDatalist(originInput, originHidden, originDatalist);
    bindDatalist(destinationInput, destinationHidden, destinationDatalist);

    document.querySelectorAll('.kbs-route-result').forEach(card => {
        card.addEventListener('click', () => {
            drawSelectedLine(
                JSON.parse(card.dataset.route),
                JSON.parse(card.dataset.routeStopNames || '[]'),
                card.dataset.mapPath ? JSON.parse(card.dataset.mapPath) : null
            );
            document.querySelectorAll('.kbs-route-result').forEach(c => c.style.borderColor = '');
            card.style.borderColor = 'var(--kbs-primary)';
        });
    });

    const selectedOrigin = stopById(originHidden.value);
    const selectedDestination = stopById(destinationHidden.value);
    if (selectedOrigin) originInput.value = selectedOrigin.name;
    if (selectedDestination) destinationInput.value = selectedDestination.name;

    const firstRoute = document.querySelector('.kbs-route-result');
    if (firstRoute) {
        drawSelectedLine(
            JSON.parse(firstRoute.dataset.route),
            JSON.parse(firstRoute.dataset.routeStopNames || '[]'),
            firstRoute.dataset.mapPath ? JSON.parse(firstRoute.dataset.mapPath) : null
        );
    } else if (routePreviews.length > 0) {
        drawSelectedLine(routePreviews[0].coords, routePreviews[0].stop_names || []);
        document.getElementById('location-status').innerText =
            `Route exists: ${routePreviews[0].route_name}. No scheduled bus with enough seats on selected date.`;
    } else {
        drawSelectedLine();
    }

    function locateUser() {
        if (!navigator.geolocation) {
            document.getElementById('location-status').innerText = 'Geolocation is not supported by this browser.';
            return;
        }

        navigator.geolocation.getCurrentPosition((position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const nearest = stops
                .map(stop => ({ ...stop, distance: Math.hypot(stop.lat - lat, stop.lng - lng) }))
                .sort((a, b) => a.distance - b.distance)[0];

            if (userMarker) userMarker.remove();
            userMarker = L.circleMarker([lat, lng], {
                radius: 8,
                color: '#fff',
                fillColor: '#c0392b',
                fillOpacity: 1,
                weight: 2
            }).bindPopup('You are here').addTo(map);

            if (nearest) setStop(nearest, 'origin');
            map.flyTo([lat, lng], 15);
        }, () => {
            document.getElementById('location-status').innerText = 'Location access was not allowed.';
        });
    }
</script>
@endsection
