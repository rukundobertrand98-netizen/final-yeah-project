@extends('layouts.app')

@section('content')
<div class="kbs-sidebar-layout">
    <aside class="kbs-sidebar">
        <strong style="display:block;margin-bottom:1rem;color:var(--kbs-primary)">{{ $sidebarTitle ?? 'Dashboard' }}</strong>
        @yield('sidebar')
    </aside>
    <div>@yield('panel')</div>
</div>
@endsection
