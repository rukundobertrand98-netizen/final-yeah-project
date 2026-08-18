@extends('layouts.dashboard')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #live-map { width:100%; height:460px; border-radius:10px; border:1px solid var(--kbs-border); z-index:1; }
        .monitor-layout { display:grid; grid-template-columns:300px minmax(0,1fr); gap:1rem; align-items:start; }
        .route-list { display:grid; gap:.65rem; max-height:460px; overflow:auto; padding-right:.25rem; }
        .route-button {
            width:100%; border:1px solid var(--kbs-border); border-radius:8px; background:#fff;
            padding:.85rem; text-align:left; cursor:pointer; transition:border-color .2s,box-shadow .2s,background .2s;
        }
        .route-button:hover, .route-button.active { border-color:var(--kbs-green); background:var(--kbs-green-light); box-shadow:var(--kbs-shadow); }
        .route-button strong { display:block; color:var(--kbs-green-dark); line-height:1.25; }
        .route-button small { display:block; color:var(--kbs-muted); margin-top:.2rem; }
        .bus-card {
            background:#fff; border:1px solid var(--kbs-border); border-radius:10px; padding:1rem;
            transition:border-color .2s,transform .2s,box-shadow .2s; cursor:pointer;
        }
        .bus-card:hover { border-color:var(--kbs-green); transform:translateY(-2px); box-shadow:var(--kbs-shadow-lg); }
        .bus-card.has-location { border-left:4px solid var(--kbs-green); }
        .bus-card.no-location  { border-left:4px solid var(--kbs-muted); opacity:.75; }
        .pulse-dot {
            display:inline-block;width:10px;height:10px;border-radius:50%;
            background:var(--kbs-green);animation:kbsPulse 1.8s ease infinite;
        }
        .empty-monitor {
            min-height:180px; display:grid; place-items:center; text-align:center; color:var(--kbs-muted);
            border:1px dashed var(--kbs-border); border-radius:8px; background:#fff; padding:1rem;
        }
        @media(max-width:900px){ .monitor-layout { grid-template-columns:1fr; } .route-list { max-height:none; } }
    </style>
@endpush

@section('sidebar')
    <a href="{{ route('admin.dashboard') }}">📊 Dashboard</a>
    <a href="{{ route('admin.users') }}">👥 Users</a>
    <a href="{{ route('admin.buses') }}">🚌 Buses</a>
    <a href="{{ route('admin.monitor') }}" class="active">📍 Live Monitor</a>
    <a href="{{ route('admin.bus-status') }}">⚠️ Bus Status</a>
    <a href="{{ route('admin.payments') }}">💳 Payments</a>
    <a href="{{ route('admin.complaints') }}">📝 Complaints</a>
    <a href="{{ route('admin.reports') }}">📈 Reports</a>
@endsection

@section('panel')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 .3rem;color:var(--kbs-green-dark);">Live Bus Monitor</h1>
        <p style="color:var(--kbs-muted);margin:0;">Select a route to see buses working on that route. Map refreshes every 20 seconds.</p>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
        <span class="kbs-badge kbs-badge-default" id="tracked-count">{{ $trips->count() }} buses tracked</span>
        <span class="kbs-badge kbs-badge-success"><span class="pulse-dot" style="margin-right:.35rem;"></span>{{ $trips->where('status','in_progress')->count() }} in-progress</span>
        <span class="kbs-badge kbs-badge-warning">{{ $trips->where('status','boarding')->count() }} boarding</span>
        <button onclick="refreshMap()" class="kbs-btn kbs-btn-sm kbs-btn-ghost">Refresh Now</button>
    </div>
</div>

