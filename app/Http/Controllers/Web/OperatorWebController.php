<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Payment;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OperatorWebController extends Controller
{
    public function dashboard(): View
    {
        $operatorId = auth()->id();

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
        $buses = Bus::where('operator_id', auth()->id())->latest()->get();

        return view('operator.buses', compact('buses'));
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

        Bus::create([...$data, 'operator_id' => auth()->id()]);

        return back()->with('success', 'Bus added successfully.');
    }

    public function routes(): View
    {
        $routes = Route::with(['originStop', 'destinationStop', 'stops'])->where('operator_id', auth()->id())->get();

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
            'operator_id' => auth()->id(),
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
        $schedules = Schedule::with(['route', 'bus', 'driver'])
            ->where('operator_id', auth()->id())
            ->latest('travel_date')
            ->paginate(15);

        $routes = Route::where('operator_id', auth()->id())->get();
        $buses = Bus::where('operator_id', auth()->id())->get();
        $drivers = User::where('role', UserRole::Driver->value)->get();

        return view('operator.schedules', compact('schedules', 'routes', 'buses', 'drivers'));
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

        Schedule::create([...$data, 'operator_id' => auth()->id(), 'status' => 'scheduled']);

        return back()->with('success', 'Schedule created.');
    }

    public function bookings(): View
    {
        $bookings = Booking::with(['user', 'schedule.route', 'schedule.bus', 'ticket', 'payment'])
            ->whereHas('schedule', fn ($q) => $q->where('operator_id', auth()->id()))
            ->latest()
            ->paginate(20);

        return view('operator.bookings', compact('bookings'));
    }

    public function payments(): View
    {
        $payments = Payment::with(['booking.user', 'booking.schedule.route'])
            ->whereHas('booking.schedule', fn ($q) => $q->where('operator_id', auth()->id()))
            ->latest()
            ->paginate(20);

        return view('operator.payments', compact('payments'));
    }

    public function passengers(): View
    {
        $passengers = User::where('role', UserRole::Passenger->value)
            ->whereHas('bookings.schedule', fn ($q) => $q->where('operator_id', auth()->id()))
            ->withCount(['bookings as operator_bookings_count' => fn ($q) => $q->whereHas('schedule', fn ($s) => $s->where('operator_id', auth()->id()))])
            ->orderBy('name')
            ->paginate(20);

        return view('operator.passengers', compact('passengers'));
    }

    public function reports(): View
    {
        $operatorId = auth()->id();

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
