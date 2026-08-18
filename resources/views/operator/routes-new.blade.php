@extends('layouts.dashboard')

@push('head')
<style>
#googleMap { width:100%; height:500px; border-radius:10px; border:2px solid var(--kbs-border); margin:1rem 0; }
.route-step { background:var(--kbs-card-bg); border:2px solid var(--kbs-border); border-radius:12px; padding:1.25rem; margin-bottom:1rem; transition:all .24s ease-in-out; }
.route-step.active { border-color:var(--kbs-green); background:var(--kbs-green-light); box-shadow:0 10px 30px rgba(0,0,0,.06); }
.stop-list { max-height:340px; overflow-y:auto; border:1px solid var(--kbs-border); border-radius:12px; padding:.75rem; background:#fff; box-shadow:0 6px 18px rgba(27, 38, 49, 0.05); }
.stop-item { padding:.85rem; border-radius:10px; margin-bottom:.6rem; border:1px solid #e9ecef; display:flex; justify-content:space-between; align-items:center; gap:.75rem; transition:all .2s ease; cursor:pointer; background:#fff; }
.stop-item.selected { background:var(--kbs-green); color:#fff; border-color:var(--kbs-green); }
.stop-item:hover { transform:translateY(-1px); border-color:var(--kbs-green); }
.loading-overlay { position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,.96); display:flex; align-items:center; justify-content:center; border-radius:10px; z-index:1000; }
.spinner { border:4px solid #f3f3f3; border-top:4px solid var(--kbs-green); border-radius:50%; width:40px; height:40px; animation:spin 1s linear infinite; }
@keyframes spin { 0% { transform:rotate(0deg); } 100% { transform:rotate(360deg); } }
.route-map-toolbar { display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
.route-search-field, .route-summary-panel { background:var(--kbs-card-bg); border:1px solid var(--kbs-border); border-radius:12px; padding:1rem; width:100%; }
.route-search-field { flex:2 1 420px; }
.route-summary-panel { flex:1 1 240px; min-width:240px; display:flex; flex-direction:column; gap:.5rem; }
.route-search-field label { display:block; margin-bottom:.5rem; font-weight:600; color:var(--kbs-green-dark); }
.route-search-field input { width:100%; padding:.85rem 1rem; border:1px solid var(--kbs-border); border-radius:10px; background:#fff; }
.autocomplete-suggestions { margin-top:.5rem; border-radius:10px; overflow:hidden; box-shadow:0 18px 50px rgba(0,0,0,.08); }
.suggestion-item, .suggestion-empty { width:100%; text-align:left; border:none; background:#fff; padding:.85rem 1rem; cursor:pointer; font-size:.95rem; color:#1f2937; }
.suggestion-item:hover { background:rgba(26, 115, 82, .08); }
.suggestion-empty { color:#6b7280; }
.route-status { border-radius:10px; padding:.8rem 1rem; font-size:.95rem; }
.route-status-info { background:#eff6ff; color:#1d4ed8; }
.route-status-success { background:#ecfdf5; color:#166534; }
.route-status-warning { background:#fef3c7; color:#92400e; }
.route-status-error { background:#fef2f2; color:#991b1b; }
.card-panel { background:var(--kbs-card-bg); border:1px solid var(--kbs-border); border-radius:12px; padding:1rem; height:100%; display:flex; flex-direction:column; }
.card-panel h4 { margin-bottom:.8rem; }
.card-panel p { margin:0; }
.kbs-btn-sm { font-size:.82rem; padding:.45rem .75rem; }
</style>
@endpush

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
<h1 style="margin-bottom:1.5rem;">Create New Route with Smart Bus Stops</h1>

<div class="kbs-card kbs-form">
    <form id="routeForm" method="POST" action="{{ route('operator.routes.store') }}">
        @csrf
        
        {{-- STEP 1: Basic Route Info --}}
        <div class="route-step active" id="step1">
            <h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">📋 Step 1: Route Information</h3>
            <div class="kbs-grid kbs-grid-3">
                <div>
                    <label>Route Name <span style="color:red;">*</span></label>
                    <input name="name" required placeholder="e.g., Nyabugogo to Kimironko" value="{{ old('name') }}">
                </div>
                <div>
                    <label>Route Code <span style="color:red;">*</span></label>
                    <input name="code" required placeholder="e.g., KBS-101" value="{{ old('code') }}">
                </div>
                <div>
                    <label>Ticket Price (RWF) <span style="color:red;">*</span></label>
                    <input type="number" name="base_price" required min="100" value="{{ old('base_price', 600) }}">
                </div>
            </div>
            <div style="margin-top:1rem;text-align:right;">
                <button type="button" class="kbs-btn kbs-btn-primary" onclick="goToStep(2)">Next: Select Origin & Destination →</button>
            </div>
        </div>

        {{-- STEP 2: Origin & Destination --}}
        <div class="route-step" id="step2" style="display:none;">
            <h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">📍 Step 2: Select Origin & Destination</h3>
            
            <div id="step2Status" class="route-status route-status-info" style="margin-bottom:1rem;">
                Enter location names and press Tab or click outside to geocode them.
            </div>
            
            <div class="kbs-grid kbs-grid-2">
                <div style="position:relative;">
                    <label>Origin (Starting Point) <span style="color:red;">*</span></label>
                    <input id="originInput" type="text" placeholder="e.g., Nyabugogo Bus Terminal" autocomplete="off">
                    <div id="originSuggestions" class="autocomplete-suggestions" style="display:none;"></div>
                    <input type="hidden" name="origin_lat" id="originLat">
                    <input type="hidden" name="origin_lng" id="originLng">
                    <input type="hidden" name="origin_name" id="originName">
                </div>
                <div>
                    <label>Destination (End Point) <span style="color:red;">*</span></label>
                    <input id="destinationInput" type="text" placeholder="e.g., Kimironko Market" autocomplete="off">
                    <div id="destinationSuggestions" class="autocomplete-suggestions" style="display:none;"></div>
                    <input type="hidden" name="destination_lat" id="destinationLat">
                    <input type="hidden" name="destination_lng" id="destinationLng">
                    <input type="hidden" name="destination_name" id="destinationName">
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                <button type="button" class="kbs-btn kbs-btn-ghost" onclick="goToStep(1)">← Back</button>
                <button type="button" class="kbs-btn kbs-btn-primary" onclick="loadRouteAndStops()" id="loadRouteBtn" disabled>Load Route & Detect Bus Stops →</button>
                <button type="button" class="kbs-btn kbs-btn-ghost" onclick="debugInfo()" style="margin-left:auto;">🔍 Debug Info</button>
            </div>
        </div>

        {{-- STEP 3: Smart Bus Stop Selection --}}
        <div class="route-step" id="step3" style="display:none;">
            <h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">🚏 Step 3: Build Route from Map Locations</h3>

            <div class="route-map-toolbar">
                <div class="route-search-field">
                    <label>Search Kigali transport stops, intersections, stations or bus stops</label>
                    <input id="placeSearch" type="search" placeholder="Search route locations..." autocomplete="off">
                    <div id="placeSuggestions" class="autocomplete-suggestions"></div>
                </div>
                <div class="route-summary-panel">
                    <div><strong>Route summary</strong></div>
                    <div id="routeDistance">Distance: —</div>
                    <div id="routeDuration">Estimated time: —</div>
                    <div id="routeMessage" class="route-status route-status-info">Load origin and destination to display route details.</div>
                </div>
            </div>

            <div style="position:relative;">
                <div id="googleMap"></div>
                <div id="mapLoading" class="loading-overlay" style="display:none;">
                    <div>
                        <div class="spinner"></div>
                        <p style="margin-top:1rem;color:var(--kbs-green-dark);font-weight:600;">Loading route and transport locations...</p>
                    </div>
                </div>
            </div>

            <div class="kbs-grid kbs-grid-2" style="margin-top:1rem; gap:1rem;">
                <div class="card-panel">
                    <h4 style="margin:0 0 .75rem;">🔍 Available Map Locations <span id="availableCount" style="color:var(--kbs-muted);font-size:.85rem;">(0)</span></h4>
                    <div class="stop-list" id="availableStops">
                        <p style="color:var(--kbs-muted);text-align:center;padding:1rem;">Select origin and destination to load map locations.</p>
                    </div>
                </div>
                <div class="card-panel">
                    <h4 style="margin:0 0 .75rem;">✅ Selected Stops <span id="selectedCount" style="color:var(--kbs-muted);font-size:.85rem;">(0)</span></h4>
                    <div class="stop-list" id="selectedStops">
                        <p style="color:var(--kbs-muted);text-align:center;padding:1rem;">Click map markers or search to add route stops.</p>
                    </div>
                </div>
            </div>

            <input type="hidden" name="estimated_duration_minutes" id="estimatedDuration" value="30">
            <div id="routeStopsData"></div>

            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;">
                <button type="button" class="kbs-btn kbs-btn-ghost" onclick="goToStep(2)">← Back</button>
                <button type="submit" class="kbs-btn kbs-btn-primary" id="submitBtn" disabled>💾 Save Route with Selected Stops</button>
            </div>
        </div>
    </form>
</div>

<h2 style="margin-top:2rem;">Existing Routes</h2>
<div class="kbs-grid">
@foreach($routes as $route)
    <div class="kbs-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <strong>{{ $route->code }} - {{ $route->name }}</strong>
            <a href="{{ route('operator.routes.edit', $route) }}" class="kbs-btn kbs-btn-ghost" style="padding:.25rem .5rem;font-size:.8rem;">Edit</a>
        </div>
        <p style="margin:.35rem 0;color:var(--kbs-muted);">
            {{ $route->originStop->name }} to {{ $route->destinationStop->name }} - {{ number_format($route->base_price) }} RWF
            @if(!$route->is_active) <span class="kbs-badge kbs-badge-warning">Inactive</span> @endif
        </p>
        <small>{{ $route->stops->count() }} stops</small>
    </div>
@endforeach
</div>

<script>
// Configuration - Replace with your Google Maps API Key
const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '' && !GOOGLE_MAPS_API_KEY.includes('YOUR_API_KEY_HERE');

let map, directionsService, directionsRenderer, placesService, autocompleteService, geocoder;
let originMarker, destinationMarker, snappedRouteOverlay;
let selectedStops = [];
let detectedStops = [];
let stopMarkers = [];
let leafletMap = null;
let leafletRouteLayer = null;
let leafletStopMarkers = [];
let currentRoute = null;
let suggestionTimer = null;
let isMapInitialized = false;

function goToStep(stepNumber) {
    document.querySelectorAll('.route-step').forEach(step => {
        step.style.display = 'none';
        step.classList.remove('active');
    });
    document.getElementById(`step${stepNumber}`).style.display = 'block';
    document.getElementById(`step${stepNumber}`).classList.add('active');
}

async function initGoogleMap() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    const kigaliBounds = new google.maps.LatLngBounds(
        { lat: -1.990, lng: 29.950 },
        { lat: -1.860, lng: 30.150 }
    );

    map = new google.maps.Map(document.getElementById('googleMap'), {
        center: kigaliCenter,
        zoom: 13,
        mapTypeId: 'roadmap',
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
        restriction: {
            latLngBounds: kigaliBounds,
            strictBounds: false
        }
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map,
        suppressMarkers: true,
        preserveViewport: true,
        polylineOptions: {
            strokeColor: '#1a7a4a',
            strokeWeight: 6,
            strokeOpacity: 0.82
        }
    });

    placesService = new google.maps.places.PlacesService(map);
    autocompleteService = new google.maps.places.AutocompleteService();
    geocoder = new google.maps.Geocoder();
    new google.maps.TransitLayer().setMap(map);

    setupAutocomplete('originInput', 'origin');
    setupAutocomplete('destinationInput', 'destination');
    // Attach OpenStreetMap/Nominatim suggestions (autocomplete as you type)
    attachNominatimSuggestions('originInput', 'originSuggestions', 'origin');
    attachNominatimSuggestions('destinationInput', 'destinationSuggestions', 'destination');
    bindPlaceSearch();
}

async function initMap() {
    if (isMapInitialized) return;
    isMapInitialized = true;

    console.log('Initializing map...', HAS_GOOGLE_KEY ? 'Using Google Maps' : 'Using Leaflet fallback');
    
    // Called by Google callback or directly for fallback
    if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && google?.maps) {
        await initGoogleMap();
        setMessage('Google Maps loaded successfully. Ready to create route.', 'success');
        return;
    }

    // Leaflet fallback initialization
    console.log('Loading Leaflet fallback...');
    await ensureLeaflet();
    map = leafletMap; // keep compatibility variable
    setupAutocomplete('originInput', 'origin');
    setupAutocomplete('destinationInput', 'destination');
    attachNominatimSuggestions('originInput', 'originSuggestions', 'origin');
    attachNominatimSuggestions('destinationInput', 'destinationSuggestions', 'destination');
    bindPlaceSearch();
    setMessage('Map loaded (OpenStreetMap). Enter origin and destination to proceed.', 'success');
}

function setupAutocomplete(inputId, type) {
    const input = document.getElementById(inputId);

    // Replace Google Places autocomplete with a simple geocode-on-blur workflow.
    // When the operator finishes typing and blurs the field, attempt to geocode
    // the entered text using the Google Geocoder (if available) and fall back
    // to Nominatim (OpenStreetMap) if the geocoder is not present or fails.
    input.addEventListener('blur', async () => {
        const text = input.value?.trim();
        if (!text) return;

        // Try Google geocoder first (if available)
        if (typeof geocoder !== 'undefined' && geocoder) {
            try {
                geocoder.geocode({ address: text, componentRestrictions: { country: 'RW' } }, (results, status) => {
                    if (status === google.maps.GeocoderStatus.OK && results && results[0]) {
                        const place = results[0];
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        const name = place.formatted_address || text;

                        document.getElementById(`${type}Lat`).value = lat;
                        document.getElementById(`${type}Lng`).value = lng;
                        document.getElementById(`${type}Name`).value = name;

                        updateEndpointMarker(type, { lat, lng, name });
                        checkIfCanLoadRoute();
                        setMessage(`${type === 'origin' ? 'Origin' : 'Destination'} located: ${name}`, 'success');
                        return;
                    }

                    // fall through to nominatim
                    fallbackNominatim(text, type);
                });
                return;
            } catch (e) {
                // ignore and fallback
            }
        }

        // Google geocoder not available or failed -> use Nominatim
        await fallbackNominatim(text, type);
    });

    async function fallbackNominatim(query, type) {
        console.log(`Geocoding "${query}" for ${type} using Nominatim...`);
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=3&countrycodes=rw&q=${encodeURIComponent(query)}&city=Kigali`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(`Nominatim HTTP ${res.status}`);
            const places = await res.json();
            console.log(`Nominatim returned ${places.length} results for "${query}"`);
            
            if (!places || places.length === 0) {
                setMessage(`Location "${query}" not found in Kigali. Try a different name.`, 'warning');
                return;
            }
            const p = places[0];
            const lat = parseFloat(p.lat);
            const lng = parseFloat(p.lon);
            const name = p.display_name || query;

            console.log(`Selected location:`, {name, lat, lng});

            document.getElementById(`${type}Lat`).value = lat;
            document.getElementById(`${type}Lng`).value = lng;
            document.getElementById(`${type}Name`).value = name;

            updateEndpointMarker(type, { lat, lng, name });
            checkIfCanLoadRoute();
            setMessage(`${type === 'origin' ? 'Origin' : 'Destination'} located: ${name}`, 'success');
        } catch (err) {
            console.error('Nominatim geocoding failed:', err);
            setMessage(`Could not locate "${query}". Please try a different name.`, 'error');
        }
    }
}

// --- Nominatim autocomplete suggestions (OpenStreetMap) ---
const suggestTimers = {};
function attachNominatimSuggestions(inputId, suggestionsId, type) {
    const input = document.getElementById(inputId);
    const box = document.getElementById(suggestionsId);
    if (!input || !box) return;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        box.innerHTML = '';
        box.style.display = 'none';
        if (!q || q.length < 2) return;

        if (suggestTimers[inputId]) clearTimeout(suggestTimers[inputId]);
        suggestTimers[inputId] = setTimeout(async () => {
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&limit=6&addressdetails=1&countrycodes=rw&q=${encodeURIComponent(q)}&city=Kigali`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const items = await res.json();
                if (!items || items.length === 0) return;
                box.innerHTML = items.map(i => `<div class="suggestion-item" data-lat="${i.lat}" data-lon="${i.lon}" data-display="${escapeHtml(i.display_name)}">${escapeHtml(i.display_name)}</div>`).join('');
                box.style.display = 'block';
                box.querySelectorAll('.suggestion-item').forEach(el => el.addEventListener('click', () => {
                    const lat = parseFloat(el.dataset.lat);
                    const lon = parseFloat(el.dataset.lon);
                    const name = el.dataset.display;
                    input.value = name;
                    document.getElementById(`${type}Lat`).value = lat;
                    document.getElementById(`${type}Lng`).value = lon;
                    document.getElementById(`${type}Name`).value = name;
                    updateEndpointMarker(type, { lat, lng: lon, name });
                    checkIfCanLoadRoute();
                    setMessage(`${type === 'origin' ? 'Origin' : 'Destination'} selected: ${name}`, 'success');
                    box.style.display = 'none';
                }));
            } catch (e) { /* ignore */ }
        }, 250);
    });

    // hide suggestions when clicking outside
    document.addEventListener('click', (ev) => {
        if (!box.contains(ev.target) && ev.target !== input) box.style.display = 'none';
    });
}

function escapeHtml(text) {
    return (text || '').replace(/[&"'<>]/g, c => ({'&':'&amp;','"':'&quot;','\'':'&#39;','<':'&lt;','>':'&gt;'}[c]));
}

function updateEndpointMarker(type, location) {
    if (typeof google !== 'undefined' && google?.maps) {
        const icon = {
            path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
            scale: 8,
            fillColor: type === 'origin' ? '#2d82ff' : '#ff6f3c',
            fillOpacity: 0.95,
            strokeWeight: 2,
            strokeColor: '#ffffff'
        };

        const marker = new google.maps.Marker({
            map,
            position: location,
            title: `${type === 'origin' ? 'Origin' : 'Destination'}: ${location.name}`,
            icon,
            animation: google.maps.Animation.DROP
        });

        if (type === 'origin') {
            if (originMarker) originMarker.setMap(null);
            originMarker = marker;
        } else {
            if (destinationMarker) destinationMarker.setMap(null);
            destinationMarker = marker;
        }

        if (originMarker && destinationMarker) {
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(originMarker.getPosition());
            bounds.extend(destinationMarker.getPosition());
            map.fitBounds(bounds, 72);
        }
        return;
    }

    // Leaflet fallback
    try {
        if (type === 'origin') {
            if (originMarker && originMarker.remove) originMarker.remove();
            originMarker = L.marker([location.lat, location.lng], { title: location.name }).addTo(leafletMap).bindPopup(location.name);
        } else {
            if (destinationMarker && destinationMarker.remove) destinationMarker.remove();
            destinationMarker = L.marker([location.lat, location.lng], { title: location.name }).addTo(leafletMap).bindPopup(location.name);
        }
        if (originMarker && destinationMarker) {
            const group = L.featureGroup([originMarker, destinationMarker]);
            leafletMap.fitBounds(group.getBounds(), { padding: [40,40] });
        }
    } catch (e) {
        // ignore leaflet errors
    }
}

function checkIfCanLoadRoute() {
    const hasOrigin = document.getElementById('originLat').value && document.getElementById('originLng').value;
    const hasDest = document.getElementById('destinationLat').value && document.getElementById('destinationLng').value;
    const canLoad = hasOrigin && hasDest;
    
    console.log('Checking route prerequisites:', {
        hasOrigin, 
        hasDest, 
        canLoad,
        originData: {
            lat: document.getElementById('originLat').value,
            lng: document.getElementById('originLng').value,
            name: document.getElementById('originName').value
        },
        destData: {
            lat: document.getElementById('destinationLat').value,
            lng: document.getElementById('destinationLng').value,
            name: document.getElementById('destinationName').value
        }
    });
    
    document.getElementById('loadRouteBtn').disabled = !canLoad;
    
    if (canLoad) {
        setMessage('Origin and destination selected. Ready to load route.', 'success');
    }
}

async function loadRouteAndStops() {
    const originLat = parseFloat(document.getElementById('originLat').value);
    const originLng = parseFloat(document.getElementById('originLng').value);
    const destLat = parseFloat(document.getElementById('destinationLat').value);
    const destLng = parseFloat(document.getElementById('destinationLng').value);

    if (!originLat || !originLng || !destLat || !destLng) {
        setMessage('Please select both origin and destination before proceeding.', 'error');
        return;
    }

    console.log('Loading route from', {originLat, originLng}, 'to', {destLat, destLng});

    goToStep(3);
    showLoading(true, 'Building route and loading transport features...');

    try {
        // If Google Directions is available, use it; otherwise fall back to OSRM + Leaflet
        if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && typeof directionsService !== 'undefined') {
            console.log('Using Google Directions API');
            const result = await requestDirections({
                origin: { lat: originLat, lng: originLng },
                destination: { lat: destLat, lng: destLng },
                travelMode: google.maps.TravelMode.DRIVING,
                provideRouteAlternatives: false,
                region: 'rw'
            });

            currentRoute = result.routes[0];
            directionsRenderer.setDirections(result);
            updateRouteSummary(currentRoute);
            setMessage('Route plotted successfully. Select stops on the map or search for locations.', 'success');

            if (originMarker && destinationMarker) {
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(originMarker.getPosition());
                bounds.extend(destinationMarker.getPosition());
                currentRoute.overview_path.forEach(point => bounds.extend(point));
                map.fitBounds(bounds, 64);
            }

            await renderSnappedRoute(currentRoute.overview_path);
            await detectRouteStops(currentRoute);
            seedRouteEndpoints();
            updateUI();
        } else {
            console.log('Using OSRM fallback');
            // Fallback: call OSRM to get the driving route and use Leaflet to draw it
            await ensureLeaflet();
            const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${originLng},${originLat};${destLng},${destLat}?overview=full&geometries=geojson`;
            console.log('Fetching OSRM route:', osrmUrl);
            
            const res = await fetch(osrmUrl);
            if (!res.ok) throw new Error('OSRM route fetch failed: ' + res.status);
            const data = await res.json();
            if (!data.routes || !data.routes.length) throw new Error('No OSRM route returned');
            
            const route = data.routes[0];
            console.log('OSRM route received:', route);
            const coords = route.geometry.coordinates.map(p => [p[1], p[0]]); // [lat,lng]

            // draw route
            if (leafletRouteLayer) {
                leafletRouteLayer.remove();
                leafletRouteLayer = null;
            }
            leafletRouteLayer = L.polyline(coords, { color: '#1a7a4a', weight: 5, opacity: 0.9 }).addTo(leafletMap);
            leafletMap.fitBounds(leafletRouteLayer.getBounds(), { padding: [40,40] });

            // set origin/destination leaflet markers
            if (originMarker && originMarker.remove) originMarker.remove();
            if (destinationMarker && destinationMarker.remove) destinationMarker.remove();
            
            originMarker = L.marker([originLat, originLng]).addTo(leafletMap).bindPopup('Origin: ' + document.getElementById('originName').value);
            destinationMarker = L.marker([destLat, destLng]).addTo(leafletMap).bindPopup('Destination: ' + document.getElementById('destinationName').value);

            // create a pseudo-route object for downstream functions
            currentRoute = { 
                overview_path: coords, 
                legs: [{ 
                    distance: { text: Math.round(route.distance/1000)+' km', value: route.distance }, 
                    duration: { text: Math.round(route.duration/60)+' mins', value: route.duration }, 
                    start_location: { lat: originLat, lng: originLng }, 
                    end_location: { lat: destLat, lng: destLng } 
                }] 
            };
            
            updateRouteSummary(currentRoute);
            setMessage('Route plotted successfully. Detecting nearby bus stops...', 'success');

            await detectRouteStopsFallback(coords, currentRoute);
            seedRouteEndpoints();
            updateUI();
        }
    } catch (error) {
        console.error('Route loading failed:', error);
        setMessage('Unable to calculate route: ' + error.message + '. Please try different locations.', 'error');
    } finally {
        showLoading(false);
    }
}

function requestDirections(request) {
    return new Promise((resolve, reject) => {
        directionsService.route(request, (result, status) => {
            if (status === 'OK' && result?.routes?.length) {
                resolve(result);
            } else {
                reject(status || 'REQUEST_FAILED');
            }
        });
    });
}

async function renderSnappedRoute(path) {
    if (snappedRouteOverlay) {
        snappedRouteOverlay.setMap(null);
        snappedRouteOverlay = null;
    }

    if (!GOOGLE_MAPS_API_KEY || GOOGLE_MAPS_API_KEY.includes('YOUR_API_KEY_HERE')) return;

    const snappedPathUrl = `https://roads.googleapis.com/v1/snapToRoads?interpolate=true&key=${encodeURIComponent(GOOGLE_MAPS_API_KEY)}&path=${encodeURIComponent(path.slice(0, 100).map(p => `${p.lat()},${p.lng()}`).join('|'))}`;
    try {
        const response = await fetch(snappedPathUrl);
        if (!response.ok) return;
        const data = await response.json();
        const snappedPoints = data.snappedPoints?.map(point => ({ lat: point.location.latitude, lng: point.location.longitude })) || [];
        if (snappedPoints.length) {
            snappedRouteOverlay = new google.maps.Polyline({
                path: snappedPoints,
                strokeColor: '#2c3e50',
                strokeOpacity: 0.28,
                strokeWeight: 5,
                icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 4 }, offset: '0', repeat: '18px' }]
            });
            snappedRouteOverlay.setMap(map);
        }
    } catch (error) {
        console.warn('Roads API error', error);
    }
}

async function detectRouteStops(route) {
    detectedStops = [];
    const points = sampleRoutePath(route.overview_path, 18);
    const searchTypes = ['bus_station', 'transit_station', 'train_station', 'subway_station', 'point_of_interest'];
    const keywords = ['bus stop', 'station', 'intersection', 'transit'];

    for (const point of points) {
        for (const type of searchTypes) {
            await fetchNearbyPlaces(point, 260, type, '');
        }
        for (const keyword of keywords) {
            await fetchNearbyPlaces(point, 220, 'point_of_interest', keyword);
        }
    }

    const uniqueStops = Array.from(new Map(detectedStops.map(item => [item.place_id || `${item.lat}-${item.lng}`, item])).values());
    detectedStops = uniqueStops.filter(stop => isAllowedStopLocation(stop, route));
    displayDetectedStops();
    createStopMarkers();
}

function sampleRoutePath(path, maxPoints) {
    if (!path?.length) return [];
    const step = Math.max(1, Math.floor(path.length / maxPoints));
    return path.filter((_, index) => index % step === 0 || index === path.length - 1);
}

function fetchNearbyPlaces(location, radius, type, keyword) {
    return new Promise(resolve => {
        const request = { location, radius, type, keyword, rankBy: google.maps.places.RankBy.PROMINENCE };
        placesService.nearbySearch(request, (results, status) => {
            if (status === google.maps.places.PlacesServiceStatus.OK && Array.isArray(results)) {
                results.forEach(place => {
                    if (!place.geometry || !place.name) return;
                    detectedStops.push({
                        place_id: place.place_id,
                        name: place.name,
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng(),
                        vicinity: place.vicinity || place.formatted_address || '',
                        type: place.types?.[0] || 'transport'
                    });
                });
            }
            resolve();
        });
    });
}

async function ensureLeaflet() {
    if (leafletMap) return;
    // load CSS
    if (!document.querySelector('link[data-leaflet]')) {
        const lcss = document.createElement('link');
        lcss.rel = 'stylesheet';
        lcss.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        lcss.setAttribute('data-leaflet', '1');
        document.head.appendChild(lcss);
    }
    if (!window.L) {
        await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    // create map
    leafletMap = L.map('googleMap').setView([-1.9441,30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(leafletMap);
}

async function detectRouteStopsFallback(coords, route) {
    detectedStops = [];
    if (!coords || !coords.length) return;
    // compute bbox
    const lats = coords.map(c => c[0]);
    const lons = coords.map(c => c[1]);
    const minLat = Math.min(...lats) - 0.01, maxLat = Math.max(...lats) + 0.01;
    const minLon = Math.min(...lons) - 0.01, maxLon = Math.max(...lons) + 0.01;

    const overpassQuery = `[out:json][timeout:25];(node["highway"="bus_stop"](${minLat},${minLon},${maxLat},${maxLon});node["public_transport"="platform"](${minLat},${minLon},${maxLat},${maxLon});node["public_transport"="stop_position"](${minLat},${minLon},${maxLat},${maxLon});node["amenity"="bus_station"](${minLat},${minLon},${maxLat},${maxLon}););out body;`;
    try {
        const res = await fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: overpassQuery, headers: { 'Content-Type': 'text/plain' } });
        if (!res.ok) throw new Error('Overpass error');
        const json = await res.json();
        const nodes = json.elements || [];
        // filter nodes close to route
        for (const node of nodes) {
            const stop = { place_id: 'osm_' + node.id, name: node.tags?.name || 'Bus stop', lat: node.lat, lng: node.lon, vicinity: node.tags?.ref || '' };
            const distanceToPath = distanceToPolylineFallback([stop.lat, stop.lng], coords);
            if (distanceToPath <= 300) {
                detectedStops.push(stop);
            }
        }
        // dedupe
        const unique = Array.from(new Map(detectedStops.map(item => [item.place_id, item])).values());
        detectedStops = unique;
        displayDetectedStops();
        createStopMarkers();
    } catch (e) {
        console.warn('Overpass/OSM detection failed', e);
    }
}

function distanceToPolylineFallback(point, path) {
    // point: [lat,lng], path: [[lat,lng],...]
    if (!path || path.length < 2) return Number.MAX_VALUE;
    let min = Number.MAX_VALUE;
    for (let i=0;i<path.length-1;i++) {
        const a = path[i], b = path[i+1];
        const d = distanceToSegmentFallback(point, a, b);
        if (d < min) min = d;
    }
    return min;
}

function distanceToSegmentFallback(p, a, b) {
    // Haversine-based perpendicular distance to segment in meters
    const toRad = v => v * Math.PI / 180;
    const lat1 = a[0], lon1 = a[1], lat2 = b[0], lon2 = b[1], latP = p[0], lonP = p[1];
    // project onto segment in Cartesian approx
    const x1 = lon1, y1 = lat1, x2 = lon2, y2 = lat2, x0 = lonP, y0 = latP;
    const dx = x2 - x1, dy = y2 - y1;
    const t = dx* (x0 - x1) + dy*(y0 - y1);
    const denom = dx*dx + dy*dy;
    const u = denom === 0 ? 0 : Math.max(0, Math.min(1, t/denom));
    const cx = x1 + u*dx, cy = y1 + u*dy;
    return haversineMeters(cy, cx, y0, x0);
}

function haversineMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) * Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function isAllowedStopLocation(stop, route) {
    if (!route) return false;
    // If Google geometry available, use it for precise checks
    if (typeof google !== 'undefined' && google?.maps && google.maps.geometry) {
        const origin = route.legs[0].start_location;
        const destination = route.legs[0].end_location;
        const point = new google.maps.LatLng(stop.lat, stop.lng);
        const routeDistance = google.maps.geometry.spherical.computeDistanceBetween(origin, destination);
        const distanceToOrigin = google.maps.geometry.spherical.computeDistanceBetween(point, origin);
        const distanceToDestination = google.maps.geometry.spherical.computeDistanceBetween(point, destination);
        const closeToRoute = distanceToPolyline(point, route.overview_path) <= 240;
        return closeToRoute && (distanceToOrigin + distanceToDestination) <= routeDistance * 1.4;
    }

    // Leaflet/OSRM fallback: use haversine and polyline distance helpers
    const origin = route.legs[0].start_location;
    const destination = route.legs[0].end_location;
    const routeDistance = haversineMeters(origin.lat, origin.lng, destination.lat, destination.lng);
    const distanceToOrigin = haversineMeters(stop.lat, stop.lng, origin.lat, origin.lng);
    const distanceToDestination = haversineMeters(stop.lat, stop.lng, destination.lat, destination.lng);
    const closeToRoute = distanceToPolylineFallback([stop.lat, stop.lng], route.overview_path) <= 240;
    return closeToRoute && (distanceToOrigin + distanceToDestination) <= routeDistance * 1.4;
}

function distanceToPolyline(point, path) {
    if (!path?.length) return Number.MAX_VALUE;
    return path.slice(0, -1).reduce((min, current, index) => Math.min(min, distanceToSegment(point, current, path[index + 1])), Number.MAX_VALUE);
}

function distanceToSegment(point, a, b) {
    const start = new google.maps.LatLng(a.lat(), a.lng());
    const end = new google.maps.LatLng(b.lat(), b.lng());
    const segmentLength = google.maps.geometry.spherical.computeDistanceBetween(start, end);
    if (segmentLength === 0) return google.maps.geometry.spherical.computeDistanceBetween(point, start);
    const u = ((point.lng() - a.lng()) * (b.lng() - a.lng()) + (point.lat() - a.lat()) * (b.lat() - a.lat())) / ((b.lng() - a.lng()) ** 2 + (b.lat() - a.lat()) ** 2);
    const clamped = Math.max(0, Math.min(1, u));
    const closest = new google.maps.LatLng(a.lat() + clamped * (b.lat() - a.lat()), a.lng() + clamped * (b.lng() - a.lng()));
    return google.maps.geometry.spherical.computeDistanceBetween(point, closest);
}

function displayDetectedStops() {
    const container = document.getElementById('availableStops');
    document.getElementById('availableCount').textContent = `(${detectedStops.length})`;
    if (!detectedStops.length) {
        container.innerHTML = '<p style="color:var(--kbs-muted);text-align:center;padding:1rem;">No transport locations found along the route.</p>';
        return;
    }

    container.innerHTML = detectedStops.map(stop => `
        <div class="stop-item${isStopSelected(stop) ? ' selected' : ''}" id="stop-${stop.place_id}" onclick="toggleStop('${stop.place_id}')">
            <div>
                <strong>${escapeHtml(stop.name)}</strong>
                <small style="display:block;color:#666;margin-top:.2rem;">${escapeHtml(stop.vicinity)}</small>
            </div>
            <button type="button" class="kbs-btn kbs-btn-sm kbs-btn-ghost">${isStopSelected(stop) ? 'Remove' : 'Add'}</button>
        </div>
    `).join('');
}

function createStopMarkers() {
    // Clear previous markers
    if (typeof google !== 'undefined' && map && google.maps) {
        stopMarkers.forEach(marker => marker.setMap(null));
        stopMarkers = [];
        detectedStops.forEach(stop => {
            const marker = new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng },
                map,
                title: stop.name,
                icon: getMarkerIcon(isStopSelected(stop) ? '#1a7a4a' : '#5f736c', isStopSelected(stop) ? 11 : 8),
                animation: google.maps.Animation.DROP,
                stopData: stop
            });

            marker.addListener('click', () => {
                toggleStop(stop.place_id);
                marker.setAnimation(google.maps.Animation.BOUNCE);
                setTimeout(() => marker.setAnimation(null), 700);
            });

            stopMarkers.push(marker);
        });
    } else {
        // Leaflet fallback
        leafletStopMarkers.forEach(m => m.remove());
        leafletStopMarkers = [];
        detectedStops.forEach(stop => {
            try {
                const m = L.circleMarker([stop.lat, stop.lng], { radius: isStopSelected(stop) ? 8 : 6, color: isStopSelected(stop) ? '#1a7a4a' : '#5f736c', fillColor: '#fff', weight: 2 }).addTo(leafletMap).bindPopup(`<b>${escapeHtml(stop.name)}</b><br>${escapeHtml(stop.vicinity || '')}`);
                m.on('click', () => {
                    toggleStop(stop.place_id);
                });
                m.stopData = stop;
                leafletStopMarkers.push(m);
            } catch (e) { /* ignore */ }
        });
    }
}

