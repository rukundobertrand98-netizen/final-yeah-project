@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}" class="active">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="margin-bottom:1.5rem;">
    <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">💳 Payments</h1>
    <p style="color:var(--kbs-muted);margin:0;">Full passenger payment records with route, origin and destination details.</p>
</div>

{{-- Summary Cards --}}
<div class="kbs-grid kbs-grid-3" style="margin-bottom:1.5rem;">
    <div class="kbs-card kbs-stat" style="padding:1.2rem 1.2rem 1.2rem 1.7rem;">
        <strong>{{ number_format($payments->total()) }}</strong>
        <span>Total Transactions</span>
    </div>
    <div class="kbs-card kbs-stat kbs-stat-green" style="border:none;padding:1.2rem 1.2rem 1.2rem 1.7rem;">
        <strong style="font-size:1.35rem;">{{ number_format($payments->where('status','successful')->sum('amount')) }}</strong>
        <span>Successful (RWF)</span>
    </div>
    <div class="kbs-card kbs-stat kbs-stat-yellow" style="border:none;padding:1.2rem 1.2rem 1.2rem 1.7rem;">
        <strong>{{ $payments->where('status','pending')->count() }}</strong>
        <span>Pending Payments</span>
    </div>
</div>

<div class="kbs-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger</th>
                    <th>Contact</th>
                    <th>Route</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Bus</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td style="color:var(--kbs-muted);font-size:.82rem;">{{ $p->id }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--kbs-green),var(--kbs-yellow));
                                        display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.82rem;flex-shrink:0;">
                                {{ strtoupper(substr($p->booking?->user?->name ?? 'U',0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:.9rem;">{{ $p->booking?->user?->name ?? '—' }}</div>
                                <div style="font-size:.76rem;color:var(--kbs-muted);">ID: {{ $p->booking?->user?->id ?? '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:.85rem;">
                        <div>{{ $p->booking?->user?->email ?? '—' }}</div>
                        @if($p->booking?->user?->phone)
                            <div style="color:var(--kbs-muted);">{{ $p->booking->user->phone }}</div>
                        @endif
                        @if($p->payer_phone)
                            <div style="color:var(--kbs-muted);">📱 {{ $p->payer_phone }}</div>
                        @endif
                    </td>
                    <td style="font-size:.88rem;">
                        <strong>{{ $p->booking?->schedule?->route?->name ?? '—' }}</strong>
                        <div style="font-size:.76rem;color:var(--kbs-muted);">
                            {{ $p->booking?->schedule?->travel_date?->format('d M Y') }}
                            {{ $p->booking?->schedule?->departure_time ? '· '.$p->booking->schedule->departure_time : '' }}
                        </div>
                    </td>
                    <td>
                        <span class="kbs-badge kbs-badge-success">
                            📍 {{ $p->booking?->originStop?->name ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <span class="kbs-badge kbs-badge-info">
                            🏁 {{ $p->booking?->destinationStop?->name ?? '—' }}
                        </span>
                    </td>
                    <td style="font-size:.85rem;">
                        {{ $p->booking?->schedule?->bus?->plate_number ?? '—' }}
                    </td>
                    <td>
                        <strong style="color:var(--kbs-green-dark);">{{ number_format($p->amount) }}</strong>
                        <span style="font-size:.78rem;color:var(--kbs-muted);">{{ $p->currency }}</span>
                    </td>
                    <td>
                        @php $sm=['successful'=>'success','pending'=>'warning','failed'=>'danger']; @endphp
                        <span class="kbs-badge kbs-badge-{{ $sm[$p->status] ?? 'default' }}">{{ ucfirst($p->status) }}</span>
                    </td>
                    <td style="font-size:.82rem;color:var(--kbs-muted);white-space:nowrap;">
                        {{ $p->created_at->format('d M Y') }}<br>{{ $p->created_at->format('H:i') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:3rem;color:var(--kbs-muted);">
                        <div style="font-size:2.5rem;margin-bottom:.5rem;">💳</div>
                        No payment records found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem 1.35rem;border-top:1px solid var(--kbs-border);">
        {{ $payments->links() }}
    </div>
</div>
@endsection
