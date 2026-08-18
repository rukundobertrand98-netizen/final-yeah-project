@extends('layouts.dashboard')

@push('head')
<style>
#googleMap { width:100%; height:500px; border-radius:8px; border:1px solid var(--kbs-border); margin:1rem 0; }
.origin-destination-section { background:var(--kbs-card-bg); border:2px solid var(--kbs-border); border-radius:8px; padding:1.2rem; margin-bottom:1.5rem; }
.origin-destination-section.has-locations { border-color:var(--kbs-green); background:var(--kbs-green-light); }
.location-input-group { position:relative; margin-bottom:1rem; }
.location-input-group input { width:100%; padding:.8rem; border:1px solid var(--kbs-border); border-radius:6px; }
.location-input-group.filled input { border-color:var(--kbs-green); background:var(--kbs-green-light); }
.autocomplete-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--kbs-border); border-top:none; border-radius:0 0 6px 6px; max-height:200px; overflow-y:auto; z-index:1000; }
.suggestion-item { padding:.8rem; cursor:pointer; border-bottom:1px solid #eee; }
.suggestion-item:hover { background:var(--kbs-green-light); }
.suggestion-item:last-child { border-bottom:none; }
.map-status { padding:.8rem; border-radius:6px; margin:.5rem 0; font-size:.9rem; }
.map-status.success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.map-status.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.map-status.info { background:#eff6ff; color:#1d4ed8; border:1px solid #dbeafe; }
.coordinates-display { font-size:.8rem; color:var(--kbs-muted); margin-top:.3rem; }

/* Bus Stop Management Styles */
.kbs-route-stops { margin-top: 1rem; }
.kbs-route-stop-row { margin-bottom: 1rem; }
.kbs-route-stop-row input[readonly] { 
    background: #f3f4f6 !important; 
    color: #6b7280 !important;
    border-color: #d1d5db !important;
    cursor: not-allowed;
}
.kbs-route-stop-row input.readonly { 
    background: #f3f4f6 !important; 
    color: #6b7280 !important;
    border-color: #d1d5db !important;
    cursor: not-allowed;
}
.kbs-route-stop-row label { 
    display: block; 
    margin-bottom: 0.3rem; 
    font-weight: 500; 
    font-size: 0.9rem; 
}

/* Info window styles for markers */
.gm-style-iw-chr { display: none !important; }
.gm-style-iw-c { border-radius: 8px; padding: 0; }
.gm-style-iw-d { overflow: hidden !important; }

/* Custom Leaflet marker styles */
.custom-marker { 
    background: transparent !important; 
    border: none !important;
}

.custom-bus-stop-marker { 
    background: transparent !important; 
    border: none !important;
}
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
<h1>Manage Routes</h1>

<div class="kbs-card kbs-form" style="margin-bottom:1.5rem">
    <form id="routeForm" method="POST" action="{{ route('operator.routes.store') }}">
        @csrf
        {{-- Route Basic Information --}}
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
        </div>

        <div class="kbs-grid kbs-grid-2" style="margin-top:1rem;">
            <div>
                <label>Estimated Duration (minutes)</label>
                <input type="number" name="estimated_duration_minutes" required min="1" value="{{ old('estimated_duration_minutes', 45) }}">
            </div>
            <div style="display:flex;align-items:end;gap:0.5rem;">
                <div id="map-status" class="map-status info" style="flex:1;">
                    Select origin and destination to see route on map
                </div>
                <button type="button" onclick="debugMapStatus()" class="kbs-btn kbs-btn-ghost" style="padding:0.5rem;">
                    🐛 Debug
                </button>
            </div>
        </div>

        {{-- Origin and Destination Selection --}}
        <div class="origin-destination-section" id="originDestSection">
            <h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">📍 Route Origin & Destination</h3>
            
            <div class="kbs-grid kbs-grid-2">
                <div class="location-input-group" id="originGroup">
                    <label>Route Origin (Starting Point) <span style="color:red;">*</span></label>
                    <input type="text" id="originInput" placeholder="e.g., Nyabugogo Bus Terminal" autocomplete="off">
                    <div id="originSuggestions" class="autocomplete-suggestions" style="display:none;"></div>
                    <div class="coordinates-display" id="originCoords">Click suggestions to select location</div>
                    
                    {{-- Hidden fields for form submission --}}
                    <input type="hidden" name="origin_name" id="originName">
                    <input type="hidden" name="origin_lat" id="originLat">
                    <input type="hidden" name="origin_lng" id="originLng">
                </div>
                
                <div class="location-input-group" id="destinationGroup">
                    <label>Route Destination (End Point) <span style="color:red;">*</span></label>
                    <input type="text" id="destinationInput" placeholder="e.g., Kimironko Market" autocomplete="off">
                    <div id="destinationSuggestions" class="autocomplete-suggestions" style="display:none;"></div>
                    <div class="coordinates-display" id="destinationCoords">Click suggestions to select location</div>
                    
                    {{-- Hidden fields for form submission --}}
                    <input type="hidden" name="destination_name" id="destinationName">
                    <input type="hidden" name="destination_lat" id="destinationLat">
                    <input type="hidden" name="destination_lng" id="destinationLng">
                </div>
            </div>
        </div>

        {{-- Google Maps Display --}}
        <div id="googleMap"></div>

        {{-- Bus Stops Section --}}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1.5rem">
            <h3 style="margin:0">Bus Stops Along Route</h3>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="button" class="kbs-btn kbs-btn-secondary" onclick="loadBusStopsOnMap()" id="loadStopsBtn" disabled>
                    <svg><use href="#icon-map"></use></svg>Show Bus Stops on Map
                </button>
                <button type="button" class="kbs-btn kbs-btn-ghost" onclick="addStopRow()" id="addStopBtn" disabled>
                    <svg><use href="#icon-plus"></use></svg>Add Stop Manually
                </button>
            </div>
        </div>

        {{-- Bus Stops Instructions --}}
        <div id="busStopsInstructions" class="map-status info" style="display:none;">
            Click "Show Bus Stops on Map" to see all available bus stops in Kigali. Then click any blue marker on the map to add that bus stop to your route, or use "Add Stop Manually" to enter custom locations.
        </div>

        <div id="routeStops" class="kbs-route-stops"></div>

        <button class="kbs-btn kbs-btn-primary" id="saveRouteBtn" disabled style="margin-top:1rem;">
            <svg><use href="#icon-bus"></use></svg>Save Route
        </button>
    </form>
</div>

<h2>Active Routes</h2>
<div class="kbs-grid">
@foreach($routes as $route)
    @php
        $routeMapPoints = $route->stops->map(fn ($stop) => [(float) $stop->latitude, (float) $stop->longitude])->values();
    @endphp
    <div class="kbs-card kbs-route-card" data-route='@json($routeMapPoints)'>
        <div style="display:flex;justify-content:space-between;align-items:start">
            <strong>{{ $route->code }} - {{ $route->name }}</strong>
            <a href="{{ route('operator.routes.edit', $route) }}" class="kbs-btn kbs-btn-ghost" style="padding:.25rem .5rem;font-size:.8rem"><svg><use href="#icon-edit"></use></svg>Edit</a>
        </div>
        <p style="margin:.35rem 0;color:var(--kbs-muted)">
            {{ $route->originStop->name }} to {{ $route->destinationStop->name }}
            @if($route->base_price)
                - {{ number_format($route->base_price) }} RWF
            @endif
            - {{ $route->estimated_duration_minutes }} min
            @if(!$route->is_active) <span class="kbs-badge kbs-badge-warning">Inactive</span> @endif
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

<script>
// Configuration
const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '';

// Global variables
let map, directionsService, directionsRenderer, geocoder, placesService;
let originMarker, destinationMarker;
let routePath = null;
let markers = [];
let busStopMarkers = [];
let selectedStops = [];
let autocompleteTimer = null;
let isMapReady = false;
let busStopsLoaded = false;

const oldStops = @json($oldStops);

// Initialize the map
async function initMap() {
    console.log('🗺️ Initializing map...', HAS_GOOGLE_KEY ? 'Google Maps' : 'Leaflet fallback');
    
    try {
        if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && google?.maps) {
            await initGoogleMap();
            console.log('✅ Google Maps initialized successfully');
        } else {
            await initLeafletFallback();
            console.log('✅ Leaflet fallback initialized successfully');
        }
        
        // Set up autocomplete for origin and destination
        setupLocationAutocomplete();
        console.log('🔍 Location autocomplete setup complete');
        
        // Load old stops if any
        if (oldStops && oldStops.length > 0 && oldStops[0].name) {
            console.log('📍 Loading existing stops:', oldStops.length);
            oldStops.forEach(stop => addStopRow(stop));
        }
        
        isMapReady = true;
        updateMapStatus('Map ready. Enter origin and destination to start creating route.', 'info');
    } catch (error) {
        console.error('❌ Map initialization failed:', error);
        updateMapStatus('Map initialization failed. Please refresh the page.', 'error');
    }
}

// Delegated handler for "Add to Route" buttons rendered inside info windows
document.addEventListener('click', function (e) {
    const btn = e.target.closest && e.target.closest('.add-to-route-btn');
    if (!btn) return;

    const placeId = btn.dataset.placeId;
    const name = btn.dataset.name;
    const lat = parseFloat(btn.dataset.lat);
    const lng = parseFloat(btn.dataset.lng);

    if (!placeId || !name || Number.isNaN(lat) || Number.isNaN(lng)) {
        updateMapStatus('Could not read stop data from the map control. Try again.', 'error');
        return;
    }

    addBusStopFromMarker(placeId, name, lat, lng);
});

// Initialize Google Maps
async function initGoogleMap() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    
    map = new google.maps.Map(document.getElementById('googleMap'), {
        zoom: 13,
        center: kigaliCenter,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
        restriction: {
            latLngBounds: {
                north: -1.860,
                south: -1.990,
                east: 30.150,
                west: 29.950
            }
        }
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#16a34a', // Green color for route line
            strokeWeight: 6,
            strokeOpacity: 0.8
        }
    });

    geocoder = new google.maps.Geocoder();
    placesService = new google.maps.places.PlacesService(map);
}

