<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Route as BusRoute;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->latest()
            ->paginate(30);

        return response()->json($users);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'in:admin,operator,driver,passenger'],
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function approveOperator(User $user): JsonResponse
    {
        if ($user->role !== UserRole::Operator) {
            return response()->json(['message' => 'User is not an operator.'], 422);
        }

        $user->update(['operator_approved_at' => now()]);

        return response()->json($user);
    }

    public function analytics(): JsonResponse
    {
        return response()->json([
            'users' => User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'bookings_today' => Booking::whereDate('created_at', today())->count(),
            'revenue_total' => Payment::where('status', 'successful')->sum('amount'),
            'active_trips' => Schedule::where('status', 'in_progress')->count(),
            'open_complaints' => Complaint::where('status', 'open')->count(),
        ]);
    }

    public function monitorBuses(Request $request): JsonResponse
    {
        $activeStatuses = ['scheduled', 'boarding', 'in_progress', 'delayed'];
        $selectedRouteId = $request->integer('route_id');

        $trips = Schedule::with(['bus', 'route', 'latestLocation.nearestStop', 'driver'])
            ->whereIn('status', $activeStatuses)
            ->when($selectedRouteId, fn ($query) => $query->where('route_id', $selectedRouteId))
            ->latest()
            ->get()
            ->map(fn (Schedule $trip) => [
                'id' => $trip->id,
                'plate_number' => $trip->bus?->plate_number,
                'route_id' => $trip->route_id,
                'route' => $trip->route?->name,
                'driver' => $trip->driver?->name,
                'status' => $trip->status,
                'latitude' => $trip->latestLocation?->latitude,
                'longitude' => $trip->latestLocation?->longitude,
                'speed_kmh' => $trip->latestLocation?->speed_kmh,
                'heading' => $trip->latestLocation?->heading,
                'nearest_stop' => $trip->latestLocation?->nearestStop?->name,
                'recorded_at' => $trip->latestLocation?->recorded_at?->toIso8601String(),
                'gps_age_seconds' => $trip->latestLocation?->recorded_at?->diffInSeconds(now()),
            ]);

        $route = $selectedRouteId
            ? BusRoute::with(['originStop', 'destinationStop', 'stops'])->find($selectedRouteId)
            : null;

        return response()->json([
            'trips' => $trips,
            'route' => $route ? [
                'id' => $route->id,
                'name' => $route->name,
                'origin' => $route->originStop?->name,
                'destination' => $route->destinationStop?->name,
                'stops' => $route->stops->map(fn (Stop $stop) => [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'latitude' => (float) $stop->latitude,
                    'longitude' => (float) $stop->longitude,
                ])->values(),
            ] : null,
        ]);
    }

    public function payments(): JsonResponse
    {
        $payments = Payment::with('booking.user')->latest()->paginate(30);

        return response()->json($payments);
    }

    public function complaints(): JsonResponse
    {
        $complaints = Complaint::with(['user', 'booking'])->latest()->paginate(20);

        return response()->json($complaints);
    }

    public function resolveComplaint(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate([
            'admin_response' => ['required', 'string'],
            'status' => ['required', 'in:resolved,closed'],
        ]);

        $complaint->update([
            ...$data,
            'handled_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json($complaint);
    }
}
