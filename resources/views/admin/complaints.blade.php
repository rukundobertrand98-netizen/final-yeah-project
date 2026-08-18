@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}" class="active">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">📝 Complaints</h1>
        <p style="color:var(--kbs-muted);margin:0;">Review and resolve passenger complaints.</p>
    </div>
    <div style="display:flex;gap:.6rem;">
        <span class="kbs-badge kbs-badge-warning">{{ $complaints->where('status','open')->count() }} Open</span>
        <span class="kbs-badge kbs-badge-success">{{ $complaints->where('status','resolved')->count() }} Resolved</span>
    </div>
</div>

@if(session('success'))
    <div class="kbs-alert kbs-alert-success" style="margin-bottom:1.5rem;">✅ {{ session('success') }}</div>
@endif

<div class="kbs-card" style="padding:0;overflow:hidden;">
    <table class="kbs-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Passenger</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($complaints as $complaint)
            <tr>
                <td style="color:var(--kbs-muted);font-size:.82rem;">{{ $complaint->id }}</td>
                <td>{{ $complaint->user->name ?? '—' }}</td>
                <td>
                    <div style="font-weight:600;">{{ $complaint->subject }}</div>
                    <div style="font-size:.8rem;color:var(--kbs-muted);margin-top:.15rem;">{{ Str::limit($complaint->message ?? '', 60) }}</div>
                </td>
                <td>
                    @if($complaint->status === 'open' || $complaint->status === 'pending')
                        <span class="kbs-badge kbs-badge-warning">Open</span>
                    @else
                        <span class="kbs-badge kbs-badge-success">Resolved</span>
                    @endif
                </td>
                <td style="font-size:.82rem;color:var(--kbs-muted);">{{ $complaint->created_at->format('d M Y H:i') }}</td>
                <td>
                    @if($complaint->status === 'open' || $complaint->status === 'pending')
                        <button type="button" class="kbs-btn kbs-btn-sm kbs-btn-primary"
                                onclick="showResolveForm({{ $complaint->id }})">Resolve</button>
                    @else
                        <span class="kbs-badge kbs-badge-success">Done</span>
                    @endif
                </td>
            </tr>
            @if($complaint->status === 'open' || $complaint->status === 'pending')
            <tr id="resolve-row-{{ $complaint->id }}" style="display:none;background:var(--kbs-green-light);">
                <td colspan="6" style="padding:1rem 1.35rem;">
                    <form method="POST" action="{{ route('admin.complaints.resolve', $complaint) }}" class="kbs-form">
                        @csrf
                        <label>Admin Response</label>
                        <textarea name="admin_response" rows="3" required placeholder="Write your resolution note…"></textarea>
                        <div style="display:flex;gap:.6rem;">
                            <button type="submit" class="kbs-btn kbs-btn-primary kbs-btn-sm">Submit Resolution</button>
                            <button type="button" class="kbs-btn kbs-btn-ghost kbs-btn-sm"
                                    onclick="hideResolveForm({{ $complaint->id }})">Cancel</button>
                        </div>
                    </form>
                </td>
            </tr>
            @endif
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:3rem;color:var(--kbs-muted);">
                    <div style="font-size:2.5rem;margin-bottom:.5rem;">📝</div>
                    No complaints found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:1rem 1.35rem;border-top:1px solid var(--kbs-border);">
        {{ $complaints->links() }}
    </div>
</div>

<script>
function showResolveForm(id){ document.getElementById('resolve-row-'+id).style.display='table-row'; }
function hideResolveForm(id){ document.getElementById('resolve-row-'+id).style.display='none'; }
</script>
@endsection
