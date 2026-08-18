<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('leg_direction', 10)->default('forward')->after('status');
            $table->unsignedSmallInteger('leg_number')->default(1)->after('leg_direction');
            $table->timestamp('arrived_at')->nullable()->after('ended_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('leg_number')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('leg_number');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['leg_direction', 'leg_number', 'arrived_at']);
        });
    }
};
