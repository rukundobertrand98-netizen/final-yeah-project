<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('role', 20)->default('passenger')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('operator_approved_at')->nullable()->after('is_active');
            $table->string('national_id', 30)->nullable()->after('operator_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'is_active', 'operator_approved_at', 'national_id']);
        });
    }
};
