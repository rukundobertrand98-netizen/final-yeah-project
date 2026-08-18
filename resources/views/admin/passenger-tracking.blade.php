@extends('layouts.dashboard')

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.users') }}">Users</a>
    <a href="{{ route('admin.buses') }}">Buses</a>
    <a href="{{ route('admin.routes') }}">Routes</a>
    <a href="{{ route('admin.trips') }}">Trips</a>
    <a href="{{ route('admin.payments') }}">Payments</a>
    <a href="{{ route('admin.booking-history') }}">Booking History</a>
    <a href="{{ route('admin.passenger-tracking') }}" class="active">Passenger Tracking</a>
    <a href="{{ route('admin.reports') }}">Reports</a>
    <a href="{{ route('admin.monitor') }}">Monitor</a>
@endsection

@section('panel')
<div class="kbs-header">
    <h1>📍 Passenger Tracking</h1>
    <div class="kbs-header-actions">
        <a href="{{ route('admin.passenger-tracking.map') }}" class="kbs-btn kbs-btn-primary">
            <svg><use href="#icon-map"></use></svg>View Map
        </a>
        <button onclick="refreshData()" class="kbs-btn kbs-btn-secondary">
            <svg><use href="#icon-refresh"></use></svg>Refresh
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="kbs-grid kbs-grid-3" style="margin-bottom: 2rem;">
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['active_passengers']) }}</div>
        <div class="kbs-stat-label">Active Now</div>
        <div class="kbs-stat-note">Last 30 minutes</div>
    </div>
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['total_locations_today']) }}</div>
        <div class="kbs-stat-label">Location Updates Today</div>
    </div>
    <div class="kbs-card kbs-stat">
        <div class="kbs-stat-number">{{ number_format($stats['unique_passengers_today']) }}</div>
        <div class="kbs-stat-label">Unique Passengers Today</div>
    </div>
</div>

@if($activeLocations->count() > 0)
<div class="kbs-card">
    <div class="kbs-card-header">
        <h2>Active Passenger Locations</h2>
        <p style="margin: 0; color: var(--kbs-muted); font-size: 0.9rem;">
            Passengers who have shared their location in the last 2 hours
        </p>
    </div>

    <div class="kbs-table-container">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>Passenger</th>
                    <th>Current Location</th>
                    <th>Coordinates</th>
                    <th>Accuracy</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activeLocations as $location)
                <tr id="location-{{ $location->id }}">
                    <td>
                        <div style="font-weight: 500;">{{ $location->user->name }}</div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            {{ $location->user->email }}<br>
                            {{ $location->user->phone }}
                        </div>
                    </td>
                    <td>
                        <div style="max-width: 250px;">
                            @if($location->address)
                                {{ Str::limit($location->address, 100) }}
                            @else
                                <em style="color: var(--kbs-muted);">Address not available</em>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-family: monospace; font-size: 0.8rem;">
                            {{ number_format($location->latitude, 6) }}<br>
                            {{ number_format($location->longitude, 6) }}
                        </div>
                    </td>
                    <td>
                        @if($location->accuracy)
                            <div style="font-size: 0.9rem;">
                                ±{{ number_format($location->accuracy) }}m
                            </div>
                            <div class="kbs-badge kbs-badge-{{ 
                                $location->accuracy <= 10 ? 'success' : 
                                ($location->accuracy <= 50 ? 'warning' : 'danger')
                            }}" style="font-size: 0.7rem; margin-top: 0.2rem;">
                                {{ 
                                    $location->accuracy <= 10 ? 'High' : 
                                    ($location->accuracy <= 50 ? 'Medium' : 'Low')
                                }} Accuracy
                            </div>
                        @else
                            <em style="color: var(--kbs-muted);">Unknown</em>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500;">
                            {{ $location->location_time->format('H:i:s') }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--kbs-muted);">
                            {{ $location->location_time->format('M d, Y') }}
                        </div>
                        <div class="kbs-badge kbs-badge-{{ 
                            $location->location_time->diffInMinutes(now()) <= 5 ? 'success' : 
                            ($location->location_time->diffInMinutes(now()) <= 30 ? 'warning' : 'danger')
                        }}" style="font-size: 0.7rem; margin-top: 0.2rem;">
                            {{ $location->location_time->diffForHumans() }}
                        </div>
                    </td>
                    <td>
                        <div class="kbs-dropdown">
                            <button class="kbs-btn kbs-btn-ghost kbs-btn-sm">
                                Actions
                                <svg><use href="#icon-chevron-down"></use></svg>
                            </button>
                            <div class="kbs-dropdown-menu">
                                <button onclick="viewOnMap({{ $location->latitude }}, {{ $location->longitude }}, '{{ $location->user->name }}')" 
                                        class="kbs-dropdown-item">
                                    <svg><use href="#icon-map-pin"></use></svg>View on Map
                                </button>
                                <button onclick="showLocationHistory({{ $location->user_id }})" 
                                        class="kbs-dropdown-item">
                                    <svg><use href="#icon-clock"></use></svg>Location History
                                </button>
                                <button onclick="sendNotification({{ $location->user_id }})" 
                                        class="kbs-dropdown-item">
                                    <svg><use href="#icon-bell"></use></svg>Send Notification
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $activeLocations->links() }}
</div>
@else
<div class="kbs-empty-state">
    <h3>No Active Locations</h3>
    <p>No passengers are currently sharing their location.</p>
    <a href="{{ route('admin.passenger-tracking.map') }}" class="kbs-btn kbs-btn-primary">
        <svg><use href="#icon-map"></use></svg>View Map
    </a>
