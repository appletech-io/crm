<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->boolean('uses_bookings')->default(true)->after('industry_id');
        });

        // IT already has no Booking-based feature anywhere in the app
        // (ItDashboard/ItReports/ItPlacementsOverview are all built purely
        // on the Vacancy/Application/Placement pipeline) — every existing
        // company_industry row for it is backfilled to match that reality,
        // rather than leaving Bookings/Run Payroll/Timesheets visible (but
        // empty) until a site admin happens to flip this manually.
        DB::table('company_industry')
            ->join('industries', 'industries.id', '=', 'company_industry.industry_id')
            ->where('industries.slug', 'it')
            ->update(['company_industry.uses_bookings' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->dropColumn('uses_bookings');
        });
    }
};
