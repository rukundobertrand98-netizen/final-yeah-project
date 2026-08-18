@extends('layouts.dashboard')

@section('title', 'Operator Dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}" class="active">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
    <h1>Welcome, {{ auth()->user()->name }}</h1>

    <div class="kbs-grid kbs-grid-3" style="margin-bottom: 1.5rem">
        <div class="kbs-card kbs-stat"><strong>{{ $stats['buses'] }}</strong><span>My Buses</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ $stats['routes'] }}</strong><span>My Routes</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['bookings_today']) }}</strong><span>Bookings Today</span></div>
    </div>

    <div class="kbs-grid kbs-grid-3" style="margin-bottom: 2rem">
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['revenue']) }} RWF</strong><span>Total Revenue</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['passengers']) }}</strong><span>Total Passengers</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['payments']) }}</strong><span>Payments Handled</span></div>
    </div>

    <div class="kbs-grid kbs-grid-2">
        <div class="kbs-card">
            <h3>Recent Bookings</h3>
            <table class="kbs-table">
                <thead><tr><th>Ref</th><th>Passenger</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($recentBookings as $b)
                    <tr>
                        <td>{{ $b->reference }}</td>
                        <td>{{ $b->user->name }}</td>
                        <td>{{ $b->status }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="kbs-card">
            <h3>Active Trips</h3>
            <table class="kbs-table">
                <thead><tr><th>Route</th><th>Bus</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($activeTrips as $trip)
                    <tr>
                        <td>{{ $trip->route->name }}</td>
                        <td>{{ $trip->bus->plate_number }}</td>
                        <td>{{ $trip->status }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
