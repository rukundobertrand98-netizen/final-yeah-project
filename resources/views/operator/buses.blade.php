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
        <h3>Bus management is reserved for admins</h3>
        <p>Only administrators can add or assign new buses. Your operator dashboard can still display and manage existing buses owned by your operator account.</p>
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
