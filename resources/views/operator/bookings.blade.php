@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}" class="active">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Monitor Bookings</h1>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Ref</th><th>Passenger</th><th>Bus</th><th>Route</th><th>Seat</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($bookings as $b)
            <tr>
                <td>{{ $b->reference }}</td>
                <td>{{ $b->user->name }}</td>
                <td>{{ $b->schedule->bus->plate_number }}</td>
                <td>{{ $b->schedule->route->name }}</td>
                <td>{{ $b->seat_number }}</td>
                <td>{{ number_format($b->amount) }}</td>
                <td>{{ $b->payment?->status ?? 'not started' }}</td>
                <td>{{ $b->status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $bookings->links() }}
</div>
@endsection
