<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'KBS Online Bus Reservation') - Kigali</title>
    <link rel="stylesheet" href="{{ asset('css/kbs.css') }}">
    <style>
        /* Thin override — full theme lives in kbs.css */
        input, select, textarea {
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--kbs-border);
            background: #fff;
            color: var(--kbs-ink);
            padding: 0.78rem 0.9rem;
            font: inherit;
            transition: border-color var(--kbs-transition), box-shadow var(--kbs-transition);
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--kbs-green);
            box-shadow: var(--kbs-ring);
            outline: none;
        }
    </style>
    @stack('head')
</head>
<body>
    <svg aria-hidden="true" class="kbs-icons">
        <symbol id="icon-home" viewBox="0 0 24 24"><path d="M3 10.8 12 3l9 7.8V21a1 1 0 0 1-1 1h-5.5v-6.5h-5V22H4a1 1 0 0 1-1-1V10.8Z"/></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24"><path d="M10.5 18a7.5 7.5 0 1 1 5.28-2.17l4.2 4.19-1.41 1.42-4.2-4.2A7.47 7.47 0 0 1 10.5 18Zm0-2a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Z"/></symbol>
        <symbol id="icon-ticket" viewBox="0 0 24 24"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Zm9-1.2h-2v2.4h2V5.8Zm0 4h-2v2.4h2V9.8Zm0 4h-2v2.4h2v-2.4Z"/></symbol>
        <symbol id="icon-user" viewBox="0 0 24 24"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.7 0-8 2.23-8 5.1V21h16v-1.9c0-2.87-3.3-5.1-8-5.1Z"/></symbol>
        <symbol id="icon-login" viewBox="0 0 24 24"><path d="M11 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-8v-2h8V5h-8V3Zm1.3 5.3L16 12l-3.7 3.7-1.4-1.4L12.2 13H3v-2h9.2l-1.3-1.3 1.4-1.4Z"/></symbol>
        <symbol id="icon-logout" viewBox="0 0 24 24"><path d="M5 3h8v2H5v14h8v2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm11.7 5.3L20.4 12l-3.7 3.7-1.4-1.4 1.3-1.3H8v-2h8.6l-1.3-1.3 1.4-1.4Z"/></symbol>
        <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/></symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></symbol>
        <symbol id="icon-edit" viewBox="0 0 24 24"><path d="m16.9 3.7 3.4 3.4-10.8 10.8-4.2.8.8-4.2L16.9 3.7Zm-9 11.7-.2 1 1-.2 8.8-8.8-1-1-8.6 9ZM4 20h16v2H4v-2Z"/></symbol>
        <symbol id="icon-bus" viewBox="0 0 24 24"><path d="M7 3h10a3 3 0 0 1 3 3v10.5a2.5 2.5 0 0 1-2 2.45V21h-2v-2H8v2H6v-2.05a2.5 2.5 0 0 1-2-2.45V6a3 3 0 0 1 3-3Zm0 2a1 1 0 0 0-1 1v4h12V6a1 1 0 0 0-1-1H7Zm-1 7v4.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V12H6Zm2 1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm8 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></symbol>
    </svg>

    <header class="kbs-header">
        <div class="container">
            <nav class="kbs-nav">
                <a href="{{ route('home') }}" class="kbs-brand">
                    <span class="kbs-logo"><svg><use href="#icon-bus"></use></svg></span>
                    <span><strong>KBS Limited</strong><small>Kigali smart bus booking</small></span>
                </a>
                <button class="kbs-menu-toggle" type="button" aria-label="Open navigation" aria-controls="kbsNavLinks" aria-expanded="false">
                    <svg><use href="#icon-menu"></use></svg>
                </button>
                <div class="kbs-nav-links" id="kbsNavLinks">
                    <a href="{{ route('home') }}"><svg><use href="#icon-home"></use></svg>Home</a>
                    @auth
                        @if(auth()->user()->role === \App\Enums\UserRole::Passenger)
                            <a href="{{ route('passenger.search') }}"><svg><use href="#icon-search"></use></svg>Book Trip</a>
                            <a href="{{ route('passenger.bookings') }}"><svg><use href="#icon-ticket"></use></svg>My Tickets</a>
                        @elseif(auth()->user()->role === \App\Enums\UserRole::Operator)
                            <a href="{{ route('operator.dashboard') }}"><svg><use href="#icon-home"></use></svg>Operator</a>
                        @elseif(auth()->user()->role === \App\Enums\UserRole::Driver)
                            <a href="{{ route('driver.dashboard') }}"><svg><use href="#icon-home"></use></svg>Driver</a>
                        @elseif(auth()->user()->role === \App\Enums\UserRole::Admin)
                            <a href="{{ route('admin.dashboard') }}"><svg><use href="#icon-home"></use></svg>Admin</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="kbs-btn kbs-btn-outline"><svg><use href="#icon-logout"></use></svg>Logout</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"><svg><use href="#icon-login"></use></svg>Login</a>
                        <a href="{{ route('register') }}" class="kbs-btn kbs-btn-primary"><svg><use href="#icon-user"></use></svg>Create Account</a>
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
        <strong>KBS Limited</strong>
        <span>&copy; {{ date('Y') }} Public Transport Management - Kigali City, Rwanda</span>
    </footer>

    <script>
        document.querySelector('.kbs-menu-toggle')?.addEventListener('click', function () {
            const links = document.getElementById('kbsNavLinks');
            const isOpen = links.classList.toggle('is-open');
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    </script>
    @stack('scripts')
</body>
</html>
