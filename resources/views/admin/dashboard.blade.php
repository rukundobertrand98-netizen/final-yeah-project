@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}" class="active">Analytics</a>
    <a href="{{ route('admin.users') }}">Users</a>
    <a href="{{ route('admin.monitor') }}">Monitor Buses</a>
    <a href="{{ route('admin.payments') }}">Payments</a>
    <a href="{{ route('admin.complaints') }}">Complaints</a>
@endsection

@section('panel')
<h1>System Administrator — KBS</h1>
<div class="kbs-grid kbs-grid-3">
    <div class="kbs-card kbs-stat"><strong>{{ $stats['passengers'] }}</strong><span>Passengers</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['operators'] }}</strong><span>Operators</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['drivers'] }}</strong><span>Drivers</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['bookings_today'] }}</strong><span>Bookings Today</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['revenue']) }}</strong><span>Total Revenue</span></div>
    <div class="kbs-card kbs-stat"><strong>{{ $stats['active_trips'] }}</strong><span>Active Trips</span></div>
</div>
@endsection
