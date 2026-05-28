<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'KBS Online Bus Reservation') — Kigali</title>
    <link rel="stylesheet" href="{{ asset('css/kbs.css') }}">
    @stack('head')
</head>
<body>
    <header class="kbs-header">
        <div class="container">
            <nav class="kbs-nav">
                <a href="{{ route('home') }}" class="kbs-brand">
                    <span class="kbs-logo">KBS</span>
                    <span>KBS Limited · Kigali</span>
                </a>
                <div class="kbs-nav-links">
                    @auth
                        @if(auth()->user()->role === \App\Enums\UserRole::Passenger)
                            <a href="{{ route('passenger.search') }}">Book Trip</a>
                            <a href="{{ route('passenger.bookings') }}">My Tickets</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" style="display:inline">
                            @csrf
                            <button type="submit" class="kbs-btn kbs-btn-outline">Logout</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}">Login</a>
                        <a href="{{ route('register') }}" class="kbs-btn kbs-btn-primary">Create Account</a>
                    @endauth
                </div>
            </nav>
            @yield('hero')
        </div>
    </header>

    <main class="kbs-main">
        <div class="container">
            @if(session('success'))
                <div class="kbs-alert kbs-alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="kbs-alert kbs-alert-error">
                    <ul style="margin:0;padding-left:1.2rem">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>

    <footer class="kbs-footer">
        &copy; {{ date('Y') }} KBS Limited — Public Transport Management · Kigali City, Rwanda
    </footer>
    @stack('scripts')
</body>
</html>
