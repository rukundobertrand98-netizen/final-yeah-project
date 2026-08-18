@extends('layouts.dashboard')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #driver-map { width:100%; height:380px; border-radius:10px; border:1px solid var(--kbs-border); z-index:1; }
        #passenger-map { width:100%; height:340px; border-radius:10px; border:1px solid var(--kbs-border); z-index:1; display:none; }
        .trip-item { cursor:pointer; transition:border-color .22s,transform .22s; }
        .trip-item:hover { border-color:var(--kbs-green); transform:translateY(-1px); }
        .locate-btn { font-size:.78rem; padding:.3rem .7rem; }
    </style>
@endpush

@section('sidebar')
    <a href="{{ route('driver.dashboard') }}" class="active">📊 Dashboard</a>
    @if($stats['active_trip'])
    <a href="{{ route('driver.trip', $stats['active_trip']) }}">🚦 Active Trip</a>
    @endif
@endsection

@section('panel')
{{-- Stats Row --}}
<div style="margin-bottom:1.5rem;">
    <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">Driver Dashboard</h1>
    <p style="color:var(--kbs-muted);margin:0;">Welcome, <strong>{{ auth()->user()->name }}</strong>.</p>
</div>

<div class="kbs-grid kbs-grid-4" style="margin-bottom:1.5rem;">
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-green-dark);">{{ $stats['upcoming_trips'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);">Upcoming Trips</div>
    </div>
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-green);">{{ $stats['completed_trips'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);">Completed Trips</div>
    </div>
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-green-dark);">{{ $stats['total_trips'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);">Total Trips</div>
    </div>
    @if($assignedBus)
    <div class="kbs-card" style="text-align:center;padding:1.2rem;border:2px solid var(--kbs-yellow);">
        <div style="font-size:1.1rem;font-weight:800;color:var(--kbs-green-dark);">{{ $assignedBus->plate_number }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);margin-top:.2rem;">{{ ucfirst($assignedBus->status) }}</div>
    </div>
    @endif
</div>

{{-- Active Trip Banner --}}
@if($stats['active_trip'])
<div class="kbs-card" style="margin-bottom:1.5rem;border-left:4px solid var(--kbs-yellow);padding:1.2rem;background:var(--kbs-yellow-light);">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <div>
            <h3 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">🚌 Active Trip Now</h3>
            <strong>{{ $stats['active_trip']->route->name }}</strong>
            · <span class="kbs-badge kbs-badge-warning">{{ ucfirst($stats['active_trip']->status) }}</span>
        </div>
        <a href="{{ route('driver.trip', $stats['active_trip']) }}" class="kbs-btn kbs-btn-primary">Manage Trip →</a>
    </div>
</div>
@endif

