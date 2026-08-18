<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_histories', function (Blueprint $table) {
            $table->id();
            $table->string('original_booking_reference');
            $table->string('passenger_name');
            $table->string('passenger_email')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->string('route_name');
            $table->string('route_code');
            $table->string('origin_stop_name');
            $table->string('destination_stop_name');
            $table->decimal('amount', 10, 2);
            $table->string('seat_number')->nullable();
            $table->string('status'); // confirmed, cancelled, completed, etc.
            $table->datetime('travel_date');
            $table->time('departure_time');
            $table->string('bus_plate_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('operator_name');
            $table->string('deletion_reason')->nullable(); // why route was deleted
            $table->timestamp('original_booking_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            
            $table->index(['passenger_email', 'passenger_phone']);
            $table->index('route_code');
            $table->index('travel_date');
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_histories');
    }
};
