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
        Schema::table('company_industry', function (Blueprint $table) {
            // Opt-in, unlike uses_bookings/uses_perm — this gates brand-new
            // booking-form complexity (Sleep-In/Waking Night shift types)
            // that must stay invisible everywhere until a site admin
            // deliberately turns it on for one company+industry.
            $table->boolean('complex_booking')->default(false)->after('uses_perm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->dropColumn('complex_booking');
        });
    }
};
