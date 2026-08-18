<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MtnMomoWebhookController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\RouteSearchController;
use App\Http\Controllers\Api\TicketQrController;
use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocationTrackingController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/stops', [RouteSearchController::class, 'stops']);
    Route::get('/routes/search', [RouteSearchController::class, 'search']);
    Route::post('/mtn-momo/callback', MtnMomoWebhookController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/pay-momo', [BookingController::class, 'pay']);

        Route::get('/tracking/schedules/{schedule}', [TrackingController::class, 'scheduleLocation']);
        Route::get('/tracking/bookings/{booking}', [TrackingController::class, 'myTrip']);
        Route::get('/alerts', [TrackingController::class, 'alerts']);
        Route::get('/passenger/nearby-buses', [TrackingController::class, 'nearbyBuses']);
        Route::patch('/alerts/{alert}/read', [TrackingController::class, 'markAlertRead']);

        Route::get('/tickets/{ticket}/qr', [TicketQrController::class, 'show']);

        // Location Tracking
        Route::post('/location/update', [LocationTrackingController::class, 'updatePassengerLocation']);
        Route::get('/location/my', [LocationTrackingController::class, 'getPassengerLocation']);
        Route::get('/bookings/{booking}/track-bus', [LocationTrackingController::class, 'trackBusForBooking']);

        Route::middleware('role:driver')->prefix('driver')->group(function () {
            Route::get('/trips', [DriverController::class, 'trips']);
            Route::post('/trips/{schedule}/start', [DriverController::class, 'startTrip']);
            Route::post('/trips/{schedule}/arrived', [DriverController::class, 'arrived']);
            Route::post('/trips/{schedule}/return', [DriverController::class, 'returnTrip']);
            Route::post('/trips/{schedule}/end', [DriverController::class, 'endTrip']);
            Route::patch('/trips/{schedule}/status', [DriverController::class, 'updateStatus']);
            Route::post('/trips/{schedule}/report', [DriverController::class, 'report']);
            Route::post('/tickets/verify', [DriverController::class, 'verifyTicket']);
            Route::get('/trips/{schedule}/passengers', [DriverController::class, 'passengers']);
            Route::post('/trips/{schedule}/location', [DriverController::class, 'updateLocation']);
            Route::post('/bookings/{booking}/attendance', [DriverController::class, 'markAttendance']);
        });

        Route::middleware('role:operator')->prefix('operator')->group(function () {
            Route::get('/buses', [OperatorController::class, 'buses']);
            Route::get('/routes', [OperatorController::class, 'routes']);
            Route::post('/routes', [OperatorController::class, 'storeRoute']);
            Route::get('/schedules', [OperatorController::class, 'schedules']);
            Route::post('/schedules', [OperatorController::class, 'storeSchedule']);
            Route::post('/schedules/{schedule}/assign-driver', [OperatorController::class, 'assignDriver']);
            Route::get('/bookings', [OperatorController::class, 'bookings']);
            Route::get('/reports', [OperatorController::class, 'reports']);
            Route::post('/drivers', [OperatorController::class, 'storeDriver']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/users', [AdminController::class, 'users']);
            Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
            Route::post('/operators/{user}/approve', [AdminController::class, 'approveOperator']);
            Route::get('/analytics', [AdminController::class, 'analytics']);
            Route::get('/buses/monitor', [AdminController::class, 'monitorBuses']);
            Route::get('/payments', [AdminController::class, 'payments']);
            Route::get('/complaints', [AdminController::class, 'complaints']);
            Route::patch('/complaints/{complaint}', [AdminController::class, 'resolveComplaint']);
            
            // Admin Location Tracking
            Route::get('/locations/all', [LocationTrackingController::class, 'getAllPassengerLocations']);
        });
    });
});
