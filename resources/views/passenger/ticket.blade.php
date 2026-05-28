@extends('layouts.dashboard')

@section('panel')
<div class="kbs-ticket">
    <div class="kbs-ticket-header">
        <strong>KBS LIMITED</strong><br>
        <small>Digital Bus Ticket - Kigali</small>
    </div>
    <div class="kbs-ticket-body">
        <p><strong>Ticket ID</strong> {{ $booking->ticket->ticket_number }}</p>
        <p><strong>Reference</strong> {{ $booking->reference }}</p>
        <p><strong>Passenger Name</strong> {{ $booking->user->name }}</p>
        <p><strong>Bus Name</strong> {{ $booking->schedule->bus->model ?: $booking->schedule->bus->plate_number }}</p>
        <p><strong>Route</strong> {{ $booking->schedule->route->name }}</p>
        <p><strong>Seat Number</strong> {{ $booking->seat_number }}</p>
        <p><strong>Pickup Location</strong> {{ $booking->originStop->name }}</p>
        <p><strong>Destination</strong> {{ $booking->destinationStop->name }}</p>
        <p><strong>Departure Time</strong> {{ $booking->schedule->travel_date->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($booking->schedule->departure_time)->format('h:i A') }}</p>
        <p><strong>Booking Date</strong> {{ $booking->created_at->format('d M Y h:i A') }}</p>
        <p><strong>Payment Status</strong> <span class="kbs-badge kbs-badge-success">{{ $booking->payment?->status === 'successful' ? 'Paid' : ucfirst($booking->payment?->status ?? $booking->status) }}</span></p>
        <div style="text-align:center;margin:1.5rem 0">
            <img src="{{ route('tickets.qr', $booking->ticket) }}" alt="QR Code" style="max-width:220px">
            <p style="font-size:.8rem;color:var(--kbs-muted)">Show this QR code to the driver or conductor</p>
        </div>
        <a href="{{ route('passenger.track', $booking) }}" class="kbs-btn kbs-btn-ghost" style="width:100%">Track Bus</a>
    </div>
</div>
@endsection
