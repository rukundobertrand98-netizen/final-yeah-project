@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}" class="active">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">Fleet Management</h1>
        <p style="color:var(--kbs-muted);margin:0;">Manage all buses — each driver may only be assigned to one bus.</p>
    </div>
    <a href="{{ route('admin.buses.create') }}" class="kbs-btn kbs-btn-primary">+ Add New Bus</a>
</div>

@if(session('success'))
    <div class="kbs-alert kbs-alert-success" style="margin-bottom:1.5rem;">✅ {{ session('success') }}</div>
@endif

<div class="kbs-card" style="padding:0;overflow:hidden;">
    <table class="kbs-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Plate Number</th>
                <th>Model</th>
                <th>Capacity</th>
                <th>Driver Assigned</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($buses as $bus)
            <tr>
                <td style="color:var(--kbs-muted);font-size:.85rem;">{{ $bus->id }}</td>
                <td>
                    <strong style="color:var(--kbs-green-dark);">{{ $bus->plate_number }}</strong>
                    @if($bus->fleet_number)
                        <div style="font-size:.78rem;color:var(--kbs-muted);">Fleet: {{ $bus->fleet_number }}</div>
                    @endif
                </td>
                <td>{{ $bus->model ?? '—' }}</td>
                <td>
                    <span class="kbs-badge kbs-badge-default">{{ $bus->capacity }} seats</span>
                </td>
                <td>
                    @if($bus->driver)
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--kbs-green),var(--kbs-yellow));
                                        display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.85rem;flex-shrink:0;">
                                {{ strtoupper(substr($bus->driver->name,0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:.9rem;">{{ $bus->driver->name }}</div>
                                <div style="font-size:.76rem;color:var(--kbs-muted);">{{ $bus->driver->email }}</div>
                            </div>
                        </div>
                    @else
                        <span class="kbs-badge kbs-badge-warning">Unassigned</span>
                    @endif
                </td>
                <td>
                    @php
                        $statusMap = ['active'=>'success','inactive'=>'danger','maintenance'=>'warning'];
                        $s = $bus->status ?? 'active';
                    @endphp
                    <span class="kbs-badge kbs-badge-{{ $statusMap[$s] ?? 'default' }}">{{ ucfirst($s) }}</span>
                </td>
                <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                        <a href="{{ route('admin.buses.edit', $bus) }}" class="kbs-btn kbs-btn-sm kbs-btn-info">✏️ Edit</a>
                        <form action="{{ route('admin.buses.delete', $bus) }}" method="POST" style="display:inline-block;"
                              onsubmit="return confirm('Delete bus {{ $bus->plate_number }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="kbs-btn kbs-btn-sm kbs-btn-danger">🗑️ Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:3rem;color:var(--kbs-muted);">
                    <div style="font-size:2.5rem;margin-bottom:.5rem;">🚌</div>
                    No buses registered yet.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:1rem 1.35rem;border-top:1px solid var(--kbs-border);">
        {{ $buses->links() }}
    </div>
</div>
@endsection
