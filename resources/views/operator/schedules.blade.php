@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}" class="active">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Schedules & Prices</h1>
<div class="kbs-card kbs-form" style="margin-bottom:1.5rem">
    <form method="POST" action="{{ route('operator.schedules.store') }}">
        @csrf
        <div class="kbs-grid kbs-grid-3">
            <div><label>Route</label><select name="route_id" id="route_id">@foreach($routes as $r)<option value="{{ $r->id }}" data-price="{{ $r->base_price }}">{{ $r->name }}</option>@endforeach</select></div>
            <div><label>Bus</label><select name="bus_id">@foreach($buses as $b)<option value="{{ $b->id }}">{{ $b->plate_number }}</option>@endforeach</select></div>
            <div><label>Driver</label><select name="driver_id"><option value="">—</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
            <div><label>Date</label><input type="date" name="travel_date" required></div>
            <div><label>Departure</label><input type="time" name="departure_time" required></div>
            <div><label>Arrival</label><input type="time" name="arrival_time"></div>
            <div><label>Price (RWF)</label><input type="number" name="price" id="price" value="500" required></div>
        </div>
        <button class="kbs-btn kbs-btn-primary">Add Schedule</button>
    </form>
</div>
<table class="kbs-table kbs-card">
    <thead><tr><th>Date</th><th>Route</th><th>Bus</th><th>Driver</th><th>Price</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($schedules as $s)
        <tr>
            <td>{{ $s->travel_date->format('d/m/Y') }}</td>
            <td>{{ $s->route->name }}</td>
            <td>{{ $s->bus->plate_number }}</td>
            <td>{{ $s->driver?->name ?? '—' }}</td>
            <td>{{ number_format($s->price) }}</td>
            <td>{{ $s->status }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $schedules->links() }}
<script>
    const routeSelect = document.getElementById('route_id');
    const priceInput = document.getElementById('price');
    function syncRoutePrice() {
        const price = routeSelect?.selectedOptions[0]?.dataset.price;
        if (price) priceInput.value = Math.round(Number(price));
    }
    routeSelect?.addEventListener('change', syncRoutePrice);
    syncRoutePrice();
</script>
@endsection
