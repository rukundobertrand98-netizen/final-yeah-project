@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}" class="active">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Schedules & Prices</h1>
<div class="kbs-card kbs-form" style="margin-bottom:1.5rem">
    <form method="POST" action="{{ route('operator.schedules.store') }}">
        @csrf
        <div class="kbs-grid kbs-grid-3">
            <div>
                <label>Route</label>
                <select name="route_id" id="route_id" required>
                    <option value="">Select Route...</option>
                    @foreach($routes as $r)
                        <option value="{{ $r->id }}" data-price="{{ $r->base_price }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Bus</label>
                <select name="bus_id" id="bus_id" required onchange="updateAssignedDriver()">
                    <option value="">Select Bus...</option>
                    @foreach($buses as $b)
                        <option value="{{ $b->id }}" 
                                data-driver-id="{{ $b->driver_id }}" 
                                data-driver-name="{{ $b->driver?->name }}">
                            {{ $b->plate_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Assigned Driver</label>
                <input type="text" id="driver_display" readonly 
                       placeholder="Select a bus to see assigned driver" 
                       style="background: #f3f4f6; color: #6b7280;">
                <input type="hidden" name="driver_id" id="driver_id">
                <small style="color: var(--kbs-muted); font-size: 0.8rem; display: block; margin-top: 0.3rem;">
                    Driver is automatically assigned based on the selected bus
                </small>
            </div>
            <div>
                <label>Date</label>
                <input type="date" name="travel_date" required min="{{ date('Y-m-d') }}">
            </div>
            <div>
                <label>Departure Time</label>
                <input type="time" name="departure_time" required>
            </div>
            <div>
                <label>Arrival Time (Optional)</label>
                <input type="time" name="arrival_time">
            </div>
            <div>
                <label>Price (RWF)</label>
                <input type="number" name="price" id="price" value="500" required min="100">
            </div>
        </div>
        <button class="kbs-btn kbs-btn-primary" id="submitBtn" disabled>
            <svg><use href="#icon-calendar"></use></svg>Add Schedule
        </button>
    </form>
</div>

<div class="kbs-card">
    <div class="kbs-card-header">
        <h2>Current Schedules</h2>
    </div>
    <table class="kbs-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Route</th>
                <th>Bus & Driver</th>
                <th>Price</th>
                <th>Status</th>
                <th>Bookings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($schedules as $s)
            <tr id="schedule-{{ $s->id }}">
                <td>
                    <div style="font-weight: 500;">{{ $s->travel_date->format('M d, Y') }}</div>
                    <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                        Dep: {{ $s->departure_time }}
                        @if($s->arrival_time)
                            | Arr: {{ $s->arrival_time }}
                        @endif
                    </div>
                </td>
                <td>
                    <div style="font-weight: 500;">{{ $s->route->name }}</div>
                    <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                        {{ $s->route->originStop->name }} → {{ $s->route->destinationStop->name }}
                    </div>
                </td>
                <td>
                    <div style="font-weight: 500;">{{ $s->bus->plate_number }}</div>
                    <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                        Driver: {{ $s->driver?->name ?? $s->bus->driver?->name ?? 'No driver assigned' }}
                    </div>
                </td>
                <td>
                    <span style="font-weight: 500; color: var(--kbs-green-dark);">
                        RWF {{ number_format($s->price) }}
                    </span>
                </td>
                <td>
                    <span class="kbs-badge kbs-badge-{{ 
                        $s->status === 'completed' ? 'success' : 
                        ($s->status === 'cancelled' ? 'danger' : 
                        ($s->status === 'in_progress' ? 'info' : 
                        ($s->status === 'delayed' ? 'warning' : 'secondary')))
                    }}">
                        {{ ucfirst(str_replace('_', ' ', $s->status)) }}
                    </span>
                </td>
                <td>
                    <div style="font-weight: 500;">{{ $s->bookings_count ?? 0 }}</div>
                    <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                        / {{ $s->bus->capacity }} seats
                    </div>
                </td>
                <td style="white-space:nowrap">
                    <button type="button" 
                            class="kbs-btn kbs-btn-ghost kbs-btn-sm" 
                            onclick="toggleEdit('{{ $s->id }}')">
                        <svg><use href="#icon-edit"></use></svg>
                    </button>
                    <form method="POST" 
                          action="{{ route('operator.schedules.delete', $s) }}" 
                          style="display:inline" 
                          onsubmit="return confirm('Delete this schedule? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="kbs-btn kbs-btn-sm" 
                                style="background:#fee2e2; color:#dc2626; border: 1px solid #fecaca;" 
                                title="Remove Schedule">
                            <svg><use href="#icon-trash"></use></svg>
                        </button>
                    </form>
                </td>
            </tr>
            <tr id="edit-{{ $s->id }}" style="display: none;">
                <td colspan="7" style="background:var(--kbs-bg);padding:1rem">
                    <form method="POST" action="{{ route('operator.schedules.update', $s) }}" class="kbs-form">
                        @csrf @method('PUT')
                        <h4 style="margin: 0 0 1rem;">Edit Schedule</h4>
                        <div class="kbs-grid kbs-grid-4" style="gap:.8rem">
                            <div>
                                <label>Route</label>
                                <select name="route_id" required>
                                    @foreach($routes as $r)
                                        <option value="{{ $r->id }}" @selected($s->route_id === $r->id)>
                                            {{ $r->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label>Bus</label>
                                <select name="bus_id" required onchange="updateEditDriver(this, '{{ $s->id }}')">
                                    @foreach($buses as $b)
                                        <option value="{{ $b->id }}" 
                                                @selected($s->bus_id === $b->id)
                                                data-driver-id="{{ $b->driver_id }}" 
                                                data-driver-name="{{ $b->driver?->name }}">
                                            {{ $b->plate_number }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label>Assigned Driver</label>
                                <input type="text" 
                                       id="edit_driver_display_{{ $s->id }}" 
                                       readonly 
                                       value="{{ $s->driver?->name ?? $s->bus->driver?->name ?? 'No driver assigned' }}"
                                       style="background: #f3f4f6; color: #6b7280;">
                                <input type="hidden" 
                                       name="driver_id" 
                                       id="edit_driver_id_{{ $s->id }}" 
                                       value="{{ $s->driver_id ?? $s->bus->driver_id }}">
                            </div>
                            <div>
                                <label>Status</label>
                                <select name="status">
                                    @foreach(['scheduled','boarding','in_progress','delayed','completed','cancelled'] as $st)
                                        <option value="{{ $st }}" @selected($s->status === $st)>
                                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label>Date</label>
                                <input type="date" name="travel_date" value="{{ $s->travel_date->format('Y-m-d') }}" required>
                            </div>
                            <div>
                                <label>Departure</label>
                                <input type="time" name="departure_time" value="{{ $s->departure_time }}" required>
                            </div>
                            <div>
                                <label>Arrival</label>
                                <input type="time" name="arrival_time" value="{{ $s->arrival_time }}">
                            </div>
                            <div>
                                <label>Price (RWF)</label>
                                <input type="number" name="price" value="{{ (int) $s->price }}" required min="100">
                            </div>
                        </div>
                        <div style="margin-top:1rem; display: flex; gap: 0.5rem;">
                            <button type="submit" class="kbs-btn kbs-btn-primary">
                                <svg><use href="#icon-save"></use></svg>Save Changes
                            </button>
                            <button type="button" class="kbs-btn kbs-btn-ghost" onclick="toggleEdit('{{ $s->id }}')">
                                Cancel
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{ $schedules->links() }}

<script>
// Update assigned driver when bus is selected
function updateAssignedDriver() {
    const busSelect = document.getElementById('bus_id');
    const driverDisplay = document.getElementById('driver_display');
    const driverIdInput = document.getElementById('driver_id');
    const submitBtn = document.getElementById('submitBtn');
    
    if (busSelect.value) {
        const selectedOption = busSelect.selectedOptions[0];
        const driverId = selectedOption.dataset.driverId;
        const driverName = selectedOption.dataset.driverName;
        
        if (driverId && driverName) {
            driverDisplay.value = driverName;
            driverIdInput.value = driverId;
            driverDisplay.style.color = 'var(--kbs-text)';
        } else {
            driverDisplay.value = 'No driver assigned to this bus';
            driverIdInput.value = '';
            driverDisplay.style.color = '#ef4444';
        }
    } else {
        driverDisplay.value = '';
        driverIdInput.value = '';
        driverDisplay.style.color = '#6b7280';
    }
    
    // Enable/disable submit button based on form completion
    validateForm();
}

// Update driver in edit mode
function updateEditDriver(busSelect, scheduleId) {
    const selectedOption = busSelect.selectedOptions[0];
    const driverId = selectedOption.dataset.driverId;
    const driverName = selectedOption.dataset.driverName;
    
    const driverDisplay = document.getElementById(`edit_driver_display_${scheduleId}`);
    const driverIdInput = document.getElementById(`edit_driver_id_${scheduleId}`);
    
    if (driverId && driverName) {
        driverDisplay.value = driverName;
        driverIdInput.value = driverId;
        driverDisplay.style.color = 'var(--kbs-text)';
    } else {
        driverDisplay.value = 'No driver assigned to this bus';
        driverIdInput.value = '';
        driverDisplay.style.color = '#ef4444';
    }
}

// Toggle edit row visibility
function toggleEdit(scheduleId) {
    const editRow = document.getElementById(`edit-${scheduleId}`);
    if (editRow.style.display === 'none') {
        editRow.style.display = 'table-row';
    } else {
        editRow.style.display = 'none';
    }
}

// Validate form completion
function validateForm() {
    const routeId = document.getElementById('route_id').value;
    const busId = document.getElementById('bus_id').value;
    const driverId = document.getElementById('driver_id').value;
    const travelDate = document.querySelector('input[name="travel_date"]').value;
    const departureTime = document.querySelector('input[name="departure_time"]').value;
    const price = document.getElementById('price').value;
    
    const submitBtn = document.getElementById('submitBtn');
    const isValid = routeId && busId && driverId && travelDate && departureTime && price;
    
    submitBtn.disabled = !isValid;
    
    if (!isValid) {
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    }
}

// Sync route price when route is selected
function syncRoutePrice() {
    const routeSelect = document.getElementById('route_id');
    const priceInput = document.getElementById('price');
    
    if (routeSelect.value) {
        const price = routeSelect.selectedOptions[0]?.dataset.price;
        if (price) {
            priceInput.value = Math.round(Number(price));
        }
    }
    
    validateForm();
}

// Set up event listeners
document.addEventListener('DOMContentLoaded', function() {
    const routeSelect = document.getElementById('route_id');
    const busSelect = document.getElementById('bus_id');
    
    routeSelect?.addEventListener('change', syncRoutePrice);
    busSelect?.addEventListener('change', updateAssignedDriver);
    
    // Add validation to other required fields
    document.querySelectorAll('input[required], select[required]').forEach(field => {
        field.addEventListener('change', validateForm);
        field.addEventListener('input', validateForm);
    });
    
    // Initialize form state
    syncRoutePrice();
    updateAssignedDriver();
    validateForm();
});
</script>
@endsection
