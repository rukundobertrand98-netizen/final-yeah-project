<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Stop;
use Illuminate\Support\Collection;

class NearbyBusService
{
    public function __construct(
        protected BusTrackingService $tracking
    ) {}

    public function findNearestStop(float $latitude, float $longitude): ?Stop
    {
        return Stop::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Stop $stop) => $this->tracking->distanceKm(
                $latitude,
                $longitude,
                $stop->latitude,
                $stop->longitude
            ))
            ->first();
    }

    /**
     * @return array{nearest_stop: array|null, buses: array<int, array>}
     */
    public function busesNearPassenger(float $latitude, float $longitude): array
    {
        $nearestStop = $this->findNearestStop($latitude, $longitude);

        if (! $nearestStop) {
            return ['nearest_stop' => null, 'buses' => []];
        }

        $distanceToStopKm = $this->tracking->distanceKm(
            $latitude,
            $longitude,
            $nearestStop->latitude,
            $nearestStop->longitude
        );

        $schedules = Schedule::with(['route.stops', 'bus', 'latestLocation.nearestStop', 'driver'])
            ->whereDate('travel_date', today())
            ->whereIn('status', ['scheduled', 'boarding', 'in_progress', 'delayed'])
            ->get();

        $buses = $schedules
            ->filter(fn (Schedule $schedule) => $schedule->routeContainsStops($nearestStop->id))
            ->map(function (Schedule $schedule) use ($nearestStop) {
                return $this->formatBusForStop($schedule, $nearestStop);
            })
            ->filter(fn (array $bus) => $bus['passes_stop'])
            ->sortBy('distance_to_stop_km')
            ->values()
            ->all();

        return [
            'nearest_stop' => [
                'id' => $nearestStop->id,
                'name' => $nearestStop->name,
                'code' => $nearestStop->code,
                'latitude' => (float) $nearestStop->latitude,
                'longitude' => (float) $nearestStop->longitude,
                'distance_km' => round($distanceToStopKm, 2),
            ],
            'passenger' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'buses' => $buses,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    protected function formatBusForStop(Schedule $schedule, Stop $passengerStop): array
    {
        $location = $schedule->latestLocation;
        $passengerStopIdx = $schedule->stopOrderIndex($passengerStop->id);
        $origin = $schedule->effectiveOriginStop();
        $destination = $schedule->effectiveDestinationStop();

        $passengerStopOnRoute = $schedule->routeContainsStops($passengerStop->id);
        $busStopIdx = $location?->nearest_stop_id
            ? $schedule->stopOrderIndex((int) $location->nearest_stop_id)
            : null;

        $approaching = $passengerStopOnRoute && (
            in_array($schedule->status, ['scheduled', 'boarding', 'in_progress', 'delayed'], true)
            || ($busStopIdx !== null && $busStopIdx <= $passengerStopIdx)
        );

        $routePath = $schedule->orderedStopsForLeg()
            ->map(fn (Stop $stop) => [(float) $stop->latitude, (float) $stop->longitude])
            ->values()
            ->all();

        $distanceToStopKm = $location
            ? $this->tracking->distanceKm(
                $location->latitude,
                $location->longitude,
                $passengerStop->latitude,
                $passengerStop->longitude
            )
            : null;

        return [
            'schedule_id' => $schedule->id,
            'bus_plate' => $schedule->bus->plate_number,
            'route_name' => $schedule->displayRouteName(),
            'direction' => $schedule->leg_direction,
            'leg_number' => $schedule->leg_number,
            'status' => $schedule->status,
            'origin' => $origin?->name,
            'destination' => $destination?->name,
            'origin_stop_id' => $origin?->id,
            'destination_stop_id' => $destination?->id,
            'heading_to' => $destination?->name,
            'available_seats' => $schedule->availableSeatCount(),
            'passes_stop' => $approaching,
            'approaching_your_stop' => $approaching && in_array($schedule->status, ['in_progress', 'delayed', 'boarding'], true),
            'distance_to_stop_km' => $distanceToStopKm !== null ? round($distanceToStopKm, 2) : null,
            'location' => $location ? [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
                'nearest_stop' => $location->nearestStop?->name,
            ] : null,
            'route_path' => $routePath,
        ];
    }

    public function activeSchedulesForStop(int $stopId): Collection
    {
        return Schedule::with(['route.stops', 'bus', 'latestLocation'])
            ->whereDate('travel_date', today())
            ->whereIn('status', ['scheduled', 'boarding', 'in_progress', 'delayed'])
            ->get()
            ->filter(fn (Schedule $s) => $s->routeContainsStops($stopId));
    }
}
