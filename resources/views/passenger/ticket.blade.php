@extends('layouts.dashboard')

@section('title', 'Digital Ticket')

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

        {{-- Track Bus Button - More Prominent --}}
        <div style="margin: 1rem 0;">
            <a href="{{ route('passenger.track', $booking) }}" 
               class="kbs-btn kbs-btn-primary" 
               style="width:100%; padding: 0.8rem; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                </svg>
                📍 Track Bus on Map
            </a>
            <p style="text-align: center; font-size: 0.8rem; color: var(--kbs-muted); margin-top: 0.5rem;">
                See real-time location of your bus on the map
            </p>
        </div>
    </div>
</div>
@endsection
