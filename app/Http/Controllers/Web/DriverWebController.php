<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripReport;
use App\Services\BusTrackingService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DriverWebController extends Controller
{
    public function dashboard(): View
    {
        $trips = Schedule::with(['route', 'bus'])
            ->where('driver_id', auth()->id())
            ->whereDate('travel_date', '>=', today())
            ->orderBy('travel_date')
            ->orderBy('departure_time')
            ->get();

        return view('driver.dashboard', compact('trips'));
    }

    public function trip(Schedule $schedule): View
    {
        if ($schedule->driver_id !== auth()->id()) {
            abort(403);
        }

        $passengers = Booking::with(['user', 'ticket'])
            ->where('schedule_id', $schedule->id)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->get();

        return view('driver.trip', compact('schedule', 'passengers'));
    }

    public function start(Schedule $schedule): RedirectResponse
    {
        $schedule->update(['status' => 'in_progress', 'started_at' => now()]);

        return back()->with('success', 'Trip started.');
    }

    public function end(Schedule $schedule): RedirectResponse
    {
        $schedule->update(['status' => 'completed', 'ended_at' => now()]);

        return back()->with('success', 'Trip completed.');
    }

    public function scan(Request $request, TicketService $ticketService): RedirectResponse
    {
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
        $data = $request->validate([
            'type' => ['required'],
            'message' => ['required', 'string'],
            'delay_minutes' => ['nullable', 'integer'],
        ]);

        TripReport::create([
            'schedule_id' => $schedule->id,
            'driver_id' => auth()->id(),
            ...$data,
        ]);

        if ($data['type'] === 'delay') {
            $schedule->update(['status' => 'delayed', 'delay_reason' => $data['message']]);
        }

        return back()->with('success', 'Report submitted.');
    }

    public function updateLocation(Request $request, Schedule $schedule, BusTrackingService $tracking): RedirectResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $tracking->updateLocation($schedule, $data['latitude'], $data['longitude']);

        return back()->with('success', 'Location updated.');
    }
}