function getMarkerIcon(color, scale) {
    return { path: google.maps.SymbolPath.CIRCLE, scale, fillColor: color, fillOpacity: 0.95, strokeColor: '#ffffff', strokeWeight: 2 };
}

function toggleStop(placeId) {
    const stop = detectedStops.find(item => item.place_id === placeId) || selectedStops.find(item => item.place_id === placeId);
    if (!stop) return;

    if (isStopSelected(stop)) {
        selectedStops = selectedStops.filter(item => item.place_id !== placeId);
        setMessage(`${stop.name} removed from route.`, 'info');
    } else {
        if (!currentRoute || !isAllowedStopLocation(stop, currentRoute)) {
            setMessage('This location is not valid for the selected route.', 'error');
            return;
        }
        selectedStops.push({ ...stop, order: selectedStops.length + 1 });
        setMessage(`${stop.name} added to route stops.`, 'success');
    }

    reorderSelectedStops();
    updateUI();
}

function isStopSelected(stop) {
    return selectedStops.some(item => item.place_id === stop.place_id);
}

function reorderSelectedStops() {
    if (!currentRoute) return;
    selectedStops.sort((a, b) => findRoutePositionIndex(a) - findRoutePositionIndex(b));
    selectedStops.forEach((stop, index) => stop.order = index + 1);
}

