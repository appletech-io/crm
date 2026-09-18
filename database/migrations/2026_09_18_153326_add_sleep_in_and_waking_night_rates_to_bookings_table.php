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
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('sleep_in_rate')->nullable()->after('half_day_charge_rate');
            $table->unsignedInteger('sleep_in_charge_rate')->nullable()->after('sleep_in_rate');
            $table->unsignedInteger('waking_night_rate')->nullable()->after('sleep_in_charge_rate');
            $table->unsignedInteger('waking_night_charge_rate')->nullable()->after('waking_night_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['sleep_in_rate', 'sleep_in_charge_rate', 'waking_night_rate', 'waking_night_charge_rate']);
        });
    }
};
