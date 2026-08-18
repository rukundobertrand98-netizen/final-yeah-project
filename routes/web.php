<?php

use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\ComplaintController;
use App\Http\Controllers\Web\TicketQrWebController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DriverWebController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\OperatorWebController;
use App\Http\Controllers\Web\PassengerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Auth (Guest)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Ticket QR
|--------------------------------------------------------------------------
*/
Route::get('/tickets/{ticket}/qr', [TicketQrWebController::class, 'show'])
    ->name('tickets.qr');

/*
|--------------------------------------------------------------------------
| Passenger
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:passenger'])
    ->prefix('passenger')
    ->name('passenger.')
    ->group(function () {

    Route::get('/dashboard', [PassengerController::class, 'dashboard'])->name('dashboard');
    Route::get('/nearby-buses', [PassengerController::class, 'nearbyBusesData'])->name('nearby-buses');
    Route::get('/search', [PassengerController::class, 'search'])->name('search');

    Route::get('/schedules/{schedule}/seats', [PassengerController::class, 'seatData'])->name('schedules.seats');

    Route::get('/book/{schedule}', [PassengerController::class, 'book'])->name('book');
    Route::post('/book/{schedule}', [PassengerController::class, 'storeBooking'])->name('book.store');

    Route::get('/checkout/{booking}', [PassengerController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/{booking}/pay', [PassengerController::class, 'pay'])->name('pay');
    Route::post('/checkout/{booking}/status', [PassengerController::class, 'paymentStatus'])->name('pay.status');

    Route::get('/ticket/{booking}', [PassengerController::class, 'ticket'])->name('ticket');

    Route::get('/track/{booking}', [PassengerController::class, 'track'])->name('track');
    Route::get('/track/{booking}/data', [PassengerController::class, 'trackData'])->name('track.data');
    Route::post('/track/{booking}/arrived', [PassengerController::class, 'arrived'])->name('track.arrived');

    Route::get('/bookings', [PassengerController::class, 'bookings'])->name('bookings');
    Route::get('/alerts', [PassengerController::class, 'alerts'])->name('alerts');
    Route::get('/my-location', [PassengerController::class, 'myLocation'])->name('my-location');
    Route::get('/track-buses', [PassengerController::class, 'trackMyBuses'])->name('track-buses');

    Route::get('/complaints/new', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
});

/*
|--------------------------------------------------------------------------
| Operator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:operator'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {

    Route::get('/dashboard', [OperatorWebController::class, 'dashboard'])->name('dashboard');

    Route::get('/buses', [OperatorWebController::class, 'buses'])->name('buses');

    Route::get('/routes', [OperatorWebController::class, 'routes'])->name('routes');
    Route::post('/routes', [OperatorWebController::class, 'storeRoute'])->name('routes.store');
    Route::get('/routes/{route}/edit', [OperatorWebController::class, 'editRoute'])->name('routes.edit');
    Route::put('/routes/{route}', [OperatorWebController::class, 'updateRoute'])->name('routes.update');
    Route::delete('/routes/{route}', [OperatorWebController::class, 'deleteRoute'])->name('routes.delete');

    Route::get('/schedules', [OperatorWebController::class, 'schedules'])->name('schedules');
    Route::post('/schedules', [OperatorWebController::class, 'storeSchedule'])->name('schedules.store');
    Route::put('/schedules/{schedule}', [OperatorWebController::class, 'updateSchedule'])->name('schedules.update');
    Route::delete('/schedules/{schedule}', [OperatorWebController::class, 'deleteSchedule'])->name('schedules.delete');

    Route::get('/bookings', [OperatorWebController::class, 'bookings'])->name('bookings');
    Route::get('/routes/{route}/bookings', [OperatorWebController::class, 'showRouteBookings'])->name('routes.bookings');
    Route::put('/bookings/{booking}/status', [OperatorWebController::class, 'updateBookingStatus'])->name('bookings.update-status');
    Route::get('/payments', [OperatorWebController::class, 'payments'])->name('payments');
    Route::get('/passengers', [OperatorWebController::class, 'passengers'])->name('passengers');
    Route::get('/reports', [OperatorWebController::class, 'reports'])->name('reports');
});

/*
|--------------------------------------------------------------------------
| Driver
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:driver'])
    ->prefix('driver')
    ->name('driver.')
    ->group(function () {

    Route::get('/dashboard', [DriverWebController::class, 'dashboard'])->name('dashboard');

    Route::get('/trip/{schedule}', [DriverWebController::class, 'trip'])->name('trip');
    Route::post('/trip/{schedule}/start', [DriverWebController::class, 'start'])->name('trip.start');
    Route::post('/trip/{schedule}/arrived', [DriverWebController::class, 'arrived'])->name('trip.arrived');
    Route::post('/trip/{schedule}/return', [DriverWebController::class, 'returnTrip'])->name('trip.return');
    Route::post('/trip/{schedule}/end', [DriverWebController::class, 'end'])->name('trip.end');
    Route::post('/trip/{schedule}/scan', [DriverWebController::class, 'scan'])->name('trip.scan');
    Route::post('/trip/{schedule}/report', [DriverWebController::class, 'report'])->name('trip.report');
    Route::post('/trip/{schedule}/location', [DriverWebController::class, 'updateLocation'])->name('trip.location');

    // Bus status reports (driver reports issues for their assigned bus)
    Route::post('/bus-status', [DriverWebController::class, 'submitBusStatus'])->name('bus-status.store');
});

/*
|--------------------------------------------------------------------------
| Admin (FULL FIXED)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

    Route::get('/dashboard', [AdminWebController::class, 'dashboard'])->name('dashboard');

    /*
    | USERS
    */
    Route::get('/users', [AdminWebController::class, 'users'])->name('users');
    Route::get('/users/create', [AdminWebController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminWebController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminWebController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{user}', [AdminWebController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminWebController::class, 'deleteUser'])->name('users.delete');

    /*
    | OPERATORS
    */
    Route::post('/operators/{user}/approve', [AdminWebController::class, 'approveOperator'])
        ->name('operators.approve');

    /*
    | BUSES
    */
    Route::get('/buses', [AdminWebController::class, 'buses'])->name('buses');
    Route::get('/buses/create', [AdminWebController::class, 'createBus'])->name('buses.create');
    Route::post('/buses', [AdminWebController::class, 'storeBus'])->name('buses.store');
    Route::get('/buses/{bus}/edit', [AdminWebController::class, 'editBus'])->name('buses.edit');
    Route::put('/buses/{bus}', [AdminWebController::class, 'updateBus'])->name('buses.update');
    Route::delete('/buses/{bus}', [AdminWebController::class, 'deleteBus'])->name('buses.delete');

    /*
    | TRIPS / ROUTES
    */
    Route::get('/routes', [AdminWebController::class, 'routes'])->name('routes');
    Route::get('/trips', [AdminWebController::class, 'trips'])->name('trips');

    /*
    | BUS STATUS
    */
    Route::get('/bus-status', [AdminWebController::class, 'busStatus'])->name('bus-status');
    Route::post('/bus-status/{report}/resolve', [AdminWebController::class, 'resolveBusStatus'])->name('bus-status.resolve');

    /*
    | REPORTS (FIXED: REAL ROUTE EXISTS NOW)
    */
    Route::get('/reports', [AdminWebController::class, 'reports'])->name('reports');
    Route::post('/reports/download', [AdminWebController::class, 'downloadReport'])->name('reports.download');

    /*
    | OTHER
    */
    Route::get('/monitor', [AdminWebController::class, 'monitor'])->name('monitor');
    Route::get('/monitor/data', [AdminWebController::class, 'monitorData'])->name('monitor.data');
    Route::get('/payments', [AdminWebController::class, 'payments'])->name('payments');

    Route::get('/complaints', [AdminWebController::class, 'complaints'])->name('complaints');
    Route::post('/complaints/{complaint}', [AdminWebController::class, 'resolveComplaint'])
        ->name('complaints.resolve');

    /*
    | BOOKING HISTORY & PASSENGER TRACKING
    */
    Route::get('/booking-history', [AdminWebController::class, 'bookingHistory'])->name('booking-history');
    Route::get('/passenger-tracking', [AdminWebController::class, 'passengerTracking'])->name('passenger-tracking');
    Route::get('/passenger-tracking/map', [AdminWebController::class, 'passengerTrackingMap'])->name('passenger-tracking.map');
});
