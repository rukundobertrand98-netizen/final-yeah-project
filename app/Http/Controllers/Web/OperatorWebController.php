<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\BusStatusReport;
use App\Models\Payment;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use App\Models\BookingHistory;
use App\Models\PassengerLocation;

class OperatorWebController extends Controller
{
    public function dashboard(): View
    {
        $operatorId = Auth::id();

        $stats = [
            'buses' => Bus::where('operator_id', $operatorId)->count(),
            'routes' => Route::where('operator_id', $operatorId)->count(),
            'bookings_today' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId)->whereDate('travel_date', today()))->count(),
            'revenue' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId))->where('status', 'confirmed')->sum('amount'),
            'passengers' => User::whereHas('bookings.schedule', fn ($q) => $q->where('operator_id', $operatorId))->count(),
            'payments' => Payment::whereHas('booking.schedule', fn ($q) => $q->where('operator_id', $operatorId))->count(),
        ];

        $routes = Route::with(['originStop', 'destinationStop', 'stops'])
            ->where('operator_id', $operatorId)
            ->where('is_active', true)
            ->get();

        $activeTrips = Schedule::with(['route', 'bus', 'driver', 'latestLocation.nearestStop'])
            ->where('operator_id', $operatorId)
            ->whereIn('status', ['scheduled', 'boarding', 'in_progress'])
            ->latest('travel_date')
            ->limit(6)
            ->get();

        $recentBookings = Booking::with(['user', 'schedule.route', 'schedule.bus'])
            ->whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId))
            ->latest()
            ->limit(6)
            ->get();

        $recentPayments = Payment::with(['booking.user', 'booking.schedule.route'])
            ->whereHas('booking.schedule', fn ($q) => $q->where('operator_id', $operatorId))
            ->latest()
            ->limit(6)
            ->get();

        return view('operator.dashboard', compact('stats', 'routes', 'activeTrips', 'recentBookings', 'recentPayments'));
    }

    public function buses(): View
    {
        $buses = Bus::where('operator_id', Auth::id())->latest()->get();

        return view('operator.buses', compact('buses'));
    }

    public function busStatus(): View
    {
        $reports = BusStatusReport::with(['bus.driver'])
            ->whereHas('bus', fn ($query) => $query->where('operator_id', Auth::id()))
            ->latest()
            ->get();

        $openCount = $reports->where('status', 'open')->count();
        $resolvedCount = $reports->where('status', 'resolved')->count();

        return view('operator.bus-status', compact('reports', 'openCount', 'resolvedCount'));
    }

    public function storeBus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'unique:buses,plate_number'],
            'fleet_number' => ['nullable'],
            'capacity' => ['required', 'integer', 'min:10'],
            'rows' => ['required', 'integer'],
            'seats_per_row' => ['required', 'integer'],
            'model' => ['nullable'],
        ]);

        Bus::create([...$data, 'operator_id' => Auth::id()]);

        return back()->with('success', 'Bus added successfully.');
    }

    public function routes(): View
    {
        $routes = Route::with(['originStop', 'destinationStop', 'stops'])
            ->where('operator_id', Auth::id())
            ->orderByDesc('is_active')
            ->latest()
            ->get();

        return view('operator.routes', compact('routes'));
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required'],
            'code' => ['required', 'unique:routes,code'],
            'base_price' => ['required', 'numeric', 'min:100'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1'],
            'route_stops' => ['required', 'array', 'min:2'],
            'route_stops.*.name' => ['required', 'string', 'max:255'],
            'route_stops.*.code' => ['nullable', 'string', 'max:20'],
            'route_stops.*.district' => ['nullable', 'string', 'max:255'],
            'route_stops.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_stops.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $usedCodes = [];

        $stopModels = collect($data['route_stops'])->map(function (array $stop) use (&$usedCodes) {
            $submittedCode = strtoupper(trim((string) ($stop['code'] ?? '')));
            $code = $submittedCode && ! in_array($submittedCode, $usedCodes, true)
                ? $submittedCode
                : $this->makeStopCode($stop['name'], $usedCodes);

            $usedCodes[] = strtoupper($code);

            return Stop::updateOrCreate(
                ['code' => strtoupper($code)],
                [
                    'name' => $stop['name'],
                    'district' => ($stop['district'] ?? null) ?: 'Kigali',
                    'latitude' => $stop['latitude'],
                    'longitude' => $stop['longitude'],
                    'is_active' => true,
                ]
            );
        })->values();

        if ($stopModels->pluck('id')->unique()->count() < 2) {
            return back()->withErrors(['route_stops' => 'Enter at least two different bus stops for this route.'])->withInput();
        }

        if ($stopModels->pluck('id')->unique()->count() !== $stopModels->count()) {
            return back()->withErrors(['route_stops' => 'Each stop on a route must be different. Check for repeated stop codes or duplicate places.'])->withInput();
        }

        $mapPath = $this->fetchOsrmRoadPath($stopModels);

        $route = Route::create([
            'operator_id' => Auth::id(),
            'name' => $data['name'],
            'code' => $data['code'],
            'origin_stop_id' => $stopModels->first()->id,
            'destination_stop_id' => $stopModels->last()->id,
            'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            'base_price' => $data['base_price'],
            'map_path' => $mapPath,
        ]);

        foreach ($stopModels as $i => $stop) {
            $minutes = $stopModels->count() > 1
                ? (int) round(($data['estimated_duration_minutes'] / ($stopModels->count() - 1)) * $i)
                : 0;

            $route->stops()->attach($stop->id, ['sequence' => $i + 1, 'minutes_from_start' => $minutes]);
        }

        return back()->with('success', 'Route created.');
    }

    protected function makeStopCode(string $name, array $reservedCodes = []): string
    {
        $base = preg_replace('/[^A-Z0-9]/', '', strtoupper(substr($name, 0, 8))) ?: 'STOP';
        $code = $base;
        $suffix = 1;
        $reservedCodes = array_map('strtoupper', $reservedCodes);

        while (in_array($code, $reservedCodes, true) || Stop::where('code', $code)->exists()) {
            $code = substr($base, 0, 14).$suffix;
            $suffix++;
        }

        return $code;
    }

    public function editRoute(Route $route): View
    {
        if ($route->operator_id !== Auth::id()) {
            abort(403);
        }

        $route->load(['stops' => fn ($q) => $q->orderBy('route_stops.sequence')]);
        $buses = Bus::where('operator_id', Auth::id())->get();
        $drivers = User::where('role', UserRole::Driver->value)->get();
        $schedules = Schedule::with(['bus', 'driver'])
            ->where('route_id', $route->id)
            ->where('operator_id', Auth::id())
            ->latest('travel_date')
            ->get();

        return view('operator.route-edit', compact('route', 'buses', 'drivers', 'schedules'));
    }

    public function updateRoute(Request $request, Route $route): RedirectResponse
    {
        if ($route->operator_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required'],
            'code' => ['required', 'unique:routes,code,'.$route->id],
            'base_price' => ['required', 'numeric', 'min:100'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable'],
            'route_stops' => ['required', 'array', 'min:2'],
            'route_stops.*.name' => ['required', 'string', 'max:255'],
            'route_stops.*.code' => ['nullable', 'string', 'max:20'],
            'route_stops.*.district' => ['nullable', 'string', 'max:255'],
            'route_stops.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_stops.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $usedCodes = [];

        $stopModels = collect($data['route_stops'])->map(function (array $stop) use (&$usedCodes) {
            $submittedCode = strtoupper(trim((string) ($stop['code'] ?? '')));
            $code = $submittedCode && ! in_array($submittedCode, $usedCodes, true)
                ? $submittedCode
                : $this->makeStopCode($stop['name'], $usedCodes);

            $usedCodes[] = strtoupper($code);

            return Stop::updateOrCreate(
                ['code' => strtoupper($code)],
                [
                    'name' => $stop['name'],
                    'district' => ($stop['district'] ?? null) ?: 'Kigali',
                    'latitude' => $stop['latitude'],
                    'longitude' => $stop['longitude'],
                    'is_active' => true,
                ]
            );
        })->values();

        if ($stopModels->pluck('id')->unique()->count() < 2) {
            return back()->withErrors(['route_stops' => 'Enter at least two different bus stops for this route.'])->withInput();
        }

        if ($stopModels->pluck('id')->unique()->count() !== $stopModels->count()) {
            return back()->withErrors(['route_stops' => 'Each stop on a route must be different.'])->withInput();
        }

        $mapPath = $this->fetchOsrmRoadPath($stopModels);

        $route->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'origin_stop_id' => $stopModels->first()->id,
            'destination_stop_id' => $stopModels->last()->id,
            'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            'base_price' => $data['base_price'],
            'map_path' => $mapPath,
            'is_active' => $request->has('is_active'),
        ]);

        $route->stops()->detach();
        foreach ($stopModels as $i => $stop) {
            $minutes = $stopModels->count() > 1
                ? (int) round(($data['estimated_duration_minutes'] / ($stopModels->count() - 1)) * $i)
                : 0;

            $route->stops()->attach($stop->id, ['sequence' => $i + 1, 'minutes_from_start' => $minutes]);
        }

        return redirect()->route('operator.routes.edit', $route)->with('success', 'Route updated successfully.');
    }

    public function showRouteBookings(Route $route): View
    {
        if ($route->operator_id !== Auth::id()) {
            abort(403);
        }

        $bookings = Booking::with(['user', 'schedule.bus', 'schedule.driver', 'originStop', 'destinationStop', 'ticket', 'payment'])
            ->whereHas('schedule', fn ($q) => $q->where('route_id', $route->id))
            ->latest()
            ->paginate(20);

        return view('operator.route-bookings', compact('route', 'bookings'));
    }

    public function updateBookingStatus(Request $request, Booking $booking): RedirectResponse
    {
        // Verify the booking belongs to operator's route
        if ($booking->schedule->operator_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled,completed,boarded'],
            'reason' => ['nullable', 'string', 'max:500']
        ]);

        $booking->update(['status' => $data['status']]);

        $statusMessage = match($data['status']) {
            'cancelled' => 'Booking cancelled successfully',
            'completed' => 'Booking marked as completed',
            'confirmed' => 'Booking confirmed',
            'boarded' => 'Passenger marked as boarded',
        };

        return back()->with('success', $statusMessage);
    }

    public function deleteRoute(Route $route): RedirectResponse
    {
        if ($route->operator_id !== Auth::id()) {
            abort(403);
        }

        // Archive all bookings before deleting route
        $bookings = Booking::with(['user', 'schedule.bus', 'schedule.driver', 'schedule.route', 'originStop', 'destinationStop'])
            ->whereHas('schedule', fn ($q) => $q->where('route_id', $route->id))
            ->get();

        foreach ($bookings as $booking) {
            BookingHistory::create([
                'original_booking_reference' => $booking->reference,
                'passenger_name' => $booking->user->name,
                'passenger_email' => $booking->user->email,
                'passenger_phone' => $booking->user->phone,
                'route_name' => $booking->schedule->route->name,
                'route_code' => $booking->schedule->route->code,
                'origin_stop_name' => $booking->originStop->name,
                'destination_stop_name' => $booking->destinationStop->name,
                'amount' => $booking->amount,
                'seat_number' => $booking->seat_number,
                'status' => $booking->status,
                'travel_date' => $booking->schedule->travel_date,
                'departure_time' => $booking->schedule->departure_time,
                'bus_plate_number' => $booking->schedule->bus?->plate_number,
                'driver_name' => $booking->schedule->driver?->name,
                'operator_name' => Auth::user()->name,
                'deletion_reason' => 'Route deleted by operator',
                'original_booking_date' => $booking->created_at,
                'archived_at' => now(),
            ]);
        }

        // Delete all associated data
        $route->stops()->detach();
        Schedule::where('route_id', $route->id)->delete();
        $route->delete();

        return redirect()->route('operator.routes')->with('success', 
            "Route deleted successfully. {$bookings->count()} booking records have been archived for admin review.");
    }

    public function deleteSchedule(Schedule $schedule): RedirectResponse
    {
        if ($schedule->operator_id !== Auth::id()) {
            abort(403);
        }

        $hasBookings = $schedule->bookings()->whereIn('status', ['confirmed', 'boarded'])->exists();

        if ($hasBookings) {
            return back()->withErrors(['schedule' => 'Cannot delete schedule with active bookings. Cancel or complete the bookings first.']);
        }

        $schedule->bookings()->where('status', 'pending')->delete();
        $schedule->locations()->delete();
        $schedule->delete();

        return back()->with('success', 'Schedule deleted.');
    }

    protected function assertDriverBusAssignmentAllowed(array $data, ?Schedule $schedule = null): void
    {
        if (empty($data['bus_id'])) {
            return;
        }

        $bus = Bus::find($data['bus_id']);
        if (! $bus || $bus->status !== 'active') {
            throw ValidationException::withMessages([
                'bus_id' => 'This bus cannot be assigned because it is currently reported as having an issue or is not active.',
            ]);
        }

        // Allow a driver to be assigned to multiple buses/routes simultaneously.
        // (Business rule changed per operator request — do not block driver assignment.)
        if (empty($data['driver_id'])) {
            return;
        }

        $driverId = $data['driver_id'];
        $busId = $data['bus_id'];

        $conflictingBus = Schedule::where('bus_id', $busId)
            ->when($schedule, fn ($query) => $query->where('id', '<>', $schedule->id))
            ->whereNotNull('driver_id')
            ->where('driver_id', '<>', $driverId)
            ->exists();

        if ($conflictingBus) {
            throw ValidationException::withMessages([
                'driver_id' => 'This bus is already assigned to another driver. One bus may only have one driver.',
            ]);
        }
    }

    protected function fetchOsrmRoadPath(\Illuminate\Support\Collection $stopModels): ?array
    {
        if ($stopModels->count() < 2) {
            return null;
        }

        $coordinates = $stopModels->map(fn (Stop $stop) => $stop->longitude . ',' . $stop->latitude)->implode(';');

        try {
            $response = Http::timeout(10)->get("https://router.project-osrm.org/route/v1/driving/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

            if ($response->successful()) {
                $geometry = $response->json('routes.0.geometry.coordinates');
                if (is_array($geometry) && count($geometry) > 1) {
                    return array_map(fn (array $point) => [$point[1], $point[0]], $geometry);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OSRM road path fetch failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function schedules(): View
    {
        $schedules = Schedule::with(['route', 'bus.driver', 'driver'])
            ->withCount('bookings')
            ->where('operator_id', Auth::id())
            ->latest('travel_date')
            ->paginate(15);

        $routes = Route::where('operator_id', Auth::id())->get();
        $buses = Bus::with('driver')->where('operator_id', Auth::id())->where('status', 'active')->get();

        return view('operator.schedules', compact('schedules', 'routes', 'buses'));
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'route_id' => ['required', 'exists:routes,id'],
            'bus_id' => ['required', 'exists:buses,id'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'travel_date' => ['required', 'date'],
            'departure_time' => ['required'],
            'arrival_time' => ['nullable'],
            'price' => ['required', 'numeric', 'min:100'],
        ]);

        // Verify the bus belongs to this operator
        $bus = Bus::where('id', $data['bus_id'])
            ->where('operator_id', Auth::id())
            ->first();
        
        if (!$bus) {
            return back()->withErrors(['bus_id' => 'Selected bus does not belong to your fleet.'])->withInput();
        }

        // Use the bus's assigned driver if no driver specified
        if (empty($data['driver_id']) && $bus->driver_id) {
            $data['driver_id'] = $bus->driver_id;
        }

        // Verify the route belongs to this operator
        $route = Route::where('id', $data['route_id'])
            ->where('operator_id', Auth::id())
            ->first();
        
        if (!$route) {
            return back()->withErrors(['route_id' => 'Selected route does not belong to your operation.'])->withInput();
        }

        // Check if bus is already scheduled for another route on the same date
        $existingSchedule = Schedule::where('bus_id', $data['bus_id'])
            ->where('travel_date', $data['travel_date'])
            ->where('operator_id', Auth::id())
            ->whereNotIn('status', ['cancelled', 'completed']) // Ignore cancelled/completed schedules
            ->with('route')
            ->first();

        if ($existingSchedule) {
            return back()->withErrors([
                'bus_id' => "This bus is already scheduled for route '{$existingSchedule->route->name}' on {$existingSchedule->travel_date->format('M d, Y')}. A bus can only be assigned to one route per day."
            ])->withInput();
        }

        $this->assertDriverBusAssignmentAllowed($data);

        Schedule::create([...$data, 'operator_id' => Auth::id(), 'status' => 'scheduled']);

        return back()->with('success', 'Schedule created successfully.');
    }

    public function updateSchedule(Request $request, Schedule $schedule): RedirectResponse
    {
        if ($schedule->operator_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'route_id' => ['required', 'exists:routes,id'],
            'bus_id' => ['required', 'exists:buses,id'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'travel_date' => ['required', 'date'],
            'departure_time' => ['required'],
            'arrival_time' => ['nullable'],
            'price' => ['required', 'numeric', 'min:100'],
            'status' => ['required', 'in:scheduled,boarding,in_progress,delayed,completed,cancelled'],
        ]);

        // Verify the bus belongs to this operator
        $bus = Bus::where('id', $data['bus_id'])
            ->where('operator_id', Auth::id())
            ->first();
        
        if (!$bus) {
            return back()->withErrors(['bus_id' => 'Selected bus does not belong to your fleet.'])->withInput();
        }

        // Use the bus's assigned driver if no driver specified
        if (empty($data['driver_id']) && $bus->driver_id) {
            $data['driver_id'] = $bus->driver_id;
        }

        // Verify the route belongs to this operator
        $route = Route::where('id', $data['route_id'])
            ->where('operator_id', Auth::id())
            ->first();
        
        if (!$route) {
            return back()->withErrors(['route_id' => 'Selected route does not belong to your operation.'])->withInput();
        }

        // Check if bus is already scheduled for another route on the same date (excluding current schedule)
        $existingSchedule = Schedule::where('bus_id', $data['bus_id'])
            ->where('travel_date', $data['travel_date'])
            ->where('operator_id', Auth::id())
            ->where('id', '!=', $schedule->id) // Exclude current schedule
            ->whereNotIn('status', ['cancelled', 'completed']) // Ignore cancelled/completed schedules
            ->with('route')
            ->first();

        if ($existingSchedule) {
            return back()->withErrors([
                'bus_id' => "This bus is already scheduled for route '{$existingSchedule->route->name}' on {$existingSchedule->travel_date->format('M d, Y')}. A bus can only be assigned to one route per day."
            ])->withInput();
        }

        $this->assertDriverBusAssignmentAllowed($data, $schedule);

        $schedule->update($data);

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function bookings(): View
    {
        $bookings = Booking::with(['user', 'schedule.route', 'schedule.bus', 'ticket', 'payment'])
            ->whereHas('schedule', fn ($q) => $q->where('operator_id', Auth::id()))
            ->latest()
            ->paginate(20);

        return view('operator.bookings', compact('bookings'));
    }

    public function payments(): View
    {
        $payments = Payment::with(['booking.user', 'booking.schedule.route'])
            ->whereHas('booking.schedule', fn ($q) => $q->where('operator_id', Auth::id()))
            ->latest()
            ->paginate(20);

        return view('operator.payments', compact('payments'));
    }

    public function passengers(): View
    {
        $passengers = User::where('role', UserRole::Passenger->value)
            ->whereHas('bookings.schedule', fn ($q) => $q->where('operator_id', Auth::id()))
            ->withCount(['bookings as operator_bookings_count' => fn ($q) => $q->whereHas('schedule', fn ($s) => $s->where('operator_id', Auth::id()))])
            ->orderBy('name')
            ->paginate(20);

        return view('operator.passengers', compact('passengers'));
    }

    public function reports(): View
    {
        $operatorId = Auth::id();

        $stats = [
            'confirmed_bookings' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId))->where('status', 'confirmed')->count(),
            'boarded_passengers' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId))->where('status', 'boarded')->count(),
            'revenue' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId))->whereIn('status', ['confirmed', 'boarded'])->sum('amount'),
            'scheduled_trips' => Schedule::where('operator_id', $operatorId)->count(),
        ];

        $routes = Route::where('operator_id', $operatorId)
            ->withCount('schedules')
            ->get();

        return view('operator.reports', compact('stats', 'routes'));
    }
}
