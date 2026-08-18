@extends('layouts.dashboard')

@section('title', 'Choose Seats')

@section('panel')
<h1>Choose Your Seats</h1>

<div class="kbs-card" style="margin-bottom: 1.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Route</div>
            <div style="font-weight: 500; margin-top: 0.3rem; font-size: 1.1rem;">{{ $schedule->route->name }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">{{ $schedule->route->code }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Travel Date</div>
            <div style="font-weight: 500; margin-top: 0.3rem;">{{ $schedule->travel_date->format('D, d M Y') }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">Departure: {{ $schedule->departure_time }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">From → To</div>
            <div style="font-weight: 500; margin-top: 0.3rem;">{{ $stops->firstWhere('id', $selectedOrigin)?->name ?? 'Pickup' }}</div>
            <div style="font-size: 0.9rem; color: var(--kbs-muted);">↓</div>
            <div style="font-weight: 500;">{{ $stops->firstWhere('id', $selectedDestination)?->name ?? 'Destination' }}</div>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: var(--kbs-muted); text-transform: uppercase;">Price per Seat</div>
            <div style="font-weight: 600; margin-top: 0.3rem; font-size: 1.3rem; color: var(--kbs-green-dark);">
                {{ number_format($schedule->price) }} RWF
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('passenger.book.store', $schedule) }}" class="kbs-card">
    @csrf
    {{-- Hidden pickup and destination (auto-filled from search) --}}
    <input type="hidden" name="origin_stop_id" value="{{ $selectedOrigin }}">
    <input type="hidden" name="destination_stop_id" value="{{ $selectedDestination }}">
    
    <div class="kbs-grid kbs-grid-2">
        <div>
            <h3 style="margin: 0 0 1rem;">Your Journey</h3>
            
            <div style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.8rem;">
                    <div style="width: 40px; height: 40px; background: var(--kbs-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">A</div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">Pickup Location</div>
                        <div style="font-weight: 500;">{{ $stops->firstWhere('id', $selectedOrigin)?->name ?? 'Select pickup' }}</div>
                    </div>
                </div>
                
                <div style="border-left: 3px dashed var(--kbs-border); height: 20px; margin-left: 18px;"></div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">B</div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">Destination</div>
                        <div style="font-weight: 500;">{{ $stops->firstWhere('id', $selectedDestination)?->name ?? 'Select destination' }}</div>
                    </div>
                </div>
            </div>

            <div style="background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Seats Requested:</span>
                    <strong>{{ $requestedSeats }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Price per Seat:</span>
                    <strong>{{ number_format($schedule->price) }} RWF</strong>
                </div>
                <hr style="margin: 0.5rem 0; border-color: #bbf7d0;">
                <div style="display: flex; justify-content: space-between;">
                    <span><strong>Total:</strong></span>
                    <strong style="font-size: 1.1rem; color: var(--kbs-green-dark);">
                        {{ number_format($schedule->price * $requestedSeats) }} RWF
                    </strong>
                </div>
            </div>
        </div>
        
        <div>
            <h3 style="margin: 0 0 1rem;">Select {{ $requestedSeats }} Seat{{ $requestedSeats > 1 ? 's' : '' }}</h3>
            
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
            
            <p id="seatStatus" style="color:var(--kbs-muted);font-size:.9rem; margin-top: 1rem;">
                Click on available seats to select them. You need to select {{ $requestedSeats }} seat{{ $requestedSeats > 1 ? 's' : '' }}.
            </p>
        </div>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--kbs-border);">
        <a href="{{ route('passenger.search', ['origin_stop_id' => $selectedOrigin, 'destination_stop_id' => $selectedDestination, 'travel_date' => $schedule->travel_date->format('Y-m-d'), 'seats' => $requestedSeats]) }}" 
           class="kbs-btn kbs-btn-ghost">
            <svg><use href="#icon-arrow-left"></use></svg>Back to Search
        </a>
        <button type="submit" class="kbs-btn kbs-btn-primary" id="submitBtn">
            <svg><use href="#icon-credit-card"></use></svg>Continue to Payment
        </button>
    </div>
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
