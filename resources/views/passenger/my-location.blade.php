@extends('layouts.dashboard')

@push('head')
<style>
#locationMap { 
    width: 100%; 
    height: 500px; 
    border-radius: 8px; 
    border: 1px solid var(--kbs-border); 
    margin: 1rem 0; 
}

.location-status {
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    font-size: 0.9rem;
}

.location-status.success {
    background: #ecfdf5;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.location-status.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.location-status.info {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
}

.location-info {
    background: var(--kbs-card-bg);
    border: 1px solid var(--kbs-border);
    border-radius: 8px;
    padding: 1.5rem;
    margin: 1rem 0;
}

.location-toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    margin: 1rem 0;
}

.toggle-switch {
    position: relative;
    width: 60px;
    height: 30px;
    background: #ccc;
    border-radius: 15px;
    cursor: pointer;
    transition: background 0.3s;
}

.toggle-switch.active {
    background: var(--kbs-green);
}

.toggle-slider {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.toggle-switch.active .toggle-slider {
    transform: translateX(30px);
}
</style>
@endpush

@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Dashboard</a>
    <a href="{{ route('passenger.search') }}">Search Routes</a>
    <a href="{{ route('passenger.bookings') }}">My Bookings</a>
    <a href="{{ route('passenger.track-buses') }}">Track My Buses</a>
    <a href="{{ route('passenger.my-location') }}" class="active">My Location</a>
@endsection

@section('panel')
<h1>📍 My Location</h1>
<p>Share your location to track buses and receive arrival notifications.</p>

<div class="location-toggle">
    <div class="toggle-switch" id="locationToggle">
        <div class="toggle-slider"></div>
    </div>
    <div>
        <strong>Location Sharing</strong><br>
        <small id="toggleStatus">Enable to start sharing your location</small>
    </div>
</div>

<div id="locationStatus" class="location-status info">
    Click the toggle above to enable location sharing and see your position on the map.
</div>

<div class="location-info" style="display: none;" id="locationInfo">
    <h3>📍 Current Location</h3>
    <div id="locationDetails">
        <p><strong>Coordinates:</strong> <span id="coordinates">--</span></p>
        <p><strong>Address:</strong> <span id="address">Loading...</span></p>
        <p><strong>Accuracy:</strong> <span id="accuracy">--</span></p>
        <p><strong>Last Updated:</strong> <span id="lastUpdated">--</span></p>
    </div>
</div>

<div id="locationMap"></div>

<div class="kbs-card" style="margin-top: 1.5rem;">
    <h3>🔒 Privacy & Security</h3>
    <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
        <li>Your location is only used for bus tracking and notifications</li>
        <li>Location data is encrypted and stored securely</li>
        <li>You can turn off location sharing at any time</li>
        <li>Only you and the system administrators can see your location</li>
        <li>Location history is automatically deleted after 24 hours</li>
    </ul>
</div>

<script>
// Configuration
const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '';

// Global variables
let map;
let userMarker;
let watchId;
let isTracking = false;
let locationUpdateInterval;

// Initialize map
async function initMap() {
    console.log('🗺️ Initializing location map...', HAS_GOOGLE_KEY ? 'Google Maps' : 'Leaflet');
    
    try {
        if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && google?.maps) {
            await initGoogleMap();
        } else {
            await initLeafletMap();
        }
        
        console.log('✅ Location map initialized');
        updateStatus('Map ready. Enable location sharing to see your position.', 'info');
        
        // Check if user previously enabled location tracking
        const wasTracking = localStorage.getItem('kbs_location_enabled') === 'true';
        if (wasTracking) {
            enableLocationTracking();
        }
        
    } catch (error) {
        console.error('❌ Map initialization failed:', error);
        updateStatus('Map initialization failed. Please refresh the page.', 'error');
    }
}

// Initialize Google Maps
async function initGoogleMap() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    
    map = new google.maps.Map(document.getElementById('locationMap'), {
        zoom: 15,
        center: kigaliCenter,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
    });
}

