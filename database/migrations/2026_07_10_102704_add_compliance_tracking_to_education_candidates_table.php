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
            $table->unsignedTinyInteger('compliance_step')->nullable()->after('qualification_id');
            $table->timestamp('compliance_completed_at')->nullable()->after('compliance_step');
            $table->foreignId('compliance_completed_by')->nullable()->after('compliance_completed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compliance_completed_by');
            $table->dropColumn(['compliance_step', 'compliance_completed_at']);
        });
    }
};
