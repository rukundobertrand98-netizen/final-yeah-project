@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Overview</a>
    <a href="{{ route('passenger.search') }}">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}">My Tickets</a>
@endsection

@section('panel')
<h1>Track Your Bus</h1>

<div class="kbs-card" style="margin-bottom:1rem">
    <p style="margin:0">
        <span class="kbs-route-label">From (Departure):</span>
        <strong>{{ $booking->originStop->name }}</strong>
        ({{ $booking->originStop->code }})
        &nbsp;→&nbsp;
        <span class="kbs-route-label">To (Destination):</span>
        <strong>{{ $booking->destinationStop->name }}</strong>
        ({{ $booking->destinationStop->code }})
    </p>
    <p style="margin:.5rem 0 0;color:var(--kbs-muted)">
        Route: {{ $booking->schedule->route->name }} · Ref: {{ $booking->reference }} · Seat {{ $booking->seat_number }}
    </p>
</div>

@include('partials.live-trip-map', [
    'mapId' => 'trackLiveMap',
    'dataUrl' => route('passenger.track.data', $booking),
])

<p style="margin-top:1rem">
    <a href="{{ route('passenger.bookings') }}" class="kbs-btn kbs-btn-ghost">← Back to My Tickets</a>
</p>
@endsection
