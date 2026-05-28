@extends('layouts.dashboard')

@section('panel')
<h1>{{ $schedule->route->name }}</h1>
<div class="kbs-grid kbs-grid-2" style="margin-bottom:1rem">
    <form method="POST" action="{{ route('driver.trip.start', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-primary">Start Trip</button></form>
    <form method="POST" action="{{ route('driver.trip.end', $schedule) }}">@csrf<button class="kbs-btn kbs-btn-ghost">End Trip</button></form>
</div>

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
        <select name="type"><option value="delay">Delay</option><option value="breakdown">Breakdown</option><option value="traffic">Traffic</option></select>
        <textarea name="message" required placeholder="Describe the issue"></textarea>
        <input type="number" name="delay_minutes" placeholder="Delay minutes">
        <button class="kbs-btn kbs-btn-ghost">Submit Report</button>
    </form>
</div>

<h2>Passengers Onboard ({{ $passengers->where('status','boarded')->count() }}/{{ $passengers->count() }})</h2>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Name</th><th>Seat</th><th>From</th><th>Status</th><th>Ticket</th></tr></thead>
        <tbody>
        @foreach($passengers as $p)
            <tr>
                <td>{{ $p->user->name }}</td>
                <td>{{ $p->seat_number }}</td>
                <td>{{ $p->originStop->name }}</td>
                <td>{{ $p->status }}</td>
                <td>{{ $p->ticket?->status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<script>
const locationUrl = @json(route('driver.trip.location', $schedule));
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value;
let watchId = null;
let autoTracking = false;
let lastSentAt = 0;
const SEND_INTERVAL_MS = 10000;

function updateStatus(msg) {
    document.getElementById('locationStatus').innerText = msg;
}

async function sendGpsUpdate(lat, lng) {
    try {
        const response = await fetch(locationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ latitude: lat, longitude: lng }),
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
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);

            const now = Date.now();
            if (now - lastSentAt >= SEND_INTERVAL_MS) {
                lastSentAt = now;
                sendGpsUpdate(lat, lng);
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
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);
            sendGpsUpdate(lat, lng);
        },
        () => { updateStatus('Location access denied.'); },
        { enableHighAccuracy: true }
    );
}
</script>
@endsection
