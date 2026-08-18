@extends('layouts.dashboard')

@section('title', 'Track Your Bus')

@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Overview</a>
    <a href="{{ route('passenger.search') }}">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}">My Tickets</a>
@endsection

@section('panel')
<div class="kbs-header">
    <h1>🚌 Track Your Bus</h1>
    <div class="kbs-header-actions">
        <a href="{{ route('passenger.ticket', $booking) }}" class="kbs-btn kbs-btn-ghost">
            <svg><use href="#icon-ticket"></use></svg>View Ticket
        </a>
        <a href="{{ route('passenger.bookings') }}" class="kbs-btn kbs-btn-ghost">
            <svg><use href="#icon-arrow-left"></use></svg>Back to Bookings
        </a>
    </div>
</div>

<div class="kbs-card" style="margin-bottom:1rem">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Route</div>
            <div style="font-weight: 500; margin-top: 0.3rem; font-size: 1.1rem;">{{ $booking->schedule->route->name }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">{{ $booking->schedule->route->code }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Departure</div>
            <div style="font-weight: 500; margin-top: 0.3rem;">{{ $booking->schedule->travel_date->format('D, d M Y') }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">{{ $booking->schedule->departure_time }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Your Journey</div>
            <div style="font-weight: 500; margin-top: 0.3rem;">{{ $booking->originStop->name }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">↓</div>
            <div style="font-weight: 500;">{{ $booking->destinationStop->name }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Seat Number</div>
            <div style="font-weight: 600; margin-top: 0.3rem; font-size: 1.3rem; color: var(--kbs-green-dark);">
                {{ $booking->seat_number }}
            </div>
        </div>
    </div>
</div>

@include('partials.live-trip-map', [
    'mapId' => 'trackLiveMap',
    'dataUrl' => route('passenger.track.data', $booking),
])

<div class="kbs-card" style="margin-top:1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="margin: 0 0 0.5rem;">Booking Reference: {{ $booking->reference }}</h3>
            <p style="margin: 0; font-size: 0.9rem; color: var(--kbs-muted);">
                Reference: {{ $booking->reference }} · Bus: {{ $booking->schedule->bus->plate_number }}
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <form method="POST" action="{{ route('passenger.track.arrived', $booking) }}" 
                  onsubmit="return confirm('Confirm you have arrived at destination? This will free your seat and mark your trip as completed.');" 
                  style="display: inline;">
                @csrf
                <button type="submit" class="kbs-btn kbs-btn-warning">
                    <svg><use href="#icon-check-circle"></use></svg>I've Arrived
                </button>
            </form>
            <button onclick="refreshTracking()" class="kbs-btn kbs-btn-secondary">
                <svg><use href="#icon-refresh"></use></svg>Refresh Location
            </button>
        </div>
    </div>
</div>

<div class="kbs-card" style="margin-top:1rem;">
    <h3 style="margin: 0 0 1rem;">📍 How to Track Your Bus</h3>
    <ul style="margin: 0; padding-left: 1.5rem; color: var(--kbs-muted);">
        <li><strong>Green marker</strong> - Your departure/pickup location</li>
        <li><strong>Red marker</strong> - Your destination</li>
        <li><strong>Large green marker</strong> - Your bus real-time location</li>
        <li><strong>Yellow line</strong> - The route path the bus follows</li>
        <li><strong>White markers</strong> - Bus stops along the route</li>
        <li>The map updates automatically every 15 seconds</li>
    </ul>
</div>
@endsection

@push('scripts')
<script>
function refreshTracking() {
    location.reload();
}

// Auto-refresh every 30 seconds for better tracking
setInterval(function() {
    console.log('🔄 Auto-refreshing tracking data...');
    // The partial already has auto-refresh, but this ensures page stays current
}, 30000);
</script>
@endpush
