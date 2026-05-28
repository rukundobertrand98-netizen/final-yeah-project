<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingService
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function create(
        User $passenger,
        Schedule $schedule,
        int $originStopId,
        int $destinationStopId,
        array|string $seatNumbers
    ): Booking {
        if ($schedule->travel_date->isPast() && ! $schedule->travel_date->isToday()) {
            throw new InvalidArgumentException('Cannot book a past trip.');
        }

        $requestedSeats = $this->normalizeSeats($seatNumbers);

        if (empty($requestedSeats)) {
            throw new InvalidArgumentException('Select at least one seat.');
        }

        $seatLabels = $schedule->bus->seatLabels();
        foreach ($requestedSeats as $seatNumber) {
            if (! in_array($seatNumber, $seatLabels, true)) {
                throw new InvalidArgumentException("Invalid seat selection: {$seatNumber}.");
            }
        }

        $schedule->loadMissing('route.stops', 'bus');
        $origin = $schedule->route->stops->firstWhere('id', $originStopId);
        $destination = $schedule->route->stops->firstWhere('id', $destinationStopId);

        if (! $origin || ! $destination) {
            throw new InvalidArgumentException('Selected stops are not on this route.');
        }

        if ((int) $origin->pivot->sequence >= (int) $destination->pivot->sequence) {
            throw new InvalidArgumentException('Destination must be after your origin on this route.');
        }

        return DB::transaction(function () use ($passenger, $schedule, $originStopId, $destinationStopId, $requestedSeats) {
            $lockedSchedule = Schedule::with('bus')->lockForUpdate()->findOrFail($schedule->id);
            $pendingCutoff = now()->subMinutes((int) config('kbs.booking.pending_hold_minutes', 15));
            $occupied = Booking::where('schedule_id', $lockedSchedule->id)
                ->where(function ($q) use ($pendingCutoff) {
                    $q->whereIn('status', ['confirmed', 'boarded'])
                        ->orWhere(function ($sq) use ($pendingCutoff) {
                            $sq->where('status', 'pending')
                                ->where('created_at', '>=', $pendingCutoff);
                        });
                })
                ->lockForUpdate()
                ->pluck('seat_number')
                ->flatMap(fn (string $seats) => array_map('trim', explode(',', $seats)))
                ->filter()
                ->values()
                ->all();
            $alreadyTaken = array_values(array_intersect($requestedSeats, $occupied));

            if (! empty($alreadyTaken)) {
                throw new InvalidArgumentException('Seat already taken: '.implode(', ', $alreadyTaken).'.');
            }

            $booking = Booking::create([
                'reference' => 'KBS-'.strtoupper(Str::random(8)),
                'user_id' => $passenger->id,
                'schedule_id' => $lockedSchedule->id,
                'origin_stop_id' => $originStopId,
                'destination_stop_id' => $destinationStopId,
                'seat_number' => implode(', ', $requestedSeats),
                'amount' => $lockedSchedule->price * count($requestedSeats),
                'status' => 'pending',
            ]);

            $this->ticketService->issueForBooking($booking);

            return $booking->load(['ticket', 'schedule.route', 'originStop', 'destinationStop']);
        });
    }

    protected function normalizeSeats(array|string $seatNumbers): array
    {
        $seats = is_array($seatNumbers)
            ? Arr::flatten($seatNumbers)
            : explode(',', $seatNumbers);

        return collect($seats)
            ->map(fn ($seat) => strtoupper(trim((string) $seat)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function confirm(Booking $booking): Booking
    {
        $booking->update(['status' => 'confirmed']);

        return $booking->fresh();
    }
}
