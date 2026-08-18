@extends('layouts.dashboard')

@section('title', 'Passenger Dashboard')
@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}" class="active">Overview</a>
    <a href="{{ route('passenger.search') }}">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}">My Tickets</a>
    <a href="{{ route('passenger.complaints.create') }}">Complaint</a>
@endsection

@section('panel')
<h1>Welcome, {{ auth()->user()->name }}</h1>
<p style="color:var(--kbs-muted);margin-top:0">Live buses near you — no fixed timetable required. We show real-time location and direction.</p>

<div class="kbs-card" style="margin-bottom:1.5rem;padding:0;overflow:hidden">
    <div style="padding:1rem 1rem 0">
        <h2 style="margin:0">Buses Will Pass your location</h2>
        <p id="nearbyStatus" style="color:var(--kbs-muted);font-size:.9rem;margin:.5rem 0">Detecting your location…</p>
        <button type="button" class="kbs-btn kbs-btn-ghost" onclick="refreshNearby()">Refresh location</button>
    </div>
    <div id="nearbyMap" class="kbs-live-map" style="height:380px;border-radius:0;border-left:0;border-right:0"></div>
    <div id="nearbyBusList" style="padding:1rem"></div>
</div>

<div id="alertsContainer">
@if($alerts->count())
    <div class="kbs-card" style="margin:1rem 0;border-left:4px solid var(--kbs-accent)">
        <strong>Bus approaching your stop</strong>
        @foreach($alerts as $alert)
            <p style="margin:.5rem 0">{{ $alert->message }}</p>
        @endforeach
    </div>
@endif
</div>

<h2>Recent Bookings</h2>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Ref</th><th>Route</th><th>Seat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($bookings as $b)
            <tr>
                <td>{{ $b->reference }}</td>
                <td>{{ $b->schedule?->displayRouteName() ?? ($b->schedule->route->name ?? '-') }}</td>
                <td>{{ $b->seat_number }}</td>
                <td><span class="kbs-badge kbs-badge-{{ $b->status === 'confirmed' ? 'success' : 'warning' }}">{{ $b->status }}</span></td>
                <td>
                    @if($b->status === 'confirmed')
                        <a href="{{ route('passenger.ticket', $b) }}">Ticket</a> ·
                        <a href="{{ route('passenger.track', $b) }}">Track</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No bookings yet. <a href="{{ route('passenger.search') }}">Book a trip</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.user-location-marker {
    background: transparent;
    border: none;
}

.bus-location-marker {
    background: transparent;
    border: none;
}

.accuracy-circle {
    opacity: 0.3;
}

.location-popup {
    text-align: center;
    padding: 10px;
}

.location-popup h4 {
    margin: 0 0 5px 0;
    color: #2563eb;
}

.location-popup p {
    margin: 5px 0;
    font-size: 0.85rem;
    color: #666;
}

.bus-popup h4 {
    margin: 0 0 8px;
    color: #f59e0b;
    font-size: 1.1rem;
}

.bus-popup p {
    margin: 4px 0;
    font-size: 0.9rem;
}