// Fallback to Leaflet if Google Maps not available
async function initLeafletFallback() {
    if (!document.querySelector('link[href*="leaflet"]')) {
        const leafletCSS = document.createElement('link');
        leafletCSS.rel = 'stylesheet';
        leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(leafletCSS);
    }

    if (!window.L) {
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    map = L.map('googleMap').setView([-1.9441, 30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

// Setup location autocomplete
function setupLocationAutocomplete() {
    setupLocationInput('originInput', 'originSuggestions', 'origin');
    setupLocationInput('destinationInput', 'destinationSuggestions', 'destination');
}

function setupLocationInput(inputId, suggestionsId, type) {
    const input = document.getElementById(inputId);
    const suggestionsDiv = document.getElementById(suggestionsId);
    
    if (!input || !suggestionsDiv) {
        console.error(`❌ Could not find elements: ${inputId} or ${suggestionsId}`);
        return;
    }
    
    console.log(`🔧 Setting up ${type} input:`, inputId);
    
    input.addEventListener('input', function() {
        const query = this.value.trim();
        console.log(`🔍 ${type} input changed:`, query);
        
        clearTimeout(autocompleteTimer);
        suggestionsDiv.innerHTML = '';
        suggestionsDiv.style.display = 'none';
        
        // Clear previous coordinates when user types new text
        document.getElementById(type + 'Lat').value = '';
        document.getElementById(type + 'Lng').value = '';
        document.getElementById(type + 'Name').value = '';
        document.getElementById(type + 'Coords').textContent = 'Click suggestions to select location';
        document.getElementById(type + 'Group').classList.remove('filled');
        
        if (query.length < 3) {
            console.log(`⏳ Query too short for ${type}, need at least 3 characters`);
            return;
        }
        
        console.log(`⏱️ Starting search timer for ${type}: "${query}"`);
        autocompleteTimer = setTimeout(() => {
            console.log(`🚀 Executing search for ${type}: "${query}"`);
            searchLocations(query, suggestionsDiv, type);
        }, 500);
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest(`#${inputId}`) && !e.target.closest(`#${suggestionsId}`)) {
            suggestionsDiv.style.display = 'none';
        }
    });
}

async function searchLocations(query, suggestionsDiv, type) {
    console.log(`🔍 Searching locations for ${type}: "${query}"`);
    
    try {
        if (HAS_GOOGLE_KEY && geocoder) {
            console.log('📡 Using Google Geocoding API');
            geocoder.geocode({
                address: query + ', Kigali, Rwanda',
                componentRestrictions: { country: 'RW' }
            }, function(results, status) {
                console.log(`📊 Google geocoding result for ${type}:`, status, results?.length || 0, 'results');
                
                if (status === 'OK' && results.length > 0) {
                    displaySuggestions(results.slice(0, 5), suggestionsDiv, type, 'google');
                } else {
                    console.log('🔄 Google geocoding failed, falling back to Nominatim');
                    fallbackToNominatim(query, suggestionsDiv, type);
                }
            });
        } else {
            console.log('🌍 Using Nominatim (OpenStreetMap) geocoding');
            fallbackToNominatim(query, suggestionsDiv, type);
        }
    } catch (error) {
        console.error(`❌ Location search failed for ${type}:`, error);
        fallbackToNominatim(query, suggestionsDiv, type);
    }
}

async function fallbackToNominatim(query, suggestionsDiv, type) {
    console.log(`🌍 Nominatim search for ${type}: "${query}"`);
    
    try {
        const url = `https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=rw&q=${encodeURIComponent(query)}&addressdetails=1`;
        console.log(`📡 Fetching from Nominatim:`, url);
        
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'KBS-Bus-System/1.0'
            }
        });
        
        console.log(`📊 Nominatim response status:`, response.status);
        
        if (response.ok) {
            const results = await response.json();
            console.log(`📍 Nominatim results for ${type}:`, results.length, 'locations found');
            displaySuggestions(results, suggestionsDiv, type, 'nominatim');
        } else {
            console.error(`❌ Nominatim request failed:`, response.status, response.statusText);
            showNoResults(suggestionsDiv, `Search failed (${response.status}). Please try again.`);
        }
    } catch (error) {
        console.error(`❌ Nominatim search failed for ${type}:`, error);
        showNoResults(suggestionsDiv, 'Search failed. Please check your internet connection.');
    }
}

