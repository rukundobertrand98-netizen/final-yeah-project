<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_search_returns_schedules_for_route_stops(): void
    {
        $operator = User::factory()->create([
            'role' => UserRole::Operator->value,
            'is_active' => true,
        ]);
        $passenger = User::factory()->create([
            'role' => UserRole::Passenger->value,
            'is_active' => true,
        ]);

        $origin = Stop::create([
            'name' => 'Origin Stop',
            'code' => 'ORIGIN',
            'district' => 'Kigali',
            'latitude' => -1.9441,
            'longitude' => 30.0619,
            'is_active' => true,
        ]);
        $destination = Stop::create([
            'name' => 'Destination Stop',
            'code' => 'DEST',
            'district' => 'Kigali',
            'latitude' => -1.9500,
            'longitude' => 30.0700,
            'is_active' => true,
        ]);

        $bus = Bus::create([
            'operator_id' => $operator->id,
            'driver_id' => null,
            'plate_number' => 'RAA-001-A',
            'fleet_number' => 'FLEET-1',
            'capacity' => 40,
            'rows' => 10,
            'seats_per_row' => 4,
            'model' => 'Coaster',
            'status' => 'active',
        ]);

        $route = Route::create([
            'operator_id' => $operator->id,
            'name' => 'Test Route',
            'code' => 'TEST-ROUTE',
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'estimated_duration_minutes' => 45,
            'distance_km' => 12.5,
            'base_price' => 2000,
            'description' => 'Test route',
            'is_active' => true,
        ]);

        $route->stops()->attach([
            $origin->id => ['sequence' => 1],
            $destination->id => ['sequence' => 2],
        ]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => null,
            'operator_id' => $operator->id,
            'travel_date' => today(),
            'departure_time' => '08:00:00',
            'arrival_time' => '10:00:00',
            'price' => 2000,
            'status' => 'scheduled',
            'leg_direction' => 'forward',
            'leg_number' => 1,
        ]);

        $response = $this->actingAs($passenger)->get('/passenger/search?origin_stop_id=' . $origin->id . '&destination_stop_id=' . $destination->id . '&travel_date=' . today()->toDateString() . '&seats=1');

        $response->assertOk();
        $response->assertViewHas('schedules', function ($schedules) use ($schedule) {
            return $schedules->contains('id', $schedule->id);
        });
    }

    public function test_passenger_search_matches_schedule_when_requested_stops_share_names_with_route_stops(): void
    {
        $operator = User::factory()->create([
            'role' => UserRole::Operator->value,
            'is_active' => true,
        ]);
        $passenger = User::factory()->create([
            'role' => UserRole::Passenger->value,
            'is_active' => true,
        ]);

        $origin = Stop::create([
            'name' => 'Origin Stop',
            'code' => 'ORIGIN',
            'district' => 'Kigali',
            'latitude' => -1.9441,
            'longitude' => 30.0619,
            'is_active' => true,
        ]);
        $destination = Stop::create([
            'name' => 'Destination Stop',
            'code' => 'DEST',
            'district' => 'Kigali',
            'latitude' => -1.9500,
            'longitude' => 30.0700,
            'is_active' => true,
        ]);

        $requestedOrigin = Stop::create([
            'name' => 'Origin Stop',
            'code' => 'ORIGIN-ALT',
            'district' => 'Kigali',
            'latitude' => -1.9442,
            'longitude' => 30.0620,
            'is_active' => true,
        ]);
        $requestedDestination = Stop::create([
            'name' => 'Destination Stop',
            'code' => 'DEST-ALT',
            'district' => 'Kigali',
            'latitude' => -1.9501,
            'longitude' => 30.0701,
            'is_active' => true,
        ]);

        $bus = Bus::create([
            'operator_id' => $operator->id,
            'driver_id' => null,
            'plate_number' => 'RAA-002-A',
            'fleet_number' => 'FLEET-2',
            'capacity' => 40,
            'rows' => 10,
            'seats_per_row' => 4,
            'model' => 'Coaster',
            'status' => 'active',
        ]);

        $route = Route::create([
            'operator_id' => $operator->id,
            'name' => 'Test Route',
            'code' => 'TEST-ROUTE-2',
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'estimated_duration_minutes' => 45,
            'distance_km' => 12.5,
            'base_price' => 2000,
            'description' => 'Test route',
            'is_active' => true,
        ]);

        $route->stops()->attach([
            $origin->id => ['sequence' => 1],
            $destination->id => ['sequence' => 2],
        ]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => null,
            'operator_id' => $operator->id,
            'travel_date' => today(),
            'departure_time' => '08:00:00',
            'arrival_time' => '10:00:00',
            'price' => 2000,
            'status' => 'scheduled',
            'leg_direction' => 'forward',
            'leg_number' => 1,
        ]);

        $response = $this->actingAs($passenger)->get('/passenger/search?origin_stop_id=' . $requestedOrigin->id . '&destination_stop_id=' . $requestedDestination->id . '&travel_date=' . today()->toDateString() . '&seats=1');

        $response->assertOk();
        $response->assertViewHas('schedules', function ($schedules) use ($schedule) {
            return $schedules->contains('id', $schedule->id);
        });
    }
}
