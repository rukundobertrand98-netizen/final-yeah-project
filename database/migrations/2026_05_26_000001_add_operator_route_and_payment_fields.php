<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable()->after('distance_km');
            $table->json('map_path')->nullable()->after('description');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_phone', 20)->nullable()->after('currency');
            $table->timestamp('checked_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_phone', 'checked_at']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'map_path']);
        });
    }
};
