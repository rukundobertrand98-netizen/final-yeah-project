@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}" class="active">Reports</a>
@endsection

@section('panel')
<h1>Operator Reports</h1>
<div class="kbs-grid kbs-grid-4" style="margin-bottom:1.5rem">
    <div class="kbs-card kbs-stat"><strong>{{ $stats['scheduled_trips'] }}</strong><span>Scheduled Trips</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['confirmed_bookings'] }}</strong><span>Confirmed Bookings</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['boarded_passengers'] }}</strong><span>Boarded Passengers</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['revenue']) }}</strong><span>Revenue (RWF)</span></div>
</div>

<div class="kbs-card">
    <h2>Route Activity</h2>
    <table class="kbs-table">
        <thead><tr><th>Route</th><th>Schedules</th><th>Base Price</th></tr></thead>
        <tbody>
            @foreach($routes as $route)
                <tr>
                    <td>{{ $route->name }}</td>
                    <td>{{ $route->schedules_count }}</td>
                    <td>{{ number_format($route->base_price ?? 0) }} RWF</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