function showNoResults(suggestionsDiv, message) {
    suggestionsDiv.innerHTML = `<div class="suggestion-item" style="color:#666;">${message}</div>`;
    suggestionsDiv.style.display = 'block';
}

function displaySuggestions(results, suggestionsDiv, type, source) {
    console.log(`📋 Displaying ${results.length} suggestions for ${type} from ${source}`);
    
    if (results.length === 0) {
        showNoResults(suggestionsDiv, 'No locations found in Kigali area');
        return;
    }
    
    let html = '';
    
    results.forEach((result, index) => {
        let name, address, lat, lng;
        
        if (source === 'google') {
            name = result.formatted_address;
            address = result.formatted_address;
            lat = result.geometry.location.lat();
            lng = result.geometry.location.lng();
        } else { // nominatim
            name = result.display_name;
            address = result.display_name;
            lat = parseFloat(result.lat);
            lng = parseFloat(result.lon);
        }
        
        // Clean up the display name
        const shortName = name.split(',').slice(0, 2).join(', ');
        
        html += `
            <div class="suggestion-item" 
                 onclick="selectLocation('${type}', '${escapeHtml(shortName)}', ${lat}, ${lng})"
                 style="padding:0.8rem;border-bottom:1px solid #eee;cursor:pointer;">
                <strong>${escapeHtml(shortName)}</strong>
                <div style="font-size:0.8rem;color:#666;margin-top:0.2rem;">${escapeHtml(address)}</div>
            </div>
        `;
        
        console.log(`📍 Suggestion ${index + 1}:`, shortName, `(${lat}, ${lng})`);
    });
    
    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = 'block';
    
    console.log(`✅ Suggestions displayed for ${type}`);
}

function selectLocation(type, name, lat, lng) {
    console.log(`🎯 Selecting ${type} location:`, name, `(${lat}, ${lng})`);
    
    // Validate coordinates
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
        console.error(`❌ Invalid coordinates for ${type}:`, lat, lng);
        updateMapStatus(`Invalid location coordinates for ${type}. Please try another location.`, 'error');
        return;
    }
    
    // Update input field
    const inputElement = document.getElementById(type + 'Input');
    if (inputElement) {
        inputElement.value = name;
        console.log(`✅ Updated ${type} input field`);
    } else {
        console.error(`❌ Could not find ${type}Input element`);
    }
    
    // Update hidden fields
    document.getElementById(type + 'Name').value = name;
    document.getElementById(type + 'Lat').value = lat;
    document.getElementById(type + 'Lng').value = lng;
    console.log(`✅ Updated ${type} hidden fields`);
    
    // Update coordinates display
    const coordsElement = document.getElementById(type + 'Coords');
    if (coordsElement) {
        coordsElement.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        console.log(`✅ Updated ${type} coordinates display`);
    }
    
    // Mark input group as filled
    const groupElement = document.getElementById(type + 'Group');
    if (groupElement) {
        groupElement.classList.add('filled');
        console.log(`✅ Marked ${type} group as filled`);
    }
    
    // Hide suggestions
    const suggestionsElement = document.getElementById(type + 'Suggestions');
    if (suggestionsElement) {
        suggestionsElement.style.display = 'none';
        console.log(`✅ Hidden ${type} suggestions`);
    }
    
    // Update marker on map
    try {
        updateLocationMarker(type, { lat, lng, name });
        console.log(`✅ Updated ${type} marker on map`);
    } catch (error) {
        console.error(`❌ Failed to update ${type} marker:`, error);
    }
    
    // Check if we can draw route
    try {
        checkAndDrawRoute();
        console.log(`✅ Checked route drawing capability`);
    } catch (error) {
        console.error(`❌ Failed to check route:`, error);
    }
    
    updateMapStatus(`${type === 'origin' ? 'Origin' : 'Destination'} set: ${name}`, 'success');
}

