@extends('layouts.dashboard')

@section('panel')
<h1>Driver — Assigned Trips</h1>
@foreach($trips as $trip)
    <div class="kbs-card" style="margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap">
        <div>
            <strong>{{ $trip->route->name }}</strong><br>
            {{ $trip->travel_date->format('d M Y') }} · {{ $trip->departure_time }} · {{ $trip->bus->plate_number }}
            <span class="kbs-badge kbs-badge-info">{{ $trip->status }}</span>
        </div>
        <a href="{{ route('driver.trip', $trip) }}" class="kbs-btn kbs-btn-primary">Manage Trip</a>
    </div>
@endforeach
@endsection
