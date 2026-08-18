@extends('layouts.app')

@section('content')
<div class="kbs-sidebar-layout">
    <aside class="kbs-sidebar">
        <div class="kbs-sidebar-title">
            <span>Menu</span>
            <strong>{{ $sidebarTitle ?? 'Dashboard' }}</strong>
        </div>
        @yield('sidebar')
    </aside>
    <section class="kbs-panel">@yield('panel')</section>
</div>
@endsection
