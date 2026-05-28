<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function create(): View
    {
        return view('passenger.complaint');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
        ]);

        Complaint::create([
            'user_id' => auth()->id(),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'booking_id' => $data['booking_id'] ?? null,
            'status' => 'open',
        ]);

        return redirect()->route('passenger.dashboard')->with('success', 'Complaint submitted. KBS support will respond soon.');
    }
}
