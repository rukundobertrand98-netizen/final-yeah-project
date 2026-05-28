@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('operator.dashboard') }}">Dashboard</a>
    <a href="{{ route('operator.buses') }}">Buses</a>
    <a href="{{ route('operator.routes') }}">Routes</a>
    <a href="{{ route('operator.schedules') }}">Schedules</a>
    <a href="{{ route('operator.bookings') }}">Bookings</a>
    <a href="{{ route('operator.payments') }}" class="active">Payments</a>
    <a href="{{ route('operator.passengers') }}">Passengers</a>
    <a href="{{ route('operator.reports') }}">Reports</a>
@endsection

@section('panel')
<h1>Monitor Payments</h1>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Booking</th><th>Passenger</th><th>Route</th><th>Method</th><th>Phone</th><th>Amount</th><th>Status</th><th>Paid At</th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->booking->reference }}</td>
                <td>{{ $payment->booking->user->name }}</td>
                <td>{{ $payment->booking->schedule->route->name }}</td>
                <td>{{ strtoupper(str_replace('_', ' ', $payment->method)) }}</td>
                <td>{{ $payment->payer_phone ?? '-' }}</td>
                <td>{{ number_format($payment->amount) }} {{ $payment->currency }}</td>
                <td>{{ $payment->status }}</td>
                <td>{{ $payment->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
