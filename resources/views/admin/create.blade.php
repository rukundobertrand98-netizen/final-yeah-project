@extends('layouts.dashboard')

@section('title', 'Create User')

@section('panel')
    <h1>Create New User</h1>

    <div class="kbs-card kbs-form">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="phone">Phone (Optional)</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
            @error('phone')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            @error('password')<span class="kbs-error">{{ $message }}</span>@enderror

            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role')<span class="kbs-error">{{ $message }}</span>@enderror

            <div style="margin-top:1rem;">
                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                <label for="is_active" style="display:inline-block;margin-left:.5rem;">Active</label>
            </div>

            <button type="submit" class="kbs-btn kbs-btn-primary" style="margin-top:1.5rem;">Create User</button>
            <a href="{{ route('admin.users') }}" class="kbs-btn kbs-btn-ghost" style="margin-top:1.5rem;">Cancel</a>
        </form>
    </div>
@endsection