{{-- Bus Status Report Form --}}
@if($assignedBus)
<div class="kbs-card" style="margin-bottom:1.5rem;padding:1.2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
        <h3 style="margin:0;color:var(--kbs-green-dark);">⚠️ Report Bus Issue</h3>
        <button onclick="toggleReportForm()" class="kbs-btn kbs-btn-sm kbs-btn-warning" id="toggle-report-btn">+ Report Issue</button>
    </div>

    @if(session('success'))
        <div class="kbs-alert kbs-alert-success" style="margin-bottom:1rem;padding:.875rem;border-radius:8px;background:#d1fae5;color:#065f46;border:1px solid #10b981;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="kbs-alert kbs-alert-error" style="margin-bottom:1rem;padding:.875rem;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #ef4444;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div id="report-form-container" style="display:none;">
        <form method="POST" action="{{ route('driver.bus-status.store') }}" style="margin-top:1rem;">
            @csrf

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;font-weight:600;color:var(--kbs-ink);">Bus</label>
                <input type="text" value="{{ $assignedBus->plate_number }}" disabled style="background:#f3f4f6;cursor:not-allowed;">
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;font-weight:600;color:var(--kbs-ink);">Issue Type <span style="color:#dc2626;">*</span></label>
                <select name="issue_type" required style="width:100%;padding:.78rem .9rem;border:1px solid var(--kbs-border);border-radius:8px;">
                    <option value="">Select issue type...</option>
                    <option value="mechanical">Mechanical Problem</option>
                    <option value="electrical">Electrical Issue</option>
                    <option value="tire">Tire Problem</option>
                    <option value="fuel">Fuel Issue</option>
                    <option value="accident">Accident/Damage</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;font-weight:600;color:var(--kbs-ink);">Description <span style="color:#dc2626;">*</span></label>
                <textarea name="description" rows="4" required placeholder="Describe the issue in detail..." style="width:100%;padding:.78rem .9rem;border:1px solid var(--kbs-border);border-radius:8px;resize:vertical;"></textarea>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;font-weight:600;color:var(--kbs-ink);">Estimated Fix Time (optional)</label>
                <input type="datetime-local" name="estimated_fix_at" style="width:100%;padding:.78rem .9rem;border:1px solid var(--kbs-border);border-radius:8px;">
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <button type="submit" class="kbs-btn kbs-btn-warning">Submit Report</button>
                <button type="button" onclick="toggleReportForm()" class="kbs-btn kbs-btn-ghost">Cancel</button>
            </div>
        </form>
    </div>

    @if(isset($myBusReports) && $myBusReports->count() > 0)
        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--kbs-border);">
            <h4 style="margin:0 0 .75rem;color:var(--kbs-green-dark);font-size:.95rem;">Recent Reports</h4>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                @foreach($myBusReports as $report)
                    <div style="padding:.75rem;background:#f9fafb;border-radius:6px;border-left:3px solid {{ $report->status === 'pending' ? '#dc2626' : 'var(--kbs-green)' }};">
                        <div style="display:flex;justify-content:space-between;align-items:start;gap:.5rem;margin-bottom:.3rem;">
                            <strong style="font-size:.9rem;color:var(--kbs-ink);">{{ ucfirst($report->issue_type) }}</strong>
                            <span class="kbs-badge" style="{{ $report->status === 'pending' ? 'background:#dc2626;' : 'background:var(--kbs-green);' }}color:white;font-size:.7rem;">
                                {{ $report->status === 'pending' ? '⚠ Pending' : '✅ Resolved' }}
                            </span>
                        </div>
                        <div style="font-size:.85rem;color:var(--kbs-muted);line-height:1.4;">{{ Str::limit($report->description, 100) }}</div>
                        <div style="font-size:.75rem;color:var(--kbs-muted);margin-top:.3rem;">
                            {{ $report->created_at->format('M d, Y h:i A') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endif

{{-- Live Location Map --}}
<div class="kbs-card" style="margin-bottom:1.5rem;padding:1.2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
        <h3 style="margin:0;color:var(--kbs-green-dark);">� My Live Location</h3>
        <div style="display:flex;gap:.5rem;align-items:center;">
            <span id="gps-status" style="font-size:.82rem;color:var(--kbs-muted);">Detecting GPS…</span>
            <button onclick="centerMyLocation()" class="kbs-btn kbs-btn-sm kbs-btn-ghost">⊕ Re-center</button>
        </div>
    </div>
    <div id="driver-map"></div>
    <div style="font-size:.8rem;color:var(--kbs-muted);margin-top:.5rem;">
        <span class="kbs-dot kbs-dot-bus"></span> Your live position is tracked automatically and shared with admin.
    </div>
</div>

{{-- Passenger Map (shown when clicking a passenger) --}}
<div class="kbs-card" style="margin-bottom:1.5rem;padding:1.2rem;" id="passenger-map-card" style="display:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
        <h3 style="margin:0;color:var(--kbs-green-dark);">🗺️ Passenger Location</h3>
        <button onclick="closePassengerMap()" class="kbs-btn kbs-btn-sm kbs-btn-ghost">✕ Close</button>
    </div>
    <div id="passenger-map"></div>
    <div style="font-size:.8rem;color:var(--kbs-muted);margin-top:.5rem;">
        <span class="kbs-dot kbs-dot-bus"></span> Your location &nbsp;
        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--kbs-danger);margin-right:.25rem;vertical-align:middle;"></span> Passenger pickup location
    </div>
</div>

{{-- Upcoming Trips --}}
<h2 style="margin:0 0 1rem;color:var(--kbs-green-dark);">Upcoming Trips</h2>
@if($upcomingTrips->count() > 0)
    <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem;">
        @foreach($upcomingTrips as $trip)
        <div class="kbs-card trip-item" style="padding:1.1rem;border:1px solid var(--kbs-border);"
             onclick="loadTripPassengers({{ $trip->id }})">
            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.75rem;">
                <div>
                    <strong style="font-size:1.05rem;color:var(--kbs-green-dark);">{{ $trip->route->name }}</strong><br>
                    <span style="font-size:.87rem;color:var(--kbs-muted);">
                        📅 {{ $trip->travel_date->format('d M Y') }} &nbsp;·&nbsp;
                        🕐 {{ $trip->departure_time }} &nbsp;·&nbsp;
                        🚌 {{ $trip->bus->plate_number ?? 'N/A' }}
                    </span><br>
                    <span class="kbs-badge kbs-badge-{{ $trip->status === 'scheduled' ? 'info' : ($trip->status === 'delayed' ? 'warning' : 'success') }}" style="margin-top:.4rem;">
                        {{ ucfirst($trip->status) }}
                    </span>
                </div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <button onclick="event.stopPropagation();showTripOnMap({{ $trip->id }})"
                            class="kbs-btn kbs-btn-sm kbs-btn-ghost locate-btn">📍 Show Passengers</button>
                    <a href="{{ route('driver.trip', $trip) }}" class="kbs-btn kbs-btn-sm kbs-btn-primary"
                       onclick="event.stopPropagation()">Manage Trip</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@else
    <div class="kbs-card" style="padding:2rem;text-align:center;color:var(--kbs-muted);margin-bottom:1.5rem;">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">🚌</div>
        No upcoming trips. Contact your operator.
    </div>
@endif

{{-- Recent Completed --}}
@if($completedTrips->count() > 0)
<h2 style="margin:0 0 1rem;color:var(--kbs-green-dark);">Recent Completed Trips</h2>
<div class="kbs-grid kbs-grid-3" style="margin-bottom:1.5rem;">
    @foreach($completedTrips as $trip)
    <div class="kbs-card" style="padding:1rem;">
        <strong>{{ $trip->route->name }}</strong><br>
        <small style="color:var(--kbs-muted);">{{ $trip->travel_date->format('d M Y') }}</small><br>
        <span class="kbs-badge kbs-badge-success" style="margin-top:.4rem;">Completed</span>
    </div>
    @endforeach
</div>
@endif

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Driver live location map ──
const driverMap = L.map('driver-map',{zoomControl:true,attributionControl:false})
                   .setView([-1.9499,30.0605],14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(driverMap);

const activeTripLocationUrl = @json($stats['active_trip'] ? route('driver.trip.location', $stats['active_trip']) : null);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const GPS_SEND_INTERVAL_MS = 10000;
let lastGpsSentAt = 0;

const driverIcon = L.divIcon({
    className:'',
    html:`<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1a7a4a,#f5c800);
               border:3px solid #fff;display:flex;align-items:center;justify-content:center;
               font-size:16px;box-shadow:0 2px 8px rgba(0,0,0,.3);">🚌</div>`,
    iconSize:[36,36],iconAnchor:[18,18],
});

let driverMarker = null;
let myLat = null, myLng = null;

function updateDriverMarker(lat, lng) {
    myLat = lat; myLng = lng;
    const pos = [lat, lng];
    if (driverMarker) {
        driverMarker.setLatLng(pos);
    } else {
        driverMarker = L.marker(pos, {icon: driverIcon}).addTo(driverMap)
                        .bindPopup('<strong>Your Location</strong>');
        driverMap.setView(pos, 15);
    }
}

function centerMyLocation() {
    if (myLat && myLng) { driverMap.setView([myLat, myLng], 15, {animate:true}); }
}

async function postActiveTripLocation(position) {
    if (!activeTripLocationUrl || !csrfToken) {
        return;
    }

    const now = Date.now();
    if (now - lastGpsSentAt < GPS_SEND_INTERVAL_MS) {
        return;
    }
    lastGpsSentAt = now;

    const speedKmh = position.coords.speed !== null
        ? Math.max(0, position.coords.speed * 3.6)
        : null;

    try {
        const response = await fetch(activeTripLocationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                speed_kmh: speedKmh,
                heading: position.coords.heading,
            }),
        });

        if (!response.ok) {
            document.getElementById('gps-status').textContent = 'GPS detected, server update failed';
            document.getElementById('gps-status').style.color = 'var(--kbs-warning)';
        }
    } catch (error) {
        document.getElementById('gps-status').textContent = 'GPS detected, server unreachable';
        document.getElementById('gps-status').style.color = 'var(--kbs-warning)';
    }
}

