@extends('layouts.dashboard')

@push('head')
<style>
#passengerMap { 
    width: 100%; 
    height: 70vh; 
    border-radius: 8px; 
    border: 1px solid var(--kbs-border); 
    margin: 1rem 0; 
}

.map-controls {
    background: var(--kbs-card-bg);
    border: 1px solid var(--kbs-border);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.legend {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.stats-bar {
    background: #f8fafc;
    border-radius: 6px;
    padding: 0.8rem;
    margin: 1rem 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--kbs-green-dark);
}

.stat-label {
    font-size: 0.8rem;
    color: var(--kbs-muted);
    text-transform: uppercase;
}
</style>
@endpush

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.users') }}">Users</a>
    <a href="{{ route('admin.buses') }}">Buses</a>
    <a href="{{ route('admin.routes') }}">Routes</a>
    <a href="{{ route('admin.trips') }}">Trips</a>
    <a href="{{ route('admin.payments') }}">Payments</a>
    <a href="{{ route('admin.booking-history') }}">Booking History</a>
    <a href="{{ route('admin.passenger-tracking') }}" class="active">Passenger Tracking</a>
    <a href="{{ route('admin.reports') }}">Reports</a>
    <a href="{{ route('admin.monitor') }}">Monitor</a>
@endsection

@section('panel')
<div class="kbs-header">
    <h1>🗺️ Passenger Location Map</h1>
    <div class="kbs-header-actions">
        <a href="{{ route('admin.passenger-tracking') }}" class="kbs-btn kbs-btn-ghost">
            <svg><use href="#icon-list"></use></svg>Back to List
        </a>
        <button onclick="refreshLocations()" class="kbs-btn kbs-btn-secondary">
            <svg><use href="#icon-refresh"></use></svg>Refresh
        </button>
        <button onclick="toggleAutoRefresh()" class="kbs-btn kbs-btn-secondary" id="autoRefreshBtn">
            <svg><use href="#icon-play"></use></svg>Auto Refresh
        </button>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-value" id="totalPassengers">0</div>
        <div class="stat-label">Active Passengers</div>
    </div>
    <div class="stat-item">
        <div class="stat-value" id="recentUpdates">0</div>
        <div class="stat-label">Recent Updates (5min)</div>
    </div>
    <div class="stat-item">
        <div class="stat-value" id="averageAccuracy">0m</div>
        <div class="stat-label">Avg. Accuracy</div>
    </div>
    <div class="stat-item">
        <div class="stat-value" id="lastUpdate">--</div>
        <div class="stat-label">Last Update</div>
    </div>
</div>

<!-- Map Controls -->
<div class="map-controls">
    <div>
        <label style="margin-right: 0.5rem;">Filter by Age:</label>
        <select id="ageFilter" onchange="applyFilters()">
            <option value="all">All Locations</option>
            <option value="5" selected>Last 5 minutes</option>
            <option value="15">Last 15 minutes</option>
            <option value="30">Last 30 minutes</option>
            <option value="60">Last 1 hour</option>
            <option value="120">Last 2 hours</option>
        </select>
    </div>
    
    <div>
        <label style="margin-right: 0.5rem;">Min. Accuracy:</label>
        <select id="accuracyFilter" onchange="applyFilters()">
            <option value="all">Any Accuracy</option>
            <option value="10">±10m (High)</option>
            <option value="50" selected>±50m (Medium)</option>
            <option value="100">±100m (Low)</option>
        </select>
    </div>

    <button onclick="fitMapToLocations()" class="kbs-btn kbs-btn-ghost">
        <svg><use href="#icon-target"></use></svg>Fit All
    </button>
</div>

<!-- Legend -->
<div class="legend">
    <div class="legend-item">
        <div class="legend-dot" style="background: #10b981;"></div>
        <span>Recent (< 5 min)</span>
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background: #f59e0b;"></div>
        <span>Moderate (5-30 min)</span>
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background: #ef4444;"></div>
        <span>Old (> 30 min)</span>
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background: #3b82f6;"></div>
        <span>Selected</span>
    </div>
</div>

<!-- Map Container -->
<div id="passengerMap"></div>

<script>
// Configuration
const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '';

// Global variables
let map;
let passengerMarkers = [];
let isAutoRefresh = false;
let autoRefreshInterval;
let allLocations = [];
let filteredLocations = [];

// Initialize map
async function initMap() {
    console.log('🗺️ Initializing passenger tracking map...', HAS_GOOGLE_KEY ? 'Google Maps' : 'Leaflet');
    
    try {
        if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && google?.maps) {
            await initGoogleMap();
        } else {
            await initLeafletMap();
        }
        
        console.log('✅ Map initialized');
        
        // Load initial passenger locations
        await loadPassengerLocations();
        
    } catch (error) {
        console.error('❌ Map initialization failed:', error);
    }
}

