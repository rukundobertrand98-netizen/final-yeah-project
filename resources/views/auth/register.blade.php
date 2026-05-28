@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div style="max-width:480px;margin:2rem auto">
    <div class="kbs-card">
        <h1 style="margin-top:0">Create Passenger Account</h1>
        <p style="color:var(--kbs-muted)">For KBS public transport in Kigali city only.</p>
        <form method="POST" action="{{ route('register') }}" class="kbs-form">
            @csrf
            <label>Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
            <label>Phone (MTN)</label>
            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="078xxxxxxx" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" required>
            <button type="submit" class="kbs-btn kbs-btn-primary" style="width:100%">Create Account</button>
        </form>
    </div>
</div>
@endsection
