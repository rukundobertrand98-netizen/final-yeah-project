<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripReport;
use App\Services\BusTrackingService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DriverController extends Controller
{
    public function trips(Request $request): JsonResponse
    {
        $trips = Schedule::with(['route.originStop', 'route.destinationStop', 'bus'])
            ->where('driver_id', $request->user()->id)
            ->whereDate('travel_date', '>=', now()->toDateString())
            ->orderBy('travel_date')
            ->orderBy('departure_time')
            ->get();

        return response()->json($trips);
    }

    public function startTrip(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);
        $schedule->update(['status' => 'in_progress', 'started_at' => now()]);

        return response()->json($schedule);
    }

    public function endTrip(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);
        $schedule->update(['status' => 'completed', 'ended_at' => now()]);

        return response()->json($schedule);
    }

    public function updateStatus(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);

        $data = $request->validate([
            'status' => ['required', 'in:scheduled,boarding,in_progress,delayed,completed,cancelled'],
            'delay_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule->update($data);

        return response()->json($schedule);
    }

    public function report(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);

        $data = $request->validate([
            'type' => ['required', 'in:delay,breakdown,traffic,other'],
            'message' => ['required', 'string', 'max:1000'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($data['type'] === 'delay') {
            $schedule->update(['status' => 'delayed', 'delay_reason' => $data['message']]);
        }

        $report = TripReport::create([
            'schedule_id' => $schedule->id,
            'driver_id' => $request->user()->id,
            ...$data,
        ]);

        return response()->json($report, 201);
    }

    public function verifyTicket(Request $request, TicketService $ticketService): JsonResponse
    {
        $data = $request->validate(['qr_token' => ['required', 'string']]);

        try {
            $ticket = $ticketService->verify($data['qr_token'], $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Ticket verified.', 'ticket' => $ticket->load('booking.user')]);
    }

    public function passengers(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);

        $passengers = Booking::with(['user', 'ticket', 'originStop', 'destinationStop'])
            ->where('schedule_id', $schedule->id)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->get();

        return response()->json([
            'schedule' => $schedule,
            'passengers' => $passengers,
            'occupied_seats' => $schedule->occupiedSeats(),
        ]);
    }

    public function updateLocation(Request $request, Schedule $schedule, BusTrackingService $tracking): JsonResponse
    {
        $this->authorizeTrip($request, $schedule);

        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'speed_kmh' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'integer', 'min:0', 'max:360'],
        ]);

        $location = $tracking->updateLocation(
            $schedule,
            $data['latitude'],
            $data['longitude'],
            $data['speed_kmh'] ?? null,
            $data['heading'] ?? null
        );

        return response()->json($location->load('nearestStop'));
    }

    public function markAttendance(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->schedule->driver_id !== $request->user()->id) {
            abort(403);
        }

        $booking->update(['status' => 'boarded', 'boarded_at' => now()]);

        return response()->json($booking);
    }

    protected function authorizeTrip(Request $request, Schedule $schedule): void
    {
        if ($schedule->driver_id !== $request->user()->id) {
            abort(403, 'Not your assigned trip.');
        }
    }
}