function findRoutePositionIndex(stop) {
    if (!currentRoute) return 0;
    // If Google geometry available, use it
    if (typeof google !== 'undefined' && google?.maps && google.maps.geometry) {
        const point = new google.maps.LatLng(stop.lat, stop.lng);
        let bestIndex = 0;
        let bestDistance = Number.MAX_VALUE;
        currentRoute.overview_path.forEach((routePoint, index) => {
            const distance = google.maps.geometry.spherical.computeDistanceBetween(point, routePoint);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = index;
            }
        });
        return bestIndex;
    }

    // Fallback: overview_path is array of [lat,lng]
    let bestIndex = 0;
    let bestDistance = Number.MAX_VALUE;
    currentRoute.overview_path.forEach((routePoint, index) => {
        const distance = haversineMeters(stop.lat, stop.lng, routePoint[0], routePoint[1]);
        if (distance < bestDistance) {
            bestDistance = distance;
            bestIndex = index;
        }
    });
    return bestIndex;
}

function updateUI() {
    updateSelectedStopsList();
    updateAvailableStopsList();
    updateStopMarkers();
    updateFormData();
    document.getElementById('submitBtn').disabled = selectedStops.length < 2;
}

function updateSelectedStopsList() {
    const container = document.getElementById('selectedStops');
    document.getElementById('selectedCount').textContent = `(${selectedStops.length})`;
    if (!selectedStops.length) {
        container.innerHTML = '<p style="color:var(--kbs-muted);text-align:center;padding:1rem;">No stops selected yet.</p>';
        return;
    }
    container.innerHTML = selectedStops.map((stop, index) => `
        <div class="stop-item selected">
            <div>
                <strong>${index + 1}. ${escapeHtml(stop.name)}</strong>
                <small style="display:block;color:rgba(255,255,255,.85);margin-top:.2rem;">${escapeHtml(stop.vicinity || 'Selected location')}</small>
            </div>
            <button type="button" class="kbs-btn kbs-btn-sm" onclick="event.stopPropagation();toggleStop('${stop.place_id}')" style="background:rgba(255,255,255,.18);color:#fff;">Remove</button>
        </div>
    `).join('');
}

