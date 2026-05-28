<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $stops = Stop::where('is_active', true)->orderBy('name')->get();
        $featuredRoutes = Route::with(['originStop', 'destinationStop'])
            ->where('is_active', true)
            ->limit(6)
            ->get();

        return view('home', compact('stops', 'featuredRoutes'));
    }
}
