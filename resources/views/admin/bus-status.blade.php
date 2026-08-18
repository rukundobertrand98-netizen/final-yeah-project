@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}" class="active">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('title', 'Bus Status Reports')

@section('content')
<div class="kbs-page-header">
    <h1>Bus Status Reports</h1>
    <p>Driver-reported bus issues and maintenance requests</p>
</div>

@if(session('success'))
    <div class="kbs-alert kbs-alert-success" style="animation: kbsFadeUp 0.3s ease-out;">
        {{ session('success') }}
    </div>
@endif

<!-- Summary Cards -->
<div class="kbs-stats-grid" style="margin-bottom: 2rem;">
    <div class="kbs-stat-card" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">
        <div class="kbs-stat-icon">⚠️</div>
        <div class="kbs-stat-content">
            <div class="kbs-stat-value">{{ $reports->where('status', 'pending')->count() }}</div>
            <div class="kbs-stat-label">Pending Issues</div>
        </div>
    </div>

    <div class="kbs-stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <div class="kbs-stat-icon">🔧</div>
        <div class="kbs-stat-content">
            <div class="kbs-stat-value">{{ $reports->where('issue_type', 'mechanical')->count() }}</div>
            <div class="kbs-stat-label">Mechanical</div>
        </div>
    </div>

    <div class="kbs-stat-card" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
        <div class="kbs-stat-icon">🛞</div>
        <div class="kbs-stat-content">
            <div class="kbs-stat-value">{{ $reports->where('issue_type', 'tire')->count() }}</div>
            <div class="kbs-stat-label">Tire Issues</div>
        </div>
    </div>

    <div class="kbs-stat-card" style="background: linear-gradient(135deg, var(--kbs-green) 0%, #0d5e37 100%);">
        <div class="kbs-stat-icon">✅</div>
        <div class="kbs-stat-content">
            <div class="kbs-stat-value">{{ $reports->where('status', 'resolved')->count() }}</div>
            <div class="kbs-stat-label">Resolved</div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="kbs-filter-tabs" style="margin-bottom: 1.5rem;">
    <button class="kbs-filter-tab active" data-filter="all">All Reports ({{ $reports->count() }})</button>
    <button class="kbs-filter-tab" data-filter="pending">Pending ({{ $reports->where('status', 'pending')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="resolved">Resolved ({{ $reports->where('status', 'resolved')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="mechanical">Mechanical ({{ $reports->where('issue_type', 'mechanical')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="electrical">Electrical ({{ $reports->where('issue_type', 'electrical')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="tire">Tire ({{ $reports->where('issue_type', 'tire')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="accident">Accident ({{ $reports->where('issue_type', 'accident')->count() }})</button>
    <button class="kbs-filter-tab" data-filter="other">Other ({{ $reports->where('issue_type', 'other')->count() }})</button>
</div>

<!-- Bus Status Reports List -->
<div class="kbs-card">
    @if($reports->isEmpty())
        <div style="text-align: center; padding: 3rem; color: var(--kbs-muted);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚌</div>
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No bus status reports</p>
            <p style="font-size: 0.95rem;">All buses are operating normally</p>
        </div>
    @else
        <div class="kbs-report-list">
            @foreach($reports as $report)
                <div class="kbs-report-item" 
                     data-status="{{ $report->status }}" 
                     data-type="{{ $report->issue_type }}"
                     style="padding: 1.25rem; border-bottom: 1px solid var(--kbs-border); animation: kbsFadeUp 0.3s ease-out;">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: start;">
                        <!-- Report Details -->
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                <!-- Bus Info -->
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 1.5rem;">🚌</span>
                                    <strong style="font-size: 1.1rem; color: var(--kbs-ink);">
                                        {{ $report->bus->plate_number ?? 'N/A' }}
                                    </strong>
                                </div>

                                <!-- Issue Type Badge -->
                                @php
                                    $typeColors = [
                                        'mechanical' => 'background: #f59e0b; color: white;',
                                        'electrical' => 'background: #3b82f6; color: white;',
                                        'tire' => 'background: #6366f1; color: white;',
                                        'accident' => 'background: #dc2626; color: white;',
                                        'fuel' => 'background: #10b981; color: white;',
                                        'other' => 'background: #6b7280; color: white;',
                                    ];
                                    $typeColor = $typeColors[$report->issue_type] ?? $typeColors['other'];
                                @endphp
                                <span class="kbs-badge" style="{{ $typeColor }} font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">
                                    {{ ucfirst($report->issue_type) }}
                                </span>

                                <!-- Status Badge -->
                                @if($report->status === 'pending')
                                    <span class="kbs-badge" style="background: #dc2626; color: white; font-weight: 600;">
                                        ⚠️ PENDING
                                    </span>
                                @else
                                    <span class="kbs-badge" style="background: var(--kbs-green); color: white; font-weight: 600;">
                                        ✅ RESOLVED
                                    </span>
                                @endif
                            </div>

                            <!-- Driver Info -->
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                                <span style="color: var(--kbs-muted); font-size: 0.9rem;">Reported by:</span>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--kbs-green); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.9rem;">
                                        {{ substr($report->driver->name ?? 'N/A', 0, 1) }}
                                    </div>
                                    <strong style="color: var(--kbs-ink);">{{ $report->driver->name ?? 'N/A' }}</strong>
                                </div>
                            </div>

                            <!-- Description -->
                            <div style="background: #f9fafb; padding: 0.875rem; border-radius: 8px; border-left: 3px solid {{ $report->status === 'pending' ? '#dc2626' : 'var(--kbs-green)' }}; margin-bottom: 0.75rem;">
                                <p style="margin: 0; color: var(--kbs-ink); line-height: 1.6;">
                                    {{ $report->description }}
                                </p>
                            </div>

                            <!-- Metadata -->
                            <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.875rem; color: var(--kbs-muted);">
                                <div>
                                    <strong>Reported:</strong> {{ $report->created_at->format('M d, Y h:i A') }}
                                </div>
                                @if($report->estimated_fix_at)
                                    <div>
                                        <strong>Est. Fix:</strong> {{ \Carbon\Carbon::parse($report->estimated_fix_at)->format('M d, Y h:i A') }}
                                    </div>
                                @endif
                                @if($report->status === 'resolved' && $report->resolved_at)
                                    <div style="color: var(--kbs-green); font-weight: 600;">
                                        <strong>Resolved:</strong> {{ \Carbon\Carbon::parse($report->resolved_at)->format('M d, Y h:i A') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div>
                            @if($report->status === 'pending')
                                <form method="POST" action="{{ route('admin.bus-status.resolve', $report) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="kbs-btn kbs-btn-success" 
                                            style="background: var(--kbs-green); white-space: nowrap;"
                                            onclick="return confirm('Mark this issue as resolved?')">
                                        ✅ Mark Resolved
                                    </button>
                                </form>
                            @else
                                <div style="text-align: right; color: var(--kbs-green); font-weight: 600; font-size: 0.9rem;">
                                    <div style="font-size: 2rem;">✅</div>
                                    <div>Resolved</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($reports->hasPages())
            <div style="padding: 1.5rem; border-top: 1px solid var(--kbs-border);">
                {{ $reports->links() }}
            </div>
        @endif
    @endif
</div>

<style>
.kbs-filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.kbs-filter-tab {
    padding: 0.625rem 1rem;
    border: 1px solid var(--kbs-border);
    background: white;
    color: var(--kbs-ink);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    font-weight: 500;
}

.kbs-filter-tab:hover {
    border-color: var(--kbs-green);
    background: #f0fdf4;
}

.kbs-filter-tab.active {
    background: var(--kbs-green);
    color: white;
    border-color: var(--kbs-green);
}

.kbs-report-item[data-status="resolved"] {
    opacity: 0.75;
}

.kbs-alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.kbs-alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.kbs-filter-tab');
    const reports = document.querySelectorAll('.kbs-report-item');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const filter = this.dataset.filter;

            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Filter reports
            reports.forEach(report => {
                const status = report.dataset.status;
                const type = report.dataset.type;

                if (filter === 'all') {
                    report.style.display = '';
                } else if (filter === 'pending' || filter === 'resolved') {
                    report.style.display = status === filter ? '' : 'none';
                } else {
                    report.style.display = type === filter ? '' : 'none';
                }
            });
        });
    });
});
</script>
@endsection