.bus-popup .kbs-btn {
    margin-top: 10px;
}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const nearbyUrl = @json(route('passenger.nearby-buses'));
    const alertsUrl = @json(route('passenger.alerts'));
    const searchUrl = @json(route('passenger.search'));
    const GOOGLE_MAPS_API_KEY = '{{ env("GOOGLE_MAPS_API_KEY", "") }}';
    const HAS_GOOGLE_KEY = GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY.trim() !== '';
    
    let map, layers = {}, pollTimer;
    let userLocation = null;
    let userMarker = null;
    let accuracyCircle = null;

    function initMap(lat, lng) {
        if (map) return;
        map = L.map('nearbyMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18, attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        
        console.log('🗺️ Map initialized at:', lat, lng);
    }

    function clearLayers() {
        Object.values(layers).forEach(l => map && l.remove());
        layers = {};
    }

    // Create a custom blue marker for user's location
    function createUserMarker(lat, lng, accuracy) {
        // Remove existing user marker and accuracy circle
        if (userMarker) {
            map.removeLayer(userMarker);
        }
        if (accuracyCircle) {
            map.removeLayer(accuracyCircle);
        }

        // Add accuracy circle (shows GPS precision)
        if (accuracy && accuracy < 1000) {
            accuracyCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 0.15,
                weight: 2,
                opacity: 0.3,
                className: 'accuracy-circle'
            }).addTo(map);
        }

        // Create custom blue icon
        const userIcon = L.divIcon({
            html: `
                <div style="
                    width: 32px;
                    height: 32px;
                    background: #3b82f6;
                    border: 4px solid white;
                    border-radius: 50%;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.3), 0 0 0 2px rgba(59,130,246,0.3);
                    position: relative;
                ">
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 12px;
                        height: 12px;
                        background: white;
                        border-radius: 50%;
                    "></div>
                </div>
            `,
            className: 'user-location-marker',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        // Create marker with popup
        userMarker = L.marker([lat, lng], { icon: userIcon })
            .bindPopup(`
                <div class="location-popup">
                    <h4>📍 Your Location</h4>
                    <p><strong>Coordinates:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                    ${accuracy ? `<p><strong>Accuracy:</strong> ±${Math.round(accuracy)}m</p>` : ''}
                    <hr style="margin: 10px 0; border-color: #e5e7eb;">
                    <p style="font-size: 0.85rem; color: #666; margin: 0;">
                        <strong style="color: #3b82f6;">● Blue marker</strong> = You (current location)<br>
                        <strong style="color: #f4b400;">● Yellow marker</strong> = Bus stops<br>
                        <strong style="color: #f59e0b;">📍 Orange pointer</strong> = Live buses<br>
                        <strong style="color: #f59e0b;">--- Orange line</strong> = Bus route
                    </p>
                </div>
            `)
            .addTo(map);

        console.log('📍 Blue user marker added at:', lat, lng);
    }

    function renderNearby(data) {
        const p = data.passenger;
        userLocation = { lat: p.latitude, lng: p.longitude, accuracy: p.accuracy };
        
        initMap(p.latitude, p.longitude);
        clearLayers();

        // Add prominent blue marker for user's location
        createUserMarker(p.latitude, p.longitude, p.accuracy);

        if (data.nearest_stop) {
            const s = data.nearest_stop;
            layers.stop = L.circleMarker([s.latitude, s.longitude], {
                radius: 10, color: '#0b5e3c', fillColor: '#f4b400', fillOpacity: .95, weight: 2
            }).bindPopup(`<b>Nearest stop</b><br>${s.name}<br>${s.distance_km} km away`).addTo(map);
            
            document.getElementById('nearbyStatus').innerHTML = `
                <span style="color: #2563eb; font-weight: 500;">📍 Your location detected</span> · 
                Nearest stop: <strong>${s.name}</strong> (${s.distance_km} km away) · 
                ${data.buses.length} bus(es) nearby
            `;
        } else {
            document.getElementById('nearbyStatus').innerHTML = `
                <span style="color: #2563eb; font-weight: 500;">📍 Your location detected</span> · 
                ${data.buses.length} bus(es) nearby
            `;
        }

        let listHtml = '';
        data.buses.forEach((bus, i) => {
            if (bus.location) {
                // Create custom bus icon (location pointer)
                const busIcon = L.divIcon({
                    html: `
                        <div style="
                            position: relative;
                            width: 40px;
                            height: 40px;
                        ">
                            <svg width="40" height="40" viewBox="0 0 40 40" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                                <path d="M20 0 C12 0 6 6 6 14 C6 22 20 38 20 38 C20 38 34 22 34 14 C34 6 28 0 20 0 Z" 
                                      fill="#f59e0b" 
                                      stroke="#ffffff" 
                                      stroke-width="2"/>
                                <circle cx="20" cy="14" r="6" fill="#ffffff"/>
                            </svg>
                            <div style="
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                font-size: 10px;
                                font-weight: bold;
                                color: #f59e0b;
                            ">🚌</div>
                        </div>
                    `,
                    className: 'bus-location-marker',
                    iconSize: [40, 40],
                    iconAnchor: [20, 40],
                    popupAnchor: [0, -40]
                });

                layers['bus'+i] = L.marker([bus.location.latitude, bus.location.longitude], { icon: busIcon })
                    .bindPopup(`
                        <div style="min-width: 200px; padding: 5px;">
                            <h4 style="margin: 0 0 8px; color: #f59e0b;">🚌 ${bus.bus_plate}</h4>
                            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Route:</strong> ${bus.route_name}</p>
                            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Direction:</strong> ${bus.origin} → ${bus.destination}</p>
                            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Heading to:</strong> ${bus.heading_to}</p>
                            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Status:</strong> 
                                <span style="color: ${bus.status === 'in_progress' ? '#10b981' : '#3b82f6'};">${bus.status}</span>
                            </p>
                            <p style="margin: 4px 0; font-size: 0.9rem;"><strong>Seats Available:</strong> ${bus.available_seats}</p>
                            ${bus.distance_to_stop_km ? `<p style="margin: 4px 0; font-size: 0.9rem;"><strong>Distance:</strong> ${bus.distance_to_stop_km} km from nearest stop</p>` : ''}
                            <a href="${searchUrl}?origin_stop_id=${data.nearest_stop?.id || ''}&destination_stop_id=${bus.destination_stop_id || ''}&seats=1" 
                               class="kbs-btn kbs-btn-primary" 
                               style="display: block; text-align: center; margin-top: 10px; padding: 6px 12px; text-decoration: none;">
                                Book This Bus
                            </a>
                        </div>
                    `)
                    .addTo(map);
            }
            if (bus.route_path?.length > 1) {
                layers['path'+i] = L.polyline(bus.route_path, { 
                    color: '#f59e0b', 
                    weight: 4, 
                    opacity: 0.7, 
                    dashArray: '8 4' 
                }).addTo(map);
            }
            listHtml += `<div class="kbs-card" style="margin-bottom:.75rem">
                <strong>${bus.bus_plate}</strong> · ${bus.route_name}
                <span class="kbs-badge kbs-badge-info">${bus.status}</span>
                ${bus.approaching_your_stop ? '<span class="kbs-badge kbs-badge-success">Approaching your stop</span>' : ''}
                <br><small style="color:var(--kbs-muted)">Direction: ${bus.origin} → ${bus.destination} · ${bus.available_seats} seats · ${bus.distance_to_stop_km ?? '?'} km from stop</small>
                <br><a href="${searchUrl}?origin_stop_id=${data.nearest_stop?.id || ''}&destination_stop_id=${bus.destination_stop_id || ''}&seats=1" class="kbs-btn kbs-btn-primary" style="margin-top:.5rem;padding:.35rem .75rem;font-size:.85rem">Book this route</a>
            </div>`;
        });

        document.getElementById('nearbyBusList').innerHTML = listHtml ||
            '<p style="color:var(--kbs-muted)">No live buses heading toward your nearest stop right now. Try again shortly.</p>';

        // Center map on user location with all nearby elements visible
        const bounds = [[p.latitude, p.longitude]];
        if (data.nearest_stop) bounds.push([data.nearest_stop.latitude, data.nearest_stop.longitude]);
        data.buses.forEach(b => { if (b.location) bounds.push([b.location.latitude, b.location.longitude]); });
        
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
        } else {
            map.setView([p.latitude, p.longitude], 15);
        }
    }

    window.refreshNearby = function () {
        if (!navigator.geolocation) {
            document.getElementById('nearbyStatus').innerHTML = '<span style="color: #dc2626;">Geolocation not supported by your browser.</span>';
            return;
        }
        
        document.getElementById('nearbyStatus').innerHTML = '<span style="color: #2563eb;">📍 Detecting your location…</span>';
        
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                console.log('📍 Location obtained:', pos.coords.latitude, pos.coords.longitude, '±' + pos.coords.accuracy + 'm');
                
                try {
                    const res = await fetch(`${nearbyUrl}?latitude=${pos.coords.latitude}&longitude=${pos.coords.longitude}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    if (!res.ok) throw new Error('Failed to fetch nearby buses');
                    
                    const data = await res.json();
                    data.passenger.accuracy = pos.coords.accuracy; // Add accuracy to data
                    renderNearby(data);
                    
                    // Send location to server for tracking
                    sendLocationToServer(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                    
                } catch (e) {
                    console.error('❌ Error loading nearby buses:', e);
                    document.getElementById('nearbyStatus').innerHTML = '<span style="color: #dc2626;">Could not load nearby buses. Please try again.</span>';
                }
            },
            (error) => {
                console.error('❌ Geolocation error:', error);
                let message = 'Location access denied. ';
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message += 'Please allow location access in your browser settings.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message += 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        message += 'Location request timed out. Please try again.';
                        break;
                    default:
                        message += 'An unknown error occurred.';
                }
                
                document.getElementById('nearbyStatus').innerHTML = `<span style="color: #dc2626;">${message}</span>`;
            },
            { 
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000
            }
        );
    };

    // Send location to server for tracking
    async function sendLocationToServer(lat, lng, accuracy) {
        try {
            await fetch('/api/v1/location/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    latitude: lat,
                    longitude: lng,
                    accuracy: accuracy,
                    device_info: navigator.userAgent
                })
            });
            console.log('📤 Location sent to server for tracking');
        } catch (error) {
            console.warn('⚠️ Could not send location to server:', error);
        }
    }

    async function pollAlerts() {
        try {
            const res = await fetch(alertsUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const alerts = await res.json();
            if (!alerts.length) return;
            let html = '<div class="kbs-card" style="margin:1rem 0;border-left:4px solid var(--kbs-accent)"><strong>🚌 Bus approaching your stop</strong>';
            alerts.forEach(a => { html += `<p style="margin:.5rem 0">${a.message}</p>`; });
            html += '</div>';
            document.getElementById('alertsContainer').innerHTML = html;
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshNearby();
        pollTimer = setInterval(refreshNearby, 15000);
        setInterval(pollAlerts, 15000);
    });
})();
</script>
@endpush
