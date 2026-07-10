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
            $table->string('barred_list_check')->nullable()->after('compliance_completed_by');
            $table->date('barred_list_check_date')->nullable()->after('barred_list_check');
            $table->string('overseas_police_clearance_check')->nullable()->after('barred_list_check_date');
            $table->date('overseas_police_clearance_check_date')->nullable()->after('overseas_police_clearance_check');
            $table->date('visa_issue_date')->nullable()->after('overseas_police_clearance_check_date');
            $table->date('visa_expiry_date')->nullable()->after('visa_issue_date');
            $table->text('visa_notes')->nullable()->after('visa_expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'barred_list_check',
                'barred_list_check_date',
                'overseas_police_clearance_check',
                'overseas_police_clearance_check_date',
                'visa_issue_date',
                'visa_expiry_date',
                'visa_notes',
            ]);
        });
    }
};
