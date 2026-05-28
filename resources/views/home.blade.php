@extends('layouts.app')

@section('title', 'Home')

@section('hero')
<section class="kbs-hero">
    <h1>Kigali Public Transport — Book Your KBS Bus Online</h1>
    <p>Search routes across Kigali, choose your seat, pay with MTN MoMo, and travel with a digital QR ticket. Real-time bus tracking included.</p>
    <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap">
        <a href="{{ route('register') }}" class="kbs-btn kbs-btn-primary">Get Started</a>
        @auth
            <a href="{{ route('passenger.search') }}" class="kbs-btn kbs-btn-outline">Search Routes</a>
        @else
            <a href="{{ route('login') }}" class="kbs-btn kbs-btn-outline">Login to Book</a>
        @endauth
    </div>
</section>
@endsection

@section('content')
<div class="kbs-grid kbs-grid-4" style="margin-bottom:2rem">
    <div class="kbs-card kbs-stat"><strong>24/7</strong><span>Online Booking</span></div>
    <div class="kbs-card kbs-stat"><strong>MTN</strong><span>MoMo Payments</span></div>
    <div class="kbs-card kbs-stat"><strong>QR</strong><span>Digital Tickets</span></div>
    <div class="kbs-card kbs-stat"><strong>GPS</strong><span>Live Bus Tracking</span></div>
</div>

<h2 style="margin-bottom:1rem">Popular Kigali Routes</h2>
<div class="kbs-grid kbs-grid-3">
    @forelse($featuredRoutes as $route)
        <div class="kbs-card">
            <span class="kbs-badge kbs-badge-info">{{ $route->code }}</span>
            <h3 style="margin:.5rem 0">{{ $route->name }}</h3>
            <p style="color:var(--kbs-muted);margin:0">
                {{ $route->originStop->name }} → {{ $route->destinationStop->name }}
            </p>
        </div>
    @empty
        <p>Routes will appear after system setup.</p>
    @endforelse
</div>
@endsection
