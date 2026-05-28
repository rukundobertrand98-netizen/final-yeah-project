@extends('layouts.dashboard')

@section('panel')
<h1>Booking Confirmation</h1>
<div class="kbs-grid kbs-grid-2">
    <div class="kbs-card">
        <h2 style="margin-top:0">Booking Summary</h2>
        <p><strong>Passenger:</strong> {{ $booking->user->name }}</p>
        <p><strong>Bus:</strong> {{ $booking->schedule->bus->model ?: $booking->schedule->bus->plate_number }}</p>
        <p><strong>Route:</strong> {{ $booking->schedule->route->name }}</p>
        <p><strong>Pickup:</strong> {{ $booking->originStop->name }}</p>
        <p><strong>Destination:</strong> {{ $booking->destinationStop->name }}</p>
        <p><strong>Seat{{ str_contains($booking->seat_number, ',') ? 's' : '' }}:</strong> {{ $booking->seat_number }}</p>
        <p><strong>Date:</strong> {{ $booking->schedule->travel_date->format('d/m/Y') }}</p>
        <p><strong>Time:</strong> {{ \Illuminate\Support\Carbon::parse($booking->schedule->departure_time)->format('h:i A') }}</p>
        <p><strong>Amount:</strong> {{ number_format($booking->amount) }} RWF</p>
    </div>

    <div class="kbs-card">
        <h2 style="margin-top:0">Pay with MTN MoMo</h2>
        <p><strong>Booking:</strong> {{ $booking->reference }}</p>
        @if($booking->payment)
            <p><strong>Payment status:</strong> <span class="kbs-badge kbs-badge-info">{{ $booking->payment->status }}</span></p>
            @if($booking->payment->status === 'processing')
                <form method="POST" action="{{ route('passenger.pay.status', $booking) }}" style="margin-bottom:1rem">
                    @csrf
                    <button class="kbs-btn kbs-btn-ghost">Check MTN MoMo Status</button>
                </form>
            @endif
        @endif
        <form method="POST" action="{{ route('passenger.pay', $booking) }}" class="kbs-form">
            @csrf
            <label>MTN Mobile Money Number</label>
            <input type="text" name="phone" placeholder="078xxxxxxx" required>
            <p style="font-size:.85rem;color:var(--kbs-muted)">A payment request will be sent to your phone for approval.</p>
            <button type="submit" class="kbs-btn kbs-btn-primary">Pay Now</button>
        </form>
    </div>
</div>
@endsection
