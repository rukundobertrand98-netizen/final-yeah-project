<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PassengerLocation;
use App\Models\BusLocation;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class LocationTrackingController extends Controller
{
    public function updatePassengerLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'device_info' => ['nullable', 'string', 'max:500'],
        ]);

        // Deactivate previous locations
        PassengerLocation::where('user_id', Auth::id())
            ->update(['is_active' => false]);

        // Get address from coordinates using reverse geocoding
        $address = $this->reverseGeocode($data['latitude'], $data['longitude']);

        // Create new location record
        $location = PassengerLocation::create([
            'user_id' => Auth::id(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'address' => $address,
            'is_active' => true,
            'location_time' => now(),
            'device_info' => $data['device_info'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'location' => $location,
            'address' => $address,
        ]);
    }

    public function getPassengerLocation(Request $request): JsonResponse
    {
        $userId = $request->input('user_id', Auth::id());
        
        // For admin/operator, allow viewing other passengers
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        if ($userId !== Auth::id() && ! ($authUser && $authUser->isRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Operator))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $location = PassengerLocation::where('user_id', $userId)
            ->where('is_active', true)
            ->latest('location_time')
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'No active location found',
            ]);
        }

        return response()->json([
            'success' => true,
            'location' => $location,
        ]);
    }

    public function trackBusForBooking(Booking $booking): JsonResponse
    {
        // Verify booking belongs to authenticated user or user is admin/operator
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        if ($booking->user_id !== Auth::id() && ! ($authUser && $authUser->isRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Operator))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get bus location
        $busLocation = BusLocation::where('bus_id', $booking->schedule->bus_id)
            ->latest('recorded_at')
            ->first();

        if (!$busLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Bus location not available',
            ]);
        }

        // Calculate distance to passenger's origin stop
        $originStop = $booking->originStop;
        $distanceToOrigin = $this->calculateDistance(
            $busLocation->latitude,
            $busLocation->longitude,
            $originStop->latitude,
            $originStop->longitude
        );

        // Estimate arrival time (assuming 30 km/h average speed in city)
        $estimatedArrivalMinutes = max(1, round($distanceToOrigin / 0.5)); // 0.5 km per minute

        return response()->json([
            'success' => true,
            'bus_location' => [
                'latitude' => $busLocation->latitude,
                'longitude' => $busLocation->longitude,
                'last_updated' => $busLocation->recorded_at,
                'speed' => $busLocation->speed_kmh ?? 0,
            ],
            'origin_stop' => [
                'name' => $originStop->name,
                'latitude' => $originStop->latitude,
                'longitude' => $originStop->longitude,
            ],
            'distance_to_origin_km' => round($distanceToOrigin, 2),
            'estimated_arrival_minutes' => $estimatedArrivalMinutes,
            'bus_info' => [
                'plate_number' => $booking->schedule->bus->plate_number,
                'driver_name' => $booking->schedule->driver?->name,
            ],
        ]);
    }

    public function getAllPassengerLocations(): JsonResponse
    {
        // Admin/Operator only
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        if (! ($authUser && $authUser->isRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Operator))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $locations = PassengerLocation::with('user')
            ->where('is_active', true)
            ->where('location_time', '>=', now()->subHours(1)) // Last hour only
            ->get()
            ->map(function ($location) {
                return [
                    'user_id' => $location->user_id,
                    'user_name' => $location->user->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'address' => $location->address,
                    'last_updated' => $location->location_time,
                ];
            });

        return response()->json([
            'success' => true,
            'locations' => $locations,
        ]);
    }

    private function reverseGeocode(float $latitude, float $longitude): ?string
    {
        $googleApiKey = config('services.google_maps.api_key');
        
        try {
            if ($googleApiKey) {
                // Use Google Geocoding API
                $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$googleApiKey}";
                $response = file_get_contents($url);
                $data = json_decode($response, true);
                
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    return $data['results'][0]['formatted_address'];
                }
            }
            
            // Fallback to Nominatim
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&addressdetails=1";
            $context = stream_context_create([
                'http' => [
                    'header' => 'User-Agent: KBS-Bus-System/1.0',
                    'timeout' => 5,
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            $data = json_decode($response, true);
            
            return $data['display_name'] ?? null;
            
        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed: ' . $e->getMessage());
            return null;
        }
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
