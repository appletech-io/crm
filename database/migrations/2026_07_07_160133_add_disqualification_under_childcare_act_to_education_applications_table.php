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
        Schema::table('education_applications', function (Blueprint $table) {
            $table->string('childcare_act_guidance_read')->nullable()->after('working_time_regulations_accepted_at');
            $table->text('childcare_act_guidance_read_details')->nullable()->after('childcare_act_guidance_read');
            $table->string('childcare_act_no_disqualification_reasons')->nullable()->after('childcare_act_guidance_read_details');
            $table->text('childcare_act_no_disqualification_reasons_details')->nullable()->after('childcare_act_no_disqualification_reasons');
            $table->string('childcare_act_will_notify_changes')->nullable()->after('childcare_act_no_disqualification_reasons_details');
            $table->text('childcare_act_will_notify_changes_details')->nullable()->after('childcare_act_will_notify_changes');
            $table->timestamp('disqualification_under_childcare_act_completed_at')->nullable()->after('childcare_act_will_notify_changes_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->dropColumn([
                'childcare_act_guidance_read',
                'childcare_act_guidance_read_details',
                'childcare_act_no_disqualification_reasons',
                'childcare_act_no_disqualification_reasons_details',
                'childcare_act_will_notify_changes',
                'childcare_act_will_notify_changes_details',
                'disqualification_under_childcare_act_completed_at',
            ]);
        });
    }
};
