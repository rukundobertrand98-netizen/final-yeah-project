@extends('layouts.dashboard')

@section('title', 'My Tickets')

@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}">Overview</a>
    <a href="{{ route('passenger.search') }}">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}" class="active">My Tickets</a>
    <a href="{{ route('passenger.complaints.create') }}">Complaint</a>
@endsection

@section('panel')
<h1>My Tickets</h1>
<p style="color:var(--kbs-muted);margin-top:0">
    <strong>From</strong> and <strong>To</strong> are the stops you selected when booking, not always the full route terminus.
</p>

@php
    $pageBookings = $bookings->getCollection();
@endphp
<div class="kbs-grid kbs-grid-4" style="margin-bottom:1.5rem">
    <div class="kbs-card kbs-stat"><strong>{{ $pageBookings->whereIn('status', ['pending', 'confirmed'])->count() }}</strong><span>Active Bookings</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $pageBookings->where('status', 'boarded')->count() }}</strong><span>Completed Trips</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $pageBookings->where('status', 'cancelled')->count() }}</strong><span>Cancelled Trips</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $pageBookings->whereNotNull('ticket')->count() }}</strong><span>Previous Tickets</span></div>
</div>

@if($trackableBooking)
    <div class="kbs-card" style="margin-bottom:1.5rem">
        <h2 style="margin-top:0">Live map - {{ $trackableBooking->reference }}</h2>
        <p style="margin-bottom:1rem">
            <span class="kbs-route-label">From:</span>
            <strong>{{ $trackableBooking->originStop->name }}</strong>
            &nbsp;to&nbsp;
            <span class="kbs-route-label">To:</span>
            <strong>{{ $trackableBooking->destinationStop->name }}</strong>
            <br>
            <small style="color:var(--kbs-muted)">Route: {{ $trackableBooking->schedule->route->name }}</small>
        </p>
        @include('partials.live-trip-map', [
            'mapId' => 'bookingsLiveMap',
            'dataUrl' => route('passenger.track.data', $trackableBooking),
        ])
    </div>
@endif

<div class="kbs-card">
    <table class="kbs-table">
        <thead>
            <tr>
                <th>Ref</th>
                <th>Route</th>
                <th>Pickup</th>
                <th>Destination</th>
                <th>Date</th>
                <th>Seat</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($bookings as $b)
            <tr class="{{ $trackableBooking && $trackableBooking->id === $b->id ? 'kbs-row-active' : '' }}">
                <td>{{ $b->reference }}</td>
                <td>{{ $b->schedule->route->name }}</td>
                <td><strong>{{ $b->originStop->name ?? '-' }}</strong></td>
                <td><strong>{{ $b->destinationStop->name ?? '-' }}</strong></td>
                <td>{{ $b->schedule->travel_date->format('d/m/Y') }}</td>
                <td>{{ $b->seat_number }}</td>
                <td><span class="kbs-badge kbs-badge-{{ $b->status === 'confirmed' ? 'success' : ($b->status === 'boarded' ? 'info' : 'warning') }}">{{ $b->status }}</span></td>
                <td>{{ $b->payment?->status ?? 'not started' }}</td>
                <td>
                    @if(in_array($b->status, ['confirmed', 'boarded']))
                        <a href="{{ route('passenger.ticket', $b) }}">QR</a> |
                        <a href="{{ route('passenger.track', $b) }}">Live map</a>
                    @elseif($b->status === 'pending')
                        <a href="{{ route('passenger.checkout', $b) }}">Pay</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">No bookings yet. <a href="{{ route('passenger.search') }}">Book a trip</a></td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $bookings->links() }}
</div>
@endsection
