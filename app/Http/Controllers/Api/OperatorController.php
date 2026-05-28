<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OperatorController extends Controller
{
    public function buses(Request $request): JsonResponse
    {
        $buses = Bus::where('operator_id', $request->user()->id)->latest()->get();

        return response()->json($buses);
    }

    public function storeBus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'string', 'unique:buses,plate_number'],
            'fleet_number' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:10', 'max:80'],
            'rows' => ['required', 'integer', 'min:5', 'max:20'],
            'seats_per_row' => ['required', 'integer', 'min:2', 'max:6'],
            'model' => ['nullable', 'string'],
        ]);

        $bus = Bus::create([...$data, 'operator_id' => $request->user()->id]);

        return response()->json($bus, 201);
    }

    public function routes(Request $request): JsonResponse
    {
        $routes = Route::with(['originStop', 'destinationStop', 'stops'])
            ->where('operator_id', $request->user()->id)
            ->get();

        return response()->json($routes);
    }

    public function storeRoute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'unique:routes,code'],
            'origin_stop_id' => ['required', 'exists:stops,id'],
            'destination_stop_id' => ['required', 'exists:stops,id'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5'],
            'distance_km' => ['nullable', 'numeric'],
            'base_price' => ['required', 'numeric', 'min:100'],
            'description' => ['nullable', 'string'],
            'stop_ids' => ['required', 'array', 'min:2'],
            'stop_ids.*' => ['exists:stops,id'],
        ]);

        $stops = \App\Models\Stop::whereIn('id', $data['stop_ids'])->get()->keyBy('id');

        $route = Route::create([
            'operator_id' => $request->user()->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'origin_stop_id' => $data['origin_stop_id'],
            'destination_stop_id' => $data['destination_stop_id'],
            'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            'distance_km' => $data['distance_km'] ?? null,
            'base_price' => $data['base_price'],
            'description' => $data['description'] ?? null,
            'map_path' => collect($data['stop_ids'])->map(fn ($stopId) => [
                'lat' => (float) $stops[$stopId]->latitude,
                'lng' => (float) $stops[$stopId]->longitude,
            ])->values()->all(),
        ]);

        foreach ($data['stop_ids'] as $index => $stopId) {
            $route->stops()->attach($stopId, [
                'sequence' => $index + 1,
                'minutes_from_start' => (int) round(($data['estimated_duration_minutes'] / (count($data['stop_ids']) - 1)) * $index),
            ]);
        }

        return response()->json($route->load('stops'), 201);
    }

    public function schedules(Request $request): JsonResponse
    {
        $schedules = Schedule::with(['route', 'bus', 'driver'])
            ->where('operator_id', $request->user()->id)
            ->latest('travel_date')
            ->paginate(20);

        return response()->json($schedules);
    }

    public function storeSchedule(Request $request): JsonResponse
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

        $schedule = Schedule::create([
            ...$data,
            'operator_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        return response()->json($schedule->load(['route', 'bus', 'driver']), 201);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = Booking::with(['user', 'schedule.route', 'ticket', 'payment'])
            ->whereHas('schedule', fn ($q) => $q->where('operator_id', $request->user()->id))
            ->latest()
            ->paginate(25);

        return response()->json($bookings);
    }

    public function assignDriver(Request $request, Schedule $schedule): JsonResponse
    {
        if ($schedule->operator_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate(['driver_id' => ['required', 'exists:users,id']]);

        $driver = User::where('id', $data['driver_id'])->where('role', UserRole::Driver->value)->firstOrFail();
        $schedule->update(['driver_id' => $driver->id]);

        return response()->json($schedule->load('driver'));
    }

    public function reports(Request $request): JsonResponse
    {
        $operatorId = $request->user()->id;

        return response()->json([
            'total_buses' => Bus::where('operator_id', $operatorId)->count(),
            'active_routes' => Route::where('operator_id', $operatorId)->where('is_active', true)->count(),
            'bookings_today' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId)->whereDate('travel_date', today()))->count(),
            'revenue_today' => Booking::whereHas('schedule', fn ($q) => $q->where('operator_id', $operatorId)->whereDate('travel_date', today()))
                ->where('status', 'confirmed')
                ->sum('amount'),
        ]);
    }

    public function storeDriver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'password' => ['required', 'min:8'],
            'national_id' => ['nullable', 'string'],
        ]);

        $driver = User::create([
            ...$data,
            'role' => UserRole::Driver,
            'password' => Hash::make($data['password']),
            'operator_approved_at' => now(),
        ]);

        return response()->json($driver, 201);
    }
}
