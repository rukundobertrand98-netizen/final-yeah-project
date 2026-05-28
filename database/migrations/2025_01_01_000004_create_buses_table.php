<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('plate_number', 20)->unique();
            $table->string('fleet_number', 20)->nullable();
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->unsignedSmallInteger('rows')->default(10);
            $table->unsignedSmallInteger('seats_per_row')->default(4);
            $table->string('model')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
