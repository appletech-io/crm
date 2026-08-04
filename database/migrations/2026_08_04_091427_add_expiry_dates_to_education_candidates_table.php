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
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->date('dbs_expiry_date')->nullable()->after('dbs_checked_date');
            $table->date('safeguarding_expiry_date')->nullable()->after('safeguarding_certified_date');
            $table->date('right_to_work_expiry_date')->nullable()->after('visa_expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['dbs_expiry_date', 'safeguarding_expiry_date', 'right_to_work_expiry_date']);
        });
    }
};
