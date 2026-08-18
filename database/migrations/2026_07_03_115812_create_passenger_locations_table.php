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
        Schema::create('passenger_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable(); // GPS accuracy in meters
            $table->string('address')->nullable(); // Reverse geocoded address
            $table->boolean('is_active')->default(true);
            $table->timestamp('location_time');
            $table->string('device_info')->nullable(); // Browser/device info
            $table->timestamps();
            
            $table->index(['user_id', 'location_time']);
            $table->index(['user_id', 'is_active']);
            $table->index('location_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_locations');
    }
};
