<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->foreignId('origin_stop_id')->constrained('stops');
            $table->foreignId('destination_stop_id')->constrained('stops');
            $table->unsignedInteger('estimated_duration_minutes')->default(45);
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('minutes_from_start')->default(0);
            $table->unique(['route_id', 'stop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
