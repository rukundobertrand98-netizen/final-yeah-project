@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div style="max-width:420px;margin:2rem auto">
    <div class="kbs-card">
        <h1 style="margin-top:0">Sign in to KBS</h1>
        <form method="POST" action="{{ route('login') }}" class="kbs-form">
            @csrf
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <label><input type="checkbox" name="remember"> Remember me</label>
            <button type="submit" class="kbs-btn kbs-btn-primary" style="width:100%">Login</button>
        </form>
        <p style="text-align:center;margin-top:1rem">No account? <a href="{{ route('register') }}">Register as passenger</a></p>
    </div>
</div>
@endsection
