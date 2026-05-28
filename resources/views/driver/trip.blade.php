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
    <h3>Update GPS Location</h3>
    <form method="POST" action="{{ route('driver.trip.location', $schedule) }}">
        @csrf
        <div class="kbs-grid kbs-grid-2">
            <input name="latitude" id="latitude" placeholder="Latitude" value="-1.9441" required>
            <input name="longitude" id="longitude" placeholder="Longitude" value="30.0619" required>
        </div>
        <button type="button" class="kbs-btn kbs-btn-ghost" onclick="useDriverLocation()">Use Current Location</button>
        <button class="kbs-btn kbs-btn-ghost">Update Location</button>
        <p id="locationStatus" style="color:var(--kbs-muted);font-size:.85rem"></p>
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
function useDriverLocation() {
    const status = document.getElementById('locationStatus');
    if (!navigator.geolocation) {
        status.innerText = 'Location is not supported on this device.';
        return;
    }

    status.innerText = 'Getting current location...';
    navigator.geolocation.getCurrentPosition((position) => {
        document.getElementById('latitude').value = position.coords.latitude.toFixed(7);
        document.getElementById('longitude').value = position.coords.longitude.toFixed(7);
        status.innerText = 'Current location is ready. Submit to update the live map.';
    }, () => {
        status.innerText = 'Location access was not allowed.';
    }, { enableHighAccuracy: true });
}
</script>
@endsection
