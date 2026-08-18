<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    protected $fillable = [
        'operator_id', 'driver_id', 'plate_number', 'fleet_number', 'capacity',
        'rows', 'seats_per_row', 'model', 'status',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function statusReports(): HasMany
    {
        return $this->hasMany(BusStatusReport::class);
    }

    public function latestStatusReport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BusStatusReport::class)->latestOfMany();
    }

    public function seatLabels(): array
    {
        $seats = [];
        for ($row = 1; $row <= $this->rows; $row++) {
            for ($col = 1; $col <= $this->seats_per_row; $col++) {
                $seats[] = chr(64 + $col).$row;
            }
        }

        return array_slice($seats, 0, $this->capacity);
    }
}
