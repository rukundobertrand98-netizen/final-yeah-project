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
        'started_at', 'ended_at', 'delay_reason',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'price' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
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
        $stop = $this->route->stops->firstWhere('id', $stopId);

        return $stop ? (int) $stop->pivot->minutes_from_start : null;
    }

    public function timeAtStop(int $stopId): ?Carbon
    {
        $minutes = $this->minutesFromStartForStop($stopId);

        if ($minutes === null) {
            return null;
        }

        return Carbon::parse($this->travel_date->toDateString().' '.$this->departure_time)
            ->addMinutes($minutes);
    }
}