<div class="monitor-layout" style="margin-bottom:1.5rem;">
    <div class="kbs-card" style="padding:1rem;">
        <h3 style="margin:0 0 .85rem;color:var(--kbs-green-dark);">Routes</h3>
        @if($routes->isEmpty())
            <div class="empty-monitor">No active routes have buses working right now.</div>
        @else
            <div class="route-list">
                @foreach($routes as $route)
                    <button type="button"
                            class="route-button {{ (int) $selectedRouteId === (int) $route->id ? 'active' : '' }}"
                            data-route-id="{{ $route->id }}"
                            onclick="selectRoute({{ $route->id }})">
                        <strong>{{ $route->name }}</strong>
                        <small>{{ $route->originStop?->name ?? 'Origin' }} to {{ $route->destinationStop?->name ?? 'Destination' }}</small>
                        <span class="kbs-badge kbs-badge-default" style="margin-top:.55rem;">{{ $route->active_schedules_count }} working bus{{ $route->active_schedules_count === 1 ? '' : 'es' }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="kbs-card" style="padding:.5rem;">
        <div id="live-map"></div>
        <div style="display:flex;gap:1.2rem;flex-wrap:wrap;padding:.75rem .5rem .25rem;font-size:.82rem;color:var(--kbs-muted);">
            <span><span class="kbs-dot kbs-dot-bus"></span> Bus location from driver GPS</span>
            <span><span class="kbs-dot kbs-dot-stop"></span> Route stops</span>
            <span><span style="display:inline-block;width:18px;height:3px;background:var(--kbs-green);margin-right:.3rem;vertical-align:middle;"></span> Selected route</span>
        </div>
    </div>
</div>

<div class="kbs-grid kbs-grid-2" id="bus-cards">
    @foreach($trips as $trip)
    @php $loc = $trip->latestLocation; $hasLoc = !is_null($loc?->latitude); @endphp
    <div class="bus-card {{ $hasLoc ? 'has-location' : 'no-location' }}"
         onclick="focusBus({{ $trip->id }},{{ $hasLoc ? $loc->latitude : 'null' }},{{ $hasLoc ? $loc->longitude : 'null' }})">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.6rem;gap:.5rem;">
            <div>
                <div style="font-size:1.05rem;font-weight:800;color:var(--kbs-green-dark);">
                    Bus {{ $trip->bus->plate_number ?? 'N/A' }}
                </div>
                <div style="color:var(--kbs-muted);font-size:.85rem;">{{ $trip->route->name ?? 'Route N/A' }}</div>
            </div>
            @php $sm=['in_progress'=>'success','boarding'=>'warning','delayed'=>'danger','scheduled'=>'info']; @endphp
            <span class="kbs-badge kbs-badge-{{ $sm[$trip->status] ?? 'default' }}">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem .75rem;font-size:.85rem;">
            <div><span style="color:var(--kbs-muted);">Driver:</span> <strong>{{ $trip->driver?->name ?? $trip->bus?->driver?->name ?? 'N/A' }}</strong></div>
            <div><span style="color:var(--kbs-muted);">Date:</span> {{ $trip->travel_date?->format('d M Y') }}</div>
            <div><span style="color:var(--kbs-muted);">Departs:</span> {{ $trip->departure_time }}</div>
            <div>
                @if($hasLoc)
                    <span style="color:var(--kbs-muted);">Speed:</span> {{ $loc->speed_kmh ? round($loc->speed_kmh).' km/h' : '-' }}
                @else
                    <span style="color:var(--kbs-muted);">No GPS yet</span>
                @endif
            </div>
        </div>
        @if($hasLoc)
        <div style="margin-top:.6rem;padding:.5rem .65rem;background:var(--kbs-green-light);border-radius:6px;font-size:.8rem;">
            <span style="color:var(--kbs-muted);">Nearest stop:</span>
            <strong>{{ $loc->nearestStop?->name ?? 'Unknown' }}</strong>
            <span style="color:var(--kbs-muted);float:right;">{{ $loc->recorded_at?->diffForHumans() }}</span>
        </div>
        @endif
    </div>
    @endforeach
    @if($trips->isEmpty())
        <div class="empty-monitor">Choose another route to view buses with active trips.</div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('live-map',{zoomControl:true,attributionControl:false})
              .setView([-1.9499,30.0605],12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

const busIcon = L.divIcon({
    className:'',
    html:`<div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1a7a4a,#f5c800);
               border:3px solid #fff;display:flex;align-items:center;justify-content:center;
               box-shadow:0 2px 8px rgba(0,0,0,.3);font-size:14px;">BUS</div>`,
    iconSize:[32,32],iconAnchor:[16,16],
});

const markers = {};
const stopMarkers = [];
let routeLine = null;
let selectedRouteId = @json($selectedRouteId);

function refreshMap(){
    const url = new URL('{{ route('admin.monitor.data') }}', window.location.origin);
    if(selectedRouteId) url.searchParams.set('route_id', selectedRouteId);

    fetch(url)
        .then(r=>r.json())
        .then(({trips, route})=>{
            drawRoute(route);
            syncMarkers(trips);
            renderBusCards(trips);
            document.getElementById('tracked-count').textContent = `${trips.length} buses tracked`;
        })
        .catch(()=>{});
}

function syncMarkers(trips){
    const visibleIds = new Set();

    trips.forEach(t=>{
        if(!t.latitude||!t.longitude) return;
        visibleIds.add(String(t.id));
        const pos=[t.latitude,t.longitude];
        const popup=`<div style="min-width:180px;font-family:inherit;">
            <strong style="color:#1a7a4a;">${escapeHtml(t.plate_number||'Bus')}</strong><br>
            <small>${escapeHtml(t.route||'Route N/A')}</small><br>
            <span>Driver: ${escapeHtml(t.driver||'N/A')}</span><br>
            <span style="font-size:.8rem;color:#5a7060;">Status: ${escapeHtml(t.status)}</span><br>
            ${t.nearest_stop?`<span style="font-size:.8rem;">Stop: ${escapeHtml(t.nearest_stop)}</span><br>`:''}
            ${t.speed_kmh?`<span style="font-size:.8rem;">Speed: ${Math.round(t.speed_kmh)} km/h</span>`:''}
        </div>`;

        if(markers[t.id]){
            markers[t.id].setLatLng(pos).setPopupContent(popup);
        } else {
            markers[t.id]=L.marker(pos,{icon:busIcon}).addTo(map).bindPopup(popup);
        }
    });

    Object.keys(markers).forEach(id=>{
        if(!visibleIds.has(id)){
            map.removeLayer(markers[id]);
            delete markers[id];
        }
    });

    fitMap(trips);
}

function drawRoute(route){
    if(routeLine) {
        map.removeLayer(routeLine);
        routeLine = null;
    }
    stopMarkers.splice(0).forEach(marker => map.removeLayer(marker));

    if(!route || !route.stops || !route.stops.length) return;

    const points = route.stops
        .filter(stop => stop.latitude && stop.longitude)
        .map(stop => [stop.latitude, stop.longitude]);

    if(points.length > 1) {
        routeLine = L.polyline(points, {color:'#1a7a4a', weight:4, opacity:.72}).addTo(map);
    }

    route.stops.forEach(stop => {
        if(!stop.latitude || !stop.longitude) return;
        const marker = L.circleMarker([stop.latitude, stop.longitude], {
            radius:6, color:'#5a7060', weight:2, fillColor:'#fff', fillOpacity:1
        }).addTo(map).bindTooltip(escapeHtml(stop.name));
        stopMarkers.push(marker);
    });
}

function fitMap(trips){
    const points = trips
        .filter(t => t.latitude && t.longitude)
        .map(t => [t.latitude, t.longitude]);

    if(points.length) {
        map.fitBounds(points, {padding:[45,45], maxZoom:15});
    } else if(routeLine) {
        map.fitBounds(routeLine.getBounds(), {padding:[45,45], maxZoom:14});
    }
}

function selectRoute(routeId){
    selectedRouteId = routeId;
    document.querySelectorAll('.route-button').forEach(button => {
        button.classList.toggle('active', Number(button.dataset.routeId) === Number(routeId));
    });
    const url = new URL(window.location.href);
    url.searchParams.set('route_id', routeId);
    window.history.replaceState({}, '', url);
    refreshMap();
}

function renderBusCards(trips){
    const cards = document.getElementById('bus-cards');
    if(!trips.length) {
        cards.innerHTML = '<div class="empty-monitor">No buses are currently working on this route.</div>';
        return;
    }

    cards.innerHTML = trips.map(t => {
        const hasLoc = t.latitude && t.longitude;
        const statusClass = {in_progress:'success', boarding:'warning', delayed:'danger', scheduled:'info'}[t.status] || 'default';
        return `<div class="bus-card ${hasLoc ? 'has-location' : 'no-location'}" onclick="focusBus(${t.id},${hasLoc ? t.latitude : 'null'},${hasLoc ? t.longitude : 'null'})">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.6rem;gap:.5rem;">
                <div>
                    <div style="font-size:1.05rem;font-weight:800;color:var(--kbs-green-dark);">Bus ${escapeHtml(t.plate_number || 'N/A')}</div>
                    <div style="color:var(--kbs-muted);font-size:.85rem;">${escapeHtml(t.route || 'Route N/A')}</div>
                </div>
                <span class="kbs-badge kbs-badge-${statusClass}">${escapeHtml((t.status || 'active').replace('_', ' '))}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem .75rem;font-size:.85rem;">
                <div><span style="color:var(--kbs-muted);">Driver:</span> <strong>${escapeHtml(t.driver || 'N/A')}</strong></div>
                <div><span style="color:var(--kbs-muted);">Date:</span> ${escapeHtml(t.travel_date || '')}</div>
                <div><span style="color:var(--kbs-muted);">Departs:</span> ${escapeHtml(t.departure_time || '')}</div>
                <div>${hasLoc ? `<span style="color:var(--kbs-muted);">Speed:</span> ${t.speed_kmh ? Math.round(t.speed_kmh) + ' km/h' : '-'}` : '<span style="color:var(--kbs-muted);">No GPS yet</span>'}</div>
            </div>
            ${hasLoc ? `<div style="margin-top:.6rem;padding:.5rem .65rem;background:var(--kbs-green-light);border-radius:6px;font-size:.8rem;">
                <span style="color:var(--kbs-muted);">Nearest stop:</span>
                <strong>${escapeHtml(t.nearest_stop || 'Unknown')}</strong>
            </div>` : ''}
        </div>`;
    }).join('');
}

function focusBus(id,lat,lng){
    if(!lat||!lng) return;
    map.setView([lat,lng],15,{animate:true});
    if(markers[id]) markers[id].openPopup();
}

function escapeHtml(value){
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[char]));
}

refreshMap();
setInterval(refreshMap, 20000);
</script>
@endpush
