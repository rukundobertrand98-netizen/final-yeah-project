<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'operator_id', 'name', 'code', 'origin_stop_id', 'destination_stop_id',
        'estimated_duration_minutes', 'distance_km', 'base_price', 'description',
        'map_path', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'distance_km' => 'float',
            'base_price' => 'decimal:2',
            'map_path' => 'array',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    public function stops(): BelongsToMany
    {
        return $this->belongsToMany(Stop::class, 'route_stops')
            ->withPivot(['sequence', 'minutes_from_start'])
            ->orderByPivot('sequence');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
