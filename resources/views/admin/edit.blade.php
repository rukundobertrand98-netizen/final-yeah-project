@extends('layouts.dashboard')

@section('title', 'Edit User')

@section('panel')
    <h1>Edit User: {{ $user->name }}</h1>

    <div class="kbs-card kbs-form">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="phone">Phone (Optional)</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            @error('phone')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="password">New Password (leave blank to keep current)</label>
            <input type="password" id="password" name="password">
            @error('password')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation">

            <label for="role">Role</label>
            <select id="role" name="role" required>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role')<span class="kbs-error">{{ $message }}</span>@enderror

            <div style="margin-top:1rem;">
                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                <label for="is_active" style="display:inline-block;margin-left:.5rem;">Active</label>
            </div>

            <button type="submit" class="kbs-btn kbs-btn-primary" style="margin-top:1.5rem;">Update User</button>
            <a href="{{ route('admin.users') }}" class="kbs-btn kbs-btn-ghost" style="margin-top:1.5rem;">Cancel</a>
        </form>
    </div>
@endsection
