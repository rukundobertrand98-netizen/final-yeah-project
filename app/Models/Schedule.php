<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Schedule extends Model
{
    protected $fillable = [
        'route_id', 'bus_id', 'driver_id', 'operator_id', 'travel_date',
        'departure_time', 'arrival_time', 'price', 'status',
        'leg_direction', 'leg_number',
        'started_at', 'ended_at', 'arrived_at', 'delay_reason',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'price' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'arrived_at' => 'datetime',
            'leg_number' => 'integer',
        ];
    }

    public function isReverseLeg(): bool
    {
        return $this->leg_direction === 'reverse';
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['scheduled', 'boarding', 'in_progress', 'delayed', 'arrived'], true);
    }

    public function orderedStopsForLeg(): \Illuminate\Support\Collection
    {
        $this->loadMissing('route.stops');

        $stops = $this->route->stops
            ->sortBy(function (Stop $stop) {
                $sequence = data_get($stop, 'pivot.sequence');

                return is_numeric($sequence) ? (int) $sequence : PHP_INT_MAX;
            })
            ->values();

        return $this->isReverseLeg() ? $stops->reverse()->values() : $stops;
    }

    public function effectiveOriginStop(): ?Stop
    {
        return $this->orderedStopsForLeg()->first();
    }

    public function effectiveDestinationStop(): ?Stop
    {
        return $this->orderedStopsForLeg()->last();
    }

    public function stopOrderIndex(int $stopId): ?int
    {
        $index = $this->orderedStopsForLeg()->search(fn (Stop $stop) => $stop->id === $stopId);

        return $index === false ? null : (int) $index;
    }

    public function routeContainsStops(int ...$stopIds): bool
    {
        $this->loadMissing('route.stops');

        $routeStopIds = $this->route->stops
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $routeStopNames = $this->route->stops
            ->map(fn (Stop $stop) => $this->normalizeStopName($stop->name))
            ->filter()
            ->values();

        foreach ($stopIds as $stopId) {
            if ($stopId <= 0) {
                continue;
            }

            if ($routeStopIds->contains((int) $stopId)) {
                continue;
            }

            $requestedStop = Stop::find($stopId);
            if (! $requestedStop) {
                return false;
            }

            $requestedName = $this->normalizeStopName($requestedStop->name);
            if ($requestedName === '' || ! $routeStopNames->contains($requestedName)) {
                return false;
            }
        }

        return true;
    }

    public function stopsConnectInDirection(int $originStopId, int $destinationStopId): bool
    {
        if ($originStopId === $destinationStopId) {
            return false;
        }

        if (! $this->routeContainsStops($originStopId, $destinationStopId)) {
            return false;
        }

        $orderedStops = $this->orderedStopsForLeg();
        $originPosition = $this->positionOfStopInRoute($orderedStops, $originStopId);
        $destinationPosition = $this->positionOfStopInRoute($orderedStops, $destinationStopId);

        return $originPosition !== null && $destinationPosition !== null && $originPosition < $destinationPosition;
    }

    protected function normalizeStopName(?string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name)));
    }

    protected function positionOfStopInRoute(
        \Illuminate\Support\Collection $orderedStops,
        int $requestedStopId
    ): ?int {
        $exactMatchIndex = $orderedStops->search(fn (Stop $stop) => (int) $stop->id === $requestedStopId);
        if ($exactMatchIndex !== false) {
            return (int) $exactMatchIndex;
        }

        $requestedStop = Stop::find($requestedStopId);
        if (! $requestedStop) {
            return null;
        }

        $requestedName = $this->normalizeStopName($requestedStop->name);
        if ($requestedName === '') {
            return null;
        }

        $nameMatchIndex = $orderedStops->search(fn (Stop $stop) => $this->normalizeStopName($stop->name) === $requestedName);

        return $nameMatchIndex === false ? null : (int) $nameMatchIndex;
    }

    public function displayRouteName(): string
    {
        $origin = $this->effectiveOriginStop();
        $destination = $this->effectiveDestinationStop();

        if (! $origin || ! $destination) {
            return $this->route->name ?? 'Route';
        }

        $label = "{$origin->name} → {$destination->name}";

        if ($this->isReverseLeg()) {
            $label .= ' (Return)';
        }

        return $label;
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(BusLocation::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(BusLocation::class)->latestOfMany('recorded_at');
    }

    public function tripReports(): HasMany
    {
        return $this->hasMany(TripReport::class);
    }

    public function occupiedSeats(): array
    {
        $pendingCutoff = now()->subMinutes((int) config('kbs.booking.pending_hold_minutes', 15));

        return $this->bookings()
            ->where('leg_number', $this->leg_number)
            ->where(function ($q) use ($pendingCutoff) {
                $q->whereIn('status', ['confirmed', 'boarded'])
                    ->orWhere(function ($sq) use ($pendingCutoff) {
                        $sq->where('status', 'pending')
                            ->where('created_at', '>=', $pendingCutoff);
                    });
            })
            ->pluck('seat_number')
            ->flatMap(fn (string $seats) => array_map('trim', explode(',', $seats)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function availableSeatCount(): int
    {
        return max(0, (int) $this->bus->capacity - count($this->occupiedSeats()));
    }

    public function minutesFromStartForStop(int $stopId): ?int
    {
        $index = $this->stopOrderIndex($stopId);

        if ($index === null) {
            return null;
        }

        $stop = $this->orderedStopsForLeg()->get($index);

        return $stop ? (int) ($stop->pivot->minutes_from_start ?? ($index * 8)) : null;
    }

    public function timeAtStop(int $stopId): ?Carbon
    {
        if (! $this->started_at) {
            return null;
        }

        $index = $this->stopOrderIndex($stopId);
        if ($index === null) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($index * 8);
    }

    public function liveStatusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'En route — live',
            'boarding' => 'Boarding now',
            'delayed' => 'Delayed — en route',
            'arrived' => 'Arrived at destination',
            'scheduled' => 'Waiting to depart',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
