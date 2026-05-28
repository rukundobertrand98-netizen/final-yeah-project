<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteSearchController extends Controller
{
    public function stops(): JsonResponse
    {
        $stops = \App\Models\Stop::query()
            ->where('is_active', true)
            ->where('district', 'Kigali')
            ->orderBy('name')
            ->get();

        return response()->json($stops);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_stop_id' => ['required', 'exists:stops,id'],
            'destination_stop_id' => ['required', 'exists:stops,id', 'different:origin_stop_id'],
            'travel_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $date = $data['travel_date'] ?? now()->toDateString();

        $routes = Route::with(['originStop', 'destinationStop', 'stops'])
            ->where('is_active', true)
            ->whereHas('stops', fn ($q) => $q->where('stops.id', $data['origin_stop_id']))
            ->whereHas('stops', fn ($q) => $q->where('stops.id', $data['destination_stop_id']))
            ->get()
            ->filter(function (Route $route) use ($data) {
                $origin = $route->stops->firstWhere('id', (int) $data['origin_stop_id']);
                $destination = $route->stops->firstWhere('id', (int) $data['destination_stop_id']);

                return $origin && $destination
                    && (int) $origin->pivot->sequence < (int) $destination->pivot->sequence;
            })
            ->values();

        $schedules = Schedule::with(['route.stops', 'bus', 'driver'])
            ->whereIn('route_id', $routes->pluck('id'))
            ->whereDate('travel_date', $date)
            ->whereIn('status', ['scheduled', 'boarding', 'in_progress'])
            ->orderBy('departure_time')
            ->get()
            ->filter(fn (Schedule $schedule) => $schedule->availableSeatCount() > 0)
            ->map(function (Schedule $schedule) use ($data) {
                return [
                    ...$schedule->toArray(),
                    'arrival_at_origin' => $schedule->timeAtStop((int) $data['origin_stop_id'])?->toDateTimeString(),
                    'available_seat_count' => $schedule->availableSeatCount(),
                    'available_seats' => array_values(array_diff(
                        $schedule->bus->seatLabels(),
                        $schedule->occupiedSeats()
                    )),
                ];
            });

        return response()->json([
            'routes' => $routes,
            'schedules' => $schedules,
            'travel_date' => $date,
        ]);
    }
}
