<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'KBS Admin',
            'email' => 'admin@kbs.rw',
            'phone' => '250788000001',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'operator_approved_at' => now(),
        ]);

        $operator = User::create([
            'name' => 'KBS Operations',
            'email' => 'operator@kbs.rw',
            'phone' => '250788000002',
            'password' => Hash::make('password'),
            'role' => UserRole::Operator,
            'operator_approved_at' => now(),
        ]);

        $driver = User::create([
            'name' => 'Jean Bosco',
            'email' => 'driver@kbs.rw',
            'phone' => '250788000003',
            'password' => Hash::make('password'),
            'role' => UserRole::Driver,
        ]);

        User::create([
            'name' => 'Marie Uwase',
            'email' => 'passenger@kbs.rw',
            'phone' => '250788000004',
            'password' => Hash::make('password'),
            'role' => UserRole::Passenger,
        ]);

        $stops = [
            ['name' => 'Nyabugogo Bus Park', 'code' => 'NYB', 'latitude' => -1.9396, 'longitude' => 30.0444],
            ['name' => 'Kimironko Market', 'code' => 'KIM', 'latitude' => -1.9592, 'longitude' => 30.1045],
            ['name' => 'Remera Taxi Park', 'code' => 'REM', 'latitude' => -1.9495, 'longitude' => 30.1126],
            ['name' => 'Kacyiru Roundabout', 'code' => 'KAC', 'latitude' => -1.9361, 'longitude' => 30.0823],
            ['name' => 'CBD - CHIC', 'code' => 'CBD', 'latitude' => -1.9441, 'longitude' => 30.0619],
            ['name' => 'Gikondo Magerwa', 'code' => 'GIK', 'latitude' => -1.9702, 'longitude' => 30.0821],
            ['name' => 'Sonatubes', 'code' => 'SON', 'latitude' => -1.9785, 'longitude' => 30.1098],
            ['name' => 'Kicukiro Centre', 'code' => 'KIC', 'latitude' => -1.9898, 'longitude' => 30.1124],
        ];

        $stopModels = [];
        foreach ($stops as $stop) {
            $stopModels[$stop['code']] = Stop::create([...$stop, 'district' => 'Kigali']);
        }

        $route1 = Route::create([
            'operator_id' => $operator->id,
            'name' => 'Nyabugogo — Kimironko',
            'code' => 'KBS-01',
            'origin_stop_id' => $stopModels['NYB']->id,
            'destination_stop_id' => $stopModels['KIM']->id,
            'estimated_duration_minutes' => 50,
            'distance_km' => 12.5,
            'base_price' => 600,
            'description' => 'Main Kigali corridor via Remera',
        ]);

        foreach (['NYB', 'CBD', 'REM', 'KIM'] as $i => $code) {
            $route1->stops()->attach($stopModels[$code]->id, [
                'sequence' => $i + 1,
                'minutes_from_start' => $i * 15,
            ]);
        }

        $route2 = Route::create([
            'operator_id' => $operator->id,
            'name' => 'Nyabugogo — Kicukiro',
            'code' => 'KBS-02',
            'origin_stop_id' => $stopModels['NYB']->id,
            'destination_stop_id' => $stopModels['KIC']->id,
            'estimated_duration_minutes' => 45,
            'distance_km' => 10.2,
            'base_price' => 700,
        ]);

        foreach (['NYB', 'GIK', 'SON', 'KIC'] as $i => $code) {
            $route2->stops()->attach($stopModels[$code]->id, [
                'sequence' => $i + 1,
                'minutes_from_start' => $i * 12,
            ]);
        }

        $bus1 = Bus::create([
            'operator_id' => $operator->id,
            'plate_number' => 'RAB 100 K',
            'fleet_number' => 'KBS-101',
            'capacity' => 40,
            'rows' => 10,
            'seats_per_row' => 4,
            'model' => 'Yutong Coaster',
        ]);

        $bus2 = Bus::create([
            'operator_id' => $operator->id,
            'plate_number' => 'RAB 200 K',
            'fleet_number' => 'KBS-102',
            'capacity' => 40,
            'rows' => 10,
            'seats_per_row' => 4,
            'model' => 'Toyota Coaster',
        ]);

        foreach ([$route1, $route2] as $index => $route) {
            Schedule::create([
                'route_id' => $route->id,
                'bus_id' => $index === 0 ? $bus1->id : $bus2->id,
                'driver_id' => $driver->id,
                'operator_id' => $operator->id,
                'travel_date' => now()->toDateString(),
                'departure_time' => '07:00:00',
                'arrival_time' => '08:00:00',
                'price' => 600,
                'status' => 'scheduled',
            ]);
            Schedule::create([
                'route_id' => $route->id,
                'bus_id' => $index === 0 ? $bus1->id : $bus2->id,
                'driver_id' => $driver->id,
                'operator_id' => $operator->id,
                'travel_date' => now()->addDay()->toDateString(),
                'departure_time' => '17:30:00',
                'price' => 700,
                'status' => 'scheduled',
            ]);
        }

        $this->command->info('KBS seed complete.');
        $this->command->table(['Role', 'Email', 'Password'], [
            ['Admin', 'admin@kbs.rw', 'password'],
            ['Operator', 'operator@kbs.rw', 'password'],
            ['Driver', 'driver@kbs.rw', 'password'],
            ['Passenger', 'passenger@kbs.rw', 'password'],
        ]);
    }
}
