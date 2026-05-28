@extends('layouts.dashboard')

@section('title', 'Passenger Dashboard')
@section('sidebar')
    <a href="{{ route('passenger.dashboard') }}" class="active">Overview</a>
    <a href="{{ route('passenger.search') }}">Search & Book</a>
    <a href="{{ route('passenger.bookings') }}">My Tickets</a>
    <a href="{{ route('passenger.complaints.create') }}">Complaint</a>
@endsection

@section('panel')
<h1>Welcome, {{ auth()->user()->name }}</h1>
<div id="alertsContainer">
@if($alerts->count())
    <div class="kbs-card" style="margin:1rem 0;border-left:4px solid var(--kbs-accent)">
        <strong>Bus approaching your stop</strong>
        @foreach($alerts as $alert)
            <p style="margin:.5rem 0">{{ $alert->message }}</p>
        @endforeach
    </div>
@endif
</div>
<h2>Recent Bookings</h2>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Ref</th><th>Route</th><th>Seat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($bookings as $b)
            <tr>
                <td>{{ $b->reference }}</td>
                <td>{{ $b->schedule->route->name ?? '-' }}</td>
                <td>{{ $b->seat_number }}</td>
                <td><span class="kbs-badge kbs-badge-{{ $b->status === 'confirmed' ? 'success' : 'warning' }}">{{ $b->status }}</span></td>
                <td>
                    @if($b->status === 'confirmed')
                        <a href="{{ route('passenger.ticket', $b) }}">Ticket</a> ·
                        <a href="{{ route('passenger.track', $b) }}">Track</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No bookings yet. <a href="{{ route('passenger.search') }}">Book a trip</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@push('scripts')
<script>
(function () {
    const alertsUrl = @json(route('passenger.alerts'));
    const container = document.getElementById('alertsContainer');

    async function pollAlerts() {
        try {
            const res = await fetch(alertsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const alerts = await res.json();
            if (!alerts.length) return;

            let html = '<div class="kbs-card" style="margin:1rem 0;border-left:4px solid var(--kbs-accent)">';
            html += '<strong>Bus approaching your stop</strong>';
            alerts.forEach(alert => {
                html += `<p style="margin:.5rem 0">${alert.message}</p>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } catch (e) {}
    }

    setInterval(pollAlerts, 15000);
})();
</script>
@endpush
@endsection
