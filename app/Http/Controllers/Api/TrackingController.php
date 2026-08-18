<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PassengerAlert;
use App\Models\Schedule;
use App\Services\BusTrackingService;
use App\Services\NearbyBusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function scheduleLocation(Request $request, Schedule $schedule): JsonResponse
    {
        $location = $schedule->latestLocation()->with('nearestStop')->first();

        return response()->json([
            'schedule' => $schedule->load('route'),
            'location' => $location,
            'status' => $schedule->status,
        ]);
    }

    public function myTrip(Request $request, Booking $booking, BusTrackingService $tracking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($tracking->buildBookingTracking($booking));
    }

    public function alerts(Request $request): JsonResponse
    {
        $alerts = PassengerAlert::with(['schedule.route', 'stop'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($alerts);
    }

    public function markAlertRead(Request $request, PassengerAlert $alert): JsonResponse
    {
        if ($alert->user_id !== $request->user()->id) {
            abort(403);
        }

        $alert->update(['is_read' => true]);

        return response()->json($alert);
    }

    public function nearbyBuses(Request $request, NearbyBusService $nearby): JsonResponse
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
}
