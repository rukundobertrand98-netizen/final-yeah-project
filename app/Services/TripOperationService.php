<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use InvalidArgumentException;

class TripOperationService
{
    public function markArrived(Schedule $schedule): Schedule
    {
        if (! in_array($schedule->status, ['in_progress', 'delayed'], true)) {
            throw new InvalidArgumentException('Trip must be in progress before marking arrival.');
        }

        $schedule->update([
            'status' => 'arrived',
            'arrived_at' => now(),
        ]);

        Booking::where('schedule_id', $schedule->id)
            ->where('leg_number', $schedule->leg_number)
            ->where('status', 'boarded')
            ->update(['status' => 'completed']);

        return $schedule->fresh(['route.stops', 'bus']);
    }

    public function startReturnTrip(Schedule $schedule): Schedule
    {
        if ($schedule->status !== 'arrived') {
            throw new InvalidArgumentException('Mark the trip as Arrived at destination before starting return.');
        }

        $nextDirection = $schedule->isReverseLeg() ? 'forward' : 'reverse';

        $schedule->update([
            'leg_direction' => $nextDirection,
            'leg_number' => $schedule->leg_number + 1,
            'status' => 'in_progress',
            'arrived_at' => null,
            'started_at' => now(),
            'ended_at' => null,
            'delay_reason' => null,
        ]);

        return $schedule->fresh(['route.stops', 'bus']);
    }

    public function startTrip(Schedule $schedule): Schedule
    {
        if (! in_array($schedule->status, ['scheduled', 'boarding', 'delayed'], true)) {
            throw new InvalidArgumentException('This trip cannot be started from its current status.');
        }

        $schedule->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $schedule->fresh(['route.stops', 'bus']);
    }
}