function updateLocationMarker(type, location) {
    if (HAS_GOOGLE_KEY && google?.maps) {
        if (type === 'origin' && originMarker) {
            originMarker.setMap(null);
        }
        if (type === 'destination' && destinationMarker) {
            destinationMarker.setMap(null);
        }
        
        const marker = new google.maps.Marker({
            position: { lat: location.lat, lng: location.lng },
            map: map,
            title: location.name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 12,
                fillColor: type === 'origin' ? '#fbbf24' : '#dc2626', // Yellow for origin, Red for destination
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            }
        });
        
        // Add info window for origin/destination markers
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div style="padding: 8px; text-align: center;">
                    <strong style="color: ${type === 'origin' ? '#d97706' : '#dc2626'};">
                        ${type === 'origin' ? '🟡 ORIGIN' : '🔴 DESTINATION'}
                    </strong><br>
                    <div style="margin: 4px 0; font-weight: 500;">${location.name}</div>
                    <small style="color: #666;">${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}</small>
                </div>
            `
        });
        
        marker.addListener('click', () => {
            infoWindow.open(map, marker);
        });
        
        if (type === 'origin') {
            originMarker = marker;
        } else {
            destinationMarker = marker;
        }
    } else {
        // Leaflet implementation
        if (type === 'origin' && originMarker) {
            map.removeLayer(originMarker);
        }
        if (type === 'destination' && destinationMarker) {
            map.removeLayer(destinationMarker);
        }
        
        // Create custom icon for Leaflet
        const iconColor = type === 'origin' ? '#fbbf24' : '#dc2626';
        const iconHtml = `
            <div style="
                width: 24px; 
                height: 24px; 
                background-color: ${iconColor}; 
                border: 3px solid #ffffff; 
                border-radius: 50%; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            "></div>
        `;
        
        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-marker',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        
        const marker = L.marker([location.lat, location.lng], { icon: customIcon })
            .addTo(map)
            .bindPopup(`
                <div style="text-align: center; padding: 4px;">
                    <strong style="color: ${type === 'origin' ? '#d97706' : '#dc2626'};">
                        ${type === 'origin' ? '🟡 ORIGIN' : '🔴 DESTINATION'}
                    </strong><br>
                    <div style="margin: 4px 0; font-weight: 500;">${location.name}</div>
                    <small style="color: #666;">${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}</small>
                </div>
            `);
        
        if (type === 'origin') {
            originMarker = marker;
        } else {
            destinationMarker = marker;
        }
    }
}

function checkAndDrawRoute() {
    const hasOrigin = document.getElementById('originLat').value && document.getElementById('originLng').value;
    const hasDest = document.getElementById('destinationLat').value && document.getElementById('destinationLng').value;
    
    if (hasOrigin && hasDest) {
        drawRoute();
        document.getElementById('originDestSection').classList.add('has-locations');
        document.getElementById('addStopBtn').disabled = false;
        document.getElementById('loadStopsBtn').disabled = false;
        document.getElementById('saveRouteBtn').disabled = false;
        document.getElementById('busStopsInstructions').style.display = 'block';
    }
}

async function drawRoute() {
    const originLat = parseFloat(document.getElementById('originLat').value);
    const originLng = parseFloat(document.getElementById('originLng').value);
    const destLat = parseFloat(document.getElementById('destinationLat').value);
    const destLng = parseFloat(document.getElementById('destinationLng').value);
    
    if (HAS_GOOGLE_KEY && directionsService) {
        const request = {
            origin: { lat: originLat, lng: originLng },
            destination: { lat: destLat, lng: destLng },
            travelMode: google.maps.TravelMode.DRIVING
        };
        
        directionsService.route(request, function(result, status) {
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                routePath = result.routes[0];
                
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(originMarker.getPosition());
                bounds.extend(destinationMarker.getPosition());
                map.fitBounds(bounds);
                
                updateMapStatus('Route calculated successfully. You can now add bus stops.', 'success');
            } else {
                updateMapStatus('Could not calculate route. Please try different locations.', 'error');
            }
        });
    } else {
        try {
            const response = await fetch(
                `https://router.project-osrm.org/route/v1/driving/${originLng},${originLat};${destLng},${destLat}?overview=full&geometries=geojson`
            );
            
            if (response.ok) {
                const data = await response.json();
                if (data.routes && data.routes[0]) {
                    const coords = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                    
                    if (routePath) {
                        map.removeLayer(routePath);
                    }
                    
                    routePath = L.polyline(coords, {
                        color: '#1a7a4a',
                        weight: 6,
                        opacity: 0.8
                    }).addTo(map);
                    
                    const group = L.featureGroup([originMarker, destinationMarker, routePath]);
                    map.fitBounds(group.getBounds(), { padding: [20, 20] });
                    
                    updateMapStatus('Route calculated successfully. You can now add bus stops.', 'success');
                }
            }
        } catch (error) {
            console.error('OSRM routing failed:', error);
            updateMapStatus('Could not calculate route. Please check your internet connection.', 'error');
        }
    }
}

