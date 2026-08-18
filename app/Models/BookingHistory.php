<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingHistory extends Model
{
    protected $fillable = [
        'original_booking_reference',
        'passenger_name',
        'passenger_email',
        'passenger_phone',
        'route_name',
        'route_code',
        'origin_stop_name',
        'destination_stop_name',
        'amount',
        'seat_number',
        'status',
        'travel_date',
        'departure_time',
        'bus_plate_number',
        'driver_name',
        'operator_name',
        'deletion_reason',
        'original_booking_date',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'travel_date' => 'datetime',
            'departure_time' => 'datetime',
            'original_booking_date' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
