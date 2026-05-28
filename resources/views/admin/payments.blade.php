@extends('layouts.dashboard')

@section('panel')
<h1>Payments</h1>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>ID</th><th>Passenger</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        @foreach($payments as $p)
            <tr>
                <td>{{ $p->id }}</td>
                <td>{{ $p->booking->user->name ?? '-' }}</td>
                <td>{{ number_format($p->amount) }} {{ $p->currency }}</td>
                <td>{{ $p->method }}</td>
                <td>{{ $p->status }}</td>
                <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
