<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusLocation extends Model
{
    protected $fillable = [
        'schedule_id', 'latitude', 'longitude', 'speed_kmh',
        'heading', 'nearest_stop_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed_kmh' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function nearestStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'nearest_stop_id');
    }
}
