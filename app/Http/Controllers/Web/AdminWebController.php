<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Booking;
use App\Models\BusStatusReport;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Route as BusRoute;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\BookingHistory;
use App\Models\PassengerLocation;

class AdminWebController extends Controller
{
    /* ================= DASHBOARD ================= */

    public function dashboard(): View
    {
        $stats = [
            'total_users' => User::count(),

            'passengers' => User::where(
                'role',
                UserRole::Passenger->value
            )->count(),

            'operators' => User::where(
                'role',
                UserRole::Operator->value
            )->count(),

            'drivers' => User::where(
                'role',
                UserRole::Driver->value
            )->count(),

            'admins' => User::where(
                'role',
                UserRole::Admin->value
            )->count(),

            'new_users_today' => User::whereDate(
                'created_at',
                today()
            )->count(),

            'total_buses' => Bus::count(),

            // FIXED
            'active_buses' => Schedule::whereIn(
                'status',
                ['in_progress', 'boarding']
            )
                ->whereDate('travel_date', today())
                ->distinct('bus_id')
                ->count('bus_id'),

            'active_trips' => Schedule::whereIn(
                'status',
                ['in_progress', 'boarding', 'delayed']
            )->count(),

            'trips_today' => Schedule::whereDate(
                'travel_date',
                today()
            )->count(),

            // FIXED
            'completed_trips_today' => Schedule::where(
                'status',
                'completed'
            )
                ->whereDate('travel_date', today())
                ->count(),

            'bookings_today' => Booking::whereDate(
                'created_at',
                today()
            )->count(),

            'total_bookings' => Booking::count(),

            'revenue' => Payment::where(
                'status',
                'successful'
            )->sum('amount'),

            'revenue_today' => Payment::where(
                'status',
                'successful'
            )
                ->whereDate('created_at', today())
                ->sum('amount'),

            'pending_complaints' => Complaint::where(
                'status',
                'pending'
            )->count(),

            'resolved_complaints' => Complaint::where(
                'status',
                'resolved'
            )->count(),

            'total_routes' => BusRoute::count(),

            'total_stops' => Stop::count(),

            'pending_operators' => User::where(
                'role',
                UserRole::Operator->value
            )
                ->whereNull('operator_approved_at')
                ->count(),
        ];

        $active_trips = Schedule::with([
                'bus',
                'route',
                'driver'
            ])
            ->withCount('bookings')
            ->whereIn(
                'status',
                ['in_progress', 'boarding', 'delayed']
            )
            ->whereDate('travel_date', today())
            ->latest()
            ->get();

        // FIXED
        $recent_activities = [
            [
                'icon' => '🧑‍💼',
                'title' => 'New user registered',
                'description' => 'A new user signed up today',
                'time' => now()->diffForHumans(),
            ],

            [
                'icon' => '🚌',
                'title' => 'Bus updated',
                'description' => 'A bus record was modified',
                'time' => now()->subMinutes(10)->diffForHumans(),
            ],

            [
                'icon' => '🎫',
                'title' => 'Booking created',
                'description' => 'New ticket booking made',
                'time' => now()->subMinutes(25)->diffForHumans(),
            ],
        ];

        $chartData = [
            'usage' => [
                'Buses' => $stats['total_buses'],
                'Bookings' => $stats['total_bookings'],
                'Passengers' => $stats['passengers'],
                'Payments' => Payment::count(),
            ],
            'money' => [
                'Total' => (float) $stats['revenue'],
                'Today' => (float) $stats['revenue_today'],
            ],
            'operations' => [
                'Active trips' => $stats['active_trips'],
                'Trips today' => $stats['trips_today'],
                'Completed today' => $stats['completed_trips_today'],
                'Pending complaints' => $stats['pending_complaints'],
            ],
        ];

        return view('admin.dashboard', compact(
            'stats',
            'active_trips',
            'recent_activities',
            'chartData'
        ));
    }

    /* ================= USERS ================= */

    public function users(Request $request): View
    {
        $query = User::latest();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->boolean('pending')) {
            $query->where('role', UserRole::Operator->value)
                ->whereNull('operator_approved_at');
        }

