@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}" class="active">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="margin-bottom:1.5rem;">
    <a href="{{ route('admin.buses') }}" style="color:var(--kbs-muted);font-size:.88rem;">← Back to Buses</a>
    <h1 style="margin:.5rem 0 .3rem;color:var(--kbs-green-dark);">Add New Bus</h1>
    <p style="color:var(--kbs-muted);margin:0;">Each driver can only be assigned to one bus at a time.</p>
</div>

@if($errors->any())
    <div class="kbs-alert kbs-alert-error" style="margin-bottom:1.5rem;">
        <strong>Please fix the following errors:</strong>
        <ul style="margin:.5rem 0 0;padding-left:1.2rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="kbs-card kbs-form" style="max-width:640px;">
    <form method="POST" action="{{ route('admin.buses.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1.2rem;">
            <div>
                <label>Plate Number *</label>
                <input type="text" name="plate_number" value="{{ old('plate_number') }}" required placeholder="e.g. RAB 123A">
            </div>
            <div>
                <label>Fleet Number</label>
                <input type="text" name="fleet_number" value="{{ old('fleet_number') }}" placeholder="optional">
            </div>
            <div>
                <label>Capacity (seats) *</label>
                <input type="number" name="capacity" value="{{ old('capacity', 40) }}" required min="1">
            </div>
            <div>
                <label>Rows *</label>
                <input type="number" name="rows" value="{{ old('rows', 10) }}" required min="1">
            </div>
            <div>
                <label>Seats per Row *</label>
                <input type="number" name="seats_per_row" value="{{ old('seats_per_row', 4) }}" required min="1">
            </div>
            <div>
                <label>Model / Make</label>
                <input type="text" name="model" value="{{ old('model') }}" placeholder="e.g. Toyota Coaster">
            </div>
        </div>

        <label>Operator *</label>
        <select name="operator_id" required>
            <option value="">— Select an operator —</option>
            @foreach($operators as $op)
                <option value="{{ $op->id }}" {{ old('operator_id') == $op->id ? 'selected' : '' }}>
                    {{ $op->name }} ({{ $op->email }})
                </option>
            @endforeach
        </select>

        <label>Assign Driver *
            <span style="font-weight:400;color:var(--kbs-muted);font-size:.8rem;">— only unassigned drivers are listed</span>
        </label>
        <select name="driver_id" required>
            <option value="">— Select a driver —</option>
            @foreach($drivers as $driver)
                <option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>
                    {{ $driver->name }} ({{ $driver->email }})
                </option>
            @endforeach
        </select>
        @if($drivers->isEmpty())
            <p style="color:var(--kbs-warning);font-size:.85rem;margin-top:-.5rem;margin-bottom:1rem;">
                ⚠️ No available drivers. All drivers are already assigned to a bus.
            </p>
        @endif

        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="submit" class="kbs-btn kbs-btn-primary">Create Bus</button>
            <a href="{{ route('admin.buses') }}" class="kbs-btn kbs-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
