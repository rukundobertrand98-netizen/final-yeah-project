@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.users') }}">Users</a>
    <a href="{{ route('admin.buses') }}">Buses</a>
    <a href="{{ route('admin.routes') }}">Routes</a>
    <a href="{{ route('admin.trips') }}">Trips</a>
    <a href="{{ route('admin.payments') }}">Payments</a>
    <a href="{{ route('admin.booking-history') }}" class="active">Booking History</a>
    <a href="{{ route('admin.passenger-tracking') }}">Passenger Tracking</a>
    <a href="{{ route('admin.reports') }}">Reports</a>
    <a href="{{ route('admin.monitor') }}">Monitor</a>
@endsection

@section('panel')
<div class="kbs-header">
    <h1>📚 Booking History</h1>
    <p>Archived booking records from deleted routes and cancelled bookings</p>
</div>

<!-- Stats Cards -->
<div class="kbs-grid kbs-grid-4" style="margin-bottom: 2rem;">
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['total_archived']) }}</div>
        <div class="kbs-stat-label">Total Archived</div>
    </div>
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">RWF {{ number_format($stats['total_amount']) }}</div>
        <div class="kbs-stat-label">Total Value</div>
    </div>
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['unique_passengers']) }}</div>
        <div class="kbs-stat-label">Unique Passengers</div>
    </div>
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['deleted_routes']) }}</div>
        <div class="kbs-stat-label">Deleted Routes</div>
    </div>
</div>

<!-- Search and Filters -->
<div class="kbs-card" style="margin-bottom: 1.5rem;">
    <div class="kbs-card-header">
        <h2>Search & Filter</h2>
    </div>
    <form method="GET" class="kbs-grid kbs-grid-5" style="align-items: end;">
        <div>
            <label>Search</label>
            <input type="text" 
                   name="search" 
                   value="{{ request('search') }}" 
                   placeholder="Passenger name, email, route...">
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="boarded" {{ request('status') === 'boarded' ? 'selected' : '' }}>Boarded</option>
            </select>
        </div>
        <div>
            <label>From Date</label>
            <input type="date" 
                   name="date_from" 
                   value="{{ request('date_from') }}">
        </div>
        <div>
            <label>To Date</label>
            <input type="date" 
                   name="date_to" 
                   value="{{ request('date_to') }}">
        </div>
        <div>
            <button type="submit" class="kbs-btn kbs-btn-primary">
                <svg><use href="#icon-search"></use></svg>Search
            </button>
        </div>
    </form>
</div>

@if($bookingHistories->count() > 0)
<div class="kbs-card">
    <div class="kbs-card-header">
        <h2>Archived Bookings</h2>
        <div class="kbs-header-actions">
            <button onclick="exportData()" class="kbs-btn kbs-btn-secondary">
                <svg><use href="#icon-download"></use></svg>Export CSV
            </button>
        </div>
    </div>

    <div class="kbs-table-container">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>Booking Reference</th>
                    <th>Passenger</th>
                    <th>Route</th>
                    <th>Journey</th>
                    <th>Travel Details</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Archived</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookingHistories as $history)
                <tr>
                    <td>
                        <div style="font-weight: 500; font-family: monospace;">
                            {{ $history->original_booking_reference }}
                        </div>
                        @if($history->deletion_reason)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Reason: {{ $history->deletion_reason }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $history->passenger_name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            @if($history->passenger_email)
                                {{ $history->passenger_email }}<br>
                            @endif
                            @if($history->passenger_phone)
                                {{ $history->passenger_phone }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $history->route_name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            Code: {{ $history->route_code }}
                        </div>
                        @if($history->operator_name)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Operator: {{ $history->operator_name }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $history->origin_stop_name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted); margin: 0.2rem 0;">↓</div>
                        <div>{{ $history->destination_stop_name }}</div>
                        @if($history->seat_number)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Seat: {{ $history->seat_number }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500;">
                            {{ $history->travel_date->format('M d, Y') }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            Departure: {{ $history->departure_time ?: 'N/A' }}
                        </div>
                        @if($history->bus_plate_number)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Bus: {{ $history->bus_plate_number }}
                            </div>
                        @endif
                        @if($history->driver_name)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Driver: {{ $history->driver_name }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--kbs-green-dark);">
                            RWF {{ number_format($history->amount) }}
                        </div>
                    </td>
                    <td>
                        <span class="kbs-badge kbs-badge-{{ 
                            $history->status === 'confirmed' ? 'success' : 
                            ($history->status === 'cancelled' ? 'danger' : 
                            ($history->status === 'completed' ? 'info' : 'warning'))
                        }}">
                            {{ ucfirst($history->status) }}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 500;">
                            {{ $history->archived_at->format('M d, Y') }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            {{ $history->archived_at->format('H:i') }}
                        </div>
                        @if($history->original_booking_date)
                            <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                                Original: {{ $history->original_booking_date->format('M d, Y') }}
                            </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $bookingHistories->links() }}
</div>
@else
<div class="kbs-empty-state">
    <h3>No Booking History Found</h3>
    <p>
        @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
            No archived bookings match your search criteria.
        @else
            No bookings have been archived yet.
        @endif
    </p>
    @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
        <a href="{{ route('admin.booking-history') }}" class="kbs-btn kbs-btn-primary">
            Clear Filters
        </a>
    @endif
</div>
@endif

<script>
// Export data to CSV
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create a temporary link to download the CSV
    const link = document.createElement('a');
    link.href = window.location.pathname + '?' + params.toString();
    link.download = 'booking-history-' + new Date().toISOString().split('T')[0] + '.csv';
    
    // Trigger download (note: this would need to be implemented on the backend)
    alert('CSV export functionality would be implemented on the backend to generate and return the CSV file.');
}

// Auto-submit form when filters change
document.querySelectorAll('select[name="status"]').forEach(select => {
    select.addEventListener('change', function() {
        if (this.value !== '' || document.querySelector('input[name="search"]').value !== '') {
            this.form.submit();
        }
    });
});

// Add date validation
document.querySelector('input[name="date_from"]')?.addEventListener('change', function() {
    const dateFrom = this.value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    
    if (dateFrom && dateTo && dateFrom > dateTo) {
        document.querySelector('input[name="date_to"]').value = dateFrom;
    }
});

document.querySelector('input[name="date_to"]')?.addEventListener('change', function() {
    const dateTo = this.value;
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    
    if (dateFrom && dateTo && dateTo < dateFrom) {
        document.querySelector('input[name="date_from"]').value = dateTo;
    }
});
</script>
@endsection