@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}" class="active">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="margin-bottom:1.75rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="margin:0 0 .3rem;font-size:2rem;color:var(--kbs-green-dark);">Admin Dashboard</h1>
        <p style="color:var(--kbs-muted);margin:0;">Welcome back, <strong>{{ auth()->user()->name }}</strong> — here's today's overview.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="{{ route('admin.buses.create') }}" class="kbs-btn kbs-btn-primary">+ Add Bus</a>
        <a href="{{ route('admin.users.create') }}" class="kbs-btn kbs-btn-ghost">+ Add User</a>
    </div>
</div>

{{-- ── KPI Cards ── --}}
<div class="kbs-grid kbs-grid-4" style="margin-bottom:1.5rem;">
    <div class="kbs-card kbs-stat kbs-stat-green" style="border:none;">
        <div style="font-size:1.65rem;margin-bottom:.3rem;">👥</div>
        <strong>{{ $stats['total_users'] }}</strong>
        <span>Total Users</span>
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.22);font-size:.82rem;color:rgba(255,255,255,.82);">
            +{{ $stats['new_users_today'] }} registered today
        </div>
    </div>
    <div class="kbs-card kbs-stat kbs-stat-yellow" style="border:none;">
        <div style="font-size:1.65rem;margin-bottom:.3rem;">🚌</div>
        <strong>{{ $stats['total_buses'] }}</strong>
        <span>Total Buses</span>
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(20,95,56,.2);font-size:.82rem;color:rgba(20,95,56,.72);">
            {{ $stats['active_buses'] }} active right now
        </div>
    </div>
    <div class="kbs-card kbs-stat kbs-stat-green" style="border:none;">
        <div style="font-size:1.65rem;margin-bottom:.3rem;">🚦</div>
        <strong>{{ $stats['active_trips'] }}</strong>
        <span>Active Trips</span>
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.22);font-size:.82rem;color:rgba(255,255,255,.82);">
            {{ $stats['trips_today'] }} trips today
        </div>
    </div>
    <div class="kbs-card kbs-stat kbs-stat-yellow" style="border:none;">
        <div style="font-size:1.65rem;margin-bottom:.3rem;">💰</div>
        <strong style="font-size:1.4rem;">{{ number_format($stats['revenue']) }}</strong>
        <span>Revenue (RWF)</span>
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(20,95,56,.2);font-size:.82rem;color:rgba(20,95,56,.72);">
            {{ number_format($stats['revenue_today']) }} RWF today
        </div>
    </div>
</div>