if (navigator.geolocation) {
    navigator.geolocation.watchPosition(pos => {
        const {latitude: lat, longitude: lng} = pos.coords;
        updateDriverMarker(lat, lng);
        postActiveTripLocation(pos);
        document.getElementById('gps-status').textContent =
            activeTripLocationUrl
                ? `GPS active and shared with admin - ${lat.toFixed(5)}, ${lng.toFixed(5)}`
                : `GPS active - no active trip to share`;
        document.getElementById('gps-status').style.color = 'var(--kbs-green)';
    }, () => {
        document.getElementById('gps-status').textContent = 'GPS permission is required for admin live tracking';
        document.getElementById('gps-status').style.color = 'var(--kbs-danger)';
    }, {enableHighAccuracy:true, maximumAge:10000});
} else {
    document.getElementById('gps-status').textContent = 'GPS not supported';
}

// ── Passenger map ──
let passMap = null, passMkr = null;

function showTripOnMap(tripId) {
    const card = document.getElementById('passenger-map-card');
    card.style.display = 'block';
    card.scrollIntoView({behavior:'smooth',block:'start'});

    if (!passMap) {
        passMap = L.map('passenger-map',{zoomControl:true,attributionControl:false})
                   .setView([-1.9499,30.0605],14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(passMap);
        document.getElementById('passenger-map').style.display = 'block';
    } else {
        document.getElementById('passenger-map').style.display = 'block';
        passMap.invalidateSize();
    }

    // Place driver marker
    if (myLat && myLng) {
        L.marker([myLat, myLng], {icon: driverIcon})
         .addTo(passMap).bindPopup('<strong>You (Driver)</strong>').openPopup();
        passMap.setView([myLat, myLng], 13);
    }

    // Note: passenger GPS coordinates would come from their booking/profile in a full implementation.
    // Showing a placeholder note on the map for now.
    const info = L.popup({closeOnClick:false,autoClose:false})
        .setLatLng(myLat && myLng ? [myLat + 0.005, myLng + 0.005] : [-1.9499, 30.0605])
        .setContent(`<div style="font-size:.85rem;"><strong>Trip #${tripId}</strong><br>
            <span style="color:#5a7060;">Passenger pickup locations appear here when they share their location.</span></div>`)
        .addTo(passMap);
}

function loadTripPassengers(tripId) {
    // Triggers trip info highlight; full implementation would fetch passenger data
    showTripOnMap(tripId);
}

function closePassengerMap() {
    document.getElementById('passenger-map-card').style.display = 'none';
    document.getElementById('passenger-map').style.display = 'none';
}
</script>
@endpush
