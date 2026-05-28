@extends('layouts.dashboard')

@section('title', 'Manage Complaints')

@section('panel')
<h1>Complaints</h1>
@foreach($complaints as $c)
    <div class="kbs-card" style="margin-bottom:1rem">
        <strong>{{ $c->subject }}</strong> — {{ $c->user->name }}
        <span class="kbs-badge kbs-badge-{{ $c->status === 'open' ? 'warning' : 'success' }}">{{ $c->status }}</span>
        <p>{{ $c->message }}</p>
        @if($c->status === 'open')
            <form method="POST" action="{{ route('admin.complaints.resolve', $c) }}" class="kbs-form">
                @csrf
                <textarea name="admin_response" required placeholder="Your response"></textarea>
                <button class="kbs-btn kbs-btn-primary">Resolve</button>
            </form>
        @else
            <p><em>{{ $c->admin_response }}</em></p>
        @endif
    <h1>All Complaints</h1>

    <div class="kbs-card">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    <tr>
                        <td>{{ $complaint->id }}</td>
                        <td>{{ $complaint->user->name ?? 'N/A' }}</td>
                        <td>{{ $complaint->subject }}</td>
                        <td><span class="kbs-badge kbs-badge-{{ $complaint->status === 'open' ? 'warning' : 'success' }}">{{ $complaint->status }}</span></td>
                        <td>{{ $complaint->created_at->format('d M Y H:i') }}</td>
                        <td>
                            @if($complaint->status === 'open')
                                <button type="button" class="kbs-btn kbs-btn-sm kbs-btn-primary" onclick="showResolveForm({{ $complaint->id }})">Resolve</button>
                            @else
                                <span class="kbs-badge kbs-badge-info">Resolved</span>
                            @endif
                        </td>
                    </tr>
                    @if($complaint->status === 'open')
                        <tr id="resolve-form-{{ $complaint->id }}" style="display:none;">
                            <td colspan="6">
                                <div class="kbs-card kbs-form" style="margin-top:1rem;padding:1rem;">
                                    <form action="{{ route('admin.complaints.resolve', $complaint) }}" method="POST">
                                        @csrf
                                        <label for="admin_response_{{ $complaint->id }}">Admin Response</label>
                                        <textarea id="admin_response_{{ $complaint->id }}" name="admin_response" rows="3" required>{{ old('admin_response') }}</textarea>
                                        @error('admin_response')<span class="kbs-error">{{ $message }}</span>@enderror
                                        <button type="submit" class="kbs-btn kbs-btn-primary" style="margin-top:.5rem;">Submit Resolution</button>
                                        <button type="button" class="kbs-btn kbs-btn-ghost" style="margin-top:.5rem;" onclick="hideResolveForm({{ $complaint->id }})">Cancel</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6">No complaints found.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $complaints->links() }}
    </div>
@endforeach
{{ $complaints->links() }}

    <script>
        function showResolveForm(complaintId) {
            document.getElementById(`resolve-form-${complaintId}`).style.display = 'table-row';
        }
        function hideResolveForm(complaintId) {
            document.getElementById(`resolve-form-${complaintId}`).style.display = 'none';
        }
    </script>
@endsection
