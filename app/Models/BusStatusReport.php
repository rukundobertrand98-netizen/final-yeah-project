<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusStatusReport extends Model
{
    protected $fillable = [
        'bus_id', 'driver_id', 'issue_type', 'description',
        'estimated_fix_at', 'status', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_fix_at' => 'datetime',
            'resolved_at'      => 'datetime',
        ];
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
