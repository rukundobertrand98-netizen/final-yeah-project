@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}" class="active">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Manage Passengers</h1>
<div class="kbs-card">
    <table class="kbs-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th></tr>
        </thead>
        <tbody>
            @foreach($passengers as $passenger)
                <tr>
                    <td>{{ $passenger->name }}</td>
                    <td>{{ $passenger->email }}</td>
                    <td>{{ $passenger->phone }}</td>
                    <td>{{ $passenger->operator_bookings_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $passengers->links() }}
</div>
@endsection
