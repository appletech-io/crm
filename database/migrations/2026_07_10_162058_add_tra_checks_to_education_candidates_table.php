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
            $table->date('trn_issue_date')->nullable()->after('trn_number');
            $table->date('safeguarding_certified_date')->nullable()->after('visa_notes');
            $table->string('prevent_training_completed')->nullable()->after('safeguarding_certified_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'trn_issue_date',
                'safeguarding_certified_date',
                'prevent_training_completed',
            ]);
        });
    }
};
