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
            // Opt-in, same as complex_booking — care logging is a per-shift
            // compliance requirement that only some company+industry
            // combinations (e.g. a healthcare sector under CQC-style rules)
            // need, so it must stay off until deliberately switched on.
            $table->boolean('care_logging')->default(false)->after('complex_booking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->dropColumn('care_logging');
        });
    }
};
