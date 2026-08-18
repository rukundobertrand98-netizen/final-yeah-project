<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\PassengerAlert;
use App\Models\Schedule;
use App\Models\Stop;
use App\Services\BookingService;
use App\Services\BusTrackingService;
use App\Services\MtnMomoService;
use App\Services\NearbyBusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use App\Models\PassengerLocation;

class PassengerController extends Controller
{
    public function dashboard(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $bookings = $user->bookings()
            ->with(['ticket', 'schedule.route', 'originStop', 'destinationStop'])
            ->latest()
            ->limit(5)
            ->get();

        $alerts = PassengerAlert::where('user_id', Auth::id())
            ->where('is_read', false)
            ->latest()
            ->limit(5)
            ->get();

        return view('passenger.dashboard', compact('bookings', 'alerts'));
    }

    public function nearbyBusesData(Request $request, NearbyBusService $nearby): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        return response()->json($nearby->busesNearPassenger(
            (float) $data['latitude'],
            (float) $data['longitude']
        ));
    }

    public function alerts(): \Illuminate\Http\JsonResponse
    {
        $alerts = PassengerAlert::where('user_id', Auth::id())
            ->where('is_read', false)
            ->latest()
            ->limit(10)
            ->get();

        PassengerAlert::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json($alerts);
    }

    public function search(Request $request): View
    {
        $stops = Stop::where('is_active', true)->orderBy('name')->get();
        $schedules = collect();
        $selectedOrigin = null;
        $selectedDestination = null;
        $routePreviews = collect();

        // Fetch all routes to allow passengers to discover the network
        $allRoutes = \App\Models\Route::with(['stops' => fn($q) => $q->orderBy('route_stops.sequence')])->get();
        $routePreviews = $allRoutes->map(fn($r) => [
            'route_name' => $r->name,
            'coords' => $r->stops->map(fn($s) => [(float)$s->latitude, (float)$s->longitude])->values(),
            'stop_names' => $r->stops->pluck('name'),
            'map_path' => $r->map_path,
        ]);

        if ($request->filled(['origin_stop_id', 'destination_stop_id'])) {
            $request->validate([
                'origin_stop_id' => ['required', 'exists:stops,id'],
                'destination_stop_id' => ['required', 'exists:stops,id', 'different:origin_stop_id'],
                'travel_date' => ['nullable', 'date'],
                'seats' => ['nullable', 'integer', 'min:1'],
            ]);

            $date = $request->input('travel_date', now()->toDateString());
            $requestedSeats = max(1, (int) $request->input('seats', 1));
            $selectedOrigin = (int) $request->origin_stop_id;
            $selectedDestination = (int) $request->destination_stop_id;

            $schedules = Schedule::with([
                'route.originStop',
                'route.destinationStop',
                'route.stops' => fn ($q) => $q->orderBy('route_stops.sequence'),
                'bus',
            ])
                ->whereDate('travel_date', $date)
                ->whereIn('status', ['scheduled', 'boarding', 'in_progress', 'delayed'])
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'boarding' THEN 1 WHEN 'delayed' THEN 2 ELSE 3 END")
                ->orderBy('departure_time')
                ->get()
                ->filter(function (Schedule $schedule) use ($selectedOrigin, $selectedDestination, $requestedSeats) {
                    $containsStops = $schedule->stopsConnectInDirection($selectedOrigin, $selectedDestination);

                    return $containsStops && $schedule->availableSeatCount() >= $requestedSeats;
                })
                ->values();
        }

        return view('passenger.search', compact('stops', 'schedules', 'selectedOrigin', 'selectedDestination', 'routePreviews'));
    }

    public function book(Schedule $schedule, Request $request): View
    {
        $schedule->load('route.stops', 'bus');
        $pendingCutoff = now()->subMinutes((int) config('kbs.booking.pending_hold_minutes', 15));
        $reserved = $schedule->bookings()
            ->where('leg_number', $schedule->leg_number)
            ->where('status', 'pending')
            ->where('created_at', '>=', $pendingCutoff)
            ->pluck('seat_number')
            ->flatMap(fn (string $seats) => array_map('trim', explode(',', $seats)))
            ->filter()
            ->values()
            ->all();
        $booked = $schedule->bookings()
            ->where('leg_number', $schedule->leg_number)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->pluck('seat_number')
            ->flatMap(fn (string $seats) => array_map('trim', explode(',', $seats)))
            ->filter()
            ->values()
            ->all();
        $occupied = array_values(array_unique([...$reserved, ...$booked]));
        $seats = array_diff($schedule->bus->seatLabels(), $occupied);
        $stops = $schedule->orderedStopsForLeg();
        $selectedOrigin = $request->integer('origin_stop_id') ?: null;
        $selectedDestination = $request->integer('destination_stop_id') ?: null;
        $requestedSeats = max(1, $request->integer('seats') ?: 1);

        return view('passenger.book', compact(
            'schedule', 'seats', 'stops', 'occupied', 'reserved', 'booked', 'selectedOrigin', 'selectedDestination', 'requestedSeats'
        ));
    }

    public function storeBooking(Request $request, Schedule $schedule, BookingService $bookingService): RedirectResponse
    {
        $data = $request->validate([
            'origin_stop_id' => ['required', 'exists:stops,id'],
            'destination_stop_id' => ['required', 'exists:stops,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'string'],
        ]);

        try {
            $booking = $bookingService->create(
                $request->user(),
                $schedule,
                $data['origin_stop_id'],
                $data['destination_stop_id'],
                $data['seat_numbers']
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['seat_numbers' => $e->getMessage()])->withInput();
        }

        return redirect()->route('passenger.checkout', $booking);
    }

    public function seatData(Schedule $schedule): JsonResponse
    {
        $schedule->load('bus');

        return response()->json([
            'occupied' => $schedule->occupiedSeats(),
            'capacity' => $schedule->bus->capacity,
            'available_count' => $schedule->availableSeatCount(),
        ]);
    }

    public function checkout(Booking $booking): View
    {
        $this->authorizeBooking($booking);
        $booking->load(['user', 'schedule.bus', 'schedule.route', 'originStop', 'destinationStop', 'payment']);

        return view('passenger.checkout', compact('booking'));
    }

    public function pay(Request $request, Booking $booking, MtnMomoService $momo, BookingService $bookingService): RedirectResponse
    {
        $this->authorizeBooking($booking);

        $data = $request->validate(['phone' => ['required', 'string']]);
        $payment = $momo->requestToPay($booking, $data['phone']);

        if ($payment->status === 'successful') {
            $bookingService->confirm($booking);

            return redirect()->route('passenger.ticket', $booking)->with('success', 'Payment successful via MTN MoMo.');
        }

        if ($payment->status === 'processing') {
            return redirect()->route('passenger.checkout', $booking)
                ->with('success', 'MTN MoMo payment request sent. Approve it on your phone, then check status.');
        }

        return back()->withErrors(['phone' => 'Payment could not be completed. Please try again.']);
    }

    public function paymentStatus(Booking $booking, MtnMomoService $momo, BookingService $bookingService): RedirectResponse
    {
        $this->authorizeBooking($booking);
        $payment = $booking->payment;

        if (! $payment) {
            return back()->withErrors(['phone' => 'No payment request has been started for this booking.']);
        }

        $payment = $momo->checkStatus($payment);

        if ($payment->status === 'successful') {
            $bookingService->confirm($booking);

            return redirect()->route('passenger.ticket', $booking)->with('success', 'Payment confirmed. Your ticket is ready.');
        }

        return back()->with('success', 'Payment status: '.$payment->status.'. Approve the request on your phone if it is still pending.');
    }

    public function ticket(Booking $booking): View
    {
        $this->authorizeBooking($booking);
        $booking->load(['user', 'ticket', 'schedule.bus', 'schedule.route', 'originStop', 'destinationStop', 'payment']);

        return view('passenger.ticket', compact('booking'));
    }

    public function track(Booking $booking, BusTrackingService $tracking): View
    {
        $this->authorizeBooking($booking);
        $booking->load(['schedule.latestLocation.nearestStop', 'schedule.route.stops', 'originStop', 'destinationStop']);
        $trackingData = $tracking->buildBookingTracking($booking);

        return view('passenger.track', compact('booking', 'trackingData'));
    }

    public function trackData(Booking $booking, BusTrackingService $tracking): JsonResponse
    {
        $this->authorizeBooking($booking);
        $booking->load(['user']);

        return response()->json($tracking->buildBookingTracking($booking));
    }

    public function arrived(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if (! in_array($booking->status, ['confirmed', 'boarded'], true)) {
            return back()->withErrors(['booking' => 'This booking cannot be marked as arrived from its current status.']);
        }

        $booking->update(['status' => 'completed']);

        return back()->with('success', 'Thank you — your arrival has been recorded and the seat is now freed.');
    }

    public function bookings(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $bookings = $user->bookings()
            ->with([
                'ticket',
                'payment',
                'originStop',
                'destinationStop',
                'schedule.route',
                'schedule.latestLocation.nearestStop',
            ])
            ->latest()
            ->paginate(12);

        $trackableBooking = $bookings->first(fn ($b) => in_array($b->status, ['confirmed', 'boarded'], true)
            && $b->schedule->travel_date->greaterThanOrEqualTo(today()));

        return view('passenger.bookings', compact('bookings', 'trackableBooking'));
    }

    public function myLocation(): View
    {
        return view('passenger.my-location');
    }

    public function trackMyBuses(): View
    {
        /** @var User $user */
        $user = Auth::user();
        
        // Get active bookings for tracking buses
        $activeBookings = $user->bookings()
            ->with([
                'schedule.route',
                'schedule.bus',
                'schedule.latestLocation',
                'originStop',
                'destinationStop'
            ])
            ->whereIn('status', ['confirmed', 'boarded'])
            ->whereHas('schedule', function($q) {
                $q->whereDate('travel_date', '>=', today())
                  ->whereIn('status', ['scheduled', 'boarding', 'in_progress']);
            })
            ->get();

        return view('passenger.track-buses', compact('activeBookings'));
    }

    protected function authorizeBooking(Booking $booking): void
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