</div>
@endif

<!-- Location History Modal -->
<div id="historyModal" class="kbs-modal" style="display: none;">
    <div class="kbs-modal-content" style="max-width: 800px;">
        <div class="kbs-modal-header">
            <h3>Location History</h3>
            <button onclick="closeHistoryModal()" class="kbs-modal-close">×</button>
        </div>
        <div class="kbs-modal-body" id="historyContent">
            <!-- Content will be loaded here -->
        </div>
        <div class="kbs-modal-footer">
            <button onclick="closeHistoryModal()" class="kbs-btn kbs-btn-primary">Close</button>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notificationModal" class="kbs-modal" style="display: none;">
    <div class="kbs-modal-content">
        <div class="kbs-modal-header">
            <h3>Send Notification</h3>
            <button onclick="closeNotificationModal()" class="kbs-modal-close">×</button>
        </div>
        <form id="notificationForm" onsubmit="sendNotificationMessage(event)">
            <div class="kbs-modal-body">
                <div style="margin-bottom: 1rem;">
                    <label>Message Type</label>
                    <select name="type" required>
                        <option value="">Select type...</option>
                        <option value="arrival">Bus Arrival Notification</option>
                        <option value="delay">Delay Information</option>
                        <option value="general">General Information</option>
                        <option value="emergency">Emergency Alert</option>
                    </select>
                </div>
                <div>
                    <label>Message</label>
                    <textarea name="message" rows="4" placeholder="Enter your message..." required></textarea>
                </div>
            </div>
            <div class="kbs-modal-footer">
                <button type="button" onclick="closeNotificationModal()" class="kbs-btn kbs-btn-ghost">Cancel</button>
                <button type="submit" class="kbs-btn kbs-btn-primary">Send Notification</button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedUserId = null;

// Refresh data
function refreshData() {
    location.reload();
}

// View location on map
function viewOnMap(lat, lng, userName) {
    const mapUrl = `{{ route('admin.passenger-tracking.map') }}?lat=${lat}&lng=${lng}&user=${encodeURIComponent(userName)}`;
    window.open(mapUrl, '_blank');
}

// Show location history
async function showLocationHistory(userId) {
    try {
        const modal = document.getElementById('historyModal');
        const content = document.getElementById('historyContent');
        
        content.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading...</div>';
        modal.style.display = 'flex';
        
        // Simulate loading location history (would be an actual API call)
        setTimeout(() => {
            content.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--kbs-muted);">
                    <h4>Location History</h4>
                    <p>This feature would show the passenger's location history over time.</p>
                    <p>Implementation would include:</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>Timeline of location updates</li>
                        <li>Map view of location trail</li>
                        <li>Statistics and analytics</li>
                        <li>Privacy controls</li>
                    </ul>
                </div>
            `;
        }, 1000);
        
    } catch (error) {
        console.error('Error loading location history:', error);
        alert('Failed to load location history. Please try again.');
    }
}

// Show notification modal
function sendNotification(userId) {
    selectedUserId = userId;
    document.getElementById('notificationModal').style.display = 'flex';
}

// Send notification message
async function sendNotificationMessage(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        user_id: selectedUserId,
        type: formData.get('type'),
        message: formData.get('message')
    };
    
    try {
        // Simulate sending notification (would be an actual API call)
        console.log('Sending notification:', data);
        
        // Show success message
        alert('Notification sent successfully!');
        closeNotificationModal();
        
    } catch (error) {
        console.error('Error sending notification:', error);
        alert('Failed to send notification. Please try again.');
    }
}

// Close modals
function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}

function closeNotificationModal() {
    document.getElementById('notificationModal').style.display = 'none';
    document.getElementById('notificationForm').reset();
    selectedUserId = null;
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const historyModal = document.getElementById('historyModal');
    const notificationModal = document.getElementById('notificationModal');
    
    if (event.target === historyModal) {
        closeHistoryModal();
    }
    if (event.target === notificationModal) {
        closeNotificationModal();
    }
});

// Auto-refresh every 30 seconds for active locations
setInterval(() => {
    if (!document.hidden) {
        // Only refresh if no modals are open
        const modalsOpen = document.querySelector('.kbs-modal[style*="flex"]');
        if (!modalsOpen) {
            refreshData();
        }
    }
}, 30000);
</script>
@endsection