@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}" class="active">📈 Reports</a>
@endsection

@section('panel')
<div style="margin-bottom:1.5rem;">
    <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">📈 System Reports</h1>
    <p style="color:var(--kbs-muted);margin:0;">Select the report type to preview and download a professionally formatted PDF.</p>
</div>

{{-- Stats Summary --}}
<div class="kbs-grid kbs-grid-3" style="margin-bottom:1.75rem;">
    <div class="kbs-card kbs-stat">
        <strong>{{ number_format($data['total_revenue']) }}</strong>
        <span>Total Revenue (RWF)</span>
    </div>
    <div class="kbs-card kbs-stat">
        <strong>{{ number_format($data['total_bookings']) }}</strong>
        <span>Total Bookings</span>
    </div>
    <div class="kbs-card kbs-stat">
        <strong>{{ number_format($data['total_users']) }}</strong>
        <span>Registered Users</span>
    </div>
</div>

{{-- Report Type Cards --}}
<h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">Select a Report Type</h3>
<div class="kbs-grid kbs-grid-3" style="margin-bottom:1.75rem;" id="reportCards">
    @php
        $typeInfo = [
            'overview'   => ['icon'=>'📋','title'=>'System Overview','desc'=>'Total buses, bookings, passengers, payments summary.'],
            'payments'   => ['icon'=>'💳','title'=>'Payments & Passengers','desc'=>'Detailed passenger payment activity with routes.'],
            'operations' => ['icon'=>'🚌','title'=>'Operations Report','desc'=>'Bus, driver, route, and trip allocation status.'],
        ];
    @endphp
    @foreach($reportTypes as $type => $label)
    @php $info = $typeInfo[$type]; @endphp
    <div class="kbs-card report-type-card" data-type="{{ $type }}"
         onclick="selectReport('{{ $type }}')"
         style="cursor:pointer;padding:1.4rem;border:2px solid var(--kbs-border);
                transition:border-color .22s,transform .22s,box-shadow .22s;">
        <div style="font-size:2.2rem;margin-bottom:.6rem;">{{ $info['icon'] }}</div>
        <div style="font-weight:800;font-size:1rem;color:var(--kbs-green-dark);margin-bottom:.4rem;">{{ $info['title'] }}</div>
        <div style="font-size:.85rem;color:var(--kbs-muted);line-height:1.4;">{{ $info['desc'] }}</div>
        <div class="selected-check" style="display:none;margin-top:.75rem;">
            <span class="kbs-badge kbs-badge-success">✓ Selected</span>
        </div>
    </div>
    @endforeach
</div>

{{-- Download Form --}}
<div class="kbs-card" style="padding:1.5rem;max-width:560px;" id="downloadPanel" style="display:none;">
    <h3 style="margin:0 0 1rem;color:var(--kbs-green-dark);">📥 Download PDF Report</h3>
    <form method="POST" action="{{ route('admin.reports.download') }}" id="reportForm">
        @csrf
        <input type="hidden" name="type" id="selectedType" value="">
        <div style="padding:1rem;background:var(--kbs-green-light);border-radius:8px;
                    border:1px solid var(--kbs-border);margin-bottom:1rem;" id="selectedPreview">
            <span style="color:var(--kbs-muted);">No report type selected yet. Click a card above.</span>
        </div>
        <button type="submit" class="kbs-btn kbs-btn-primary" id="downloadBtn" disabled
                style="width:100%;justify-content:center;gap:.5rem;">
            📥 Download PDF Report
        </button>
    </form>
</div>

<script>
function selectReport(type) {
    document.querySelectorAll('.report-type-card').forEach(c => {
        c.style.borderColor = 'var(--kbs-border)';
        c.style.transform = 'none';
        c.style.boxShadow = 'none';
        c.querySelector('.selected-check').style.display = 'none';
    });
    const card = document.querySelector(`[data-type="${type}"]`);
    card.style.borderColor = 'var(--kbs-green)';
    card.style.transform = 'translateY(-2px)';
    card.style.boxShadow = 'var(--kbs-shadow-lg)';
    card.querySelector('.selected-check').style.display = 'block';

    document.getElementById('selectedType').value = type;
    document.getElementById('downloadBtn').disabled = false;

    const titles = {
        'overview':   '📋 System Overview Report',
        'payments':   '💳 Payments & Passengers Report',
        'operations': '🚌 Operations Report'
    };
    document.getElementById('selectedPreview').innerHTML =
        `<strong style="color:var(--kbs-green-dark);">${titles[type]}</strong>
         <span style="color:var(--kbs-muted);margin-left:.5rem;">— ready to download</span>`;

    document.getElementById('downloadPanel').style.display = 'block';
    document.getElementById('downloadPanel').scrollIntoView({behavior:'smooth',block:'nearest'});
}
</script>
@endsection
