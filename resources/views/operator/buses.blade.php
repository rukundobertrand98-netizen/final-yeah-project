@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}" class="active">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Manage Buses</h1>
<div class="kbs-grid kbs-grid-2">
    <div class="kbs-card kbs-form">
        <h3>Add Bus</h3>
        <form method="POST" action="{{ route('operator.buses.store') }}">
            @csrf
            <label>Plate Number</label><input name="plate_number" required>
            <label>Fleet Number</label><input name="fleet_number">
            <label>Capacity</label><input type="number" name="capacity" value="40" required>
            <label>Rows</label><input type="number" name="rows" value="10" required>
            <label>Seats per Row</label><input type="number" name="seats_per_row" value="4" required>
            <label>Model</label><input name="model">
            <button class="kbs-btn kbs-btn-primary">Add Bus</button>
        </form>
    </div>
    <div class="kbs-card">
        <table class="kbs-table">
            <thead><tr><th>Plate</th><th>Capacity</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($buses as $bus)
                <tr><td>{{ $bus->plate_number }}</td><td>{{ $bus->capacity }}</td><td>{{ $bus->status }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