function updateMapStatus(message, type) {
    const statusDiv = document.getElementById('map-status');
    statusDiv.textContent = message;
    statusDiv.className = `map-status ${type}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load and display bus stops on the map
async function loadBusStopsOnMap() {
    if (busStopsLoaded) {
        updateMapStatus('Bus stops already loaded. Click any blue marker to add stops.', 'info');
        return;
    }
    
    console.log('🚏 Loading bus stops on map...');
    updateMapStatus('Loading bus stops in Kigali area...', 'info');
    
    try {
        if (HAS_GOOGLE_KEY && placesService) {
            await loadGooglePlacesBusStops();
        } else {
            await loadOverpassBusStops();
        }
        
        busStopsLoaded = true;
        updateMapStatus(`${busStopMarkers.length} bus stops loaded. Click any blue marker to add to route.`, 'success');
    } catch (error) {
        console.error('❌ Failed to load bus stops:', error);
        updateMapStatus('Failed to load bus stops. Please try again.', 'error');
    }
}

// Load bus stops using Google Places API
async function loadGooglePlacesBusStops() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    
    const request = {
        location: kigaliCenter,
        radius: 15000, // 15km radius to cover Kigali
        types: ['bus_station', 'transit_station', 'subway_station'],
        fields: ['name', 'geometry', 'place_id', 'types', 'vicinity']
    };
    
    return new Promise((resolve, reject) => {
        placesService.nearbySearch(request, (results, status, pagination) => {
            if (status === google.maps.places.PlacesServiceStatus.OK) {
                console.log(`📍 Found ${results.length} transit places from Google Places`);
                
                results.forEach(place => {
                    const marker = new google.maps.Marker({
                        position: place.geometry.location,
                        map: map,
                        title: place.name,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: '#3b82f6',
                            fillOpacity: 0.8,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        }
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 8px;">
                                <strong>${place.name}</strong><br>
                                <small>${place.vicinity || 'Kigali, Rwanda'}</small><br>
                                <button class="add-to-route-btn"
                                        data-place-id="${place.place_id}"
                                        data-name="${escapeHtml(place.name)}"
                                        data-lat="${place.geometry.location.lat()}"
                                        data-lng="${place.geometry.location.lng()}"
                                        style="margin-top: 8px; padding: 4px 8px; background: #1a7a4a; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Add to Route
                                </button>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        // Close any open info windows
                        busStopMarkers.forEach(m => m.infoWindow && m.infoWindow.close());
                        infoWindow.open(map, marker);
                    });
                    
                    marker.infoWindow = infoWindow;
                    busStopMarkers.push(marker);
                });
                
                // Load additional transit-related places
                loadAdditionalGooglePlaces().then(() => resolve()).catch(reject);
            } else {
                console.warn('Google Places search failed:', status);
                reject(new Error(`Google Places failed: ${status}`));
            }
        });
    });
}

// Load additional places like markets, schools, hospitals that often serve as bus stops
async function loadAdditionalGooglePlaces() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    const additionalTypes = [
        { type: 'shopping_mall', name: 'Shopping Centers' },
        { type: 'hospital', name: 'Hospitals' },
        { type: 'university', name: 'Universities' },
        { type: 'school', name: 'Schools' },
        { type: 'bank', name: 'Banks' },
        { type: 'government', name: 'Government Buildings' }
    ];
    
    for (const placeType of additionalTypes) {
        const request = {
            location: kigaliCenter,
            radius: 12000,
            types: [placeType.type],
            fields: ['name', 'geometry', 'place_id', 'vicinity']
        };
        
        await new Promise((resolve) => {
            placesService.nearbySearch(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK && results.length > 0) {
                    console.log(`📍 Found ${results.length} ${placeType.name}`);
                    
                    // Limit results to avoid too many markers
                    results.slice(0, 10).forEach(place => {
                        const marker = new google.maps.Marker({
                            position: place.geometry.location,
                            map: map,
                            title: place.name,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 6,
                                fillColor: '#06b6d4',
                                fillOpacity: 0.7,
                                strokeColor: '#ffffff',
                                strokeWeight: 2
                            }
                        });
                        
                        const infoWindow = new google.maps.InfoWindow({
                            content: `
                                <div style="padding: 8px;">
                                    <strong>${place.name}</strong><br>
                                    <small>${place.vicinity || 'Kigali, Rwanda'}</small><br>
                                    <em>${placeType.name}</em><br>
                                    <button class="add-to-route-btn"
                                            data-place-id="${place.place_id}"
                                            data-name="${escapeHtml(place.name)}"
                                            data-lat="${place.geometry.location.lat()}"
                                            data-lng="${place.geometry.location.lng()}"
                                            style="margin-top: 8px; padding: 4px 8px; background: #1a7a4a; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                        Add to Route
                                    </button>
                                </div>
                            `
                        });
                        
                        marker.addListener('click', () => {
                            busStopMarkers.forEach(m => m.infoWindow && m.infoWindow.close());
                            infoWindow.open(map, marker);
                        });
                        
                        marker.infoWindow = infoWindow;
                        busStopMarkers.push(marker);
                    });
                }
                resolve();
            });
        });
        
        // Small delay between requests to be polite to the API
        await new Promise(resolve => setTimeout(resolve, 200));
    }
}

// Fallback: Load bus stops using Overpass API (OpenStreetMap)
async function loadOverpassBusStops() {
    const kigaliBounds = {
        south: -1.990,
        west: 29.950,
        north: -1.860,
        east: 30.150
    };
    
    const overpassQuery = `
        [out:json][timeout:25];
        (
          node["highway"="bus_stop"](${kigaliBounds.south},${kigaliBounds.west},${kigaliBounds.north},${kigaliBounds.east});
          node["public_transport"="stop_position"](${kigaliBounds.south},${kigaliBounds.west},${kigaliBounds.north},${kigaliBounds.east});
          node["amenity"="bus_station"](${kigaliBounds.south},${kigaliBounds.west},${kigaliBounds.north},${kigaliBounds.east});
          node["public_transport"="station"](${kigaliBounds.south},${kigaliBounds.west},${kigaliBounds.north},${kigaliBounds.east});
        );
        out geom;
    `;
    
    try {
        const response = await fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `data=${encodeURIComponent(overpassQuery)}`
        });
        
        if (response.ok) {
            const data = await response.json();
            console.log(`📍 Found ${data.elements.length} bus stops from OpenStreetMap`);
            
            data.elements.forEach(stop => {
                const name = stop.tags?.name || stop.tags?.ref || `Bus Stop (ID: ${stop.id})`;
                const lat = stop.lat;
                const lng = stop.lon;
                
                if (HAS_GOOGLE_KEY && google?.maps) {
                    const marker = new google.maps.Marker({
                        position: { lat, lng },
                        map: map,
                        title: name,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: '#3b82f6',
                            fillOpacity: 0.8,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        }
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 8px;">
                                <strong>${escapeHtml(name)}</strong><br>
                                <small>Bus Stop</small><br>
                                <button class="add-to-route-btn"
                                        data-place-id="osm_${stop.id}"
                                        data-name="${escapeHtml(name)}"
                                        data-lat="${lat}"
                                        data-lng="${lng}"
                                        style="margin-top: 8px; padding: 4px 8px; background: #1a7a4a; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Add to Route
                                </button>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        busStopMarkers.forEach(m => m.infoWindow && m.infoWindow.close());
                        infoWindow.open(map, marker);
                    });
                    
                    marker.infoWindow = infoWindow;
                    busStopMarkers.push(marker);
                } else {
                    // Leaflet marker
                    const marker = L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(`
                            <div style="padding: 8px;">
                                <strong>${escapeHtml(name)}</strong><br>
                                <small>Bus Stop</small><br>
                                <button class="add-to-route-btn"
                                        data-place-id="osm_${stop.id}"
                                        data-name="${escapeHtml(name)}"
                                        data-lat="${lat}"
                                        data-lng="${lng}"
                                        style="margin-top: 8px; padding: 4px 8px; background: #1a7a4a; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Add to Route
                                </button>
                            </div>
                        `);
                    
                    busStopMarkers.push(marker);
                }
            });
        }
    } catch (error) {
        console.error('Overpass API failed:', error);
        throw error;
    }
}

// Add bus stop from map marker
function addBusStopFromMarker(placeId, name, lat, lng) {
    // Ensure numeric coordinates
    lat = parseFloat(lat);
    lng = parseFloat(lng);

    // Check if this stop is already added (tolerate small float differences)
    const EPS = 0.00001;
    const existingStop = selectedStops.find(stop => {
        const sLat = parseFloat(stop.lat);
        const sLng = parseFloat(stop.lng);
        const sameCoords = !Number.isNaN(sLat) && !Number.isNaN(sLng) && Math.abs(sLat - lat) <= EPS && Math.abs(sLng - lng) <= EPS;
        return sameCoords || stop.id === placeId;
    });
    if (existingStop) {
        updateMapStatus(`"${name}" is already added to the route.`, 'error');
        return;
    }
    
    // Check if the stop is along the route path
    if (!isStopAlongRoute(lat, lng)) {
        const confirmed = confirm(`"${name}" appears to be far from your route path. Add it anyway?`);
        if (!confirmed) return;
    }
    
    // Generate route-specific stop code
    const routeCode = document.querySelector('input[name="code"]')?.value || 'ROUTE';
    const stopCode = generateStopCode(name, routeCode, selectedStops.length + 1);
    
    const stopData = {
        id: placeId,
        name: name,
        code: stopCode,
        district: 'Kigali',
        lat: lat,
        lng: lng,
        sequence: selectedStops.length + 1
    };
    
    selectedStops.push(stopData);
    addStopRow(stopData);
    
    // Change marker color to gray to indicate it's selected
    changeMarkerColor(placeId, lat, lng, '#6b7280'); // Gray color
    
    // Close any open info windows
    busStopMarkers.forEach(m => m.infoWindow && m.infoWindow.close());
    
    // Update the route visualization
    updateRouteVisualization();
    
    updateMapStatus(`Added "${name}" to route (Stop #${stopData.sequence})`, 'success');
    console.log('✅ Added stop from marker:', stopData);
}

// Generate a unique stop code for the route
function generateStopCode(stopName, routeCode, sequence) {
    // Clean the stop name to create a code
    const cleanName = stopName
        .replace(/[^a-zA-Z0-9\s]/g, '') // Remove special characters
        .split(' ')
        .map(word => word.substring(0, 3).toUpperCase()) // Take first 3 letters of each word
        .join('')
        .substring(0, 6); // Limit to 6 characters
    
    // Format: ROUTE-CLEANNAME-SEQ (e.g., KBS03-NYA-01)
    const sequenceStr = sequence.toString().padStart(2, '0');
    return `${routeCode}-${cleanName || 'STOP'}-${sequenceStr}`;
}

// Change marker color when selected
function changeMarkerColor(placeId, lat, lng, newColor) {
    // Find the marker that matches this location
    const targetMarker = busStopMarkers.find(marker => {
        if (HAS_GOOGLE_KEY && google?.maps) {
            const position = marker.getPosition();
            return Math.abs(position.lat() - lat) < 0.0001 && Math.abs(position.lng() - lng) < 0.0001;
        } else {
            // Leaflet
            const position = marker.getLatLng();
            return Math.abs(position.lat - lat) < 0.0001 && Math.abs(position.lng - lng) < 0.0001;
        }
    });
    
    if (targetMarker) {
        if (HAS_GOOGLE_KEY && google?.maps) {
            // Update Google Maps marker icon
            targetMarker.setIcon({
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: newColor,
                fillOpacity: 0.8,
                strokeColor: '#ffffff',
                strokeWeight: 2
            });
        } else {
            // Update Leaflet marker
            const iconHtml = `
                <div style="
                    width: 16px; 
                    height: 16px; 
                    background-color: ${newColor}; 
                    border: 2px solid #ffffff; 
                    border-radius: 50%; 
                    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "></div>
            `;
            
            const newIcon = L.divIcon({
                html: iconHtml,
                className: 'custom-bus-stop-marker',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
            
            targetMarker.setIcon(newIcon);
        }
        
        console.log(`🎨 Changed marker color to ${newColor} for stop at (${lat}, ${lng})`);
    }
}

// Update route visualization to show path through selected stops
function updateRouteVisualization() {
    if (selectedStops.length === 0) return;
    
    const origin = {
        lat: parseFloat(document.getElementById('originLat').value),
        lng: parseFloat(document.getElementById('originLng').value)
    };
    
    const destination = {
        lat: parseFloat(document.getElementById('destinationLat').value),
        lng: parseFloat(document.getElementById('destinationLng').value)
    };
    
    // Create waypoints array including all selected stops
    const waypoints = selectedStops
        .sort((a, b) => a.sequence - b.sequence)
        .map(stop => ({ lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) }));
    
    if (HAS_GOOGLE_KEY && directionsService && directionsRenderer) {
        // Calculate route through all stops
        const request = {
            origin: origin,
            destination: destination,
            waypoints: waypoints.map(point => ({ location: point, stopover: true })),
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: false // Keep the order as specified
        };
        
        directionsService.route(request, function(result, status) {
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                routePath = result.routes[0];
                
                // Fit map to show entire route
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(origin);
                bounds.extend(destination);
                waypoints.forEach(point => bounds.extend(point));
                map.fitBounds(bounds);
                
                updateMapStatus(`Route updated to pass through ${selectedStops.length} bus stops`, 'success');
            } else {
                console.warn('Route calculation failed:', status);
                updateMapStatus('Could not calculate route through all stops. Some stops may be inaccessible.', 'error');
            }
        });
    } else {
        // Fallback: Use OSRM for routing with waypoints
        calculateOSRMRouteWithWaypoints(origin, destination, waypoints);
    }
}

// Calculate route using OSRM with waypoints
async function calculateOSRMRouteWithWaypoints(origin, destination, waypoints) {
    try {
        // Build coordinate string: origin, waypoints, destination
        const allPoints = [origin, ...waypoints, destination];
        const coordinates = allPoints.map(point => `${point.lng},${point.lat}`).join(';');
        
        const response = await fetch(
            `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`
        );
        
        if (response.ok) {
            const data = await response.json();
            if (data.routes && data.routes[0]) {
                const coords = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                
                // Remove existing route
                if (routePath && routePath.remove) {
                    map.removeLayer(routePath);
                }
                
                // Add new route with green color
                routePath = L.polyline(coords, {
                    color: '#16a34a', // Green color
                    weight: 6,
                    opacity: 0.8
                }).addTo(map);
                
                // Fit map to show entire route
                const allMarkers = [originMarker, destinationMarker, ...selectedStops.map(stop => 
                    L.marker([stop.lat, stop.lng])
                )];
                const group = L.featureGroup(allMarkers.filter(m => m));
                map.fitBounds(group.getBounds(), { padding: [20, 20] });
                
                updateMapStatus(`Route updated to pass through ${selectedStops.length} bus stops`, 'success');
            }
        }
    } catch (error) {
        console.error('OSRM routing with waypoints failed:', error);
        updateMapStatus('Could not calculate route through all stops.', 'error');
    }
}

// Check if a point is reasonably close to the route path
function isStopAlongRoute(lat, lng) {
    if (!routePath) return true; // If no route path, allow any location
    
    const maxDistance = 2000; // 2km tolerance
    const stopLatLng = HAS_GOOGLE_KEY ? 
        new google.maps.LatLng(lat, lng) : 
        L.latLng(lat, lng);
    
    try {
        if (HAS_GOOGLE_KEY && routePath.overview_path) {
            // Google Maps: Check distance to route path
            for (const point of routePath.overview_path) {
                const distance = google.maps.geometry.spherical.computeDistanceBetween(stopLatLng, point);
                if (distance <= maxDistance) return true;
            }
        } else if (routePath.getLatLngs) {
            // Leaflet: Check distance to route path
            const routePoints = routePath.getLatLngs();
            for (const point of routePoints) {
                const distance = stopLatLng.distanceTo(point);
                if (distance <= maxDistance) return true;
            }
        }
    } catch (error) {
        console.warn('Route distance check failed:', error);
        return true; // Allow if check fails
    }
    
    return false;
}

// Add a manual stop row
function addStopRow(stopData = null) {
    const routeStopsDiv = document.getElementById('routeStops');
    // Normalize incoming stopData keys (some payloads use `latitude`/`longitude`)
    if (stopData && typeof stopData === 'object') {
        if (typeof stopData.latitude !== 'undefined' && (typeof stopData.lat === 'undefined' || stopData.lat === '')) {
            stopData.lat = stopData.latitude;
        }
        if (typeof stopData.longitude !== 'undefined' && (typeof stopData.lng === 'undefined' || stopData.lng === '')) {
            stopData.lng = stopData.longitude;
        }
    }

    // If stopData is provided (e.g. selected from map), ensure it's stored in selectedStops
    if (stopData) {
        // Avoid duplicating by id or coordinates
        const already = selectedStops.find(s => {
            if (s.id && stopData.id && s.id === stopData.id) return true;
            const sLat = parseFloat(s.lat || s.latitude || '0');
            const sLng = parseFloat(s.lng || s.longitude || '0');
            const tLat = parseFloat(stopData.lat || stopData.latitude || '0');
            const tLng = parseFloat(stopData.lng || stopData.longitude || '0');
            return !Number.isNaN(sLat) && !Number.isNaN(sLng) && Math.abs(sLat - tLat) < 0.00001 && Math.abs(sLng - tLng) < 0.00001;
        });
        if (!already) {
            stopData.sequence = stopData.sequence ?? (selectedStops.length + 1);
            selectedStops.push(stopData);
        }
    } else {
        stopData = {
            name: '',
            code: '',
            district: 'Kigali',
            lat: '',
            lng: '',
            sequence: selectedStops.length + 1
        };
        selectedStops.push(stopData);
    }

    const index = selectedStops.length - 1;
    
    const stopRow = document.createElement('div');
    stopRow.className = 'kbs-route-stop-row';
    stopRow.dataset.index = index;
    stopRow.dataset.stopId = stopData.id || '';
    
    // Determine if fields should be readonly (for map-selected stops)
    const isMapSelected = (typeof stopData.id !== 'undefined' && stopData.id)
        && (typeof stopData.lat !== 'undefined' && stopData.lat !== '')
        && (typeof stopData.lng !== 'undefined' && stopData.lng !== '');
    const readonlyClass = isMapSelected ? 'readonly' : '';
    
    stopRow.innerHTML = `
        <div class="kbs-grid kbs-grid-6" style="align-items:end;gap:0.8rem;margin-bottom:1rem;padding:1rem;background:var(--kbs-card-bg);border:1px solid var(--kbs-border);border-radius:8px;">
            <div>
                <label>Stop #${stopData.sequence} ${isMapSelected ? '📍' : '✏️'}</label>
                <input type="text" 
                       name="route_stops[${index}][name]" 
                       value="${escapeHtml(stopData.name || '')}" 
                       placeholder="Stop Name" 
                       required
                       ${isMapSelected ? 'readonly' : ''} 
                       class="${readonlyClass}">
            </div>
            <div>
                <label>Stop Code</label>
                <input type="text" 
                       name="route_stops[${index}][code]" 
                       value="${escapeHtml(stopData.code || '')}" 
                       placeholder="Auto-generated"
                       ${isMapSelected ? 'readonly' : ''} 
                       class="${readonlyClass}">
            </div>
            <div>
                <label>District</label>
                <input type="text" 
                       name="route_stops[${index}][district]" 
                       value="${escapeHtml(stopData.district || 'Kigali')}" 
                       required
                       ${isMapSelected ? 'readonly' : ''} 
                       class="${readonlyClass}">
            </div>
            <div>
                <label>Latitude</label>
                  <input type="number" 
                      name="route_stops[${index}][latitude]" 
                      value="${typeof stopData.lat !== 'undefined' && stopData.lat !== null ? stopData.lat : ''}" 
                       step="any" 
                       placeholder="-1.9441" 
                       required 
                       ${isMapSelected ? 'readonly' : ''} 
                       class="${readonlyClass}">
            </div>
            <div>
                <label>Longitude</label>
                  <input type="number" 
                      name="route_stops[${index}][longitude]" 
                      value="${typeof stopData.lng !== 'undefined' && stopData.lng !== null ? stopData.lng : ''}" 
                       step="any" 
                       placeholder="30.0619" 
                       required 
                       ${isMapSelected ? 'readonly' : ''} 
                       class="${readonlyClass}">
            </div>
            <div>
                <button type="button" onclick="removeStopRow(this)" class="kbs-btn kbs-btn-ghost" style="background:#fee2e2;color:#dc2626;margin-bottom:0;">
                    <svg><use href="#icon-trash"></use></svg>
                </button>
            </div>
        </div>
        ${isMapSelected ? `
            <div style="font-size:0.8rem;color:#059669;margin-top:-0.8rem;margin-bottom:1rem;padding:0 1rem;">
                ✅ Selected from map • Place ID: ${stopData.id}
            </div>
        ` : ''}
    `;
    
    routeStopsDiv.appendChild(stopRow);
    
    // Add location search for manually added stops only
    if (!isMapSelected) {
        setupStopLocationSearch(index);
    }
    
    console.log('✅ Added stop row:', stopData);
}

// Setup location search for a specific stop input
function setupStopLocationSearch(stopIndex) {
    const nameInput = document.querySelector(`input[name="route_stops[${stopIndex}][name]"]`);
    const latInput = document.querySelector(`input[name="route_stops[${stopIndex}][latitude]"]`);
    const lngInput = document.querySelector(`input[name="route_stops[${stopIndex}][longitude]"]`);
    
    if (!nameInput || !latInput || !lngInput) return;
    
    let searchTimer;
    
    nameInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimer);
        
        if (query.length < 3) return;
        
        searchTimer = setTimeout(async () => {
            try {
                if (HAS_GOOGLE_KEY && geocoder) {
                    geocoder.geocode({
                        address: query + ', Kigali, Rwanda',
                        componentRestrictions: { country: 'RW' }
                    }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            const location = results[0].geometry.location;
                            latInput.value = location.lat().toFixed(6);
                            lngInput.value = location.lng().toFixed(6);
                            latInput.style.background = '#ecfdf5';
                            lngInput.style.background = '#ecfdf5';
                        }
                    });
                } else {
                    // Nominatim fallback
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=rw&q=${encodeURIComponent(query)}`
                    );
                    
                    if (response.ok) {
                        const results = await response.json();
                        if (results[0]) {
                            latInput.value = parseFloat(results[0].lat).toFixed(6);
                            lngInput.value = parseFloat(results[0].lon).toFixed(6);
                            latInput.style.background = '#ecfdf5';
                            lngInput.style.background = '#ecfdf5';
                        }
                    }
                }
            } catch (error) {
                console.warn('Stop location search failed:', error);
            }
        }, 800);
    });
}

// Remove a stop row
function removeStopRow(button) {
    const stopRow = button.closest('.kbs-route-stop-row');
    const index = parseInt(stopRow.dataset.index);
    const stopId = stopRow.dataset.stopId;
    
    // Get the stop data before removing
    const removedStop = selectedStops[index];
    
    // If this was a map-selected stop, restore marker color to blue
    if (stopId && removedStop) {
        changeMarkerColor(stopId, removedStop.lat, removedStop.lng, '#3b82f6'); // Blue color
    }
    
    // Remove from selectedStops array
    selectedStops.splice(index, 1);
    
    // Remove the DOM element
    stopRow.remove();
    
    // Reindex remaining stops
    reindexStops();
    
    // Update route visualization
    updateRouteVisualization();
    
    updateMapStatus(`Stop "${removedStop?.name || 'Unknown'}" removed from route.`, 'info');
}

// Reindex stops after removal
function reindexStops() {
    const stopRows = document.querySelectorAll('.kbs-route-stop-row');
    
    stopRows.forEach((row, index) => {
        row.dataset.index = index;
        
        // Update sequence numbers
        selectedStops[index].sequence = index + 1;
        
        // Update form field names
        const inputs = row.querySelectorAll('input[name^="route_stops"]');
        inputs.forEach(input => {
            const currentName = input.name;
            const newName = currentName.replace(/\[\d+\]/, `[${index}]`);
            input.name = newName;
        });
        
        // Update label
        const label = row.querySelector('label');
        if (label) {
            label.textContent = `Stop #${index + 1}`;
        }
    });
}

