@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">All Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<div class="kbs-header">
    <h1>Route Bookings: {{ $route->name }}</h1>
    <div class="kbs-header-actions">
        <a href="{{ route('operator.routes') }}" class="kbs-btn kbs-btn-ghost">
            <svg><use href="#icon-arrow-left"></use></svg>Back to Routes
        </a>
        <a href="{{ route('operator.routes.edit', $route) }}" class="kbs-btn kbs-btn-secondary">
            <svg><use href="#icon-edit"></use></svg>Edit Route
        </a>
    </div>
</div>

<div class="kbs-card" style="margin-bottom: 1.5rem;">
    <div class="kbs-grid kbs-grid-4">
        <div class="kbs-stat">
            <div class="kbs-stat-number">{{ $bookings->total() }}</div>
            <div class="kbs-stat-label">Total Bookings</div>
        </div>
        <div class="kbs-stat">
            <div class="kbs-stat-number">{{ $bookings->where('status', 'confirmed')->count() }}</div>
            <div class="kbs-stat-label">Confirmed</div>
        </div>
        <div class="kbs-stat">
            <div class="kbs-stat-number">{{ $bookings->where('status', 'cancelled')->count() }}</div>
            <div class="kbs-stat-label">Cancelled</div>
        </div>
        <div class="kbs-stat">
            <div class="kbs-stat-number">RWF {{ number_format($bookings->where('status', 'confirmed')->sum('amount')) }}</div>
            <div class="kbs-stat-label">Revenue</div>
        </div>
    </div>
</div>

<div class="kbs-card">
    <div class="kbs-card-header">
        <h2>Route Information</h2>
    </div>
    <div class="kbs-grid kbs-grid-3">
        <div>
            <label>Route Code</label>
            <div style="font-weight: 500;">{{ $route->code }}</div>
        </div>
        <div>
            <label>Origin → Destination</label>
            <div>{{ $route->originStop->name }} → {{ $route->destinationStop->name }}</div>
        </div>
        <div>
            <label>Base Price</label>
            <div>RWF {{ number_format($route->base_price) }}</div>
        </div>
    </div>
</div>

@if($bookings->count() > 0)
<div class="kbs-card">
    <div class="kbs-card-header">
        <h2>Bookings Management</h2>
        <p style="margin: 0; color: var(--kbs-muted); font-size: 0.9rem;">
            Manage individual bookings for this route. You can cancel or complete bookings as needed.
        </p>
    </div>

    <div class="kbs-table-container">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>Passenger</th>
                    <th>Journey</th>
                    <th>Travel Details</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookings as $booking)
                <tr>
                    <td>
                        <div style="font-weight: 500;">{{ $booking->user->name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            {{ $booking->user->email }}<br>
                            {{ $booking->user->phone }}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $booking->originStop->name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); margin: 0.2rem 0;">↓</div>
                        <div>{{ $booking->destinationStop->name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            Seat: {{ $booking->seat_number }}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $booking->schedule->travel_date->format('M d, Y') }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            Departure: {{ $booking->schedule->departure_time }}<br>
                            Bus: {{ $booking->schedule->bus->plate_number }}
                            @if($booking->schedule->driver)
                                <br>Driver: {{ $booking->schedule->driver->name }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--kbs-green-dark);">
                            RWF {{ number_format($booking->amount) }}
                        </div>
                        @if($booking->payment)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Paid via {{ ucfirst($booking->payment->method) }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <span class="kbs-badge kbs-badge-{{ 
                            $booking->status === 'confirmed' ? 'success' : 
                            ($booking->status === 'cancelled' ? 'danger' : 
                            ($booking->status === 'completed' ? 'info' : 'warning'))
                        }}">
                            {{ ucfirst($booking->status) }}
                        </span>
                        @if($booking->boarded_at)
                            <div style="font-size: 0.7rem; color: var(--kbs-muted); margin-top: 0.2rem;">
                                Boarded: {{ $booking->boarded_at->format('H:i') }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="kbs-dropdown">
                            <button class="kbs-btn kbs-btn-ghost kbs-btn-sm">
                                Actions
                                <svg><use href="#icon-chevron-down"></use></svg>
                            </button>
                            <div class="kbs-dropdown-menu">
                                @if($booking->status === 'confirmed')
                                    <button onclick="updateBookingStatus({{ $booking->id }}, 'boarded')" 
                                            class="kbs-dropdown-item">
                                        <svg><use href="#icon-check-circle"></use></svg>Mark as Boarded
                                    </button>
                                    <button onclick="updateBookingStatus({{ $booking->id }}, 'completed')" 
                                            class="kbs-dropdown-item">
                                        <svg><use href="#icon-flag"></use></svg>Mark as Completed
                                    </button>
                                    <button onclick="showCancelDialog({{ $booking->id }})" 
                                            class="kbs-dropdown-item text-red-600">
                                        <svg><use href="#icon-x-circle"></use></svg>Cancel Booking
                                    </button>
                                @elseif($booking->status === 'boarded')
                                    <button onclick="updateBookingStatus({{ $booking->id }}, 'completed')" 
                                            class="kbs-dropdown-item">
                                        <svg><use href="#icon-flag"></use></svg>Mark as Completed
                                    </button>
                                @endif
                                
                                @if($booking->ticket)
                                    <a href="{{ route('tickets.qr', $booking->ticket) }}" 
                                       class="kbs-dropdown-item" target="_blank">
                                        <svg><use href="#icon-qr-code"></use></svg>View Ticket
                                    </a>
                                @endif
                                
                                <button onclick="showBookingDetails({{ $booking->id }})" 
                                        class="kbs-dropdown-item">
                                    <svg><use href="#icon-eye"></use></svg>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $bookings->links() }}
