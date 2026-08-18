@extends('layouts.dashboard')

@section('panel')
<h1>{{ $schedule->displayRouteName() }}</h1>
<div class="kbs-grid kbs-grid-3" style="margin-bottom:1rem;gap:1rem;">
    <div class="kbs-card">
        <strong>Bus</strong><br>
        {{ $schedule->bus->plate_number }} · {{ $schedule->bus->model ?? 'Model N/A' }}<br>
        <span class="kbs-badge kbs-badge-{{ $schedule->bus->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($schedule->bus->status) }}</span>
    </div>
    <div class="kbs-card">
        <strong>Live trip</strong><br>
        Leg #{{ $schedule->leg_number }} · {{ $schedule->isReverseLeg() ? 'Return direction' : 'Forward direction' }}<br>
        <span class="kbs-badge kbs-badge-info">{{ $schedule->liveStatusLabel() }}</span>
        @if($schedule->started_at)
            <br><small style="color:var(--kbs-muted)">Started {{ $schedule->started_at->diffForHumans() }}</small>
        @endif
    </div>
    <div class="kbs-card">
        <strong>Current direction</strong><br>
        {{ $schedule->effectiveOriginStop()?->name ?? 'Origin' }} → {{ $schedule->effectiveDestinationStop()?->name ?? 'Destination' }}<br>
        Stops on this leg: {{ $schedule->orderedStopsForLeg()->count() }}
    </div>
</div>
<div class="kbs-card kbs-form" style="margin-bottom:1rem">
    <h3>Live Map</h3>
    @php
        $location = $schedule->latestLocation;
        $mapDelta = 0.01;
    @endphp
    @if($location)
        <p>Current position: {{ $location->latitude }}, {{ $location->longitude }} @if($location->nearestStop) · Nearest stop: {{ $location->nearestStop->name }} @endif</p>
        <iframe
            width="100%"
            height="320"
            frameborder="0"
            scrolling="no"
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $location->longitude - $mapDelta }},{{ $location->latitude - $mapDelta }},{{ $location->longitude + $mapDelta }},{{ $location->latitude + $mapDelta }}&layer=mapnik&marker={{ $location->latitude }},{{ $location->longitude }}">
        </iframe>
        <p><a href="https://www.openstreetmap.org/?mlat={{ $location->latitude }}&mlon={{ $location->longitude }}#map=14/{{ $location->latitude }}/{{ $location->longitude }}" target="_blank">Open full map</a></p>
    @else
        <p>No GPS location has been logged yet. Use the live GPS controls below to start tracking.</p>
    @endif
</div>
@php
    $tripCanStart = in_array($schedule->status, ['scheduled', 'boarding', 'delayed'], true);
    $tripCanArrive = in_array($schedule->status, ['in_progress', 'delayed'], true);
    $tripCanReturn = $schedule->status === 'arrived';
    $tripCanEnd = in_array($schedule->status, ['boarding', 'in_progress', 'delayed', 'arrived'], true);
    $autoGpsEnabled = in_array($schedule->status, ['boarding', 'in_progress', 'delayed'], true);
@endphp

<div class="kbs-grid kbs-grid-2" style="margin-bottom:1rem;gap:.75rem">
    @if($tripCanStart)
        <form method="POST" action="{{ route('driver.trip.start', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-primary">Start Trip</button></form>
    @endif
    @if($tripCanArrive)
        <form method="POST" action="{{ route('driver.trip.arrived', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-primary">Arrived at Destination</button></form>
    @endif
    @if($tripCanReturn)
        <form method="POST" action="{{ route('driver.trip.return', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-primary">Return Trip (Reverse Stops)</button></form>
    @endif
    @if($tripCanEnd)
        <form method="POST" action="{{ route('driver.trip.end', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-ghost">End Day / Complete</button></form>
    @endif
    @unless($tripCanStart || $tripCanArrive || $tripCanReturn || $tripCanEnd)
        <div class="kbs-card" style="grid-column:1/-1;color:var(--kbs-muted);">This trip is {{ str_replace('_', ' ', $schedule->status) }} and cannot be changed by the driver.</div>
    @endunless
</div>
<p style="color:var(--kbs-muted);font-size:.9rem;margin-bottom:1rem">
    At destination, click <strong>Arrived</strong>, then <strong>Return Trip</strong> to automatically reverse all operator stops (e.g. Nyabugogo → Nyamirambo) without creating a new route.
</p>

<div class="kbs-card kbs-form" style="margin-bottom:1rem">
    <h3>Scan QR / Verify Ticket</h3>
    <form method="POST" action="{{ route('driver.trip.scan', $schedule) }}">
        @csrf
        <input name="qr_token" placeholder="Paste QR token or scan result" required>
        <button class="kbs-btn kbs-btn-primary">Verify</button>
    </form>
</div>

