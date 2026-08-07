<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->string('right_to_work_checked')->nullable()->after('visa_notes');
            $table->date('right_to_work_checked_date')->nullable()->after('right_to_work_checked');
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->string('right_to_work_checked')->nullable()->after('visa_notes');
            $table->date('right_to_work_checked_date')->nullable()->after('right_to_work_checked');
        });
    }

    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['right_to_work_checked', 'right_to_work_checked_date']);
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->dropColumn(['right_to_work_checked', 'right_to_work_checked_date']);
        });
    }
};