// Debug function to help troubleshoot issues
function debugMapStatus() {
    const debugInfo = {
        '🗺️ Map Status': {
            'Google API Key': HAS_GOOGLE_KEY ? '✅ Configured' : '❌ Not configured',
            'Map Ready': isMapReady ? '✅ Yes' : '❌ No',
            'Map Type': map ? (map.getZoom ? 'Google Maps' : 'Leaflet') : '❌ No map',
            'Google Maps Available': typeof google !== 'undefined' && google?.maps ? '✅ Yes' : '❌ No',
            'Places Service': placesService ? '✅ Available' : '❌ Not available'
        },
        '📍 Origin Data': {
            'Input Value': document.getElementById('originInput')?.value || '❌ Empty',
            'Hidden Name': document.getElementById('originName')?.value || '❌ Empty',
            'Hidden Lat': document.getElementById('originLat')?.value || '❌ Empty',
            'Hidden Lng': document.getElementById('originLng')?.value || '❌ Empty',
            'Marker': originMarker ? '✅ Created' : '❌ No marker'
        },
        '📍 Destination Data': {
            'Input Value': document.getElementById('destinationInput')?.value || '❌ Empty',
            'Hidden Name': document.getElementById('destinationName')?.value || '❌ Empty', 
            'Hidden Lat': document.getElementById('destinationLat')?.value || '❌ Empty',
            'Hidden Lng': document.getElementById('destinationLng')?.value || '❌ Empty',
            'Marker': destinationMarker ? '✅ Created' : '❌ No marker'
        },
        '🛣️ Route Data': {
            'Route Path': routePath ? '✅ Calculated' : '❌ No route',
            'Can Add Stops': !document.getElementById('addStopBtn').disabled ? '✅ Yes' : '❌ No',
            'Can Load Bus Stops': !document.getElementById('loadStopsBtn').disabled ? '✅ Yes' : '❌ No',
            'Can Save Route': !document.getElementById('saveRouteBtn').disabled ? '✅ Yes' : '❌ No'
        },
        '🚏 Bus Stops Data': {
            'Bus Stops Loaded': busStopsLoaded ? '✅ Yes' : '❌ No',
            'Bus Stop Markers': `${busStopMarkers.length} markers`,
            'Selected Stops': `${selectedStops.length} selected`,
            'Stop Rows': `${document.querySelectorAll('.kbs-route-stop-row').length} rows`
        }
    };
    
    console.log('🐛 DEBUG INFO:', debugInfo);
    
    // Show in alert for easy reading
    let alertText = '🐛 DEBUG INFORMATION:\n\n';
    for (const [section, data] of Object.entries(debugInfo)) {
        alertText += `${section}:\n`;
        for (const [key, value] of Object.entries(data)) {
            alertText += `  • ${key}: ${value}\n`;
        }
        alertText += '\n';
    }
    alertText += '\n📱 Check browser console (F12) for detailed logs.';
    
    alert(alertText);
}

// Load Google Maps API or fallback
if (HAS_GOOGLE_KEY) {
    console.log('Loading Google Maps API...');
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=geometry,places&callback=initMap`;
    script.async = true;
    script.defer = true;
    script.onerror = () => {
        console.warn('Google Maps failed to load, using Leaflet fallback');
        initMap();
    };
    document.head.appendChild(script);
} else {
    console.info('No Google Maps API key configured, using Leaflet fallback');
    document.addEventListener('DOMContentLoaded', initMap);
}
</script>
@endsection
