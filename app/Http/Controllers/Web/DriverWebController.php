<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\BusStatusReport;
use App\Models\Schedule;
use App\Models\TripReport;
use App\Services\BusTrackingService;
use App\Services\TicketService;
use App\Services\TripOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class DriverWebController extends Controller
{
    public function dashboard(): View
    {
        $upcomingTrips = Schedule::with(['route', 'bus', 'latestLocation.nearestStop'])
            ->where('driver_id', Auth::id())
            ->whereDate('travel_date', '>=', today())
            ->orderBy('travel_date')
            ->orderBy('departure_time')
            ->get();

        $completedTrips = Schedule::where('driver_id', Auth::id())
            ->where('status', 'completed')
            ->latest('ended_at')
            ->limit(5)
            ->get();

        $stats = [
            'total_trips'     => Schedule::where('driver_id', Auth::id())->count(),
            'completed_trips' => Schedule::where('driver_id', Auth::id())->where('status', 'completed')->count(),
            'upcoming_trips'  => $upcomingTrips->count(),
            'active_trip'     => Schedule::with(['route', 'bus'])
                                    ->where('driver_id', Auth::id())
                                    ->whereDate('travel_date', today())
                                    ->whereIn('status', ['scheduled', 'boarding', 'in_progress', 'delayed', 'arrived'])
                                    ->orderBy('departure_time')
                                    ->first(),
        ];

        $driver      = Auth::user();
        $assignedBus = Bus::where('driver_id', Auth::id())->with('latestStatusReport')->first();

        // Recent bus status reports submitted by this driver
        $myBusReports = BusStatusReport::where('driver_id', Auth::id())
            ->with('bus')
            ->latest()
            ->limit(5)
            ->get();

        return view('driver.dashboard', compact(
            'upcomingTrips', 'completedTrips', 'stats',
            'driver', 'assignedBus', 'myBusReports'
        ));
    }

    public function trip(Schedule $schedule): View
    {
        $this->authorizeSchedule($schedule);

        $schedule->load(['route.stops', 'bus', 'latestLocation.nearestStop']);

        $passengers = Booking::with(['user', 'ticket'])
            ->where('schedule_id', $schedule->id)
            ->where('leg_number', $schedule->leg_number)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->get();

        return view('driver.trip', compact('schedule', 'passengers'));
    }

    public function start(Schedule $schedule, TripOperationService $trips): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        try {
            $trips->startTrip($schedule);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['trip' => $e->getMessage()]);
        }

        return back()->with('success', 'Trip started. GPS tracking is active.');
    }

    public function arrived(Schedule $schedule, TripOperationService $trips): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        try {
            $trips->markArrived($schedule);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['trip' => $e->getMessage()]);
        }

        return back()->with('success', 'Arrived at destination. Click Return Trip to reverse direction.');
    }

    public function returnTrip(Schedule $schedule, TripOperationService $trips): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        try {
            $schedule = $trips->startReturnTrip($schedule);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['trip' => $e->getMessage()]);
        }

        return back()->with('success', "Return trip started: {$schedule->displayRouteName()}. Same stops, reversed order.");
    }

    public function end(Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        if (! in_array($schedule->status, ['boarding', 'in_progress', 'delayed', 'arrived'], true)) {
            return back()->withErrors(['trip' => 'This trip cannot be completed from its current status.']);
        }

        $schedule->update(['status' => 'completed', 'ended_at' => now()]);

        return back()->with('success', 'Trip completed for today.');
    }

    public function scan(Request $request, TicketService $ticketService, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $data = $request->validate(['qr_token' => ['required', 'string']]);

        try {
            $ticketService->verify($data['qr_token'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['qr_token' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket verified successfully.');
    }

    public function report(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $data = $request->validate([
            'type' => ['required'],
            'message' => ['required', 'string'],
            'delay_minutes' => ['nullable', 'integer'],
        ]);

        TripReport::create([
            'schedule_id' => $schedule->id,
            'driver_id' => Auth::id(),
            ...$data,
        ]);

        if ($data['type'] === 'delay') {
            $schedule->update(['status' => 'delayed', 'delay_reason' => $data['message']]);
        }

        if (in_array($data['type'], ['breakdown', 'maintenance'], true)) {
            $schedule->bus->update(['status' => 'maintenance']);
        }

        if ($data['type'] === 'control_technique') {
            $schedule->bus->update(['status' => 'control_technique']);
        }

        return back()->with('success', 'Report submitted.');
    }

    public function updateLocation(Request $request, Schedule $schedule, BusTrackingService $tracking): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeSchedule($schedule);

        if (! in_array($schedule->status, ['boarding', 'in_progress', 'delayed'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Location tracking is not active for this trip status.'], 422);
            }

            return back()->withErrors(['location' => 'Location tracking is not active for this trip status.']);
        }

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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'location' => $location->load('nearestStop'),
                'trip' => [
                    'display_route' => $schedule->fresh()->displayRouteName(),
                    'leg_direction' => $schedule->leg_direction,
                    'leg_number' => $schedule->leg_number,
                ],
            ]);
        }

        return back()->with('success', 'Location updated.');
    }

    public function submitBusStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'issue_type' => ['required', 'in:breakdown,maintenance,flat_tire,engine_problem,brake_issue,fuel_issue,electrical_issue,control_technique,other'],
            'description' => ['required', 'string', 'max:500'],
            'estimated_fix_at' => ['nullable', 'date', 'after:now'],
        ]);

        $assignedBus = Bus::where('driver_id', Auth::id())->first();

        if (!$assignedBus) {
            return back()->withErrors(['bus' => 'You do not have an assigned bus to report issues for.']);
        }

        BusStatusReport::create([
            'bus_id' => $assignedBus->id,
            'driver_id' => Auth::id(),
            'issue_type' => $data['issue_type'],
            'description' => $data['description'],
            'estimated_fix_at' => $data['estimated_fix_at'] ?? null,
            'status' => 'open',
        ]);

        // Mark bus as under maintenance
        $assignedBus->update(['status' => 'maintenance']);

        return back()->with('success', 'Bus issue reported successfully. Admin has been notified.');
    }

    protected function authorizeSchedule(Schedule $schedule): void
    {
        if ($schedule->driver_id !== Auth::id()) {
            abort(403);
        }
    }
}