        $users = $query->paginate(25)->withQueryString();

        return view('admin.users', compact('users'));
    }

    public function createUser(): View
    {
        $roles = UserRole::cases();

        return view('admin.create', compact('roles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6'],
            'role' => ['required'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return redirect()
            ->route('admin.users')
            ->with('success', 'User created successfully.');
    }

    public function editUser(User $user): View
    {
        $roles = UserRole::cases();

        return view('admin.edit', compact('user', 'roles'));
    }

    public function updateUser(
        Request $request,
        User $user
    ): RedirectResponse {

        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email'],
            'role' => ['required'],
        ]);

        $user->update($data);

        return redirect()
            ->route('admin.users')
            ->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with(
            'success',
            'User deleted successfully.'
        );
    }

    /* ================= OPERATORS ================= */

    public function approveOperator(User $user): RedirectResponse
    {
        $user->update(['operator_approved_at' => now()]);

        return back()->with('success', 'Operator approved successfully.');
    }

    /* ================= BUSES ================= */

    public function buses(): View
    {
        $buses = Bus::with(['driver', 'operator'])
            ->latest()
            ->paginate(25);

        return view('admin.buses', compact('buses'));
    }

    public function createBus(): View
    {
        $operators = User::where('role', UserRole::Operator->value)
            ->whereNotNull('operator_approved_at')
            ->get();

        $drivers = $this->availableDrivers();

        return view('admin.create-bus', compact('operators', 'drivers'));
    }

    public function storeBus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'string', 'unique:buses,plate_number'],
            'fleet_number' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'integer', 'min:1'],
            'seats_per_row' => ['required', 'integer', 'min:1'],
            'model' => ['nullable', 'string'],
            'operator_id' => ['required', 'exists:users,id'],
            'driver_id' => ['required', 'exists:users,id', 'unique:buses,driver_id'],
        ]);

        $operator = User::where('id', $data['operator_id'])
            ->where('role', UserRole::Operator->value)
            ->firstOrFail();

        $driver = User::where('id', $data['driver_id'])
            ->where('role', UserRole::Driver->value)
            ->firstOrFail();

        // Enforce: driver must not be on an active/scheduled route right now
        $activeSchedule = Schedule::where('driver_id', $driver->id)
            ->whereIn('status', ['in_progress', 'boarding', 'scheduled'])
            ->whereDate('travel_date', today())
            ->first();
        if ($activeSchedule) {
            return back()
                ->withInput()
                ->withErrors(['driver_id' => 'This driver is currently assigned to an active trip today and cannot be assigned to a new bus at this time.']);
        }

        Bus::create([
            'plate_number' => $data['plate_number'],
            'fleet_number' => $data['fleet_number'] ?? null,
            'capacity' => $data['capacity'],
            'rows' => $data['rows'],
            'seats_per_row' => $data['seats_per_row'],
            'model' => $data['model'] ?? null,
            'operator_id' => $operator->id,
            'driver_id' => $driver->id,
            'status' => 'active',
        ]);

        return redirect()
            ->route('admin.buses')
            ->with('success', 'Bus created successfully.');
    }

    public function editBus(Bus $bus): View
    {
        $operators = User::where(
            'role',
            UserRole::Operator->value
        )->get();

        $drivers = $this->availableDrivers($bus->driver_id);

        // FIXED
        return view('admin.edit-bus', compact(
            'bus',
            'operators',
            'drivers'
        ));
    }

    public function updateBus(
        Request $request,
        Bus $bus
    ): RedirectResponse {

        $data = $request->validate([
            'plate_number' => ['required', 'string', 'unique:buses,plate_number,'.$bus->id],
            'fleet_number' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'integer', 'min:1'],
            'seats_per_row' => ['required', 'integer', 'min:1'],
            'model' => ['nullable', 'string'],
            'operator_id' => ['required', 'exists:users,id'],
            'driver_id' => ['required', 'exists:users,id', 'unique:buses,driver_id,'.$bus->id],
            'status' => ['nullable', 'string'],
        ]);

        User::where('id', $data['operator_id'])
            ->where('role', UserRole::Operator->value)
            ->firstOrFail();

        $driver = User::where('id', $data['driver_id'])
            ->where('role', UserRole::Driver->value)
            ->firstOrFail();

        // Enforce: if driver is changing, new driver must not have an active trip on a DIFFERENT bus today
        if ((int) $data['driver_id'] !== (int) $bus->driver_id) {
            $activeSchedule = Schedule::where('driver_id', $driver->id)
                ->whereIn('status', ['in_progress', 'boarding', 'scheduled'])
                ->whereDate('travel_date', today())
                ->first();
            if ($activeSchedule) {
                return back()
                    ->withInput()
                    ->withErrors(['driver_id' => 'This driver is currently on an active trip today and cannot be reassigned.']);
            }
        }

        $bus->update([
            'plate_number' => $data['plate_number'],
            'fleet_number' => $data['fleet_number'] ?? null,
            'capacity' => $data['capacity'],
            'rows' => $data['rows'],
            'seats_per_row' => $data['seats_per_row'],
            'model' => $data['model'] ?? null,
            'operator_id' => $data['operator_id'],
            'driver_id' => $data['driver_id'],
            'status' => $data['status'] ?? $bus->status,
        ]);

        return back()->with('success', 'Bus updated successfully.');
    }

    public function deleteBus(Bus $bus): RedirectResponse
    {
        $bus->delete();

        return back()->with(
            'success',
            'Bus deleted successfully.'
        );
    }

    /* ================= OTHER PAGES ================= */

    public function trips(): View
    {
        $trips = Schedule::with([
                'bus',
                'route',
                'driver'
            ])
            ->latest()
            ->paginate(25);

        return view('admin.trips', compact('trips'));
    }

    public function reports(): View
    {
        $data = [
            'total_revenue' => Payment::where(
                'status',
                'successful'
            )->sum('amount'),

            'total_bookings' => Booking::count(),

            'total_users' => User::count(),
        ];

        $reportTypes = [
            'overview' => 'System overview',
            'payments' => 'Payments and passengers',
            'operations' => 'Buses, drivers, and trips',
        ];

        return view('admin.reports', compact('data', 'reportTypes'));
    }

    public function monitor(Request $request): View
    {
        $activeStatuses = ['scheduled', 'boarding', 'in_progress', 'delayed'];

        $routes = BusRoute::with(['originStop', 'destinationStop', 'stops'])
            ->withCount(['schedules as active_schedules_count' => fn ($query) => $query
                ->whereIn('status', $activeStatuses)])
            ->whereHas('schedules', fn ($query) => $query->whereIn('status', $activeStatuses))
            ->orderBy('name')
            ->get();

        $selectedRouteId = $request->integer('route_id') ?: $routes->first()?->id;

        $trips = Schedule::with(['bus.driver', 'route', 'driver', 'latestLocation.nearestStop'])
            ->whereIn('status', $activeStatuses)
            ->when($selectedRouteId, fn ($query) => $query->where('route_id', $selectedRouteId))
            ->latest()
            ->get();

        return view('admin.monitor', compact('trips', 'routes', 'selectedRouteId'));
    }

    public function monitorData(Request $request): \Illuminate\Http\JsonResponse
    {
        $activeStatuses = ['scheduled', 'boarding', 'in_progress', 'delayed'];
        $selectedRouteId = $request->integer('route_id');

        $trips = Schedule::with(['bus.driver', 'route', 'driver', 'latestLocation.nearestStop'])
            ->whereIn('status', $activeStatuses)
            ->when($selectedRouteId, fn ($query) => $query->where('route_id', $selectedRouteId))
            ->latest()
            ->get()
            ->map(fn (Schedule $trip) => [
                'id' => $trip->id,
                'plate_number' => $trip->bus?->plate_number,
                'route' => $trip->route?->name,
                'driver' => $trip->driver?->name ?? $trip->bus?->driver?->name,
                'status' => $trip->status,
                'travel_date' => $trip->travel_date?->format('Y-m-d'),
                'departure_time' => $trip->departure_time,
                'latitude' => $trip->latestLocation?->latitude,
                'longitude' => $trip->latestLocation?->longitude,
                'speed_kmh' => $trip->latestLocation?->speed_kmh,
                'heading' => $trip->latestLocation?->heading,
                'nearest_stop' => $trip->latestLocation?->nearestStop?->name,
                'recorded_at' => $trip->latestLocation?->recorded_at?->toIso8601String(),
                'gps_age_seconds' => $trip->latestLocation?->recorded_at?->diffInSeconds(now()),
            ]);

        $route = $selectedRouteId
            ? BusRoute::with(['originStop', 'destinationStop', 'stops'])->find($selectedRouteId)
            : null;

        $routeStops = $route?->stops->map(fn (Stop $stop) => [
            'id' => $stop->id,
            'name' => $stop->name,
            'latitude' => (float) $stop->latitude,
            'longitude' => (float) $stop->longitude,
        ])->values() ?? collect();

        return response()->json([
            'trips' => $trips,
            'route' => $route ? [
                'id' => $route->id,
                'name' => $route->name,
                'origin' => $route->originStop?->name,
                'destination' => $route->destinationStop?->name,
                'stops' => $routeStops,
            ] : null,
        ]);
    }

    public function payments(): View
    {
        $payments = Payment::with([
                'booking.user',
                'booking.originStop',
                'booking.destinationStop',
                'booking.schedule.route',
                'booking.schedule.bus',
            ])
            ->latest()
            ->paginate(25);

        return view('admin.payments', compact('payments'));
    }

    public function downloadReport(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:overview,payments,operations'],
        ]);

        $payload = $this->reportPayload($data['type']);
        $pdf = $this->buildSimplePdf($payload['title'], $payload['subtitle'], $payload['rows']);
        $fileName = 'kbs-'.$data['type'].'-report-'.now()->format('Ymd-His').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function complaints(): View
    {
        $complaints = Complaint::latest()->paginate(25);

        return view('admin.complaints', compact('complaints'));
    }

    public function resolveComplaint(
        Complaint $complaint
    ): RedirectResponse {

        $complaint->update([
            'status' => 'resolved',
            'handled_by' => Auth::id(),
        ]);

        return back()->with(
            'success',
            'Complaint resolved successfully.'
        );
    }

    public function routes(): View
    {
        $routes = BusRoute::with(['operator', 'originStop', 'destinationStop'])
            ->withCount('schedules')
            ->latest()
            ->paginate(25);

        return view('admin.routes', compact('routes'));
    }

    public function busStatus(): View
    {
        $reports = BusStatusReport::with(['bus.driver', 'driver'])
            ->latest()
            ->paginate(30);

        $openCount     = BusStatusReport::where('status', 'open')->count();
        $resolvedCount = BusStatusReport::where('status', 'resolved')->count();

        return view('admin.bus-status', compact('reports', 'openCount', 'resolvedCount'));
    }

    public function resolveBusStatus(BusStatusReport $report): RedirectResponse
    {
        $report->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        // Set bus back to active when issue resolved
        $report->bus?->update(['status' => 'active']);

        return back()->with('success', 'Bus status report resolved. Bus set back to active.');
    }

    protected function availableDrivers(?int $currentDriverId = null): Collection
    {
        return User::where('role', UserRole::Driver->value)
            ->where(function ($query) use ($currentDriverId) {
                $query->whereDoesntHave('assignedBus');

                if ($currentDriverId) {
                    $query->orWhere('id', $currentDriverId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function reportPayload(string $type): array
    {
        if ($type === 'payments') {
            $rows = Payment::with(['booking.user', 'booking.originStop', 'booking.destinationStop', 'booking.schedule.route'])
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (Payment $payment) => [
                    'Passenger' => $payment->booking?->user?->name ?? '-',
                    'Route' => $payment->booking?->schedule?->route?->name ?? '-',
                    'From' => $payment->booking?->originStop?->name ?? '-',
                    'To' => $payment->booking?->destinationStop?->name ?? '-',
                    'Amount' => number_format((float) $payment->amount).' '.$payment->currency,
                    'Status' => ucfirst($payment->status),
                ])
                ->all();

            return ['title' => 'Payments and Passengers Report', 'subtitle' => 'Passenger payment activity with route selections', 'rows' => $rows];
        }

        if ($type === 'operations') {
            $rows = Schedule::with(['bus.driver', 'driver', 'route'])
                ->latest('travel_date')
                ->limit(30)
                ->get()
                ->map(fn (Schedule $schedule) => [
                    'Bus' => $schedule->bus?->plate_number ?? '-',
                    'Driver' => $schedule->driver?->name ?? $schedule->bus?->driver?->name ?? '-',
                    'Route' => $schedule->route?->name ?? '-',
                    'Date' => $schedule->travel_date?->format('d M Y') ?? '-',
                    'Time' => $schedule->departure_time,
                    'Status' => ucfirst($schedule->status),
                ])
                ->all();

            return ['title' => 'Operations Report', 'subtitle' => 'Bus, driver, route, and trip allocation status', 'rows' => $rows];
        }

        return [
            'title' => 'System Overview Report',
            'subtitle' => 'Summary of buses, bookings, passengers, and payments',
            'rows' => [
                ['Metric' => 'Total buses',          'Value' => number_format(Bus::count())],
                ['Metric' => 'Buses with drivers',   'Value' => number_format(Bus::whereNotNull('driver_id')->count())],
                ['Metric' => 'Total bookings',       'Value' => number_format(Booking::count())],
                ['Metric' => 'Passengers',           'Value' => number_format(User::where('role', UserRole::Passenger->value)->count())],
                ['Metric' => 'Successful payments',  'Value' => number_format(Payment::where('status', 'successful')->count())],
                ['Metric' => 'Total revenue',        'Value' => number_format((float) Payment::where('status', 'successful')->sum('amount')).' RWF'],
            ],
        ];
    }

    protected function buildSimplePdf(string $title, string $subtitle, array $rows): string
    {
        // ── Color palette (RGB 0-1): green=#1a7a4a yellow=#f5c800 white lightGreen lightGrey ──
        $green  = '0.10 0.48 0.29';   // header bg
        $greenD = '0.08 0.37 0.22';   // darker green for footer accent
        $yellow = '0.96 0.78 0.00';   // accent stripe
        $white  = '1 1 1';
        $ink    = '0.07 0.14 0.11';   // near-black text
        $muted  = '0.36 0.44 0.38';
        $rowA   = '0.93 0.97 0.94';   // light green row
        $rowB   = '1 1 1';            // white row
        $hdrBg  = '0.10 0.48 0.29';   // table header bg
        $thText = '1 1 1';

        $objects = [];

        // ── Page stream ──
        $c = '';

        // Full-page header band (dark green)
        $c .= "{$green} rg\n0 752 612 40 re f\n";
        // Yellow accent stripe under header
        $c .= "{$yellow} rg\n0 748 612 5 re f\n";
        // Logo / brand mark (yellow square)
        $c .= "{$yellow} rg\n28 756 28 28 re f\n";
        $c .= "{$greenD} rg\nBT /F1 14 Tf 32 762 Td (KBS) Tj ET\n";

        // Title
        $c .= $this->pdfText(68, 769, 15, $title, $white);
        // Subtitle and date
        $c .= $this->pdfText(68, 755, 8.5, $subtitle, '0.85 0.93 0.87');
        $c .= $this->pdfText(462, 755, 8, 'Generated: '.now()->format('d M Y, H:i'), '0.75 0.88 0.78');

        // ── Divider line ──
        $c .= "{$yellow} rg\n28 738 556 1.5 re f\n";

        // ── Summary info box ──
        $c .= "0.96 0.99 0.97 rg\n28 700 556 32 re f\n";
        $c .= "0.80 0.92 0.83 rg\n28 700 556 1 re f\n28 732 556 1 re f\n";
        $c .= $this->pdfText(36, 721, 8, 'KBS Limited | Transport Management System | Kigali, Rwanda', $muted);
        $c .= $this->pdfText(36, 709, 7.5, 'This report is auto-generated. For official records please verify with the system administrator.', $muted);

        // ── Table setup ──
        $y = 686;
        $headers = array_keys($rows[0] ?? ['Report' => 'No data available']);
        $colCount = count($headers);
        $tableW = 556;
        $colW = $tableW / max(1, $colCount);
        $rowH = 20;

        // Table header row
        $c .= "{$hdrBg} rg\n28 ".($y - $rowH + 4)." {$tableW} {$rowH} re f\n";
        // Yellow left border on header
        $c .= "{$yellow} rg\n28 ".($y - $rowH + 4)." 4 {$rowH} re f\n";
        foreach ($headers as $i => $h) {
            $c .= $this->pdfText(36 + $i * $colW, $y - 5, 8, strtoupper($h), $thText);
        }

        $y -= ($rowH + 2);

        // Data rows
        foreach ($rows as $ri => $row) {
            if ($y < 56) {
                break;
            }
            $fill = $ri % 2 === 0 ? $rowA : $rowB;
            $c .= "{$fill} rg\n28 ".($y - $rowH + 4)." {$tableW} {$rowH} re f\n";
            // Subtle left border for even rows
            if ($ri % 2 === 0) {
                $c .= "0.60 0.85 0.68 rg\n28 ".($y - $rowH + 4)." 3 {$rowH} re f\n";
            }
            foreach ($headers as $i => $h) {
                $c .= $this->pdfText(34 + $i * $colW, $y - 5, 7.5, (string) ($row[$h] ?? '—'), $ink);
            }
            $y -= $rowH;
        }

        // Row count note
        $c .= $this->pdfText(28, max(52, $y - 10), 7.5, 'Showing '.count($rows).' record(s).', $muted);

        // ── Footer band ──
        $c .= "{$green} rg\n0 0 612 36 re f\n";
        $c .= "{$yellow} rg\n0 36 612 3 re f\n";
        $c .= $this->pdfText(28, 14, 7.5, 'KBS Limited — Professional Transport Management System — Kigali, Rwanda', '0.85 0.93 0.87');
        $c .= $this->pdfText(490, 14, 7.5, 'Page 1 of 1', '0.85 0.93 0.87');

        // ── Assemble PDF objects ──
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Length '.strlen($c)." >>\nstream\n{$c}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $idx => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($idx + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    public function bookingHistory(Request $request): View
    {
        $query = BookingHistory::query();

        // Search filters
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('passenger_name', 'like', "%{$search}%")
                  ->orWhere('passenger_email', 'like', "%{$search}%")
                  ->orWhere('route_name', 'like', "%{$search}%")
                  ->orWhere('route_code', 'like', "%{$search}%")
                  ->orWhere('original_booking_reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('travel_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('travel_date', '<=', $request->query('date_to'));
        }

        $bookingHistories = $query->latest('archived_at')->paginate(25)->withQueryString();

        $stats = [
            'total_archived' => BookingHistory::count(),
            'total_amount' => BookingHistory::sum('amount'),
            'unique_passengers' => BookingHistory::distinct('passenger_email')->count(),
            'deleted_routes' => BookingHistory::distinct('route_code')->count(),
        ];

        return view('admin.booking-history', compact('bookingHistories', 'stats'));
    }

    public function passengerTracking(): View
    {
        $activeLocations = PassengerLocation::with('user')
            ->where('is_active', true)
            ->where('location_time', '>=', now()->subHours(2)) // Last 2 hours
            ->latest('location_time')
            ->paginate(50);

        $stats = [
            'active_passengers' => PassengerLocation::where('is_active', true)
                ->where('location_time', '>=', now()->subMinutes(30))
                ->count(),
            'total_locations_today' => PassengerLocation::whereDate('created_at', today())->count(),
            'unique_passengers_today' => PassengerLocation::whereDate('created_at', today())
                ->distinct('user_id')->count(),
        ];

        return view('admin.passenger-tracking', compact('activeLocations', 'stats'));
    }

    public function passengerTrackingMap(): View
    {
        return view('admin.passenger-tracking-map');
    }

    protected function pdfText(float $x, float $y, float $size, string $text, string $color): string
    {
        $clean = substr(str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text), 0, 90);

        return "{$color} rg\nBT /F1 {$size} Tf {$x} {$y} Td ({$clean}) Tj ET\n";
    }
}