{{-- ── Secondary Stats ── --}}
<div class="kbs-grid kbs-grid-4" style="margin-bottom:1.5rem;">
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-green-dark);">{{ $stats['bookings_today'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);margin-top:.25rem;">Bookings Today</div>
        <div style="font-size:.78rem;color:var(--kbs-muted);margin-top:.2rem;">Total: {{ number_format($stats['total_bookings']) }}</div>
    </div>
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:{{ $stats['pending_complaints'] > 0 ? 'var(--kbs-danger)' : 'var(--kbs-green-dark)' }};">{{ $stats['pending_complaints'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);margin-top:.25rem;">Pending Complaints</div>
        <div style="font-size:.78rem;color:var(--kbs-muted);margin-top:.2rem;">Resolved: {{ $stats['resolved_complaints'] }}</div>
    </div>
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-green-dark);">{{ $stats['total_routes'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);margin-top:.25rem;">Routes</div>
        <div style="font-size:.78rem;color:var(--kbs-muted);margin-top:.2rem;">Stops: {{ $stats['total_stops'] }}</div>
    </div>
    <div class="kbs-card" style="text-align:center;padding:1.2rem;">
        <div style="font-size:2rem;font-weight:800;color:var(--kbs-warning);">{{ $stats['pending_operators'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);margin-top:.25rem;">Pending Approvals</div>
        <div style="font-size:.78rem;color:var(--kbs-muted);margin-top:.2rem;">Completed today: {{ $stats['completed_trips_today'] }}</div>
    </div>
</div>

{{-- ── Charts Row ── --}}
<div class="kbs-grid kbs-grid-2" style="margin-bottom:1.5rem;">
    {{-- User Roles Distribution (donut-style CSS) --}}
    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">👥 User Role Distribution</h3>
        @php
            $roles = [
                ['label'=>'Passengers','count'=>$stats['passengers'],'color'=>'var(--kbs-green)'],
                ['label'=>'Operators','count'=>$stats['operators'],'color'=>'var(--kbs-yellow-dark)'],
                ['label'=>'Drivers','count'=>$stats['drivers'],'color'=>'var(--kbs-green-mid)'],
                ['label'=>'Admins','count'=>$stats['admins'],'color'=>'#1565c0'],
            ];
            $total = max(1,$stats['total_users']);
        @endphp
        <div style="display:flex;flex-direction:column;gap:.9rem;">
            @foreach($roles as $r)
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.35rem;">
                    <span style="font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:.5rem;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $r['color'] }};"></span>
                        {{ $r['label'] }}
                    </span>
                    <span style="font-weight:800;color:{{ $r['color'] }};">{{ $r['count'] }}</span>
                </div>
                <div class="kbs-progress-track">
                    <div class="kbs-progress-bar" style="width:{{ round($r['count']/$total*100) }}%;background:{{ $r['color'] }};"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- System Usage Bar Chart ── --}}
    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">📊 System Usage</h3>
        @php $maxVal = max(1, max(array_values($chartData['usage']))); @endphp
        <div style="display:flex;align-items:flex-end;gap:10px;height:100px;margin-bottom:.75rem;">
            @foreach($chartData['usage'] as $label => $val)
            @php $pct = max(4, round($val/$maxVal*100)); @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.3rem;height:100%;">
                <div style="font-size:.7rem;font-weight:800;color:var(--kbs-green-dark);">{{ number_format($val) }}</div>
                <div style="flex:1;width:100%;display:flex;align-items:flex-end;">
                    <div title="{{ $label }}: {{ $val }}" style="width:100%;height:{{ $pct }}%;border-radius:5px 5px 0 0;background:linear-gradient(180deg,var(--kbs-yellow),var(--kbs-green));transition:height .6s ease;"></div>
                </div>
            </div>
            @endforeach
        </div>
        <div style="display:flex;gap:10px;justify-content:space-around;">
            @foreach($chartData['usage'] as $label => $val)
            <div style="text-align:center;font-size:.72rem;color:var(--kbs-muted);font-weight:700;">{{ $label }}</div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Revenue + Active Trips ── --}}
