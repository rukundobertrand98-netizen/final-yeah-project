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

        $schedules = Schedule::with(['route.stops', 'bus', 'driver', 'latestLocation'])
            ->whereDate('travel_date', $date)
            ->whereIn('status', ['scheduled', 'boarding', 'in_progress', 'delayed'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'boarding' THEN 1 WHEN 'delayed' THEN 2 ELSE 3 END")
            ->orderBy('departure_time')
            ->get()
            ->filter(fn (Schedule $schedule) => $schedule->stopsConnectInDirection(
                (int) $data['origin_stop_id'],
                (int) $data['destination_stop_id']
            ) && $schedule->availableSeatCount() > 0)
            ->map(function (Schedule $schedule) use ($data) {
                return [
                    ...$schedule->toArray(),
                    'display_route' => $schedule->displayRouteName(),
                    'live_status' => $schedule->liveStatusLabel(),
                    'leg_direction' => $schedule->leg_direction,
                    'arrival_at_origin' => $schedule->timeAtStop((int) $data['origin_stop_id'])?->toDateTimeString(),
                    'available_seat_count' => $schedule->availableSeatCount(),
                    'available_seats' => array_values(array_diff(
                        $schedule->bus->seatLabels(),
                        $schedule->occupiedSeats()
                    )),
                    'location' => $schedule->latestLocation,
                ];
            })
            ->values();

        $routes = $schedules->pluck('route')->unique('id')->values();

        return response()->json([
            'routes' => $routes,
            'schedules' => $schedules,
            'travel_date' => $date,
        ]);
    }
}
