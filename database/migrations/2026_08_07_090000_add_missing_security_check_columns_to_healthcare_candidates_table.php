<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Healthcare vetting wizard's Security Checks step has always read
     * and written overseas_police_clearance_check and
     * overseas_police_clearance_check_date (see
     * HealthcareVettingSteps::securityChecks()), but — unlike
     * education_candidates — healthcare_candidates never actually got these
     * columns added. Saving that step for a candidate who has lived overseas
     * for 6+ months fails outright as a result.
     */
    public function up(): void
    {
        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->string('overseas_police_clearance_check')->nullable()->after('compliance_completed_by');
            $table->date('overseas_police_clearance_check_date')->nullable()->after('overseas_police_clearance_check');
        });
    }

    public function down(): void
    {
        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'overseas_police_clearance_check',
                'overseas_police_clearance_check_date',
            ]);
        });
    }
};