<div class="kbs-grid kbs-grid-2" style="margin-bottom:1.5rem;">
    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">💰 Revenue Snapshot</h3>
        @foreach($chartData['money'] as $label => $amount)
        <div style="padding:1rem;background:var(--kbs-green-light);border-radius:8px;margin-bottom:.75rem;
                    border-left:4px solid {{ $loop->first ? 'var(--kbs-green)' : 'var(--kbs-yellow-dark)' }};">
            <div style="font-size:.82rem;color:var(--kbs-muted);margin-bottom:.3rem;font-weight:700;">{{ $label }} Revenue</div>
            <div style="font-size:1.45rem;font-weight:800;color:var(--kbs-green-dark);">{{ number_format($amount) }} <span style="font-size:.85rem;font-weight:600;">RWF</span></div>
        </div>
        @endforeach
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.5rem;">
            @foreach($chartData['operations'] as $label => $val)
            <div style="padding:.75rem;background:#fff;border:1px solid var(--kbs-border);border-radius:8px;text-align:center;">
                <div style="font-size:1.4rem;font-weight:800;color:var(--kbs-green-dark);">{{ $val }}</div>
                <div style="font-size:.75rem;color:var(--kbs-muted);margin-top:.15rem;">{{ $label }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">🚦 Active Trips Right Now</h3>
        @if($active_trips->count() > 0)
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                @foreach($active_trips->take(5) as $trip)
                <div style="padding:.9rem;background:var(--kbs-green-light);border-radius:8px;
                            border-left:4px solid var(--kbs-green);
                            transition:background var(--kbs-transition);">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.35rem;">
                        <div style="font-weight:700;color:var(--kbs-green-dark);">{{ $trip->bus->plate_number ?? 'N/A' }}</div>
                        <span class="kbs-badge kbs-badge-success">{{ ucfirst($trip->status) }}</span>
                    </div>
                    <div style="font-size:.88rem;color:var(--kbs-ink);">{{ $trip->route->name ?? 'Route N/A' }}</div>
                    <div style="font-size:.82rem;color:var(--kbs-muted);margin-top:.2rem;">
                        Driver: {{ $trip->driver->name ?? 'N/A' }} · {{ $trip->bookings_count ?? 0 }} passengers
                    </div>
                </div>
                @endforeach
            </div>
            @if($active_trips->count() > 5)
            <div style="margin-top:1rem;text-align:center;">
                <a href="{{ route('admin.trips') }}" class="kbs-btn kbs-btn-sm kbs-btn-ghost">
                    View all {{ $active_trips->count() }} trips →
                </a>
            </div>
            @endif
        @else
        <div style="text-align:center;padding:2rem 1rem;color:var(--kbs-muted);">
            <div style="font-size:2.5rem;margin-bottom:.5rem;">🚌</div>
            <div>No active trips at the moment</div>
        </div>
        @endif
    </div>
</div>

{{-- ── Recent Activity + Quick Actions ── --}}
<div class="kbs-grid kbs-grid-2" style="margin-bottom:1.5rem;">
    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">⚡ Recent Activity</h3>
        <div style="display:flex;flex-direction:column;gap:0;">
            @foreach($recent_activities as $activity)
            <div style="display:flex;gap:.9rem;padding:.85rem 0;border-bottom:1px solid var(--kbs-border);">
                <div style="flex-shrink:0;width:38px;height:38px;border-radius:50%;
                            background:var(--kbs-green-light);display:flex;align-items:center;
                            justify-content:center;font-size:1.15rem;">{{ $activity['icon'] }}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:.88rem;color:var(--kbs-ink);">{{ $activity['title'] }}</div>
                    <div style="font-size:.82rem;color:var(--kbs-muted);margin-top:.15rem;">{{ $activity['description'] }}</div>
                    <div style="font-size:.76rem;color:var(--kbs-muted);margin-top:.1rem;">{{ $activity['time'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="kbs-card" style="padding:1.4rem;">
        <h3 style="margin:0 0 1.2rem;color:var(--kbs-green-dark);">⚡ Quick Actions</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <a href="{{ route('admin.users.create') }}" class="kbs-btn kbs-btn-primary" style="flex-direction:column;gap:.4rem;padding:1.1rem;height:auto;">
                <span style="font-size:1.6rem;">👤</span><span style="font-size:.85rem;">Add User</span>
            </a>
            <a href="{{ route('admin.buses.create') }}" class="kbs-btn" style="flex-direction:column;gap:.4rem;padding:1.1rem;height:auto;background:var(--kbs-green);color:#fff;">
                <span style="font-size:1.6rem;">🚌</span><span style="font-size:.85rem;">Add Bus</span>
            </a>
            <a href="{{ route('admin.users') }}?pending=1" class="kbs-btn kbs-btn-ghost" style="flex-direction:column;gap:.4rem;padding:1.1rem;height:auto;">
                <span style="font-size:1.6rem;">✅</span><span style="font-size:.85rem;">Approve Operators</span>
            </a>
            <a href="{{ route('admin.reports') }}" class="kbs-btn kbs-btn-ghost" style="flex-direction:column;gap:.4rem;padding:1.1rem;height:auto;">
                <span style="font-size:1.6rem;">📊</span><span style="font-size:.85rem;">View Reports</span>
            </a>
        </div>
    </div>
</div>
@endsection
