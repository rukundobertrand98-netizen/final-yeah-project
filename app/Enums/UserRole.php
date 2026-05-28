<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Driver = 'driver';
    case Passenger = 'passenger';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Operator => 'Bus Operator',
            self::Driver => 'Driver',
            self::Passenger => 'Passenger',
        };
    }
}
