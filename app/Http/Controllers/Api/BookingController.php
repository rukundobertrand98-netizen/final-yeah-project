<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Services\BookingService;
use App\Services\MtnMomoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected MtnMomoService $momoService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'exists:schedules,id'],
            'origin_stop_id' => ['required', 'exists:stops,id'],
            'destination_stop_id' => ['required', 'exists:stops,id'],
            'seat_number' => ['required', 'string', 'max:10'],
        ]);

        try {
            $schedule = Schedule::with('bus')->findOrFail($data['schedule_id']);
            $booking = $this->bookingService->create(
                $request->user(),
                $schedule,
                $data['origin_stop_id'],
                $data['destination_stop_id'],
                $data['seat_number']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($booking, 201);
    }

    public function pay(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $payment = $this->momoService->requestToPay($booking, $data['phone']);

        if ($payment->status === 'successful') {
            $this->bookingService->confirm($booking);
        }

        return response()->json([
            'payment' => $payment,
            'booking' => $booking->fresh(['ticket', 'payment']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['ticket', 'payment', 'schedule.route', 'originStop', 'destinationStop'])
            ->latest()
            ->paginate(15);

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id && ! $request->user()->isRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Operator, \App\Enums\UserRole::Driver)) {
            abort(403);
        }

        return response()->json($booking->load(['ticket', 'payment', 'schedule.bus', 'schedule.route', 'originStop', 'destinationStop']));
    }
}