function updateAvailableStopsList() {
    detectedStops.forEach(stop => {
        const row = document.getElementById(`stop-${stop.place_id}`);
        if (!row) return;
        const selected = isStopSelected(stop);
        row.classList.toggle('selected', selected);
        const button = row.querySelector('button');
        if (button) button.textContent = selected ? 'Remove' : 'Add';
    });
}

function updateStopMarkers() {
    if (typeof google !== 'undefined' && google?.maps) {
        stopMarkers.forEach(marker => {
            const selected = isStopSelected(marker.stopData);
            marker.setIcon(getMarkerIcon(selected ? '#1a7a4a' : '#5f736c', selected ? 11 : 8));
        });
    } else {
        leafletStopMarkers.forEach(m => {
            const selected = isStopSelected(m.stopData);
            try { m.setStyle({ radius: selected ? 8 : 6, color: selected ? '#1a7a4a' : '#5f736c' }); } catch (e) {}
        });
    }
}

function updateFormData() {
    const container = document.getElementById('routeStopsData');
    container.innerHTML = selectedStops.map((stop, index) => `
        <input type="hidden" name="route_stops[${index}][name]" value="${escapeHtml(stop.name)}">
        <input type="hidden" name="route_stops[${index}][latitude]" value="${stop.lat}">
        <input type="hidden" name="route_stops[${index}][longitude]" value="${stop.lng}">
        <input type="hidden" name="route_stops[${index}][code]" value="">
        <input type="hidden" name="route_stops[${index}][district]" value="Kigali">
    `).join('');
}

