@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.users') }}" class="active">Users</a>
@endsection

@section('panel')
<h1>Manage Users</h1>
<div class="kbs-card">
    <table class="kbs-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Action</th></tr></thead>
        <tbody>
        @foreach($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->role->value }}</td>
                <td>{{ $user->is_active ? 'Yes' : 'No' }}</td>
                <td>
                    @if($user->role === \App\Enums\UserRole::Operator && !$user->operator_approved_at)
                        <form method="POST" action="{{ route('admin.operators.approve', $user) }}">@csrf
                            <button class="kbs-btn kbs-btn-primary">Approve Operator</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
