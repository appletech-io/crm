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
            $table->string('retired_early')->nullable()->after('reasonable_accommodations');
            $table->string('retired_early_medical_grounds')->nullable()->after('retired_early');
            $table->string('dismissed_from_relevant_position')->nullable()->after('retired_early_medical_grounds');
            $table->text('dismissal_details')->nullable()->after('dismissed_from_relevant_position');
            $table->string('subject_to_disciplinary_action')->nullable()->after('dismissal_details');
            $table->text('disciplinary_action_details')->nullable()->after('subject_to_disciplinary_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'retired_early',
                'retired_early_medical_grounds',
                'dismissed_from_relevant_position',
                'dismissal_details',
                'subject_to_disciplinary_action',
                'disciplinary_action_details',
            ]);
        });
    }
};
