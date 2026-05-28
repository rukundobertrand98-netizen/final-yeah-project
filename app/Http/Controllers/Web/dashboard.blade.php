@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}" class="active">Dashboard</a>
    <a href="{{ route('admin.users') }}">Users</a>
    <a href="{{ route('admin.monitor') }}">Monitor</a>
    <a href="{{ route('admin.payments') }}">Payments</a>
    <a href="{{ route('admin.complaints') }}">Complaints</a>
@endsection

@section('panel')
    <h1>System Overview</h1>
    
    <div class="kbs-grid kbs-grid-3" style="margin-bottom: 1.5rem">
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['passengers']) }}</strong><span>Passengers</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['operators']) }}</strong><span>Operators</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['drivers']) }}</strong><span>Drivers</span></div>
    </div>

    <div class="kbs-grid kbs-grid-3">
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['bookings_today']) }}</strong><span>Bookings Today</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['revenue']) }} RWF</strong><span>Total Revenue</span></div>
        <div class="kbs-card kbs-stat"><strong>{{ number_format($stats['active_trips']) }}</strong><span>Active Trips</span></div>
    </div>
@endsection