// Initialize Google Maps
async function initGoogleMap() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    
    map = new google.maps.Map(document.getElementById('passengerMap'), {
        zoom: 13,
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

    map = L.map('passengerMap').setView([-1.9441, 30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

// Load passenger locations from API
async function loadPassengerLocations() {
    try {
        console.log('📡 Loading passenger locations...');
        
        const response = await fetch('/api/v1/admin/locations/all', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.success) {
                allLocations = data.locations;
                console.log(`✅ Loaded ${allLocations.length} passenger locations`);
                
                applyFilters();
                updateStats();
            } else {
                console.error('❌ Failed to load locations:', data.message);
            }
        } else {
            console.error('❌ API request failed:', response.status);
        }
        
    } catch (error) {
        console.error('❌ Error loading locations:', error);
    }
}

// Apply filters and update map
function applyFilters() {
    const ageFilter = document.getElementById('ageFilter').value;
    const accuracyFilter = document.getElementById('accuracyFilter').value;
    const now = new Date();
    
    filteredLocations = allLocations.filter(location => {
        const locationTime = new Date(location.last_updated);
        const ageMinutes = (now - locationTime) / (1000 * 60);
        
        // Age filter
        if (ageFilter !== 'all' && ageMinutes > parseInt(ageFilter)) {
            return false;
        }
        
        // Accuracy filter (assuming accuracy is in meters)
        if (accuracyFilter !== 'all' && location.accuracy && location.accuracy > parseInt(accuracyFilter)) {
            return false;
        }
        
        return true;
    });
    
    console.log(`🔍 Filtered to ${filteredLocations.length} locations`);
    updateMapMarkers();
    updateStats();
}

// Update markers on map
function updateMapMarkers() {
    // Clear existing markers
    passengerMarkers.forEach(marker => {
        if (HAS_GOOGLE_KEY && google?.maps) {
            marker.setMap(null);
        } else {
            map.removeLayer(marker);
        }
    });
    passengerMarkers = [];
    
    // Add new markers
    filteredLocations.forEach(location => {
        const marker = createPassengerMarker(location);
        passengerMarkers.push(marker);
    });
    
    console.log(`📍 Created ${passengerMarkers.length} markers`);
}

// Create passenger marker
function createPassengerMarker(location) {
    const now = new Date();
    const locationTime = new Date(location.last_updated);
    const ageMinutes = (now - locationTime) / (1000 * 60);
    
    // Determine marker color based on age
    let color = '#ef4444'; // Red (old)
    if (ageMinutes < 5) {
        color = '#10b981'; // Green (recent)
    } else if (ageMinutes < 30) {
        color = '#f59e0b'; // Yellow (moderate)
    }
    
    if (HAS_GOOGLE_KEY && google?.maps) {
        const marker = new google.maps.Marker({
            position: { lat: location.latitude, lng: location.longitude },
            map: map,
            title: location.user_name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: color,
                fillOpacity: 0.8,
                strokeColor: '#ffffff',
                strokeWeight: 2
            }
        });
        
        const infoWindow = new google.maps.InfoWindow({
            content: createInfoWindowContent(location, ageMinutes)
        });
        
        marker.addListener('click', () => {
            // Close other info windows
            passengerMarkers.forEach(m => m.infoWindow && m.infoWindow.close());
            infoWindow.open(map, marker);
        });
        
        marker.infoWindow = infoWindow;
        return marker;
        
    } else {
        // Leaflet marker
        const marker = L.marker([location.latitude, location.longitude], {
            icon: L.divIcon({
                html: `<div style="width:20px;height:20px;background:${color};border:2px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(0,0,0,0.3);"></div>`,
                className: 'passenger-marker',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        }).addTo(map);
        
        marker.bindPopup(createInfoWindowContent(location, ageMinutes));
        return marker;
    }
}

// Create info window content
function createInfoWindowContent(location, ageMinutes) {
    return `
        <div style="padding: 8px; min-width: 200px;">
            <h4 style="margin: 0 0 8px; color: var(--kbs-green-dark);">${location.user_name}</h4>
            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Location:</strong> ${location.address || 'Address not available'}</p>
            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Coordinates:</strong> ${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}</p>
            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Last Update:</strong> ${Math.round(ageMinutes)} minutes ago</p>
            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Time:</strong> ${new Date(location.last_updated).toLocaleTimeString()}</p>
        </div>
    `;
}

// Update statistics
function updateStats() {
    const now = new Date();
    
    // Total passengers
    document.getElementById('totalPassengers').textContent = filteredLocations.length;
    
    // Recent updates (last 5 minutes)
    const recentCount = filteredLocations.filter(loc => {
        const ageMinutes = (now - new Date(loc.last_updated)) / (1000 * 60);
        return ageMinutes <= 5;
    }).length;
    document.getElementById('recentUpdates').textContent = recentCount;
    
    // Average accuracy
    const locationsWithAccuracy = filteredLocations.filter(loc => loc.accuracy);
    if (locationsWithAccuracy.length > 0) {
        const avgAccuracy = locationsWithAccuracy.reduce((sum, loc) => sum + (loc.accuracy || 0), 0) / locationsWithAccuracy.length;
        document.getElementById('averageAccuracy').textContent = Math.round(avgAccuracy) + 'm';
    } else {
        document.getElementById('averageAccuracy').textContent = '--';
    }
    
    // Last update
    if (filteredLocations.length > 0) {
        const latestUpdate = Math.max(...filteredLocations.map(loc => new Date(loc.last_updated).getTime()));
        document.getElementById('lastUpdate').textContent = new Date(latestUpdate).toLocaleTimeString();
    } else {
        document.getElementById('lastUpdate').textContent = '--';
    }
}

// Fit map to show all locations
function fitMapToLocations() {
    if (filteredLocations.length === 0) return;
    
    if (HAS_GOOGLE_KEY && google?.maps) {
        const bounds = new google.maps.LatLngBounds();
        filteredLocations.forEach(location => {
            bounds.extend({ lat: location.latitude, lng: location.longitude });
        });
        map.fitBounds(bounds, { padding: 50 });
    } else {
        const coordinates = filteredLocations.map(loc => [loc.latitude, loc.longitude]);
        const group = L.featureGroup(coordinates.map(coord => L.marker(coord)));
        map.fitBounds(group.getBounds(), { padding: [20, 20] });
    }
}

// Refresh locations
async function refreshLocations() {
    console.log('🔄 Refreshing passenger locations...');
    await loadPassengerLocations();
}

// Toggle auto refresh
function toggleAutoRefresh() {
    const button = document.getElementById('autoRefreshBtn');
    
    if (isAutoRefresh) {
        clearInterval(autoRefreshInterval);
        isAutoRefresh = false;
        button.innerHTML = '<svg><use href="#icon-play"></use></svg>Auto Refresh';
        button.classList.remove('kbs-btn-primary');
        button.classList.add('kbs-btn-secondary');
    } else {
        autoRefreshInterval = setInterval(refreshLocations, 30000); // 30 seconds
        isAutoRefresh = true;
        button.innerHTML = '<svg><use href="#icon-pause"></use></svg>Stop Auto Refresh';
        button.classList.remove('kbs-btn-secondary');
        button.classList.add('kbs-btn-primary');
    }
}

// Check for URL parameters (for direct links to specific locations)
function checkUrlParameters() {
    const params = new URLSearchParams(window.location.search);
    const lat = params.get('lat');
    const lng = params.get('lng');
    const user = params.get('user');
    
    if (lat && lng) {
        const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
        
        if (HAS_GOOGLE_KEY && google?.maps) {
            map.setCenter(position);
            map.setZoom(16);
            
            // Add a special marker for the highlighted location
            new google.maps.Marker({
                position: position,
                map: map,
                title: user || 'Highlighted Location',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: '#3b82f6',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 3
                }
            });
        } else {
            map.setView([position.lat, position.lng], 16);
            
            L.marker([position.lat, position.lng], {
                icon: L.divIcon({
                    html: '<div style="width:24px;height:24px;background:#3b82f6;border:3px solid white;border-radius:50%;"></div>',
                    className: 'highlighted-marker',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                })
            }).addTo(map).bindPopup(user || 'Highlighted Location');
        }
    }
}

// Initialize when page loads
if (HAS_GOOGLE_KEY) {
    // Load Google Maps API
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&callback=initMap`;
    script.async = true;
    script.defer = true;
    script.onload = checkUrlParameters;
    document.head.appendChild(script);
} else {
    // Initialize Leaflet
    document.addEventListener('DOMContentLoaded', () => {
        initMap().then(checkUrlParameters);
    });
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});
</script>
@endsection