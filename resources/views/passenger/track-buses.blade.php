@extends('layouts.dashboard')

@push('head')
<style>
#trackingMap { 
    width: 100%; 
    height: 600px; 
    border-radius: 8px; 
    border: 1px solid var(--kbs-border); 
    margin: 1rem 0; 
}

.booking-card {
    background: var(--kbs-card-bg);
    border: 1px solid var(--kbs-border);
    border-radius: 8px;
    padding: 1.5rem;
    margin: 1rem 0;
}

.booking-card.active {
    border-color: var(--kbs-green);
    background: var(--kbs-green-light);
}

.tracking-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.info-item {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 6px;
    text-align: center;
}

.info-item .value {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--kbs-green-dark);
    margin: 0.3rem 0;
}

.info-item .label {
    font-size: 0.8rem;
    color: var(--kbs-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 0.5rem;
}

.status-indicator.online { background: #10b981; }
.status-indicator.offline { background: #6b7280; }
.status-indicator.delayed { background: #f59e0b; }

.refresh-button {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--kbs-green);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    cursor: pointer;
    font-size: 1.5rem;
    z-index: 1000;
}

.refresh-button:hover {
    background: var(--kbs-green-dark);
    transform: scale(1.05);
}

.no-bookings {
    text-align: center;
    padding: 3rem;
    color: var(--kbs-muted);
}
</style>
@endpush

@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Dashboard</a>
    <a href="{{ route('passenger.search') }}">Search Routes</a>
    <a href="{{ route('passenger.bookings') }}">My Bookings</a>
    <a href="{{ route('passenger.track-buses') }}" class="active">Track My Buses</a>
    <a href="{{ route('passenger.my-location') }}">My Location</a>
@endsection

@section('panel')
<h1>🚌 Track My Buses</h1>
<p>Real-time tracking of buses for your confirmed bookings.</p>

@if($activeBookings->count() > 0)
    <div class="kbs-grid">
        @foreach($activeBookings as $booking)
            <div class="booking-card" data-booking-id="{{ $booking->id }}">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--kbs-green-dark);">
                            {{ $booking->schedule->route->name }}
                        </h3>
                        <p style="margin: 0.3rem 0; font-size: 0.9rem; color: var(--kbs-muted);">
                            {{ $booking->originStop->name }} → {{ $booking->destinationStop->name }}
                        </p>
                    </div>
                    <span class="kbs-badge kbs-badge-{{ $booking->status === 'confirmed' ? 'success' : 'info' }}">
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>

                <div class="tracking-info">
                    <div class="info-item">
                        <div class="label">Travel Date</div>
                        <div class="value">{{ $booking->schedule->travel_date->format('M d') }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Departure Time</div>
                        <div class="value">{{ $booking->schedule->departure_time }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Bus Number</div>
                        <div class="value">{{ $booking->schedule->bus->plate_number }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Seat</div>
                        <div class="value">{{ $booking->seat_number }}</div>
                    </div>
                </div>

                <div id="tracking-{{ $booking->id }}" class="tracking-details" style="display: none;">
                    <hr style="margin: 1.5rem 0;">
                    <h4 style="margin: 0 0 1rem;">🚌 Live Bus Tracking</h4>
                    <div class="tracking-info">
                        <div class="info-item">
                            <div class="label">Distance to You</div>
                            <div class="value" id="distance-{{ $booking->id }}">--</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Estimated Arrival</div>
                            <div class="value" id="arrival-{{ $booking->id }}">--</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Bus Status</div>
                            <div class="value" id="status-{{ $booking->id }}">
                                <span class="status-indicator offline"></span>Checking...
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Last Updated</div>
                            <div class="value" id="updated-{{ $booking->id }}">--</div>
                        </div>
                    </div>
                </div>

                <button class="kbs-btn kbs-btn-primary" onclick="trackBus({{ $booking->id }})" 
                        style="width: 100%; margin-top: 1rem;" id="track-btn-{{ $booking->id }}">
                    📍 Track This Bus
                </button>
            </div>
        @endforeach
    </div>

    <div id="trackingMap"></div>

    <button class="refresh-button" onclick="refreshAllTracking()" title="Refresh Tracking">
        🔄
    </button>
@else
    <div class="no-bookings">
        <h3>No Active Bookings</h3>
        <p>You don't have any confirmed bookings for upcoming trips.</p>
        <a href="{{ route('passenger.search') }}" class="kbs-btn kbs-btn-primary">
            Search for Routes
        </a>
    </div>
@endif

<script>
// Configuration
const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '';

// Global variables
let map;
let userMarker, busMarkers = {}, routeLines = {};
let userLocation = null;
let activeBookingId = null;
let trackingInterval;

const bookings = @json($activeBookings->map(function($booking) {
    return [
        'id' => $booking->id,
        'route_name' => $booking->schedule->route->name,
        'bus_plate' => $booking->schedule->bus->plate_number,
        'origin_lat' => floatval($booking->originStop->latitude),
        'origin_lng' => floatval($booking->originStop->longitude),
        'origin_name' => $booking->originStop->name,
        'destination_lat' => floatval($booking->destinationStop->latitude),
        'destination_lng' => floatval($booking->destinationStop->longitude),
        'destination_name' => $booking->destinationStop->name,
    ];
}));

// Initialize map
async function initMap() {
    console.log('🗺️ Initializing tracking map...', HAS_GOOGLE_KEY ? 'Google Maps' : 'Leaflet');
    
    try {
        if (HAS_GOOGLE_KEY && typeof google !== 'undefined' && google?.maps) {
            await initGoogleMap();
        } else {
            await initLeafletMap();
        }
        
        console.log('✅ Tracking map initialized');
        
        // Get user location for distance calculations
        getUserLocation();
        
    } catch (error) {
        console.error('❌ Map initialization failed:', error);
    }
}

// Initialize Google Maps
async function initGoogleMap() {
    const kigaliCenter = { lat: -1.9441, lng: 30.0619 };
    
    map = new google.maps.Map(document.getElementById('trackingMap'), {
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

    map = L.map('trackingMap').setView([-1.9441, 30.0619], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

// Get user location
function getUserLocation() {
    if (!navigator.geolocation) {
        console.warn('⚠️ Geolocation not supported');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            console.log('📍 User location:', userLocation);
            updateUserMarker();
        },
        (error) => {
            console.warn('⚠️ Could not get user location:', error);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

// Update user marker on map
function updateUserMarker() {
    if (!userLocation) return;
    
    if (HAS_GOOGLE_KEY && google?.maps) {
        if (userMarker) userMarker.setMap(null);
        
        userMarker = new google.maps.Marker({
            position: userLocation,
            map: map,
            title: 'Your Location',
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: '#4285f4',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            }
        });
    } else {
        if (userMarker) map.removeLayer(userMarker);
        
        userMarker = L.marker([userLocation.lat, userLocation.lng], {
            icon: L.divIcon({
                html: '<div style="width:20px;height:20px;background:#4285f4;border:3px solid white;border-radius:50%;"></div>',
                className: 'user-marker',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        }).addTo(map);
    }
}

// Track specific bus
async function trackBus(bookingId) {
    console.log(`🚌 Starting to track bus for booking ${bookingId}`);
    
    // Update UI
    document.querySelectorAll('.booking-card').forEach(card => {
        card.classList.remove('active');
    });
    
    const bookingCard = document.querySelector(`[data-booking-id="${bookingId}"]`);
    bookingCard.classList.add('active');
    
    const trackingDetails = document.getElementById(`tracking-${bookingId}`);
    trackingDetails.style.display = 'block';
    
    const trackButton = document.getElementById(`track-btn-${bookingId}`);
    trackButton.textContent = '🔄 Tracking...';
    trackButton.disabled = true;
    
    // Set active booking
    activeBookingId = bookingId;
    
    // Start tracking
    await updateBusTracking(bookingId);
    
    // Set up interval for updates
    if (trackingInterval) clearInterval(trackingInterval);
    trackingInterval = setInterval(() => {
        updateBusTracking(bookingId);
    }, 15000); // Update every 15 seconds
    
    trackButton.textContent = '✅ Tracking Active';
}

// Update bus tracking data
async function updateBusTracking(bookingId) {
    try {
        const response = await fetch(`/api/v1/bookings/${bookingId}/track-bus`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            console.log('📡 Tracking data received:', data);
            
            if (data.success) {
                updateTrackingUI(bookingId, data);
                updateBusMarker(bookingId, data);
            } else {
                updateTrackingError(bookingId, data.message);
            }
        } else {
            updateTrackingError(bookingId, 'Failed to get tracking data');
        }
        
    } catch (error) {
        console.error('❌ Tracking update failed:', error);
        updateTrackingError(bookingId, 'Connection error');
    }
}

// Update tracking UI
function updateTrackingUI(bookingId, data) {
    document.getElementById(`distance-${bookingId}`).textContent = 
        `${data.distance_to_origin_km} km`;
    
    document.getElementById(`arrival-${bookingId}`).textContent = 
        `~${data.estimated_arrival_minutes} min`;
    
    const statusElement = document.getElementById(`status-${bookingId}`);
    const isOnline = data.bus_location && 
        new Date(data.bus_location.last_updated) > new Date(Date.now() - 5 * 60 * 1000);
    
    statusElement.innerHTML = `
        <span class="status-indicator ${isOnline ? 'online' : 'offline'}"></span>
        ${isOnline ? 'Online' : 'Offline'}
    `;
    
    document.getElementById(`updated-${bookingId}`).textContent = 
        data.bus_location ? new Date(data.bus_location.last_updated).toLocaleTimeString() : '--';
}

// Update bus marker on map
function updateBusMarker(bookingId, data) {
    if (!data.bus_location) return;
    
    const booking = bookings.find(b => b.id === bookingId);
    if (!booking) return;
    
    const busLat = data.bus_location.latitude;
    const busLng = data.bus_location.longitude;
    
    if (HAS_GOOGLE_KEY && google?.maps) {
        // Remove existing marker
        if (busMarkers[bookingId]) {
            busMarkers[bookingId].setMap(null);
        }
        
        // Add bus marker
        busMarkers[bookingId] = new google.maps.Marker({
            position: { lat: busLat, lng: busLng },
            map: map,
            title: `Bus ${data.bus_info.plate_number}`,
            icon: {
                path: 'M12 2L3 12h9v9h0l9-9-9-10z',
                scale: 2,
                fillColor: '#16a34a',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
                rotation: 0
            }
        });
        
        // Add origin marker
        new google.maps.Marker({
            position: { lat: booking.origin_lat, lng: booking.origin_lng },
            map: map,
            title: `Origin: ${booking.origin_name}`,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#fbbf24',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2
            }
        });
        
        // Fit map to show bus and origin
        const bounds = new google.maps.LatLngBounds();
        bounds.extend({ lat: busLat, lng: busLng });
        bounds.extend({ lat: booking.origin_lat, lng: booking.origin_lng });
        if (userLocation) bounds.extend(userLocation);
        map.fitBounds(bounds, { padding: 50 });
        
    } else {
        // Leaflet implementation
        if (busMarkers[bookingId]) {
            map.removeLayer(busMarkers[bookingId]);
        }
        
        busMarkers[bookingId] = L.marker([busLat, busLng], {
            icon: L.divIcon({
                html: '<div style="width:24px;height:24px;background:#16a34a;border:3px solid white;border-radius:50%;"></div>',
                className: 'bus-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(map).bindPopup(`Bus ${data.bus_info.plate_number}`);
        
        // Add origin marker
        L.marker([booking.origin_lat, booking.origin_lng], {
            icon: L.divIcon({
                html: '<div style="width:16px;height:16px;background:#fbbf24;border:2px solid white;border-radius:50%;"></div>',
                className: 'origin-marker',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            })
        }).addTo(map).bindPopup(`Origin: ${booking.origin_name}`);
        
        // Fit map
        const group = L.featureGroup([busMarkers[bookingId]]);
        if (userMarker) group.addLayer(userMarker);
        map.fitBounds(group.getBounds(), { padding: [20, 20] });
    }
}

// Update tracking error
function updateTrackingError(bookingId, message) {
    const statusElement = document.getElementById(`status-${bookingId}`);
    statusElement.innerHTML = `<span class="status-indicator offline"></span>${message}`;
}

// Refresh all tracking
function refreshAllTracking() {
    if (activeBookingId) {
        updateBusTracking(activeBookingId);
    }
    getUserLocation();
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

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (trackingInterval) clearInterval(trackingInterval);
});
</script>
@endsection