</div>
@else
<div class="kbs-empty-state">
    <h3>No Bookings Found</h3>
    <p>This route doesn't have any bookings yet.</p>
    <a href="{{ route('operator.schedules') }}" class="kbs-btn kbs-btn-primary">
        <svg><use href="#icon-calendar"></use></svg>Create Schedule
    </a>
</div>
@endif

<!-- Cancel Booking Modal -->
<div id="cancelModal" class="kbs-modal" style="display: none;">
    <div class="kbs-modal-content">
        <div class="kbs-modal-header">
            <h3>Cancel Booking</h3>
            <button onclick="closeCancelDialog()" class="kbs-modal-close">×</button>
        </div>
        <form id="cancelForm" method="POST">
            @csrf
            @method('PUT')
            <div class="kbs-modal-body">
                <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
                <div style="margin-top: 1rem;">
                    <label>Cancellation Reason (Optional)</label>
                    <textarea name="reason" rows="3" placeholder="Provide a reason for cancellation..."></textarea>
                </div>
            </div>
            <div class="kbs-modal-footer">
                <button type="button" onclick="closeCancelDialog()" class="kbs-btn kbs-btn-ghost">Cancel</button>
                <button type="submit" class="kbs-btn kbs-btn-danger">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="detailsModal" class="kbs-modal" style="display: none;">
    <div class="kbs-modal-content">
        <div class="kbs-modal-header">
            <h3>Booking Details</h3>
            <button onclick="closeDetailsDialog()" class="kbs-modal-close">×</button>
        </div>
        <div class="kbs-modal-body" id="bookingDetailsContent">
            <!-- Content will be loaded here -->
        </div>
        <div class="kbs-modal-footer">
            <button onclick="closeDetailsDialog()" class="kbs-btn kbs-btn-primary">Close</button>
        </div>
    </div>
</div>

<script>
// Update booking status
async function updateBookingStatus(bookingId, status) {
    if (!confirm(`Are you sure you want to mark this booking as ${status}?`)) {
        return;
    }
    
    try {
        const response = await fetch(`/operator/bookings/${bookingId}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ status: status })
        });
        
        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to update booking status. Please try again.');
        }
    } catch (error) {
        console.error('Error updating booking status:', error);
        alert('An error occurred. Please try again.');
    }
}

// Show cancel dialog
function showCancelDialog(bookingId) {
    const modal = document.getElementById('cancelModal');
    const form = document.getElementById('cancelForm');
    
    form.action = `/operator/bookings/${bookingId}/status`;
    modal.style.display = 'flex';
    
    // Update form to include cancelled status
    if (!form.querySelector('input[name="status"]')) {
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = 'cancelled';
        form.appendChild(statusInput);
    }
}

// Close cancel dialog
function closeCancelDialog() {
    document.getElementById('cancelModal').style.display = 'none';
}

// Show booking details
function showBookingDetails(bookingId) {
    // You would typically load this via AJAX
    const bookingData = @json($bookings->keyBy('id'));
    const booking = bookingData[bookingId];
    
    if (!booking) return;
    
    const content = `
        <div class="kbs-grid kbs-grid-2">
            <div>
                <h4>Passenger Information</h4>
                <p><strong>Name:</strong> ${booking.user.name}</p>
                <p><strong>Email:</strong> ${booking.user.email}</p>
                <p><strong>Phone:</strong> ${booking.user.phone || 'N/A'}</p>
            </div>
            <div>
                <h4>Journey Details</h4>
                <p><strong>Route:</strong> ${booking.schedule.route.name}</p>
                <p><strong>From:</strong> ${booking.origin_stop.name}</p>
                <p><strong>To:</strong> ${booking.destination_stop.name}</p>
                <p><strong>Seat:</strong> ${booking.seat_number}</p>
            </div>
        </div>
        <div class="kbs-grid kbs-grid-2" style="margin-top: 1rem;">
            <div>
                <h4>Travel Information</h4>
                <p><strong>Date:</strong> ${new Date(booking.schedule.travel_date).toLocaleDateString()}</p>
                <p><strong>Departure:</strong> ${booking.schedule.departure_time}</p>
                <p><strong>Bus:</strong> ${booking.schedule.bus.plate_number}</p>
                ${booking.schedule.driver ? `<p><strong>Driver:</strong> ${booking.schedule.driver.name}</p>` : ''}
            </div>
            <div>
                <h4>Payment Information</h4>
                <p><strong>Amount:</strong> RWF ${parseInt(booking.amount).toLocaleString()}</p>
                <p><strong>Status:</strong> ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</p>
                ${booking.payment ? `<p><strong>Payment Method:</strong> ${booking.payment.method}</p>` : ''}
                <p><strong>Booking Ref:</strong> ${booking.reference}</p>
            </div>
        </div>
    `;
    
    document.getElementById('bookingDetailsContent').innerHTML = content;
    document.getElementById('detailsModal').style.display = 'flex';
}

// Close details dialog
function closeDetailsDialog() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const cancelModal = document.getElementById('cancelModal');
    const detailsModal = document.getElementById('detailsModal');
    
    if (event.target === cancelModal) {
        closeCancelDialog();
    }
    if (event.target === detailsModal) {
        closeDetailsDialog();
    }
});
</script>
@endsection