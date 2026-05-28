<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusLocation;
use App\Models\PassengerAlert;
use App\Models\Schedule;
use App\Models\Stop;
use Illuminate\Support\Facades\Log;

class BusTrackingService
{
    public function updateLocation(
        Schedule $schedule,
        float $latitude,
        float $longitude,
        ?float $speed = null,
        ?int $heading = null
    ): BusLocation {
        $nearest = $this->findNearestStop($latitude, $longitude);

        $location = BusLocation::create([
            'schedule_id' => $schedule->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed_kmh' => $speed,
            'heading' => $heading,
            'nearest_stop_id' => $nearest?->id,
            'recorded_at' => now(),
        ]);

        $this->notifyPassengersNearPickup($schedule, $nearest);

        return $location;
    }

    public function findNearestStop(float $lat, float $lng, ?int $routeId = null): ?Stop
    {
        $query = Stop::query()->where('is_active', true);

        if ($routeId) {
            $query->whereHas('routes', fn ($q) => $q->where('routes.id', $routeId));
        }

        return $query->get()
            ->sortBy(fn (Stop $stop) => $this->distanceKm($lat, $lng, $stop->latitude, $stop->longitude))
            ->first();
    }

    public function isNearStop(float $lat, float $lng, Stop $stop, ?float $radiusKm = null): bool
    {
        $radiusKm ??= (float) config('kbs.tracking.proximity_radius_km', 0.5);

        return $this->distanceKm($lat, $lng, $stop->latitude, $stop->longitude) <= $radiusKm;
    }

    public function buildBookingTracking(Booking $booking): array
    {
        $booking->loadMissing([
            'originStop',
            'destinationStop',
            'schedule.latestLocation.nearestStop',
            'schedule.route.stops',
        ]);

        $schedule = $booking->schedule;
        $bus = $schedule->latestLocation;
        $departure = $booking->originStop;
        $destination = $booking->destinationStop;

        $nearestToBus = $bus
            ? $this->findNearestStop($bus->latitude, $bus->longitude, $schedule->route_id)
            : null;

        $distanceToDepartureKm = $bus
            ? $this->distanceKm($bus->latitude, $bus->longitude, $departure->latitude, $departure->longitude)
            : null;

        $approachingPickup = $bus && $this->isNearStop($bus->latitude, $bus->longitude, $departure);
        $estimatedArrivalMinutes = $bus && $distanceToDepartureKm !== null
            ? max(1, (int) round(($distanceToDepartureKm / max((float) ($bus->speed_kmh ?: 25), 10)) * 60))
            : null;

        $routeStops = $schedule->route->stops
            ->sortBy(fn (Stop $stop) => (int) $stop->pivot->sequence)
            ->values()
            ->map(fn (Stop $stop) => [
            'id' => $stop->id,
            'name' => $stop->name,
            'code' => $stop->code,
            'latitude' => (float) $stop->latitude,
            'longitude' => (float) $stop->longitude,
            'sequence' => (int) $stop->pivot->sequence,
            'is_departure' => $stop->id === $departure->id,
            'is_destination' => $stop->id === $destination->id,
        ])->values();

        return [
            'booking_reference' => $booking->reference,
            'route_name' => $schedule->route->name,
            'trip_status' => $schedule->status,
            'departure' => [
                'id' => $departure->id,
                'name' => $departure->name,
                'code' => $departure->code,
                'latitude' => (float) $departure->latitude,
                'longitude' => (float) $departure->longitude,
            ],
            'destination' => [
                'id' => $destination->id,
                'name' => $destination->name,
                'code' => $destination->code,
                'latitude' => (float) $destination->latitude,
                'longitude' => (float) $destination->longitude,
            ],
            'bus' => $bus ? [
                'latitude' => (float) $bus->latitude,
                'longitude' => (float) $bus->longitude,
                'recorded_at' => $bus->recorded_at->toIso8601String(),
                'speed_kmh' => $bus->speed_kmh,
            ] : null,
            'nearest_stop' => $nearestToBus ? [
                'id' => $nearestToBus->id,
                'name' => $nearestToBus->name,
                'latitude' => (float) $nearestToBus->latitude,
                'longitude' => (float) $nearestToBus->longitude,
                'is_your_departure' => $nearestToBus->id === $departure->id,
            ] : null,
            'distance_to_departure_km' => $distanceToDepartureKm !== null ? round($distanceToDepartureKm, 2) : null,
            'approaching_pickup' => $approachingPickup,
            'estimated_arrival_minutes' => $estimatedArrivalMinutes,
            'proximity_radius_km' => (float) config('kbs.tracking.proximity_radius_km', 0.5),
            'map_path' => $schedule->route->map_path,
            'route_stops' => $routeStops,
        ];
    }

    protected function notifyPassengersNearPickup(Schedule $schedule, ?Stop $nearest): void
    {
        if (! $nearest) {
            return;
        }

        $bookings = Booking::with('user')
            ->where('schedule_id', $schedule->id)
            ->where('origin_stop_id', $nearest->id)
            ->where('status', 'confirmed')
            ->get();

        foreach ($bookings as $booking) {
            $exists = PassengerAlert::query()
                ->where('user_id', $booking->user_id)
                ->where('schedule_id', $schedule->id)
                ->where('stop_id', $nearest->id)
                ->where('type', 'proximity')
                ->exists();

            if ($exists) {
                continue;
            }

            PassengerAlert::create([
                'user_id' => $booking->user_id,
                'schedule_id' => $schedule->id,
                'stop_id' => $nearest->id,
                'type' => 'proximity',
                'message' => "Your bus is approaching {$nearest->name} Stop. Please prepare for boarding. Estimated arrival: 5 minutes.",
            ]);

            $this->sendSmsNotification(
                $booking->user?->phone,
                "Your bus is approaching {$nearest->name} Stop. Please prepare for boarding. Estimated arrival: 5 minutes."
            );
        }
    }

    protected function sendSmsNotification(?string $phone, string $message): void
    {
        if (! $phone) {
            return;
        }

        Log::info('Passenger SMS notification queued', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }

    public function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
