@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}" class="active">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">👥 User Management</h1>
        <p style="color:var(--kbs-muted);margin:0;">Manage all system users and their roles.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="kbs-btn kbs-btn-primary">+ Add New User</a>
</div>

{{-- Filter Tabs --}}
<div class="kbs-card" style="padding:.8rem;margin-bottom:1.25rem;">
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        @php
            $tabs = [
                '' => 'All Users',
                'passenger' => '👤 Passengers',
                'operator' => '🏢 Operators',
                'driver' => '🚗 Drivers',
                'admin' => '👑 Admins',
            ];
        @endphp
        @foreach($tabs as $val => $label)
        <a href="{{ route('admin.users') }}{{ $val ? '?role='.$val : '' }}"
           style="padding:.45rem .95rem;border-radius:999px;text-decoration:none;font-weight:700;font-size:.86rem;
                  transition:all .2s;
                  {{ (!request('role') && !request('pending') && $val === '') || request('role') === $val
                     ? 'background:var(--kbs-green);color:#fff;'
                     : 'background:var(--kbs-green-light);color:var(--kbs-green-dark);' }}">
            {{ $label }}
        </a>
        @endforeach
        <a href="{{ route('admin.users') }}?pending=1"
           style="padding:.45rem .95rem;border-radius:999px;text-decoration:none;font-weight:700;font-size:.86rem;
                  transition:all .2s;
                  {{ request('pending') ? 'background:var(--kbs-yellow-dark);color:#fff;' : 'background:var(--kbs-yellow-light);color:var(--kbs-green-dark);' }}">
            ⏳ Pending Approvals
        </a>
    </div>
</div>

@if(session('success'))
    <div class="kbs-alert kbs-alert-success" style="margin-bottom:1.25rem;">✅ {{ session('success') }}</div>
@endif

<div class="kbs-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th style="text-align:center;">Status</th>
                    <th style="text-align:center;">Joined</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:.65rem;">
                            <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                                        background:linear-gradient(135deg,var(--kbs-green),var(--kbs-yellow));
                                        display:flex;align-items:center;justify-content:center;
                                        color:#fff;font-weight:800;font-size:.95rem;">
                                {{ strtoupper(substr($user->name,0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:700;">{{ $user->name }}</div>
                                <div style="font-size:.76rem;color:var(--kbs-muted);">#{{ $user->id }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:.87rem;">
                        <div>{{ $user->email }}</div>
                        @if($user->phone)<div style="color:var(--kbs-muted);">{{ $user->phone }}</div>@endif
                    </td>
                    <td>
                        @php $roleColors=['passenger'=>'default','operator'=>'info','driver'=>'success','admin'=>'warning']; @endphp
                        <span class="kbs-badge kbs-badge-{{ $roleColors[$user->role->value] ?? 'default' }}">
                            {{ ucfirst($user->role->value) }}
                        </span>
                        @if($user->role->value === 'operator' && !$user->operator_approved_at)
                            <div style="margin-top:.3rem;"><span class="kbs-badge kbs-badge-warning">⏳ Pending</span></div>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        @if($user->is_active)
                            <span class="kbs-badge kbs-badge-success">Active</span>
                        @else
                            <span class="kbs-badge kbs-badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td style="text-align:center;font-size:.82rem;color:var(--kbs-muted);">
                        {{ $user->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem;justify-content:center;flex-wrap:wrap;">
                            <a href="{{ route('admin.users.edit', $user) }}" class="kbs-btn kbs-btn-sm kbs-btn-info">✏️ Edit</a>
                            @if($user->role->value === 'operator' && !$user->operator_approved_at)
                                <form method="POST" action="{{ route('admin.operators.approve', $user) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="kbs-btn kbs-btn-sm kbs-btn-success">✅ Approve</button>
                                </form>
                            @endif
                            @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.delete', $user) }}" style="display:inline;"
                                      onsubmit="return confirm('Delete {{ $user->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="kbs-btn kbs-btn-sm kbs-btn-danger">🗑️</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:3rem;color:var(--kbs-muted);">
                        <div style="font-size:2.5rem;margin-bottom:.5rem;">👥</div>
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem 1.35rem;border-top:1px solid var(--kbs-border);">
        {{ $users->links() }}
    </div>
</div>
@endsection
