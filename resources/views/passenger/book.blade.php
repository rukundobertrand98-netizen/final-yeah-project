@extends('layouts.dashboard')

@section('title', 'Choose Seats')

@section('panel')
<h1>Choose Your Seats - {{ $schedule->route->name }}</h1>
<p>{{ $schedule->travel_date->format('D, d M Y') }} at {{ $schedule->departure_time }} - {{ number_format($schedule->price) }} RWF per seat</p>

<form method="POST" action="{{ route('passenger.book.store', $schedule) }}" class="kbs-card">
    @csrf
    <div class="kbs-grid kbs-grid-2">
        <div>
            <label>Pickup Location</label>
            <select name="origin_stop_id" required>
                @foreach($stops as $stop)
                    <option value="{{ $stop->id }}" @selected($selectedOrigin === $stop->id)>{{ $stop->name }}</option>
                @endforeach
            </select>
            <label>Destination</label>
            <select name="destination_stop_id" required>
                @foreach($stops as $stop)
                    <option value="{{ $stop->id }}" @selected($selectedDestination === $stop->id)>{{ $stop->name }}</option>
                @endforeach
            </select>
            <p style="color:var(--kbs-muted);font-size:.9rem">
                Green seats are available. Red seats are already booked. Gray seats are reserved by passengers who have not finished payment.
            </p>
        </div>
        <div>
            <label>Select {{ $requestedSeats }} Seat{{ $requestedSeats > 1 ? 's' : '' }}</label>
            <div class="kbs-live-map-legend" style="margin:.25rem 0 .75rem">
                <span><i class="kbs-dot kbs-dot-available"></i> Available</span>
                <span><i class="kbs-dot kbs-dot-booked"></i> Booked</span>
                <span><i class="kbs-dot kbs-dot-reserved"></i> Reserved</span>
            </div>
            <div id="selectedSeatInputs"></div>
            <div class="kbs-seat-grid" id="seatGrid">
                @foreach($schedule->bus->seatLabels() as $seat)
                    @php
                        $isBooked = in_array($seat, $booked, true);
                        $isReserved = in_array($seat, $reserved, true);
                        $taken = $isBooked || $isReserved;
                    @endphp
                    <button type="button"
                            class="kbs-seat {{ $isBooked ? 'booked' : ($isReserved ? 'reserved' : 'available') }}"
                            data-seat="{{ $seat }}"
                            @disabled($taken)
                            @if(!$taken) onclick="toggleSeat('{{ $seat }}')" @endif>
                        {{ $seat }}
                    </button>
                @endforeach
            </div>
            <p id="seatStatus" style="color:var(--kbs-muted);font-size:.9rem">No seats selected.</p>
        </div>
    </div>
    <button type="submit" class="kbs-btn kbs-btn-primary">Continue to Payment</button>
</form>
@endsection

@push('scripts')
<script>
const requiredSeats = {{ (int) $requestedSeats }};
const seatStatusUrl = @json(route('passenger.schedules.seats', $schedule));
let selectedSeats = [];

function syncSelectedSeatInputs() {
    const box = document.getElementById('selectedSeatInputs');
    box.innerHTML = selectedSeats.map(seat => `<input type="hidden" name="seat_numbers[]" value="${seat}">`).join('');
    document.getElementById('seatStatus').innerText = selectedSeats.length
        ? `Selected: ${selectedSeats.join(', ')}`
        : 'No seats selected.';
}

function toggleSeat(seat) {
    const seatEl = document.querySelector(`[data-seat="${seat}"]`);
    if (!seatEl || !seatEl.classList.contains('available')) return;

    if (selectedSeats.includes(seat)) {
        selectedSeats = selectedSeats.filter(item => item !== seat);
        seatEl.classList.remove('selected');
        syncSelectedSeatInputs();
        return;
    }

    if (selectedSeats.length >= requiredSeats) {
        alert(`You selected ${requiredSeats} seat${requiredSeats > 1 ? 's' : ''}. Remove one before choosing another.`);
        return;
    }

    selectedSeats.push(seat);
    seatEl.classList.add('selected');
    syncSelectedSeatInputs();
}

async function refreshSeats() {
    try {
        const response = await fetch(seatStatusUrl, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        document.querySelectorAll('.kbs-seat').forEach(seatEl => {
            const seat = seatEl.dataset.seat;
            if (!data.occupied.includes(seat)) return;
            seatEl.classList.remove('available', 'selected');
            seatEl.classList.add('reserved');
            seatEl.disabled = true;
            selectedSeats = selectedSeats.filter(item => item !== seat);
        });
        syncSelectedSeatInputs();
    } catch (error) {}
}

document.querySelector('form').addEventListener('submit', event => {
    if (selectedSeats.length !== requiredSeats) {
        event.preventDefault();
        alert(`Please select exactly ${requiredSeats} seat${requiredSeats > 1 ? 's' : ''}.`);
    }
});

syncSelectedSeatInputs();
setInterval(refreshSeats, 10000);
</script>
@endpush