<div class="kbs-card kbs-form" style="margin-bottom:1rem">
    <h3>Live GPS Tracking</h3>
    <div class="kbs-grid kbs-grid-2" style="margin-bottom:.5rem">
        <button type="button" id="autoTrackBtn" class="kbs-btn kbs-btn-primary" onclick="toggleAutoTracking()">Start Auto GPS Tracking</button>
        <button type="button" class="kbs-btn kbs-btn-ghost" onclick="sendLocationOnce()">Send Location Once</button>
    </div>
    <p id="locationStatus" style="color:var(--kbs-muted);font-size:.85rem">GPS tracking not started.</p>
    <form method="POST" action="{{ route('driver.trip.location', $schedule) }}" id="manualLocationForm" style="margin-top:.5rem">
        @csrf
        <div class="kbs-grid kbs-grid-2">
            <input name="latitude" id="latitude" placeholder="Latitude" required>
            <input name="longitude" id="longitude" placeholder="Longitude" required>
        </div>
        <button class="kbs-btn kbs-btn-ghost">Manual Update</button>
    </form>
</div>

<div class="kbs-card kbs-form" style="margin-bottom:1rem">
    <h3>Report Delay / Problem</h3>
    <form method="POST" action="{{ route('driver.trip.report', $schedule) }}">
        @csrf
        <select name="type">
            <option value="delay">Delay</option>
            <option value="breakdown">Breakdown</option>
            <option value="traffic">Traffic</option>
            <option value="maintenance">Maintenance</option>
            <option value="control_technique">Control Technique</option>
        </select>
        <textarea name="message" required placeholder="Describe the issue"></textarea>
        <input type="number" name="delay_minutes" placeholder="Delay minutes">
        <button class="kbs-btn kbs-btn-ghost">Submit Report</button>
    </form>
</div>

<h2>Passengers Onboard ({{ $passengers->where('status','boarded')->count() }}/{{ $passengers->count() }})</h2>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Name</th><th>Seat</th><th>From</th><th>To</th><th>Status</th><th>Ticket</th></tr></thead>
        <tbody>
        @foreach($passengers as $p)
            <tr>
                <td>{{ $p->user->name }}</td>
                <td>{{ $p->seat_number }}</td>
                <td>{{ $p->originStop->name }}</td>
                <td>{{ $p->destinationStop?->name ?? '—' }}</td>
                <td>{{ $p->status }}</td>
                <td>{{ $p->ticket?->status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<script>
const locationUrl = @json(route('driver.trip.location', $schedule));
const autoGpsEnabled = @json($autoGpsEnabled);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value;
let watchId = null;
let autoTracking = false;
let lastSentAt = 0;
const SEND_INTERVAL_MS = 10000;

function updateStatus(msg) {
    document.getElementById('locationStatus').innerText = msg;
}

async function sendGpsUpdate(lat, lng, speedKmh = null, heading = null) {
    try {
        const response = await fetch(locationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ latitude: lat, longitude: lng, speed_kmh: speedKmh, heading }),
        });
        if (response.ok || response.status === 302) {
            updateStatus(`GPS updated: ${lat.toFixed(6)}, ${lng.toFixed(6)} at ${new Date().toLocaleTimeString()}`);
        }
    } catch (e) {
        updateStatus('Failed to send GPS update. Retrying...');
    }
}

function toggleAutoTracking() {
    const btn = document.getElementById('autoTrackBtn');
    if (autoTracking) {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        autoTracking = false;
        btn.textContent = 'Start Auto GPS Tracking';
        btn.classList.remove('kbs-btn-ghost');
        btn.classList.add('kbs-btn-primary');
        updateStatus('Auto GPS tracking stopped.');
        return;
    }

    if (!navigator.geolocation) {
        updateStatus('Geolocation is not supported on this device.');
        return;
    }

    autoTracking = true;
    btn.textContent = 'Stop Auto GPS Tracking';
    btn.classList.remove('kbs-btn-primary');
    btn.classList.add('kbs-btn-ghost');
    updateStatus('Starting auto GPS tracking...');

    watchId = navigator.geolocation.watchPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const speedKmh = position.coords.speed !== null ? Math.max(0, position.coords.speed * 3.6) : null;
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);

            const now = Date.now();
            if (now - lastSentAt >= SEND_INTERVAL_MS) {
                lastSentAt = now;
                sendGpsUpdate(lat, lng, speedKmh, position.coords.heading);
            }
        },
        () => { updateStatus('Location access denied. Please allow location in your browser.'); },
        { enableHighAccuracy: true, maximumAge: 5000 }
    );
}

function sendLocationOnce() {
    if (!navigator.geolocation) {
        updateStatus('Geolocation is not supported on this device.');
        return;
    }
    updateStatus('Getting current location...');
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const speedKmh = position.coords.speed !== null ? Math.max(0, position.coords.speed * 3.6) : null;
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);
            sendGpsUpdate(lat, lng, speedKmh, position.coords.heading);
        },
        () => { updateStatus('Location access denied.'); },
        { enableHighAccuracy: true }
    );
}

function startAutoTrackingWhenReady() {
    if (autoGpsEnabled && !autoTracking) {
        toggleAutoTracking();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAutoTrackingWhenReady);
} else {
    startAutoTrackingWhenReady();
}
</script>
@endsection