function seedRouteEndpoints() {
    const origin = {
        place_id: 'origin',
        name: document.getElementById('originName').value,
        lat: parseFloat(document.getElementById('originLat').value),
        lng: parseFloat(document.getElementById('originLng').value),
        vicinity: 'Route origin'
    };
    const destination = {
        place_id: 'destination',
        name: document.getElementById('destinationName').value,
        lat: parseFloat(document.getElementById('destinationLat').value),
        lng: parseFloat(document.getElementById('destinationLng').value),
        vicinity: 'Route destination'
    };
    selectedStops = [origin, destination];
    reorderSelectedStops();
}

function bindPlaceSearch() {
    const input = document.getElementById('placeSearch');
    const suggestions = document.getElementById('placeSuggestions');
    input.addEventListener('input', () => {
        const query = input.value.trim();
        clearTimeout(suggestionTimer);
        suggestions.innerHTML = '';
        if (!query || !currentRoute) return;
        suggestionTimer = setTimeout(async () => {
            // If Google services available, use them; otherwise use Nominatim fallback
            if (typeof autocompleteService !== 'undefined' && autocompleteService && typeof google !== 'undefined') {
                autocompleteService.getQueryPredictions({
                    input: query,
                    componentRestrictions: { country: 'rw' },
                    locationBias: map.getBounds ? map.getBounds() : undefined,
                    types: ['establishment', 'geocode']
                }, (predictions, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK && Array.isArray(predictions)) {
                        suggestions.innerHTML = predictions.slice(0, 6).map(prediction => `
                            <button type="button" class="suggestion-item" onclick="selectSearchPrediction('${prediction.place_id}')">${escapeHtml(prediction.description)}</button>
                        `).join('');
                    } else {
                        suggestions.innerHTML = '<div class="suggestion-empty">No matching transport locations found.</div>';
                    }
                });
                return;
            }

            // Nominatim fallback
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&limit=6&addressdetails=1&countrycodes=rw&q=${encodeURIComponent(query)}&city=Kigali`;
                const res = await fetch(url);
                if (!res.ok) throw new Error('Nominatim failed');
                const items = await res.json();
                if (!items || !items.length) { suggestions.innerHTML = '<div class="suggestion-empty">No matching transport locations found.</div>'; return; }
                suggestions.innerHTML = items.map(i => `<button type="button" class="suggestion-item" data-lat="${i.lat}" data-lon="${i.lon}">${escapeHtml(i.display_name)}</button>`).join('');
                suggestions.querySelectorAll('.suggestion-item').forEach(btn => btn.addEventListener('click', () => {
                    const lat = parseFloat(btn.dataset.lat), lon = parseFloat(btn.dataset.lon), name = btn.textContent;
                    const stop = { place_id: 'nom-' + lat + '-' + lon, name, lat, lng: lon, vicinity: '' };
                    if (!currentRoute || !isAllowedStopLocation(stop, currentRoute)) {
                        setMessage('This location is not along the selected route.', 'error');
                        return;
                    }
                    detectedStops = [stop, ...detectedStops.filter(item => item.place_id !== stop.place_id)];
                    createStopMarkers();
                    displayDetectedStops();
                    toggleStop(stop.place_id);
                    suggestions.innerHTML = '';
                }));
            } catch (e) {
                suggestions.innerHTML = '<div class="suggestion-empty">No matching transport locations found.</div>';
            }
        }, 200);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('#placeSearch') && !event.target.closest('#placeSuggestions')) {
            suggestions.innerHTML = '';
        }
    });
}

function selectSearchPrediction(placeId) {
    document.getElementById('placeSearch').value = '';
    document.getElementById('placeSuggestions').innerHTML = '';
    placesService.getDetails({ placeId, fields: ['place_id', 'geometry', 'name', 'formatted_address', 'types'] }, (place, status) => {
        if (status !== google.maps.places.PlacesServiceStatus.OK || !place?.geometry) {
            setMessage('Unable to load location details.', 'error');
            return;
        }
        const stop = {
            place_id: place.place_id,
            name: place.name,
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng(),
            vicinity: place.formatted_address || '',
            type: place.types?.[0] || 'transport'
        };
        if (!currentRoute || !isAllowedStopLocation(stop, currentRoute)) {
            setMessage('This location is not along the selected route.', 'error');
            return;
        }
        detectedStops = [stop, ...detectedStops.filter(item => item.place_id !== stop.place_id)];
        createStopMarkers();
        displayDetectedStops();
        toggleStop(stop.place_id);
    });
}

function setMessage(text, type = 'info') {
    const label = document.getElementById('routeMessage');
    label.textContent = text;
    label.className = `route-status route-status-${type}`;
}

function showLoading(visible, message = 'Loading map data...') {
    const loader = document.getElementById('mapLoading');
    loader.style.display = visible ? 'flex' : 'none';
    loader.querySelector('p').textContent = message;
}

function updateRouteSummary(route) {
    const distance = route.legs[0].distance?.text || '—';
    const duration = route.legs[0].duration?.text || '—';
    document.getElementById('routeDistance').textContent = `Distance: ${distance}`;
    document.getElementById('routeDuration').textContent = `Estimated time: ${duration}`;
    document.getElementById('estimatedDuration').value = Math.max(1, Math.ceil((route.legs[0].duration?.value || 0) / 60));
}

function debugInfo() {
    const info = {
        googleMapsKey: HAS_GOOGLE_KEY ? 'Configured' : 'Not configured',
        mapInitialized: isMapInitialized,
        mapType: leafletMap ? 'Leaflet' : (map ? 'Google' : 'None'),
        originData: {
            input: document.getElementById('originInput').value,
            lat: document.getElementById('originLat').value,
            lng: document.getElementById('originLng').value,
            name: document.getElementById('originName').value
        },
        destinationData: {
            input: document.getElementById('destinationInput').value,
            lat: document.getElementById('destinationLat').value,
            lng: document.getElementById('destinationLng').value,
            name: document.getElementById('destinationName').value
        },
        currentRoute: currentRoute ? 'Loaded' : 'Not loaded',
        selectedStops: selectedStops.length,
        detectedStops: detectedStops.length
    };
    
    console.log('DEBUG INFO:', info);
    alert('Debug info logged to console. Press F12 to view.');
}

document.getElementById('routeForm').addEventListener('submit', e => {
    if (selectedStops.length < 2) {
        e.preventDefault();
        setMessage('Select origin, destination and stops before saving.', 'error');
    }
});

// Conditionally load Google Maps script only when a real API key is configured.
if (HAS_GOOGLE_KEY) {
    console.log('Loading Google Maps API...');
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places,geometry&callback=initMap`;
    script.async = true;
    script.defer = true;
    script.onerror = () => { 
        console.warn('Google Maps failed to load — falling back to OpenStreetMap/Leaflet'); 
        HAS_GOOGLE_KEY = false;
        initMap(); 
    };
    document.head.appendChild(script);
} else {
    // No Google API key configured — initialize Leaflet fallback immediately
    console.info('No Google Maps API key configured — using OpenStreetMap/Leaflet fallback');
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
}
</script>
@endsection