// Initialize Leaflet Map
async function initLeafletMap() {
    // Load Leaflet if not already loaded
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

    map = L.map('locationMap').setView([-1.9441, 30.0619], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

// Toggle location tracking
document.getElementById('locationToggle').addEventListener('click', function() {
    if (isTracking) {
        disableLocationTracking();
    } else {
        enableLocationTracking();
    }
});

// Enable location tracking
function enableLocationTracking() {
    if (!navigator.geolocation) {
        updateStatus('Geolocation is not supported by your browser.', 'error');
        return;
    }
    
    console.log('📍 Enabling location tracking...');
    updateStatus('Requesting location permission...', 'info');
    
    const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 30000 // 30 seconds
    };
    
    // Get initial position
    navigator.geolocation.getCurrentPosition(
        (position) => {
            console.log('✅ Initial location obtained');
            updateUserLocation(position);
            startLocationWatching(options);
        },
        (error) => {
            console.error('❌ Location error:', error);
            handleLocationError(error);
        },
        options
    );
}

// Start continuous location watching
function startLocationWatching(options) {
    watchId = navigator.geolocation.watchPosition(
        updateUserLocation,
        handleLocationError,
        options
    );
    
    isTracking = true;
    updateToggleUI();
    localStorage.setItem('kbs_location_enabled', 'true');
    
    // Send location updates to server every 30 seconds
    locationUpdateInterval = setInterval(() => {
        if (isTracking) {
            navigator.geolocation.getCurrentPosition(
                sendLocationToServer,
                console.error,
                options
            );
        }
    }, 30000);
    
    updateStatus('Location tracking enabled. Your position will be updated automatically.', 'success');
}

// Disable location tracking
function disableLocationTracking() {
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    
    if (locationUpdateInterval) {
        clearInterval(locationUpdateInterval);
        locationUpdateInterval = null;
    }
    
    isTracking = false;
    updateToggleUI();
    localStorage.setItem('kbs_location_enabled', 'false');
    
    updateStatus('Location tracking disabled.', 'info');
    document.getElementById('locationInfo').style.display = 'none';
}

// Update user location on map and UI
function updateUserLocation(position) {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;
    const accuracy = position.coords.accuracy;
    
    console.log(`📍 Location update: ${lat}, ${lng} (±${accuracy}m)`);
    
    // Update map marker
    updateMapMarker(lat, lng, accuracy);
    
    // Update UI
    document.getElementById('coordinates').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    document.getElementById('accuracy').textContent = `±${Math.round(accuracy)} meters`;
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
    
    // Show location info
    document.getElementById('locationInfo').style.display = 'block';
    
    // Reverse geocode to get address
    reverseGeocode(lat, lng);
    
    // Send to server
    sendLocationToServer(position);
}

// Update map marker
function updateMapMarker(lat, lng, accuracy) {
    if (HAS_GOOGLE_KEY && google?.maps) {
        // Google Maps marker
        if (userMarker) {
            userMarker.setMap(null);
        }
        
        userMarker = new google.maps.Marker({
            position: { lat, lng },
            map: map,
            title: 'Your Location',
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 12,
                fillColor: '#4285f4',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            }
        });
        
        // Add accuracy circle
        new google.maps.Circle({
            strokeColor: '#4285f4',
            strokeOpacity: 0.3,
            strokeWeight: 2,
            fillColor: '#4285f4',
            fillOpacity: 0.1,
            map: map,
            center: { lat, lng },
            radius: accuracy
        });
        
        // Center map on user
        map.setCenter({ lat, lng });
        
    } else {
        // Leaflet marker
        if (userMarker) {
            map.removeLayer(userMarker);
        }
        
        userMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: '<div style="width:24px;height:24px;background:#4285f4;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(0,0,0,0.3);"></div>',
                className: 'user-location-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(map);
        
        // Add accuracy circle
        L.circle([lat, lng], {
            color: '#4285f4',
            fillColor: '#4285f4',
            fillOpacity: 0.1,
            radius: accuracy
        }).addTo(map);
        
        // Center map on user
        map.setView([lat, lng], 16);
    }
}

// Send location to server
async function sendLocationToServer(position) {
    try {
        const data = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            device_info: navigator.userAgent
        };
        
        const response = await fetch('/api/v1/location/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        });
        
        if (response.ok) {
            const result = await response.json();
            console.log('📤 Location sent to server:', result);
        } else {
            console.warn('⚠️ Failed to send location to server:', response.status);
        }
        
    } catch (error) {
        console.error('❌ Error sending location:', error);
    }
}

// Reverse geocode to get address
async function reverseGeocode(lat, lng) {
    try {
        let address = 'Loading address...';
        
        if (HAS_GOOGLE_KEY) {
            // Use Google Geocoding API
            const response = await fetch(
                `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${GOOGLE_MAPS_API_KEY}`
            );
            const data = await response.json();
            
            if (data.status === 'OK' && data.results[0]) {
                address = data.results[0].formatted_address;
            }
        } else {
            // Use Nominatim
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`,
                { headers: { 'User-Agent': 'KBS-Bus-System/1.0' } }
            );
            const data = await response.json();
            address = data.display_name || 'Address not found';
        }
        
        document.getElementById('address').textContent = address;
        
    } catch (error) {
        console.error('Reverse geocoding failed:', error);
        document.getElementById('address').textContent = 'Address lookup failed';
    }
}

// Handle location errors
function handleLocationError(error) {
    let message = 'Location access denied or unavailable.';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            message = 'Location access denied. Please enable location permissions in your browser.';
            break;
        case error.POSITION_UNAVAILABLE:
            message = 'Location information is unavailable. Please check your GPS settings.';
            break;
        case error.TIMEOUT:
            message = 'Location request timed out. Please try again.';
            break;
    }
    
    updateStatus(message, 'error');
    disableLocationTracking();
}

// Update status message
function updateStatus(message, type) {
    const statusDiv = document.getElementById('locationStatus');
    statusDiv.textContent = message;
    statusDiv.className = `location-status ${type}`;
}

// Update toggle UI
function updateToggleUI() {
    const toggle = document.getElementById('locationToggle');
    const status = document.getElementById('toggleStatus');
    
    if (isTracking) {
        toggle.classList.add('active');
        status.textContent = 'Location sharing is enabled';
    } else {
        toggle.classList.remove('active');
        status.textContent = 'Location sharing is disabled';
    }
}

// Initialize when page loads
if (HAS_GOOGLE_KEY) {
    // Load Google Maps API
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&callback=initMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
} else {
    // Initialize Leaflet
    document.addEventListener('DOMContentLoaded', initMap);
}
</script>
@endsection