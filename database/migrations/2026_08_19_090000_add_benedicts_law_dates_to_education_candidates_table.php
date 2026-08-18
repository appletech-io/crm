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
            $table->date('benedicts_law_issue_date')->nullable()->after('safeguarding_expiry_date');
            $table->date('benedicts_law_expiry_date')->nullable()->after('benedicts_law_issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['benedicts_law_issue_date', 'benedicts_law_expiry_date']);
        });
    }
};
