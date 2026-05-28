<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Schedule;
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

    public function monitorBuses(): JsonResponse
    {
        $trips = Schedule::with(['bus', 'route', 'latestLocation.nearestStop', 'driver'])
            ->whereIn('status', ['boarding', 'in_progress', 'delayed'])
            ->whereDate('travel_date', today())
            ->get();

        return response()->json($trips);
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
