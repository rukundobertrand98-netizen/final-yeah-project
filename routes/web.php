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

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/tickets/{ticket}/qr', [TicketQrWebController::class, 'show'])->name('tickets.qr');

Route::middleware(['auth', 'role:passenger'])->prefix('passenger')->name('passenger.')->group(function () {
    Route::get('/dashboard', [PassengerController::class, 'dashboard'])->name('dashboard');
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
    Route::get('/bookings', [PassengerController::class, 'bookings'])->name('bookings');
    Route::get('/complaints/new', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
});

Route::middleware(['auth', 'role:operator'])->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', [OperatorWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/buses', [OperatorWebController::class, 'buses'])->name('buses');
    Route::post('/buses', [OperatorWebController::class, 'storeBus'])->name('buses.store');
    Route::get('/routes', [OperatorWebController::class, 'routes'])->name('routes');
    Route::post('/routes', [OperatorWebController::class, 'storeRoute'])->name('routes.store');
    Route::get('/schedules', [OperatorWebController::class, 'schedules'])->name('schedules');
    Route::post('/schedules', [OperatorWebController::class, 'storeSchedule'])->name('schedules.store');
    Route::get('/bookings', [OperatorWebController::class, 'bookings'])->name('bookings');
    Route::get('/payments', [OperatorWebController::class, 'payments'])->name('payments');
    Route::get('/passengers', [OperatorWebController::class, 'passengers'])->name('passengers');
    Route::get('/reports', [OperatorWebController::class, 'reports'])->name('reports');
});

Route::middleware(['auth', 'role:driver'])->prefix('driver')->name('driver.')->group(function () {
    Route::get('/dashboard', [DriverWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/trip/{schedule}', [DriverWebController::class, 'trip'])->name('trip');
    Route::post('/trip/{schedule}/start', [DriverWebController::class, 'start'])->name('trip.start');
    Route::post('/trip/{schedule}/end', [DriverWebController::class, 'end'])->name('trip.end');
    Route::post('/trip/{schedule}/scan', [DriverWebController::class, 'scan'])->name('trip.scan');
    Route::post('/trip/{schedule}/report', [DriverWebController::class, 'report'])->name('trip.report');
    Route::post('/trip/{schedule}/location', [DriverWebController::class, 'updateLocation'])->name('trip.location');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminWebController::class, 'users'])->name('users');
    Route::post('/operators/{user}/approve', [AdminWebController::class, 'approveOperator'])->name('operators.approve');
    Route::get('/monitor', [AdminWebController::class, 'monitor'])->name('monitor');
    Route::get('/payments', [AdminWebController::class, 'payments'])->name('payments');
    Route::get('/complaints', [AdminWebController::class, 'complaints'])->name('complaints');
    Route::post('/complaints/{complaint}', [AdminWebController::class, 'resolveComplaint'])->name('complaints.resolve');
});
