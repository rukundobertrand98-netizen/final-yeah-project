<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketService
{
    public function issueForBooking(Booking $booking): Ticket
    {
        return Ticket::create([
            'booking_id' => $booking->id,
            'ticket_number' => 'TKT-'.strtoupper(Str::random(10)),
            'qr_token' => hash('sha256', $booking->reference.Str::uuid()),
            'status' => 'valid',
        ]);
    }

    public function verify(string $qrToken, User $driver): Ticket
    {
        $payload = json_decode($qrToken, true);
        if (is_array($payload) && isset($payload['token'])) {
            $qrToken = $payload['token'];
        }

        $ticket = Ticket::with(['booking.schedule', 'booking.user'])
            ->where('qr_token', $qrToken)
            ->first();

        if (! $ticket) {
            throw new InvalidArgumentException('Invalid QR code.');
        }

        if ($ticket->status !== 'valid') {
            throw new InvalidArgumentException('Ticket is no longer valid.');
        }

        if ($ticket->booking->schedule->driver_id !== $driver->id) {
            throw new InvalidArgumentException('Ticket is not for your assigned trip.');
        }

        $ticket->update([
            'status' => 'used',
            'verified_at' => now(),
            'verified_by' => $driver->id,
        ]);

        $ticket->booking->update([
            'status' => 'boarded',
            'boarded_at' => now(),
        ]);

        return $ticket->fresh();
    }

    public function qrPayload(Ticket $ticket): string
    {
        $ticket->loadMissing(['booking.user', 'booking.schedule.route']);

        return json_encode([
            'ticket' => $ticket->ticket_number,
            'token' => $ticket->qr_token,
            'ref' => $ticket->booking->reference,
            'route' => $ticket->booking->schedule->route->name,
            'passenger' => $ticket->booking->user->name,
            'seat' => $ticket->booking->seat_number,
        ]);
    }
}
