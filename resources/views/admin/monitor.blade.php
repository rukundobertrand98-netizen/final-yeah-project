@extends('layouts.dashboard')

@section('panel')
<h1>Live Bus Monitor — Kigali</h1>
@foreach($trips as $trip)
    <div class="kbs-card" style="margin-bottom:1rem">
        <strong>{{ $trip->bus->plate_number }}</strong> — {{ $trip->route->name }}
        <span class="kbs-badge kbs-badge-warning">{{ $trip->status }}</span>
        @if($trip->latestLocation)
            <p>Location: {{ $trip->latestLocation->latitude }}, {{ $trip->latestLocation->longitude }}
            · Near {{ $trip->latestLocation->nearestStop?->name }}</p>
        @endif
        <p>Driver: {{ $trip->driver?->name }}</p>
    </div>
@endforeach
@endsection
